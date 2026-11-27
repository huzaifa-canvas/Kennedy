<?php

namespace App\Support;

use Closure;
use DateTimeZone;

/**
 * User timezones arrive either as an IANA name ("Asia/Karachi") or, from
 * devices that only expose an offset, as "UTC+5", "GMT+05:30", "+0500" etc.
 * Offsets are stored as "+05:00", which PHP / Carbon accept as a timezone.
 */
final class Timezone
{
    /** "+HH:MM" as stored. */
    private const OFFSET_PATTERN = '/^[+-]\d{2}:\d{2}$/';

    /** Optional UTC/GMT prefix, a sign, hours, optional minutes (with or without a colon). */
    private const INPUT_OFFSET_PATTERN = '/^(?:(?:UTC|GMT)\s*)?([+-])\s*(\d{1,2})(?::?(\d{2}))?$/i';

    /** Real-world offsets run from UTC-12:00 to UTC+14:00. */
    private const MIN_OFFSET_MINUTES = -12 * 60;

    private const MAX_OFFSET_MINUTES = 14 * 60;

    /**
     * Turn an offset into "+05:00" (a zero offset becomes "UTC"). Anything
     * else is returned trimmed, so an IANA name passes through untouched
     * and an invalid value is left for validation to reject.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Some keyboards / locales produce a Unicode minus sign.
        $value = trim(str_replace("\u{2212}", '-', $value));

        if (in_array(strtoupper($value), ['UTC', 'GMT', 'Z'], true)) {
            return 'UTC';
        }

        if (! preg_match(self::INPUT_OFFSET_PATTERN, $value, $m)) {
            return self::canonicalName($value);
        }

        $hours = (int) $m[2];
        $minutes = (int) ($m[3] ?? 0);

        if ($minutes > 59) {
            return $value;
        }

        $total = ($hours * 60 + $minutes) * ($m[1] === '-' ? -1 : 1);

        if ($total < self::MIN_OFFSET_MINUTES || $total > self::MAX_OFFSET_MINUTES) {
            return $value;
        }

        if ($total === 0) {
            return 'UTC';
        }

        return sprintf('%s%02d:%02d', $total < 0 ? '-' : '+', intdiv(abs($total), 60), abs($total) % 60);
    }

    /** "asia/karachi" -> "Asia/Karachi"; unknown names are returned unchanged. */
    private static function canonicalName(string $value): string
    {
        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            if (strcasecmp($identifier, $value) === 0) {
                return $identifier;
            }
        }

        return $value;
    }

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        return preg_match(self::OFFSET_PATTERN, $value) === 1
            || in_array($value, DateTimeZone::listIdentifiers(), true);
    }

    /** Validation rule; run it on a value already passed through normalize(). */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! self::isValid($value)) {
                $fail('The timezone must be a name like America/New_York or an offset like UTC-5.');
            }
        };
    }
}
