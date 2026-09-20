<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Schemas\EncounterCoverageSchema;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Classes\Services\BranchService;

class EncounterForm
{
    /**
     * Only the current status and the transitions the encounter allows from it
     * are offered, so the raw select cannot skip lifecycle steps.
     *
     * @return array<string, string>
     */
    public static function statusOptions(?Encounter $record): array
    {
        if ($record === null || $record->status === null) {
            // New visits start planned, or arrived for walk-ins.
            return collect([EncounterStatus::PLANNED, EncounterStatus::ARRIVED])
                ->mapWithKeys(fn (EncounterStatus $status): array => [$status->value => $status->getLabel()])
                ->all();
        }

        return collect(EncounterStatus::cases())
            ->filter(fn (EncounterStatus $status): bool => $status === $record->status || $record->canTransitionTo($status))
            ->mapWithKeys(fn (EncounterStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * Default status for a new encounter from the Clinical settings, limited
     * to the statuses a new visit may start in.
     */
    public static function defaultStatus(): EncounterStatus
    {
        $configured = enum_try_from(EncounterStatus::class, app_settings()->clinicalValue('default_encounter_status', 'planned'));

        return in_array($configured, [EncounterStatus::PLANNED, EncounterStatus::ARRIVED], true)
            ? $configured
            : EncounterStatus::PLANNED;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(array_merge([
                Section::make('Patient Information')
                    ->description('Select an existing patient or register a walk-in guest')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('patient_id')
                                    ->relationship('patient', 'mrn')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record ? $record->full_name : 'Select patient')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Patient (Existing)'),

                                TextInput::make('guest_name')
                                    ->label('Guest Name')
                                    ->helperText('For walk-in guests without patient record'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('guest_phone')
                                    ->label('Guest Phone')
                                    ->tel(),

                                TextInput::make('guest_email')
                                    ->label('Guest Email')
                                    ->email()
                                    ->nullable(),
                            ]),
                    ]),

            ], self::quickElements()));
    }

    public static function quickElements(): array
    {
        return [
            Section::make('Encounter Details')
                ->description('Basic encounter information')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Select::make('type')
                                ->options(EncounterType::class)
                                ->default(fn (): string => (string) app_settings()->clinicalValue('default_encounter_type', 'outpatient'))
                                ->required()
                                ->live()
                                ->label('Encounter Type'),

                            Select::make('priority')
                                ->options(EncounterPriority::class)
                                ->default(fn (): string => (string) app_settings()->clinicalValue('default_encounter_class', 'routine'))
                                ->required()
                                ->label('Priority'),

                            Select::make('status')
                                ->options(fn (?Encounter $record): array => self::statusOptions($record))
                                ->default(fn (): string => self::defaultStatus()->value)
                                ->required()
                                ->rule(fn (?Encounter $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $target = enum_try_from(EncounterStatus::class, $value);

                                    if ($record === null || $target === null || $target === $record->status) {
                                        return;
                                    }

                                    if (! $record->canTransitionTo($target)) {
                                        $fail(__('An encounter cannot move from :from to :to.', [
                                            'from' => $record->status?->getLabel() ?? $record->status?->value,
                                            'to' => $target->getLabel(),
                                        ]));
                                    }
                                })
                                ->label('Status'),

                            EncounterCoverageSchema::coverageField(),
                        ]),

                    EncounterCoverageSchema::claimCheckCodeField(),

                    Fieldset::make('Clinical Information')
                        ->schema([
                            // Textarea::make('chief_complaint')
                            //     ->label('Chief Complaint')
                            //     ->helperText('Primary reason for visit')
                            //     ->rows(2)
                            //     ->columnSpanFull(),

                            RichEditor::make('notes')
                                ->label('Clinical Notes')
                                ->toolbarButtons([
                                    'attachFiles',
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'orderedList',
                                    'strike',
                                ])
                                ->fileAttachmentsDisk('local')
                                ->fileAttachmentsDirectory('encounters')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Location & Assignment')
                ->description('Encounter location and assigned resources')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('branch_id')
                                ->relationship('branch', 'name')
                                ->required()
                                ->default(fn () => app(BranchService::class)->getDefaultBranchId())
                                ->label('Branch/ Facility'),

                            Select::make('location_id')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->label('Current Location'),

                            Select::make('department_id')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->label('Department'),
                        ]),
                ]),
        ];
    }
}
