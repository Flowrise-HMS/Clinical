<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->boolean('witness_confirmed')->default(false)->after('status');
            $table->text('omission_reason')->nullable()->after('witness_confirmed');
            $table->text('prn_reason')->nullable()->after('omission_reason');
            $table->unsignedSmallInteger('dose_slot_sequence')->nullable()->after('prn_reason');
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropColumn([
                'witness_confirmed',
                'omission_reason',
                'prn_reason',
                'dose_slot_sequence',
            ]);
        });
    }
};
