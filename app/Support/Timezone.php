<?php

namespace App\Support;

use DateTimeZone;

/**
 * Normalises timezone names reported by browsers. Some report legacy aliases
 * (e.g. Chrome in India reports "Asia/Calcutta"), which aren't in PHP's canonical list.
 */
final class Timezone
{
    /** Common legacy aliases → canonical IANA names. */
    private const ALIASES = [
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Dacca' => 'Asia/Dhaka',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar',
        'Europe/Kiev' => 'Europe/Kyiv',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'America/Indianapolis' => 'America/Indiana/Indianapolis',
        'Pacific/Truk' => 'Pacific/Chuuk',
        'Atlantic/Faeroe' => 'Atlantic/Faroe',
    ];

    /** Canonical name for any valid input, or UTC when the input is unusable. */
    public static function normalize(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if (isset(self::ALIASES[$timezone])) {
            return self::ALIASES[$timezone];
        }

        if (in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return $timezone;
        }

        // Other legacy-but-valid names (e.g. "US/Eastern") still work in PHP; keep them.
        return in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true) ? $timezone : 'UTC';
    }

    /** @return list<string> canonical identifiers, plus $current if it is a legacy name */
    public static function options(?string $current = null): array
    {
        $list = DateTimeZone::listIdentifiers();

        return $current && ! in_array($current, $list, true) ? [$current, ...$list] : $list;
    }
}
