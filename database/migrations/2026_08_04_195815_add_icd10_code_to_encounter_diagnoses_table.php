<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounter_diagnoses', function (Blueprint $table) {
            $table->string('icd10_code', 20)->nullable()->after('icd_code');
        });
    }

    public function down(): void
    {
        Schema::table('encounter_diagnoses', function (Blueprint $table) {
            $table->dropColumn('icd10_code');
        });
    }
};
