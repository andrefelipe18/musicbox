<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
