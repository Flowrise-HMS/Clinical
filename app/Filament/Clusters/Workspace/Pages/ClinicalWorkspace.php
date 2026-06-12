<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Classes\Services\DiagnosisService;
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
use Modules\Clinical\Filament\Widgets\PatientVitalsHistoryWidget;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Core\Models\Service;
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

    public string $diagnosisNotes = '';

    public array $vitalsData = [];

    public array $serviceRequestData = [];

    public array $allergyData = [];

    public array $consultationData = [];

    public array $medicationData = [];

    public array $encounterFormData = [];

    public array $dischargeData = [];

    public array $referralData = [];

    protected ?ClinicalWorkspaceService $workspaceService = null;

    protected ?PatientSearchService $patientSearchService = null;

    protected ?VitalSignService $vitalSignService = null;

    protected ?ClinicalNoteService $clinicalNoteService = null;

    protected ?ServiceRequestService $serviceRequestService = null;

    protected ?EncounterService $encounterService = null;

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
        $this->allergyService ??= app(AllergyService::class);
        $this->diagnosisService ??= app(DiagnosisService::class);

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
        $this->encounterFormData = [];
        $this->consultationChiefComplaint = '';
        $this->consultationNotes = '';
        $this->consultationData = [];
        $this->diagnosisCodes = [];
        $this->diagnosisNotes = '';
        $this->vitalsData = [];
        $this->serviceRequestData = [];
        $this->allergyData = [];
        $this->medicationData = [];
        $this->dischargeData = [];
        $this->referralData = [];
    }

    protected function loadPatientContext(): void
    {
        if (! $this->patientId) {
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

            if ($this->currentEncounter) {
                $existing = $this->diagnosisService->getForEncounter($this->currentEncounter->id);
                $this->diagnosisCodes = array_map(fn ($dx) => $dx['code']
                    ? $dx['code'].' - '.$dx['label']
                    : $dx['label'], $existing['diagnoses']);
                $this->diagnosisNotes = $existing['notes'];
            }
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
            $widgets[] = PendingFulfillmentsWidget::make(['patientId' => $this->currentPatient?->id,'encounterId' => $this->currentEncounter?->id]);
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

        $this->clinicalNoteService->record(
            $this->currentPatient,
            [
                'note_type' => NoteType::CONSULTATION,
                'status' => NoteStatus::DRAFT,
                'subject' => 'Consultation - '.($this->consultationChiefComplaint ?: 'General'),
                'content' => $this->consultationData['notes'] ?? '',
            ],
            $this->currentEncounter->id,
        );

        if ($this->consultationChiefComplaint && ! $this->currentEncounter->chief_complaint) {
            $this->currentEncounter->update(['chief_complaint' => $this->consultationChiefComplaint]);
        }

        Notification::make()->title('Consultation saved')->success()->send();
    }

    public function saveVitals(): void
    {
        if (! $this->currentPatient) {
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
        if (! $this->currentPatient) {
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
        if (! $this->currentPatient) {
            return;
        }

        $this->allergyService->record($this->currentPatient, $this->allergyData);

        $this->allergyData = [];
        $this->loadPatientContext();
        Notification::make()->title('Allergy recorded')->success()->send();
    }

    public function createEncounter(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        if ($this->currentEncounter?->isActive()) {
            Notification::make()
                ->title('Active encounter exists')
                ->body('This patient already has an active encounter. Switch to the Vitals tab to continue.')
                ->warning()
                ->send();

            return;
        }

        $encounter = $this->encounterService->createForPatient(
            patient: $this->currentPatient,
            type: EncounterType::OUTPATIENT,
            chiefComplaint: $this->encounterFormData['chief_complaint'] ?? null,
            priority: EncounterPriority::ROUTINE,
        );

        if ($coverage = $this->encounterFormData['coverage_type'] ?? null) {
            $encounter->update(['coverage_type' => $coverage]);
        }

        $this->currentEncounter = $encounter->fresh();
        $this->encounterFormData = [];
        $this->activeTab = 'vitals';
        Notification::make()->title('OPD encounter created')->success()->send();
    }

    public function saveDiagnoses(): void
    {
        if (! $this->currentEncounter || empty($this->diagnosisCodes)) {
            Notification::make()->title('No diagnoses to save')->warning()->send();

            return;
        }

        $items = [];
        foreach ($this->diagnosisCodes as $tag) {
            $parts = explode(' - ', $tag, 2);
            $code = DiagnosisCode::where('code', $parts[0])->first();

            if ($code && $code->code.' - '.$code->description === $tag) {
                $items[] = ['id' => $code->id, 'label' => $code->description];
            } else {
                $items[] = ['id' => null, 'label' => $tag];
            }
        }

        $this->diagnosisService->record(
            $this->currentPatient,
            $items,
            $this->currentEncounter->id,
            Auth::id(),
            $this->diagnosisNotes ?: null,
        );

        $this->loadPatientContext();
        Notification::make()->title('Diagnoses saved')->success()->send();
    }

    public function saveLabResult(): void
    {
        if (! $this->currentPatient || ! $this->serviceRequestData['request_item_id'] ?? null) {
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
        if (! $this->currentPatient || ! $this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        if (empty($this->medicationData['items'])) {
            Notification::make()->title('Add at least one medication')->warning()->send();

            return;
        }

        try {
            $service = app(MedicationOrderService::class);
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
        if (! $this->currentEncounter) {
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
        if (! $this->currentPatient || ! $this->currentEncounter) {
            Notification::make()->title('No active encounter')->danger()->send();

            return;
        }

        try {
            $this->clinicalNoteService->record(
                $this->currentPatient,
                [
                    'note_type' => NoteType::CONSULTATION,
                    'status' => NoteStatus::DRAFT,
                    'subject' => 'Referral - '.($this->referralData['destination'] ?? 'Unspecified'),
                    'content' => ($this->referralData['notes'] ?? '')
                        ."\n\nDestination: ".($this->referralData['destination'] ?? ''),
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
            'encounterForm' => $this->makeSchema()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('coverage_type')
                                ->label('Coverage Type')
                                ->default('none')
                                ->options([
                                    'nhis' => 'NHIS',
                                    'private' => 'Private Insurance',
                                    'none' => 'Cash',
                                ])
                                ->required()
                                ->native(false),
                        ]),
                ])
                ->statePath('encounterFormData'),
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
                ->schema([
                    TagsInput::make('diagnosisCodes')
                        ->label('Add Diagnoses')
                        ->placeholder('Type or select ICD codes...')
                        ->trim()
                        ->suggestions(fn () => DiagnosisCode::where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->map(fn ($code) => $code->code.' - '.$code->description)
                            ?->toArray()),
                    RichEditor::make('diagnosisNotes')
                        ->label('Assessment / Notes')
                        ->placeholder('Add notes, assessment, or plan for these diagnoses...')
                        ->toolbarButtons([
                            'bold',
                            'bulletList',
                            'italic',
                            'orderedList',
                        ]),
                ]),
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
            'history' => [
                'label' => 'History',
                'icon' => 'heroicon-m-clock',
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
            'history' => [
                'label' => 'History',
                'icon' => 'heroicon-m-clock',
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
        if (!$this->currentPatient) {
            return [];
        }

        $encounters = Encounter::where('patient_id', $this->currentPatient->id)
            // ->when($this->currentEncounter, fn ($q) => $q->where('id', '!=', $this->currentEncounter->id))
            ->with([
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
                        ? $latestVitals->systolic_bp . '/' . $latestVitals->diastolic_bp : null,
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
                'note_preview' => $latestNote ? strip_tags($latestNote->content) : null,
            ];
        })->toArray();
    }
}
