<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('encounter_id')
                ->constrained('encounters')
                ->cascadeOnDelete();
            $table->foreignUuid('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();
            $table->foreignUuid('diagnosis_code_id')
                ->nullable()
                ->constrained('diagnosis_codes')
                ->nullOnDelete();
            $table->string('icd_code', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('primary');
            $table->foreignId('ordered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['encounter_id', 'type']);
            $table->index(['patient_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_diagnoses');
    }
};
