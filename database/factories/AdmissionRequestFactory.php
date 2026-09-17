<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Location;

/**
 * @extends Factory<AdmissionRequest>
 */
class AdmissionRequestFactory extends Factory
{
    protected $model = AdmissionRequest::class;

    public function definition(): array
    {
        return [
            'encounter_id' => Encounter::factory(),
            'patient_id' => fn (array $attributes) => Encounter::query()
                ->find($attributes['encounter_id'])
                ?->patient_id,
            'branch_id' => fn (array $attributes) => Encounter::query()
                ->find($attributes['encounter_id'])
                ?->branch_id,
            'requested_ward_id' => Location::factory()->room(),
            'requested_bed_id' => null,
            'assigned_bed_id' => null,
            'status' => AdmissionRequestStatus::Pending,
            'notes' => null,
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'decided_by' => null,
            'decided_at' => null,
            'decision_notes' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => AdmissionRequestStatus::Accepted,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'status' => AdmissionRequestStatus::Rejected,
            'decided_by' => User::factory(),
            'decided_at' => now(),
            'decision_notes' => $reason ?? 'No bed available',
        ]);
    }
}
