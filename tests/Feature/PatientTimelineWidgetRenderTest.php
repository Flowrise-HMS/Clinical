<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Filament\Widgets\PatientTimelineWidget;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->user->id,
            'admitted_at' => now()->subHour(),
        ]);
});

it('renders patient timeline event timestamps without optional() type errors', function (): void {
    Livewire::actingAs($this->user)
        ->test(PatientTimelineWidget::class, [
            'patientId' => $this->patient->id,
        ])
        ->assertOk()
        ->assertSee('Clinical Timeline');
});
