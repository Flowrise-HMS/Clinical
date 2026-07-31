<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_medical_diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
            $table->foreignUuid('encounter_diagnosis_id')->constrained('encounter_diagnoses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['care_plan_id', 'encounter_diagnosis_id'],
                'care_plan_medical_diagnoses_plan_diagnosis_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_medical_diagnoses');
    }
};
