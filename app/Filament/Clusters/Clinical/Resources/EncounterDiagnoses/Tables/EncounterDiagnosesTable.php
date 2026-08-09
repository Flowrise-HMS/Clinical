<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas\EncounterDiagnosisInfolist;
use Modules\Core\Filament\Support\DateRangeFilter;

class EncounterDiagnosesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters())
            ->recordActions(self::recordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, SelectFilter|Filter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('type')
                ->options(DiagnosisType::class),
            SelectFilter::make('certainty')
                ->options(DiagnosisCertainty::class),
            DateRangeFilter::make('created_at', 'Recorded'),
        ];
    }

    /**
     * Actions are authorized explicitly because widgets, unlike resource pages and relation
     * managers, do not resolve a default policy response for their table actions.
     *
     * @return array<int, ViewAction|EditAction>
     */
    public static function recordActions(bool $includeMutations = true): array
    {
        $actions = [
            ViewAction::make()
                ->authorize('view')
                ->schema(fn (Schema $schema): Schema => EncounterDiagnosisInfolist::configure($schema))
                ->slideOver(),
        ];

        if ($includeMutations) {
            $actions[] = EditAction::make()->authorize('update');
        }

        return $actions;
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
