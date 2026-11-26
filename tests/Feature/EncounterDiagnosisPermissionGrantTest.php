<?php

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Clinical\Database\Seeders\ClinicalCustomPermissionSeeder;
use Modules\Clinical\Policies\EncounterDiagnosisPolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);
    $this->seed(ShieldSeeder::class);
});

function stockRoleUser(string $role): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::factory()->create()->assignRole($role);
}

it('ships stock roles without diagnosis permissions, which is what this fixes', function (): void {
    expect(app(EncounterDiagnosisPolicy::class)->create(stockRoleUser('doctor')))->toBeFalse();
});

it('grants doctors and nurses the diagnosis permissions from the clinical seeder', function (): void {
    $this->seed(ClinicalCustomPermissionSeeder::class);

    $policy = app(EncounterDiagnosisPolicy::class);
    $doctor = stockRoleUser('doctor');
    $nurse = stockRoleUser('nurse');
    $receptionist = stockRoleUser('receptionist');

    expect($policy->create($doctor))->toBeTrue()
        ->and($policy->update($doctor, new Modules\Clinical\Models\EncounterDiagnosis))->toBeTrue()
        ->and($policy->delete($doctor, new Modules\Clinical\Models\EncounterDiagnosis))->toBeFalse()
        ->and($policy->viewAny($nurse))->toBeTrue()
        ->and($policy->create($nurse))->toBeFalse()
        ->and($policy->viewAny($receptionist))->toBeFalse();
});

it('upgrades existing installs without re-granting what an admin revoked', function (): void {
    $this->seed(ClinicalCustomPermissionSeeder::class);

    // An admin later takes a Clinical permission away from nurses.
    Role::findByName('nurse', 'web')->revokePermissionTo(['manage_bed_status']);

    $migration = require base_path('Modules/Clinical/database/migrations/2026_11_25_000001_grant_encounter_diagnosis_permissions.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // The upgrade only adds the diagnosis grants: other Clinical grants stay as the admin left them.
    expect(Role::findByName('nurse', 'web')->hasPermissionTo('manage_bed_status'))->toBeFalse()
        ->and(Role::findByName('doctor', 'web')->hasPermissionTo('Create EncounterDiagnosis'))->toBeTrue()
        ->and(Role::findByName('nurse', 'web')->hasPermissionTo('ViewAny EncounterDiagnosis'))->toBeTrue();
});

it('skips roles an install has deleted', function (): void {
    Role::findByName('nurse', 'web')->delete();

    ClinicalCustomPermissionSeeder::grantEncounterDiagnosisPermissions();

    expect(Role::query()->where('name', 'nurse')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'ViewAny EncounterDiagnosis')->exists())->toBeTrue();
});
