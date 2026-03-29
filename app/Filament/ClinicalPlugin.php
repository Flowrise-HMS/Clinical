<?php

namespace Modules\Clinical\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class ClinicalPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Clinical';
    }

    public function getId(): string
    {
        return 'clinical';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
