<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\CreateAllergy;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\EditAllergy;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\ListAllergies;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Schemas\AllergyForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Tables\AllergiesTable;
use Modules\Clinical\Models\Allergy;
use Modules\Core\Enums\NavigationGroup;

class AllergyResource extends Resource
{
    protected static ?string $model = Allergy::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $navigationLabel = 'Allergies';

    protected static ?string $slug = 'clinical/allergies';

    public static function form(Schema $schema): Schema
    {
        return AllergyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AllergiesTable::configure($table);
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
            'index' => ListAllergies::route('/'),
            'create' => CreateAllergy::route('/create'),
            'edit' => EditAllergy::route('/{record}/edit'),
        ];
    }
}
