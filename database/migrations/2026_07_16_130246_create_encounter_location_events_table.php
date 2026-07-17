<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_location_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignUuid('encounter_id')
                ->constrained('encounters')
                ->cascadeOnDelete();
            $table->foreignUuid('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();
            $table->string('event_type');
            $table->foreignUuid('from_bed_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->foreignUuid('to_bed_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->foreignUuid('from_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->foreignUuid('to_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->foreignUuid('from_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->foreignUuid('to_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->string('destination_type')->nullable();
            $table->foreignUuid('destination_branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->string('destination_label')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('acted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['branch_id', 'occurred_at']);
            $table->index(['encounter_id', 'occurred_at']);
            $table->index(['patient_id', 'occurred_at']);
            $table->index(['event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_location_events');
    }
};
