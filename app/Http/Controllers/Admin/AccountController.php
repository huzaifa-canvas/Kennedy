<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The signed-in admin's own profile — reached from the user dropdown.
 * Managing *other* users lives in UserController.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('content.pages.profile-edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\+\-\s\(\)]+$/'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $previous = $user->avatar;
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        } else {
            unset($data['avatar']);
        }

        $data['email'] = strtolower($data['email']);

        // A new address is no longer a verified one.
        if ($data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->fill($data)->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'Your new password must be different from the current one.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        // Mobile tokens issued before the change should not outlive it.
        $user->tokens()->delete();

        // Keep this browser signed in, invalidate any other session.
        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully.');
    }
}
