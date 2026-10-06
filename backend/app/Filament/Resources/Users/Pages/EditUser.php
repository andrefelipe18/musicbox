<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<Action>
     */
    protected function getGroupedHeaderActions(): array
    {
        return [
            ...parent::getGroupedHeaderActions(),
            DeleteAction::make(),
        ];
    }
}
