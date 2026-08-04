<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables\CarePlansTable;
use Modules\Clinical\Models\CarePlan;

class CarePlanWardTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Recent care plans';

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $branchId = Auth::user()?->branch_id;

        return CarePlan::query()
            ->when(
                filled($branchId),
                fn (Builder $query): Builder => $query->where('branch_id', $branchId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->whereIn('status', [
                CarePlanStatus::DRAFT,
                CarePlanStatus::ACTIVE,
                CarePlanStatus::ON_HOLD,
            ])
            ->with(['patient', 'encounter', 'custodian'])
            ->latest('updated_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('patient.full_name')
                ->label('Patient')
                ->searchable()
                ->placeholder('Unknown patient')
                ->action(
                    Action::make('openPatient')
                        ->action(fn (CarePlan $record) => $this->dispatch('select-patient', patientId: $record->patient_id))
                ),
            ...CarePlansTable::wardWorkspaceColumns(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('resume')
                ->label('Resume')
                ->button()
                ->color('primary')
                ->visible(fn (CarePlan $record): bool => $record->status->canActivate())
                ->action(fn (CarePlan $record) => $this->dispatch('resume-care-plan', carePlanId: $record->id)),
            Action::make('preview')
                ->label('Preview')
                ->button()
                ->color('gray')
                ->visible(fn (CarePlan $record): bool => ! $record->status->canActivate())
                ->action(fn (CarePlan $record) => $this->dispatch('preview-care-plan', carePlanId: $record->id)),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No recent care plans in this branch';
    }
}
