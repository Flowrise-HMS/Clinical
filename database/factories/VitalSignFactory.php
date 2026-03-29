<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\PatientPosition;
use Modules\Clinical\Enums\SpO2Label;
use Modules\Clinical\Enums\SpO2Parameter;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class VitalSignFactory extends Factory
{
    protected $model = VitalSign::class;

    public function definition(): array
    {
        return [
            'patient_id' => fn () => Patient::factory()->create()->id,
            'encounter_id' => null,
            'branch_id' => fn () => Branch::factory()->create()->id,
            'recorded_by' => User::factory()->create()->id,
            'recorded_at' => now(),
            'type' => VitalSignType::ROUTINE,
            'position' => PatientPosition::SITTING,
            'measurement_location' => null,
            'systolic_bp' => $this->faker->numberBetween(110, 130),
            'diastolic_bp' => $this->faker->numberBetween(70, 85),
            'heart_rate' => $this->faker->numberBetween(60, 100),
            'respiratory_rate' => $this->faker->numberBetween(12, 20),
            'temperature' => $this->faker->randomFloat(1, 36.0, 37.5),
            'spo2' => $this->faker->numberBetween(95, 100),
            'spo2_label' => SpO2Label::NORMAL,
            'spo2_parameter' => SpO2Parameter::ROOM_AIR,
            'weight' => $this->faker->randomFloat(2, 50, 100),
            'height' => $this->faker->randomFloat(2, 150, 190),
            'bmi' => null,
            'pain_level' => $this->faker->optional()->numberBetween(0, 10),
            'gcs_eye' => null,
            'gcs_verbal' => null,
            'gcs_motor' => null,
            'intake' => null,
            'output' => null,
            'fbs' => null,
            'rbs' => null,
            'notes' => null,
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

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VitalSignType::EMERGENCY,
        ]);
    }

    public function admission(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VitalSignType::ADMISSION,
        ]);
    }

    public function discharge(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VitalSignType::DISCHARGE,
        ]);
    }

    public function withBloodPressure(int $systolic, int $diastolic): static
    {
        return $this->state(fn (array $attributes) => [
            'systolic_bp' => $systolic,
            'diastolic_bp' => $diastolic,
        ]);
    }

    public function withHighBloodPressure(): static
    {
        return $this->state(fn (array $attributes) => [
            'systolic_bp' => $this->faker->numberBetween(140, 180),
            'diastolic_bp' => $this->faker->numberBetween(90, 110),
        ]);
    }

    public function withHighHeartRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'heart_rate' => $this->faker->numberBetween(100, 140),
        ]);
    }

    public function withLowOxygen(): static
    {
        return $this->state(fn (array $attributes) => [
            'spo2' => $this->faker->numberBetween(88, 94),
        ]);
    }

    public function withGcs(): static
    {
        return $this->state(fn (array $attributes) => [
            'gcs_eye' => $this->faker->numberBetween(1, 4),
            'gcs_verbal' => $this->faker->numberBetween(1, 5),
            'gcs_motor' => $this->faker->numberBetween(1, 6),
        ]);
    }

    public function withPain(?int $level = null): static
    {
        return $this->state(fn (array $attributes) => [
            'pain_level' => $level ?? $this->faker->numberBetween(1, 10),
        ]);
    }

    public function withIos(): static
    {
        return $this->state(fn (array $attributes) => [
            'intake' => $this->faker->randomFloat(2, 500, 3000),
            'output' => $this->faker->randomFloat(2, 300, 2500),
        ]);
    }

    public function withBloodSugar(): static
    {
        return $this->state(fn (array $attributes) => [
            'fbs' => $this->faker->randomFloat(2, 70, 130),
            'rbs' => $this->faker->randomFloat(2, 80, 180),
        ]);
    }

    public function withHighFbs(): static
    {
        return $this->state(fn (array $attributes) => [
            'fbs' => $this->faker->randomFloat(2, 126, 300),
        ]);
    }

    public function withLowOxygenWithParameter(SpO2Parameter $parameter): static
    {
        return $this->state(fn (array $attributes) => [
            'spo2' => $this->faker->numberBetween(88, 94),
            'spo2_label' => SpO2Label::LOW,
            'spo2_parameter' => $parameter,
        ]);
    }

    public function standalone(): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => null,
            'encounter_id' => null,
            'branch_id' => null,
        ]);
    }
}
