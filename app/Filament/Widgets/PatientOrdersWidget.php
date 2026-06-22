<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Models\RequestItem;

class PatientOrdersWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Recent Orders';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'clinical::filament.widgets.collapsible-table-widget';

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    protected function getTableQuery(): Builder
    {
        return RequestItem::query()
            ->whereHas('serviceRequest', fn ($q) => $q
                ->where('patient_id', $this->patientId)
                ->when($this->encounterId, fn ($q) => $q->where('encounter_id', $this->encounterId))
            )
            ->with(['serviceRequest', 'service', 'serviceVariant', 'fulfilledBy'])
            ->latest()
            ->limit(20);
    }

    protected function getTableColumns(): array
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
            BadgeColumn::make('status')
                ->label('Status')
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

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No orders';
    }

    protected function getTablePollingInterval(): ?string
    {
        return null;
    }
}
