<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_dose_reminder_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_item_id')->constrained('request_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('dose_slot_sequence');
            $table->string('reminder_type');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['request_item_id', 'dose_slot_sequence', 'reminder_type'], 'mar_reminder_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_dose_reminder_logs');
    }
};
