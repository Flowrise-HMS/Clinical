<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Models\CarePlan;

class CarePlanRecentTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Recent care plans';

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
                CarePlanStatus::DRAFT,
                CarePlanStatus::ACTIVE,
                CarePlanStatus::ON_HOLD,
            ])
            ->with(['encounter', 'custodian'])
            ->latest('updated_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('category')
                ->badge()
                ->sortable(),
            TextColumn::make('encounter.encounter_number')
                ->label('Encounter')
                ->placeholder('—'),
            TextColumn::make('custodian.name')
                ->label('Custodian')
                ->placeholder('—'),
            TextColumn::make('updated_at')
                ->label('Updated')
                ->date()
                ->placeholder('—')
                ->sortable(),
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
            Action::make('open')
                ->label('Open')
                ->button()
                ->color('gray')
                ->visible(fn (CarePlan $record): bool => ! $record->status->canActivate())
                ->action(fn (CarePlan $record) => $this->dispatch('select-care-plan', carePlanId: $record->id)),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No recent care plans';
    }
}
