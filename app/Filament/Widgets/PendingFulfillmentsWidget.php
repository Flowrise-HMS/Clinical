<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Services\BranchService;
use Modules\Pharmacy\Classes\Services\DispenseService;

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
            ->with([
                'serviceRequest.patient',
                'serviceRequest.orderedBy',
                'serviceRequest.encounter',
                'service.category',
                'prescriptionDetail.doseUnit',
                'medicationAdministrations' => fn ($q) => $q->latest(),
            ])
            ->latest();
    }

    protected function getTableColumns(): array
    {
        $policy = app(MedicationFulfillmentPolicy::class);

        return [
            TextColumn::make('#')->rowIndex(),
            TextColumn::make('serviceRequest.patient.display_name')
                ->label('Patient')
                ->sortable(),
            TextColumn::make('service.name')
                ->label('Service')
                ->searchable()
                ->sortable(),
            TextColumn::make('prescriptionDetail.administration_context')
                ->label('Context')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '—'),
            TextColumn::make('prescriptionDetail.next_dose_at')
                ->label('Next due')
                ->dateTime('M j H:i')
                ->placeholder('—'),
            TextColumn::make('serviceRequest.orderedBy.name')
                ->label('Ordered By')
                ->sortable(),
            TextColumn::make('serviceRequest.created_at')
                ->label('Ordered At')
                ->since()
                ->sortable(),
            TextColumn::make('status')
                ->colors([
                    'warning' => 'pending',
                    'primary' => 'in_progress',
                    'success' => 'completed',
                    'gray' => 'cancelled',
                ])->badge(),
            TextColumn::make('remaining')
                ->label('Doses left')
                ->getStateUsing(function (RequestItem $record) use ($policy) {
                    $detail = $record->prescriptionDetail;
                    if (! $detail || ! $detail->total_administrations) {
                        return null;
                    }
                    $given = $policy->countGivenDoses($record);
                    $unit = $detail->doseUnit?->label ?? '';

                    return max(0, $detail->total_administrations - $given).'/'.$detail->total_administrations.' '.$unit;
                })
                ->visible(fn ($record): bool => $record?->prescriptionDetail !== null),
            TextColumn::make('payment_status')
                ->label('Payment')
                ->badge()
                ->color(fn (RequestItem $record): string => $record->payment_status?->getColor() ?? 'gray')
                ->formatStateUsing(fn (RequestItem $record): string => $record->payment_status?->getLabel() ?? '—'),
        ];
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
                if (! $record->prescriptionDetail) {
                    return false;
                }

                if ($record->prescriptionDetail->isTakeHome()) {
                    return $policy->canDispense($record);
                }

                return $policy->requiresMar($record->prescriptionDetail)
                    && Auth::user()?->hasAnyRole(['pharmacist', 'pharmacy_technician']);
            })
            ->modalHeading(fn (RequestItem $record): string => 'Dispense — '.($record->service?->name ?? 'Medication'))
            ->modalSubmitActionLabel('Dispense')
            ->schema(fn (RequestItem $record): array => MarRecordDoseFormSchema::dispenseFields($record))
            ->action(function (array $data, RequestItem $record): void {
                try {
                    app(DispenseService::class)->dispense($record, $data, Auth::user());
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
                if ($record->isTerminal()) {
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

    protected function getTablePollingInterval(): ?string
    {
        return '30s';
    }
}
