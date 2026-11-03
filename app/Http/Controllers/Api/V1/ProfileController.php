<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\UpdateSettingsRequest;
use App\Http\Resources\BillResource;
use App\Http\Resources\IncomeSourceResource;
use App\Http\Resources\SavingsJarResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponses;

    public function show(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            $previous = $user->avatar;
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        // Changing the email means it is no longer a verified address.
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->fill($data)->save();

        return $this->ok(new UserResource($user->fresh()), 'Profile updated successfully.');
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'notifications_enabled' => (bool) $user->notifications_enabled,
            'currency' => $user->currency,
            'allowance_view' => $user->allowance_view,
            'timezone' => $user->timezone,
        ]);
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return $this->ok([
            'notifications_enabled' => (bool) $user->notifications_enabled,
            'currency' => $user->currency,
            'allowance_view' => $user->allowance_view,
            'timezone' => $user->timezone,
        ], 'Settings updated successfully.');
    }

    /** Everything the user has put into the app, for the Export Data screen. */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'incomeSources',
            'bills.category',
            'savingsJars',
        ]);

        return $this->ok([
            'exported_at' => now()->toIso8601String(),
            'profile' => new UserResource($user),
            'income_sources' => IncomeSourceResource::collection($user->incomeSources),
            'bills' => BillResource::collection($user->bills),
            'savings_jars' => SavingsJarResource::collection($user->savingsJars),
            'transactions' => TransactionResource::collection(
                $user->transactions()->with('category')->orderByDesc('occurred_on')->limit(5000)->get()
            ),
        ]);
    }

    /**
     * Delete Account. Password is re-checked because this wipes everything.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.current_password' => 'Your password is incorrect.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->notifications()->delete();
            $user->devices()->delete();
            $user->delete();
        });

        return $this->ok(null, 'Your account has been deleted.');
    }
}
