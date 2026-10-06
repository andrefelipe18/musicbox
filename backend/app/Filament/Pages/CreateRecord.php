<?php

namespace App\Filament\Pages;

use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

abstract class CreateRecord extends BaseCreateRecord
{
    public function canCreateAnother(): bool
    {
        return false;
    }

    public function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make($this->getGroupedHeaderActions())
                ->button(),

            $this->getSubmitFormAction()
                ->submit(null)
                ->action($this->getSubmitFormLivewireMethodName())
                ->icon(Phosphor::Plus),

            ...$this->getAdditionalHeaderActions(),
        ];
    }

    /**
     * Header actions rendered inside the overflow action group.
     *
     * @return array<Action>
     */
    protected function getGroupedHeaderActions(): array
    {
        return [$this->getCancelFormAction()];
    }

    /**
     * Header actions rendered outside the action group, after the submit action.
     *
     * @return array<Action>
     */
    protected function getAdditionalHeaderActions(): array
    {
        return [];
    }
}
