<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetOtp extends Model
{
    /** How long a code stays usable. */
    public const TTL_MINUTES = 10;

    /** Wrong guesses allowed before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    /** How long the verified reset token lasts. */
    public const RESET_TOKEN_TTL_MINUTES = 15;

    protected $fillable = [
        'email', 'otp_hash', 'reset_token_hash', 'attempts', 'expires_at', 'verified_at',
    ];

    protected $hidden = ['otp_hash', 'reset_token_hash'];

    protected $attributes = ['attempts' => 0];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Issue a fresh code, replacing any previous one for this address.
     *
     * @return array{record: self, otp: string}
     */
    public static function issueFor(string $email): array
    {
        // random_int is cryptographically secure; rand() is not.
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = static::updateOrCreate(
            ['email' => $email],
            [
                'otp_hash' => Hash::make($otp),
                'reset_token_hash' => null,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'verified_at' => null,
            ]
        );

        return ['record' => $record, 'otp' => $otp];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLockedOut(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function matches(string $otp): bool
    {
        return Hash::check($otp, $this->otp_hash);
    }

    /** Swap the verified code for a single-use reset token. */
    public function issueResetToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'reset_token_hash' => hash('sha256', $token),
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
            'attempts' => 0,
        ])->save();

        return $token;
    }

    public function matchesResetToken(string $token): bool
    {
        return $this->reset_token_hash
            && hash_equals($this->reset_token_hash, hash('sha256', $token));
    }
}
