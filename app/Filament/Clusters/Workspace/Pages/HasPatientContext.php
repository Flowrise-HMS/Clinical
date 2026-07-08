<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\TextSize;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Models\Encounter;
use Modules\Patient\Models\Patient;

trait HasPatientContext
{
    use InteractsWithInfolists;

    #[Url]
    public ?string $patientId = null;

    public ?Patient $currentPatient = null;

    public ?Encounter $currentEncounter = null;

    public ?object $latestVitals = null;

    public ?object $nextAppointment = null;

    protected ?ClinicalWorkspaceService $workspaceService = null;

    public function bootHasPatientContext(): void
    {
        $this->workspaceService = app(ClinicalWorkspaceService::class);
        $this->loadPatientContext();
    }

    public function mountHasPatientContext(): void
    {
        $this->workspaceService = app(ClinicalWorkspaceService::class);
    }

    protected function loadPatientContext(): void
    {
        if ($this->patientId) {
            $this->currentPatient = Patient::with([
                'allergies',
                'activeEncounter',
                'latestEncounter',
                'latestVitals',
            ])->find($this->patientId);

            if ($this->currentPatient) {
                $this->workspaceService->setPatient($this->currentPatient);
                $this->currentEncounter = $this->currentPatient->activeEncounter ?? $this->currentPatient->latestEncounter;
                $this->latestVitals = $this->workspaceService->getLatestVitals();
                $this->nextAppointment = $this->workspaceService->getNextAppointmentForPatient($this->currentPatient);
            }
        }
    }

    public function infolist()
    {
        return $this
            ->makeSchema()
            ->model(Patient::class)
            ->record($this->currentPatient)
            ->schema([
                ImageEntry::make('photo')
                    ->imageSize(120)
                    ->hiddenLabel()
                    ->alignCenter()
                    ->circular()
                    ->extraAttributes(['class' => 'mt-6']),
                TextEntry::make('full_name')
                    ->label('Name')
                    ->weight('bold')
                    ->hiddenLabel()
                    ->size(TextSize::Large)
                    ->formatStateUsing(fn ($record) => $record->full_name.'('.$record->mrn.')')
                    ->alignCenter(),

                Grid::make(4)
                    ->schema([
                        TextEntry::make('age')
                            ->label('Age')
                            ->size(TextSize::Small)
                            ->color('gray')
                            ->formatStateUsing(fn ($record) => $record->age.' yrs'),

                        TextEntry::make('gender')
                            ->label('Gender')
                            ->size(TextSize::Small)
                            ->color('gray')
                            ->formatStateUsing(fn ($record) => $record->gender?->getLabel() ?? '-'),

                        TextEntry::make('allergies')
                            ->label('Allergies')
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record) => $record?->allergies?->isNotEmpty())
                            ->formatStateUsing(fn ($record) => $record->allergies->count()),
                        TextEntry::make('bp')
                            ->label('BP')
                            ->formatStateUsing(fn () => $this->latestVitals?->blood_pressure ?? '—'),
                        TextEntry::make('hr')
                            ->label('Heart Rate')
                            ->visible(filled($this->latestVitals?->heart_rate))
                            ->formatStateUsing(fn () => $this->latestVitals->heart_rate.' bpm'),
                        TextEntry::make('spo2')
                            ->label('SpO2')
                            ->visible(filled($this->latestVitals?->spo2))
                            ->formatStateUsing(fn () => $this->latestVitals->spo2.'%'),
                        TextEntry::make('encounter_type')
                            ->label('Type')
                            ->formatStateUsing(fn () => $this->currentEncounter?->type?->getLabel() ?? '-'),

                        TextEntry::make('encounter_status')
                            ->label('Status')
                            ->formatStateUsing(fn () => $this->currentEncounter?->status?->getLabel() ?? '-'),
                        TextEntry::make('next_appointment')
                            ->label('Next Appointment')
                            ->formatStateUsing(fn () => $this->nextAppointment?->start_at?->format('M j, g:i A') ?? '-'),
                    ]),
            ]);
    }
}
