<?php

namespace App\Support;

/**
 * Formats and parses tracked durations (stored as seconds).
 */
final class Duration
{
    /** 3725 => "1:02:05" (hours are not capped at 24). */
    public static function format(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /** 3725 => "1.03" decimal hours. */
    public static function decimal(int $seconds, int $precision = 2): string
    {
        return number_format($seconds / 3600, $precision, '.', '');
    }

    /** 3725 => "1h 2m" (compact, for charts and tiles). */
    public static function short(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    /**
     * Parse user input into seconds. Accepts "1:30", "1:30:15", "1.5", "1,5", "2" (hours),
     * "130" / "0130" (HMM), "1h 30m 10s", "45m". Returns null when unparseable.
     */
    public static function parse(string $input): ?int
    {
        $value = strtolower(trim(str_replace(',', '.', $input)));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $value, $m)) {
            if ((int) $m[2] > 59 || (int) ($m[3] ?? 0) > 59) {
                return null;
            }

            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) ($m[3] ?? 0);
        }

        if (preg_match('/^\d+\.\d+$/', $value)) {
            return (int) round((float) $value * 3600);
        }

        if (preg_match('/^\d{1,2}$/', $value)) {
            return (int) $value * 3600;
        }

        if (preg_match('/^(\d{1,2})(\d{2})$/', $value, $m)) {
            return (int) $m[2] > 59 ? null : (int) $m[1] * 3600 + (int) $m[2] * 60;
        }

        if (preg_match('/^(?:(\d+(?:\.\d+)?)\s*h)?\s*(?:(\d+)\s*m)?\s*(?:(\d+)\s*s)?$/', $value, $m) && trim($value) !== '') {
            return (int) round((float) ($m[1] ?? 0) * 3600) + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
        }

        return null;
    }
}
