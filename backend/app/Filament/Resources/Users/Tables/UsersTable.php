<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.resources.users.fields.name'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('app.resources.users.fields.email'))
                    ->searchable(),

                TextColumn::make('email_verified_at')
                    ->label(__('app.resources.users.fields.email_verified_at'))
                    ->badge()
                    ->formatStateUsing(fn (): string => __('app.resources.users.verified'))
                    ->color('success')
                    ->placeholder(__('app.resources.users.unverified'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('app.resources.users.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('app.resources.users.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
