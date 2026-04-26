<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\CreateServiceRequest;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\EditServiceRequest;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\ListServiceRequests;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\ViewServiceRequest;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\RelationManagers\RequestItemsRelationManager;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Tables\ServiceRequestsTable;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Enums\NavigationGroup;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $recordTitleAttribute = 'request_number';

    protected static ?string $slug = 'clinical/service-requests';

    public static function form(Schema $schema): Schema
    {
        return ServiceRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RequestItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'create' => CreateServiceRequest::route('/create'),
            'view' => ViewServiceRequest::route('/{record}'),
            'edit' => EditServiceRequest::route('/{record}/edit'),
        ];
    }
}
