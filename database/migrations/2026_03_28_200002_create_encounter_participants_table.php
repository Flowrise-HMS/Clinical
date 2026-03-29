<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('encounter_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('role')->default('nurse');
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['encounter_id']);
            $table->index(['user_id']);
            $table->index(['status']);
            $table->unique(['encounter_id', 'user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_participants');
    }
};
