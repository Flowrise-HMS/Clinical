<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_problem_strengths', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_problem_id')->constrained('care_plan_problems')->cascadeOnDelete();
            $table->text('description');
            $table->foreignId('identified_by')->constrained('users');
            $table->timestamp('identified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_problem_strengths');
    }
};
