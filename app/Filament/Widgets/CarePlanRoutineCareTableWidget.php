<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlanRoutineCareTable;
use Modules\Clinical\Models\CarePlanRoutineCare;

class CarePlanRoutineCareTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $carePlanId = null;

    protected function getTableQuery(): Builder
    {
        return CarePlanRoutineCare::query()
            ->when(
                $this->carePlanId,
                fn (Builder $query): Builder => $query->where('care_plan_id', $this->carePlanId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->orderBy('item');
    }

    protected function getTableColumns(): array
    {
        return CarePlanRoutineCareTable::columns();
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No routine care saved yet';
    }
}
