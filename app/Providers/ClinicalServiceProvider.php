<?php

namespace Modules\Clinical\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\Task;
use Modules\Clinical\Models\VitalSign;
use Modules\Clinical\Observers\EncounterObserver;
use Modules\Clinical\Observers\RequestItemObserver;
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
    // protected array $commands = [];

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

        // Register Filament views namespace
        $this->loadViewsFrom(
            module_path($this->name, 'resources/views/filament'),
            'clinical'
        );
    }

    public function boot(): void
    {
        parent::boot();

        Encounter::observe(EncounterObserver::class);
        RequestItem::observe(RequestItemObserver::class);

        if (class_exists(Patient::class)) {
            Patient::resolveRelationUsing('serviceRequests', function ($patient) {
                return $patient->hasMany(ServiceRequest::class);
            });

            Patient::resolveRelationUsing('encounters', function ($patient) {
                return $patient->hasMany(Encounter::class);
            });

            Patient::resolveRelationUsing('clinicalNotes', function ($patient) {
                return $patient->hasMany(ClinicalNote::class);
            });

            Patient::resolveRelationUsing('vitalSigns', function ($patient) {
                return $patient->hasMany(VitalSign::class);
            });

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
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
