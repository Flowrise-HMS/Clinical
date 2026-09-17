<?php

namespace Modules\Clinical\Console;

use Illuminate\Console\Command;
use Modules\Clinical\Classes\Services\BedStatusBackfillService;

class BackfillBedStatusCommand extends Command
{
    protected $signature = 'clinical:backfill-bed-status';

    protected $description = 'Set bed statuses from the active encounters currently holding beds';

    public function handle(BedStatusBackfillService $service): int
    {
        $result = $service->run();

        $this->info("Marked {$result['occupied']} bed(s) occupied and {$result['available']} bed(s) available.");

        return self::SUCCESS;
    }
}
