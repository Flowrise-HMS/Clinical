<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Classes\Services\WardBoardService;
use Modules\Clinical\Exceptions\DischargeBlockedException;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Policies\EncounterPolicy;
use Modules\Core\Classes\Services\BedStatusService;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Classes\Support\PageWidgetsRegistry;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Models\Location;
use Modules\Core\Support\ModuleAvailability;

/**
 * Per-ward bed map: who is in which bed, how long, what needs attention, and
 * the ADT actions ward staff take from the bedside. Polls every 60 seconds;
 * that is deliberate until Clinical adopts Echo/Reverb.
 */
class WardBoard extends Page
{
    use HasPageShield;

    protected static ?string $title = 'Ward Board';

    protected static ?string $navigationLabel = 'Ward Board';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::BedDouble;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'wards';

    protected string $view = 'clinical::clinical.workspace.pages.ward-board';

    #[Url(as: 'ward', except: '')]
    public ?string $wardId = null;

    public ?string $branchId = null;

    /**
     * @var array<string, string>
     */
    public array $wardOptions = [];

    /**
     * @var array<string, mixed>
     */
    public array $board = [];

    public function mount(): void
    {
        $this->branchId = app(BranchService::class)->getDefaultBranchId();
        $this->wardOptions = $this->branchId
            ? app(WardBoardService::class)->wardsForBranch($this->branchId)->all()
            : [];

        if ($this->wardId === null || ! array_key_exists($this->wardId, $this->wardOptions)) {
            $this->wardId = array_key_first($this->wardOptions);
        }

        $this->refreshBoard();
    }

    public function updatedWardId(): void
    {
        if ($this->wardId !== null && ! array_key_exists($this->wardId, $this->wardOptions)) {
            $this->wardId = array_key_first($this->wardOptions);
        }

        $this->refreshBoard();
    }

    public function refreshBoard(): void
    {
        $ward = $this->ward();

        $this->board = $ward ? app(WardBoardService::class)->buildWard($ward) : [];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return app(PageWidgetsRegistry::class)->for(static::class, 'header', $this);
    }

    protected function getFooterWidgets(): array
    {
        return app(PageWidgetsRegistry::class)->for(static::class, 'footer', $this);
    }

    public function pharmacyEnabled(): bool
    {
        return ModuleAvailability::pharmacyEnabled();
    }

    public function canUpdateEncounters(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('Update Encounter');
    }

    public function canManageBedStatus(): bool
    {
        return Auth::user()?->can('manage_bed_status') ?? false;
    }

    public function canDischarge(): bool
    {
        return Auth::user()?->can('discharge_patient') ?? false;
    }

    // ------------------------------------------------------------------
    // Actions (all take an argument identifying the row they act on)
    // ------------------------------------------------------------------

    public function transferAction(): Action
    {
        return Action::make('transfer')
            ->label('Transfer')
            ->icon('heroicon-m-arrows-right-left')
            ->color('info')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->disabled(fn (array $arguments): bool => $this->encounterFor($arguments) === null)
            ->modalHeading(fn (array $arguments): string => __('Transfer :name', ['name' => $this->encounterFor($arguments)?->patient?->full_name ?? '']))
            ->slideOver()
            ->schema(function (array $arguments): array {
                $encounter = $this->encounterFor($arguments);

                return [
                    Select::make('ward_id')
                        ->label('Ward / Room')
                        ->options(fn () => app(BedAssignmentService::class)->getWardsForBranch($encounter?->branch_id ?? $this->branchId)->all())
                        ->default($this->wardId)
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('bed_id', null)),
                    Select::make('bed_id')
                        ->label('Bed')
                        ->options(fn (callable $get) => $get('ward_id') && $encounter
                            ? app(BedAssignmentService::class)->getAvailableBeds($get('ward_id'), $encounter->id)->all()
                            : [])
                        ->searchable()
                        ->required(),
                    Textarea::make('notes')->label('Notes')->rows(2),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $encounter = $this->encounterFor($arguments);

                if ($encounter === null || ! $this->canUpdateEncounter($encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(AdtService::class)->transferInternal($encounter, $data['bed_id'], notes: $data['notes'] ?? null), __('Patient transferred'));
            });
    }

    public function dischargeAction(): Action
    {
        return Action::make('discharge')
            ->label('Discharge')
            ->icon('heroicon-m-arrow-left-end-on-rectangle')
            ->color('danger')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canDischarge())
            ->disabled(fn (array $arguments): bool => ($encounter = $this->encounterFor($arguments)) === null || ! EncounterActions::isDischargeVisible($encounter))
            ->modalHeading(fn (array $arguments): string => __('Discharge :name', ['name' => $this->encounterFor($arguments)?->patient?->full_name ?? '']))
            ->slideOver()
            ->schema(function (array $arguments): array {
                $encounter = $this->encounterFor($arguments);

                return $encounter ? EncounterActions::dischargeSchema($encounter) : [];
            })
            ->action(function (array $data, array $arguments): void {
                $encounter = $this->encounterFor($arguments);

                if ($encounter === null || ! $this->canDischarge() || ! EncounterActions::isDischargeVisible($encounter)) {
                    Notification::make()->title(__('Discharge is not available for this patient'))->warning()->send();

                    return;
                }

                try {
                    EncounterActions::performDischarge($encounter, $data);
                    Notification::make()->title(__('Patient discharged'))->success()->send();
                } catch (DischargeBlockedException $e) {
                    Notification::make()->title(__('Discharge blocked'))->body($e->readiness->summary())->danger()->persistent()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title(__('Action failed'))->body($e->getMessage())->danger()->persistent()->send();
                }

                $this->refreshBoard();
            });
    }

