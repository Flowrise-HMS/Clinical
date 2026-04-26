<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\VitalSign;

class Vitals extends Page
{
    use HasPatientContext;

    protected static ?string $title = 'Vitals';

    protected static ?string $navigationLabel = 'Vitals';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static ?string $slug = 'patient/{patient}/vitals';

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::Heart;

    public Collection|array|null $vitalsHistory = [];

    protected string $view = 'clinical::clinical.workspace.pages.vitals';

    protected static bool $shouldRegisterNavigation = false;

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
        $this->loadVitalsData();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    protected function getHeaderActions(): array
    {
        return app(PatientActions::class)->forPatient($this->currentPatient)->timelineSubQuickActions();
    }

    protected function loadVitalsData(): void
    {
        if ($this->currentPatient) {
            $this->vitalsHistory = VitalSign::query()
                ->where('patient_id', $this->currentPatient->id)
                ->when($this->currentEncounter?->id, fn ($q) => $q->where('encounter_id', $this->currentEncounter->id))
                ->orderBy('recorded_at', 'desc')
                ->limit(50)
                ->get();
        }
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return $this->currentPatient
            ? 'Vitals - '.$this->currentPatient->full_name
            : 'Vitals';
    }
}
