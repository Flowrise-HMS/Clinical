<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ward_incharge_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('note_type', 30)->default('daily_affairs');
            $table->string('shift', 20)->nullable();
            $table->date('note_date');
            $table->string('priority', 20)->default('normal');
            $table->string('title', 255);
            $table->text('body');
            $table->json('structured_payload')->nullable();
            $table->foreignUuid('related_encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('related_bed_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('escalated_to_role', 50)->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('authored_by')->constrained('users')->cascadeOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'note_date'], 'ward_incharge_notes_ward_date_idx');
            $table->index(['location_id', 'is_pinned', 'status'], 'ward_incharge_notes_pinned_idx');
            $table->index(['branch_id', 'note_type', 'status'], 'ward_incharge_notes_branch_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ward_incharge_notes');
    }
};
