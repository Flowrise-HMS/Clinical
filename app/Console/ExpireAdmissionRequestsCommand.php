<?php

namespace Modules\Clinical\Console;

use Illuminate\Console\Command;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Models\AdmissionRequest;

class ExpireAdmissionRequestsCommand extends Command
{
    protected $signature = 'clinical:expire-admission-requests';

    protected $description = 'Expire pending admission requests whose expiry time has passed and release any reserved beds';

    public function handle(AdtService $adtService): int
    {
        $expired = 0;

        AdmissionRequest::query()
            ->dueToExpire()
            ->orderBy('expires_at')
            ->each(function (AdmissionRequest $request) use ($adtService, &$expired): void {
                try {
                    $adtService->expireAdmissionRequest($request);
                    $expired++;
                } catch (\Throwable $e) {
                    $this->warn("Could not expire admission request {$request->id}: {$e->getMessage()}");
                }
            });

        $this->info("Expired {$expired} admission request(s).");

        return self::SUCCESS;
    }
}
