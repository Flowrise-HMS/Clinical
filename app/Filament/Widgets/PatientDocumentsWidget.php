<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\RelationManagers\PatientDocumentsRelationManager;
use Modules\Patient\Models\Patient;

/**
 * Profile-page wrapper around the Patient module's Documents relation manager,
 * so patient-level and encounter-level files share one table (and one set of
 * upload/preview/download actions) everywhere they are shown.
 */
class PatientDocumentsWidget extends Widget
{
    protected string $view = 'clinical::widgets.patient-documents-widget';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $patientId = null;

    public function getPatient(): ?Patient
    {
        if (blank($this->patientId)) {
            return null;
        }

        return Patient::query()->withoutGlobalScopes()->whereNull('deleted_at')->find($this->patientId);
    }

    public function relationManagerClass(): string
    {
        return PatientDocumentsRelationManager::class;
    }
}
