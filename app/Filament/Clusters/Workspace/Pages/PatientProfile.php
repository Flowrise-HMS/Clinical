<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
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
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Widgets\CarePlanPreviousTableWidget;
use Modules\Clinical\Filament\Widgets\CarePlanRecentTableWidget;
use Modules\Clinical\Filament\Widgets\PatientDiagnosesWidget;
use Modules\Clinical\Filament\Widgets\PatientNotesWidget;
use Modules\Clinical\Filament\Widgets\PatientOrdersWidget;
use Modules\Clinical\Filament\Widgets\PatientTimelineWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsChartWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsHistoryWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsOverviewWidget;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Clinical\Models\Allergy;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Core\Classes\Support\PageWidgetsRegistry;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;
use Modules\Patient\Models\Patient;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;

class PatientProfile extends Page implements HasActions, HasForms, HasInfolists
{
    use HasPageShield;
    use HasPatientContext, InteractsWithActions, InteractsWithForms, InteractsWithInfolists;

    protected static ?string $slug = 'patient/{patient}/profile';

    protected static ?string $title = 'Patient Profile';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::User;

    protected static bool $shouldRegisterNavigation = false;

    public Collection|array $allergies = [];

    protected string $view = 'clinical::clinical.workspace.patient-profile';

    public function boot(): void
    {
        $this->patientId = request()->route('patient') ?? $this->patientId;
        $this->bootHasPatientContext();
        $this->loadPatientData();
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $patientId = $this->currentPatient->id;

        return [
            // Full patient history on the profile (not encounter-scoped).
            PatientVitalsOverviewWidget::make([
                'patientId' => $patientId,
            ]),
            PatientVitalsChartWidget::make([
                'patientId' => $patientId,
            ]),
            PatientVitalsHistoryWidget::make([
                'patientId' => $patientId,
            ]),
            PatientDiagnosesWidget::make([
                'patientId' => $patientId,
            ]),
            PatientNotesWidget::make([
                'patientId' => $patientId,
            ]),
            PatientOrdersWidget::make([
                'patientId' => $patientId,
            ]),
            PendingFulfillmentsWidget::make([
                'patientId' => $patientId,
            ]),
            CarePlanRecentTableWidget::make([
                'patientId' => $patientId,
            ]),
            CarePlanPreviousTableWidget::make([
                'patientId' => $patientId,
            ]),
            PatientTimelineWidget::make([
                'patientId' => $patientId,
            ]),
            ...app(PageWidgetsRegistry::class)->for(static::class, 'footer', $this),
        ];
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
                        Section::make('NHIS Insurance')
                            ->collapsed()
                            ->columns(3)
                            ->visible(fn (): bool => $this->insuranceVerificationAvailable())
                            ->schema([
                                TextEntry::make('nhis_verification')
                                    ->label('Verification')
                                    ->badge()
                                    ->placeholder('-')
                                    ->state(fn ($record) => $this->nhisVerificationLabel($record))
                                    ->color(fn ($record) => $this->nhisVerificationColor($record)),
                                TextEntry::make('nhis_member_number')
                                    ->label('Member Number')
                                    ->placeholder('-')
                                    ->state(fn ($record) => $this->nhisActivePolicy($record)?->member_number),
                                TextEntry::make('nhis_master_status')
                                    ->label('Master Data')
                                    ->placeholder('-')
                                    ->state(function ($record): string {
                                        $service = $this->memberVerificationService();

                                        if ($service === null) {
                                            return 'Not imported';
                                        }

                                        return $service->masterDataStatus()['imported'] ? 'Imported' : 'Not imported';
                                    }),
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

    }

    protected function getHeaderActions(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $actions = PatientActions::make()
            ->forPatient($this->currentPatient)
            ->withEncounter($this->currentEncounter);

        return [
            $actions->clinicalWorkspaceAction(),
            $actions->timelineAction(),
            $actions->patientActionGroups(),
            ...app(PageHeaderActionsRegistry::class)->for(static::class, $this),
        ];

    }

    public function hasAllergies(): bool
    {
        return $this->allergies->isNotEmpty();
    }

    protected function insuranceVerificationAvailable(): bool
    {
        return config('insurance.enabled', true)
            && ModuleAvailability::insuranceEnabled()
            && $this->memberVerificationService() !== null;
    }

    protected function memberVerificationService(): ?object
    {
        /** @var class-string|null $class */
        $class = OptionalClass::resolve(
            'Modules\\Insurance\\Services\\MemberVerificationService',
            'Insurance',
        );

        if ($class === null) {
            return null;
        }

        return app($class);
    }

    protected function nhisActivePolicy(Patient $patient): ?object
    {
        if (! ModuleAvailability::insuranceEnabled()
            || $patient->relationResolver(Patient::class, 'insurancePolicies') === null) {
            return null;
        }

        $policies = $patient->insurancePolicies;

        if ($policies === null) {
            return null;
        }

        return $policies
            ->where('is_active', true)
            ->sortByDesc('is_primary')
            ->first();
    }

    protected function nhisVerificationLabel(Patient $patient): ?string
    {
        $policy = $this->nhisActivePolicy($patient);

        if (! $policy) {
            return 'No policy';
        }

        $service = $this->memberVerificationService();

        if ($service === null) {
            return 'No policy';
        }

        return $service->badge($policy)['label'];
    }

    protected function nhisVerificationColor(Patient $patient): string
    {
        $policy = $this->nhisActivePolicy($patient);

        if (! $policy) {
            return 'gray';
        }

        $service = $this->memberVerificationService();

        if ($service === null) {
            return 'gray';
        }

        return $service->badge($policy)['color'];
    }

    public function hasActiveEncounter(): bool
    {
        return $this->currentEncounter !== null && $this->currentEncounter->status->isActive();
    }
}
