<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\CarePlanResource;

class ListCarePlans extends ListRecords
{
    protected static string $resource = CarePlanResource::class;
}
