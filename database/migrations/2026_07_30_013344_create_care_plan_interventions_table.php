<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_plan_order_id')->constrained('care_plan_orders')->cascadeOnDelete();
            $table->text('description');
            $table->timestamp('performed_at');
            $table->foreignId('performed_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['care_plan_order_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_interventions');
    }
};
