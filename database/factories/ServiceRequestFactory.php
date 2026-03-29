<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'request_number' => 'SRQ-'.now()->format('Ymd').'-'.str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'patient_id' => null,
            'encounter_id' => null,
            'branch_id' => fn () => Branch::factory()->create()->id,
            'status' => RequestStatus::ACTIVE,
            'priority' => RequestPriority::ROUTINE,
            'notes' => $this->faker->optional()->sentence(),
            'guest_name' => null,
            'guest_phone' => null,
            'guest_email' => null,
            'ordered_by' => User::factory()->create()->id,
            'created_by' => fn (array $attributes) => $attributes['ordered_by'],
            'metadata' => null,
        ];
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patient->id,
            'branch_id' => $patient->branch_id,
        ]);
    }

    public function forEncounter(Encounter $encounter): static
    {
        return $this->state(fn (array $attributes) => [
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'branch_id' => $encounter->branch_id,
        ]);
    }

    public function asGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_phone' => $this->faker->phoneNumber(),
            'guest_email' => $this->faker->safeEmail(),
        ]);
    }

    public function withStatus(RequestStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function withPriority(RequestPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => RequestPriority::EMERGENCY,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => RequestPriority::URGENT,
        ]);
    }
}
