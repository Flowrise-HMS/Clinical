<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages;

use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListVitalSignActivities extends ListActivitiesBySubject
{
    protected static string $resource = VitalSignResource::class;
}
