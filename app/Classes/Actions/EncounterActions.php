<?php

namespace Modules\Clinical\Classes\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\DischargeReadinessService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Exceptions\DischargeBlockedException;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;

class EncounterActions
{
    /**
     * Admission can be requested for any open visit that does not occupy a
     * bed yet, unless a request is already waiting on ward staff.
     */
    public static function isAdmitVisible(Model $encounter): bool
    {
        if ($encounter->isCompleted() || filled($encounter->bed_id)) {
            return false;
        }

        if (! ($encounter->status === EncounterStatus::PLANNED || $encounter->status?->isActive())) {
            return false;
        }

        return ! $encounter->hasPendingAdmissionRequest();
    }

    public static function isAdmissionDecisionVisible(Model $encounter): bool
    {
        return ! $encounter->isCompleted() && $encounter->hasPendingAdmissionRequest();
    }

    /**
     * Inpatients leave through discharge; every other active visit is simply
     * completed once the consultation is over.
     */
    public static function isCompleteVisible(Model $encounter): bool
    {
        return $encounter->status?->isActive()
            && $encounter->type !== EncounterType::INPATIENT
            && blank($encounter->bed_id);
    }

    /**
     * Discharge closes bedded care. Besides encounters that can finish right
     * away, an inpatient or bed-occupying encounter that is still arrived or
     * triaged is dischargeable too: the service starts it before finishing.
     */
    public static function isDischargeVisible(Model $encounter): bool
    {
        if ($encounter->canTransitionTo(EncounterStatus::FINISHED)) {
            return true;
        }

        return ($encounter->isInpatient() || filled($encounter->bed_id))
            && (bool) $encounter->status?->isActive();
    }

    public static function admit(Model $encounter): Action
    {
        return Action::make('admit')
            ->label('Request Admission')
            ->icon('heroicon-m-arrow-right-start-on-rectangle')
            ->color('success')
            ->visible(fn () => self::isAdmitVisible($encounter))
            ->modalHeading(__('Request admission'))
            ->modalDescription(__('Ask ward staff to admit this patient. The ward nurse will accept the request and confirm the bed, or reject it with a reason. No bed is occupied until the request is accepted.'))
            ->modalSubmitActionLabel(__('Send request'))
            ->slideOver()
            ->schema(self::requestAdmissionSchema($encounter))
            ->action(fn (array $data) => app(AdtService::class)->requestAdmission(
                $encounter,
                $data['ward_id'],
                bedId: $data['bed_id'] ?? null,
                notes: $data['notes'] ?? null,
            ));
    }

