<?php

namespace Modules\Clinical\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

it('saves a typed diagnosis from the workspace diagnosis tab', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $doctor->assignRole('super_admin');

    $patient = Patient::factory()->create(['branch_id' => $branch->id]);

    $encounter = Encounter::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
        'type' => 'outpatient',
        'created_by' => $doctor->id,
    ]);

    $this->actingAs($doctor);

    $page = visit(route('filament.corepanel.workspace.pages.clinical-workspace', ['patientId' => $patient->id]));

    $page->assertNoJavaScriptErrors()
        ->click('button[wire\:click="$set(\'activeTab\', \'diagnosis\')"]')
        ->assertSee('Save Diagnoses');

    $page->fill('input[wire\:model="diagnosisFormData.diagnoses.0.description"]', 'Malaria')
        ->click('button[wire\:click="saveDiagnoses"]')
        ->wait(2);

    $page->screenshot();

    $page->assertSee('Diagnoses saved');

    $this->assertDatabaseHas('encounter_diagnoses', [
        'encounter_id' => $encounter->id,
        'description' => 'Malaria',
        'is_active' => 1,
    ]);
});
