<?php

namespace Modules\Clinical\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardBoard;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

it('renders the ward board without JavaScript errors', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->default()->create();
    $nurse = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $nurse->assignRole('super_admin');
    $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    $ward = Location::factory()->room()->create(['branch_id' => $branch->id, 'name' => 'Male Medical', 'is_active' => true, 'nurse_in_charge_id' => $nurse->id, 'capacity' => 4]);
    $beds = collect(range(1, 4))->map(fn (int $i) => Location::factory()->bed()->create(['branch_id' => $branch->id, 'parent_id' => $ward->id, 'name' => "Bed {$i}", 'code' => "MM-B0{$i}", 'is_active' => true]));
    $beds[3]->forceFill(['status' => BedStatus::BLOCKED, 'status_reason' => 'Awaiting repair'])->save();

    $this->actingAs($doctor);
    $patientA = Patient::factory()->male()->create(['branch_id' => $branch->id]);
    $stayA = app(AdtService::class)->admit($patientA, $beds[0]->id, chiefComplaint: 'Severe malaria with dehydration', actedBy: $doctor->id);
    $stayA->forceFill(['admitted_at' => now()->subDays(9)])->save();
    VitalSign::factory()->forPatient($patientA)->create(['encounter_id' => $stayA->id, 'systolic_bp' => 168, 'diastolic_bp' => 102, 'spo2' => 92, 'recorded_at' => now()->subMinutes(15)]);

    $patientB = Patient::factory()->male()->create(['branch_id' => $branch->id]);
    app(AdtService::class)->admit($patientB, $beds[1]->id, chiefComplaint: 'Post-op observation', actedBy: $doctor->id);

    $waiting = Patient::factory()->male()->create(['branch_id' => $branch->id]);
    $enc = Encounter::factory()->forPatient($waiting)->outpatient()->create(['branch_id' => $branch->id, 'status' => EncounterStatus::ARRIVED]);
    app(AdtService::class)->requestAdmission($enc, $ward->id, bedId: $beds[2]->id, notes: 'Needs IV antibiotics overnight', requestedBy: $doctor->id);

    $this->actingAs($nurse);

    $page = visit(WardBoard::getUrl(['ward' => $ward->id]));

    $page->assertNoJavaScriptErrors()
        ->assertSee('Nurse in charge')
        ->assertSee($patientA->full_name)
        ->assertSee($patientB->full_name)
        ->assertSee('Abnormal vitals')
        ->assertSee('Long stay')
        ->assertSee('Reserved for')
        ->assertSee('Awaiting repair')
        ->assertSee('Waiting for a bed')
        ->assertSee($waiting->full_name)
        ->screenshotElement('.fi-page', 'ward-board')
        ->click('button[wire\:click^="mountAction(\'discharge\'"] >> nth=0')
        ->assertSee('block discharge')
        ->assertSee('Discharge summary signed')
        ->assertSee('Override reason')
        ->screenshot(filename: 'ward-board-discharge-modal');
});
