<?php

namespace Modules\Clinical\Classes\Actions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Classes\Services\DiagnosisCodeService;
use Modules\Clinical\Classes\Services\DiagnosisService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Schemas\AllergyForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas\ClinicalNoteForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignForm;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\VitalSign;
use Modules\Clinical\Policies\AllergyPolicy;
use Modules\Clinical\Policies\ClinicalNotePolicy;
use Modules\Clinical\Policies\EncounterPolicy;
use Modules\Clinical\Policies\ServiceRequestPolicy;
use Modules\Clinical\Policies\VitalSignPolicy;
use Modules\Patient\Models\Patient;
use Modules\Patient\Policies\PatientPolicy;

class PatientActions
{
    public function __construct(
        protected AllergyService $allergyService,
        protected VitalSignService $vitalSignService,
        protected ClinicalNoteService $clinicalNoteService,
        protected ServiceRequestService $serviceRequestService,
        protected EncounterService $encounterService,
        protected FulfillmentService $fulfillmentService,
        protected MedicationAdministrationService $medicationAdminService,
        protected BedAssignmentService $bedAssignmentService,
        protected DiagnosisService $diagnosisService,
    ) {}

    protected ?Patient $patient = null;

    protected int|string|null $encounterId = null;

    public static function make(): static
    {
        return new static(
            app(AllergyService::class),
            app(VitalSignService::class),
            app(ClinicalNoteService::class),
            app(ServiceRequestService::class),
            app(EncounterService::class),
            app(FulfillmentService::class),
            app(MedicationAdministrationService::class),
            app(BedAssignmentService::class),
            app(DiagnosisService::class),
        );
    }

    public function timelineSubQuickActions()
    {
        if (! $this->patient) {
            return [];
        }

        return [
            $this->timelineAction(),
        ];
    }

    public function timelineQuickActions()
    {
        if (! $this->patient) {
            return [];
        }

        return [
            $this->profileAction(),
            $this->patientActionGroups(),
        ];
    }

    public function patientActionGroups()
    {
        return ActionGroup::make([
            $this->printHospitalCardAction(),
            $this->encounter(),
            $this->cancelEncounterAction(),
            $this->assignToWardAction(),
            $this->medicationAdminAction(),
            $this->fulfillServiceAction(),
            $this->note(),
            $this->order(),
            $this->medicationOrder(),
            $this->diagnosis(),
            $this->vitals(),
            $this->allergy(),
        ])
            ->label('More Actions')
            ->icon('heroicon-m-ellipsis-vertical')
            ->size('sm')
            ->color('primary')
            ->button();
    }

