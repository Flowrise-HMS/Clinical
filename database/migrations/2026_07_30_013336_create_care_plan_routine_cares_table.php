<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_routine_cares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
            $table->string('item');
            $table->text('specification')->nullable();
            $table->boolean('not_applicable')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('specified_by')->constrained('users');
            $table->timestamp('specified_at');
            $table->timestamps();

            $table->unique(['care_plan_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_routine_cares');
    }
};
