<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Enums\ParticipantStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterParticipant;

class EncounterParticipantFactory extends Factory
{
    protected $model = EncounterParticipant::class;

    public function definition(): array
    {
        return [
            'encounter_id' => Encounter::factory(),
            'user_id' => User::factory()->create()->id,
            'role' => ParticipantRole::NURSE,
            'status' => ParticipantStatus::ACTIVE,
            'joined_at' => now(),
            'left_at' => null,
            'notes' => null,
        ];
    }

    public function forEncounter(Encounter $encounter): static
    {
        return $this->state(fn (array $attributes) => [
            'encounter_id' => $encounter->id,
        ]);
    }

    public function asNurse(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ParticipantRole::NURSE,
        ]);
    }

    public function asPhysician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ParticipantRole::PRIMARY_PROVIDER,
        ]);
    }

    public function asAttending(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ParticipantRole::ATTENDING,
        ]);
    }

    public function asConsultant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ParticipantRole::CONSULTANT,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ParticipantStatus::COMPLETED,
            'left_at' => now(),
        ]);
    }
}
