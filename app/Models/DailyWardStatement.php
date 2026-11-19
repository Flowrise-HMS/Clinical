<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Clinical\Enums\WardStatementPeriod;
use Modules\Clinical\Enums\WardStatementStatus;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;

/**
 * Daily Ward Statement (Ghana Health Service Daily Bed State / Ward Return).
 *
 * Reconciliation invariant:
 *   remaining_total = brought_forward + admissions_count + transfers_in_count
 *                   - discharges_count - transfers_out_count - deaths_count - lama_absconded_count
 *
 * @property string $id
 * @property string $branch_id
 * @property string $location_id
 * @property Carbon $statement_date
 * @property WardStatementPeriod $period_type
 * @property Carbon $period_start_at
 * @property Carbon $period_end_at
 * @property WardStatementStatus $status
 * @property int $total_beds
 * @property int $blocked_beds
 * @property int $floor_or_extra_beds
 * @property int $brought_forward
 * @property int $admissions_count
 * @property int $transfers_in_count
 * @property int $discharges_count
 * @property int $transfers_out_count
 * @property int $deaths_count
 * @property int $lama_absconded_count
 * @property int $on_pass_count
 * @property int $remaining_total
 * @property ?array<string, mixed> $demographics_breakdown
 * @property ?array<string, mixed> $patient_snapshot
 * @property ?string $critically_ill_notes
 * @property ?string $special_cases_notes
 * @property ?string $general_remarks
 * @property ?int $prepared_by
 * @property ?int $submitted_by
 * @property ?Carbon $submitted_at
 * @property ?int $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property ?string $review_notes
 * @property ?string $amendment_reason
 */
class DailyWardStatement extends BaseModel
{
    use HasFactory, HasUuids;

    protected $table = 'daily_ward_statements';

    protected $keyType = 'string';

    protected $fillable = [
        'branch_id',
        'location_id',
        'statement_date',
        'period_type',
        'period_start_at',
        'period_end_at',
        'status',
        'total_beds',
        'blocked_beds',
        'floor_or_extra_beds',
        'brought_forward',
        'admissions_count',
        'transfers_in_count',
        'discharges_count',
        'transfers_out_count',
        'deaths_count',
        'lama_absconded_count',
        'on_pass_count',
        'remaining_total',
        'demographics_breakdown',
        'patient_snapshot',
        'critically_ill_notes',
        'special_cases_notes',
        'general_remarks',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'amendment_reason',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'period_type' => WardStatementPeriod::class,
            'period_start_at' => 'datetime',
            'period_end_at' => 'datetime',
            'status' => WardStatementStatus::class,
            'total_beds' => 'integer',
            'blocked_beds' => 'integer',
            'floor_or_extra_beds' => 'integer',
            'brought_forward' => 'integer',
            'admissions_count' => 'integer',
            'transfers_in_count' => 'integer',
            'discharges_count' => 'integer',
            'transfers_out_count' => 'integer',
            'deaths_count' => 'integer',
            'lama_absconded_count' => 'integer',
            'on_pass_count' => 'integer',
            'remaining_total' => 'integer',
            'demographics_breakdown' => 'array',
            'patient_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function computedRemaining(): int
    {
        return max(0, $this->brought_forward
            + $this->admissions_count
            + $this->transfers_in_count
            - $this->discharges_count
            - $this->transfers_out_count
            - $this->deaths_count
            - $this->lama_absconded_count);
    }

    public function occupancyRate(): float
    {
        $usableBeds = max(0, $this->total_beds - $this->blocked_beds);

        if ($usableBeds === 0) {
            return 0.0;
        }

        return round(($this->remaining_total / $usableBeds) * 100, 1);
    }

    public function scopeForWard($query, string $wardId)
    {
        return $query->where('location_id', $wardId);
    }
}
