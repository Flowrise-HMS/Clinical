<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ModuleAvailability;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
});

function makeBannerSlotUser(Branch $branch, array $permissions = []): User
{
    Role::findOrCreate('nurse', 'web');
    Permission::findOrCreate('View ClinicalWorkspace', 'web');

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('nurse');
    $user->givePermissionTo(['View ClinicalWorkspace', ...$permissions]);

    return $user;
}

it('exposes the billing summary widget in the patient banner slot for permitted users', function (): void {
    if (! ModuleAvailability::billingEnabled()) {
        $this->markTestSkipped('Billing module must be enabled.');
    }

    Permission::findOrCreate('view_patient_balance', 'web');
    $this->actingAs(makeBannerSlotUser($this->branch, ['view_patient_balance']));

    $page = app(ClinicalWorkspace::class);
    $page->boot();

    expect($page->patientBannerWidgets())
        ->toContain('Modules\\Billing\\Filament\\Widgets\\PatientBillingSummaryWidget');
});

it('exposes no patient banner widgets without the balance permission', function (): void {
    $this->actingAs(makeBannerSlotUser($this->branch));

    $page = app(ClinicalWorkspace::class);
    $page->boot();

    expect($page->patientBannerWidgets())->toBe([]);
});
