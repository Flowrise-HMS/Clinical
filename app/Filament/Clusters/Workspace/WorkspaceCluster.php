<?php

namespace Modules\Clinical\Filament\Clusters\Workspace;

use BackedEnum;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class WorkspaceCluster extends Cluster
{
    protected static ?string $slug = 'workspace';

    protected static ?string $title = 'Clinical Workspace';

    protected static ?string $navigationLabel = 'Clinical Workspace';

    protected static string|BackedEnum|null $navigationIcon = LucideIcon::HeartPulse;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static bool $shouldRegisterSubNavigation = false;
}
