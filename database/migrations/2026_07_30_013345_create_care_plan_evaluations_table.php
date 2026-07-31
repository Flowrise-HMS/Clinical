<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_objective_id')->constrained('care_plan_objectives')->cascadeOnDelete();
            $table->foreignId('evaluated_by')->constrained('users');
            $table->timestamp('evaluated_at');
            $table->string('outcome');
            $table->text('findings');
            $table->string('next_action');
            $table->string('achievement_status_snapshot');
            $table->timestamps();

            $table->index(['care_plan_objective_id', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_evaluations');
    }
};
