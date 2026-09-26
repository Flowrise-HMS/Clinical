<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SATS triage assessments. An encounter can be triaged more than once (re-triage);
     * the latest assessment drives the encounter's priority and queue position.
     */
    public function up(): void
    {
        Schema::create('triage_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('vital_sign_id')->nullable()->constrained('vital_signs')->nullOnDelete();
            $table->string('age_band', 20);
            $table->string('mobility', 20)->nullable();
            $table->string('avpu', 20)->nullable();
            $table->boolean('trauma')->default(false);
            $table->unsignedTinyInteger('tews_score')->nullable();
            $table->json('tews_breakdown')->nullable();
            $table->json('discriminators')->nullable();
            $table->string('tews_category', 10)->nullable();
            $table->string('discriminator_category', 10)->nullable();
            $table->string('override_category', 10)->nullable();
            $table->text('override_reason')->nullable();
            $table->string('final_category', 10)->index();
            $table->string('disposition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triaged_at');
            $table->timestamps();

            $table->index(['encounter_id', 'triaged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_assessments');
    }
};
