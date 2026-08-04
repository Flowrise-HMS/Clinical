<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Models\DiagnosisCode;

class EncounterDiagnosisForm
{
    /**
     * @var array<string, DiagnosisCodeSearchResult>
     */
    protected static array $searchResultCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::quickElements());
    }

    /**
     * @return array<int, Repeater>
     */
    public static function quickElements(): array
    {
        return [
            Repeater::make('diagnoses')
                ->label('Diagnoses')
                ->defaultItems(1)
                ->addActionLabel('Add diagnosis')
                ->schema(self::itemElements())
                ->columns(1)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['description'] ?? $state['icd_code'] ?? 'Diagnosis'),
        ];
    }

    /**
     * Shared fields for a single diagnosis (PatientActions style Selects / TextInputs).
     *
     * @return array<int, mixed>
     */
    public static function itemElements(): array
    {
        return [
            Select::make('code_search')
                ->label('ICD Code')
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    $results = app(DiagnosisCodeSearchContract::class)->search($search, limit: 15);

                    foreach ($results as $result) {
                        self::$searchResultCache[$result->optionKey()] = $result;
                    }

                    return $results
                        ->mapWithKeys(fn (DiagnosisCodeSearchResult $result): array => [
                            $result->optionKey() => $result->optionLabel(),
                        ])
                        ->all();
                })
                ->getOptionLabelUsing(function (?string $value): ?string {
                    if (blank($value)) {
                        return null;
                    }

                    if (isset(self::$searchResultCache[$value])) {
                        return self::$searchResultCache[$value]->optionLabel();
                    }

                    if (str_starts_with($value, 'local:')) {
                        $code = DiagnosisCode::find(substr($value, 6));

                        return $code ? $code->code.' - '.$code->description : null;
                    }

                    return $value;
                })
                ->nullable()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if (blank($state)) {
                        $set('diagnosis_code_id', null);
                        $set('icd_entity_id', null);
                        $set('icd_uri', null);

                        return;
                    }

                    $cached = self::$searchResultCache[$state] ?? null;

                    if ($cached instanceof DiagnosisCodeSearchResult) {
                        $set('diagnosis_code_id', $cached->localId);
                        $set('icd_entity_id', $cached->externalId);
                        $set('icd_uri', $cached->uri);
                        $set('icd_code', $cached->code);
                        $set('description', $cached->label);

                        return;
                    }

                    if (str_starts_with($state, 'local:')) {
                        $id = substr($state, 6);
                        $code = DiagnosisCode::find($id);
                        $set('diagnosis_code_id', $id);
                        $set('icd_entity_id', null);
                        $set('icd_uri', null);
                        $set('icd_code', $code?->code);
                        if ($code) {
                            $set('description', $code->description);
                        }
                    }
                })
                ->helperText('Search WHO ICD (falls back to local catalogue). Leave empty to enter a custom diagnosis.'),

            TextInput::make('description')
                ->label('Diagnosis Name')
                ->placeholder('Or type a custom diagnosis name...')
                ->requiredWithout('code_search')
                ->maxLength(500),

            TextInput::make('icd_code')
                ->label('Code')
                ->maxLength(20)
                ->dehydrated(),

            Grid::make(3)
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options(DiagnosisType::class)
                        ->default(DiagnosisType::Primary)
                        ->required(),

                    Select::make('is_new_case')
                        ->label('New case')
                        ->options([
                            '1' => 'Yes',
                            '0' => 'No',
                        ])
                        ->default('0')
                        ->required(),

                    Select::make('certainty')
                        ->label('Certainty')
                        ->options(DiagnosisCertainty::class)
                        ->default(DiagnosisCertainty::Provisional)
                        ->required(),
                ]),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->placeholder('Optional notes for this diagnosis'),

            TextInput::make('diagnosis_code_id')->hidden()->dehydrated(),
            TextInput::make('icd_entity_id')->hidden()->dehydrated(),
            TextInput::make('icd_uri')->hidden()->dehydrated(),
        ];
    }
}
