<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlansTable;
use Modules\Clinical\Models\CarePlan;

class CarePlanPreviousTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Previous care plans';

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $patientId = null;

    protected function getTableQuery(): Builder
    {
        return CarePlan::query()
            ->when(
                filled($this->patientId),
                fn (Builder $query): Builder => $query->where('patient_id', $this->patientId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->whereIn('status', [
                CarePlanStatus::ON_HOLD,
                CarePlanStatus::COMPLETED,
                CarePlanStatus::REVOKED,
                CarePlanStatus::ENTERED_IN_ERROR,
            ])
            ->with(['encounter', 'custodian'])
            ->latest();
    }

    protected function getTableColumns(): array
    {
        return CarePlansTable::previousWorkspaceColumns();
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->button()
                ->color('gray')
                ->action(fn (CarePlan $record) => $this->dispatch('preview-care-plan', carePlanId: $record->id)),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No previous care plans';
    }
}
