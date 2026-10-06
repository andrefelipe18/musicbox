<?php

use App\Enums\UserReleaseStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ApexChart;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\RatingsChart;
use App\Filament\Widgets\UserGrowthChart;
use App\Models\Admin;
use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Date;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Date::setTestNow('2026-03-31 12:00:00');

    $this->actingAs(Admin::factory()->create(), 'admin');
});

afterEach(function (): void {
    Date::setTestNow();
});

/**
 * @return array<string, string>
 */
function dashboardFilters(string $startDate, string $endDate): array
{
    return ['startDate' => $startDate, 'endDate' => $endDate];
}

/**
 * Runs a chart widget outside Livewire, since `pageFilters` is a reactive prop
 * that only the dashboard page may set.
 *
 * @param  class-string<ApexChart>  $widgetClass
 * @param  array<string, string>  $filters
 * @return array<string, mixed>
 */
function chartOptions(string $widgetClass, ?array $filters): array
{
    $widget = new $widgetClass;
    $widget->pageFilters = $filters;
    $widget->mount();

    return $widget->options;
}

/**
 * @param  array<string, string>  $filters
 * @return array<Stat>
 */
function periodStats(array $filters): array
{
    $widget = new PeriodStatsOverview;
    $widget->pageFilters = $filters;

    return $widget->getStats();
}

/**
 * Builds a listened release with a rating for a given day.
 */
function ratedRelease(User $user, int $rating, string $listenedAt): UserRelease
{
    return UserRelease::factory()->create([
        'user_id' => $user->getKey(),
        'release_id' => Release::factory(),
        'status' => UserReleaseStatus::Listened,
        'rating' => $rating,
        'listened_at' => $listenedAt,
    ]);
}

it('opens the filters action on the last thirty days', function (): void {
    livewire(Dashboard::class)
        ->mountAction('filter')
        ->assertActionDataSet(dashboardFilters('2026-03-02', '2026-03-31'));
});

it('applies the filters chosen on the dashboard', function (): void {
    livewire(Dashboard::class)
        ->callAction('filter', dashboardFilters('2026-03-02', '2026-03-10'))
        ->assertHasNoActionErrors()
        ->assertSet('filters', dashboardFilters('2026-03-02', '2026-03-10'));
});

it('rejects an end date before the start date', function (): void {
    livewire(Dashboard::class)
        ->callAction('filter', dashboardFilters('2026-03-20', '2026-03-10'))
        ->assertHasActionErrors(['endDate' => 'after_or_equal']);
});

it('rejects a period that runs into the future', function (): void {
    livewire(Dashboard::class)
        ->callAction('filter', dashboardFilters('2026-04-01', '2026-04-10'))
        ->assertHasActionErrors(['startDate']);
});

it('counts new users per day inside the filtered period', function (): void {
    User::factory()->count(2)->create(['created_at' => '2026-03-10 09:00:00']);
    User::factory()->create(['created_at' => '2026-03-20 09:00:00']);
    User::factory()->create(['created_at' => '2026-03-05 09:00:00']);

    $options = chartOptions(UserGrowthChart::class, dashboardFilters('2026-03-09', '2026-03-11'));

    expect($options['xaxis']['categories'])->toBe(['09/03', '10/03', '11/03'])
        ->and($options['series'][0]['data'])->toBe([0, 2, 0]);
});

it('falls back to the last thirty days before any filter is applied', function (): void {
    $options = chartOptions(UserGrowthChart::class, null);

    expect($options['xaxis']['categories'])->toHaveCount(30)
        ->and($options['xaxis']['categories'][0])->toBe('02/03')
        ->and($options['xaxis']['categories'][29])->toBe('31/03');
});

it('shows the year when the selected period crosses calendar years', function (): void {
    $options = chartOptions(UserGrowthChart::class, dashboardFilters('2025-12-30', '2026-01-02'));

    expect($options['xaxis']['categories'])->toBe(['30/12/2025', '31/12/2025', '01/01/2026', '02/01/2026']);
});

it('counts ratings and averages them per listened day', function (): void {
    $user = User::factory()->create();

    ratedRelease($user, 4, '2026-03-10');
    ratedRelease($user, 2, '2026-03-10');
    ratedRelease($user, 5, '2026-03-12');

    $options = chartOptions(RatingsChart::class, dashboardFilters('2026-03-10', '2026-03-11'));

    expect($options['series'][0]['data'])->toBe([2, 0])
        ->and($options['series'][1]['data'])->toBe([3.0, null]);
});

it('ignores ratings without a listened day', function (): void {
    UserRelease::factory()->create([
        'status' => UserReleaseStatus::Listened,
        'rating' => 5,
    ]);

    $options = chartOptions(RatingsChart::class, dashboardFilters('2026-03-30', '2026-03-31'));

    expect($options['series'][0]['data'])->toBe([0, 0]);
});

it('summarises the totals of the filtered period', function (): void {
    User::factory()->create(['created_at' => '2026-03-10 09:00:00']);
    User::factory()->create(['created_at' => '2026-03-30 09:00:00']);

    $user = User::factory()->create();

    ratedRelease($user, 5, '2026-03-10');
    ratedRelease($user, 5, '2026-03-10');

    $stats = periodStats(dashboardFilters('2026-03-01', '2026-03-20'));

    expect($stats[0]->getValue())->toBe(1)
        ->and($stats[1]->getValue())->toBe(2);
});

it('renders dashboard charts without the plugin section wrapper', function (): void {
    livewire(UserGrowthChart::class)
        ->assertSee(__('app.dashboard.user_growth'))
        ->assertDontSee('filament-apex-charts-section');

    livewire(RatingsChart::class)
        ->assertSee(__('app.dashboard.ratings_per_day'))
        ->assertDontSee('filament-apex-charts-section');
});

it('gives Apex charts the full width of their widget', function (): void {
    expect(chartOptions(UserGrowthChart::class, null)['chart']['width'])->toBe('100%')
        ->and(chartOptions(RatingsChart::class, null)['chart']['width'])->toBe('100%');
});

it('keeps the stats full width and the charts side by side', function (): void {
    $statsColumns = new ReflectionMethod(PeriodStatsOverview::class, 'getColumns');

    expect((new PeriodStatsOverview)->getColumnSpan())->toBe('full')
        ->and($statsColumns->invoke(new PeriodStatsOverview))->toBe(2)
        ->and((new UserGrowthChart)->getColumnSpan())->toBe(1)
        ->and((new RatingsChart)->getColumnSpan())->toBe(1)
        ->and(livewire(Dashboard::class)->instance()->getColumns())->toBe(2);
});

it('keeps polling off on every dashboard widget', function (): void {
    foreach ([UserGrowthChart::class, RatingsChart::class, PeriodStatsOverview::class] as $widgetClass) {
        $widget = new $widgetClass;

        expect((new ReflectionMethod($widget, 'getPollingInterval'))->invoke($widget))
            ->toBeNull();
    }
});
