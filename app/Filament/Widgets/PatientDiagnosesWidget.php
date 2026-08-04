<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables\EncounterDiagnosesTable;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Policies\EncounterDiagnosisPolicy;

class PatientDiagnosesWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Diagnoses';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'clinical::filament.widgets.collapsible-table-widget';

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null && app(EncounterDiagnosisPolicy::class)->viewAny($user);
    }

    protected function getTableQuery(): Builder
    {
        return EncounterDiagnosis::query()
            ->when(
                filled($this->patientId),
                fn (Builder $query): Builder => $query->where('patient_id', $this->patientId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->when(
                filled($this->encounterId),
                fn (Builder $query): Builder => $query->where('encounter_id', $this->encounterId),
            )
            ->where('is_active', true)
            ->with(['orderedBy', 'diagnosisCode'])
            ->latest('created_at');
    }

    protected function getTableColumns(): array
    {
        return EncounterDiagnosesTable::columns();
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No diagnoses recorded';
    }

    protected function getTablePollingInterval(): ?string
    {
        return null;
    }
}
