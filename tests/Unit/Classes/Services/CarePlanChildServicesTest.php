<?php

use App\Models\User;
use Modules\Clinical\Classes\Services\CarePlanObjectiveService;
use Modules\Clinical\Classes\Services\CarePlanOrderService;
use Modules\Clinical\Classes\Services\CarePlanProblemService;
use Modules\Clinical\Classes\Services\CarePlanService;
use Modules\Clinical\Classes\Services\NursingDiagnosisService;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Models\CarePlanObjective;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $this->branch = Branch::factory()->create();
    $this->author = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->patient = Patient::withoutEvents(
        fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);
    $this->plan = app(CarePlanService::class)->create(
        $this->patient,
        $this->encounter,
        CarePlanCategory::NURSING,
        $this->author,
    );
});

it('identifies a problem, records a strength, and adds an order', function (): void {
    $problem = app(CarePlanProblemService::class)->identify(
        $this->plan,
        'Acute pain',
        $this->author,
        'Postoperative pain at incision site',
        1,
    );
    $strength = app(CarePlanProblemService::class)->addStrength(
        $problem,
        'Patient can describe pain severity.',
        $this->author,
    );
    $diagnosis = app(NursingDiagnosisService::class)->formulate(
        $problem,
        NursingDiagnosisCatalogue::factory()->create(),
        'Acute pain',
        'surgical incision',
        'pain score of 8 out of 10',
        $this->author,
    );
    $order = app(CarePlanOrderService::class)->addOrder(
        $diagnosis,
        'Assess pain every four hours.',
        'every 4 hours',
    );

    expect($problem->care_plan_id)->toBe($this->plan->id)
        ->and($strength->care_plan_problem_id)->toBe($problem->id)
        ->and($order->care_plan_diagnosis_id)->toBe($diagnosis->id)
        ->and($order->sequence)->toBe(1);
});

it('composes a PES statement from the nursing diagnosis service', function (): void {
    $problem = app(CarePlanProblemService::class)->identify(
        $this->plan,
        'Acute pain',
        $this->author,
    );

    $diagnosis = app(NursingDiagnosisService::class)->formulate(
        $problem,
        NursingDiagnosisCatalogue::factory()->create(),
        'Acute pain',
        'surgical incision',
        'pain score of 8 out of 10',
        $this->author,
    );

    expect($diagnosis->composed_statement)
        ->toBe('Acute pain related to surgical incision as evidenced by pain score of 8 out of 10');
});

it('allows diagnosis-only free text without PES or catalogue', function (): void {
    $problem = app(CarePlanProblemService::class)->identify(
        $this->plan,
        'Acute pain',
        $this->author,
    );

    $diagnosis = app(NursingDiagnosisService::class)->formulate(
        $problem,
        null,
        null,
        null,
        null,
        $this->author,
        'Risk for infection',
        false,
    );

    $order = app(CarePlanOrderService::class)->addOrder(
        $diagnosis,
        'Monitor incision site daily.',
        null,
    );

    expect($diagnosis->catalogue_id)->toBeNull()
        ->and($diagnosis->label)->toBe('Risk for infection')
        ->and($diagnosis->composed_statement)->toBe('Risk for infection')
        ->and($order->frequency)->toBeNull();
});

it('resolves display labels without lazy loading the catalogue relation', function (): void {
    $catalogue = NursingDiagnosisCatalogue::factory()->create([
        'label' => 'Acute pain',
    ]);
    $problem = app(CarePlanProblemService::class)->identify($this->plan, 'Acute pain', $this->author);

    $diagnosis = app(NursingDiagnosisService::class)->formulate(
        $problem,
        $catalogue,
        'Acute pain',
        'surgical incision',
        'grimacing',
        $this->author,
    );

    $fresh = $diagnosis->fresh();

    expect($fresh->relationLoaded('catalogue'))->toBeFalse()
        ->and($fresh->label)->toBe('Acute pain')
        ->and($fresh->displayLabel())->toBe('Acute pain');
});

