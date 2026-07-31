<?php

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanIntent;
use Modules\Clinical\Enums\CarePlanOrderStatus;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;
use Modules\Clinical\Enums\GoalLifecycleStatus;
use Modules\Clinical\Enums\NursingProblemStatus;
use Modules\Clinical\Enums\RoutineCareItem;

it('exposes all care plan enum values', function (): void {
    expect(CarePlanCategory::values())->toBe(['nursing', 'wound', 'nutrition'])
        ->and(CarePlanIntent::values())->toBe(['plan'])
        ->and(CarePlanStatus::values())->toBe(['draft', 'active', 'on-hold', 'completed', 'revoked', 'entered-in-error'])
        ->and(RoutineCareItem::values())->toBe([
            'tpr',
            'bp',
            'diet',
            'fluids',
            'intake_output',
            'oral_hygiene',
            'bath',
            'urine_testing',
            'body_weight',
            'activity',
            'other',
        ])
        ->and(NursingProblemStatus::values())->toBe(['active', 'resolved'])
        ->and(GoalLifecycleStatus::values())->toBe([
            'proposed',
            'planned',
            'accepted',
            'active',
            'on-hold',
            'completed',
            'cancelled',
            'entered-in-error',
            'rejected',
        ])
        ->and(GoalAchievementStatus::values())->toBe([
            'in-progress',
            'improving',
            'worsening',
            'no-change',
            'achieved',
            'sustaining',
            'not-achieved',
            'no-progress',
            'not-attainable',
        ])
        ->and(GoalEvaluationOutcome::values())->toBe(['met', 'partially_met', 'not_met'])
        ->and(GoalEvaluationNextAction::values())->toBe(['continue', 'revise', 'discontinue', 'escalate'])
        ->and(CarePlanOrderStatus::values())->toBe(['planned', 'in_progress', 'completed', 'cancelled']);
});

it('provides labels and colors for care plan enum cases', function (): void {
    expect(CarePlanStatus::ACTIVE)->toBeInstanceOf(HasLabel::class)
        ->and(CarePlanStatus::ACTIVE)->toBeInstanceOf(HasColor::class)
        ->and(CarePlanStatus::ACTIVE->getLabel())->toBe('Active')
        ->and(CarePlanStatus::ACTIVE->getColor())->toBe('primary');
});

it('identifies open care plan statuses', function (): void {
    expect(CarePlanStatus::DRAFT->isOpen())->toBeTrue()
        ->and(CarePlanStatus::ACTIVE->isOpen())->toBeTrue()
        ->and(CarePlanStatus::ON_HOLD->isOpen())->toBeTrue()
        ->and(CarePlanStatus::COMPLETED->isOpen())->toBeFalse()
        ->and(CarePlanStatus::REVOKED->isOpen())->toBeFalse()
        ->and(CarePlanStatus::ENTERED_IN_ERROR->isOpen())->toBeFalse();
});
