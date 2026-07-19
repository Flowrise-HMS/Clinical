<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages;

use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListServiceRequestActivities extends ListActivitiesBySubject
{
    protected static string $resource = ServiceRequestResource::class;
}
