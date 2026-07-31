<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;
use Modules\Clinical\Enums\GoalLifecycleStatus;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanEvaluation;
use Modules\Clinical\Models\CarePlanObjective;

class CarePlanObjectiveService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function add(
        CarePlanDiagnosis $diagnosis,
        string $description,
        User $author,
        array $attributes = [],
    ): CarePlanObjective {
        return DB::transaction(function () use ($diagnosis, $description, $author, $attributes): CarePlanObjective {
            $diagnosis = CarePlanDiagnosis::query()
                ->with('carePlan')
                ->lockForUpdate()
                ->findOrFail($diagnosis->id);

            $this->assertPlanIsOpen($diagnosis->carePlan);

            return $diagnosis->objectives()->create(array_merge([
                'description' => $description,
                'lifecycle_status' => GoalLifecycleStatus::PROPOSED,
                'achievement_status' => GoalAchievementStatus::IN_PROGRESS,
                'start_date' => today(),
                'author_id' => $author->id,
            ], $attributes));
        });
    }

    public function evaluate(
        CarePlanObjective $objective,
        GoalEvaluationOutcome $outcome,
        string $findings,
        GoalEvaluationNextAction $nextAction,
        User $evaluatedBy,
    ): CarePlanEvaluation {
        return DB::transaction(function () use ($objective, $outcome, $findings, $nextAction, $evaluatedBy): CarePlanEvaluation {
            $objective = CarePlanObjective::query()
                ->with('diagnosis.carePlan')
                ->lockForUpdate()
                ->findOrFail($objective->id);

            $this->assertPlanIsOpen($objective->diagnosis->carePlan);

            $achievementStatus = $this->achievementStatusFor($outcome);

            $evaluation = $objective->evaluations()->create([
                'evaluated_by' => $evaluatedBy->id,
                'evaluated_at' => now(),
                'outcome' => $outcome,
                'findings' => $findings,
                'next_action' => $nextAction,
                'achievement_status_snapshot' => $achievementStatus,
            ]);

            $objective->update(['achievement_status' => $achievementStatus]);

            return $evaluation;
        });
    }

    protected function achievementStatusFor(GoalEvaluationOutcome $outcome): GoalAchievementStatus
    {
        return match ($outcome) {
            GoalEvaluationOutcome::MET => GoalAchievementStatus::ACHIEVED,
            GoalEvaluationOutcome::PARTIALLY_MET => GoalAchievementStatus::IMPROVING,
            GoalEvaluationOutcome::NOT_MET => GoalAchievementStatus::NOT_ACHIEVED,
        };
    }

    protected function assertPlanIsOpen(CarePlan $plan): void
    {
        if (! $plan->status->isOpen()) {
            throw new \InvalidArgumentException(__('Only open care plans can be updated.'));
        }
    }
}
