<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_canvas_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('canvas_key', 32);
            $table->uuid('context_id')->nullable();
            $table->json('layout');
            $table->timestamps();

            $table->unique(['user_id', 'patient_id', 'canvas_key', 'context_id'], 'canvas_layout_owner_unique');
            $table->index(['patient_id', 'canvas_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_canvas_layouts');
    }
};
