<?php

namespace Modules\Clinical\Filament\RelationManagers\Patient;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas\EncounterDiagnosisForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables\EncounterDiagnosesTable;

class PatientDiagnosesRelationManager extends RelationManager
{
    protected static string $relationship = 'diagnoses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('encounter_id')
                    ->label('Encounter')
                    ->relationship('encounter', 'encounter_number')
                    ->searchable()
                    ->preload()
                    ->required(),
                ...EncounterDiagnosisForm::itemElements(),
                Hidden::make('ordered_by')
                    ->default(fn (): ?int => auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return EncounterDiagnosesTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
