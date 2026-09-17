<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Classes\Services\BranchService;

/**
 * Inpatients past their expected discharge date or over the long-stay
 * threshold, for the workspace home. Polls like its siblings.
 */
class LongStayPatientsWidget extends Widget
{
    protected string $view = 'clinical::filament.widgets.long-stay-patients-widget';

    protected int $sorting = 2;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    /**
     * @var Collection<int, Encounter>
     */
    public Collection $encounters;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();

        $this->encounters = Encounter::query()
            ->longStay()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['patient', 'location', 'bed'])
            ->orderBy('admitted_at')
            ->limit(12)
            ->get();
    }

    public function thresholdDays(): int
    {
        return (int) config('clinical.admissions.long_stay_days', 7);
    }
}