it('warns but still activates when a diagnosis has fewer than three orders', function (): void {
    $problem = app(CarePlanProblemService::class)->identify($this->plan, 'Acute pain', $this->author);
    app(CarePlanProblemService::class)->addStrength($problem, 'Can rate pain', $this->author);

    $diagnosis = app(NursingDiagnosisService::class)->formulate(
        $problem,
        null,
        null,
        null,
        null,
        $this->author,
        'Acute pain',
        false,
    );
    app(CarePlanOrderService::class)->addOrder($diagnosis, 'Assess pain', null);

    $this->plan->update(['no_known_allergies' => true]);
    $this->plan->routineCares()->createMany(
        collect(RoutineCareItem::cases())
            ->reject(fn ($item) => $item === RoutineCareItem::OTHER)
            ->map(fn ($item): array => [
                'item' => $item,
                'specification' => 'As prescribed',
                'specified_by' => $this->author->id,
                'specified_at' => now(),
            ])
            ->all()
    );

    $medical = EncounterDiagnosis::factory()->create([
        'encounter_id' => $this->encounter->id,
        'patient_id' => $this->patient->id,
        'ordered_by' => $this->author->id,
    ]);
    app(CarePlanService::class)->attachMedicalDiagnosis($this->plan, $medical);

    $warnings = app(CarePlanService::class)->activationWarnings($this->plan->fresh());
    $activated = app(CarePlanService::class)->activate($this->plan->fresh());

    expect($warnings)->not->toBeEmpty()
        ->and($activated->status)->toBe(CarePlanStatus::ACTIVE);
});

it('appends an evaluation and updates achievement status', function (): void {
    $objective = createCarePlanObjective($this);

    $evaluation = app(CarePlanObjectiveService::class)->evaluate(
        $objective,
        GoalEvaluationOutcome::PARTIALLY_MET,
        'Pain score has reduced to 4 out of 10.',
        GoalEvaluationNextAction::CONTINUE,
        $this->author,
    );

    expect($objective->fresh()->achievement_status)->toBe(GoalAchievementStatus::IMPROVING)
        ->and($objective->evaluations()->count())->toBe(1)
        ->and($evaluation->achievement_status_snapshot)->toBe(GoalAchievementStatus::IMPROVING);
});

it('re-evaluates an existing objective without changing its identity', function (): void {
    $objective = createCarePlanObjective($this);
    $objectiveId = $objective->id;
    $service = app(CarePlanObjectiveService::class);

    $service->evaluate(
        $objective,
        GoalEvaluationOutcome::MET,
        'Pain is controlled.',
        GoalEvaluationNextAction::CONTINUE,
        $this->author,
    );

    expect($objective->fresh()->achievement_status)->toBe(GoalAchievementStatus::ACHIEVED);

    $service->evaluate(
        $objective->fresh(),
        GoalEvaluationOutcome::NOT_MET,
        'Pain persists after treatment.',
        GoalEvaluationNextAction::REVISE,
        $this->author,
    );

    expect($objective->fresh()->id)->toBe($objectiveId)
        ->and($objective->evaluations()->count())->toBe(2)
        ->and($objective->fresh()->achievement_status)->toBe(GoalAchievementStatus::NOT_ACHIEVED);
});

function createCarePlanObjective(TestCase $testCase): CarePlanObjective
{
    $testCase->plan->update(['status' => CarePlanStatus::ACTIVE]);

    $problem = app(CarePlanProblemService::class)->identify(
        $testCase->plan,
        'Acute pain',
        $testCase->author,
    );
    $diagnosis = app(NursingDiagnosisService::class)->formulate(
        $problem,
        NursingDiagnosisCatalogue::factory()->create(),
        'Acute pain',
        'surgical incision',
        'pain score of 8 out of 10',
        $testCase->author,
    );

    return app(CarePlanObjectiveService::class)->add(
        $diagnosis,
        'Patient will report pain of 3 out of 10 or less.',
        $testCase->author,
    );
}
