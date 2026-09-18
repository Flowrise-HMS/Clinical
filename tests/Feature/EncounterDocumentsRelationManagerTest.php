<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\ViewEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\EncounterDocumentsRelationManager;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);
    Storage::fake('local');
    Gate::before(fn (): bool => true);

    $this->branch = Branch::factory()->create();
    $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));
});

it('makes encounters media owners and registers the documents tab', function (): void {
    expect(new Encounter)->toBeInstanceOf(HasMedia::class)
        ->and(EncounterResource::getRelations())->toContain(EncounterDocumentsRelationManager::class);
});

it('uploads documents onto the encounter', function (): void {
    $patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id]));
    $encounter = Encounter::factory()->forPatient($patient)->create(['branch_id' => $this->branch->id]);

    Livewire::test(EncounterDocumentsRelationManager::class, [
        'ownerRecord' => $encounter,
        'pageClass' => ViewEncounter::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('upload')->table(), data: [
            'document_type' => 'lab_result',
            'files' => [$this->fakePdf('fbc.pdf')],
        ])
        ->assertHasNoActionErrors();

    expect($encounter->documents()->count())->toBe(1)
        ->and($encounter->documents()->first()->disk)->toBe('local');
});

it('supports guest encounters without a patient', function (): void {
    $encounter = Encounter::factory()->asGuest()->create(['branch_id' => $this->branch->id]);

    Livewire::test(EncounterDocumentsRelationManager::class, [
        'ownerRecord' => $encounter,
        'pageClass' => ViewEncounter::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('upload')->table(), data: [
            'document_type' => 'other',
            'files' => [UploadedFile::fake()->image('wound.png')],
        ])
        ->assertHasNoActionErrors();

    expect($encounter->documents()->count())->toBe(1);
});
