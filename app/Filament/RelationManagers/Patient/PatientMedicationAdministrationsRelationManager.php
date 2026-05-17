<?php

namespace Modules\Clinical\Filament\RelationManagers\Patient;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\MedicationAdministrations\Schemas\MedicationAdministrationInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\MedicationAdministrations\Tables\MedicationAdministrationsTable;

class PatientMedicationAdministrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'medicationAdministrations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Medication Administrations');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return MedicationAdministrationsTable::configure($table)
            ->recordActions([
                ViewAction::make()
                    ->schema(fn (Schema $schema) => MedicationAdministrationInfolist::configure($schema))
                    ->slideOver(),
            ]);
    }
}
