<?php

namespace Modules\Clinical\Providers;

use Illuminate\Console\Scheduling\Schedule;
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
