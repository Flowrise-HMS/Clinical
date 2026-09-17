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
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
}
