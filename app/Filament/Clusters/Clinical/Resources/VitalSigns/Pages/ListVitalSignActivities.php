<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages;

use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListVitalSignActivities extends ListActivities
{
    protected static string $resource = VitalSignResource::class;
}
