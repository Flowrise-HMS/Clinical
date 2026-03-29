<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;
}
