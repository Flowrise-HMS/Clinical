<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Patient\Models\Patient;

class ClinicalNoteFactory extends Factory
{
    protected $model = ClinicalNote::class;

    public function definition(): array
    {
        $noteType = $this->faker->randomElement(NoteType::cases());

        return [
            'note_type' => $noteType,
            'noteable_type' => null,
            'noteable_id' => null,
            'patient_id' => fn () => Patient::factory()->create()->id,
            'author_id' => User::factory()->create()->id,
            'encounter_id' => null,
            'service_request_id' => null,
            'status' => NoteStatus::DRAFT,
            'subject' => $this->faker->sentence(4),
            'content' => ['text' => $this->faker->paragraph(3)],
            'attachments' => null,
            'is_signed' => false,
            'signed_at' => null,
            'signed_by' => null,
        ];
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patient->id,
        ]);
    }

    public function forEncounter(Encounter $encounter): static
    {
        return $this->state(fn (array $attributes) => [
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
        ]);
    }

    public function forServiceRequest(ServiceRequest $request): static
    {
        return $this->state(fn (array $attributes) => [
            'service_request_id' => $request->id,
            'patient_id' => $request->patient_id,
        ]);
    }

    public function ofType(NoteType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'note_type' => $type,
        ]);
    }

    public function general(): static
    {
        return $this->ofType(NoteType::GENERAL);
    }

    public function progress(): static
    {
        return $this->ofType(NoteType::PROGRESS)->state(fn (array $attributes) => [
            'content' => [
                'subjective' => $this->faker->sentence(),
                'objective' => $this->faker->sentence(),
                'assessment' => $this->faker->sentence(),
                'plan' => $this->faker->sentence(),
            ],
        ]);
    }

    public function nursing(): static
    {
        return $this->ofType(NoteType::NURSING)->state(fn (array $attributes) => [
            'content' => [
                'procedure' => $this->faker->sentence(),
                'patient_response' => $this->faker->sentence(),
                'observations' => $this->faker->paragraph(),
            ],
        ]);
    }

    public function surgery(): static
    {
        return $this->ofType(NoteType::SURGERY)->state(fn (array $attributes) => [
            'content' => [
                'procedure_performed' => $this->faker->sentence(),
                'findings' => $this->faker->paragraph(),
                'complications' => 'None',
                'anaesthesia_type' => 'General',
                'duration_minutes' => $this->faker->numberBetween(60, 240),
                'outcome' => 'Successful',
                'post_op_instructions' => $this->faker->sentence(),
            ],
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NoteStatus::DRAFT,
            'is_signed' => false,
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NoteStatus::SIGNED,
            'is_signed' => true,
            'signed_at' => now(),
            'signed_by' => User::factory()->create()->id,
        ]);
    }

    public function withContent(array $content): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }
}
