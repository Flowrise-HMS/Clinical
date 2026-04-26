<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Patient reference (UUID)
            $table->foreignUuid('patient_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            // Allergy details
            $table->string('allergen');
            $table->string('allergen_code')->nullable(); // ICD-10 or other coding system
            $table->enum('allergen_type', [
                'medication',
                'food',
                'environmental',
                'biological',
                'other',
            ])->nullable();

            // Reaction details
            $table->text('reaction')->nullable();
            $table->enum('severity', [
                'mild',
                'moderate',
                'severe',
                'life_threatening',
            ])->default('moderate');

            // Onset information
            $table->enum('onset_type', [
                'acute',
                'chronic',
                'unknown',
            ])->default('unknown');

            // Status
            $table->boolean('is_active')->default(true);
            $table->date('onset_date')->nullable();
            $table->date('verified_at')->nullable();

            // Verification
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->enum('verification_status', [
                'unverified',
                'verified',
                'refuted',
            ])->default('unverified');

            // Notes and metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['patient_id', 'is_active']);
            $table->index(['allergen_type', 'is_active']);
            $table->index(['severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergies');
    }
};
