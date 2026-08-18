<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalWorkspaceNoteValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $doctor;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

        $this->branch = Branch::factory()->default()->create();
        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        Role::findOrCreate('doctor', 'web');
        $this->doctor->assignRole('doctor');

        foreach (['Create ClinicalNote', 'View ClinicalNote'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->doctor->givePermissionTo($permission);
        }

        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );
    }

    public function test_save_clinical_note_rejects_an_empty_submission(): void
    {
        $this->actingAs($this->doctor);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->noteFormData = ['content' => null];

        try {
            $page->saveClinicalNote();
            $this->fail('An empty clinical note should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('noteFormData.note_type', $exception->errors());
        }

        $this->assertDatabaseMissing('clinical_notes', ['patient_id' => $this->patient->id]);
    }

    public function test_save_clinical_note_persists_a_complete_note(): void
    {
        $this->actingAs($this->doctor);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->noteFormData = [
            'note_type' => NoteType::PROGRESS->value,
            'status' => NoteStatus::DRAFT->value,
            'subject' => 'Morning round',
            'content' => '<p>Patient stable overnight.</p>',
        ];

        $page->saveClinicalNote();

        $note = ClinicalNote::query()
            ->where('patient_id', $this->patient->id)
            ->firstOrFail();

        $this->assertSame(NoteType::PROGRESS, $note->note_type);
        $this->assertSame('Morning round', $note->subject);
        $this->assertStringContainsString('Patient stable overnight', $note->content_html);
    }

    public function test_save_consultation_persists_rich_editor_html(): void
    {
        $this->actingAs($this->doctor);

        $this->openEncounter();

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->consultationChiefComplaint = 'Headache';
        $page->consultationData = [
            'notes' => '<p>SOAP: tension headache, discharge with analgesia.</p>',
        ];

        $page->saveConsultation();

        $note = ClinicalNote::query()
            ->where('patient_id', $this->patient->id)
            ->firstOrFail();

        $this->assertSame(NoteType::CONSULTATION, $note->note_type);
        $this->assertStringContainsString('tension headache', $note->content_html);
    }

    public function test_save_consultation_persists_tiptap_document_state(): void
    {
        $this->actingAs($this->doctor);

        $this->openEncounter();

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->consultationData = [
            'notes' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'SOAP plan documented in the editor',
                    ]],
                ]],
            ],
        ];

        $page->saveConsultation();

        $note = ClinicalNote::query()
            ->where('patient_id', $this->patient->id)
            ->firstOrFail();

        $this->assertStringContainsString('SOAP plan documented in the editor', $note->content_html);
    }

    protected function openEncounter(): void
    {
        Encounter::factory()->create([
            'patient_id' => $this->patient->id,
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::IN_PROGRESS,
            'type' => EncounterType::OUTPATIENT,
        ]);
    }

    protected function makeWorkspacePage(): ClinicalWorkspace
    {
        $page = app(ClinicalWorkspace::class);
        $page->boot();

        return $page;
    }
}
