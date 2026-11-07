<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Inclusive date range expressed in a user's timezone, convertible to UTC bounds for queries.
 */
final class DateRange
{
    public const PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This week',
        'last_week' => 'Last week',
        'past_2_weeks' => 'Past two weeks',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_year' => 'Last year',
    ];

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function preset(string $preset, string $timezone, int $weekStart = 1): self
    {
        $now = CarbonImmutable::now($timezone);
        $weekStartDay = $now->startOfWeek($weekStart);

        [$from, $to] = match ($preset) {
            'today' => [$now, $now],
            'yesterday' => [$now->subDay(), $now->subDay()],
            'last_week' => [$weekStartDay->subWeek(), $weekStartDay->subDay()],
            'past_2_weeks' => [$weekStartDay->subWeek(), $weekStartDay->addDays(6)],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            'last_year' => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
            default => [$weekStartDay, $weekStartDay->addDays(6)],
        };

        return new self($from->startOfDay(), $to->endOfDay());
    }

    public static function fromStrings(string $from, string $to, string $timezone): self
    {
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timezone)->endOfDay();

        return $end->lessThan($start) ? new self($end->startOfDay(), $start->endOfDay()) : new self($start, $end);
    }

    public function utcFrom(): CarbonImmutable
    {
        return $this->from->utc();
    }

    public function utcTo(): CarbonImmutable
    {
        return $this->to->utc();
    }

    /** @return list<CarbonImmutable> each day (start of day) in the range */
    public function days(): array
    {
        $days = [];
        for ($day = $this->from->startOfDay(); $day->lessThanOrEqualTo($this->to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    public function dayCount(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('M j, Y');
        }

        return $this->from->format('M j, Y').' – '.$this->to->format('M j, Y');
    }
}
