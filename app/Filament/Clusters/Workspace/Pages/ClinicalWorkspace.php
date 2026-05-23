<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use Filament\Actions\Concerns\HasSchema;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Schemas\AllergyForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignForm;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Widgets\CriticalPatientsWidget;
use Modules\Clinical\Filament\Widgets\MyTasksWidget;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Models\Patient;

class ClinicalWorkspace extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $slug = '';

    protected static ?string $navigationLabel = 'Clinical Workspace';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-m-heart';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'clinical::clinical.workspace.clinical-workspace';

    #[Url]
    public ?string $patientId = null;

    public string $mode = 'home';

    public string $activeTab = '';

    public ?Patient $currentPatient = null;

    public ?Encounter $currentEncounter = null;

    public string $searchTerm = '';

    public array $searchResults = [];

    public string $consultationChiefComplaint = '';

    public string $consultationNotes = '';

    public array $diagnosisCodes = [];

    public string $diagnosisSearch = '';

    public array $vitalsData = [];

    public array $serviceRequestData = [];

    public array $allergyData = [];

    public ?string $selectedEncounterCoverage = null;

    public array $consultationData = [];

    public array $medicationData = [];

    public array $dischargeData = [];

    public array $referralData = [];

    protected ?ClinicalWorkspaceService $workspaceService = null;

    protected ?PatientSearchService $patientSearchService = null;

    protected ?VitalSignService $vitalSignService = null;

    protected ?ClinicalNoteService $clinicalNoteService = null;

    protected ?ServiceRequestService $serviceRequestService = null;

    protected ?EncounterService $encounterService = null;

    protected ?AllergyService $allergyService = null;

    public function boot(): void
    {
        $this->workspaceService ??= app(ClinicalWorkspaceService::class);
        $this->patientSearchService ??= app(PatientSearchService::class);
        $this->vitalSignService ??= app(VitalSignService::class);
        $this->clinicalNoteService ??= app(ClinicalNoteService::class);
        $this->serviceRequestService ??= app(ServiceRequestService::class);
        $this->encounterService ??= app(EncounterService::class);
        $this->allergyService ??= app(AllergyService::class);

        foreach ($this->registerForms() as $name => $schema) {
            $this->cacheSchema($name, $schema);
        }
    }

    public function mount(): void
    {
        if ($this->patientId) {
            $this->selectPatient($this->patientId);
        }
    }

    public function selectPatient(string $id): void
    {
        $this->patientId = $id;
        $this->mode = 'patient';
        $this->loadPatientContext();
        $this->setDefaultTab();
        $this->searchTerm = '';
        $this->searchResults = [];
    }

    #[On('select-patient')]
    public function onSelectPatient(string $id): void
    {
        $this->selectPatient($id);
    }

    public function clearPatient(): void
    {
        $this->patientId = null;
        $this->mode = 'home';
        $this->currentPatient = null;
        $this->currentEncounter = null;
        $this->activeTab = '';
        $this->resetFormStates();
    }

    protected function resetFormStates(): void
    {
        $this->consultationChiefComplaint = '';
        $this->consultationNotes = '';
        $this->consultationData = [];
        $this->diagnosisCodes = [];
        $this->diagnosisSearch = '';
        $this->vitalsData = [];
        $this->serviceRequestData = [];
        $this->allergyData = [];
        $this->medicationData = [];
        $this->dischargeData = [];
        $this->referralData = [];
        $this->selectedEncounterCoverage = null;
    }

    protected function loadPatientContext(): void
    {
        if (!$this->patientId) {
            return;
        }

        $this->currentPatient = Patient::with([
            'allergies',
            'activeEncounter',
            'latestEncounter',
            'latestVitals',
        ])->find($this->patientId);

        if ($this->currentPatient) {
            $this->workspaceService->setPatient($this->currentPatient);
            $this->currentEncounter = $this->currentPatient->activeEncounter ?? $this->currentPatient->latestEncounter;
        }
    }

    protected function setDefaultTab(): void
    {
        if ($this->activeTab) {
            return;
        }
        $this->activeTab = match ($this->getUserRoleKey()) {
            'nurse' => 'encounter',
            'lab' => 'pending-labs',
            default => 'vitals',
        };
    }

    public function updatedSearchTerm(): void
    {
        if (strlen($this->searchTerm) < 2) {
            $this->searchResults = [];
            return;
        }
        $this->searchResults = $this->patientSearchService
            ->search($this->searchTerm, 10)
            ->toArray();
    }

    #[Computed]
    public function userRole(): string
    {
        return $this->getUserRoleKey();
    }

    protected function getUserRoleKey(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'clinician';
        }

        $roleNames = $user->getRoleNames();

        foreach ($roleNames as $role) {
            if (in_array($role, ['doctor', 'clinical_officer', 'consultant', 'physician', 'specialist'])) {
                return 'clinician';
            }
            if (in_array($role, ['nurse', 'registered_nurse', 'practice_nurse'])) {
                return 'nurse';
            }
            if (in_array($role, ['laboratory_technician', 'lab_technician', 'radiographer'])) {
                return 'lab';
            }
        }

        return 'clinician';
    }

    protected function getHeaderWidgets(): array
    {
        if ($this->mode !== 'home') {
            return [];
        }

        return [
            CriticalPatientsWidget::class,
            MyTasksWidget::class,
            ...($this->hasAppointmentModule() ? [WorkspaceTodayAppointmentsWidget::class] : []),
        ];
    }

    protected function getHeaderActions(): array
    {
        if (!$this->currentPatient) {
            return [];
        }

        $actions = PatientActions::make()
            ->forPatient($this->currentPatient)
            ->withEncounter($this->currentEncounter);

        return [
            $actions->timelineAction(),
            $actions->patientActionGroups(),
            ...app(PageHeaderActionsRegistry::class)->for(static::class, $this),
        ];

    }

    protected function getFooterWidgets(): array
    {
        $widgets= [];
        if(!empty($this->currentPatient?->id)){
            $widgets[] = PendingFulfillmentsWidget::make(['patientId' => $this->currentPatient?->id]);
        }
        return $widgets;

    }

    protected function hasAppointmentModule(): bool
    {
        return class_exists('Modules\\Appointment\\Models\\Appointment');
    }

    public function saveConsultation(): void
    {
        if (!$this->currentPatient || !$this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();
            return;
        }

        $this->clinicalNoteService->record(
            $this->currentPatient,
            [
                'note_type' => NoteType::CONSULTATION,
                'status' => NoteStatus::DRAFT,
                'subject' => 'Consultation - ' . ($this->consultationChiefComplaint ?: 'General'),
                'content' => $this->consultationData['notes'] ?? '',
            ],
            $this->currentEncounter->id,
        );

        if ($this->consultationChiefComplaint && !$this->currentEncounter->chief_complaint) {
            $this->currentEncounter->update(['chief_complaint' => $this->consultationChiefComplaint]);
        }

        Notification::make()->title('Consultation saved')->success()->send();
    }

    public function saveVitals(): void
    {
        if (!$this->currentPatient) {
            return;
        }

        $this->vitalSignService->record(
            $this->currentPatient,
            $this->vitalsData,
            $this->currentEncounter?->id,
        );

        $this->vitalsData = [];
        $this->loadPatientContext();
        Notification::make()->title('Vital signs recorded')->success()->send();
    }

    public function saveServiceRequest(): void
    {
        if (!$this->currentPatient) {
            return;
        }

        $this->serviceRequestService->record(
            $this->currentPatient,
            $this->serviceRequestData,
            $this->currentEncounter?->id,
        );

        $this->serviceRequestData = [];
        Notification::make()->title('Service request created')->success()->send();
    }

    public function saveAllergy(): void
    {
        if (!$this->currentPatient) {
            return;
        }

        $this->allergyService->record($this->currentPatient, $this->allergyData);

        $this->allergyData = [];
        $this->loadPatientContext();
        Notification::make()->title('Allergy recorded')->success()->send();
    }

    public function createEncounter(): void
    {
        if (!$this->currentPatient) {
            return;
        }

        $encounter = $this->encounterService->createForPatient(
            patient: $this->currentPatient,
            type: EncounterType::OUTPATIENT,
            chiefComplaint: $this->consultationChiefComplaint ?: null,
            priority: EncounterPriority::ROUTINE,
        );

        if ($this->selectedEncounterCoverage && $encounter->getConnection()->getSchemaBuilder()->hasColumn('encounters', 'coverage_type')) {
            $encounter->update(['coverage_type' => $this->selectedEncounterCoverage]);
        }

        $this->currentEncounter = $encounter->fresh();
        Notification::make()->title('OPD encounter created')->success()->send();
    }

    public function removeDiagnosis(int $index): void
    {
        if (isset($this->diagnosisCodes[$index])) {
            unset($this->diagnosisCodes[$index]);
            $this->diagnosisCodes = array_values($this->diagnosisCodes);
        }
    }

    public function saveLabResult(): void
    {
        if (!$this->currentPatient || !$this->serviceRequestData['request_item_id'] ?? null) {
            Notification::make()->title('Select a pending lab item')->warning()->send();
            return;
        }

        $item = RequestItem::find($this->serviceRequestData['request_item_id']);
        if ($item) {
            app(FulfillmentService::class)->fulfill($item, [
                'notes' => $this->serviceRequestData['notes'] ?? '',
                'started_at' => now(),
                'ended_at' => now(),
            ]);
        }

        $this->serviceRequestData = [];
        Notification::make()->title('Lab result submitted')->success()->send();
    }

    public function saveMedicationOrder(): void
    {
        if (!$this->currentPatient || !$this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();
            return;
        }

        if (empty($this->medicationData['items'])) {
            Notification::make()->title('Add at least one medication')->warning()->send();
            return;
        }

        try {
            $service = app(\Modules\Pharmacy\Classes\Services\MedicationOrderService::class);
            $request = $service->order(
                $this->currentPatient,
                $this->medicationData['items'],
                Auth::user(),
                $this->currentEncounter->id,
            );

            if ($request) {
                $this->medicationData['items'] = [];
                Notification::make()->title('Medication order created')->success()->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Order failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveDischarge(): void
    {
        if (!$this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();
            return;
        }

        try {
            $this->encounterService->discharge(
                $this->currentEncounter,
                DischargeDisposition::from($this->dischargeData['discharge_disposition'] ?? 'completed'),
                $this->dischargeData['transfer_destination'] ?? null,
            );

            $this->dischargeData = [];
            $this->currentEncounter = null;
            Notification::make()->title('Patient discharged')->success()->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Discharge failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveReferral(): void
    {
        if (!$this->currentPatient || !$this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();
            return;
        }

        try {
            $this->clinicalNoteService->record(
                $this->currentPatient,
                [
                    'note_type' => NoteType::CONSULTATION,
                    'status' => NoteStatus::DRAFT,
                    'subject' => 'Referral - ' . ($this->referralData['destination'] ?? 'Unspecified'),
                    'content' => ($this->referralData['notes'] ?? '')
                        . "\n\nDestination: " . ($this->referralData['destination'] ?? ''),
                ],
                $this->currentEncounter->id,
            );

            $this->referralData = [];
            Notification::make()->title('Referral submitted')->success()->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Referral failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function registerForms(): array
    {
        return [
            'vitalsForm' => $this->makeSchema()
                ->schema(VitalSignForm::quickElements())
                ->statePath('vitalsData'),
            'serviceRequestForm' => $this->makeSchema()
                ->schema(ServiceRequestForm::quickElements(hidenEncounter: true))
                ->statePath('serviceRequestData'),
            'allergyForm' => $this->makeSchema()
                ->schema(AllergyForm::quickElements())
                ->statePath('allergyData'),
            'triageForm' => $this->makeSchema()
                ->schema([
                    RichEditor::make('notes')
                        ->label('Triage Notes')
                        ->placeholder('Triage observations...'),
                ])
                ->statePath('consultationData'),
            'consultationForm' => $this->makeSchema()
                ->schema([
                    RichEditor::make('notes')
                        ->label('Consultation / SOAP Notes')
                        ->placeholder('Subjective, Objective, Assessment, Plan...'),
                ])
                ->statePath('consultationData'),
            'labResultForm' => $this->makeSchema()
                ->schema([
                    RichEditor::make('notes')
                        ->label('Result Notes')
                        ->placeholder('Enter lab result details...'),
                ])
                ->statePath('serviceRequestData'),
            'medicationForm' => $this->makeSchema()
                ->schema([
                    Repeater::make('items')
                        ->minItems(1)
                        ->schema([
                            Select::make('service_id')
                                ->label('Medication')
                                ->required()
                                ->searchable()
                                ->options(fn () => \Modules\Core\Models\Service::where('requires_prescription', true)->pluck('name', 'id')),
                            TextInput::make('dosage')
                                ->label('Dosage')
                                ->placeholder('e.g. 500mg'),
                            Select::make('frequency')
                                ->label('Frequency')
                                ->options(\Modules\Pharmacy\Enums\MedicationFrequency::class)
                                ->searchable(),
                            Select::make('route')
                                ->label('Route')
                                ->options(\Modules\Pharmacy\Enums\MedicationRoute::class)
                                ->searchable(),
                            TextInput::make('duration_days')
                                ->label('Duration (days)')
                                ->numeric()
                                ->minValue(1),
                            Textarea::make('instructions')
                                ->label('SIG / Instructions')
                                ->rows(2),
                            Checkbox::make('prn')
                                ->label('Take as needed (PRN)'),
                            TextInput::make('indication')
                                ->label('Indication'),
                            TextInput::make('refills')
                                ->label('Refills')
                                ->numeric()
                                ->default(0)
                                ->minValue(0),
                        ]),
                ])
                ->statePath('medicationData'),
            'dischargeForm' => $this->makeSchema()
                ->schema([
                    Select::make('discharge_disposition')
                        ->label('Disposition')
                        ->options(DischargeDisposition::class)
                        ->default('completed')
                        ->required(),
                    TextInput::make('transfer_destination')
                        ->label('Transfer Destination')
                        ->visible(fn ($get) => $get('discharge_disposition') === 'transferred'),
                    RichEditor::make('discharge_notes')
                        ->label('Discharge Notes'),
                ])
                ->statePath('dischargeData'),
            'referralForm' => $this->makeSchema()
                ->schema([
                    TextInput::make('destination')
                        ->label('Referral Destination')
                        ->placeholder('Department or facility name')
                        ->required(),
                    RichEditor::make('notes')
                        ->label('Referral Notes'),
                ])
                ->statePath('referralData'),
        ];
    }

    public function getClinicianTabs(): array
    {
        return [
            'vitals' => [
                'label' => 'Vitals',
                'icon' => 'heroicon-m-heart',
            ],
            'service-lab' => [
                'label' => 'Service / Lab',
                'icon' => 'heroicon-m-clipboard-document-list',
            ],
            'medication' => [
                'label' => 'Medication',
                'icon' => 'heroicon-m-beaker',
            ],
            'allergies' => [
                'label' => 'Allergies',
                'icon' => 'heroicon-m-exclamation-triangle',
            ],
            'discharge' => [
                'label' => 'Discharge',
                'icon' => 'heroicon-m-arrow-right-on-rectangle',
            ],
            'referral' => [
                'label' => 'Referral',
                'icon' => 'heroicon-m-arrow-path',
            ],
        ];
    }

    public function getNurseTabs(): array
    {
        return [
            'encounter' => [
                'label' => 'Encounter',
                'icon' => 'heroicon-m-plus-circle',
            ],
            'vitals' => [
                'label' => 'Vitals',
                'icon' => 'heroicon-m-heart',
            ],
            'allergies' => [
                'label' => 'Allergies',
                'icon' => 'heroicon-m-exclamation-triangle',
            ],
            'triage' => [
                'label' => 'Triage',
                'icon' => 'heroicon-m-clipboard-document',
            ],
        ];
    }

    public function getLabTabs(): array
    {
        return [
            'pending-labs' => [
                'label' => 'Pending Labs',
                'icon' => 'heroicon-m-clock',
            ],
            'submit-results' => [
                'label' => 'Submit Results',
                'icon' => 'heroicon-m-check-circle',
            ],
            'completed' => [
                'label' => 'Completed',
                'icon' => 'heroicon-m-check-badge',
            ],
        ];
    }

    #[Computed]
    public function latestVitals(): ?object
    {
        return $this->currentPatient ? $this->workspaceService?->getLatestVitals() : null;
    }

    #[Computed]
    public function pendingLabItems(): array
    {
        if (!$this->currentPatient) {
            return [];
        }

        return RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('serviceRequest', fn($q) => $q->where('patient_id', $this->currentPatient->id))
            ->whereDoesntHave('prescriptionDetail')
            ->with(['service', 'serviceRequest.orderedBy', 'service.category'])
            ->get()
            ->toArray();
    }

    #[Computed]
    public function completedLabItems(): array
    {
        if (!$this->currentPatient) {
            return [];
        }

        return RequestItem::query()
            ->where('status', 'completed')
            ->whereHas('serviceRequest', fn($q) => $q->where('patient_id', $this->currentPatient->id))
            ->whereDoesntHave('prescriptionDetail')
            ->with(['service', 'serviceRequest.orderedBy', 'service.category'])
            ->latest()
            ->limit(20)
            ->get()
            ->toArray();
    }
}
