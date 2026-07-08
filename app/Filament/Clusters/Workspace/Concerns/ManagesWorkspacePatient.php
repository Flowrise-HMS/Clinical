<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Core\Classes\Services\BranchService;
use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Events\PatientRegistered;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientForm;
use Modules\Patient\Models\Patient;
use Modules\Patient\Policies\PatientPolicy;

trait ManagesWorkspacePatient
{
    public array $registerFormData = [];

    public array $patientFormData = [];

    public bool $postRegistrationFlow = false;

    public bool $confirmDuplicateRegistration = false;

    public int $registrationFormKey = 0;

    public function startRegistration(): void
    {
        Gate::authorize('create', Patient::class);

        $this->mode = 'register';
        $this->confirmDuplicateRegistration = false;
        $this->registrationFormKey++;
        $this->fillRegistrationFormDefaults();
        $this->prefillRegistrationFromSearch($this->searchTerm);
    }

    public function cancelRegistration(): void
    {
        $this->mode = 'home';
        $this->registerFormData = [];
        $this->confirmDuplicateRegistration = false;
    }

    public function prefillRegistrationFromSearch(string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        if (preg_match('/^[\d\s+\-()]+$/', $term)) {
            $this->registerFormData['phone'] = $term;

            return;
        }

        $parts = preg_split('/\s+/', $term, 2);

        if (count($parts) === 2) {
            $this->registerFormData['first_name'] = ucfirst(strtolower($parts[0]));
            $this->registerFormData['last_name'] = ucfirst(strtolower($parts[1]));
        } else {
            $this->registerFormData['last_name'] = ucfirst(strtolower($parts[0]));
        }
    }

