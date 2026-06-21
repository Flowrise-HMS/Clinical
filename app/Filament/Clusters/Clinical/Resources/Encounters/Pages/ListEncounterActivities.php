<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListEncounterActivities extends ListActivities
{
    protected static string $resource = EncounterResource::class;
}
