<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\ClinicalNote;

class Notes extends Page
{
    use HasPatientContext;

    protected static ?string $title = 'Notes';

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static ?string $slug = 'patient/{patient}/clinical-notes';

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::FileText;

    public Collection|array|null $clinicalNotes = [];

    protected string $view = 'clinical::clinical.workspace.pages.notes';

    protected static bool $shouldRegisterNavigation = false;

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
        $this->loadNotesData();
    }

    protected function getHeaderActions(): array
    {
        return app(PatientActions::class)->forPatient($this->currentPatient)->timelineSubQuickActions();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    protected function loadNotesData(): void
    {
        if ($this->currentPatient) {
            $this->clinicalNotes = ClinicalNote::query()
                ->where('patient_id', $this->currentPatient->id)
                ->when($this->currentEncounter?->id, fn ($q) => $q->where('encounter_id', $this->currentEncounter->id))
                ->with(['author'])
                ->orderBy('created_at', 'desc')
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
            ? 'Notes - '.$this->currentPatient->full_name
            : 'Notes';
    }
}
