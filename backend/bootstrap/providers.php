<?php

use App\Providers\AppServiceProvider;
use App\Providers\ConfigureFilamentServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    ConfigureFilamentServiceProvider::class,
    AdminPanelProvider::class,
];
