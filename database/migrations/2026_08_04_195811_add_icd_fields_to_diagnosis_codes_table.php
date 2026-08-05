<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosis_codes', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->change();
            $table->string('icd_entity_id', 64)->nullable()->after('code');
            $table->string('icd_uri', 255)->nullable()->after('icd_entity_id');

            $table->index('icd_entity_id');
        });
    }

    public function down(): void
    {
        Schema::table('diagnosis_codes', function (Blueprint $table) {
            $table->dropIndex(['icd_entity_id']);
            $table->dropColumn(['icd_entity_id', 'icd_uri']);
            $table->string('code', 20)->nullable(false)->change();
        });
    }
};