    public function registerPatient(): void
    {
        Gate::authorize('create', Patient::class);

        $schema = $this->getSchema('registerPatientForm');
        $data = $schema->getState();
        $data = $this->mutateRegistrationData($data);

        if (empty($data['branch_id'])) {
            Notification::make()
                ->title('Branch required')
                ->body('Ensure your user account has a default branch configured.')
                ->danger()
                ->send();

            return;
        }

        $searchLabel = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        if (! $this->confirmDuplicateRegistration && $searchLabel !== '') {
            $similar = app(PatientSearchService::class)
                ->suggestSimilarPatients($searchLabel, 5);

            if ($similar->isNotEmpty()) {
                Notification::make()
                    ->title('Similar patients found')
                    ->body('Review the list below. Select an existing patient or confirm to create a new record.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $patient = new Patient;
        $patient->fill($data);
        $patient->save();

        $schema->model($patient)->saveRelationships();

        if (config('insurance.enabled', true) && app()->bound(PatientInsuranceService::class)) {
            app(PatientInsuranceService::class)->createPolicyFromData($patient->id, $data);
        }

        if (class_exists(PatientRegistered::class)) {
            event(new PatientRegistered($patient));
        }

        $this->confirmDuplicateRegistration = false;
        $this->registerFormData = [];

        Notification::make()
            ->title('Patient registered')
            ->body("{$patient->full_name} ({$patient->mrn})")
            ->success()
            ->send();

        $this->selectPatient($patient->id, fromRegistration: true);
    }

    public function confirmRegisterDespiteDuplicates(): void
    {
        $this->confirmDuplicateRegistration = true;
        $this->registerPatient();
    }

    public function savePatientDetails(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        Gate::authorize('update', $this->currentPatient);

        $schema = $this->getSchema('patientDetailsForm')->model($this->currentPatient);
        $data = $schema->getState();

        $this->currentPatient->update($data);

        $this->loadPatientContext();
        $this->fillPatientFormDataFromCurrentPatient();

        Notification::make()
            ->title('Patient details updated')
            ->success()
            ->send();
    }

    public function canCreatePatient(): bool
    {
        $user = Auth::user();

        return $user && app(PatientPolicy::class)->create($user);
    }

    public function canUpdateCurrentPatient(): bool
    {
        if (! $this->currentPatient) {
            return false;
        }

        $user = Auth::user();

        return $user && app(PatientPolicy::class)->update($user, $this->currentPatient);
    }

    #[Computed]
    public function similarPatientsForRegistration(): array
    {
        if ($this->mode !== 'register') {
            return [];
        }

        $first = $this->registerFormData['first_name'] ?? '';
        $last = $this->registerFormData['last_name'] ?? '';
        $label = trim("{$first} {$last}");

        if ($label === '' && filled($this->searchTerm)) {
            $label = $this->searchTerm;
        }

        if ($label === '') {
            return [];
        }

        return app(PatientSearchService::class)
            ->suggestSimilarPatients($label, 5)
            ->toArray();
    }

    protected function mutateRegistrationData(array $data): array
    {
        $data['branch_id'] = $data['branch_id'] ?? app(BranchService::class)->getDefaultBranchId();
        $data['created_by'] = Auth::id();

        if (function_exists('generate_global_uuid')) {
            $data['global_uuid'] = generate_global_uuid();
        }

        return $data;
    }

    protected function fillRegistrationFormDefaults(): void
    {
        if (! method_exists($this, 'getSchema')) {
            $this->registerFormData = $this->defaultRegistrationFormData();

            return;
        }

        $this->getSchema('registerPatientForm')
            ->model(new Patient)
            ->fill();

        $this->registerFormData['branch_id'] = $this->registerFormData['branch_id']
            ?? Auth::user()?->branch_id
            ?? app(BranchService::class)->getDefaultBranchId();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultRegistrationFormData(): array
    {
        return [
            'branch_id' => Auth::user()?->branch_id ?? app(BranchService::class)->getDefaultBranchId(),
            'preferred_language' => 'english',
            'nationality' => config('core.default_country_code', 'GH'),
            'is_date_of_birth_estimated' => false,
            'is_student' => false,
            'address' => [
                'country' => config('core.default_country_code', 'GH'),
            ],
            'identifiers' => [],
            'emergencyContacts' => [],
            'schools' => [],
        ];
    }

    protected function fillPatientFormDataFromCurrentPatient(): void
    {
        if (! $this->currentPatient) {
            $this->patientFormData = [];

            return;
        }

        $patient = $this->currentPatient->loadMissing(['identifiers', 'emergencyContacts', 'schools']);

        if (! method_exists($this, 'getSchema')) {
            $this->patientFormData = [
                'mrn' => $patient->mrn,
                'title' => $patient->title?->value ?? $patient->title,
                'first_name' => $patient->first_name,
                'middle_name' => $patient->middle_name,
                'last_name' => $patient->last_name,
                'date_of_birth' => $patient->date_of_birth,
                'is_date_of_birth_estimated' => $patient->is_date_of_birth_estimated,
                'gender' => $patient->gender?->value ?? $patient->gender,
                'blood_type' => $patient->blood_type?->value ?? $patient->blood_type,
                'marital_status' => $patient->marital_status?->value ?? $patient->marital_status,
                'education_level' => $patient->education_level?->value ?? $patient->education_level,
                'occupation' => $patient->occupation,
                'nationality' => $patient->nationality,
                'is_student' => $patient->schools->isNotEmpty(),
                'phone' => $patient->phone,
                'email' => $patient->email,
                'preferred_language' => $patient->preferred_language,
                'address' => $patient->address ?? [],
                'photo' => $patient->photo,
            ];

            return;
        }

        $schema = $this->getSchema('patientDetailsForm');
        $schema->model($patient)->fill([
            ...$patient->attributesToArray(),
            'is_student' => $patient->schools->isNotEmpty(),
        ]);
        $schema->loadStateFromRelationships(shouldHydrate: true);
    }

    protected function getPostRegistrationTab(): string
    {
        return match ($this->getUserRoleKey()) {
            'lab' => 'pending-labs',
            default => 'encounter',
        };
    }

    protected function registerPatientManagementForms(): array
    {
        return [
            'registerPatientForm' => $this->makeSchema()
                ->model(fn (): Patient => new Patient)
                ->schema(PatientForm::getSteps())
                ->statePath('registerFormData'),
            'patientDetailsForm' => $this->makeSchema()
                ->model(fn (): Patient => $this->currentPatient ?? new Patient)
                ->schema(PatientForm::getSteps())
                ->statePath('patientFormData')
                ->disabled(fn (): bool => $this->currentPatient === null),
        ];
    }

    protected function resetPatientManagementState(): void
    {
        $this->registerFormData = [];
        $this->patientFormData = [];
        $this->postRegistrationFlow = false;
        $this->confirmDuplicateRegistration = false;
    }

    protected function prependPatientDetailsTab(array $tabs): array
    {
        if (! $this->canUpdateCurrentPatient()) {
            return $tabs;
        }

        return array_merge([
            'patient-details' => [
                'label' => 'Patient Details',
                'icon' => 'heroicon-m-user',
            ],
        ], $tabs);
    }

    protected function prependEncounterTab(array $tabs): array
    {
        if (isset($tabs['encounter'])) {
            return $tabs;
        }

        return array_merge([
            'encounter' => [
                'label' => 'Encounter',
                'icon' => 'heroicon-m-plus-circle',
            ],
        ], $tabs);
    }
}
