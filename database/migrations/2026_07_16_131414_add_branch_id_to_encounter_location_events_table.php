<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('encounter_location_events')) {
            return;
        }

        if (Schema::hasColumn('encounter_location_events', 'branch_id')) {
            return;
        }

        Schema::table('encounter_location_events', function (Blueprint $table) {
            $table->foreignUuid('branch_id')
                ->nullable()
                ->after('id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('encounter_location_events')) {
            return;
        }

        if (! Schema::hasColumn('encounter_location_events', 'branch_id')) {
            return;
        }

        Schema::table('encounter_location_events', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'occurred_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
