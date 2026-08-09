<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Classes\Support\RequestItemTableEnricher;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Tables\RequestItemsTable;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;

class PendingFulfillmentsWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected int $sorting = 3;

    protected int|string|array $columnSpan = 'full';

    public ?string $patientId = null;

    protected function getTableQuery(): Builder
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();
        $user = Auth::user();

        return RequestItem::query()
            ->when($this->patientId, fn ($q) => $q->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->patientId)))
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('serviceRequest', fn (Builder $q) => $q->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId)))
            ->where(fn (Builder $q) => $q->whereDoesntHave('service.roles')
                ->orWhereHas('service.roles', fn (Builder $q) => $q->whereIn('name', $user->getRoleNames()->toArray())))
            ->withFulfillmentAggregates()
            ->with([
                'serviceRequest.patient',
                'serviceRequest.orderedBy',
                'serviceRequest.encounter',
                'service.category',
                'prescriptionDetail.doseUnit',
                'invoiceLine',
            ])
            ->latest();
    }

    protected function getTableColumns(): array
    {
        return RequestItemsTable::pendingFulfillmentColumns();
    }

    protected function getTableActions(): array
    {
        return [
            $this->recordDoseAction(),
            $this->dispenseAction(),
            $this->genericFulfillAction(),
        ];
    }

    protected function recordDoseAction(): Action
    {
        $policy = app(MedicationFulfillmentPolicy::class);

        return Action::make('record_dose')
            ->label('Record dose')
            ->icon('heroicon-m-beaker')
            ->color('success')
            ->button()
            ->visible(fn (RequestItem $record): bool => $record->prescriptionDetail?->isInFacility()
                && ! $record->hasActiveFinancialHold()
                && $policy->canRecordMar($record))
            ->modalHeading(fn (RequestItem $record): string => 'Record dose — '.($record->service?->name ?? 'Medication'))
            ->modalSubmitActionLabel('Save administration')
            ->schema(fn (RequestItem $record): array => [
                ...MarRecordDoseFormSchema::forSingleItem($record),
                Textarea::make('notes')->label('Notes')->rows(2),
            ])
            ->action(function (array $data, RequestItem $record): void {
                try {
                    app(MedicationAdministrationService::class)->administer($record, $data, $data['notes'] ?? null);
                    Notification::make()->title('Dose recorded')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Could not record dose')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    protected function dispenseAction(): Action
    {
        $policy = app(MedicationFulfillmentPolicy::class);

        return Action::make('dispense')
            ->label('Dispense')
            ->icon('heroicon-m-shopping-bag')
            ->color('info')
            ->button()
            ->visible(function (RequestItem $record) use ($policy): bool {
                if (! ModuleAvailability::pharmacyEnabled()) {
                    return false;
                }

                $user = Auth::user();

                if ($user === null || ! $policy->isPharmacyStaff($user)) {
                    return false;
                }

                if ($record->hasActiveFinancialHold() || $record->prescriptionDetail === null) {
                    return false;
                }

                return $policy->canDispense($record, $user);
            })
            ->modalHeading(fn (RequestItem $record): string => 'Dispense — '.($record->service?->name ?? 'Medication'))
            ->modalSubmitActionLabel('Dispense')
            ->schema(fn (RequestItem $record): array => MarRecordDoseFormSchema::dispenseFields($record))
            ->action(function (array $data, RequestItem $record): void {
                try {
                    $dispenseService = OptionalClass::resolve(
                        'Modules\\Pharmacy\\Classes\\Services\\DispenseService',
                        'Pharmacy',
                    );

                    if ($dispenseService === null) {
                        throw new \RuntimeException('Pharmacy dispense is not available.');
                    }

                    app($dispenseService)->dispense($record, $data, Auth::user());
                    Notification::make()->title('Dispensed successfully')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Dispense failed')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    protected function genericFulfillAction(): Action
    {
        return Action::make('fulfill')
            ->label(fn (RequestItem $record): string => match (app(FulfillmentService::class)->getType($record)) {
                'diagnostic' => 'Record Results',
                default => 'Fulfill',
            })
            ->icon('heroicon-m-check-circle')
            ->color('primary')
            ->button()
            ->visible(function (RequestItem $record): bool {
                if ($record->isTerminal() || $record->hasActiveFinancialHold()) {
                    return false;
                }

                $type = app(FulfillmentService::class)->getType($record);

                return $type !== 'medication';
            })
            ->modalHeading(fn (RequestItem $record): string => 'Fulfill — '.($record->service?->name ?? 'Service'))
            ->schema(fn (RequestItem $record): array => app(FulfillmentService::class)->getFormSchema($record))
            ->action(function (array $data, RequestItem $record): void {
                try {
                    app(FulfillmentService::class)->fulfill($record, $data);
                    Notification::make()->title('Fulfilled successfully')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Fulfillment failed')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        $paginator = parent::paginateTableQuery($query);

        RequestItemTableEnricher::applyFinancialHolds(
            Collection::make($paginator->items()),
        );

        return $paginator;
    }

    protected function getTablePollingInterval(): ?string
    {
        return '30s';
    }
}
