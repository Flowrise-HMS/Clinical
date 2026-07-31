<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
            $table->foreignUuid('care_plan_problem_id')->constrained('care_plan_problems')->cascadeOnDelete();
            $table->foreignUuid('catalogue_id')->constrained('nursing_diagnosis_catalogue')->cascadeOnDelete();
            $table->text('problem_statement');
            $table->text('related_to');
            $table->text('as_evidenced_by');
            $table->text('composed_statement');
            $table->timestamp('recorded_at');
            $table->foreignId('formulated_by')->constrained('users');
            $table->timestamps();

            $table->index(['care_plan_id', 'care_plan_problem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_diagnoses');
    }
};
