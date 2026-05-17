<?php

namespace Modules\Clinical\Filament\RelationManagers\Patient;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Tables\EncountersTable;

class PatientEncountersRelationManager extends RelationManager
{
    protected static string $relationship = 'encounters';

    public function form(Schema $schema): Schema
    {
        return EncounterForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return EncountersTable::configure($table);
    }
}
