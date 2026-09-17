<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('requested_ward_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('requested_bed_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('assigned_bed_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'requested_at']);
            $table->index(['encounter_id', 'status']);
            $table->index(['requested_ward_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_requests');
    }
};
