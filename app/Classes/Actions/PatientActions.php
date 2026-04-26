<?php

namespace Modules\Clinical\Classes\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Schemas\AllergyForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas\ClinicalNoteForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignForm;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\VitalSign;
use Modules\Patient\Models\Patient;
use Modules\Patient\Policies\PatientPolicy;

class PatientActions
{
    public function __construct(
        protected AllergyService $allergyService,
        protected VitalSignService $vitalSignService,
        protected ClinicalNoteService $clinicalNoteService,
        protected ServiceRequestService $serviceRequestService,
        protected EncounterService $encounterService
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
            app(EncounterService::class)
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
            $this->timelineAction(),
        ];
    }

    public function forPatient(Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function withEncounter(int|string|null $encounterId): static
    {
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
            ->action(fn (array $data) => $this->allergyService->record(
                $this->patient,
                $data
            ))
            ->successNotificationTitle('Allergy recorded');
    }

    public function vitals(): Action
    {
        return Action::make('vitals')
            ->label('Record Vitals')
            ->icon('heroicon-m-heart')
            ->model(VitalSign::class)
            ->slideOver()
            ->schema(fn ($schema) => VitalSignForm::quickElements())
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
            ->schema(fn ($schema) => ServiceRequestForm::quickElements())
            ->mutateDataUsing(fn (array $data): array => $this->injectServiceRequestData($data))
            ->action(fn (array $data) => $this->serviceRequestService->record(
                $this->patient,
                $data,
                $this->encounterId
            ))
            ->successNotificationTitle('Service request created');
    }

    public function encounter(): Action
    {
        return Action::make('encounter')
            ->label('New Encounter')
            ->icon('heroicon-m-plus-circle')
            ->model(Encounter::class)
            ->slideOver()
            ->schema(fn ($schema) => EncounterForm::quickElements())
            ->mutateDataUsing(fn (array $data): array => $this->injectEncounterData($data))
            ->action(fn (array $data) => $this->createEncounter($data))
            ->successNotificationTitle('Encounter created successfully');
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
            type: EncounterType::from($data['type']),
            chiefComplaint: $data['chief_complaint'] ?? null,
            priority: isset($data['priority'])
                ? EncounterPriority::from($data['priority'])
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
