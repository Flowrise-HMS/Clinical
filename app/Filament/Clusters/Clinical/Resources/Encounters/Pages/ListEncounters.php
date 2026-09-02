<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Clinical\Filament\Exports\EncounterExporter;
use Modules\Core\Filament\Support\SuperAdminExportAction;

class ListEncounters extends ListRecords
{
    protected static string $resource = EncounterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuperAdminExportAction::make(EncounterExporter::class),
            CreateAction::make(),
        ];
    }
}