    /**
     * @return array<int, mixed>
     */
    public static function requestAdmissionSchema(Model $encounter): array
    {
        return [
            Select::make('ward_id')
                ->label('Ward / Room')
                ->options(fn () => app(BedAssignmentService::class)->getWardsForBranch($encounter->branch_id))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, callable $set) => $set('bed_id', null)),
            Select::make('bed_id')
                ->label('Preferred bed (optional)')
                ->helperText(__('Ward staff confirm the final bed when they accept.'))
                ->options(fn (callable $get) => $get('ward_id')
                    ? app(BedAssignmentService::class)->getAvailableBeds($get('ward_id'))
                    : [])
                ->searchable()
                ->disabled(fn (callable $get) => blank($get('ward_id'))),
            Textarea::make('notes')
                ->label('Notes for the ward')
                ->rows(2),
        ];
    }

    public static function acceptAdmission(Model $encounter): Action
    {
        return Action::make('accept_admission')
            ->label('Accept Admission')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn () => self::isAdmissionDecisionVisible($encounter))
            ->modalHeading(__('Accept admission'))
            ->modalDescription(fn (): string => self::pendingRequestSummary($encounter))
            ->modalSubmitActionLabel(__('Admit to bed'))
            ->slideOver()
            ->schema(fn (): array => self::acceptAdmissionSchema($encounter))
            ->action(function (array $data) use ($encounter): void {
                $request = $encounter->pendingAdmissionRequest()->firstOrFail();

                app(AdtService::class)->acceptAdmission(
                    $request,
                    $data['bed_id'],
                    notes: $data['notes'] ?? null,
                );
            });
    }

    /**
     * @return array<int, mixed>
     */
    public static function acceptAdmissionSchema(Model $encounter): array
    {
        $request = $encounter->pendingAdmissionRequest()->with('requestedWard')->first();
        $wardId = $request?->requested_ward_id;
        $available = $wardId
            ? app(BedAssignmentService::class)->getAvailableBeds($wardId, $encounter->id, $request?->id)
            : collect();

        return [
            Select::make('bed_id')
                ->label($request?->requestedWard?->name
                    ? __('Bed in :ward', ['ward' => $request->requestedWard->name])
                    : __('Bed'))
                ->options($available)
                ->default($request && $available->has($request->requested_bed_id) ? $request->requested_bed_id : null)
                ->searchable()
                ->required(),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(2),
        ];
    }

    public static function rejectAdmission(Model $encounter): Action
    {
        return Action::make('reject_admission')
            ->label('Reject Admission')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->visible(fn () => self::isAdmissionDecisionVisible($encounter))
            ->modalHeading(__('Reject admission'))
            ->modalDescription(fn (): string => self::pendingRequestSummary($encounter))
            ->modalSubmitActionLabel(__('Reject'))
            ->schema(self::rejectAdmissionSchema())
            ->action(function (array $data) use ($encounter): void {
                $request = $encounter->pendingAdmissionRequest()->firstOrFail();

                app(AdtService::class)->rejectAdmission($request, $data['reason']);
            });
    }

    /**
     * @return array<int, mixed>
     */
    public static function rejectAdmissionSchema(): array
    {
        return [
            Textarea::make('reason')
                ->label('Reason for rejection')
                ->rows(3)
                ->required(),
        ];
    }

    public static function pendingRequestSummary(Model $encounter): string
    {
        $request = $encounter->pendingAdmissionRequest()->with(['requestedWard', 'requestedBed', 'requester'])->first();

        if ($request === null) {
            return __('No admission request is pending.');
        }

        $parts = [
            __('Requested ward: :ward', ['ward' => $request->requestedWard?->name ?? '—']),
        ];

        if ($request->requestedBed) {
            $parts[] = __('Preferred bed: :bed', ['bed' => $request->requestedBed->name]);
        }

        $parts[] = __('Requested by :name :when', [
            'name' => $request->requester?->name ?? __('unknown'),
            'when' => $request->requested_at?->diffForHumans() ?? '',
        ]);

        if (filled($request->notes)) {
            $parts[] = __('Notes: :notes', ['notes' => $request->notes]);
        }

        return implode(' · ', $parts);
    }

    public static function complete(Model $encounter): Action
    {
        return Action::make('complete')
            ->label('Complete Encounter')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->visible(fn () => self::isCompleteVisible($encounter))
            ->modalHeading(__('Complete Encounter'))
            ->modalDescription(__('Mark this visit as finished. The encounter is closed and any pending charges are finalized for billing.'))
            ->modalSubmitActionLabel(__('Complete'))
            ->schema([
                Textarea::make('notes')
                    ->label('Completion notes')
                    ->rows(2),
            ])
            ->action(fn (array $data) => app(EncounterService::class)->completeEncounter(
                $encounter,
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
                enum_from(EncounterPriority::class, $data['priority'])
            ));
    }

    public static function isPassVisible(Model $encounter): bool
    {
        return $encounter->isInpatient()
            && filled($encounter->bed_id)
            && $encounter->status === EncounterStatus::IN_PROGRESS;
    }

    public static function isReturnFromPassVisible(Model $encounter): bool
    {
        return $encounter->isInpatient() && $encounter->status === EncounterStatus::ON_LEAVE;
    }

    public static function sendOnPass(Model $encounter): Action
    {
        return Action::make('send_on_pass')
            ->label('Send on pass')
            ->icon('heroicon-m-arrow-right-start-on-rectangle')
            ->color('warning')
            ->visible(fn () => self::isPassVisible($encounter))
            ->modalHeading(__('Send patient on pass'))
            ->modalDescription(__('The patient leaves the ward temporarily. The bed stays assigned to them.'))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->rows(2)
                    ->required(),
                DateTimePicker::make('expected_return_at')
                    ->label('Expected return')
                    ->seconds(false)
                    ->minDate(now()),
            ])
            ->action(fn (array $data) => app(AdtService::class)->sendOnPass(
                $encounter,
                $data['reason'] ?? null,
                filled($data['expected_return_at'] ?? null) ? Carbon::parse($data['expected_return_at']) : null,
            ));
    }

    public static function returnFromPass(Model $encounter): Action
    {
        return Action::make('return_from_pass')
            ->label('Return from pass')
            ->icon('heroicon-m-arrow-left-end-on-rectangle')
            ->color('success')
            ->visible(fn () => self::isReturnFromPassVisible($encounter))
            ->requiresConfirmation()
            ->modalHeading(__('Patient returned from pass'))
            ->action(fn () => app(AdtService::class)->returnFromPass($encounter));
    }

    public static function setExpectedDischarge(Model $encounter): Action
    {
        return Action::make('set_expected_discharge')
            ->label(fn (): string => $encounter->expected_discharge_at ? __('Change expected discharge') : __('Set expected discharge'))
            ->icon('heroicon-m-calendar-days')
            ->color('gray')
            ->visible(fn () => $encounter->isInpatient() && ! $encounter->isCompleted())
            ->modalHeading(__('Expected discharge'))
            ->schema([
                DateTimePicker::make('expected_discharge_at')
                    ->label('Expected discharge')
                    ->seconds(false)
                    ->default($encounter->expected_discharge_at),
                Textarea::make('reason')->label('Reason for change')->rows(2),
            ])
            ->action(fn (array $data) => app(AdtService::class)->setExpectedDischarge(
                $encounter,
                filled($data['expected_discharge_at'] ?? null) ? Carbon::parse($data['expected_discharge_at']) : null,
                Auth::id(),
                $data['reason'] ?? null,
            ));
    }

    public static function isCancelAdmissionRequestVisible(Model $encounter, ?int $userId = null, bool $canDecide = false): bool
    {
        $request = $encounter->pendingAdmissionRequest()->first();

        return $request !== null && $request->isCancellableBy($userId ?? Auth::id(), $canDecide);
    }

    public static function cancelAdmissionRequest(Model $encounter, bool $canDecide = false): Action
    {
        return Action::make('cancel_admission_request')
            ->label('Withdraw request')
            ->icon('heroicon-m-x-circle')
            ->color('gray')
            ->visible(fn () => self::isCancelAdmissionRequestVisible($encounter, Auth::id(), $canDecide))
            ->modalHeading(__('Withdraw admission request'))
            ->modalDescription(__('The ward will no longer see this request and any reserved bed is released.'))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->rows(2),
            ])
            ->action(function (array $data) use ($encounter): void {
                $request = $encounter->pendingAdmissionRequest()->firstOrFail();

                app(AdtService::class)->cancelAdmissionRequest($request, $data['reason'] ?? null);
            });
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
                        ? app(BedAssignmentService::class)->getAvailableBeds($get('ward_id'), $encounter->id)
                        : [])
                    ->searchable()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('ward_id'))),
                RichEditor::make('notes')
                    ->label('Notes')
                    ->toolbarButtons([
                        'bold',
                        'bulletList',
                        'italic',
                        'orderedList',
                    ]),
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
                RichEditor::make('notes')
                    ->label('Notes')
                    ->toolbarButtons([
                        'bold',
                        'bulletList',
                        'italic',
                        'orderedList',
                    ]),
            ])
            ->action(fn (array $data) => app(AdtService::class)->transferOut(
                $encounter,
                enum_from(AdtDestinationType::class, $data['destination_type']),
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
            ->visible(fn () => self::isDischargeVisible($encounter))
            ->modalHeading(__('Discharge Patient'))
            ->modalDescription(__('Discharge the patient from this encounter. This will finalize their stay, free up the assigned bed, and generate any pending invoices for settlement.'))
            ->slideOver()
            ->schema(fn (): array => self::dischargeSchema($encounter))
            ->action(function (array $data) use ($encounter): void {
                try {
                    self::performDischarge($encounter, $data);
                } catch (DischargeBlockedException $e) {
                    Notification::make()
                        ->title(__('Discharge blocked'))
                        ->body($e->readiness->summary())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * The discharge form, shared by every surface that can discharge.
     *
     * @return array<int, mixed>
     */
    public static function dischargeSchema(Model $encounter): array
    {
        $readiness = $encounter->isInpatient()
            ? app(DischargeReadinessService::class)->assess($encounter)->toArray()
            : null;
        // The checklist is always shown for inpatients; it only gates the form when enforcement is on.
        $hasBlocking = $readiness !== null && ! $readiness['ready'] && config('clinical.discharge.enforce_readiness', true);

        return array_values(array_filter([
            $readiness !== null
                ? ViewField::make('readiness')
                    ->hiddenLabel()
                    ->view('clinical::partials.discharge-readiness', ['readiness' => $readiness])
                    ->dehydrated(false)
                : null,
            Select::make('discharge_disposition')
                ->label('Disposition')
                ->options(DischargeDisposition::class)
                ->default('completed')
                ->required()
                ->live(),
            TextInput::make('transfer_destination')
                ->label('Transfer Destination')
                ->visible(fn (callable $get) => $get('discharge_disposition') === 'transferred'),
            $hasBlocking
                ? Textarea::make('override_reason')
                    ->label('Override reason')
                    ->helperText(__('Required to discharge while items above are unresolved (not needed for transfers out or deaths).'))
                    ->rows(2)
                    ->required(fn (callable $get) => ! in_array($get('discharge_disposition'), ['transferred', 'deceased'], true))
                : null,
            $encounter->isInpatient()
                ? DateTimePicker::make('follow_up_at')
                    ->label('Follow-up appointment')
                    ->helperText(ModuleAvailability::appointmentEnabled()
                        ? __('A follow-up appointment is booked automatically when set.')
                        : __('Recorded on the discharge summary.'))
                    ->seconds(false)
                    ->minDate(now())
                : null,
            $encounter->isInpatient()
                ? Select::make('follow_up_provider_id')
                    ->label('Follow-up with')
                    ->options(fn (): array => self::followUpProviderOptions($encounter))
                    ->searchable()
                    ->visible(fn (callable $get) => filled($get('follow_up_at')))
                : null,
            Textarea::make('notes')
                ->label('Discharge notes')
                ->rows(2),
        ]));
    }

    /**
     * Staff who can be booked for the follow-up; empty when the Staff module is absent.
     *
     * @return array<string, string>
     */
    public static function followUpProviderOptions(Model $encounter): array
    {
        return OptionalClass::when(
            'Modules\\Staff\\Models\\Staff',
            fn (string $staff): array => $staff::query()
                ->when($encounter->branch_id, fn ($q) => $q->where('branch_id', $encounter->branch_id))
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(fn ($member): array => [$member->id => trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: ($member->name ?? $member->id)])
                ->all(),
            'Staff',
        ) ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function performDischarge(Model $encounter, array $data): Model
    {
        return app(AdtService::class)->discharge(
            $encounter,
            enum_from(DischargeDisposition::class, $data['discharge_disposition'] ?? DischargeDisposition::COMPLETED->value),
            $data['transfer_destination'] ?? null,
            notes: $data['notes'] ?? $data['discharge_notes'] ?? null,
            overrideReason: $data['override_reason'] ?? null,
            followUpAt: filled($data['follow_up_at'] ?? null) ? Carbon::parse($data['follow_up_at']) : null,
            followUpProviderId: filled($data['follow_up_provider_id'] ?? null) ? (string) $data['follow_up_provider_id'] : null,
        );
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
            ->action(fn (array $data) => app(AdtService::class)->cancel($encounter, $data['reason']));
    }

    /**
     * @deprecated Use admit() — admissions now go through a ward request.
     */
    public static function assignToWard(
        Model $encounter,
        BedAssignmentService $bedAssignmentService,
        ?Closure $onSuccess = null,
    ): Action {
        return self::admit($encounter)
            ->name('assign_to_ward')
            ->action(function (array $data) use ($encounter, $onSuccess): void {
                app(AdtService::class)->requestAdmission(
                    $encounter,
                    $data['ward_id'],
                    bedId: $data['bed_id'] ?? null,
                    notes: $data['notes'] ?? null,
                );

                if ($onSuccess) {
                    ($onSuccess)($data);
                }
            });
    }
}
