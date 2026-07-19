<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListEncounterActivities extends ListActivitiesBySubject
{
    protected static string $resource = EncounterResource::class;
}
