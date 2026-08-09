<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ListCarePlans;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ViewCarePlan;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Schemas\CarePlanInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlansTable;
use Modules\Clinical\Models\CarePlan;
use Modules\Core\Enums\NavigationGroup;

class CarePlanResource extends Resource
{
    protected static ?string $model = CarePlan::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $navigationLabel = 'Care Plans';

    protected static ?string $slug = 'clinical/care-plans';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['patient', 'author']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CarePlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CarePlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCarePlans::route('/'),
            'view' => ViewCarePlan::route('/{record}'),
        ];
    }
}
