<?php

namespace Modules\Clinical\Classes\Merge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Contracts\PatientMergeHandler;

/**
 * Clinical rows the generic `patient_id` repoint cannot move safely:
 * canvas layouts carry a unique key per (user, patient, canvas, context).
 * Also surfaces active encounters on both profiles in the merge preview.
 */
final class ClinicalMergeHandler implements PatientMergeHandler
{
    public function handledTables(): array
    {
        return ['clinical_canvas_layouts'];
    }

    public function preview(Model $source, Model $target): array
    {
        $counts = [];

        $layouts = DB::table('clinical_canvas_layouts')->where('patient_id', $source->getKey())->count();
        if ($layouts > 0) {
            $counts['Canvas layouts'] = $layouts;
        }

        $activeOnBoth = Encounter::query()->withoutGlobalScopes()->active()->where('patient_id', $source->getKey())->exists()
            && Encounter::query()->withoutGlobalScopes()->active()->where('patient_id', $target->getKey())->exists();

        if ($activeOnBoth) {
            $counts['Active encounters on both profiles'] = 2;
        }

        return $counts;
    }

    public function handle(Model $source, Model $target, array $context): array
    {
        $moved = 0;
        $deduped = 0;

        $rows = DB::table('clinical_canvas_layouts')->where('patient_id', $source->getKey())->get();

        foreach ($rows as $row) {
            $collides = DB::table('clinical_canvas_layouts')
                ->where('patient_id', $target->getKey())
                ->where('user_id', $row->user_id)
                ->where('canvas_key', $row->canvas_key)
                ->where(fn ($q) => $row->context_id === null
                    ? $q->whereNull('context_id')
                    : $q->where('context_id', $row->context_id))
                ->exists();

            if ($collides) {
                DB::table('clinical_canvas_layouts')->where('id', $row->id)->delete();
                $deduped++;

                continue;
            }

            DB::table('clinical_canvas_layouts')->where('id', $row->id)->update(['patient_id' => $target->getKey()]);
            $moved++;
        }

        return ['clinical_canvas_layouts' => ['moved' => $moved, 'deduped' => $deduped]];
    }
}
