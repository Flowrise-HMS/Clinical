<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Clinical\Classes\Services\IcdCatalogueService;
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
                ->label('Search Diagnosis')
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    $results = app(DiagnosisCodeSearchContract::class)->search($search, limit: 15);
                    $catalogue = app(IcdCatalogueService::class);
                    $options = [];

                    foreach ($results as $result) {
                        // WHO hits are persisted into the local catalogue right away so the
                        // selection can be resolved from the database on the next request;
                        // a static cache does not survive between Livewire round-trips.
                        $key = $result->optionKey();

                        if (blank($result->localId) && $result->source === 'who') {
                            $localId = $catalogue->localId($result);

                            if (filled($localId)) {
                                $key = 'local:'.$localId;
                            }
                        }

                        self::$searchResultCache[$key] = $result;
                        $options[$key] = $result->optionLabel();
                    }

                    return $options;
                })
                ->getOptionLabelUsing(function (?string $value): ?string {
                    if (blank($value)) {
                        return null;
                    }

                    if (isset(self::$searchResultCache[$value])) {
                        return self::$searchResultCache[$value]->optionLabel();
                    }

                    $code = self::resolveSelection($value);

                    return $code ? $code->code.' - '.$code->description : $value;
                })
                ->nullable()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if (blank($state)) {
                        self::applyCode($set, null);

                        return;
                    }

                    $code = self::resolveSelection($state);

                    if ($code instanceof DiagnosisCode) {
                        self::applyCode($set, $code);

                        return;
                    }

                    // Last resort for results that could not be persisted (no code, uri or entity id).
                    $cached = self::$searchResultCache[$state] ?? null;

                    if ($cached instanceof DiagnosisCodeSearchResult) {
                        $set('diagnosis_code_id', $cached->localId);
                        $set('icd_entity_id', $cached->externalId);
                        $set('icd_uri', $cached->uri);
                        $set('icd_code', $cached->code);
                        $set('icd10_code', $cached->source === 'who' ? null : $cached->code);
                        $set('description', $cached->label);
                    }
                })
                ->helperText('Search by diagnosis name or ICD code - or leave empty and type your own below.'),

            TextInput::make('description')
                ->label('Diagnosis Name')
                ->placeholder('Or type a custom diagnosis name...')
                ->requiredWithout('code_search')
                ->maxLength(500),

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
            TextInput::make('icd_code')->hidden()->dehydrated(),
            TextInput::make('icd10_code')->hidden()->dehydrated(),
        ];
    }

    /**
     * Resolve a `local:<id>` or `who:<entity id>` option key to a catalogue row.
     */
    public static function resolveSelection(?string $key): ?DiagnosisCode
    {
        if (blank($key)) {
            return null;
        }

        if (str_starts_with($key, 'local:')) {
            return DiagnosisCode::query()->find(substr($key, 6));
        }

        if (str_starts_with($key, 'who:')) {
            return DiagnosisCode::query()->where('icd_entity_id', substr($key, 4))->first();
        }

        return null;
    }

    protected static function applyCode(Set $set, ?DiagnosisCode $code): void
    {
        $set('diagnosis_code_id', $code?->id);
        $set('icd_entity_id', $code?->icd_entity_id);
        $set('icd_uri', $code?->icd_uri);
        $set('icd_code', $code?->code);
        $set('icd10_code', $code !== null && $code->source !== 'who' ? $code->code : null);

        if ($code !== null) {
            $set('description', $code->description);
        }
    }
}
