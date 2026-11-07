<?php

namespace App\Support;

/**
 * Parses a wall-clock time typed by the user ("9", "930", "09:30", "9:30pm", "21.30").
 */
final class ClockTime
{
    /**
     * @return array{0:int,1:int}|null [hour, minute]
     */
    public static function parse(string $input): ?array
    {
        $value = strtolower(preg_replace('/\s+/', '', $input));

        if (! preg_match('/^(\d{1,2})(?:[:.]?(\d{2}))?(am|pm|a|p)?$/', $value, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) ($m[2] ?? 0);
        $meridiem = $m[3] ?? '';

        if ($meridiem !== '') {
            if ($hour < 1 || $hour > 12) {
                return null;
            }
            $hour = $hour % 12 + (str_starts_with($meridiem, 'p') ? 12 : 0);
        }

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return [$hour, $minute];
    }
}
