<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Concerns;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Modules\Clinical\Classes\Services\CarePlanObjectiveService;
use Modules\Clinical\Classes\Services\CarePlanOrderService;
use Modules\Clinical\Classes\Services\CarePlanProblemService;
use Modules\Clinical\Classes\Services\CarePlanService;
use Modules\Clinical\Classes\Services\NursingDiagnosisService;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan\AssessmentForm;
use Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan\DiagnosisGridForm;
use Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan\EvaluationForm;
use Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan\HeaderForm;
use Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan\RoutineCareForm;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanObjective;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Models\Patient;

trait ManagesCarePlan
{
    public string $searchTerm = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $searchResults = [];

    public ?string $draftCarePlanId = null;

    public ?string $previewCarePlanId = null;

    public function updatedSearchTerm(): void
    {
        if (mb_strlen($this->searchTerm) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = app(PatientSearchService::class)
            ->search($this->searchTerm, 10)
            ->map(fn (Patient $patient): array => [
                ...$patient->toArray(),
                'full_name' => $patient->full_name,
            ])
            ->all();
    }

    #[On('select-patient')]
    public function selectPatient(string $patientId): void
    {
        $this->patientId = $patientId;
        $this->searchTerm = '';
        $this->searchResults = [];
        $this->draftCarePlanId = null;
        $this->loadPatientContext();
    }

    public function clearPatient(): void
    {
        $this->patientId = null;
        $this->currentPatient = null;
        $this->currentEncounter = null;
        $this->latestVitals = null;
        $this->nextAppointment = null;
        $this->draftCarePlanId = null;
    }

    public function canCreateCarePlan(): bool
    {
        return $this->currentPatient !== null
            && $this->getOpenEncounter() !== null
            && Auth::user()?->can('create', CarePlan::class) === true;
    }

    public function createCarePlan(): void
    {

        $encounter = $this->getOpenEncounter();

        if ($this->currentPatient === null || $encounter === null) {
            Notification::make()
                ->title('No open encounter')
                ->body('An open encounter is required before creating a care plan.')
                ->warning()
                ->send();

            return;
        }

        /** @var User $author */
        $author = Auth::user();

        try {
            $carePlan = app(CarePlanService::class)->create(
                $this->currentPatient,
                $encounter,
                CarePlanCategory::NURSING,
                $author,
            );
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Unable to create care plan')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->draftCarePlanId = $carePlan->id;

        Notification::make()
            ->title('Care plan draft created')
            ->body('Continue through the authoring steps before activating the plan.')
            ->success()
            ->send();
    }

    public function selectedCarePlan(): ?CarePlan
    {
        if (blank($this->draftCarePlanId)) {
            return null;
        }

        return CarePlan::query()
            ->with([
                'medicalDiagnoses',
                'problems.strengths',
                'routineCares',
                'diagnoses.catalogue',
                'diagnoses.orders.interventions',
                'diagnoses.objectives.evaluations',
            ])
            ->find($this->draftCarePlanId);
    }

    /**
     * @return array{
     *     can_activate: bool,
     *     is_ready: bool,
     *     medical_diagnoses: list<array{id: string, description: string}>,
     *     items: list<array{key: string, label: string, passed: bool, detail: ?string, severity: 'required'|'warning'}>,
     *     by_key: array<string, array{key: string, label: string, passed: bool, detail: ?string, severity: 'required'|'warning'}>
     * }|null
     */
    public function carePlanActivationReadiness(): ?array
    {
        $carePlan = $this->selectedCarePlan();

        if ($carePlan === null) {
            return null;
        }

        $readiness = app(CarePlanService::class)->activationReadiness($carePlan);
        $readiness['by_key'] = collect($readiness['items'])->keyBy('key')->all();

        return $readiness;
    }

    #[On('select-care-plan')]
    public function selectCarePlan(string $carePlanId): void
    {
        $carePlan = CarePlan::query()->findOrFail($carePlanId);

        if (! $carePlan->status->isOpen()) {
            Notification::make()
                ->title('Care plan is not open')
                ->warning()
                ->send();

            return;
        }

        $this->draftCarePlanId = $carePlan->id;
    }

    #[On('resume-care-plan')]
    public function resumeCarePlan(string $carePlanId): void
    {
        $carePlan = CarePlan::query()->findOrFail($carePlanId);

        if (! $carePlan->status->isOpen()) {
            Notification::make()
                ->title('Care plan is not open')
                ->warning()
                ->send();

            return;
        }

        if ($this->patientId !== $carePlan->patient_id) {
            $this->selectPatient($carePlan->patient_id);
        }

        $this->draftCarePlanId = $carePlan->id;
    }

    public function activateCarePlan(string $carePlanId): void
    {
        $carePlan = CarePlan::query()->findOrFail($carePlanId);

        if (! $carePlan->status->canActivate()) {
            Notification::make()
                ->title('Care plan cannot be activated')
                ->body($carePlan->status === CarePlanStatus::ACTIVE
                    ? __('Care plan is already active.')
                    : __('Only draft or on-hold care plans can be activated.'))
                ->warning()
                ->send();

            return;
        }

        try {
            $warnings = app(CarePlanService::class)->activationWarnings($carePlan);
            app(CarePlanService::class)->activate($carePlan);
            $this->draftCarePlanId = null;

            $notification = Notification::make()
                ->title('Care plan activated')
                ->success();

            if ($warnings !== []) {
                $notification
                    ->warning()
                    ->title('Care plan activated with warnings')
                    ->body(implode(' ', $warnings));
            }

            $notification->send();
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Care plan cannot be activated')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    #[On('preview-care-plan')]
    public function openPreview(string $carePlanId): void
    {
        $carePlan = CarePlan::query()->findOrFail($carePlanId);

        $this->previewCarePlanId = $carePlan->id;
        $this->mountAction('previewCarePlan');
    }

    public function editCarePlanHeaderAction(): Action
    {
        return Action::make('editCarePlanHeader')
            ->label('Edit header')
            ->modalHeading('Care plan header')
            ->slideOver()
            ->record(fn (): CarePlan => $this->selectedCarePlanOrFail())
            ->fillForm(fn (CarePlan $record): array => $record->only([
                'title',
                'description',
                'discharge_date',
                'operation',
                'operation_date',
                'no_known_allergies',
            ]))
            ->schema(HeaderForm::components())
            ->action(function (array $data, CarePlan $record): void {
                $record->update($data);
            })
            ->successNotificationTitle('Care plan header saved');
    }

    public function addCarePlanAssessmentAction(): Action
    {
        return Action::make('addCarePlanAssessment')
            ->label('Add assessment')
            ->modalHeading('Assessment and strengths')
            ->slideOver()
            ->schema(AssessmentForm::components())
            ->action(function (array $data): void {
                $carePlan = $this->selectedCarePlanOrFail();

                /** @var User $user */
                $user = Auth::user();
                $problem = app(CarePlanProblemService::class)->identify(
                    $carePlan,
                    $data['label'],
                    $user,
                    $data['description'] ?? null,
                    $data['priority'] ?? null,
                );
                app(CarePlanProblemService::class)->addStrength($problem, $data['strength'], $user);
            })
            ->successNotificationTitle('Assessment recorded');
    }

    public function attachMedicalDiagnosisAction(): Action
    {
        return Action::make('attachMedicalDiagnosis')
            ->label('Attach medical diagnosis')
            ->modalHeading('Medical diagnosis')
            ->slideOver()
            ->schema([
                Select::make('encounter_diagnosis_id')
                    ->options(fn (): array => EncounterDiagnosis::query()
                        ->where('encounter_id', $this->selectedCarePlanOrFail()->encounter_id)
                        ->where('is_active', true)
                        ->orderBy('description')
                        ->pluck('description', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->label('Active encounter diagnosis'),
            ])
            ->action(function (array $data): void {
                $carePlan = $this->selectedCarePlanOrFail();
                $diagnosis = EncounterDiagnosis::query()->findOrFail($data['encounter_diagnosis_id']);
                app(CarePlanService::class)->attachMedicalDiagnosis($carePlan, $diagnosis);
            })
            ->successNotificationTitle('Medical diagnosis attached');
    }

    public function addRoutineCareAction(): Action
    {
        return Action::make('addRoutineCare')
            ->label('Set routine care')
            ->modalHeading('Routine care checklist')
            ->modalDescription('Complete all items in one pass. Use smart suggestions as a starting point.')
            ->slideOver()
            ->fillForm(fn (): array => [
                'items' => collect(RoutineCareItem::cases())
                    ->reject(fn (RoutineCareItem $item): bool => $item === RoutineCareItem::OTHER)
                    ->map(function (RoutineCareItem $item): array {
                        $existing = $this->selectedCarePlanOrFail()
                            ->routineCares()
                            ->get()
                            ->first(fn ($row) => $row->item === $item);

                        return [
                            'item' => $item->value,
                            'item_label' => $item->getLabel(),
                            'specification' => $existing?->specification,
                            'not_applicable' => (bool) ($existing?->not_applicable ?? false),
                            'notes' => $existing?->notes,
                            'placeholder' => $item->getDescription() ?? 'Enter care instructions',
                        ];
                    })
                    ->values()
                    ->all(),
            ])
            ->schema(fn (): array => RoutineCareForm::components($this->selectedCarePlanOrFail()))
            ->action(function (array $data): void {
                $carePlan = $this->selectedCarePlanOrFail();

                /** @var User $user */
                $user = Auth::user();

                app(CarePlanService::class)->syncRoutineCareChecklist(
                    $carePlan,
                    $data['items'] ?? [],
                    $user,
                );
            })
            ->successNotificationTitle('Routine care saved');
    }

    public function addNursingDiagnosisAction(): Action
    {
        return Action::make('addNursingDiagnosis')
            ->label('Add nursing diagnosis')
            ->modalHeading('NANDA diagnosis and PES')
            ->slideOver()
            ->schema(fn (): array => DiagnosisGridForm::components($this->selectedCarePlanOrFail()))
            ->action(function (array $data): void {
                $carePlan = $this->selectedCarePlanOrFail();

                /** @var User $user */
                $user = Auth::user();
                $problem = $carePlan->problems()->findOrFail($data['care_plan_problem_id']);
                $catalogue = filled($data['catalogue_id'] ?? null)
                    ? NursingDiagnosisCatalogue::query()->findOrFail($data['catalogue_id'])
                    : null;

                $diagnosis = app(NursingDiagnosisService::class)->formulate(
                    $problem,
                    $catalogue,
                    $data['problem_statement'] ?? null,
                    $data['related_to'] ?? null,
                    $data['as_evidenced_by'] ?? null,
                    $user,
                    $data['custom_label'] ?? null,
                    (bool) ($data['save_to_catalogue'] ?? false),
                );

                foreach ($data['orders'] ?? [] as $order) {
                    if (blank($order['instruction'] ?? null)) {
                        continue;
                    }

                    app(CarePlanOrderService::class)->addOrder(
                        $diagnosis,
                        $order['instruction'],
                        $order['frequency'] ?? null,
                    );
                }

                if (filled($data['objective'] ?? null)) {
                    app(CarePlanObjectiveService::class)->add($diagnosis, $data['objective'], $user);
                }
            })
            ->successNotificationTitle('Nursing diagnosis recorded');
    }

    public function recordInterventionAction(): Action
    {
        return Action::make('recordIntervention')
            ->label('Record intervention')
            ->modalHeading('Nursing intervention')
            ->slideOver()
            ->schema([
                Select::make('care_plan_order_id')
                    ->options(fn (): array => $this->selectedCarePlanOrFail()
                        ->diagnoses()
                        ->with('orders')
                        ->get()
                        ->flatMap(fn ($diagnosis) => $diagnosis->orders)
                        ->mapWithKeys(fn (CarePlanOrder $order): array => [
                            $order->id => "#{$order->sequence}: {$order->instruction}",
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->label('Nursing order'),
                Textarea::make('description')
                    ->required()
                    ->rows(3)
                    ->label('Intervention performed'),
                Textarea::make('notes')
                    ->rows(2)
                    ->label('Notes'),
            ])
            ->action(function (array $data): void {
                $carePlan = $this->selectedCarePlanOrFail();

                /** @var User $user */
                $user = Auth::user();
                $order = CarePlanOrder::query()
                    ->whereHas('diagnosis', fn ($query) => $query->where('care_plan_id', $carePlan->id))
                    ->findOrFail($data['care_plan_order_id']);

                $order->interventions()->create([
                    'description' => $data['description'],
                    'performed_at' => now(),
                    'performed_by' => $user->id,
                    'notes' => $data['notes'] ?? null,
                ]);
            })
            ->successNotificationTitle('Intervention recorded');
    }

    public function evaluateCarePlanObjectiveAction(): Action
    {
        return Action::make('evaluateCarePlanObjective')
            ->label('Evaluate objective')
            ->modalHeading('Evaluate expected outcome')
            ->slideOver()
            ->schema(array_merge([
                Select::make('care_plan_objective_id')
                    ->options(fn (): array => $this->selectedCarePlanOrFail()
                        ->diagnoses()
                        ->with('objectives')
                        ->get()
                        ->flatMap(fn ($diagnosis) => $diagnosis->objectives)
                        ->mapWithKeys(fn (CarePlanObjective $objective): array => [
                            $objective->id => $objective->description,
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->label('Expected outcome'),
            ], EvaluationForm::components()))
            ->action(function (array $data): void {
                $carePlan = $this->selectedCarePlanOrFail();

                /** @var User $user */
                $user = Auth::user();
                $objective = CarePlanObjective::query()
                    ->whereHas('diagnosis', fn ($query) => $query->where('care_plan_id', $carePlan->id))
                    ->findOrFail($data['care_plan_objective_id']);
                app(CarePlanObjectiveService::class)->evaluate(
                    $objective,
                    enum_from(GoalEvaluationOutcome::class, $data['outcome']),
                    $data['findings'],
                    enum_from(GoalEvaluationNextAction::class, $data['next_action']),
                    $user,
                );
            })
            ->successNotificationTitle('Objective evaluated');
    }

    public function previewCarePlanAction(): Action
    {
        return Action::make('previewCarePlan')
            ->modalHeading('Care plan preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->slideOver()
            ->record(fn (): CarePlan => CarePlan::query()
                ->with([
                    'patient',
                    'encounter',
                    'medicalDiagnoses',
                    'problems.strengths',
                    'routineCares',
                    'diagnoses.catalogue',
                    'diagnoses.orders.interventions',
                    'diagnoses.objectives.evaluations',
                ])
                ->findOrFail($this->previewCarePlanId))
            ->infolist([
                Section::make('Plan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('category')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('title')->placeholder('Untitled care plan'),
                        TextEntry::make('description')->columnSpanFull()->placeholder('No clinical summary'),
                        TextEntry::make('no_known_allergies')->boolean()->label('No known allergies'),
                    ]),
                Section::make('Diagnoses')
                    ->schema([
                        TextEntry::make('medical_diagnoses')
                            ->label('Medical')
                            ->badge()
                            ->state(fn (CarePlan $record): string => $record->medicalDiagnoses
                                ->pluck('description')
                                ->filter()
                                ->join(', ') ?: 'None recorded'),
                        TextEntry::make('nursing_diagnoses')
                            ->label('Nursing')
                            ->badge()
                            ->state(fn (CarePlan $record): string => $record->diagnoses
                                ->pluck('composed_statement')
                                ->filter()
                                ->join("\n") ?: 'None recorded'),
                    ]),
                Section::make('Assessment and routine care')
                    ->schema([
                        TextEntry::make('assessment')
                            ->state(fn (CarePlan $record): string => $record->problems
                                ->map(fn ($problem): string => "{$problem->label}: {$problem->strengths->pluck('description')->join(', ')}")
                                ->join("\n") ?: 'None recorded')
                            ->columnSpanFull(),
                        TextEntry::make('routine_care')
                            ->state(fn (CarePlan $record): string => $record->routineCares
                                ->map(fn ($routineCare): string => "{$routineCare->item->getLabel()}: ".($routineCare->not_applicable ? 'Not applicable' : $routineCare->specification))
                                ->join("\n") ?: 'None recorded')
                            ->columnSpanFull(),
                    ]),
                Section::make('Orders and interventions')
                    ->schema([
                        TextEntry::make('orders')
                            ->state(fn (CarePlan $record): string => $record->diagnoses
                                ->flatMap(fn ($diagnosis) => $diagnosis->orders)
                                ->map(fn (CarePlanOrder $order): string => "#{$order->sequence} {$order->instruction} ({$order->frequency}) — ".$order->interventions->pluck('description')->join(', '))
                                ->join("\n") ?: 'None recorded')
                            ->columnSpanFull(),
                    ]),
                Section::make('Objectives and evaluations')
                    ->schema([
                        TextEntry::make('evaluations')
                            ->state(fn (CarePlan $record): string => $record->diagnoses
                                ->flatMap(fn ($diagnosis) => $diagnosis->objectives)
                                ->map(fn (CarePlanObjective $objective): string => "{$objective->description}: ".$objective->evaluations
                                    ->map(fn ($evaluation): string => "{$evaluation->outcome->getLabel()} ({$evaluation->next_action->getLabel()})")
                                    ->join(', '))
                                ->join("\n") ?: 'None recorded')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function selectedCarePlanOrFail(): CarePlan
    {
        return $this->selectedCarePlan() ?? throw new ModelNotFoundException;
    }

    public function getOpenEncounter(): ?Encounter
    {
        if ($this->currentPatient === null) {
            return null;
        }

        if ($this->currentPatient->relationLoaded('activeEncounter')) {
            return $this->currentPatient->activeEncounter;
        }

        return $this->currentPatient->activeEncounter()->first();
    }

    /**
     * @return Collection<int, CarePlan>
     */
    public function wardCarePlans(): Collection
    {
        $branchId = Auth::user()?->branch_id;

        if (blank($branchId)) {
            return new Collection;
        }

        return CarePlan::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                CarePlanStatus::DRAFT,
                CarePlanStatus::ACTIVE,
                CarePlanStatus::ON_HOLD,
            ])
            ->with(['patient', 'encounter', 'custodian'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * Open care plans for the selected patient (draft, active, on hold).
     *
     * @return Collection<int, CarePlan>
     */
    public function recentCarePlans(): Collection
    {
        if ($this->currentPatient === null) {
            return new Collection;
        }

        return CarePlan::query()
            ->where('patient_id', $this->currentPatient->id)
            ->whereIn('status', [
                CarePlanStatus::DRAFT,
                CarePlanStatus::ACTIVE,
                CarePlanStatus::ON_HOLD,
            ])
            ->with(['encounter', 'custodian'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * @deprecated Use recentCarePlans()
     *
     * @return Collection<int, CarePlan>
     */
    public function activeCarePlans(): Collection
    {
        return $this->recentCarePlans();
    }

    /**
     * @return Collection<int, CarePlan>
     */
    public function previousCarePlans(): Collection
    {
        if ($this->currentPatient === null) {
            return new Collection;
        }

        return CarePlan::query()
            ->where('patient_id', $this->currentPatient->id)
            ->whereIn('status', [
                CarePlanStatus::ON_HOLD,
                CarePlanStatus::COMPLETED,
                CarePlanStatus::REVOKED,
                CarePlanStatus::ENTERED_IN_ERROR,
            ])
            ->with(['encounter', 'custodian'])
            ->latest()
            ->get();
    }
}
