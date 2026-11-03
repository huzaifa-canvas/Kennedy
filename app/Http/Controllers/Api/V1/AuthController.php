<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponses;

    /** Access tokens stop working after this long without a refresh. */
    private const TOKEN_TTL_DAYS = 60;

    /** Failed logins allowed per email+IP before a lockout. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    /** Wrong OTP submissions allowed per email+IP before a cool-off. */
    private const MAX_OTP_ATTEMPTS = 6;

    private const OTP_LOCKOUT_SECONDS = 600;

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'timezone' => $data['timezone'] ?? 'UTC',
                // role and status are never taken from the request.
                'role' => 'user',
                'status' => 'active',
            ]);
        });

        return $this->created([
            'user' => new UserResource($user),
            'token' => $this->issueToken($user, $data['device_name'] ?? null),
            'token_type' => 'Bearer',
        ], 'Account created successfully.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            return $this->fail(
                'Too many login attempts. Please try again in ' . RateLimiter::availableIn($key) . ' seconds.',
                429
            );
        }

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            // Same message either way, so the endpoint cannot be used to
            // discover which email addresses are registered.
            return $this->fail('The provided credentials are incorrect.', 401);
        }

        if (! $user->isActive()) {
            return $this->fail('This account has been suspended. Please contact support.', 403);
        }

        RateLimiter::clear($key);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return $this->ok([
            'user' => new UserResource($user),
            'token' => $this->issueToken($user, $request->validated('device_name')),
            'token_type' => 'Bearer',
        ], 'Logged in successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out successfully.');
    }

    /** Drop every token, e.g. after a password change on another device. */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->ok(null, 'Signed out of all devices.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $user->forceFill(['password' => Hash::make($request->validated('password'))])->save();

        // Invalidate every other session; keep the caller signed in.
        $user->tokens()->whereKeyNot($currentToken->getKey())->delete();

        return $this->ok(null, 'Password updated successfully.');
    }

    // ═════════════════════════════════════════════════════════════════
    // PASSWORD RESET BY ONE-TIME CODE
    //
    //   1. forgot-password  → emails a 6-digit code
    //   2. verify-otp       → trades the code for a single-use reset token
    //   3. reset-password   → sets the new password with that token
    // ═════════════════════════════════════════════════════════════════

    /** Step 1 — send the code. */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        $payload = [
            'expires_in_minutes' => PasswordResetOtp::TTL_MINUTES,
            'otp_length' => 6,
        ];

        // Always the same shape of reply, so the endpoint cannot be used to
        // discover which addresses are registered.
        if ($user && $user->isActive()) {
            ['otp' => $otp] = PasswordResetOtp::issueFor($email);

            $user->notify(new PasswordResetOtpNotification($otp));

            // Development convenience only — see shouldExposeOtp().
            if ($this->shouldExposeOtp()) {
                $payload['otp'] = $otp;
                $payload['otp_is_exposed_because'] = 'No real mailer is configured yet. '
                    . 'Set MAIL_MAILER to smtp and this field disappears on its own.';
            }
        }

        return $this->ok($payload, 'If that email is registered, a reset code is on its way.');
    }

    /** Step 2 — check the code, hand back a reset token. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_OTP_ATTEMPTS)) {
            return $this->fail(
                'Too many attempts. Please try again in ' . RateLimiter::availableIn($key) . ' seconds.',
                429
            );
        }

        $email = $request->validated('email');
        $record = PasswordResetOtp::where('email', $email)->first();

        // One message for every failure, so nothing is revealed about which
        // part was wrong — or whether the address exists at all.
        $invalid = fn () => $this->fail('The code is invalid or has expired.', 422);

        if (! $record || $record->isExpired() || $record->isLockedOut()) {
            RateLimiter::hit($key, self::OTP_LOCKOUT_SECONDS);

            return $invalid();
        }

        if (! $record->matches($request->validated('otp'))) {
            $record->increment('attempts');
            RateLimiter::hit($key, self::OTP_LOCKOUT_SECONDS);

            return $invalid();
        }

        RateLimiter::clear($key);

        return $this->ok([
            'reset_token' => $record->issueResetToken(),
            'expires_in_minutes' => PasswordResetOtp::RESET_TOKEN_TTL_MINUTES,
        ], 'Code verified. You can now set a new password.');
    }

    /** Step 3 — set the new password. */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $record = PasswordResetOtp::where('email', $email)->first();

        if (! $record
            || ! $record->verified_at
            || $record->isExpired()
            || ! $record->matchesResetToken($request->validated('reset_token'))
        ) {
            return $this->fail('This reset request is invalid or has expired. Please start again.', 422);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->fail('This reset request is invalid or has expired. Please start again.', 422);
        }

        DB::transaction(function () use ($user, $request, $record) {
            $user->forceFill([
                'password' => Hash::make($request->validated('password')),
                'remember_token' => Str::random(60),
            ])->save();

            // A reset signs every device out.
            $user->tokens()->delete();

            $record->delete();
        });

        return $this->ok(null, 'Password reset successfully. You can now sign in.');
    }

    /**
     * Whether the code may be returned in the API response.
     *
     * True only while no real mailer is configured, and never in production.
     * Setting MAIL_MAILER to smtp turns this off by itself — no code change.
     */
    private function shouldExposeOtp(): bool
    {
        return ! app()->isProduction()
            && in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function issueToken(User $user, ?string $deviceName = null): string
    {
        $name = $deviceName ? Str::limit($deviceName, 80, '') : 'mobile-app';

        return $user->createToken(
            $name,
            ['app:use'],
            now()->addDays(self::TOKEN_TTL_DAYS)
        )->plainTextToken;
    }
}
