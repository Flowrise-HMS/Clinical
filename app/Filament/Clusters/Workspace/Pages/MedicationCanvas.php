<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\CanvasLayoutService;
use Modules\Clinical\Classes\Services\ClinicalCanvasTreeBuilder;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationCanvasService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Models\CanvasLayout;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Core\Classes\Support\PageWidgetsRegistry;

/**
 * Interactive canvas of the active encounter's in-facility prescriptions: the
 * encounter at the root, one branch per medication, and its dose slots (with
 * administration history) beneath, plus a record-dose action on the next due slot.
 */
class MedicationCanvas extends Page
{
    use HasPageShield;
    use HasPatientContext;

    protected static ?string $title = 'Medication Canvas';

    protected static ?string $navigationLabel = 'Medication Canvas';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::Pill;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'patient/{patient}/medications';

    protected string $view = 'clinical::clinical.workspace.pages.medication-canvas';

    /**
     * Root node (the active encounter) with one medication child per
     * in-facility prescription and its dose slots beneath.
     *
     * @var array<string, mixed>|null
     */
    public ?array $canvasTree = null;

    /**
     * @var array<string, mixed>
     */
    public array $canvasMeta = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $savedLayout = null;

    public ?string $activeEncounterId = null;

    /**
     * One of '', 'no_patient', 'no_active_encounter', 'pharmacy_disabled', 'no_in_facility_meds'.
     */
    public string $emptyReason = '';

    public function boot(): void
    {
        $this->patientId = request()->route('patient') ?? $this->patientId;
        $this->bootHasPatientContext();
        $this->loadCanvasData();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    /**
     * Target of `wire:poll`; `boot()` already reloads on every request.
     */
    public function refreshCanvas(): void
    {
        $this->loadCanvasData();
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    public function saveLayout(array $layout): void
    {
        $user = Auth::user();

        if (! $user || ! $this->currentPatient || ! $this->activeEncounterId) {
            return;
        }

        try {
            $saved = app(CanvasLayoutService::class)->save(
                $user,
                CanvasLayout::KEY_MEDICATIONS,
                $this->currentPatient->id,
                $this->activeEncounterId,
                $layout,
            );

            $this->savedLayout = $saved->layout;
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Canvas layout not saved')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();
        }
    }

    public function resetLayout(): void
    {
        $user = Auth::user();

        if (! $user || ! $this->currentPatient || ! $this->activeEncounterId) {
            return;
        }

        app(CanvasLayoutService::class)->reset(
            $user,
            CanvasLayout::KEY_MEDICATIONS,
            $this->currentPatient->id,
            $this->activeEncounterId,
        );

        $this->savedLayout = null;
    }

    public function recordDoseAction(): Action
    {
        return Action::make('recordDose')
            ->label('Record dose')
            ->icon('heroicon-m-beaker')
            ->color('success')
            ->disabled(fn (array $arguments): bool => $this->resolveCanvasItem($arguments) === null)
            ->modalHeading(fn (array $arguments): string => 'Record dose — '.($this->resolveCanvasItem($arguments)?->service?->name ?? 'Medication'))
            ->modalSubmitActionLabel('Save administration')
            ->schema(function (array $arguments): array {
                $item = $this->resolveCanvasItem($arguments);

                if ($item === null) {
                    return [];
                }

                return [
                    ...MarRecordDoseFormSchema::forSingleItem($item),
                    Textarea::make('notes')->label('Notes')->rows(2),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $item = $this->resolveCanvasItem($arguments);

                if ($item === null) {
                    Notification::make()->title('This dose can no longer be recorded')->warning()->send();

                    return;
                }

                try {
                    app(MedicationAdministrationService::class)->administer($item, $data, $data['notes'] ?? null, Auth::user());
                    Notification::make()->title('Dose recorded')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Could not record dose')->body($e->getMessage())->danger()->persistent()->send();
                }

                $this->loadCanvasData();
            });
    }

    protected function getHeaderActions(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $actions = PatientActions::make()
            ->forPatient($this->currentPatient)
            ->withEncounter($this->activeEncounterId);

        return [
            $actions->clinicalWorkspaceAction(),
            $actions->timelineAction(),
            $actions->medicationAdminAction(),
            $actions->patientActionGroups(),
            ...app(PageHeaderActionsRegistry::class)->for(static::class, $this),
        ];
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        return [
            ...app(PageWidgetsRegistry::class)->for(static::class, 'footer', $this),
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return $this->currentPatient
            ? 'Medications - '.$this->currentPatient->full_name."({$this->currentPatient->mrn})"
            : 'Medication Canvas';
    }

    protected function loadCanvasData(): void
    {
        $this->canvasTree = null;
        $this->canvasMeta = [];
        $this->savedLayout = null;
        $this->activeEncounterId = null;

        if (! $this->currentPatient) {
            $this->emptyReason = 'no_patient';

            return;
        }

        $service = app(MedicationCanvasService::class);

        if (! $service->isAvailable()) {
            $this->emptyReason = 'pharmacy_disabled';

            return;
        }

        $encounter = $this->activeEncounter();

        if ($encounter === null) {
            $this->emptyReason = 'no_active_encounter';

            return;
        }

        $this->activeEncounterId = $encounter->id;
        $user = Auth::user();

        ['root' => $this->canvasTree, 'meta' => $this->canvasMeta] = app(ClinicalCanvasTreeBuilder::class)
            ->buildEncounterMedicationTree($encounter, $user);

        if (($this->canvasTree['children'] ?? []) === []) {
            $this->emptyReason = 'no_in_facility_meds';

            return;
        }

        $this->emptyReason = '';
        $this->savedLayout = $user
            ? app(CanvasLayoutService::class)->get($user, CanvasLayout::KEY_MEDICATIONS, $this->currentPatient->id, $encounter->id)
            : null;
    }

    /**
     * Scope is strictly the *active* encounter; HasPatientContext's fallback to
     * the latest (possibly finished) encounter must not leak in here.
     */
    protected function activeEncounter(): ?Encounter
    {
        if (! $this->currentPatient) {
            return null;
        }

        if ($this->currentPatient->relationLoaded('activeEncounter')) {
            return $this->currentPatient->activeEncounter;
        }

        return $this->currentPatient->activeEncounter()->first();
    }

    /**
     * Only an item on this patient's active encounter that the user may
     * administer is ever handed to the record-dose form.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function resolveCanvasItem(array $arguments): ?RequestItem
    {
        $requestItemId = $arguments['requestItemId'] ?? null;

        if (blank($requestItemId) || ! $this->currentPatient || ! $this->activeEncounterId) {
            return null;
        }

        $item = RequestItem::query()
            ->with(['service.category', 'serviceRequest.encounter', 'prescriptionDetail'])
            ->find($requestItemId);

        if ($item === null) {
            return null;
        }

        $serviceRequest = $item->serviceRequest;

        if ($serviceRequest?->patient_id !== $this->currentPatient->id || $serviceRequest?->encounter_id !== $this->activeEncounterId) {
            return null;
        }

        if ($item->hasActiveFinancialHold() || ! app(MedicationFulfillmentPolicy::class)->canRecordMar($item, Auth::user())) {
            return null;
        }

        return $item;
    }
}
