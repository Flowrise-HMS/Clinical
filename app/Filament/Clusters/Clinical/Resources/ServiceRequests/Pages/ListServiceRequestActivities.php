<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages;

use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListServiceRequestActivities extends ListActivities
{
    protected static string $resource = ServiceRequestResource::class;
}
