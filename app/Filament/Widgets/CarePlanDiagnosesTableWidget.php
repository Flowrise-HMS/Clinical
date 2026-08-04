<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlanDiagnosesTable;
use Modules\Clinical\Models\CarePlanDiagnosis;

class CarePlanDiagnosesTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $carePlanId = null;

    protected function getTableQuery(): Builder
    {
        return CarePlanDiagnosis::query()
            ->when(
                $this->carePlanId,
                fn (Builder $query): Builder => $query->where('care_plan_id', $this->carePlanId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['catalogue', 'orders', 'problem'])
            ->latest('recorded_at');
    }

    protected function getTableColumns(): array
    {
        return CarePlanDiagnosesTable::columns();
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No nursing diagnoses yet';
    }
}
