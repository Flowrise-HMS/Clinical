<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Models\VitalSign;

class PatientVitalsHistoryWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Vitals History';

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    protected function getTableQuery(): Builder
    {
        return VitalSign::query()
            ->where('patient_id', $this->patientId)
            ->when($this->encounterId, fn ($q) => $q->where('encounter_id', $this->encounterId))
            ->orderBy('recorded_at', 'desc');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('#')->rowIndex(),
            TextColumn::make('recorded_at')->label('Date/Time')->dateTime()->sortable(),
            TextColumn::make('blood_pressure')
                ->label('BP')
                ->suffix('mmHg')
                ->color(fn ($record) => $record->isAbnormalBloodPressure() ? 'warning' : 'gray'),
            TextColumn::make('heart_rate')->label('HR')->suffix('bpm'),
            TextColumn::make('temperature')->label('Temp'),
            TextColumn::make('spo2')->label('SpO₂'),
            TextColumn::make('respiratory_rate')->label('RR'),
            TextColumn::make('recordedBy.name')->label('By'),
        ];
    }

    protected function getTableRecordActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No vitals recorded';
    }

    protected function getTablePollingInterval(): ?string
    {
        return null;
    }
}
