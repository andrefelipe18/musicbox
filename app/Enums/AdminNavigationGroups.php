<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdminNavigationGroups: int implements HasLabel
{
    case CATALOG = 1;
    case USERS = 2;
    case SETTINGS = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::CATALOG => __('Catalog'),
            self::USERS => __('Users'),
            self::SETTINGS => __('Settings'),
        };
    }
}