    public function sendOnPassAction(): Action
    {
        return Action::make('sendOnPass')
            ->label('Pass')
            ->icon('heroicon-m-arrow-right-start-on-rectangle')
            ->color('warning')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->modalHeading(__('Send patient on pass'))
            ->schema([
                Textarea::make('reason')->label('Reason')->rows(2)->required(),
                DateTimePicker::make('expected_return_at')->label('Expected return')->seconds(false),
            ])
            ->action(function (array $data, array $arguments): void {
                $encounter = $this->encounterFor($arguments);

                if ($encounter === null || ! $this->canUpdateEncounter($encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(AdtService::class)->sendOnPass(
                    $encounter,
                    $data['reason'] ?? null,
                    filled($data['expected_return_at'] ?? null) ? Carbon::parse($data['expected_return_at']) : null,
                ), __('Patient sent on pass'));
            });
    }

    public function returnFromPassAction(): Action
    {
        return Action::make('returnFromPass')
            ->label('Returned')
            ->icon('heroicon-m-arrow-left-end-on-rectangle')
            ->color('success')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->requiresConfirmation()
            ->modalHeading(__('Patient returned from pass'))
            ->action(function (array $arguments): void {
                $encounter = $this->encounterFor($arguments);

                if ($encounter === null || ! $this->canUpdateEncounter($encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(AdtService::class)->returnFromPass($encounter), __('Patient back on the ward'));
            });
    }

    public function assignNurseAction(): Action
    {
        return Action::make('assignNurse')
            ->label('Nurse')
            ->icon('heroicon-m-user-plus')
            ->color('gray')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->modalHeading(__('Assign nurse'))
            ->schema([
                Select::make('user_id')
                    ->label('Nurse')
                    ->options(fn (): array => $this->wardStaffOptions())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $encounter = $this->encounterFor($arguments);

                if ($encounter === null || ! $this->canUpdateEncounter($encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(EncounterService::class)->assignNurse($encounter, (int) $data['user_id'], Auth::id()), __('Nurse assigned'));
            });
    }

    public function setExpectedDischargeAction(): Action
    {
        return Action::make('setExpectedDischarge')
            ->label('Expected discharge')
            ->icon('heroicon-m-calendar-days')
            ->color('gray')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->modalHeading(__('Expected discharge'))
            ->disabled(fn (array $arguments): bool => $this->encounterFor($arguments) === null)
            ->schema(function (array $arguments): array {
                $encounter = $this->encounterFor($arguments);

                return [
                    DateTimePicker::make('expected_discharge_at')
                        ->label('Expected discharge')
                        ->seconds(false)
                        ->default($encounter?->expected_discharge_at),
                    Textarea::make('reason')->label('Reason for change')->rows(2),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $encounter = $this->encounterFor($arguments);

                if ($encounter === null || ! $this->canUpdateEncounter($encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(AdtService::class)->setExpectedDischarge(
                    $encounter,
                    filled($data['expected_discharge_at'] ?? null) ? Carbon::parse($data['expected_discharge_at']) : null,
                    Auth::id(),
                    $data['reason'] ?? null,
                ), __('Expected discharge updated'));
            });
    }

    public function setBedStatusAction(): Action
    {
        return Action::make('setBedStatus')
            ->label('Status')
            ->icon('heroicon-m-adjustments-horizontal')
            ->color('gray')
            ->link()
            ->size('xs')
            ->visible(fn (): bool => $this->canManageBedStatus())
            ->disabled(fn (array $arguments): bool => $this->bedFor($arguments) === null)
            ->modalHeading(fn (array $arguments): string => __('Bed :name', ['name' => $this->bedFor($arguments)?->name ?? '']))
            ->schema(function (array $arguments): array {
                $bed = $this->bedFor($arguments);
                $options = [];

                foreach ($bed?->bedStatus()->manualTransitions() ?? [] as $status) {
                    $options[$status->value] = (string) $status->getLabel();
                }

                return [
                    Select::make('status')
                        ->label('New status')
                        ->options($options)
                        ->required(),
                    Textarea::make('reason')->label('Reason')->rows(2),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $bed = $this->bedFor($arguments);

                if ($bed === null || ! $this->canManageBedStatus()) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(BedStatusService::class)->transition($bed, enum_from(BedStatus::class, $data['status']), $data['reason'] ?? null, null, Auth::id()), __('Bed status updated'));
            });
    }

    public function acceptIncomingAction(): Action
    {
        return Action::make('acceptIncoming')
            ->label('Accept')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->button()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->modalHeading(__('Accept admission'))
            ->disabled(fn (array $arguments): bool => $this->requestFor($arguments)?->encounter === null)
            ->modalDescription(fn (array $arguments): string => ($request = $this->requestFor($arguments)) && $request->encounter ? EncounterActions::pendingRequestSummary($request->encounter) : '')
            ->slideOver()
            ->schema(function (array $arguments): array {
                $request = $this->requestFor($arguments);

                return $request?->encounter ? EncounterActions::acceptAdmissionSchema($request->encounter) : [];
            })
            ->action(function (array $data, array $arguments): void {
                $request = $this->requestFor($arguments);

                if ($request === null || ! $request->encounter || ! $this->canUpdateEncounter($request->encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(AdtService::class)->acceptAdmission($request, $data['bed_id'], notes: $data['notes'] ?? null), __('Admission accepted — patient admitted to bed'));
            });
    }

    public function rejectIncomingAction(): Action
    {
        return Action::make('rejectIncoming')
            ->label('Reject')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->button()
            ->size('xs')
            ->visible(fn (): bool => $this->canUpdateEncounters())
            ->modalHeading(__('Reject admission'))
            ->schema(EncounterActions::rejectAdmissionSchema())
            ->action(function (array $data, array $arguments): void {
                $request = $this->requestFor($arguments);

                if ($request === null || ! $request->encounter || ! $this->canUpdateEncounter($request->encounter)) {
                    Notification::make()->title(__('Not authorized'))->danger()->send();

                    return;
                }

                $this->guarded(fn () => app(AdtService::class)->rejectAdmission($request, $data['reason']), __('Admission rejected'));
            });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function ward(): ?Location
    {
        if ($this->wardId === null || $this->branchId === null) {
            return null;
        }

        return Location::withoutGlobalScope('branch')
            ->whereKey($this->wardId)
            ->where('branch_id', $this->branchId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function encounterFor(array $arguments): ?Encounter
    {
        $id = $arguments['encounterId'] ?? null;

        if (blank($id) || $this->branchId === null) {
            return null;
        }

        return Encounter::query()
            ->whereKey($id)
            ->where('branch_id', $this->branchId)
            ->with('patient')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function bedFor(array $arguments): ?Location
    {
        $id = $arguments['bedId'] ?? null;

        if (blank($id) || $this->wardId === null) {
            return null;
        }

        return Location::withoutGlobalScope('branch')
            ->whereKey($id)
            ->where('parent_id', $this->wardId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function requestFor(array $arguments): ?AdmissionRequest
    {
        $id = $arguments['requestId'] ?? null;

        if (blank($id) || $this->wardId === null) {
            return null;
        }

        return AdmissionRequest::query()
            ->pending()
            ->whereKey($id)
            ->where('requested_ward_id', $this->wardId)
            ->with('encounter')
            ->first();
    }

    protected function canUpdateEncounter(Encounter $encounter): bool
    {
        $user = Auth::user();

        return $user !== null && app(EncounterPolicy::class)->update($user, $encounter);
    }

    /**
     * @return array<int, string>
     */
    protected function wardStaffOptions(): array
    {
        $roles = (array) config('clinical.wards.notify_roles', ['nurse']);

        return User::query()
            ->where('branch_id', $this->branchId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function guarded(callable $callback, string $successTitle): void
    {
        try {
            $callback();
            Notification::make()->title($successTitle)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title(__('Action failed'))->body($e->getMessage())->danger()->persistent()->send();
        }

        $this->refreshBoard();
    }
}
