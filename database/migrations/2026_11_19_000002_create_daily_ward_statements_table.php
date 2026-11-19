<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_ward_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->date('statement_date');
            $table->string('period_type', 20)->default('daily');
            $table->dateTime('period_start_at');
            $table->dateTime('period_end_at');
            $table->string('status', 20)->default('draft');

            // Bed complement
            $table->unsignedSmallInteger('total_beds')->default(0);
            $table->unsignedSmallInteger('blocked_beds')->default(0);
            $table->unsignedSmallInteger('floor_or_extra_beds')->default(0);

            // Patient movement reconciliation (GHS Daily Bed State)
            $table->unsignedSmallInteger('brought_forward')->default(0);
            $table->unsignedSmallInteger('admissions_count')->default(0);
            $table->unsignedSmallInteger('transfers_in_count')->default(0);
            $table->unsignedSmallInteger('discharges_count')->default(0);
            $table->unsignedSmallInteger('transfers_out_count')->default(0);
            $table->unsignedSmallInteger('deaths_count')->default(0);
            $table->unsignedSmallInteger('lama_absconded_count')->default(0);
            $table->unsignedSmallInteger('on_pass_count')->default(0);
            $table->unsignedSmallInteger('remaining_total')->default(0);

            // Snapshot & Ghana / GHS breakdowns
            $table->json('demographics_breakdown')->nullable();
            $table->json('patient_snapshot')->nullable();

            // Narrative & sign-off
            $table->text('critically_ill_notes')->nullable();
            $table->text('special_cases_notes')->nullable();
            $table->text('general_remarks')->nullable();

            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'statement_date', 'period_type'], 'daily_ward_statements_ward_date_period_unique');
            $table->index(['branch_id', 'statement_date', 'status'], 'daily_ward_statements_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ward_statements');
    }
};
