<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\ServiceRequest;

class Orders extends Page
{
    use HasPatientContext;

    protected static ?string $title = 'Orders';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static ?string $slug = 'patient/{patient}/clinical-services';

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::ClipboardList;

    public Collection|array|null $serviceRequests;

    protected string $view = 'clinical::clinical.workspace.pages.orders';

    protected static bool $shouldRegisterNavigation = false;

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
        $this->loadOrdersData();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    protected function getHeaderActions(): array
    {
        return app(PatientActions::class)->forPatient($this->currentPatient)->timelineSubQuickActions();
    }

    protected function loadOrdersData(): void
    {
        if ($this->currentPatient) {
            $this->serviceRequests = ServiceRequest::query()
                ->where('patient_id', $this->currentPatient->id)
                ->when($this->currentEncounter?->id, fn ($q) => $q->where('encounter_id', $this->currentEncounter->id))
                ->with(['items.service', 'orderedBy'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
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
            ? 'Orders - '.$this->currentPatient->full_name
            : 'Orders';
    }
}
