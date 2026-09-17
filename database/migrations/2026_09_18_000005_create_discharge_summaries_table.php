<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discharge_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('encounter_id')->unique()->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('admission_diagnosis')->nullable();
            $table->json('discharge_diagnoses')->nullable();
            $table->text('presenting_complaint')->nullable();
            $table->longText('hospital_course')->nullable();
            $table->text('procedures')->nullable();
            $table->string('condition_at_discharge', 32)->nullable();
            $table->json('discharge_medications')->nullable();
            $table->text('instructions')->nullable();
            $table->text('diet')->nullable();
            $table->text('activity')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->string('follow_up_provider_id', 36)->nullable();
            $table->string('disposition', 32)->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->foreignId('authored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discharge_summaries');
    }
};
