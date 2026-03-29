<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class EncounterFactory extends Factory
{
    protected $model = Encounter::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(EncounterType::cases());

        return [
            'encounter_number' => 'ENC-'.now()->format('Ymd').'-'.str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'patient_id' => null,
            'branch_id' => fn () => Branch::factory()->create()->id,
            'location_id' => null,
            'department_id' => null,
            'type' => $type,
            'status' => EncounterStatus::PLANNED,
            'priority' => EncounterPriority::ROUTINE,
            'chief_complaint' => $this->faker->sentence(6),
            'admitted_by' => null,
            'discharged_by' => null,
            'discharge_disposition' => null,
            'transfer_destination' => null,
            'admitted_at' => null,
            'discharged_at' => null,
            'bed_id' => null,
            'guest_name' => null,
            'guest_phone' => null,
            'guest_email' => null,
            'created_by' => User::factory()->create()->id,
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

    public function asGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_phone' => $this->faker->phoneNumber(),
            'guest_email' => $this->faker->safeEmail(),
        ]);
    }

    public function inpatient(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EncounterType::INPATIENT,
        ]);
    }

    public function outpatient(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EncounterType::OUTPATIENT,
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EncounterType::EMERGENCY,
            'priority' => EncounterPriority::URGENT,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EncounterStatus::IN_PROGRESS,
            'admitted_at' => now()->subHours(2),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EncounterStatus::FINISHED,
            'admitted_at' => now()->subHours(5),
            'discharged_at' => now()->subHour(),
            'discharged_by' => User::factory()->create()->id,
            'discharge_disposition' => DischargeDisposition::COMPLETED,
        ]);
    }

    public function withStatus(EncounterStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function withPriority(EncounterPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
