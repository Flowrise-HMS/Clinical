<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Core\Settings\FeatureSettings;

class IcdBrowserPage extends Page
{
    use HasPageShield;

    protected static ?string $navigationLabel = 'ICD Browser';

    protected static ?string $title = 'ICD-11 Browser';

    protected static ?string $cluster = ClinicalCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 15;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return app(FeatureSettings::class)->icd_browser_enabled;
        } catch (\Throwable) {
            return true;
        }
    }

    protected string $view = 'clinical::filament.pages.icd-browser';
}
