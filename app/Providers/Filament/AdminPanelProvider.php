<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\AvatarProviders\BlobatarProvider;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\RatingsChart;
use App\Filament\Widgets\UserGrowthChart;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filafly\Icons\Phosphor\PhosphorIcons;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('MusicBox')
            ->brandLogo(asset('musicbox-logo.png'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.ico'))
            ->maxContentWidth(Width::SevenExtraLarge)
            ->sidebarWidth('14rem')
            ->authGuard('admin')
            ->login(Login::class)
            ->topbar(false)
            ->userMenuItems([
                Action::make('apiDocumentation')
                    ->label(__('app.user_menu.api_documentation'))
                    ->icon(Phosphor::BookOpen)
                    ->url('/docs/api')
                    ->openUrlInNewTab(),
            ])
            ->colors([
                'primary' => '#7c3aed',
                'danger' => Color::Rose,
                'gray' => Color::Zinc,
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->viteTheme('resources/css/panel.css')
            ->renderHook(PanelsRenderHook::CONTENT_BEFORE, fn (): View => view('filament.shared.main-decorations'))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): View => view('filament.shared.main-decorations'))
            ->defaultAvatarProvider(BlobatarProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PeriodStatsOverview::class,
                UserGrowthChart::class,
                RatingsChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(PhosphorIcons::make()
                ->duotone()
                ->overrideAlias(ActionsIconAlias::ACTION_GROUP, Phosphor::DotsThreeVertical),
            )
            ->plugin(FilamentApexChartsPlugin::make())
            ->bootUsing(fn () => CreateAction::configureUsing(
                fn (CreateAction $action) => $action->createAnother(false),
            ));
    }
}
