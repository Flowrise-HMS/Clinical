<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas\VitalSignInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Tables\VitalSignsTable;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\VitalSign;

class Vitals extends Page implements HasTable, HasInfolists
{
    use HasPatientContext, InteractsWithTable, InteractsWithInfolists;

    protected static ?string $title = 'Vitals';

    protected static ?string $navigationLabel = 'Vitals';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static ?string $slug = 'patient/{patient}/vitals';

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::Heart;


    protected string $view = 'clinical::clinical.workspace.pages.vitals';

    protected static bool $shouldRegisterNavigation = false;

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    public function vitalsInfolist()
    {
        return VitalSignInfolist::configure($this->makeSchema())
            ->columns(2)
            ->record($this->latestVitals);
    }

    public function table(Table $table): Table
    {
        return VitalSignsTable::configure($table)
            ->heading('Vitals History')
            ->query(
                VitalSign::where('patient_id', $this->currentPatient->id)
                    ->when($this->currentEncounter?->id, fn ($q) => $q->where('encounter_id', $this->currentEncounter->id))
                    ->orderBy('recorded_at', 'desc')
            )->recordActions([

            ]);
    }

    protected function getHeaderActions(): array
    {
        return app(PatientActions::class)->forPatient($this->currentPatient)->timelineSubQuickActions();
    }


    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return $this->currentPatient
            ? 'Vitals - '.$this->currentPatient->full_name
            : 'Vitals';
    }
}
