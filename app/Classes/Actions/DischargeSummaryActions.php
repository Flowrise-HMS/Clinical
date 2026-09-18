<?php

namespace Modules\Clinical\Classes\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\DischargeSummaryService;
use Modules\Clinical\Enums\DischargeCondition;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Models\DischargeSummary;

/**
 * Filament actions around the structured discharge summary. Static builders
 * like EncounterActions so every surface (workspace, ward board, resource)
 * shares one form.
 */
class DischargeSummaryActions
{
    public static function edit(Model $encounter): Action
    {
        return Action::make('discharge_summary')
            ->label(fn (): string => self::summaryFor($encounter)?->isSigned() ? __('Amend discharge summary') : __('Discharge summary'))
            ->icon('heroicon-m-document-text')
            ->color('primary')
            ->visible(fn (): bool => $encounter->isInpatient() && (Auth::user()?->can('Update Encounter') ?? false))
            ->modalHeading(fn (): string => __('Discharge summary — :name', ['name' => $encounter->patient?->full_name ?? '']))
            ->modalWidth('4xl')
            ->slideOver()
            ->fillForm(fn (): array => self::formData(app(DischargeSummaryService::class)->draftFor($encounter, Auth::id())))
            ->schema(fn (): array => self::schema(self::summaryFor($encounter)))
            ->action(function (array $data) use ($encounter): void {
                $service = app(DischargeSummaryService::class);
                $summary = $service->draftFor($encounter, Auth::id());
                $user = Auth::user();

                try {
                    if ($summary->isSigned()) {
                        $service->amend($summary, $data, $user, $data['amendment_reason'] ?? null);
                        Notification::make()->title(__('Discharge summary amended'))->success()->send();
                    } else {
                        $service->update($summary, $data, $user?->id);
                        Notification::make()->title(__('Discharge summary saved'))->success()->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()->title(__('Could not save discharge summary'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function sign(Model $encounter): Action
    {
        return Action::make('sign_discharge_summary')
            ->label(__('Sign discharge summary'))
            ->icon('heroicon-m-pencil-square')
            ->color('success')
            ->visible(fn (): bool => $encounter->isInpatient()
                && (Auth::user()?->can('sign_discharge_summary') ?? false)
                && ($summary = self::summaryFor($encounter)) !== null
                && ! $summary->isSigned())
            ->requiresConfirmation()
            ->modalHeading(__('Sign discharge summary'))
            ->modalDescription(__('Signing locks the summary; later changes are recorded as amendments.'))
            ->action(function () use ($encounter): void {
                $summary = self::summaryFor($encounter);

                if ($summary === null) {
                    return;
                }

                try {
                    app(DischargeSummaryService::class)->sign($summary, Auth::user());
                    Notification::make()->title(__('Discharge summary signed'))->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title(__('Could not sign'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function print(Model $encounter): Action
    {
        return Action::make('print_discharge_summary')
            ->label(__('Print discharge summary'))
            ->icon('heroicon-m-printer')
            ->color('gray')
            ->visible(fn (): bool => $encounter->isInpatient()
                && (Auth::user()?->can('print_discharge_summary') ?? false)
                && self::summaryFor($encounter) !== null)
            ->url(fn (): ?string => ($summary = self::summaryFor($encounter))
                ? route('clinical.discharge-summaries.pdf', ['dischargeSummary' => $summary])
                : null, shouldOpenInNewTab: true);
    }

    public static function summaryFor(Model $encounter): ?DischargeSummary
    {
        return DischargeSummary::query()->where('encounter_id', $encounter->getKey())->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(DischargeSummary $summary): array
    {
        return [
            'presenting_complaint' => $summary->presenting_complaint,
            'admission_diagnosis' => $summary->admission_diagnosis,
            'discharge_diagnoses' => $summary->discharge_diagnoses ?? [],
            'hospital_course' => $summary->hospital_course,
            'procedures' => $summary->procedures,
            'condition_at_discharge' => $summary->condition_at_discharge?->value,
            'discharge_medications' => $summary->discharge_medications ?? [],
            'instructions' => $summary->instructions,
            'diet' => $summary->diet,
            'activity' => $summary->activity,
            'follow_up_at' => $summary->follow_up_at,
            'follow_up_notes' => $summary->follow_up_notes,
            'disposition' => $summary->disposition?->value,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function schema(?DischargeSummary $summary): array
    {
        return array_values(array_filter([
            $summary?->isSigned()
                ? Textarea::make('amendment_reason')
                    ->label('Reason for amendment')
                    ->rows(2)
                    ->required()
                    ->columnSpanFull()
                : null,
            Section::make(__('Clinical picture'))
                ->columns(2)
                ->schema([
                    Textarea::make('presenting_complaint')->label('Presenting complaint')->rows(2)->columnSpanFull(),
                    TextInput::make('admission_diagnosis')->label('Admission diagnosis')->maxLength(255),
                    Select::make('condition_at_discharge')->label('Condition at discharge')->options(DischargeCondition::class),
                    Repeater::make('discharge_diagnoses')
                        ->label('Discharge diagnoses')
                        ->columns(4)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('description')->label('Diagnosis')->required()->columnSpan(2),
                            TextInput::make('icd10_code')->label('ICD-10'),
                            Select::make('type')->options(['primary' => 'Primary', 'secondary' => 'Secondary', 'complication' => 'Complication'])->default('secondary'),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel(__('Add diagnosis')),
                    RichEditor::make('hospital_course')->label('Hospital course')->toolbarButtons(['bold', 'bulletList', 'italic', 'orderedList'])->columnSpanFull(),
                    Textarea::make('procedures')->label('Procedures')->rows(2)->columnSpanFull(),
                ]),
            Section::make(__('Going home'))
                ->columns(2)
                ->schema([
                    Repeater::make('discharge_medications')
                        ->label('Discharge medications')
                        ->columns(6)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('drug')->label('Medication')->required()->columnSpan(2),
                            TextInput::make('dose')->label('Dose'),
                            TextInput::make('frequency')->label('Frequency'),
                            TextInput::make('duration_days')->label('Days')->numeric(),
                            TextInput::make('instructions')->label('Instructions'),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel(__('Add medication')),
                    RichEditor::make('instructions')->label('Instructions to patient')->toolbarButtons(['bold', 'bulletList', 'italic', 'orderedList'])->columnSpanFull(),
                    TextInput::make('diet')->label('Diet')->maxLength(255),
                    TextInput::make('activity')->label('Activity')->maxLength(255),
                    DateTimePicker::make('follow_up_at')->label('Follow-up')->seconds(false),
                    TextInput::make('follow_up_notes')->label('Follow-up notes')->maxLength(255),
                    Select::make('disposition')->label('Planned disposition')->options(DischargeDisposition::class),
                ]),
        ]));
    }
}
