<?php

namespace App\Filament\Widgets\Concerns;

use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the date range picked on the dashboard filters into a zero-filled
 * list of days, so every widget shares the same axis and the same defaults.
 */
trait InteractsWithDashboardDateRange
{
    use InteractsWithPageFilters;

    /**
     * Falls back to the last thirty days until the filters have been applied
     * once. Mirrors the DatePicker default on the dashboard page.
     */
    protected function startDate(): CarbonImmutable
    {
        $start = $this->pageFilters['startDate'] ?? null;

        return $start === null
            ? CarbonImmutable::today()->subDays(29)
            : CarbonImmutable::parse($start)->startOfDay();
    }

    protected function endDate(): CarbonImmutable
    {
        $end = $this->pageFilters['endDate'] ?? null;

        return $end === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse($end)->endOfDay();
    }

    /**
     * Every day of the filtered range, keyed by `Y-m-d`, ascending.
     *
     * @return array<string, CarbonImmutable>
     */
    protected function daysInRange(): array
    {
        $days = [];

        for ($day = $this->startDate(); $day->lessThanOrEqualTo($this->endDate()); $day = $day->addDay()) {
            $days[$day->toDateString()] = $day;
        }

        return $days;
    }

    /**
     * Constrains a query to the filtered range on the given date column.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    /**
     * Constrains a query to the filtered range on the given date column.
     *
     * `listened_at` is a DATE column stored as `Y-m-d`, so a `whereBetween`
     * against a `Y-m-d H:i:s` bound silently drops every row on a text
     * comparison. `whereDate` normalises both sides.
     *
     * ponytail: wrapping the column defeats an index on `created_at`; add a
     * plain timestamp range if a user count ever gets slow.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    protected function withinRange(Builder $query, string $column): Builder
    {
        return $query
            ->whereDate($column, '>=', $this->startDate())
            ->whereDate($column, '<=', $this->endDate());
    }
}
