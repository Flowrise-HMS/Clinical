<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
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
        return [
            TextColumn::make('item')
                ->label('Item')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? (string) $state),
            IconColumn::make('not_applicable')
                ->label('N/A')
                ->boolean(),
            TextColumn::make('specification')->label('Instruction')->wrap()->limit(60),
            TextColumn::make('notes')->label('Notes')->limit(40)->toggleable(),
            TextColumn::make('specified_at')->label('Updated')->dateTime()->sortable(),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No routine care saved yet';
    }
}
