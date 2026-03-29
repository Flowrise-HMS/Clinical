<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;

class ViewEncounter extends ViewRecord
{
    protected static string $resource = EncounterResource::class;
}
