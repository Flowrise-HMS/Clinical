<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Classes\Services\DiagnosisService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Schemas\AllergyForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas\ClinicalNoteForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas\EncounterDiagnosisForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignForm;
use Modules\Clinical\Filament\Clusters\Workspace\Concerns\ManagesWorkspacePatient;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Concerns\CanAutocodeIcd;
use Modules\Clinical\Filament\Widgets\CriticalPatientsWidget;
use Modules\Clinical\Filament\Widgets\MyTasksWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsHistoryWidget;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Policies\ClinicalNotePolicy;
use Modules\Clinical\Policies\EncounterDiagnosisPolicy;
use Modules\Clinical\Policies\EncounterPolicy;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Settings\FeatureSettings;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Enums\MedicationRoute;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;

class ClinicalWorkspace extends Page implements HasSchemas
{
    use CanAutocodeIcd, HasPageShield, InteractsWithSchemas, ManagesWorkspacePatient;

    protected static ?string $slug = '';

    protected static ?string $navigationLabel = 'Clinical Workspace';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-m-heart';

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return app(FeatureSettings::class)->clinical_workspace_enabled;
        } catch (\Throwable) {
            return true;
        }
    }

    protected string $view = 'clinical::clinical.workspace.clinical-workspace';

    #[Url]
    public ?string $patientId = null;

    public string $mode = 'home';

    public string $activeTab = '';

    public ?Encounter $currentEncounter = null;

    public string $searchTerm = '';

    public array $searchResults = [];

    public string $consultationChiefComplaint = '';

    public string $consultationNotes = '';

    public array $diagnosisFormData = [];

    public array $vitalsData = [];

    public array $serviceRequestData = [];

    public array $labResultData = [];

    public array $allergyData = [];

    /**
     * Nested keys must exist up-front so Filament RichEditor can Livewire-entangle them.
     *
     * @var array{notes: string|null}
     */
    public array $consultationData = [
        'notes' => null,
    ];

    public array $medicationData = [];

    public array $encounterFormData = [];

    /**
     * @var array{discharge_notes: string|null, discharge_disposition?: string}
     */
    public array $dischargeData = [
        'discharge_notes' => null,
        'discharge_disposition' => DischargeDisposition::COMPLETED->value,
    ];

    /**
     * @var array{transfer_notes: string|null, transfer_out_notes: string|null, transfer_in_notes: string|null, destination_type?: string, admission_source?: string}
     */
    public array $adtFormData = [
        'transfer_notes' => null,
        'transfer_out_notes' => null,
        'transfer_in_notes' => null,
        'destination_type' => AdtDestinationType::ExternalFacility->value,
        'admission_source' => 'local',
    ];

    /**
     * @var array{notes: string|null}
     */
    public array $referralData = [
        'notes' => null,
    ];

    /**
     * @var array{content: string|null, status?: string}
     */
    public array $noteFormData = [
        'content' => null,
        'status' => NoteStatus::DRAFT->value,
    ];

    protected ?ClinicalWorkspaceService $workspaceService = null;

    protected ?PatientSearchService $patientSearchService = null;

    protected ?VitalSignService $vitalSignService = null;

    protected ?ClinicalNoteService $clinicalNoteService = null;

    protected ?ServiceRequestService $serviceRequestService = null;

    protected ?EncounterService $encounterService = null;

    protected ?AdtService $adtService = null;

    protected ?BedAssignmentService $bedAssignmentService = null;

    protected ?AllergyService $allergyService = null;

    protected ?DiagnosisService $diagnosisService = null;

    public function boot(): void
    {
        $this->workspaceService ??= app(ClinicalWorkspaceService::class);
        $this->patientSearchService ??= app(PatientSearchService::class);
        $this->vitalSignService ??= app(VitalSignService::class);
        $this->clinicalNoteService ??= app(ClinicalNoteService::class);
        $this->serviceRequestService ??= app(ServiceRequestService::class);
        $this->encounterService ??= app(EncounterService::class);
        $this->adtService ??= app(AdtService::class);
        $this->bedAssignmentService ??= app(BedAssignmentService::class);
        $this->allergyService ??= app(AllergyService::class);
        $this->diagnosisService ??= app(DiagnosisService::class);

        foreach ($this->registerForms() as $name => $schema) {
            $this->cacheSchema($name, $schema);
        }

        $this->buildLabResultFormSchema();
    }

    public function mount(): void
    {
        if ($this->patientId) {
            $this->selectPatient($this->patientId);
        }
    }

    public function selectPatient(string $id, bool $fromRegistration = false): void
    {
        $this->patientId = $id;
        $this->mode = 'patient';
        $this->loadPatientContext();
        $this->fillPatientFormDataFromCurrentPatient();

        if ($fromRegistration) {
            $this->postRegistrationFlow = true;
            $this->activeTab = $this->getPostRegistrationTab();
        } else {
            $this->postRegistrationFlow = false;
            $this->setDefaultTab();
        }

        $this->searchTerm = '';
        $this->searchResults = [];
        $this->registerFormData = [];
        $this->confirmDuplicateRegistration = false;
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
        $this->resetPatientManagementState();
    }

    protected function resetFormStates(): void
    {
        $this->encounterFormData = [];
        $this->consultationChiefComplaint = '';
        $this->consultationNotes = '';
        $this->consultationData = $this->defaultConsultationData();
        $this->diagnosisFormData = [];
        $this->vitalsData = [];
        $this->serviceRequestData = [];
        $this->labResultData = [];
        $this->allergyData = [];
        $this->medicationData = [];
        $this->dischargeData = $this->defaultDischargeData();
        $this->adtFormData = $this->defaultAdtFormData();
        $this->referralData = $this->defaultReferralData();
        $this->noteFormData = $this->defaultNoteFormData();
    }

    /**
     * @return array{notes: null}
     */
    protected function defaultConsultationData(): array
    {
        return ['notes' => null];
    }

    /**
     * @return array{discharge_notes: null, discharge_disposition: string}
     */
    protected function defaultDischargeData(): array
    {
        return [
            'discharge_notes' => null,
            'discharge_disposition' => DischargeDisposition::COMPLETED->value,
        ];
    }

    /**
     * @return array{transfer_notes: null, transfer_out_notes: null, transfer_in_notes: null, destination_type: string, admission_source: string}
     */
    protected function defaultAdtFormData(): array
    {
        return [
            'transfer_notes' => null,
            'transfer_out_notes' => null,
            'transfer_in_notes' => null,
            'destination_type' => AdtDestinationType::ExternalFacility->value,
            'admission_source' => 'local',
        ];
    }

    /**
     * @return array{notes: null}
     */
    protected function defaultReferralData(): array
    {
        return ['notes' => null];
    }

    /**
     * The note form declares a draft status default, but the schema is never filled here,
     * so the default is seeded into the state to keep the required status field satisfied.
     *
     * @return array{content: null, status: string}
     */
    protected function defaultNoteFormData(): array
    {
        return [
            'content' => null,
            'status' => NoteStatus::DRAFT->value,
        ];
    }

    protected function loadPatientContext(): void
    {
        if (! $this->patientId) {
            return;
        }

        $this->currentPatient = Patient::with([
            'allergies',
            'activeEncounter.bed',
            'activeEncounter.location',
            'latestEncounter.bed',
            'latestEncounter.location',
            'latestVitals',
        ])->find($this->patientId);

        if ($this->currentPatient) {
            $this->workspaceService->setPatient($this->currentPatient);
            $this->currentEncounter = $this->currentPatient->activeEncounter ?? $this->currentPatient->latestEncounter;

            if ($this->currentEncounter) {
                $existing = $this->diagnosisService->getForEncounter($this->currentEncounter->id);
                $this->diagnosisFormData = [
                    'diagnoses' => $existing['diagnoses'] ?: [],
                ];
            }

            $this->fillEncounterFormData();
        }
    }

    public function hasOpenEncounter(): bool
    {
        return $this->getOpenEncounter() !== null;
    }

    public function getOpenEncounter(): ?Encounter
    {
        if (! $this->currentPatient) {
            return null;
        }

        if ($this->currentPatient->relationLoaded('activeEncounter')) {
            return $this->currentPatient->activeEncounter;
        }

        return $this->currentPatient->activeEncounter()->first();
    }

    protected function fillEncounterFormData(): void
    {
        $openEncounter = $this->getOpenEncounter();

        if (! $openEncounter) {
            $this->encounterFormData = ['type' => EncounterType::OUTPATIENT->value];

            return;
        }

        $this->encounterFormData = [
            'type' => $openEncounter->type?->value ?? EncounterType::OUTPATIENT->value,
            'coverage_type' => $openEncounter->coverage_type?->value ?? $openEncounter->coverage_type ?? 'none',
            'claim_check_code' => $openEncounter->claim_check_code,
            'chief_complaint' => $openEncounter->chief_complaint,
        ];
    }

    /**
     * @return array{type: ?string, status: ?string, status_color: string, ward: ?string, bed: ?string, los: ?string}
     */
    public function getEncounterStatusChip(): array
    {
        $encounter = $this->getOpenEncounter() ?? $this->currentEncounter;

        if (! $encounter) {
            return [
                'type' => null,
                'status' => null,
                'status_color' => 'gray',
                'ward' => null,
                'bed' => null,
                'los' => null,
            ];
        }

        $encounter->loadMissing(['bed', 'location']);

        return [
            'type' => $encounter->type?->getLabel(),
            'status' => $encounter->status?->getLabel(),
            'status_color' => $encounter->status?->getColor() ?? 'gray',
            'ward' => $encounter->location?->name,
            'bed' => $encounter->bed?->name,
            'los' => $encounter->duration,
        ];
    }

    protected function setDefaultTab(): void
    {
        if ($this->activeTab) {
            return;
        }

        if ($this->postRegistrationFlow) {
            $this->activeTab = $this->getPostRegistrationTab();

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
        if (! $user) {
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
        $widgets = [];
        if ($this->mode !== 'home') {
            return $widgets;
        }

        return [
            CriticalPatientsWidget::class,
            MyTasksWidget::class,
            ...$widgets,
            ...($this->hasAppointmentModule() ? [WorkspaceTodayAppointmentsWidget::class] : []),
        ];
    }

    protected function getHeaderActions(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $actions = PatientActions::make()
            ->forPatient($this->currentPatient)
            ->withEncounter($this->currentEncounter);

        return [
            $actions->timelineAction(),
            $actions->profileAction(),
            $actions->patientActionGroups(),
            ...app(PageHeaderActionsRegistry::class)->for(static::class, $this),
        ];

    }

    protected function getFooterWidgets(): array
    {
        $widgets = [];
        if (! empty($this->currentPatient?->id)) {
            $widgets[] = PendingFulfillmentsWidget::make(['patientId' => $this->currentPatient?->id, 'encounterId' => $this->currentEncounter?->id]);
            $widgets[] = PatientVitalsHistoryWidget::make(['patientId' => $this->currentPatient?->id]);
        }

        return $widgets;

    }

    protected function hasAppointmentModule(): bool
    {
        return class_exists('Modules\\Appointment\\Models\\Appointment');
    }

    public function saveConsultation(): void
    {
        if (! $this->currentPatient || ! $this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        if (blank($this->consultationData['notes'] ?? null) && blank($this->consultationChiefComplaint)) {
            Notification::make()->title('Nothing to save')->warning()->send();

            return;
        }

        $content = $this->consultationData['notes'] ?? '';

        if (blank($content) && filled($this->consultationChiefComplaint)) {
            $content = '<p>Chief complaint: '.e($this->consultationChiefComplaint).'</p>';
        }

        $this->clinicalNoteService->record(
            $this->currentPatient,
            [
                'note_type' => NoteType::CONSULTATION,
                'status' => NoteStatus::SIGNED,
                'subject' => 'Consultation - '.($this->consultationChiefComplaint ?: 'General'),
                'content' => $content,
            ],
            $this->currentEncounter->id,
        );

        if ($this->consultationChiefComplaint && ! $this->currentEncounter->chief_complaint) {
            $this->currentEncounter->update(['chief_complaint' => $this->consultationChiefComplaint]);
        }

        Notification::make()->title('Consultation saved')->success()->send();
    }

    /**
     * State is read back through the schema so the vitals form's own validation runs,
     * which keeps a blank reading from being saved as an empty vital signs record.
     */
    public function saveVitals(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        $vitalsForm = $this->getSchema('vitalsForm');

        if ($vitalsForm === null) {
            return;
        }

        $this->vitalSignService->record(
            $this->currentPatient,
            $vitalsForm->getState(),
            $this->currentEncounter?->id,
        );

        $this->vitalsData = [];
        $this->loadPatientContext();

        if ($this->postRegistrationFlow) {
            $this->postRegistrationFlow = false;
            Notification::make()
                ->title('Vital signs recorded')
                ->body('Ready for consultation.')
                ->success()
                ->send();

            return;
        }

        Notification::make()->title('Vital signs recorded')->success()->send();
    }

    public function saveServiceRequest(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        $serviceRequestForm = $this->getSchema('serviceRequestForm');

        if ($serviceRequestForm === null) {
            return;
        }

        $this->serviceRequestService->record(
            $this->currentPatient,
            $serviceRequestForm->getState(),
            $this->currentEncounter?->id,
        );

        $this->serviceRequestData = [];
        Notification::make()->title('Service request created')->success()->send();
    }

    public function saveAllergy(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        $allergyForm = $this->getSchema('allergyForm');

        if ($allergyForm === null) {
            return;
        }

        $this->allergyService->record($this->currentPatient, $allergyForm->getState());

        $this->allergyData = [];
        $this->loadPatientContext();
        Notification::make()->title('Allergy recorded')->success()->send();
    }

    public function createEncounter(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        if ($this->hasOpenEncounter()) {
            Notification::make()
                ->title('Active encounter exists')
                ->body('This patient already has an open encounter. Switch to the Vitals tab to continue.')
                ->warning()
                ->send();

            return;
        }

        $encounterForm = $this->getSchema('encounterForm');

        if ($encounterForm === null) {
            return;
        }

        $encounterData = $encounterForm->getState();

        $type = EncounterType::tryFrom($encounterData['type'] ?? '')
            ?? EncounterType::OUTPATIENT;

        $priority = $type === EncounterType::EMERGENCY
            ? EncounterPriority::URGENT
            : EncounterPriority::ROUTINE;

        $encounter = $this->encounterService->createForPatient(
            patient: $this->currentPatient,
            type: $type,
            chiefComplaint: $encounterData['chief_complaint'] ?? null,
            priority: $priority,
        );

        if ($coverage = $encounterData['coverage_type'] ?? null) {
            $encounter->update([
                'coverage_type' => $coverage,
                'claim_check_code' => $encounterData['claim_check_code'] ?? null,
            ]);
        }

        $this->currentEncounter = $encounter->fresh(['bed', 'location']);
        $this->currentPatient?->unsetRelation('activeEncounter');
        $this->fillEncounterFormData();
        $this->activeTab = $type === EncounterType::INPATIENT && $this->canAccessAdtTab()
            ? 'adt'
            : 'vitals';

        $label = $type->getLabel();

        if ($this->postRegistrationFlow) {
            Notification::make()
                ->title("{$label} encounter created")
                ->body($type === EncounterType::INPATIENT
                    ? 'Assign a ward and bed next.'
                    : 'Record vitals next.')
                ->success()
                ->send();

            return;
        }

        Notification::make()->title("{$label} encounter created")->success()->send();
    }

    public function admitToBed(): void
    {
        if (! $this->currentPatient) {
            Notification::make()->title('No patient selected')->danger()->send();

            return;
        }

        $adtAdmitForm = $this->getSchema('adtAdmitForm');

        if ($adtAdmitForm === null) {
            return;
        }

        /*
         * The ADT forms share one state path, so each action validates its own schema and
         * merges the result over the raw state to keep keys the schema does not own.
         */
        $adtData = array_merge($this->adtFormData, $adtAdmitForm->getState());
        $bedId = $adtData['bed_id'] ?? null;

        $open = $this->getOpenEncounter();

        if ($open) {
            if (! $this->canShowAdmitOnAdt($open)) {
                Notification::make()->title('Not authorized')->danger()->send();

                return;
            }

            try {
                $encounter = $this->adtService->assignBed(
                    $open,
                    $bedId,
                    notes: $adtData['notes'] ?? null,
                );

                $this->refreshAdtContext($encounter);
                $this->adtFormData = $this->defaultAdtFormData();
                Notification::make()->title('Patient admitted')->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title('Admit failed')->body($e->getMessage())->danger()->send();
            }

            return;
        }

        if (! $this->canCreateEncounter()) {
            Notification::make()->title('Not authorized')->danger()->send();

            return;
        }

        try {
            $encounter = $this->adtService->admit(
                $this->currentPatient,
                $bedId,
                departmentId: $adtData['department_id'] ?? null,
                chiefComplaint: $adtData['chief_complaint']
                    ?? $this->encounterFormData['chief_complaint']
                    ?? null,
                notes: $adtData['notes'] ?? null,
            );

            $this->refreshAdtContext($encounter);
            $this->adtFormData = $this->defaultAdtFormData();
            Notification::make()->title('Patient admitted')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Admit failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function transferInternal(): void
    {
        $encounter = $this->getOpenEncounter();
        if (! $encounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        $this->authorizeEncounterUpdate($encounter);

        $transferForm = $this->getSchema('adtTransferInternalForm');

        if ($transferForm === null) {
            return;
        }

        $adtData = array_merge($this->adtFormData, $transferForm->getState());

        try {
            $encounter = $this->adtService->transferInternal(
                $encounter,
                $adtData['transfer_bed_id'] ?? null,
                toDepartmentId: $adtData['transfer_department_id'] ?? null,
                notes: $adtData['transfer_notes'] ?? null,
            );

            $this->refreshAdtContext($encounter);
            $this->adtFormData = $this->defaultAdtFormData();
            Notification::make()->title('Patient transferred')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Transfer failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function transferOut(): void
    {
        $encounter = $this->getOpenEncounter();
        if (! $encounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        $this->authorizeEncounterDischarge($encounter);

        $transferOutForm = $this->getSchema('adtTransferOutForm');

        if ($transferOutForm === null) {
            return;
        }

        $adtData = array_merge($this->adtFormData, $transferOutForm->getState());

        try {
            $destinationType = AdtDestinationType::from(
                $adtData['destination_type'] ?? AdtDestinationType::ExternalFacility->value
            );

            $this->adtService->transferOut(
                $encounter,
                $destinationType,
                destinationLabel: $adtData['destination_label'] ?? null,
                destinationBranchId: $adtData['destination_branch_id'] ?? null,
                notes: $adtData['transfer_out_notes'] ?? null,
            );

            $this->adtFormData = $this->defaultAdtFormData();
            $this->currentEncounter = null;
            $this->currentPatient?->unsetRelation('activeEncounter');
            $this->loadPatientContext();
            Notification::make()->title('Patient transferred out')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Transfer out failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function transferIn(): void
    {
        if (! $this->currentPatient) {
            Notification::make()->title('No patient selected')->danger()->send();

            return;
        }

        if ($this->hasOpenEncounter()) {
            Notification::make()
                ->title('Active encounter exists')
                ->body('Finish or cancel the open encounter before admitting from transfer.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->canCreateEncounter()) {
            Notification::make()->title('Not authorized')->danger()->send();

            return;
        }

        $transferInForm = $this->getSchema('adtTransferInForm');

        if ($transferInForm === null) {
            return;
        }

        $adtData = array_merge($this->adtFormData, $transferInForm->getState());
        $isLocal = ($adtData['admission_source'] ?? 'local') === 'local';

        try {
            $encounter = $this->adtService->transferIn(
                $this->currentPatient,
                $adtData['transfer_in_bed_id'] ?? null,
                sourceLabel: $adtData['source_label'] ?? null,
                fromBranchId: $adtData['from_branch_id'] ?? null,
                chiefComplaint: $adtData['transfer_in_chief_complaint'] ?? null,
                notes: $adtData['transfer_in_notes'] ?? null,
            );

            $this->refreshAdtContext($encounter);
            $this->adtFormData = $this->defaultAdtFormData();
            $this->activeTab = 'vitals';
            Notification::make()->title($isLocal ? 'Patient admitted successfully' : 'Patient admitted from transfer')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($isLocal ? 'Admission failed' : 'Transfer in failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function authorizeEncounterUpdate(Encounter $encounter): void
    {
        if (! $this->canUpdateEncounter($encounter)) {
            throw new AuthorizationException(__('Not authorized to update this encounter.'));
        }
    }

    protected function authorizeEncounterDischarge(Encounter $encounter): void
    {
        if (! $this->canDischargeEncounter($encounter)) {
            throw new AuthorizationException(__('Not authorized to discharge this encounter.'));
        }
    }

    public function canCreateEncounter(): bool
    {
        $user = Auth::user();

        return $user !== null && app(EncounterPolicy::class)->create($user);
    }

    public function canUpdateEncounter(?Encounter $encounter = null): bool
    {
        $user = Auth::user();
        $encounter ??= $this->getOpenEncounter();

        return $user !== null
            && $encounter !== null
            && app(EncounterPolicy::class)->update($user, $encounter);
    }

    public function canDischargeEncounter(?Encounter $encounter = null): bool
    {
        $user = Auth::user();
        $encounter ??= $this->getOpenEncounter();

        if ($user === null || $encounter === null) {
            return false;
        }

        return $user->can('discharge_patient');
    }

    public function canShowAdmitOnAdt(?Encounter $encounter = null): bool
    {
        $encounter ??= $this->getOpenEncounter();

        return $encounter !== null
            && $this->canUpdateEncounter($encounter)
            && EncounterActions::isAdmitVisible($encounter);
    }

    public function canShowDischargeOnAdt(?Encounter $encounter = null): bool
    {
        $encounter ??= $this->getOpenEncounter();

        return $encounter !== null
            && $this->canDischargeEncounter($encounter)
            && EncounterActions::isDischargeVisible($encounter);
    }

    public function canAccessNotesTab(): bool
    {
        $user = Auth::user();

        return $user !== null && app(ClinicalNotePolicy::class)->create($user);
    }

    public function canAccessDiagnosisTab(): bool
    {
        $user = Auth::user();

        return $user !== null && app(EncounterDiagnosisPolicy::class)->create($user);
    }

    public function canAccessAdtTab(): bool
    {
        $encounter = $this->getOpenEncounter();

        if ($encounter === null) {
            return $this->canCreateEncounter();
        }

        return $this->canUpdateEncounter($encounter)
            || $this->canDischargeEncounter($encounter);
    }

    protected function refreshAdtContext(Encounter $encounter): void
    {
        $this->currentEncounter = $encounter->fresh(['bed', 'location']);
        $this->currentPatient?->unsetRelation('activeEncounter');
        $this->currentPatient?->unsetRelation('latestEncounter');
        $this->loadPatientContext();
        $this->fillEncounterFormData();
    }

    /**
     * @return array<string, string>
     */
    public function getWardOptions(): array
    {
        $branchId = $this->currentEncounter?->branch_id
            ?? $this->currentPatient?->branch_id;

        if (blank($branchId)) {
            return [];
        }

        return $this->bedAssignmentService->getWardsForBranch($branchId)->all();
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableBedOptions(?string $wardId): array
    {
        if (blank($wardId)) {
            return [];
        }

        return $this->bedAssignmentService->getAvailableBeds($wardId)->all();
    }

    public function saveDiagnoses(): void
    {
        if (! $this->canAccessDiagnosisTab()) {
            Notification::make()->title('Not authorized')->danger()->send();

            return;
        }

        if (! $this->currentEncounter || ! $this->currentPatient) {
            Notification::make()->title('No active encounter')->warning()->send();

            return;
        }

        $diagnoses = array_values(array_filter(
            $this->diagnosisFormData['diagnoses'] ?? [],
            fn ($row): bool => filled($row['description'] ?? null)
                || filled($row['diagnosis_code_id'] ?? null)
                || filled($row['code_search'] ?? null)
                || filled($row['icd_entity_id'] ?? null),
        ));

        if ($diagnoses === []) {
            Notification::make()->title('No diagnoses to save')->warning()->send();

            return;
        }

        try {
            $this->diagnosisService->assertValidRows($diagnoses);
            $this->diagnosisService->syncForEncounter(
                $this->currentPatient,
                $diagnoses,
                $this->currentEncounter->id,
                Auth::id(),
            );

            $existing = $this->diagnosisService->getForEncounter($this->currentEncounter->id);
            $this->diagnosisFormData = [
                'diagnoses' => $existing['diagnoses'] ?: [],
            ];

            Notification::make()->title('Diagnoses saved')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not save diagnoses')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Fill the first uncoded diagnosis row (or append a new row) from the current autocode suggestion.
     */
    public function applyAutocodeToDiagnosis(): void
    {
        $suggestion = $this->acceptIcdAutocode();

        if ($suggestion === null) {
            return;
        }

        $this->fillDiagnosisFromSuggestion($suggestion);
    }

    /**
     * Fill the first uncoded diagnosis row (or append a new row) from the ICD browser selection.
     *
     * @param  array<string, mixed>  $suggestion
     */
    #[On('icd-diagnosis-selected')]
    public function applyBrowserSelection(array $suggestion, ?string $localId = null): void
    {
        if ($localId) {
            $suggestion['local_id'] = $localId;
        }

        $this->fillDiagnosisFromSuggestion($suggestion);
    }

    /**
     * @param  array<string, mixed>  $suggestion
     */
    protected function fillDiagnosisFromSuggestion(array $suggestion): void
    {
        $diagnoses = $this->diagnosisFormData['diagnoses'] ?? [];

        foreach ($diagnoses as $index => $row) {
            if (filled($row['code_search'] ?? null)
                || filled($row['diagnosis_code_id'] ?? null)
                || filled($row['icd_entity_id'] ?? null)) {
                continue;
            }

            if (! filled($row['description'] ?? null)) {
                continue;
            }

            $this->diagnosisFormData['diagnoses'][$index] = $this->diagnosisFromAutocode($row, $suggestion);

            return;
        }

        $this->diagnosisFormData['diagnoses'][] = $this->diagnosisFromAutocode([], $suggestion);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{local_id: ?string, code: ?string, label: string, external_id: ?string, uri: ?string, source: string}  $suggestion
     * @return array<string, mixed>
     */
    protected function diagnosisFromAutocode(array $row, array $suggestion): array
    {
        return array_merge([
            'type' => DiagnosisType::Primary->value,
            'is_new_case' => '0',
            'certainty' => DiagnosisCertainty::Provisional->value,
            'notes' => null,
        ], $row, [
            'diagnosis_code_id' => $suggestion['local_id'] ?? null,
            'icd_code' => $suggestion['code'],
            'icd_entity_id' => $suggestion['external_id'],
            'icd_uri' => $suggestion['uri'],
            'description' => filled($row['description'] ?? null)
                ? $row['description']
                : $suggestion['label'],
        ]);
    }

    public function updatedServiceRequestDataRequestItemId(?string $requestItemId): void
    {
        $this->labResultData = [];
        $this->buildLabResultFormSchema();
    }

    protected function buildLabResultFormSchema(): void
    {
        $requestItemId = $this->serviceRequestData['request_item_id'] ?? null;
        $schema = [];

        if (filled($requestItemId)) {
            $item = RequestItem::query()
                ->with(['service.category', 'serviceRequest.orderedBy', 'prescriptionDetail'])
                ->find($requestItemId);

            if ($item !== null) {
                $schema = app(FulfillmentService::class)->getFormSchema($item);
            }
        }

        if ($schema === []) {
            $schema = [
                TextEntry::make('select_pending_lab')
                    ->hiddenLabel()
                    ->state('Select a pending lab item to load the result form.'),
            ];
        }

        $this->cacheSchema('labResultForm', $this->makeSchema()
            ->schema($schema)
            ->statePath('labResultData'));
    }

    public function saveLabResult(): void
    {
        if (! $this->currentPatient || empty($this->serviceRequestData['request_item_id'])) {
            Notification::make()->title('Select a pending lab item')->warning()->send();

            return;
        }

        $item = RequestItem::query()
            ->with(['service.category', 'serviceRequest.orderedBy', 'prescriptionDetail'])
            ->find($this->serviceRequestData['request_item_id']);

        if ($item === null) {
            Notification::make()->title('Lab item not found')->danger()->send();

            return;
        }

        $labResultForm = $this->getSchema('labResultForm');

        if ($labResultForm === null) {
            return;
        }

        $labResultData = $labResultForm->getState();

        try {
            app(FulfillmentService::class)->fulfill($item, $labResultData);
            $this->serviceRequestData = [];
            $this->labResultData = [];
            $this->buildLabResultFormSchema();
            Notification::make()->title('Lab result submitted')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Lab result submission failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveMedicationOrder(): void
    {
        if (! $this->currentPatient || ! $this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        if (empty($this->medicationData['items'])) {
            Notification::make()->title('Add at least one medication')->warning()->send();

            return;
        }

        $medicationForm = $this->getSchema('medicationForm');

        if ($medicationForm === null) {
            return;
        }

        $medicationItems = $medicationForm->getState()['items'] ?? [];

        try {
            $service = app(MedicationOrderService::class);
            $request = $service->order(
                $this->currentPatient,
                $medicationItems,
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
        $encounter = $this->getOpenEncounter();

        if (! $encounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        if (! $this->canShowDischargeOnAdt($encounter)) {
            Notification::make()->title('Not authorized')->danger()->send();

            return;
        }

        $dischargeForm = $this->getSchema('dischargeForm');

        if ($dischargeForm === null) {
            return;
        }

        $dischargeData = $dischargeForm->getState();

        try {
            $this->adtService->discharge(
                $encounter,
                DischargeDisposition::from($dischargeData['discharge_disposition'] ?? 'completed'),
                $dischargeData['transfer_destination'] ?? null,
                notes: $dischargeData['discharge_notes'] ?? null,
            );

            $this->dischargeData = $this->defaultDischargeData();
            $this->currentEncounter = null;
            $this->currentPatient?->unsetRelation('activeEncounter');
            $this->loadPatientContext();
            Notification::make()->title('Patient discharged')->success()->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Discharge failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveClinicalNote(): void
    {
        if (! $this->currentPatient) {
            Notification::make()->title('No patient selected')->danger()->send();

            return;
        }

        if (! $this->canAccessNotesTab()) {
            Notification::make()->title('Not authorized')->danger()->send();

            return;
        }

        $noteForm = $this->getSchema('noteForm');

        if ($noteForm === null) {
            return;
        }

        /*
         * Validation runs outside the try so its errors surface on the fields themselves
         * rather than being swallowed into the generic failure notification below.
         */
        $noteData = $noteForm->getState();

        try {
            $this->clinicalNoteService->record(
                $this->currentPatient,
                $noteData,
                $this->currentEncounter?->id ?? $this->getOpenEncounter()?->id,
            );

            $this->noteFormData = $this->defaultNoteFormData();
            Notification::make()->title('Clinical note created')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Note failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveReferral(): void
    {
        if (! $this->currentPatient || ! $this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        $referralForm = $this->getSchema('referralForm');

        if ($referralForm === null) {
            return;
        }

        $referralData = $referralForm->getState();

        try {
            $this->clinicalNoteService->record(
                $this->currentPatient,
                [
                    'note_type' => NoteType::CONSULTATION,
                    'status' => NoteStatus::SIGNED,
                    'subject' => 'Referral - '.($referralData['destination'] ?? 'Unspecified'),
                    'content' => ($referralData['notes'] ?? '')
                        ."\n\nDestination: ".($referralData['destination'] ?? ''),
                ],
                $this->currentEncounter->id,
            );

            $this->referralData = $this->defaultReferralData();
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
        return array_merge([
            'encounterForm' => $this->makeSchema()
                ->model(fn (): ?Encounter => $this->getOpenEncounter())
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('type')
                                ->label('Encounter Type')
                                ->options([
                                    EncounterType::OUTPATIENT->value => EncounterType::OUTPATIENT->getLabel(),
                                    EncounterType::INPATIENT->value => EncounterType::INPATIENT->getLabel(),
                                    EncounterType::EMERGENCY->value => EncounterType::EMERGENCY->getLabel(),
                                ])
                                ->default(EncounterType::OUTPATIENT->value)
                                ->required()
                                ->native(false),
                            Select::make('coverage_type')
                                ->label('Coverage Type')
                                ->options(CoverageType::class)
                                ->required()
                                ->live()
                                ->native(false),
                            Textarea::make('chief_complaint')
                                ->label('Chief Complaint')
                                ->placeholder('Reason for visit')
                                ->columnSpanFull(),
                            TextInput::make('claim_check_code')
                                ->label('NHIS Claim Check Code')
                                ->rules(['nullable', 'regex:/^([A-Za-z0-9]{5}|[A-Za-z0-9]{13})$/'])
                                ->helperText('Dial *842# from an authorized facility phone number and select option 1 ("Generate Claim Code"). Enter the 5-character (non-biometric) or 13-character (biometric) code returned.')
                                ->visible(function (Get $get): bool {
                                    $coverage = $get('coverage_type');

                                    return $coverage === CoverageType::NHIS || $coverage === CoverageType::NHIS->value;
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                ->disabled(fn (): bool => $this->hasOpenEncounter())
                ->statePath('encounterFormData'),
            'adtAdmitForm' => $this->makeSchema()
                ->schema([
                    Select::make('ward_id')
                        ->label('Ward / Room')
                        ->options(fn (): array => $this->getWardOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('bed_id', null)),
                    Select::make('bed_id')
                        ->label('Bed')
                        ->options(fn (callable $get): array => $this->getAvailableBedOptions($get('ward_id')))
                        ->searchable()
                        ->required()
                        ->disabled(fn (callable $get) => blank($get('ward_id'))),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->statePath('adtFormData'),
            'adtTransferInternalForm' => $this->makeSchema()
                ->schema([
                    Select::make('transfer_ward_id')
                        ->label('Destination ward / room')
                        ->options(fn (): array => $this->getWardOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('transfer_bed_id', null)),
                    Select::make('transfer_bed_id')
                        ->label('Destination bed')
                        ->options(fn (callable $get): array => $this->getAvailableBedOptions($get('transfer_ward_id')))
                        ->searchable()
                        ->required()
                        ->disabled(fn (callable $get) => blank($get('transfer_ward_id'))),
                    RichEditor::make('transfer_notes')
                        ->label('Notes')
                        ->toolbarButtons([
                            'bold',
                            'bulletList',
                            'italic',
                            'orderedList',
                        ]),
                ])
                ->statePath('adtFormData'),
            'adtTransferOutForm' => $this->makeSchema()
                ->schema([
                    Select::make('destination_type')
                        ->label('Destination type')
                        ->options([
                            AdtDestinationType::Branch->value => AdtDestinationType::Branch->getLabel(),
                            AdtDestinationType::ExternalFacility->value => AdtDestinationType::ExternalFacility->getLabel(),
                        ])
                        ->default(AdtDestinationType::ExternalFacility->value)
                        ->live()
                        ->required(),
                    Select::make('destination_branch_id')
                        ->label('Destination branch')
                        ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->visible(fn (callable $get) => $get('destination_type') === AdtDestinationType::Branch->value)
                        ->required(fn (callable $get) => $get('destination_type') === AdtDestinationType::Branch->value),
                    TextInput::make('destination_label')
                        ->label('Destination facility')
                        ->visible(fn (callable $get) => $get('destination_type') === AdtDestinationType::ExternalFacility->value)
                        ->required(fn (callable $get) => $get('destination_type') === AdtDestinationType::ExternalFacility->value),
                    RichEditor::make('transfer_out_notes')
                        ->label('Notes')
                        ->toolbarButtons([
                            'bold',
                            'bulletList',
                            'italic',
                            'orderedList',
                        ]),
                ])
                ->statePath('adtFormData'),
            'adtTransferInForm' => $this->makeSchema()
                ->schema([
                    Select::make('admission_source')
                        ->label('Admission source')
                        ->options([
                            'local' => 'Local (Admit from Consultation / ER)',
                            'transfer_in' => 'Transfer In (Referral from other Branch / Hospital)',
                        ])
                        ->default('local')
                        ->live()
                        ->required(),
                    TextInput::make('source_label')
                        ->label('Transferring facility')
                        ->placeholder('Hospital or clinic name')
                        ->visible(fn (callable $get) => $get('admission_source') === 'transfer_in'),
                    Select::make('from_branch_id')
                        ->label('From branch (same org)')
                        ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->visible(fn (callable $get) => $get('admission_source') === 'transfer_in'),
                    Textarea::make('transfer_in_chief_complaint')
                        ->label('Chief complaint')
                        ->rows(2),
                    Select::make('transfer_in_ward_id')
                        ->label('Ward / Room')
                        ->required()
                        ->options(fn (): array => $this->getWardOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('transfer_in_bed_id', null)),
                    Select::make('transfer_in_bed_id')
                        ->label('Bed')
                        ->options(fn (callable $get): array => $this->getAvailableBedOptions($get('transfer_in_ward_id')))
                        ->searchable()
                        ->required()
                        ->disabled(fn (callable $get) => blank($get('transfer_in_ward_id'))),
                    RichEditor::make('transfer_in_notes')
                        ->label('Notes')
                        ->toolbarButtons([
                            'bold',
                            'bulletList',
                            'italic',
                            'orderedList',
                        ]),
                ])
                ->statePath('adtFormData'),
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
            'medicationForm' => $this->makeSchema()
                ->schema([
                    Repeater::make('items')
                        ->minItems(1)
                        ->columns(2)
                        ->schema([
                            Select::make('service_id')
                                ->label('Medication')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    return collect(app(DrugSearchService::class)->search($search, 10))
                                        ->mapWithKeys(function (array $result): array {
                                            if (filled($result['service_id'])) {
                                                return [
                                                    (string) $result['service_id'] => '[Catalog] '.$result['display_name'],
                                                ];
                                            }

                                            if (filled($result['drug_id'])) {
                                                $prefix = $result['source_provider'] === 'local' ? '[Reference] ' : '[External] ';

                                                return [
                                                    'drug:'.$result['drug_id'] => $prefix.$result['display_name'],
                                                ];
                                            }

                                            if (filled($result['medication_id'])) {
                                                return [
                                                    'medication:'.$result['medication_id'] => $result['display_name'],
                                                ];
                                            }

                                            return [];
                                        })
                                        ->all();
                                })
                                ->getOptionLabelUsing(function ($value): ?string {
                                    if (str_starts_with($value, 'drug:')) {
                                        $drugId = str($value)->after('drug:')->toString();
                                        $drug = Drug::query()->find($drugId);

                                        if (! $drug) {
                                            return $value;
                                        }

                                        $prefix = $drug->source_provider === 'local' ? '[Reference] ' : '[External] ';

                                        return $prefix.$drug->display_name;
                                    }

                                    if (str_starts_with($value, 'medication:')) {
                                        $medicationId = str($value)->after('medication:')->toString();
                                        $medication = Medication::find($medicationId);

                                        return $medication?->service?->name ?? $medication?->generic_name ?? $value;
                                    }

                                    return Service::find($value)?->name;
                                })
                                ->createOptionForm([
                                    TextInput::make('generic_name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('brand_name')
                                        ->maxLength(255),
                                    TextInput::make('strength')
                                        ->maxLength(255),
                                    Select::make('dosage_form')
                                        ->options(DosageForm::class)
                                        ->default(DosageForm::TABLET),
                                    TextInput::make('price')
                                        ->label('Price (Cash)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix(config('core.default_currency_symbol', 'GHS'))
                                        ->placeholder('0.00')
                                        ->default(0),
                                ])
                                ->createOptionUsing(function (array $data): string {
                                    return app(MedicationService::class)->createWithService($data)->service_id;
                                }),
                            TextInput::make('dosage')
                                ->label('Dosage')
                                ->placeholder('e.g. 500mg'),
                            Select::make('frequency')
                                ->label('Frequency')
                                ->options(MedicationFrequency::class)
                                ->searchable(),
                            Select::make('route')
                                ->label('Route')
                                ->options(MedicationRoute::class)
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
            'diagnosisForm' => $this->makeSchema()
                ->schema(EncounterDiagnosisForm::quickElements())
                ->statePath('diagnosisFormData'),
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
            'noteForm' => $this->makeSchema()
                ->schema(ClinicalNoteForm::quickElements())
                ->statePath('noteFormData'),
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
        ], $this->registerPatientManagementForms());
    }

    public function getClinicianTabs(): array
    {
        $tabs = [
            'notes' => [
                'label' => 'Notes',
                'icon' => 'heroicon-m-document-text',
            ],
            'diagnosis' => [
                'label' => 'Diagnosis',
                'icon' => 'heroicon-m-document-magnifying-glass',
            ],
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
            'adt' => [
                'label' => 'ADT',
                'icon' => 'heroicon-m-arrows-right-left',
            ],
            'referral' => [
                'label' => 'Referral',
                'icon' => 'heroicon-m-arrow-path',
            ],
            'history' => [
                'label' => 'History',
                'icon' => 'heroicon-m-clock',
            ],
        ];

        $tabs = $this->filterAdtTab($tabs);
        $tabs = $this->filterNotesTab($tabs);
        $tabs = $this->filterDiagnosisTab($tabs);
        $tabs = $this->prependEncounterTab($tabs);

        return $this->prependPatientDetailsTab($tabs);
    }

    public function getNurseTabs(): array
    {
        $tabs = [
            'encounter' => [
                'label' => 'Encounter',
                'icon' => 'heroicon-m-plus-circle',
            ],
            'notes' => [
                'label' => 'Notes',
                'icon' => 'heroicon-m-document-text',
            ],
            'adt' => [
                'label' => 'ADT',
                'icon' => 'heroicon-m-arrows-right-left',
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
            'history' => [
                'label' => 'History',
                'icon' => 'heroicon-m-clock',
            ],
        ];

        return $this->prependPatientDetailsTab($this->filterNotesTab($this->filterAdtTab($tabs)));
    }

    /**
     * @param  array<string, array{label: string, icon?: string}>  $tabs
     * @return array<string, array{label: string, icon?: string}>
     */
    protected function filterAdtTab(array $tabs): array
    {
        if (! $this->canAccessAdtTab()) {
            unset($tabs['adt']);
        }

        return $tabs;
    }

    /**
     * @param  array<string, array{label: string, icon?: string}>  $tabs
     * @return array<string, array{label: string, icon?: string}>
     */
    protected function filterNotesTab(array $tabs): array
    {
        if (! $this->canAccessNotesTab()) {
            unset($tabs['notes']);
        }

        return $tabs;
    }

    /**
     * @param  array<string, array{label: string, icon?: string}>  $tabs
     * @return array<string, array{label: string, icon?: string}>
     */
    protected function filterDiagnosisTab(array $tabs): array
    {
        if (! $this->canAccessDiagnosisTab()) {
            unset($tabs['diagnosis']);
        }

        return $tabs;
    }

    public function getLabTabs(): array
    {
        $tabs = [
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

        return $this->prependPatientDetailsTab($tabs);
    }

    #[Computed]
    public function latestVitals(): ?object
    {
        return $this->currentPatient ? $this->workspaceService?->getLatestVitals() : null;
    }

    #[Computed]
    public function pendingLabItems(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        return RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->currentPatient->id))
            ->whereDoesntHave('prescriptionDetail')
            ->with(['service', 'serviceRequest.orderedBy', 'service.category'])
            ->get()
            ->toArray();
    }

    /**
     * The Diagnostics module owns the completed-result table, so Clinical only renders it when that
     * module is installed and the user may see fulfillments; otherwise the simple list is used.
     *
     * @return class-string|null
     */
    public function completedResultsWidget(): ?string
    {
        /** @var class-string $widget */
        $widget = 'Modules\\Diagnostics\\Filament\\Widgets\\CompletedDiagnosticResultsWidget';

        if (! class_exists($widget) || ! method_exists($widget, 'canView')) {
            return null;
        }

        return $widget::canView() ? $widget : null;
    }

    #[Computed]
    public function completedLabItems(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        return RequestItem::query()
            ->where('status', 'completed')
            ->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->currentPatient->id))
            ->whereDoesntHave('prescriptionDetail')
            ->with(['service', 'serviceRequest.orderedBy', 'service.category'])
            ->latest()
            ->limit(20)
            ->get()
            ->toArray();
    }

    #[Computed]
    public function pastEncounters(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $encounters = Encounter::where('patient_id', $this->currentPatient->id)
            // ->when($this->currentEncounter, fn ($q) => $q->where('id', '!=', $this->currentEncounter->id))
            ->with([
                'patient',
                'vitalSigns' => fn ($q) => $q->latest('recorded_at')->take(1),
                'clinicalNotes' => fn ($q) => $q->latest()->take(1),
            ])
            ->latest()
            ->limit(20)
            ->get();

        if ($encounters->isEmpty()) {
            return [];
        }

        $encounterIds = $encounters->pluck('id');

        $diagnoses = EncounterDiagnosis::whereIn('encounter_id', $encounterIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('encounter_id');

        $medications = RequestItem::whereHas('serviceRequest', fn ($q) => $q->whereIn('encounter_id', $encounterIds))
            ->whereHas('prescriptionDetail')
            ->with(['service', 'serviceRequest', 'prescriptionDetail'])
            ->get()
            ->groupBy(fn ($item) => $item->serviceRequest->encounter_id);

        return $encounters->map(function ($encounter) use ($diagnoses, $medications) {
            $latestVitals = $encounter->vitalSigns->first();
            $latestNote = $encounter->clinicalNotes->first();
            $encounterDiagnoses = $diagnoses->get($encounter->id, collect());
            $encounterMeds = $medications->get($encounter->id, collect());

            return [
                'id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'encounter_number' => $encounter->encounter_number,
                'type' => $encounter->type?->getLabel(),
                'status' => $encounter->status?->getLabel(),
                'status_color' => $encounter->status?->getColor(),
                'coverage' => $encounter->coverage_type?->getLabel(),
                'coverage_color' => $encounter->coverage_type?->getColor(),
                'date' => $encounter->created_at?->diffForHumans(),
                'created_at' => $encounter->created_at?->toDateTimeString(),
                'vitals' => $latestVitals ? [
                    'bp' => $latestVitals->systolic_bp && $latestVitals->diastolic_bp
                        ? $latestVitals->systolic_bp.'/'.$latestVitals->diastolic_bp : null,
                    'hr' => $latestVitals->heart_rate,
                    'temp' => $latestVitals->temperature,
                    'spo2' => $latestVitals->spo2,
                    'rr' => $latestVitals->respiratory_rate,
                ] : null,
                'diagnoses' => $encounterDiagnoses->map(fn ($dx) => [
                    'code' => $dx->icd_code,
                    'label' => $dx->description,
                ])->toArray(),
                'medications' => $encounterMeds->map(fn ($item) => [
                    'name' => $item->service?->name ?? 'Unknown',
                    'dosage' => $item->prescriptionDetail?->dosage,
                    'frequency' => $item->prescriptionDetail?->frequency,
                    'route' => $item->prescriptionDetail?->route,
                ])->toArray(),
                'note_preview' => $latestNote?->summary,
            ];
        })->toArray();
    }
}
