<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('encounter_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('branch_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('recorded_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->string('type')->default('routine');
            $table->string('position')->nullable();
            $table->string('measurement_location')->nullable();
            $table->unsignedInteger('systolic_bp')->nullable();
            $table->unsignedInteger('diastolic_bp')->nullable();
            $table->unsignedInteger('heart_rate')->nullable();
            $table->unsignedInteger('respiratory_rate')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedInteger('spo2')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->unsignedTinyInteger('gcs_eye')->nullable();
            $table->unsignedTinyInteger('gcs_verbal')->nullable();
            $table->unsignedTinyInteger('gcs_motor')->nullable();
            $table->decimal('intake', 8, 2)->nullable();           // Fluid intake (ml)
            $table->decimal('output', 8, 2)->nullable();            // Urine output (ml)
            $table->decimal('fbs', 6, 2)->nullable();               // Fasting Blood Sugar
            $table->decimal('rbs', 6, 2)->nullable();               // Random Blood Sugar
            $table->string('spo2_label')->nullable();              // SpO2 reading label
            $table->string('spo2_parameter')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['patient_id']);
            $table->index(['encounter_id']);
            $table->index(['recorded_at']);
            $table->index(['recorded_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};
