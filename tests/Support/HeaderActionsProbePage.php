<?php

namespace Tests\Support;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;

/**
 * Proves the header extension points of the app's EditRecord base page: an extending
 * page can push actions into the overflow group and outside of it independently.
 */
class HeaderActionsProbePage extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<Action>
     */
    protected function getGroupedHeaderActions(): array
    {
        return [
            ...parent::getGroupedHeaderActions(),
            Action::make('probeInsideGroup'),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getAdditionalHeaderActions(): array
    {
        return [Action::make('probeOutsideGroup')];
    }
}
