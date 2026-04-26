<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\VitalSign;
use Modules\Patient\Models\Patient;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;

class PatientProfile extends Page implements HasActions, HasForms, HasInfolists
{
    use HasPatientContext, InteractsWithActions, InteractsWithForms, InteractsWithInfolists;

    protected static ?string $slug = 'patient/{patient}/profile';

    protected static ?string $title = 'Patient Profile';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::User;

    protected static bool $shouldRegisterNavigation = false;

    public Collection|array $allergies = [];

    public Collection|array $vitalsHistory = [];

    public Collection|array $clinicalNotes = [];

    public Collection|array $serviceRequests = [];

    public Collection|array $encounters = [];

    protected string $view = 'clinical::clinical.workspace.patient-profile';

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
        $this->loadPatientData();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    public function patientInfoList(Patient $patient): Schema
    {
        return $this->makeSchema()
            ->record($patient)
            ->components([
                Section::make()
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
                            ->size('lg')
                            ->alignCenter(),

                        Grid::make()
                            ->schema([
                                IconEntry::make('is_deceased')
                                    ->boolean()
                                    ->label('Deceased')
                                    ->tooltip('Is Patient Deceased')
                                    ->state(fn ($record): bool => $record?->isDeceased()),
                                TextEntry::make('deceased_at')
                                    ->visible(fn ($record): bool => $record?->isDeceased()),
                                IconEntry::make('has_allergies')
                                    ->boolean()
                                    ->state(fn ($record): bool => $this->hasAllergies()),
                                TextEntry::make('mrn')->label('MRN'),
                                TextEntry::make('age')->label('Age'),
                                TextEntry::make('gender')
                                    ->label('Gender')
                                    ->badge()
                                    ->formatStateUsing(fn ($state, $record) => $state?->getLabel().($record?->date_of_birth ? '('.$record->date_of_birth?->format('Y-m-d').')' : null) ?? 'N/A'),
                                TextEntry::make('blood_type')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel()),
                                TextEntry::make('marital_status')
                                    ->formatStateUsing(fn ($state) => $state?->getLabel()),
                            ]),

                        Section::make('Location')
                            ->collapsed()
                            ->columns(2)
                            ->visible(fn () => $this->hasActiveEncounter() && $this->currentEncounter?->isInpatient())
                            ->schema([
                                TextEntry::make('ward')->state(fn () => $this->currentEncounter->location?->name ?? 'N/A'),
                                TextEntry::make('bed')->state(fn () => $this->currentEncounter->bed?->name ?? 'N/A'),
                            ]),
                        Section::make('Contact Information')
                            ->collapsed()
                            ->columns(2)
                            ->schema([
                                PhoneEntry::make('phone'),
                                TextEntry::make('email'),
                                TextEntry::make('country')
                                    ->formatStateUsing(fn ($record) => isset($record->address['country']) ? $record->address['country'] : 'N/A')
                                    ->placeholder('-'),
                                TextEntry::make('region')
                                    ->formatStateUsing(fn ($record) => isset($record->address['region']) ? $record->address['region'] : 'N/A')
                                    ->placeholder('-'),
                                TextEntry::make('district')
                                    ->formatStateUsing(fn ($record) => isset($record->address['district']) ? $record->address['district'] : 'N/A')
                                    ->placeholder('-'),
                                TextEntry::make('city')
                                    ->formatStateUsing(fn ($record) => isset($record->address['city']) ? $record->address['city'] : 'N/A')
                                    ->placeholder('-'),
                                TextEntry::make('street')
                                    ->formatStateUsing(fn ($record) => isset($record->address['street']) ? $record->address['street'] : 'N/A')
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->extraAttributes(['class' => 'px-6 pb-8 text-center']),
            ]);
    }

    protected function loadPatientData(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        $this->allergies = Allergy::query()
            ->where('patient_id', $this->currentPatient->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $this->vitalsHistory = VitalSign::query()
            ->where('patient_id', $this->currentPatient->id)
            ->when($this->currentEncounter?->id, fn ($q) => $q->where('encounter_id', $this->currentEncounter->id))
            ->orderBy('recorded_at', 'desc')
            ->limit(50)
            ->get();

        $this->clinicalNotes = ClinicalNote::query()
            ->where('patient_id', $this->currentPatient->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $this->serviceRequests = ServiceRequest::query()
            ->where('patient_id', $this->currentPatient->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $this->encounters = Encounter::query()
            ->where('patient_id', $this->currentPatient->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        return [
            Action::make('timeline')
                ->button()
                ->color('gray')
                ->url(Timeline::getUrl(['patient' => $this->currentPatient?->id]))
                ->icon('heroicon-m-clock'),
        ];
    }

    public function hasAllergies(): bool
    {
        return $this->allergies->isNotEmpty();
    }

    public function hasActiveEncounter(): bool
    {
        return $this->currentEncounter !== null && $this->currentEncounter->status->isActive();
    }
}
