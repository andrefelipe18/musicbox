<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardDateRange;
use App\Models\User;
use App\Models\UserRelease;
use Filament\Schemas\Components\Component;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Totals for the filtered period, read before the charts.
 */
class PeriodStatsOverview extends StatsOverviewWidget
{
    use InteractsWithDashboardDateRange;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    /**
     * Polling is off on every dashboard widget.
     */
    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('app.dashboard.period_summary');
    }

    public function getSectionContentComponent(): Component
    {
        return parent::getSectionContentComponent()
            ->extraAttributes(['class' => 'dashboard-period-stats']);
    }

    protected function getColumns(): int|array|null
    {
        return 2;
    }

    public function getStats(): array
    {
        return [
            Stat::make(
                label: __('app.dashboard.new_users'),
                value: $this->withinRange(User::query(), 'created_at')->count(),
            ),
            Stat::make(
                label: __('app.dashboard.ratings_recorded'),
                value: $this->ratedWithinRange()->count(),
            ),
        ];
    }

    /**
     * Ratings inside the period, keyed by the day the release was listened to.
     *
     * @return Builder<UserRelease>
     */
    protected function ratedWithinRange(): Builder
    {
        /** @var Builder<UserRelease> */
        return $this->withinRange(UserRelease::query(), 'listened_at')
            ->whereNotNull('rating')
            ->whereNotNull('listened_at');
    }
}
