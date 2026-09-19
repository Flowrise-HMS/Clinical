<?php

namespace Modules\Clinical\Filament\Clusters\Clinical;

use BackedEnum;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Clusters\Cluster;
use Modules\Core\Enums\SidebarGroup;

class ClinicalCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = LucideIcon::HeartHandshake;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::PatientCare;

    protected static ?int $navigationSort = 30;
}
