<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Tables\RequestItemsTable;
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
        return RequestItemsTable::columns();
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
