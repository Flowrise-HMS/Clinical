<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->timestamp('expected_discharge_at')->nullable()->after('discharged_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropIndex(['expected_discharge_at']);
            $table->dropColumn('expected_discharge_at');
        });
    }
};
