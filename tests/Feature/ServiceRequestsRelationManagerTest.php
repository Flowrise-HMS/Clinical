<?php

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\EditEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\ServiceRequestsRelationManager;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $this->orderedBy = User::factory()->create(['name' => 'Dr Ama', 'branch_id' => $this->branch->id]);
    $this->request = ServiceRequest::factory()
        ->forPatient($this->patient)
        ->forEncounter($this->encounter)
        ->create(['ordered_by' => $this->orderedBy->id]);

    $this->request->items()->save(
        RequestItem::factory()->forService(Service::factory()->create(['name' => 'Full Blood Count']))->make()
    );
});

function mountServiceRequestsRelationManager(Encounter $encounter): Testable
{
    return Livewire::test(ServiceRequestsRelationManager::class, [
        'ownerRecord' => $encounter,
        'pageClass' => EditEncounter::class,
    ]);
}

it('eager loads the client context for the service requests relation manager query', function (): void {
    $component = mountServiceRequestsRelationManager($this->encounter)->instance();

    $eagerLoads = array_keys($component->getFilteredTableQuery()->getEagerLoads());

    expect($eagerLoads)
        ->toContain('patient')
        ->toContain('orderedBy')
        ->toContain('encounter')
        ->toContain('items.service');
});

it('renders the service requests table without lazy loading violations', function (): void {
    Model::preventLazyLoading();

    mountServiceRequestsRelationManager($this->encounter)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->request]);
});

it('opens the service request infolist without lazy loading the client context', function (): void {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    Role::findOrCreate('doctor', 'web');
    $user->assignRole('doctor');
    foreach (['ViewAny ServiceRequest', 'View ServiceRequest'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
    $this->actingAs($user);

    Model::preventLazyLoading();

    mountServiceRequestsRelationManager($this->encounter)
        ->assertOk()
        ->callAction(TestAction::make(ViewAction::class)->table($this->request))
        ->assertSuccessful();
});
