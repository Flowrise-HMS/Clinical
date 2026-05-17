<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;

class MyAgenda extends Page
{
    use HasPageShield;

    protected static ?string $title = 'My agenda';

    protected static ?string $navigationLabel = 'My agenda';

    // protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::Calendar;

    protected static ?string $slug = 'my-agenda';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'clinical::filament.clinical.workspace.pages.my-agenda';

    protected function getHeaderActions(): array
    {
        return app(PageHeaderActionsRegistry::class)->for(static::class, $this);
    }

    protected function getHeaderWidgets(): array
    {
        if (! class_exists('Modules\\Appointment\\Models\\Appointment')) {
            return [];
        }

        return [
            WorkspaceTodayAppointmentsWidget::class,
        ];
    }
}
