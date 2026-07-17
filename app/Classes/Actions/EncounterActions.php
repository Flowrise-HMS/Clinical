<?php

namespace Modules\Clinical\Classes\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Core\Models\Branch;

class EncounterActions
{
    public static function admit(Model $encounter): Action
    {
        return Action::make('admit')
            ->label('Admit Patient')
            ->icon('heroicon-m-arrow-right-start-on-rectangle')
            ->color('success')
            ->visible(fn () => $encounter->canTransitionTo(EncounterStatus::ARRIVED)
                || ($encounter->type === EncounterType::INPATIENT && $encounter->status?->isActive() && blank($encounter->bed_id)))
            ->modalHeading(__('Admit Patient'))
            ->modalDescription(__('Admit the patient to a ward and bed. This will mark the encounter as Arrived and assign the selected bed. Only beds not currently occupied by another active patient are shown.'))
            ->slideOver()
            ->schema([
                Select::make('ward_id')
                    ->label('Ward / Room')
                    ->options(fn () => app(BedAssignmentService::class)->getWardsForBranch($encounter->branch_id))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('bed_id', null)),
                Select::make('bed_id')
                    ->label('Bed')
                    ->options(fn (callable $get) => $get('ward_id')
                        ? app(BedAssignmentService::class)->getAvailableBeds($get('ward_id'))
                        : [])
                    ->searchable()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('ward_id'))),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ])
            ->action(fn (array $data) => app(AdtService::class)->assignBed(
                $encounter,
                $data['bed_id'],
                notes: $data['notes'] ?? null,
            ));
    }

    public static function triage(Model $encounter): Action
    {
        return Action::make('triage')
            ->label('Triage')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('warning')
            ->visible(fn () => $encounter->status === EncounterStatus::ARRIVED)
            ->schema([
                Select::make('priority')
                    ->label('Priority')
                    ->options(EncounterPriority::class)
                    ->required(),
            ])
            ->action(fn (array $data) => app(EncounterService::class)->triage(
                $encounter,
                EncounterPriority::from($data['priority'])
            ));
    }

    public static function transferInternal(Model $encounter): Action
    {
        return Action::make('transfer_internal')
            ->label('Transfer (internal)')
            ->icon('heroicon-m-arrows-right-left')
            ->color('info')
            ->visible(fn () => ! $encounter->isCompleted()
                && ($encounter->status?->isActive() || $encounter->status === EncounterStatus::PLANNED)
                && filled($encounter->bed_id))
            ->modalHeading(__('Internal transfer'))
            ->modalDescription(__('Move the patient to another ward/bed within this facility. The encounter continues.'))
            ->slideOver()
            ->schema([
                Select::make('ward_id')
                    ->label('Ward / Room')
                    ->options(fn () => app(BedAssignmentService::class)->getWardsForBranch($encounter->branch_id))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('bed_id', null)),
                Select::make('bed_id')
                    ->label('Bed')
                    ->options(fn (callable $get) => $get('ward_id')
                        ? app(BedAssignmentService::class)->getAvailableBeds($get('ward_id'))
                        : [])
                    ->searchable()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('ward_id'))),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ])
            ->action(fn (array $data) => app(AdtService::class)->transferInternal(
                $encounter,
                $data['bed_id'],
                notes: $data['notes'] ?? null,
            ));
    }

    public static function transferOut(Model $encounter): Action
    {
        return Action::make('transfer_out')
            ->label('Transfer out')
            ->icon('heroicon-m-building-office-2')
            ->color('warning')
            ->visible(fn () => $encounter->canTransitionTo(EncounterStatus::FINISHED))
            ->modalHeading(__('Transfer out'))
            ->modalDescription(__('End this encounter and transfer the patient to another branch or external facility.'))
            ->slideOver()
            ->schema([
                Select::make('destination_type')
                    ->label('Destination type')
                    ->options(AdtDestinationType::class)
                    ->default(AdtDestinationType::ExternalFacility->value)
                    ->live()
                    ->required(),
                Select::make('destination_branch_id')
                    ->label('Destination branch')
                    ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (callable $get) => $get('destination_type') === AdtDestinationType::Branch->value)
                    ->required(fn (callable $get) => $get('destination_type') === AdtDestinationType::Branch->value),
                TextInput::make('destination_label')
                    ->label('Destination')
                    ->visible(fn (callable $get) => $get('destination_type') !== AdtDestinationType::Branch->value)
                    ->required(fn (callable $get) => $get('destination_type') === AdtDestinationType::ExternalFacility->value),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ])
            ->action(fn (array $data) => app(AdtService::class)->transferOut(
                $encounter,
                AdtDestinationType::from($data['destination_type']),
                destinationLabel: $data['destination_label'] ?? null,
                destinationBranchId: $data['destination_branch_id'] ?? null,
                notes: $data['notes'] ?? null,
            ));
    }

    public static function discharge(Model $encounter): Action
    {
        return Action::make('discharge')
            ->label('Discharge')
            ->icon('heroicon-m-arrow-left-end-on-rectangle')
            ->color('danger')
            ->visible(fn () => $encounter->canTransitionTo(EncounterStatus::FINISHED))
            ->modalHeading(__('Discharge Patient'))
            ->modalDescription(__('Discharge the patient from this encounter. This will finalize their stay, free up the assigned bed, and generate any pending invoices for settlement.'))
            ->slideOver()
            ->schema([
                Select::make('discharge_disposition')
                    ->label('Disposition')
                    ->options(DischargeDisposition::class)
                    ->default('completed')
                    ->required()
                    ->live(),
                TextInput::make('transfer_destination')
                    ->label('Transfer Destination')
                    ->visible(fn (callable $get) => $get('discharge_disposition') === 'transferred'),
                Textarea::make('notes')
                    ->label('Discharge notes')
                    ->rows(2),
            ])
            ->action(fn (array $data) => app(AdtService::class)->discharge(
                $encounter,
                DischargeDisposition::from($data['discharge_disposition']),
                $data['transfer_destination'] ?? null,
                notes: $data['notes'] ?? null,
            ));
    }

    public static function cancel(Model $encounter): Action
    {
        return Action::make('cancel')
            ->label('Cancel Encounter')
            ->icon('heroicon-m-x-circle')
            ->color('gray')
            ->visible(fn () => ! $encounter->isCompleted())
            ->modalHeading(__('Cancel Encounter'))
            ->modalDescription(__('Cancel this encounter. This will free up the assigned bed and generate any pending invoices for services rendered so far.'))
            ->slideOver()
            ->schema([
                Textarea::make('reason')
                    ->label('Reason for Cancellation')
                    ->required(),
            ])
            ->action(fn (array $data) => app(EncounterService::class)->cancelEncounter($encounter, $data['reason']));
    }

    public static function assignToWard(
        Model $encounter,
        BedAssignmentService $bedAssignmentService,
        ?Closure $onSuccess = null,
    ): Action {
        $action = Action::make('assign_to_ward')
            ->label('Assign to Ward / Bed')
            ->icon('heroicon-m-building-office')
            ->color('success')
            ->slideOver()
            ->modalHeading(__('Assign to Ward / Bed'))
            ->modalDescription(__('Select a ward and bed for this patient. If the encounter is still in Planned status, it will be admitted automatically. The bed must not be occupied by another active patient.'))
            ->visible(fn () => $encounter->type === EncounterType::INPATIENT
                && ($encounter->canTransitionTo(EncounterStatus::ARRIVED) || $encounter?->status?->isActive()))
            ->schema([
                Select::make('ward_id')
                    ->label('Ward / Room')
                    ->options(fn (): array => $bedAssignmentService->getWardsForBranch($encounter->branch_id)->toArray())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('bed_id', null)),
                Select::make('bed_id')
                    ->label('Bed')
                    ->options(fn (callable $get): array => $get('ward_id')
                        ? $bedAssignmentService->getAvailableBeds($get('ward_id'))->toArray()
                        : [])
                    ->searchable()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('ward_id'))),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ])
            ->action(function (array $data) use ($encounter, $onSuccess) {
                app(AdtService::class)->assignBed(
                    $encounter,
                    $data['bed_id'],
                    notes: $data['notes'] ?? null,
                );

                if ($onSuccess) {
                    ($onSuccess)($data);
                }
            });

        return $action;
    }
}
