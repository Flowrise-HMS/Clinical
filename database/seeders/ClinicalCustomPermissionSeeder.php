<?php

namespace Modules\Clinical\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the Clinical custom permissions and grants them to the default
 * roles. Unlike the Patient seeder, missing permissions are created rather
 * than skipped so a fresh install has them without a Shield regeneration.
 */
class ClinicalCustomPermissionSeeder extends Seeder
{
    /** @var array<string, string[]> permission name => web-guard roles */
    protected array $matrix = [
        'manage_bed_status' => ['super_admin', 'nurse', 'admissions_staff'],
        'sign_discharge_summary' => ['super_admin', 'doctor'],
        'print_discharge_summary' => ['super_admin', 'doctor', 'nurse'],
        'override_triage_category' => ['super_admin', 'doctor'],
    ];

    /**
     * Resource permissions for encounter diagnoses, per role. Shield's generated role
     * dump predates the diagnosis resource, so without this a fresh install leaves
     * doctors without the Diagnosis tab. Mirrors the clinical-note grants.
     *
     * @var array<string, list<string>>
     */
    public const ENCOUNTER_DIAGNOSIS_GRANTS = [
        'super_admin' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder'],
        'doctor' => ['ViewAny', 'View', 'Create', 'Update'],
        'nurse' => ['ViewAny', 'View'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        static::grantEncounterDiagnosisPermissions();

        foreach ($this->matrix as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'web');

            foreach ($roles as $roleName) {
                Role::query()
                    ->where(['name' => $roleName, 'guard_name' => 'web'])
                    ->first()
                    ?->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Creates the encounter diagnosis permissions and grants them to the default
     * roles that exist. Only adds: never removes a permission an admin granted.
     * Also run by a migration so existing installs get it on upgrade.
     */
    public static function grantEncounterDiagnosisPermissions(): void
    {
        foreach (self::ENCOUNTER_DIAGNOSIS_GRANTS as $roleName => $actions) {
            $role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first();

            $permissions = array_map(
                fn (string $action): Permission => Permission::findOrCreate("{$action} EncounterDiagnosis", 'web'),
                $actions,
            );

            $role?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
