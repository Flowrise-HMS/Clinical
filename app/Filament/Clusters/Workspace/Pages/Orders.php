<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\RequestItemForm;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\ServiceRequest;

class Orders extends Page implements HasActions
{
    use HasPatientContext;
    use InteractsWithActions;

    protected static ?string $title = 'Orders';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static ?string $slug = 'patient/{patient}/clinical-services';

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::ClipboardList;

    public Collection|array|null $serviceRequests;

    protected string $view = 'clinical::clinical.workspace.pages.orders';

    protected static bool $shouldRegisterNavigation = false;

    protected ServiceRequestService $serviceRequestService;

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    protected function getHeaderActions(): array
    {
        return app(PatientActions::class)->forPatient($this->currentPatient)
            ->withEncounter($this->currentEncounter)
            ->timelineSubQuickActions();
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

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
        $this->serviceRequestService = app(ServiceRequestService::class);
        $this->loadOrdersData();
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

    public function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label('Add Item')
            ->icon('heroicon-m-plus')
            ->slideOver()
            ->schema(RequestItemForm::getItemFields())
            ->action(function (array $data, array $arguments) {
                $request = ServiceRequest::findOrFail($arguments['service_request_id']);

                if ($request->isCompleted() || $request->status?->value === 'cancelled') {
                    return;
                }

                $this->serviceRequestService->addItem(
                    $request,
                    $data['service_id'],
                    $data['service_variant_id'] ?? null,
                    $data['quantity'] ?? 1
                );

                $this->loadOrdersData();
            })
            ->successNotificationTitle('Item added to request');
    }
}
