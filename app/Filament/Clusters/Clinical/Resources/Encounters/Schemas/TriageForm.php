<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas;

use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\TriageService;
use Modules\Clinical\Classes\Support\Triage\SatsDiscriminators;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageDisposition;
use Modules\Clinical\Enums\TriageMobility;
use Modules\Core\Support\SuperAdmin;

/**
 * South African Triage Scale (SATS) form: clinical discriminators, TEWS vital signs
 * with a live score, and the triage decision. Shared by the clinical workspace
 * Triage tab and the Triage action on encounters.
 */
class TriageForm
{
    /**
     * @param  Closure|string|null  $defaultAgeBand  TriageAgeBand value (usually from the patient's date of birth)
     * @return array<int, mixed>
     */
    public static function schema(Closure|string|null $defaultAgeBand = null): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('age_band')
                    ->label(__('TEWS version'))
                    ->options(TriageAgeBand::class)
                    ->default($defaultAgeBand ?? TriageAgeBand::ADULT->value)
                    ->helperText(fn (Get $get): ?string => static::band($get)->getDescription())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        foreach (TriageCategory::clinical() as $category) {
                            $set('discriminators_'.$category->value, []);
                        }
                        $set('mobility', null);
                    }),
                TextInput::make('chief_complaint')
                    ->label(__('Presenting complaint'))
                    ->maxLength(255),
            ]),

            Section::make(__('Clinical signs'))
                ->description(__('Any emergency sign makes the patient Red, a very urgent sign Orange and an urgent sign Yellow, whatever the TEWS.'))
                ->compact()
                ->collapsible()
                ->schema([
                    static::discriminatorList(TriageCategory::RED, __('Emergency signs (Red)')),
                    static::discriminatorList(TriageCategory::ORANGE, __('Very urgent signs (Orange)')),
                    static::discriminatorList(TriageCategory::YELLOW, __('Urgent signs (Yellow)')),
                ]),

            Hidden::make('source_vital_sign_id'),
            Hidden::make('source_vitals_recorded_at'),

            Section::make(__('Vital signs and TEWS'))
                ->description(function (Get $get): ?string {
                    $recordedAt = $get('source_vitals_recorded_at');

                    if (blank($recordedAt)) {
                        return null;
                    }

                    $time = Carbon::parse($recordedAt)->timezone(config('app.timezone'));

                    return __('Prefilled from vitals recorded :when (:time). Update anything that has changed.', [
                        'when' => $time->diffForHumans(),
                        'time' => $time->format('d M H:i'),
                    ]);
                })
                ->compact()
                ->columns(['default' => 2, 'md' => 3])
                ->schema([
                    TextInput::make('respiratory_rate')
                        ->label(__('Respiratory rate'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(150)
                        ->suffix('/min')
                        ->live(onBlur: true),
                    TextInput::make('heart_rate')
                        ->label(__('Heart rate'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(300)
                        ->suffix('bpm')
                        ->live(onBlur: true),
                    TextInput::make('temperature')
                        ->label(__('Temperature'))
                        ->numeric()
                        ->step(0.1)
                        ->minValue(25)
                        ->maxValue(45)
                        ->suffix('°C')
                        ->live(onBlur: true),
                    TextInput::make('systolic_bp')
                        ->label(__('Systolic BP'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(300)
                        ->suffix('mmHg')
                        ->visible(fn (Get $get): bool => ! static::band($get)->isPaediatric())
                        ->live(onBlur: true),
                    TextInput::make('diastolic_bp')
                        ->label(__('Diastolic BP'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(200)
                        ->suffix('mmHg')
                        ->visible(fn (Get $get): bool => ! static::band($get)->isPaediatric()),
                    TextInput::make('spo2')
                        ->label(__('SpO2'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%'),
                    Select::make('mobility')
                        ->label(__('Mobility'))
                        ->options(fn (Get $get): array => TriageMobility::optionsFor(static::band($get)))
                        ->live(),
                    Select::make('avpu')
                        ->label(__('AVPU'))
                        ->options(fn (Get $get): array => static::avpuOptions(static::band($get)))
                        ->live(),
                    Toggle::make('trauma')
                        ->label(__('Trauma'))
                        ->inline(false)
                        ->live(),
                ]),

            TextEntry::make('triage_result')
                ->label(__('Triage result'))
                ->state(fn (Get $get): string => static::resultHtml($get))
                ->html(),

            Section::make(__('Decision'))
                ->compact()
                ->columns(2)
                ->schema([
                    Select::make('disposition')
                        ->label(__('Next step'))
                        ->options(TriageDisposition::class)
                        ->default(TriageDisposition::CONSULTATION->value)
                        ->required(),
                    Select::make('override_category')
                        ->label(__('Override category (senior clinician)'))
                        ->options(collect(TriageCategory::cases())->mapWithKeys(
                            fn (TriageCategory $category): array => [$category->value => (string) $category->getLabel()]
                        )->all())
                        ->placeholder(__('Use the calculated category'))
                        ->visible(fn (): bool => static::canOverride())
                        ->live(),
                    Textarea::make('override_reason')
                        ->label(__('Reason for override'))
                        ->rows(2)
                        ->required(fn (Get $get): bool => filled($get('override_category')))
                        ->visible(fn (Get $get): bool => static::canOverride() && filled($get('override_category')))
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label(__('Triage notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Whether the current user may override the calculated category (SATS "senior
     * healthcare professional's discretion").
     */
    public static function canOverride(): bool
    {
        $user = Auth::user();

        return $user !== null && (
            SuperAdmin::check($user)
            || $user->can('override_triage_category')
            || (method_exists($user, 'hasRole') && $user->hasRole('doctor'))
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function forService(array $state): array
    {
        if (! static::canOverride()) {
            unset($state['override_category'], $state['override_reason']);
        }

        unset($state['triage_result'], $state['source_vitals_recorded_at']);

        return $state;
    }

    protected static function discriminatorList(TriageCategory $category, string $label): CheckboxList
    {
        return CheckboxList::make('discriminators_'.$category->value)
            ->label($label)
            ->options(fn (Get $get): array => SatsDiscriminators::options(static::band($get), $category))
            ->columns(['default' => 1, 'md' => 2])
            ->gridDirection('row')
            ->live();
    }

    /**
     * @return array<string, string>
     */
    protected static function avpuOptions(TriageAgeBand $band): array
    {
        return collect(AvpuLevel::cases())
            ->reject(fn (AvpuLevel $level): bool => $band === TriageAgeBand::YOUNGER_CHILD && $level === AvpuLevel::CONFUSED)
            ->mapWithKeys(fn (AvpuLevel $level): array => [$level->value => (string) $level->getLabel()])
            ->all();
    }

    protected static function band(Get $get): TriageAgeBand
    {
        return enum_try_from(TriageAgeBand::class, $get('age_band')) ?? TriageAgeBand::ADULT;
    }

    protected static function resultHtml(Get $get): string
    {
        $state = ['age_band' => $get('age_band'), 'mobility' => $get('mobility'), 'avpu' => $get('avpu'), 'trauma' => $get('trauma')];

        foreach (TriageService::VITAL_FIELDS as $field) {
            $state[$field] = $get($field);
        }

        foreach (TriageCategory::clinical() as $category) {
            $state['discriminators_'.$category->value] = $get('discriminators_'.$category->value) ?? [];
        }

        $preview = app(TriageService::class)->preview($state);
        $category = $preview['category'];
        $tews = $preview['tews'];

        $override = enum_try_from(TriageCategory::class, $get('override_category'));
        $shown = $override ?? $category;

        $lines = [
            '<div class="flex flex-wrap items-center gap-3">'
                .'<span class="inline-flex items-center rounded-lg px-3 py-1 text-sm font-semibold '.static::badgeClasses($shown).'">'.e((string) $shown->getLabel()).'</span>'
                .'<span class="text-sm font-medium text-gray-900 dark:text-white">TEWS '.$tews->score.'</span>'
                .'<span class="text-sm text-gray-500 dark:text-gray-400">'.e((string) $shown->getDescription()).'</span>'
                .'</div>',
        ];

        if ($preview['discriminator_category'] !== null && $preview['discriminator_category'] !== $tews->category) {
            $lines[] = '<p class="text-sm text-gray-600 dark:text-gray-300">'.e(__('Clinical signs raise the category from the TEWS colour (:tews).', ['tews' => $tews->category->shortLabel()])).'</p>';
        }

        if ($override !== null && $override !== $category) {
            $lines[] = '<p class="text-sm text-warning-700 dark:text-warning-400">'.e(__('Overridden from the calculated :category.', ['category' => $category->shortLabel()])).'</p>';
        }

        if ($tews->missing !== []) {
            $labels = array_map(fn (string $key): string => str_replace('_', ' ', $key), $tews->missing);
            $lines[] = '<p class="text-xs text-gray-500 dark:text-gray-400">'.e(__('Not recorded (scored 0): :items', ['items' => implode(', ', $labels)])).'</p>';
        }

        foreach ($tews->prompts as $prompt) {
            $lines[] = '<p class="text-sm text-danger-700 dark:text-danger-400">'.e($prompt).'</p>';
        }

        return '<div class="space-y-2">'.implode('', $lines).'</div>';
    }

    public static function badgeClasses(TriageCategory $category): string
    {
        return match ($category) {
            TriageCategory::RED => 'bg-red-600 text-white',
            TriageCategory::ORANGE => 'bg-orange-500 text-white',
            TriageCategory::YELLOW => 'bg-yellow-300 text-gray-950',
            TriageCategory::GREEN => 'bg-green-600 text-white',
            TriageCategory::BLUE => 'bg-blue-600 text-white',
        };
    }
}
