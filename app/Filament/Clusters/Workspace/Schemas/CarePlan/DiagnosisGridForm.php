<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class DiagnosisGridForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(CarePlan $carePlan): array
    {
        return [
            Select::make('care_plan_problem_id')
                ->options($carePlan->problems()
                    ->orderBy('priority')
                    ->pluck('label', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->label('Nursing problem'),
            Select::make('catalogue_id')
                ->options(fn (): array => NursingDiagnosisCatalogue::query()
                    ->where('is_active', true)
                    ->orderBy('label')
                    ->pluck('label', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->label('NANDA diagnosis'),
            Textarea::make('problem_statement')
                ->required()
                ->rows(2)
                ->label('Problem'),
            Textarea::make('related_to')
                ->required()
                ->rows(2)
                ->label('Related to'),
            Textarea::make('as_evidenced_by')
                ->required()
                ->rows(2)
                ->label('As evidenced by'),
            Section::make('Nursing orders')
                ->description('Record at least three orders for this diagnosis.')
                ->schema([
                    Repeater::make('orders')
                        ->minItems(3)
                        ->defaultItems(3)
                        ->schema([
                            TextInput::make('instruction')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('frequency')
                                ->required()
                                ->maxLength(255),
                        ]),
                ]),
            Textarea::make('objective')
                ->rows(2)
                ->label('Expected outcome')
                ->helperText('Optional measurable objective for evaluation.'),
        ];
    }
}
