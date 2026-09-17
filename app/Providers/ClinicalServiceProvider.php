<?php

namespace Modules\Clinical\Providers;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Classes\Services\DiagnosisSearch\CompositeDiagnosisCodeSearch;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Classes\Services\NullPrescriptionScheduleCalculator;
use Modules\Clinical\Console\BackfillBedStatusCommand;
use Modules\Clinical\Console\ExpireAdmissionRequestsCommand;
use Modules\Clinical\Console\SendMarDoseRemindersCommand;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Contracts\PrescriptionScheduleCalculatorContract;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\Task;
use Modules\Clinical\Models\VitalSign;
use Modules\Clinical\Observers\EncounterObserver;
use Modules\Clinical\Observers\RequestItemObserver;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;
use Modules\Patient\Models\Patient;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClinicalServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Clinical';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'clinical';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        BackfillBedStatusCommand::class,
        ExpireAdmissionRequestsCommand::class,
        SendMarDoseRemindersCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Register any other services for the module.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(MedicationFulfillmentPolicy::class);
        $this->app->bind(DiagnosisCodeSearchContract::class, CompositeDiagnosisCodeSearch::class);
        $this->app->bindIf(PrescriptionScheduleCalculatorContract::class, NullPrescriptionScheduleCalculator::class);

        // Register Filament views namespace
        $this->loadViewsFrom(
            module_path($this->name, 'resources/views/filament'),
            'clinical'
        );
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerFilamentAssets();

        Encounter::observe(EncounterObserver::class);
        RequestItem::observe(RequestItemObserver::class);

        if (class_exists(Patient::class)) {
            Patient::resolveRelationUsing('serviceRequests', function ($patient) {
                return $patient->hasMany(ServiceRequest::class, 'patient_id', 'id');
            });

            Patient::resolveRelationUsing('encounters', function ($patient) {
                return $patient->hasMany(Encounter::class, 'patient_id', 'id');
            });

            Patient::resolveRelationUsing('diagnoses', function ($patient) {
                return $patient->hasMany(EncounterDiagnosis::class, 'patient_id', 'id');
            });

            Patient::resolveRelationUsing('latestEncounter', function ($patient) {
                return $patient->hasOne(Encounter::class, 'patient_id', 'id')
                    ->orderByDesc('created_at');
            });

            Patient::resolveRelationUsing('activeEncounter', function ($patient) {
                return $patient->hasOne(Encounter::class, 'patient_id', 'id')
                    ->whereNotIn('status', ['finished', 'cancelled'])
                    ->orderByDesc('created_at');
            });

            Patient::resolveRelationUsing('latestVitals', function ($patient) {
                return $patient->hasOne(VitalSign::class, 'patient_id', 'id')
                    ->latestOfMany('recorded_at');
            });

            Patient::resolveRelationUsing('allergies', function ($patient) {
                return $patient->hasMany(Allergy::class, 'patient_id', 'id');
            });

            Patient::resolveRelationUsing('clinicalNotes', function ($patient) {
                return $patient->hasMany(ClinicalNote::class, 'patient_id', 'id');
            });

            Patient::resolveRelationUsing('vitalSigns', function ($patient) {
                return $patient->hasMany(VitalSign::class, 'patient_id', 'id');
            });

            OptionalClass::when(
                'Modules\\Billing\\Models\\Invoice',
                function (string $invoiceClass): void {
                    $invoiceClass::resolveRelationUsing('encounter', function ($invoice) {
                        return $invoice->belongsTo(Encounter::class, 'encounter_id', 'id');
                    });
                },
                'Billing',
            );

            Patient::resolveRelationUsing('medicationAdministrations', function ($patient) {
                $instance = new MedicationAdministration;

                return new HasMany(
                    $instance->newQuery()
                        ->join('request_items', 'medication_administrations.request_item_id', '=', 'request_items.id')
                        ->join('service_requests', 'request_items.service_request_id', '=', 'service_requests.id')
                        ->select('medication_administrations.*'),
                    $patient,
                    'service_requests.patient_id',
                    'id'
                );
            });

            Patient::resolveRelationUsing('tasks', function ($patient) {
                $instance = new Task;

                return new HasMany(
                    $instance->newQuery()
                        ->join('request_items', 'tasks.request_item_id', '=', 'request_items.id')
                        ->join('service_requests', 'request_items.service_request_id', '=', 'service_requests.id')
                        ->select('tasks.*'),
                    $patient,
                    'service_requests.patient_id',
                    'id'
                );
            });
        }
    }

    /**
     * The canvas engine is a plain ES module copied verbatim to public/ by
     * `filament:assets` and lazy-loaded with `x-load` where a canvas renders.
     */
    protected function registerFilamentAssets(): void
    {
        FilamentAsset::register([
            AlpineComponent::make('clinical-canvas', module_path($this->name, 'resources/assets/js/clinical-canvas.js')),
        ], $this->nameLower);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        // Admission-request expiry does not depend on any optional module.
        $schedule->command('clinical:expire-admission-requests')->hourly();

        if (! ModuleAvailability::pharmacyEnabled()) {
            return;
        }

        $schedule->command('clinical:mar-dose-reminders')->everyFiveMinutes();
    }
}
