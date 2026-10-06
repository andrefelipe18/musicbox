<?php

namespace App\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

/**
 * Admin dashboard: the period filters live in the header and every widget reads
 * them through `InteractsWithPageFilters`.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label(__('app.dashboard.filters'))
                ->modalHeading(__('app.dashboard.filters_heading'))
                ->schema($this->filtersSchema()),
        ];
    }

    /**
     * @return array<DatePicker>
     */
    protected function filtersSchema(): array
    {
        return [
            DatePicker::make('startDate')
                ->label(__('app.dashboard.start_date'))
                ->default(CarbonImmutable::today()->subDays(29))
                ->required()
                ->maxDate(CarbonImmutable::today()),

            DatePicker::make('endDate')
                ->label(__('app.dashboard.end_date'))
                ->default(CarbonImmutable::today())
                ->required()
                ->afterOrEqual('startDate'),
        ];
    }
}
