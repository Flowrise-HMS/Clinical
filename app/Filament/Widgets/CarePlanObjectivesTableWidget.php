<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlanObjectivesTable;
use Modules\Clinical\Models\CarePlanObjective;

class CarePlanObjectivesTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $carePlanId = null;

    protected function getTableQuery(): Builder
    {
        return CarePlanObjective::query()
            ->when(
                filled($this->carePlanId),
                fn (Builder $query): Builder => $query->whereHas(
                    'diagnosis',
                    fn (Builder $diagnosisQuery): Builder => $diagnosisQuery->where('care_plan_id', $this->carePlanId),
                ),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['diagnosis', 'evaluations' => fn ($query) => $query->latest('evaluated_at')])
            ->latest('created_at');
    }

    protected function getTableColumns(): array
    {
        return CarePlanObjectivesTable::columns();
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No objectives recorded yet';
    }
}
