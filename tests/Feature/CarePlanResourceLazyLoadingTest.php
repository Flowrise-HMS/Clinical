<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\CarePlanResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ListCarePlans;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ViewCarePlan;
use Modules\Clinical\Models\CarePlan;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    Gate::before(fn (): bool => true);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getDefaultPanel());

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    $this->carePlan = CarePlan::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'author_id' => $this->user->id,
    ]);
});

it('eager loads the patient and author relationships on the care plan resource query', function (): void {
    $eagerLoads = array_keys(CarePlanResource::getEloquentQuery()->getEagerLoads());

    expect($eagerLoads)->toContain('patient')
        ->and($eagerLoads)->toContain('author');
});

it('renders the patient and author columns on the care plan list page without lazy loading', function (): void {
    Model::preventLazyLoading();

    Livewire::test(ListCarePlans::class)
        ->assertOk()
        ->tap(function (Testable $page): void {
            $table = $page->instance()->getTable();
            $record = $page->instance()->getTableRecord($this->carePlan->getKey());

            expect($table->getColumn('client')->record($record)->getState())
                ->toBe($this->patient->clientIdentity()->displayWithIdentifier())
                ->and($table->getColumn('author.name')->record($record)->getStateFromRecord())
                ->toBe($this->user->name);
        });
});

it('renders the care plan view page without lazy loading', function (): void {
    Model::preventLazyLoading();

    Livewire::test(ViewCarePlan::class, ['record' => $this->carePlan->getKey()])
        ->assertOk();
});
