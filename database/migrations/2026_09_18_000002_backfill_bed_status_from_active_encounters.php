<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Clinical\Classes\Services\BedStatusBackfillService;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('locations', 'status') || ! Schema::hasTable('encounters')) {
            return;
        }

        app(BedStatusBackfillService::class)->run();
    }

    public function down(): void
    {
        // The Core migration that drops the columns undoes this.
    }
};
