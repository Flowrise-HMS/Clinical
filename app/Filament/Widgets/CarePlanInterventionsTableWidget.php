<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlanInterventionsTable;
use Modules\Clinical\Models\CarePlanIntervention;

class CarePlanInterventionsTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $carePlanId = null;

    protected function getTableQuery(): Builder
    {
        return CarePlanIntervention::query()
            ->when(
                filled($this->carePlanId),
                fn (Builder $query): Builder => $query->whereHas(
                    'order.diagnosis',
                    fn (Builder $diagnosisQuery): Builder => $diagnosisQuery->where('care_plan_id', $this->carePlanId),
                ),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['order.diagnosis', 'performedBy'])
            ->latest('performed_at');
    }

    protected function getTableColumns(): array
    {
        return CarePlanInterventionsTable::columns();
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No interventions recorded yet';
    }
}
