<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\EncounterDiagnosisResource;

class ListEncounterDiagnoses extends ListRecords
{
    protected static string $resource = EncounterDiagnosisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
