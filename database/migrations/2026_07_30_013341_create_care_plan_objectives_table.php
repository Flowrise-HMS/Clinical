<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_objectives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_diagnosis_id')->constrained('care_plan_diagnoses')->cascadeOnDelete();
            $table->text('description');
            $table->string('target_measure')->nullable();
            $table->string('target_value')->nullable();
            $table->date('target_date')->nullable();
            $table->string('lifecycle_status')->default('proposed');
            $table->string('achievement_status')->default('in-progress');
            $table->date('start_date')->nullable();
            $table->foreignId('author_id')->constrained('users');
            $table->timestamps();

            $table->index(
                ['care_plan_diagnosis_id', 'lifecycle_status'],
                'care_plan_objectives_diagnosis_lifecycle_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_objectives');
    }
};
