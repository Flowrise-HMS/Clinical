<?php

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Filament\Widgets\PatientDocumentsWidget;
use Modules\Clinical\Filament\Widgets\PatientTimelineWidget;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Classes\Services\MediaDocumentService;
use Modules\Core\Models\Branch;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\RelationManagers\PatientDocumentsRelationManager;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);
    Storage::fake('local');
    Gate::before(fn (): bool => true);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->actingAs($this->user);

    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
});

it('offers an upload documents action inside more actions', function (): void {
    $group = PatientActions::make()->forPatient($this->patient)->patientActionGroups();

    $names = collect($group->getActions())->map(fn ($action) => $action->getName());

    expect($names)->toContain('upload_documents')
        ->and($group->getLabel())->toBe('More Actions');
});

it('uploads from the profile page onto the open encounter', function (): void {
    $encounter = Encounter::factory()->forPatient($this->patient)->active()->create(['branch_id' => $this->branch->id]);

    Livewire::actingAs($this->user)
        ->test(PatientProfile::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->callAction('upload_documents', data: [
            'document_type' => 'referral_letter',
            'title' => 'Referral from clinic',
            'files' => [$this->fakePdf('referral.pdf')],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Document uploaded');

    expect($encounter->documents()->count())->toBe(1)
        ->and($encounter->documents()->first()->name)->toBe('Referral from clinic')
        ->and($this->patient->documents()->count())->toBe(0);
});

it('uploads onto the patient when there is no open encounter', function (): void {
    Livewire::actingAs($this->user)
        ->test(PatientProfile::class, ['patientId' => $this->patient->id])
        ->callAction('upload_documents', data: [
            'document_type' => 'national_id',
            'files' => [$this->fakePdf('id.pdf')],
        ])
        ->assertHasNoActionErrors();

    expect($this->patient->documents()->count())->toBe(1);
});

it('lists patient and encounter documents on the timeline with preview and download actions', function (): void {
    $encounter = Encounter::factory()->forPatient($this->patient)->active()->create(['branch_id' => $this->branch->id]);
    $service = app(MediaDocumentService::class);

    $patientDoc = $service->attach($this->patient, [$this->fakePdf('consent.pdf')], ['document_type' => 'consent_form', 'title' => 'Consent'], $this->user)->first();
    $encounterDoc = $service->attach($encounter, [$this->fakePdf('lab.pdf')], ['document_type' => 'lab_result', 'title' => 'FBC result'], $this->user)->first();

    $workspace = app(ClinicalWorkspaceService::class)->setPatient($this->patient)->clearEncounter();

    $events = $workspace->getTimelineEvents(type: 'document');
    $counts = $workspace->getTimelineEventCounts();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('id')->all())->toContain('document_'.$patientDoc->id, 'document_'.$encounterDoc->id)
        ->and($counts['document'])->toBe(2);

    $labEvent = $events->first(fn (array $event): bool => $event['id'] === 'document_'.$encounterDoc->id);

    expect($labEvent['title'])->toBe('FBC result')
        ->and($labEvent['creator'])->toBe($this->user->name)
        ->and($labEvent['metadata']['Attached to'])->toBe($encounter->encounter_number)
        ->and($labEvent['metadata']['Type'])->toBe('Lab Result')
        ->and(collect($labEvent['actions'])->pluck('label')->all())->toBe(['Preview', 'Download'])
        ->and($labEvent['actions'][0]['url'])->toContain('/media/'.$encounterDoc->uuid.'/download')->toContain('inline=1')
        ->and($labEvent['actions'][1]['url'])->toContain('signature=');

    // Encounter-scoped view only shows that encounter's files.
    $scoped = app(ClinicalWorkspaceService::class)->setPatient($this->patient)->setEncounter($encounter)->getTimelineEvents(type: 'document');
    expect($scoped->pluck('id')->all())->toBe(['document_'.$encounterDoc->id]);

    Livewire::actingAs($this->user)
        ->test(Timeline::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->call('setFilter', 'document')
        ->assertSee('FBC result')
        ->assertSee('Consent')
        ->assertSee('Preview')
        ->assertSee('Download');
});

it('shows the documents relation manager as a widget on the patient profile', function (): void {
    $service = app(MediaDocumentService::class);
    $doc = $service->attach($this->patient, [$this->fakePdf('card.pdf')], ['document_type' => 'insurance_card', 'title' => 'NHIS card'], $this->user)->first();

    $page = Livewire::actingAs($this->user)
        ->test(PatientProfile::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSeeLivewire(PatientDocumentsWidget::class)
        ->assertSeeLivewire(PatientTimelineWidget::class);

    Livewire::actingAs($this->user)
        ->test(PatientDocumentsWidget::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSeeLivewire(PatientDocumentsRelationManager::class);

    Livewire::actingAs($this->user)
        ->test(PatientDocumentsRelationManager::class, ['ownerRecord' => $this->patient, 'pageClass' => PatientDocumentsWidget::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$doc])
        ->assertActionVisible(TestAction::make('upload')->table());
});
