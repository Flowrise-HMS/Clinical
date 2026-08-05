<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses;

use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages\CreateEncounterDiagnosis;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages\EditEncounterDiagnosis;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages\ListEncounterDiagnoses;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas\EncounterDiagnosisForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables\EncounterDiagnosesTable;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Core\Enums\NavigationGroup;

class EncounterDiagnosisResource extends Resource
{
    protected static ?string $model = EncounterDiagnosis::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $slug = 'clinical/encounter-diagnoses';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('encounter_id')
                ->label('Encounter')
                ->relationship('encounter', 'encounter_number')
                ->searchable()
                ->preload()
                ->required(),
            ...EncounterDiagnosisForm::itemElements(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return EncounterDiagnosesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEncounterDiagnoses::route('/'),
            'create' => CreateEncounterDiagnosis::route('/create'),
            'edit' => EditEncounterDiagnosis::route('/{record}/edit'),
        ];
    }
}
