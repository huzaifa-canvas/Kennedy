<?php

namespace App\Support;

/**
 * The app shows one error line, so every validation message is folded into
 * the response `message` instead of a per-field `errors` object:
 *   "The email has already been taken, The password field must be at least 8 characters."
 */
final class ValidationMessage
{
    /** @param array<string, array<int, string>|string> $errors field => messages */
    public static function from(array $errors): string
    {
        $messages = collect($errors)
            ->flatten()
            ->map(fn ($message) => rtrim(trim((string) $message), '.'))
            ->filter()
            ->unique()
            ->values();

        return $messages->isEmpty()
            ? 'The given data was invalid.'
            : $messages->implode(', ') . '.';
    }
}