    public function forPatient(Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function withEncounter(object|string|null $encounterId): static
    {
        if(is_object($encounterId) &&  $encounterId instanceof Encounter){
            $this->encounterId = $encounterId?->id;
            return $this;
        }
        $this->encounterId = $encounterId;

        return $this;
    }

    public function allergy(): Action
    {
        return Action::make('allergy')
            ->label('Add Allergy')
            ->icon('heroicon-m-exclamation-triangle')
            ->model(Allergy::class)
            ->slideOver()
            ->schema(fn ($schema) => AllergyForm::quickElements())
            ->mutateDataUsing(fn (array $data): array => $this->injectAllergyData($data))
            ->visible(fn() => app(AllergyPolicy::class)->create(Auth::user()))
            ->action(fn (array $data) => $this->allergyService->record(
                $this->patient,
                $data
            ))
            ->successNotificationTitle('Allergy recorded');
    }

    public function diagnosis(): Action
    {
        return Action::make('diagnosis')
            ->label('Add Diagnosis')
            ->icon('heroicon-m-document-text')
            ->model(EncounterDiagnosis::class)
            ->slideOver()
            ->closeModalByClickingAway(false)
            ->schema([
                Select::make('diagnosis_code_id')
                    ->label('ICD Code')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        return app(DiagnosisCodeService::class)->search($search, limit: 10)
                            ->mapWithKeys(fn ($code) => [
                                $code->id => $code->code . ' - ' . $code->description,
                            ]);
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $code = DiagnosisCode::find($value);
                        return $code ? $code->code . ' - ' . $code->description : null;
                    })
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (! $state) {
                            return;
                        }
                        $code = DiagnosisCode::find($state);
                        if ($code) {
                            $set('description', $code->description);
                        }
                    }),
                TextInput::make('description')
                    ->label('Diagnosis Name')
                    ->placeholder('Or type a custom diagnosis name...')
                    ->requiredWithout('diagnosis_code_id'),
                Select::make('type')
                    ->options([
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'complication' => 'Complication',
                    ])
                    ->default('primary')
                    ->required(),
            ])
            ->visible(fn () => $this->patient !== null && $this->encounterId !== null)
            ->action(function (array $data) {
                if (! $this->patient) {
                    return;
                }

                if (! $this->encounterId) {
                    Notification::make()
                        ->title('No active encounter')
                        ->body('A diagnosis requires an active encounter.')
                        ->warning()
                        ->send();
                    return;
                }

                $description = $data['description'];
                $diagnosisCodeId = null;

                if ($data['diagnosis_code_id']) {
                    $code = DiagnosisCode::find($data['diagnosis_code_id']);
                    if ($code) {
                        $diagnosisCodeId = $code->id;
                        $description = $description ?: $code->description;
                    }
                }

                if (! $description) {
                    return;
                }

                $this->diagnosisService->record(
                    $this->patient,
                    [
                        [
                            'id' => $diagnosisCodeId,
                            'label' => $description,
                        ],
                    ],
                    $this->encounterId,
                    Auth::id(),
                );
            })
            ->successNotificationTitle('Diagnosis added');
    }

    public function vitals(): Action
    {
        return Action::make('vitals')
            ->label('Record Vitals')
            ->icon('heroicon-m-heart')
            ->model(VitalSign::class)
            ->slideOver()
            ->schema(fn () => VitalSignForm::quickElements())
            ->visible(fn() => app(VitalSignPolicy::class)->create(Auth::user()))
            ->mutateDataUsing(fn (array $data): array => $this->injectVitalSignData($data))
            ->action(fn (array $data) => $this->vitalSignService->record(
                $this->patient,
                $data,
                $this->encounterId
            ))
            ->successNotificationTitle('Vital signs recorded');
    }

    public function profileAction(): Action
    {
        return Action::make('view_profile')
            ->label('View Full Profile')
            ->icon('heroicon-m-user-circle')
            ->record($this->patient)
            ->visible(fn($record) => app(PatientPolicy::class)->view(Auth::user(), $record))
            ->url(fn ($record) => PatientProfile::getUrl(['patient' => $record?->id]), shouldOpenInNewTab: true)
            ->color('gray');
    }

    public function timelineAction(): Action
    {
        return Action::make('view_timeline')
            ->label('Timeline')
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->record($this->patient)
            ->visible(fn($record) => app(PatientPolicy::class)->view(Auth::user(), $record))
            ->url(fn ($record) => Timeline::getUrl(['patient' => $record?->id]), shouldOpenInNewTab: true);
    }

    public function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('Deactivate')
            ->icon('heroicon-o-user-minus')
            ->color('warning')
            ->record($this->patient)
            ->action(fn ($record) => $record->update(['is_active' => false]))
            ->visible(fn ($record) => $record->is_active && app(PatientPolicy::class)->update(Auth::user(), $record))
            ->requiresConfirmation()
            ->modalHeading('Deactivate Patient?')
            ->modalDescription('This patient will no longer be able to access services. You can reactivate them later.');
    }

    public function note(): Action
    {
        return Action::make('note')
            ->label('Add Note')
            ->icon('heroicon-m-document-text')
            ->model(ClinicalNote::class)
            ->slideOver()
            ->schema(fn ($schema) => ClinicalNoteForm::quickElements())
            ->visible(fn($record) => app(ClinicalNotePolicy::class)->create(Auth::user()))
            ->mutateDataUsing(fn (array $data): array => $this->injectClinicalNoteData($data))
            ->action(fn (array $data) => $this->clinicalNoteService->record(
                $this->patient,
                $data,
                $this->encounterId
            ))
            ->successNotificationTitle('Clinical note created');
    }

    public function order(): Action
    {
        return Action::make('order')
            ->label('New Order')
            ->icon('heroicon-m-clipboard-document-list')
            ->slideOver()
            ->model(ServiceRequest::class)
            ->schema(fn ($schema) => ServiceRequestForm::quickElements(hidenEncounter: true))
            ->mutateDataUsing(fn (array $data): array => $this->injectServiceRequestData($data))
            ->visible(fn($record) => app(ServiceRequestPolicy::class)->create(Auth::user()))
            ->action(fn (array $data) => $this->serviceRequestService->record(
                $this->patient,
                $data,
                $this->encounterId
            ))
            ->successNotificationTitle('Service request created');
    }

    public function medicationOrder(): Action
    {
        $medicationOrderAction = 'Modules\\Pharmacy\\Classes\\Actions\\MedicationOrderAction';

        if (! class_exists($medicationOrderAction)) {
            return Action::make('medication_order')
                ->label('Medication Order')
                ->icon('heroicon-m-beaker')
                ->disabled()
                ->tooltip('Pharmacy module is not available.');
        }

        return $medicationOrderAction::make($this->patient, $this->encounterId);
    }

    public function medicationAdminAction(): Action
    {
        $policy = app(MedicationFulfillmentPolicy::class);

        return Action::make('medication_admin')
            ->label('Administer Medications')
            ->icon('heroicon-m-beaker')
            ->color('success')
            ->slideOver()
            ->visible(function () use ($policy): bool {
                if (! $this->patient) {
                    return false;
                }

                return RequestItem::query()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->patient->id))
                    ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', 'in_facility'))
                    ->get()
                    ->contains(fn (RequestItem $item) => $policy->canRecordMar($item));
            })
            ->modalHeading(fn (): string => 'Administer Medications — '.($this->patient?->full_name ?? 'Unknown'))
            ->modalSubmitActionLabel('Administer Selected')
            ->schema(function (): array {
                $items = $this->medicationAdminService->getPendingItems($this->patient?->id, true);

                if ($items->isEmpty()) {
                    return [];
                }

                $schemas = [];
                foreach ($items as $item) {
                    $schemas[] = \Filament\Schemas\Components\Fieldset::make($item->service?->name ?? 'Medication')
                        ->schema([
                            ...MarRecordDoseFormSchema::forSingleItem($item, true),
                        ])
                        ->statePath('administrations.'.$item->id);
                }

                $schemas[] = Textarea::make('notes')->label('Notes')->rows(3);

                return $schemas;
            })
            ->action(function (array $data): void {
                $administrations = [];
                foreach ($data['administrations'] ?? [] as $itemId => $admin) {
                    $admin['request_item_id'] = $itemId;
                    $admin['selected'] = true;
                    $administrations[] = $admin;
                }

                $result = $this->medicationAdminService->administerBatch(
                    $administrations,
                    $data['notes'] ?? null
                );

                if (! empty($result['errors'])) {
                    Notification::make()
                        ->title('Some items could not be administered')
                        ->body(implode("\n", $result['errors']))
                        ->danger()
                        ->persistent()
                        ->send();
                }

                if (! empty($result['created'])) {
                    Notification::make()
                        ->title('Medications administered')
                        ->body(implode(', ', $result['created']))
                        ->success()
                        ->send();
                }
            });
    }

    public function fulfillServiceAction(): Action
    {
        return Action::make('fulfill_service')
            ->label('Fulfill Service')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->slideOver()
            ->visible(function (): bool {
                if (! $this->patient) {
                    return false;
                }

                return RequestItem::query()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->patient->id))
                    ->whereDoesntHave('prescriptionDetail')
                    ->exists();
            })
            ->modalHeading(fn (): string => 'Fulfill Service — ' . ($this->patient?->full_name ?? 'Unknown'))
            ->modalSubmitActionLabel('Submit')
            ->schema(function (): array {
                $items = RequestItem::query()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->patient?->id))
                    ->whereDoesntHave('prescriptionDetail')
                    ->with(['service', 'serviceRequest.orderedBy', 'service.category'])
                    ->get();

                if ($items->isEmpty()) {
                    return [];
                }

                $options = $items->pluck('service.name', 'id')->toArray();

                $schema = [
                    Select::make('request_item_id')
                        ->label('Service')
                        ->options($options)
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $this->updateFulfillmentForm($state, $set)),
                ];

                $schema[] = DateTimePicker::make('started_at')->label('Started At')->default(now());
                $schema[] = DateTimePicker::make('ended_at')->label('Ended At')->default(now());
                $schema[] = FileUpload::make('result_files')
                    ->label('Result Files (PDF, Images)')
                    ->multiple()
                    ->directory('diagnostics/results')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240);
                $schema[] = Textarea::make('notes')->label('Notes')->rows(2);

                return $schema;
            })
            ->action(function (array $data): void {
                $item = RequestItem::find($data['request_item_id']);
                if (! $item) {
                    return;
                }

                unset($data['request_item_id']);

                try {
                    $this->fulfillmentService->fulfill($item, $data);

                    Notification::make()
                        ->title(($item->service?->name ?? 'Service') . ' fulfilled')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Fulfillment failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    protected function updateFulfillmentForm(string $itemId, callable $set): void
    {
        $item = RequestItem::with(['service.category', 'serviceRequest.orderedBy'])
            ->find($itemId);

        if (! $item) {
            return;
        }

        $type = $this->fulfillmentService->getType($item);
        $context = $this->fulfillmentService->getContextInfo($item);
        $contextHtml = view('clinical::clinical.fulfillment-context', $context)->render();

        $set('context', $contextHtml);
    }

    public function printHospitalCardAction(): Action
    {
        return Action::make('print_hospital_card')
            ->label(__('Hospital card'))
            ->icon('heroicon-m-identification')
            ->url(fn (): string => $this->patient
                ? route('patients.hospital-card', $this->patient)
                : '#')
            ->openUrlInNewTab()
            ->visible(fn (): bool => $this->patient !== null
                && (Auth::user()?->can('print_hospital_card') ?? false));
    }

    public function dischargeAction(): Action
    {
        $encounter = $this->patient?->activeEncounter()->first();

        if (! $encounter) {
            return Action::make('discharge_patient')->hidden();
        }

        return EncounterActions::discharge($encounter)
            ->name('discharge_patient')
            ->label('Discharge patient')
            ->visible(fn () => $encounter->canTransitionTo(EncounterStatus::FINISHED)
                && (Auth::user()?->can('discharge_patient') ?? false))
            ->successNotificationTitle('Patient discharged successfully');
    }

    public function encounter(): Action
    {
        return Action::make('encounter')
            ->label('New Encounter')
            ->icon('heroicon-m-plus-circle')
            ->model(Encounter::class)
            ->slideOver()
            ->schema(fn ($schema) => EncounterForm::quickElements())
            ->visible(fn (): bool => $this->patient !== null
                && ! $this->patient->activeEncounter()->exists()
                && app(EncounterPolicy::class)->create(Auth::user()))
            ->mutateDataUsing(fn (array $data): array => $this->injectEncounterData($data))
            ->action(fn (array $data) => $this->createEncounter($data))
            ->successNotificationTitle('Encounter created successfully');
    }

    public function cancelEncounterAction(): Action
    {
        $encounter = $this->patient?->activeEncounter()->first();

        if (! $encounter) {
            return Action::make('cancel_encounter')->hidden();
        }

        return EncounterActions::cancel($encounter)
            ->name('cancel_encounter')
            ->label('Cancel Encounter')
            ->visible(fn () => $encounter->canTransitionTo(EncounterStatus::CANCELLED))
            ->successNotificationTitle('Encounter cancelled');
    }

    public function assignToWardAction(): Action
    {
        $encounter = $this->patient?->activeEncounter()->first();

        if (! $encounter) {
            return Action::make('assign_to_ward')->hidden();
        }

        return EncounterActions::assignToWard(
            $encounter,
            $this->bedAssignmentService,
        )
            ->name('assign_to_ward')
            ->label('Assign to Ward / Bed')
            ->visible(fn () => $encounter->type === EncounterType::INPATIENT
                && ($encounter->canTransitionTo(EncounterStatus::ARRIVED) || $encounter?->status?->isActive())
                && Auth::user()->can('update', $encounter))
            ->successNotificationTitle('Patient assigned to ward/bed successfully');
    }

    protected function injectEncounterData(array $data): array
    {
        if ($this->patient) {
            $data['patient_id'] = $this->patient->id;
            $data['branch_id'] = $data['branch_id'] ?? $this->patient->branch_id;
        }

        $data['created_by'] = Auth::id();

        unset($data['guest_name'], $data['guest_phone'], $data['guest_email']);

        return $data;
    }

    protected function createEncounter(array $data): Encounter
    {
        return $this->encounterService->createForPatient(
            patient: $this->patient,
            type: ($data['type']),
            chiefComplaint: $data['chief_complaint'] ?? null,
            priority: isset($data['priority'])
                ? ($data['priority'])
                : null,
            locationId: $data['location_id'] ?? null,
            departmentId: $data['department_id'] ?? null,
            createdBy: $data['created_by'] ?? null,
        );
    }

    protected function injectAllergyData(array $data): array
    {
        if ($this->patient) {
            $data['patient_id'] = $this->patient->id;
        }

        $data['verified_by'] = Auth::id();

        return $data;
    }

    protected function injectVitalSignData(array $data): array
    {
        if ($this->patient) {
            $data['patient_id'] = $this->patient->id;
        }

        if ($this->encounterId) {
            $data['encounter_id'] = $this->encounterId;
        }

        $data['recorded_by'] = Auth::id();
        $data['recorded_at'] = $data['recorded_at'] ?? now()->toDateTimeString();

        return $data;
    }

    protected function injectClinicalNoteData(array $data): array
    {
        if ($this->patient) {
            $data['patient_id'] = $this->patient->id;
        }

        if ($this->encounterId) {
            $data['encounter_id'] = $this->encounterId;
        }

        $data['author_id'] = Auth::id();

        return $data;
    }

    protected function injectServiceRequestData(array $data): array
    {
        if ($this->patient) {
            $data['patient_id'] = $this->patient->id;
        }

        if ($this->encounterId) {
            $data['encounter_id'] = $this->encounterId;
        }

        $data['ordered_by'] = Auth::id();
        $data['created_by'] = Auth::id();

        return $data;
    }
}
