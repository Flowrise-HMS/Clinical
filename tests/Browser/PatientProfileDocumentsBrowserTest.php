<?php

namespace Modules\Clinical\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Classes\Services\MediaDocumentService;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

it('renders the documents widget on the profile and document actions on the timeline', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);
    Storage::fake('local');

    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $doctor->assignRole('super_admin');

    $patient = Patient::factory()->create(['branch_id' => $branch->id]);
    $encounter = Encounter::factory()->forPatient($patient)->active()->create(['branch_id' => $branch->id]);

    app(MediaDocumentService::class)->attach($encounter, [$this->fakePdf('referral.pdf')], ['document_type' => 'referral_letter', 'title' => 'Referral letter'], $doctor);

    $this->actingAs($doctor);

    // Footer widgets are lazy and hydrate only once scrolled into view.
    $profile = visit(PatientProfile::getUrl(['patient' => $patient->id]))
        ->assertNoJavaScriptErrors();

    $profile->script('window.scrollTo(0, document.body.scrollHeight)');
    $profile->wait(2);
    $profile->script('window.scrollTo(0, document.body.scrollHeight)');

    $profile->waitForText('Referral letter')
        ->assertSee('Upload documents')
        ->assertNoJavaScriptErrors();

    visit(Timeline::getUrl(['patient' => $patient->id, 'filter' => 'document']))
        ->assertNoJavaScriptErrors()
        ->waitForText('Referral letter')
        ->assertSee('Preview')
        ->assertSee('Download');
});
