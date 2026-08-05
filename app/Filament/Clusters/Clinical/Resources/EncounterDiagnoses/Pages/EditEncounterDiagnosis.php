<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\EncounterDiagnosisResource;

class EditEncounterDiagnosis extends EditRecord
{
    protected static string $resource = EncounterDiagnosisResource::class;
}
