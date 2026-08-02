<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class DiagnosisGridForm
{
    /**
     * @return array<int, Component>
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
                ->helperText('Link this diagnosis to an identified nursing problem.')
                ->label('Nursing problem'),
            Select::make('catalogue_id')
                ->label('NANDA diagnosis')
                ->placeholder('Search catalogue or leave blank to type your own')
                ->helperText('Select a suggested NANDA label, or type a custom diagnosis below if it is not listed.')
                ->searchable()
                ->nullable()
                ->live()
                ->getSearchResultsUsing(fn (string $search): array => NursingDiagnosisCatalogue::query()
                    ->where('is_active', true)
                    ->where(function ($query) use ($search): void {
                        $query->where('label', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orderBy('label')
                    ->limit(25)
                    ->pluck('label', 'id')
                    ->all())
                ->getOptionLabelUsing(fn (?string $value): ?string => NursingDiagnosisCatalogue::query()
                    ->whereKey($value)
                    ->value('label')),
            TextInput::make('custom_label')
                ->label('Custom diagnosis')
                ->placeholder('e.g. Acute pain')
                ->helperText('Used when the diagnosis is not in the catalogue.')
                ->maxLength(255)
                ->required(fn (Get $get): bool => blank($get('catalogue_id')))
                ->visible(fn (Get $get): bool => blank($get('catalogue_id'))),
            Checkbox::make('save_to_catalogue')
                ->label('Save custom diagnosis to catalogue for reuse')
                ->helperText('Optional. Adds this label to the shared nursing diagnosis catalogue.')
                ->visible(fn (Get $get): bool => blank($get('catalogue_id')) && filled($get('custom_label'))),
            Textarea::make('problem_statement')
                ->rows(2)
                ->nullable()
                ->placeholder('Optional PES problem statement')
                ->helperText('Leave blank when documenting diagnosis-only (no full PES).')
                ->label('Problem'),
            Textarea::make('related_to')
                ->rows(2)
                ->nullable()
                ->placeholder('Optional related factors / etiology')
                ->helperText('Optional. Complete when using a full PES statement.')
                ->label('Related to'),
            Textarea::make('as_evidenced_by')
                ->rows(2)
                ->nullable()
                ->placeholder('Optional defining characteristics')
                ->helperText('Optional. Complete when using a full PES statement.')
                ->label('As evidenced by'),
            Section::make('Nursing orders')
                ->description('Add nursing orders for this diagnosis. Fewer than three will show a warning on activation but will not block you.')
                ->schema([
                    Repeater::make('orders')
                        ->defaultItems(1)
                        ->schema([
                            TextInput::make('instruction')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Assess pain score every 4 hours')
                                ->helperText('What should nursing do?')
                                ->label('Instruction'),
                            TextInput::make('frequency')
                                ->nullable()
                                ->maxLength(255)
                                ->placeholder('e.g. Q4H, BID, PRN')
                                ->helperText('Optional timing or frequency.')
                                ->label('Frequency'),
                        ])
                        ->addActionLabel('Add order'),
                ]),
            Textarea::make('objective')
                ->rows(2)
                ->label('Expected outcome')
                ->placeholder('Optional measurable goal for evaluation')
                ->helperText('Optional measurable objective for later evaluation.'),
        ];
    }
}
