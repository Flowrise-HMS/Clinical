<?php

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Resources\Resource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\EncounterDiagnosisResource;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Policies\EncounterDiagnosisPolicy;
use Tests\TestCase;

uses(TestCase::class);

it('registers the encounter diagnosis filament resource', function (): void {
    expect(EncounterDiagnosisResource::getModel())->toBe(EncounterDiagnosis::class)
        ->and(EncounterDiagnosisResource::getSlug())->toBe('clinical/encounter-diagnoses')
        ->and(is_subclass_of(EncounterDiagnosisResource::class, Resource::class))->toBeTrue()
        ->and(array_keys(EncounterDiagnosisResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('exposes the policy permissions for shield discovery', function (): void {
    $permissions = collect(FilamentShield::getResources())
        ->filter(fn (array $resource): bool => $resource['resourceFqcn'] === EncounterDiagnosisResource::class)
        ->flatMap(fn (array $resource): array => $resource['permissions'])
        ->pluck('key')
        ->all();

    expect($permissions)->toContain(
        'ViewAny EncounterDiagnosis',
        'View EncounterDiagnosis',
        'Create EncounterDiagnosis',
        'Update EncounterDiagnosis',
        'Delete EncounterDiagnosis',
        'Restore EncounterDiagnosis',
        'ForceDelete EncounterDiagnosis',
        'ForceDeleteAny EncounterDiagnosis',
        'RestoreAny EncounterDiagnosis',
        'Replicate EncounterDiagnosis',
        'Reorder EncounterDiagnosis',
    );
});

it('covers every permission gate used by the policy', function (): void {
    $policy = new ReflectionClass(EncounterDiagnosisPolicy::class);

    $traitMethods = collect($policy->getTraitNames())
        ->flatMap(fn (string $trait): array => (new ReflectionClass($trait))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->unique()
        ->all();

    $gates = collect($policy->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->reject(fn (string $method): bool => in_array($method, $traitMethods, true))
        ->values()
        ->all();

    expect($gates)->toBe([
        'viewAny', 'view', 'create', 'update', 'delete',
        'restore', 'forceDelete', 'forceDeleteAny',
        'restoreAny', 'replicate', 'reorder',
    ]);
});
