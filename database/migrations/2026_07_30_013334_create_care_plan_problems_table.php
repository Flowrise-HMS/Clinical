<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_problems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('priority')->nullable();
            $table->foreignId('identified_by')->constrained('users');
            $table->timestamps();

            $table->index(['care_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_problems');
    }
};
