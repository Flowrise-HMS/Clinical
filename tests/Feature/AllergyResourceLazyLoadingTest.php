<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\AllergyResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\ListAllergies;
use Modules\Clinical\Models\Allergy;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    Gate::before(fn (): bool => true);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getDefaultPanel());

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    $this->allergy = Allergy::factory()->create(['patient_id' => $this->patient->id]);
});

it('eager loads the patient relationship on the allergy resource query', function (): void {
    $eagerLoads = array_keys(AllergyResource::getEloquentQuery()->getEagerLoads());

    expect($eagerLoads)->toContain('patient');
});

it('renders the patient column on the allergy list page without lazy loading', function (): void {
    Model::preventLazyLoading();

    Livewire::test(ListAllergies::class)
        ->assertOk()
        ->tap(function (Testable $page): void {
            $table = $page->instance()->getTable();
            $record = $page->instance()->getTableRecord($this->allergy->getKey());

            expect($table->getColumn('client')->record($record)->getState())
                ->toBe($this->patient->clientIdentity()->displayWithIdentifier());
        });
});
