<?php

namespace Modules\Clinical\Filament\Clusters\Clinical;

use BackedEnum;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Clusters\Cluster;

class ClinicalCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = LucideIcon::HeartHandshake;
}
