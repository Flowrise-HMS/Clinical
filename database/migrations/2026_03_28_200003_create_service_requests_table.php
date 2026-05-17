<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 30)->unique();
            $table->foreignUuid('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();
            $table->foreignUuid('encounter_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('branch_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->string('priority')->default('routine');
            $table->text('notes')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->foreignId('ordered_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['patient_id']);
            $table->index(['encounter_id']);
            $table->index(['branch_id']);
            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['ordered_by']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
