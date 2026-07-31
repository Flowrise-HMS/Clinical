<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_diagnosis_id')->constrained('care_plan_diagnoses')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->text('instruction');
            $table->string('frequency');
            $table->string('status')->default('planned');
            $table->nullableUuidMorphs('plannable');
            $table->timestamps();

            $table->unique(['care_plan_diagnosis_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_orders');
    }
};
