<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
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
        return [
            TextColumn::make('description')
                ->label('Intervention')
                ->wrap()
                ->searchable(),
            TextColumn::make('order.instruction')
                ->label('Order')
                ->limit(40)
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('order.diagnosis.label')
                ->label('Diagnosis')
                ->state(fn (CarePlanIntervention $record): ?string => $record->order?->diagnosis?->displayLabel())
                ->limit(40)
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('performedBy.name')
                ->label('Performed by')
                ->placeholder('—'),
            TextColumn::make('performed_at')
                ->label('Performed')
                ->dateTime()
                ->sortable(),
            TextColumn::make('notes')
                ->limit(40)
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No interventions recorded yet';
    }
}
