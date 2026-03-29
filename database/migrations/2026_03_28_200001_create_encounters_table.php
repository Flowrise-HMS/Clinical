<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('encounter_number', 30)->unique();
            $table->foreignUuid('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();
            $table->foreignUuid('branch_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->foreignUuid('department_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('type')->default('outpatient');
            $table->string('status')->default('planned');
            $table->string('priority')->default('routine');
            $table->text('chief_complaint')->nullable();
            $table->foreignId('admitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('discharged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('discharge_disposition')->nullable();
            $table->string('transfer_destination')->nullable();
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->foreignUuid('bed_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['patient_id']);
            $table->index(['branch_id']);
            $table->index(['status']);
            $table->index(['type']);
            $table->index(['created_at']);
            $table->index(['admitted_at', 'discharged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
