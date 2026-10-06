<?php

namespace App\Filament\Auth;

use App\Models\Admin;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.shared.login-layout';

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getHeading()
            : __('app.login.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : __('app.login.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...(app()->isLocal() ? [$this->getQuickLoginFormComponent()] : []),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getQuickLoginFormComponent(): Component
    {
        return Select::make('quick_login')
            ->label(__('app.login.quick_login'))
            ->placeholder(__('app.login.select_admin'))
            ->options(Admin::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Admin $admin): array => [
                    $admin->getKey() => "{$admin->name} ({$admin->email})",
                ])
                ->all())
            ->live()
            ->afterStateUpdated(function (?string $state): void {
                if (! $state || ! ($admin = Admin::query()->find($state))) {
                    return;
                }

                Auth::guard('admin')->login($admin);
                session()->regenerate();

                $this->redirectIntended(Filament::getUrl());
            });
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->placeholder(__('app.login.email_placeholder'));
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->placeholder(__('app.login.password'));
    }
}
