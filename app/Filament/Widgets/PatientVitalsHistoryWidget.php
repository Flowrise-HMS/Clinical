<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Tables\VitalSignsTable;
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
            ->when(
                filled($this->patientId),
                fn (Builder $query): Builder => $query->where('patient_id', $this->patientId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->when(
                filled($this->encounterId),
                fn (Builder $query): Builder => $query->where('encounter_id', $this->encounterId),
            )
            ->with(['recordedBy'])
            ->orderByDesc('recorded_at');
    }

    protected function getTableColumns(): array
    {
        return VitalSignsTable::columns();
    }

    protected function getTableActions(): array
    {
        return VitalSignsTable::recordActions(includeActivities: false);
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
