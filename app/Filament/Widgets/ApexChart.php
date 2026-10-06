<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardDateRange;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Base for the dashboard charts: polling off, options recomputed on every
 * render so a page filter change redraws the chart, and day buckets aligned to
 * the filtered range.
 */
abstract class ApexChart extends ApexChartWidget
{
    use InteractsWithDashboardDateRange;

    /**
     * Polling is off on every dashboard widget.
     */
    protected ?string $pollingInterval = null;

    /**
     * The plugin computes `$options` once on mount, so a filter change would
     * leave the chart drawing stale data. Livewire calls this before each
     * render, which redraws only when the options actually changed.
     */
    public function rendering(): void
    {
        $this->updateOptions();
    }

    /**
     * Lines a `Y-m-d` keyed aggregate up with the days of the filtered range.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, mixed>
     */
    protected function alignToRange(array $values, mixed $default = null): array
    {
        return array_map(
            fn (string $day): mixed => $values[$day] ?? $default,
            array_keys($this->daysInRange()),
        );
    }
}
