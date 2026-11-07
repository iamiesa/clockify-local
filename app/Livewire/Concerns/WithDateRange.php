<?php

namespace App\Livewire\Concerns;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

/**
 * Preset / custom date range state shared by the dashboard and reports.
 */
trait WithDateRange
{
    #[Url(as: 'range')]
    public string $preset = 'this_week';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function bootWithDateRange(): void
    {
        if (! array_key_exists($this->preset, DateRange::PRESETS) && $this->preset !== 'custom') {
            $this->preset = 'this_week';
        }
    }

    public function mountWithDateRange(): void
    {
        if ($this->from === '' || $this->to === '' || $this->preset !== 'custom') {
            $range = $this->range();
            $this->from = $range->from->format('Y-m-d');
            $this->to = $range->to->format('Y-m-d');
        }
    }

    public function applyPreset(string $preset): void
    {
        if (! array_key_exists($preset, DateRange::PRESETS)) {
            return;
        }

        $this->preset = $preset;
        $range = $this->range();
        $this->from = $range->from->format('Y-m-d');
        $this->to = $range->to->format('Y-m-d');
    }

    public function applyCustomRange(): void
    {
        $this->preset = 'custom';
        $range = $this->range();
        $this->from = $range->from->format('Y-m-d');
        $this->to = $range->to->format('Y-m-d');
    }

    public function shiftRange(int $direction): void
    {
        $range = $this->range();
        $direction = $direction < 0 ? -1 : 1;
        $wholeMonths = $range->from->isSameDay($range->from->startOfMonth()) && $range->to->isSameDay($range->to->endOfMonth());

        if ($wholeMonths) {
            $months = (int) round($range->from->diffInMonths($range->to->addDay()));
            $from = $range->from->addMonthsNoOverflow($direction * max(1, $months));
            $to = $from->addMonthsNoOverflow(max(1, $months))->subDay();
        } else {
            $days = $range->dayCount();
            $from = $range->from->addDays($direction * $days);
            $to = $range->to->addDays($direction * $days);
        }

        $this->preset = 'custom';
        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
    }

    public function range(): DateRange
    {
        $user = auth()->user();

        if ($this->preset === 'custom' && $this->isValidDate($this->from) && $this->isValidDate($this->to)) {
            $range = DateRange::fromStrings($this->from, $this->to, $user->timezone);

            return $range->dayCount() > 366 * 3
                ? new DateRange($range->to->subYears(3)->startOfDay(), $range->to)
                : $range;
        }

        return DateRange::preset($this->preset === 'custom' ? 'this_week' : $this->preset, $user->timezone, $user->week_start);
    }

    public function rangeLabel(): string
    {
        return $this->preset !== 'custom' && isset(DateRange::PRESETS[$this->preset])
            ? DateRange::PRESETS[$this->preset]
            : $this->range()->label();
    }

    private function isValidDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && CarbonImmutable::hasFormat($value, 'Y-m-d');
    }
}
