<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;

class EvaluationForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            Select::make('outcome')
                ->options(GoalEvaluationOutcome::class)
                ->required()
                ->label('Outcome'),
            Textarea::make('findings')
                ->required()
                ->rows(3)
                ->label('Evaluation findings'),
            Select::make('next_action')
                ->options(GoalEvaluationNextAction::class)
                ->required()
                ->label('Next action'),
        ];
    }
}
