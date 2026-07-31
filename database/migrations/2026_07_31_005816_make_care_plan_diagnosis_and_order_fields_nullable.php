<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plan_diagnoses', function (Blueprint $table): void {
            $table->dropForeign(['catalogue_id']);
        });

        Schema::table('care_plan_diagnoses', function (Blueprint $table): void {
            $table->uuid('catalogue_id')->nullable()->change();
            $table->string('label')->nullable()->after('catalogue_id');
            $table->text('problem_statement')->nullable()->change();
            $table->text('related_to')->nullable()->change();
            $table->text('as_evidenced_by')->nullable()->change();
        });

        Schema::table('care_plan_diagnoses', function (Blueprint $table): void {
            $table->foreign('catalogue_id')
                ->references('id')
                ->on('nursing_diagnosis_catalogue')
                ->nullOnDelete();
        });

        Schema::table('care_plan_orders', function (Blueprint $table): void {
            $table->string('frequency')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('care_plan_diagnoses', function (Blueprint $table): void {
            $table->dropForeign(['catalogue_id']);
        });

        Schema::table('care_plan_diagnoses', function (Blueprint $table): void {
            $table->dropColumn('label');
            $table->uuid('catalogue_id')->nullable(false)->change();
            $table->text('problem_statement')->nullable(false)->change();
            $table->text('related_to')->nullable(false)->change();
            $table->text('as_evidenced_by')->nullable(false)->change();
        });

        Schema::table('care_plan_diagnoses', function (Blueprint $table): void {
            $table->foreign('catalogue_id')
                ->references('id')
                ->on('nursing_diagnosis_catalogue')
                ->cascadeOnDelete();
        });

        Schema::table('care_plan_orders', function (Blueprint $table): void {
            $table->string('frequency')->nullable(false)->change();
        });
    }
};
