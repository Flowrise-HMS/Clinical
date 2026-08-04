<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Tables;

use Filament\Tables\Columns\TextColumn;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Filament\Support\ClientIdentityColumn;

class RequestItemsTable
{
    /**
     * Columns for request-item / order line listings (patient orders widget, etc.).
     *
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('#')->rowIndex(),
            TextColumn::make('serviceRequest.request_number')
                ->label('Request #')
                ->searchable(),
            TextColumn::make('service.name')
                ->label('Service')
                ->searchable()
                ->description(fn ($record) => $record->serviceVariant?->name),
            TextColumn::make('quantity')->label('Qty'),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'Pending')
                ->color(fn ($state) => match ($state?->value) {
                    'completed' => 'success',
                    'cancelled' => 'gray',
                    'in_progress' => 'primary',
                    default => 'warning',
                }),
            TextColumn::make('fulfilledBy.name')->label('Fulfilled By'),
            TextColumn::make('created_at')->label('Date')->dateTime()->sortable(),
        ];
    }

    /**
     * Columns for the pending fulfillments / MAR queue widget.
     *
     * @return array<int, TextColumn>
     */
    public static function pendingFulfillmentColumns(): array
    {
        $policy = app(MedicationFulfillmentPolicy::class);

        return [
            TextColumn::make('#')->rowIndex(),
            ClientIdentityColumn::make(
                resolve: fn (RequestItem $record) => $record->serviceRequest?->clientIdentity(),
            ),
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
                    $given = $policy->givenDosesCount($record);
                    $unit = $detail->doseUnit?->label ?? '';

                    return max(0, $detail->total_administrations - $given).'/'.$detail->total_administrations.' '.$unit;
                })
                ->visible(fn ($record): bool => $record?->prescriptionDetail !== null),
            TextColumn::make('payment_status')
                ->label('Payment')
                ->badge()
                ->color(fn (RequestItem $record): string => $record->payment_status?->getColor() ?? 'gray')
                ->formatStateUsing(fn (RequestItem $record): string => $record->payment_status?->getLabel() ?? '—'),
            TextColumn::make('financial_hold')
                ->label('Hold')
                ->badge()
                ->color(fn (RequestItem $record): string => $record->hasActiveFinancialHold() ? 'danger' : 'success')
                ->formatStateUsing(fn (RequestItem $record): string => $record->hasActiveFinancialHold() ? 'On hold' : 'Clear'),
        ];
    }
}
