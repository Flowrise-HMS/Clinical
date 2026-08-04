<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Models\CarePlan;

class CarePlansTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('patient.full_name')
                ->label('Patient')
                ->searchable(),
            TextColumn::make('category')
                ->badge()
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('title')
                ->placeholder('Untitled care plan')
                ->searchable(),
            TextColumn::make('author.name')
                ->label('Author')
                ->sortable(),
            TextColumn::make('activated_at')
                ->label('Activated')
                ->dateTime()
                ->sortable()
                ->placeholder('Not activated'),
        ];
    }

    /**
     * Shared status / category / encounter columns for workspace widgets.
     *
     * @return array<int, TextColumn>
     */
    public static function workspaceCoreColumns(): array
    {
        return [
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('category')
                ->badge()
                ->sortable(),
            TextColumn::make('encounter.encounter_number')
                ->label('Encounter')
                ->placeholder('—'),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function recentWorkspaceColumns(): array
    {
        return [
            ...self::workspaceCoreColumns(),
            TextColumn::make('custodian.name')
                ->label('Custodian')
                ->placeholder('—'),
            TextColumn::make('updated_at')
                ->label('Updated')
                ->date()
                ->placeholder('—')
                ->sortable(),
        ];
    }

    /**
     * Columns for the ward list (patient column is added by the widget for Livewire actions).
     *
     * @return array<int, TextColumn>
     */
    public static function wardWorkspaceColumns(): array
    {
        return [
            ...self::workspaceCoreColumns(),
            TextColumn::make('custodian.name')
                ->label('Custodian')
                ->placeholder('—'),
            TextColumn::make('period_start')
                ->label('Period')
                ->date()
                ->placeholder('—'),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function previousWorkspaceColumns(): array
    {
        return [
            TextColumn::make('category')
                ->badge()
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('encounter.encounter_number')
                ->label('Encounter')
                ->placeholder('—'),
            TextColumn::make('closed_at')
                ->label('Closed')
                ->state(fn (CarePlan $record) => $record->completed_at ?? $record->revoked_at)
                ->date()
                ->placeholder('—'),
        ];
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('category')
                ->options(CarePlanCategory::class),
            SelectFilter::make('status')
                ->options(CarePlanStatus::class),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns(self::columns())
            ->filters(self::filters())
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
