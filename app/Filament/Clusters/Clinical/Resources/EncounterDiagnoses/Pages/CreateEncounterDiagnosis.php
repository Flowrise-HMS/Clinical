<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\EncounterDiagnosisResource;
use Modules\Clinical\Models\Encounter;

class CreateEncounterDiagnosis extends CreateRecord
{
    protected static string $resource = EncounterDiagnosisResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ordered_by'] = auth()->id();
        $data['patient_id'] = Encounter::query()
            ->whereKey($data['encounter_id'] ?? null)
            ->value('patient_id');

        return $data;
    }
}
