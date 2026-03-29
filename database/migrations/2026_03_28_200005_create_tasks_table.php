<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_item_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->json('results')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['request_item_id']);
            $table->index(['status']);
            $table->index(['performed_by']);
            $table->index(['started_at', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
