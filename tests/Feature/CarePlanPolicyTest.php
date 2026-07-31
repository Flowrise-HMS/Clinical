<?php

use App\Models\User;
use Modules\Clinical\Models\CarePlan;
use Modules\Core\Models\Branch;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Clinical']);

    $this->branch = Branch::factory()->create();
});

it('does not allow a guest to create a care plan', function (): void {
    $this->assertGuest();

    expect(auth()->guard()->guest())->toBeTrue()
        ->and(auth()->user()?->can('create', CarePlan::class) ?? false)->toBeFalse();
});

it('allows an authorized user to create a care plan', function (): void {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    Permission::findOrCreate('Create CarePlan', 'web');
    $user->givePermissionTo('Create CarePlan');

    $this->actingAs($user);

    expect($user->can('create', CarePlan::class))->toBeTrue();
});

it('allows an authorized user to evaluate a care plan objective', function (): void {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $carePlan = CarePlan::factory()->create(['branch_id' => $this->branch->id]);
    Permission::findOrCreate('Evaluate CarePlan', 'web');
    $user->givePermissionTo('Evaluate CarePlan');

    $this->actingAs($user);

    expect($user->can('evaluate', $carePlan))->toBeTrue();
});
