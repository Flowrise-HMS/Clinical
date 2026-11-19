<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\WardBoardService;
use Modules\Clinical\Enums\WardStatementPeriod;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\DailyWardStatementsPage;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardBoard;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardInchargeNotesPage;
use Modules\Clinical\Models\DailyWardStatement;
use Modules\Clinical\Models\WardInchargeNote;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Classes\Services\WardAccessService;
use Modules\Core\Contracts\WardInventoryOverviewContract;
use Modules\Core\Models\Location;

/**
 * Login Dashboard overview for Ward Incharges (primary nurse in charge or active deputy incharge).
 * Automatically hidden for users who are not assigned as incharge of any ward.
 */
class WardInchargeOverviewWidget extends Widget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'clinical::clinical.widgets.ward-incharge-overview-widget';

    public static function canView(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return app(WardAccessService::class)->wardsForIncharge($user)->isNotEmpty();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWardCards(): array
    {
        $user = Auth::user();
        if ($user === null) {
            return [];
        }

        $branchId = app(BranchService::class)->getDefaultBranchId();
        $wards = app(WardAccessService::class)->wardsForIncharge($user, $branchId);

        if ($wards->isEmpty()) {
            $wards = app(WardAccessService::class)->wardsForIncharge($user);
        }

        $wardBoardService = app(WardBoardService::class);
        $inventoryOverview = app(WardInventoryOverviewContract::class);
        $today = now()->toDateString();

        return $wards->map(function (Location $ward) use ($wardBoardService, $inventoryOverview, $today): array {
            $board = $wardBoardService->buildWard($ward);
            $stats = $board['stats'] ?? [];

            $todayStatement = DailyWardStatement::query()
                ->where('location_id', $ward->id)
                ->whereDate('statement_date', $today)
                ->where('period_type', WardStatementPeriod::DAILY->value)
                ->first();

            $unacknowledgedNotes = WardInchargeNote::query()
                ->forWard($ward->id)
                ->unacknowledged()
                ->count();

            $pinnedNotes = WardInchargeNote::query()
                ->forWard($ward->id)
                ->pinned()
                ->latest()
                ->limit(2)
                ->get(['id', 'title', 'priority']);

            $inventory = $inventoryOverview->summaryForWard($ward->id, (string) $ward->branch_id);

            return [
                'id' => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
                'beds_total' => (int) ($stats['beds_total'] ?? 0),
                'occupied' => (int) ($stats['occupied'] ?? 0),
                'available' => (int) ($stats['available'] ?? 0),
                'blocked' => (int) ($stats['blocked'] ?? 0),
                'occupancy_pct' => (int) ($stats['occupancy_pct'] ?? 0),
                'pending_admissions' => (int) ($stats['pending_requests'] ?? 0),
                'statement_status' => $todayStatement ? (string) $todayStatement->status->getLabel() : 'Not Started',
                'statement_color' => $todayStatement ? (string) $todayStatement->status->getColor() : 'warning',
                'unacknowledged_notes' => $unacknowledgedNotes,
                'pinned_notes' => $pinnedNotes->map(fn (WardInchargeNote $n): array => [
                    'title' => $n->title,
                    'priority' => $n->priority->value,
                ])->all(),
                'inventory' => $inventory,
                'ward_board_url' => WardBoard::getUrl(['ward' => $ward->id]),
                'statement_url' => DailyWardStatementsPage::getUrl(['ward' => $ward->id]),
                'notes_url' => WardInchargeNotesPage::getUrl(['ward' => $ward->id]),
            ];
        })->values()->all();
    }
}
