<?php

namespace Modules\Clinical\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Observers\EncounterObserver;
use Modules\Clinical\Observers\RequestItemObserver;
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

        if (class_exists(\Modules\Patient\Models\Patient::class)) {
            \Modules\Patient\Models\Patient::resolveRelationUsing('serviceRequests', function ($patient) {
                return $patient->hasMany(\Modules\Clinical\Models\ServiceRequest::class);
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
