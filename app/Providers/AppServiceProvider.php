<?php

namespace App\Providers;

use App\Music\Contracts\MusicCatalogProvider;
use App\Music\YouTubeMusic\YouTubeMusicProvider;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MusicCatalogProvider::class, YouTubeMusicProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): View => view('filament.hooks.sidebar-scrollbar'),
        );

        $this->configureDefaults();
        Gate::define('viewApiDocs', fn (): bool => Auth::guard('admin')->check());
        RateLimiter::for('auth', function (Request $request): Limit {
            $email = $request->input('email');
            $identity = is_string($email) ? Str::lower($email) : 'invalid-email';

            return Limit::perMinute(8)->by($identity.'|'.$request->ip());
        });
        RateLimiter::for('music-search', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('music-sync', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id.'|'.$request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
