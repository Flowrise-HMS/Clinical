<?php

namespace Modules\Clinical\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

it('renders the patient search above the home dashboard widgets', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $doctor->assignRole('super_admin');

    $this->actingAs($doctor);

    $page = visit(ClinicalWorkspace::getUrl())
        ->assertNoJavaScriptErrors()
        ->assertPresent('input[placeholder="Search patients by name, MRN, or phone..."]');

    // Lazy widgets hydrate when scrolled into view; the search box must sit above them.
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->waitForText('Critical Patients');

    $order = $page->script(<<<'JS'
        (() => {
            const search = document.querySelector('input[placeholder="Search patients by name, MRN, or phone..."]');
            const widget = [...document.querySelectorAll('*')].find(el => el.children.length === 0 && /Critical Patients/.test(el.textContent));
            return search && widget ? (search.getBoundingClientRect().top < widget.getBoundingClientRect().top) : null;
        })()
    JS);

    expect($order)->toBeTrue();
});
