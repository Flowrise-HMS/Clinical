<?php

declare(strict_types=1);

use Modules\Clinical\Filament\Clusters\Clinical\Pages\IcdBrowserPage;
use Modules\Core\Settings\FeatureSettings;
use Tests\TestCase;

uses(TestCase::class);

it('hides the ICD browser from navigation when the feature flag is off', function () {
    FeatureSettings::fake(['icd_browser_enabled' => false]);

    expect(IcdBrowserPage::shouldRegisterNavigation())->toBeFalse();
});

it('shows the ICD browser in navigation when the feature flag is on', function () {
    FeatureSettings::fake(['icd_browser_enabled' => true]);

    expect(IcdBrowserPage::shouldRegisterNavigation())->toBeTrue();
});
