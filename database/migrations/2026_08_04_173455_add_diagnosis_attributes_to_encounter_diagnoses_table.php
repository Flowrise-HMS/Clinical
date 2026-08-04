<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounter_diagnoses', function (Blueprint $table) {
            $table->boolean('is_new_case')->default(false)->after('type');
            $table->string('certainty', 20)->default('provisional')->after('is_new_case');
            $table->string('icd_entity_id', 64)->nullable()->after('diagnosis_code_id');
            $table->string('icd_uri', 255)->nullable()->after('icd_entity_id');
        });
    }

    public function down(): void
    {
        Schema::table('encounter_diagnoses', function (Blueprint $table) {
            $table->dropColumn(['is_new_case', 'certainty', 'icd_entity_id', 'icd_uri']);
        });
    }
};
