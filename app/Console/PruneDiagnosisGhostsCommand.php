<?php

namespace Modules\Clinical\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\Clinical\Models\EncounterDiagnosis;

/**
 * Removes the inactive duplicate diagnosis rows left behind by the old workspace
 * save, which deactivated and re-inserted every row on each save. A "ghost" is an
 * inactive row with an active twin on the same encounter carrying the same
 * description and codes. Rows a clinician deliberately removed (no active twin)
 * are kept for audit. Dry-run by default.
 */
class PruneDiagnosisGhostsCommand extends Command
{
    protected $signature = 'clinical:prune-diagnosis-ghosts {--force : Delete the ghost rows instead of only reporting them}';

    protected $description = 'Delete inactive encounter diagnosis rows that duplicate an active row on the same encounter';

    public function handle(): int
    {
        $query = $this->ghostQuery();
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No ghost diagnosis rows found.');

            return self::SUCCESS;
        }

        $perEncounter = (clone $query)
            ->selectRaw('encounter_id, count(*) as ghosts')
            ->groupBy('encounter_id')
            ->orderByDesc('ghosts')
            ->limit(20)
            ->get();

        $this->table(['Encounter', 'Ghost rows'], $perEncounter->map(fn ($row) => [$row->encounter_id, $row->ghosts])->all());

        if (! $this->option('force')) {
            $this->warn(sprintf('%d ghost row(s) across %d encounter(s). Re-run with --force to delete them.', $count, (clone $query)->distinct()->count('encounter_id')));

            return self::SUCCESS;
        }

        $ids = (clone $query)->pluck('id');
        $deleted = 0;

        foreach ($ids->chunk(500) as $chunk) {
            $deleted += EncounterDiagnosis::query()->whereIn('id', $chunk->all())->delete();
        }

        $this->info("Deleted {$deleted} ghost diagnosis row(s).");

        return self::SUCCESS;
    }

    /**
     * Inactive rows that have an active twin on the same encounter. `is_new_case`
     * and `notes` are deliberately not compared: they are the fields users edit.
     */
    protected function ghostQuery(): Builder
    {
        return EncounterDiagnosis::query()
            ->from('encounter_diagnoses as ghost')
            ->where('ghost.is_active', false)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('encounter_diagnoses as twin')
                    ->whereColumn('twin.encounter_id', 'ghost.encounter_id')
                    ->where('twin.is_active', true)
                    ->whereColumn('twin.type', 'ghost.type')
                    ->whereColumn('twin.certainty', 'ghost.certainty')
                    ->whereRaw('coalesce(twin.description, \'\') = coalesce(ghost.description, \'\')')
                    ->whereRaw('coalesce(twin.icd_code, \'\') = coalesce(ghost.icd_code, \'\')')
                    ->whereRaw('coalesce(twin.icd_entity_id, \'\') = coalesce(ghost.icd_entity_id, \'\')');
            });
    }
}
