<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\CreateEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\EditEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\ListEncounters;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\ViewEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\ClinicalNotesRelationManager;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\EncounterParticipantsRelationManager;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\ServiceRequestsRelationManager;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\VitalSignsRelationManager;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Tables\EncountersTable;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\NavigationGroup;

class EncounterResource extends Resource
{
    protected static ?string $model = Encounter::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $recordTitleAttribute = 'encounter_number';

    protected static ?string $slug = 'clinical/encounters';

    public static function form(Schema $schema): Schema
    {
        return EncounterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EncounterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EncountersTable::configure($table);
    }

    public static function getRelations(): array
    {
        $relations = [
            EncounterParticipantsRelationManager::class,
            VitalSignsRelationManager::class,
            ClinicalNotesRelationManager::class,
            ServiceRequestsRelationManager::class,
        ];

        if (class_exists(\Modules\Billing\Filament\RelationManagers\EncounterInvoicesRelationManager::class)) {
            $relations[] = \Modules\Billing\Filament\RelationManagers\EncounterInvoicesRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEncounters::route('/'),
            'create' => CreateEncounter::route('/create'),
            'view' => ViewEncounter::route('/{record}'),
            'edit' => EditEncounter::route('/{record}/edit'),
        ];
    }
}
