<?php

namespace App\Providers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\ServiceProvider;

class ConfigureFilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Section::configureUsing(static fn (Section $component) => $component->columnSpanFull());
        Tabs::configureUsing(static fn (Tabs $component) => $component->columnSpanFull());
        Fieldset::configureUsing(static fn (Fieldset $component) => $component->columnSpanFull());

        DateTimePicker::configureUsing(static fn (DateTimePicker $component) => $component->native(false));
        MorphToSelect::configureUsing(static fn (MorphToSelect $component) => $component->native(false));
        Select::configureUsing(static fn (Select $component) => $component->native(false));
        SelectConstraint::configureUsing(static fn (SelectConstraint $component) => $component->native(false));
        IsRelatedToOperator::configureUsing(static fn (IsRelatedToOperator $component) => $component->native(false));
        SelectColumn::configureUsing(static fn (SelectColumn $component) => $component->native(false));
        SelectFilter::configureUsing(static fn (SelectFilter $component) => $component->native(false));
    }
}
