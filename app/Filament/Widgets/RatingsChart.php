<?php

namespace App\Filament\Widgets;

use App\Models\UserRelease;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Ratings handed in per day and the average score of those ratings, grouped by
 * the day the release was listened to.
 */
class RatingsChart extends ApexChart
{
    protected static ?string $chartId = 'ratingsChart';

    protected static ?string $heading = null;

    protected static ?int $sort = 3;

    protected static ?int $contentHeight = 320;

    protected function getHeading(): null|string|Htmlable|View
    {
        return __('app.dashboard.ratings_per_day');
    }

    /**
     * @return array{count: array<int, int>, average: array<int, float|null>}
     */
    protected function ratingsPerDay(): array
    {
        $counts = [];
        $averages = [];

        $perDay = $this->withinRange(UserRelease::query(), 'listened_at')
            ->whereNotNull('rating')
            ->whereNotNull('listened_at')
            ->select(DB::raw('date(listened_at) as day, count(*) as total, avg(rating) as average'))
            ->groupBy('day')
            ->get();

        foreach ($perDay as $row) {
            $day = (string) $row->getAttribute('day');

            $counts[$day] = (int) $row->getAttribute('total');
            $averages[$day] = round((float) $row->getAttribute('average'), 2);
        }

        return [
            'count' => $this->alignToRange($counts, 0),
            'average' => $this->alignToRange($averages),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $perDay = $this->ratingsPerDay();

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => false],
            ],
            'series' => [
                [
                    'name' => __('app.dashboard.ratings'),
                    'type' => 'column',
                    'data' => $perDay['count'],
                ],
                [
                    'name' => __('app.dashboard.average'),
                    'type' => 'line',
                    'data' => $perDay['average'],
                ],
            ],
            'xaxis' => [
                'categories' => array_keys($this->daysInRange()),
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
                    ],
                ],
            ],
            'yaxis' => [
                [
                    'seriesName' => __('app.dashboard.ratings'),
                    'labels' => [
                        'style' => ['fontFamily' => 'inherit'],
                    ],
                ],
                [
                    'opposite' => true,
                    'seriesName' => __('app.dashboard.average'),
                    'min' => 0,
                    'max' => 5,
                    'labels' => [
                        'style' => ['fontFamily' => 'inherit'],
                    ],
                ],
            ],
            'colors' => ['#7c3aed', '#22d3ee'],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['width' => [2, 3]],
            'legend' => ['position' => 'top'],
        ];
    }
}
