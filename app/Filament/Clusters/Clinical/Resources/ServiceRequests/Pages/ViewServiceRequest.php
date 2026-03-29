<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;
}
