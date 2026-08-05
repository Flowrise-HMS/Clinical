<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas\EncounterDiagnosisForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables\EncounterDiagnosesTable;

class DiagnosesRelationManager extends RelationManager
{
    protected static string $relationship = 'diagnoses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...EncounterDiagnosisForm::itemElements(),
                Hidden::make('patient_id')
                    ->default(fn (): ?string => $this->getOwnerRecord()?->patient_id),
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
