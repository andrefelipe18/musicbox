<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Daily sign-up curve for the filtered period.
 */
class UserGrowthChart extends ApexChart
{
    protected static ?string $chartId = 'userGrowthChart';

    protected static ?string $heading = null;

    protected static ?int $sort = 2;

    protected static ?int $contentHeight = 320;

    protected function getHeading(): null|string|Htmlable|View
    {
        return __('app.dashboard.user_growth');
    }

    /**
     * New users per day, aligned to the filtered range.
     *
     * @return array<int, int>
     */
    protected function newUsersPerDay(): array
    {
        $perDay = $this->withinRange(User::query(), 'created_at')
            ->select(DB::raw('date(created_at) as day, count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        /** @var array<int, int> */
        return $this->alignToRange($perDay->all(), 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'chart' => [
                'type' => 'area',
                'height' => 300,
                'width' => '100%',
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => false],
            ],
            'series' => [
                [
                    'name' => __('app.dashboard.new_users'),
                    'data' => $this->newUsersPerDay(),
                ],
            ],
            'xaxis' => [
                'categories' => $this->dayLabels(),
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => ['fontFamily' => 'inherit'],
                ],
            ],
            'colors' => ['#7c3aed'],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['curve' => 'smooth', 'width' => 3],
            'fill' => [
                'type' => 'gradient',
                'gradient' => ['opacityFrom' => 0.45, 'opacityTo' => 0.05],
            ],
            'legend' => ['show' => false],
        ];
    }
}
