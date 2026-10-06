<?php

namespace App\Filament\Resources\Users\Schemas;

use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.resources.users.sections.identity.heading'))
                    ->description(__('app.resources.users.sections.identity.description'))
                    ->icon(Phosphor::IdentificationCard)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.resources.users.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('app.resources.users.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Fieldset::make()
                            ->schema([
                                TextInput::make('password')
                                    ->label(__('app.resources.users.fields.password'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->minLength(8)
                                    ->confirmed(fn (string $operation): bool => $operation === 'create'),

                                TextInput::make('password_confirmation')
                                    ->label(__('app.resources.users.fields.password_confirmation'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->minLength(8)
                                    ->visible(fn (string $operation): bool => $operation === 'create'),
                            ]),
                    ]),
            ]);
    }
}
