<?php

namespace Modules\Clinical\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

it('navigates the clinical workspace and transitions modern tabs smoothly', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);
    $doctor->assignRole('super_admin');

    $patient = Patient::factory()->create(['branch_id' => $branch->id]);

    $encounter = Encounter::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
        'type' => 'inpatient',
        'created_by' => $doctor->id,
    ]);

    $this->actingAs($doctor);

    // Visit the clinical workspace for the patient context with dynamic route name helper
    $page = visit(route('filament.corepanel.workspace.pages.clinical-workspace', ['patient' => $patient->id]));

    $page->assertNoJavaScriptErrors()
        ->assertSee($patient->full_name)
        // Verify the modernized clinician action tab bar is rendered
        ->assertSeeAnythingIn('.rounded-t-xl')
        // Click the different modern tabs and verify content transitions without errors
        ->click('button:contains("Encounter")')
        ->assertSee('SOAP Notes')
        ->click('button:contains("ADT")')
        ->assertSee('Admit / Transfer / Discharge')
        ->click('button:contains("Patient Details")')
        ->assertSee('Demographics');
});
