<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('note_type');
            $table->string('noteable_type')->nullable();
            $table->uuid('noteable_id')->nullable();
            $table->foreignUuid('patient_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('author_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('encounter_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('service_request_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('subject')->nullable();
            $table->json('content')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['noteable_type', 'noteable_id']);
            $table->index(['patient_id']);
            $table->index(['author_id']);
            $table->index(['encounter_id']);
            $table->index(['note_type']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
    }
};
