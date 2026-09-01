<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The MAR had no database backstop at all: medication_administrations shipped
 * with zero indexes, so two nurses tapping "Give" at the same time could both
 * pass the in-PHP duplicate check and both insert a dose for the same slot.
 *
 * Adds:
 *  - a unique guard so a request item can hold at most one GIVEN dose per slot.
 *    It is expressed through a generated column that is NULL unless the row is
 *    GIVEN, because MySQL has no partial indexes and NULLs do not collide in a
 *    unique index. PRN orders have no schedule, so their dose_slot_sequence
 *    stays NULL and repeated PRN doses remain legal.
 *  - the indexes the MAR board actually queries on.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->clearDuplicateGivenDoses();

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->unsignedInteger('given_dose_slot_sequence')
                ->nullable()
                ->storedAs("CASE WHEN status = 'given' THEN dose_slot_sequence ELSE NULL END");

            $table->unique(
                ['request_item_id', 'given_dose_slot_sequence'],
                'med_admin_given_slot_unique',
            );

            $table->index(['request_item_id', 'status'], 'med_admin_item_status_index');
            $table->index('started_at', 'med_admin_started_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropUnique('med_admin_given_slot_unique');
            $table->dropIndex('med_admin_item_status_index');
            $table->dropIndex('med_admin_started_at_index');
            $table->dropColumn('given_dose_slot_sequence');
        });
    }

    /**
     * Keep the earliest GIVEN dose per (request item, slot) so the unique index
     * can be created on existing data. Later rows are the duplicate recordings
     * this constraint exists to prevent.
     */
    private function clearDuplicateGivenDoses(): void
    {
        $groups = DB::table('medication_administrations')
            ->select('request_item_id', 'dose_slot_sequence')
            ->where('status', 'given')
            ->whereNotNull('dose_slot_sequence')
            ->groupBy('request_item_id', 'dose_slot_sequence')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $keepId = DB::table('medication_administrations')
                ->where('request_item_id', $group->request_item_id)
                ->where('dose_slot_sequence', $group->dose_slot_sequence)
                ->where('status', 'given')
                ->orderBy('started_at')
                ->orderBy('id')
                ->value('id');

            DB::table('medication_administrations')
                ->where('request_item_id', $group->request_item_id)
                ->where('dose_slot_sequence', $group->dose_slot_sequence)
                ->where('status', 'given')
                ->where('id', '!=', $keepId)
                ->update(['dose_slot_sequence' => null]);
        }
    }
};
