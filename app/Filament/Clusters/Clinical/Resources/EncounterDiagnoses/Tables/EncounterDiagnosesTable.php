<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;

class EncounterDiagnosesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters([
                SelectFilter::make('type')
                    ->options(DiagnosisType::class),
                SelectFilter::make('certainty')
                    ->options(DiagnosisCertainty::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, TextColumn|IconColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->sortable(),
            TextColumn::make('certainty')
                ->label('Certainty')
                ->badge()
                ->sortable(),
            IconColumn::make('is_new_case')
                ->label('New')
                ->boolean(),
            TextColumn::make('icd_code')
                ->label('Code')
                ->placeholder('—')
                ->searchable(),
            TextColumn::make('description')
                ->label('Diagnosis')
                ->wrap()
                ->searchable(),
            TextColumn::make('notes')
                ->label('Notes')
                ->limit(40)
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('orderedBy.name')
                ->label('Recorded by')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('created_at')
                ->label('Recorded')
                ->dateTime()
                ->sortable(),
        ];
    }
}
