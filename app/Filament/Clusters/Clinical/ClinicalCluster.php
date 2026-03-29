<?php

namespace Modules\Clinical\Filament\Clusters\Clinical;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ClinicalCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
}
