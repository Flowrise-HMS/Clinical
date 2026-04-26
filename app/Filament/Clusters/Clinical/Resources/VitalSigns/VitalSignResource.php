<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\CreateVitalSign;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\EditVitalSign;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\ListVitalSigns;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\ViewVitalSign;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Tables\VitalSignsTable;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Enums\NavigationGroup;

class VitalSignResource extends Resource
{
    protected static ?string $model = VitalSign::class;

    protected static string|BackedEnum|null $navigationIcon = null;
     protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;
    protected static ?string $cluster = ClinicalCluster::class;

    public static function form(Schema $schema): Schema
    {
        return VitalSignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VitalSignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VitalSignsTable::configure($table);
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
            'index' => ListVitalSigns::route('/'),
            'create' => CreateVitalSign::route('/create'),
            'view' => ViewVitalSign::route('/{record}'),
            'edit' => EditVitalSign::route('/{record}/edit'),
        ];
    }
}
