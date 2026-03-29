<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Tables\EncountersTable;

class ListEncounters extends ListRecords
{
    protected static string $resource = EncounterResource::class;

}
