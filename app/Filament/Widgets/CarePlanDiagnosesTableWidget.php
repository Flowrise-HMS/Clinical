<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
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
        return [
            TextColumn::make('diagnosis')
                ->label('Diagnosis')
                ->state(fn (CarePlanDiagnosis $record): string => $record->displayLabel())
                ->wrap()
                ->description(fn (CarePlanDiagnosis $record): ?string => $record->composed_statement !== $record->displayLabel()
                    ? $record->composed_statement
                    : null),
            TextColumn::make('problem.label')->label('Problem')->toggleable(),
            TextColumn::make('orders_count')
                ->label('Orders')
                ->state(fn (CarePlanDiagnosis $record): int => $record->orders->count())
                ->badge()
                ->color(fn (CarePlanDiagnosis $record): string => $record->orders->count() < 3 ? 'warning' : 'success'),
            TextColumn::make('recorded_at')->label('Recorded')->dateTime()->sortable(),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No nursing diagnoses yet';
    }
}
