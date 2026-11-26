<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Clinical\Database\Seeders\ClinicalCustomPermissionSeeder;

/**
 * Existing installs never re-run seeders, so doctors there still lack the
 * diagnosis permissions and cannot see the workspace Diagnosis tab. This grants
 * only those permissions, to the default roles that still exist; it leaves every
 * other grant, including ones an admin removed, alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');

        if (! Schema::hasTable($tables['roles'] ?? 'roles') || ! Schema::hasTable($tables['permissions'] ?? 'permissions')) {
            return;
        }

        ClinicalCustomPermissionSeeder::grantEncounterDiagnosisPermissions();
    }

    /**
     * Not reversed: by then admins may rely on these grants, and revoking them
     * would hide recorded diagnoses from clinicians.
     */
    public function down(): void {}
};
