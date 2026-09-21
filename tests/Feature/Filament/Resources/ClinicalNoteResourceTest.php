<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\ClinicalNoteResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\CreateClinicalNote;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\EditClinicalNote;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\ListClinicalNotes;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\ViewClinicalNote;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Clinical');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Clinical']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
    $this->patient = Patient::factory()->create(['branch_id' => $this->branch->id]);
});

FilamentResourceTestSuite::register([
    'resource' => ClinicalNoteResource::class,
    'subject' => 'ClinicalNote',
    'model' => ClinicalNote::class,
    'listPage' => ListClinicalNotes::class,
    'createPage' => CreateClinicalNote::class,
    'editPage' => EditClinicalNote::class,
    'viewPage' => ViewClinicalNote::class,
    'searchColumn' => 'subject',
    'filter' => [
        'name' => 'note_type',
        'value' => NoteType::PROGRESS->value,
        'attribute' => 'note_type',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): ClinicalNote => ClinicalNote::factory()->create([
        'patient_id' => $test->patient->id,
        'note_type' => NoteType::PROGRESS,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Factory clinical note for testing.'],
                    ],
                ],
            ],
        ],
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $records->push(ClinicalNote::factory()->create([
                'patient_id' => $test->patient->id,
                'note_type' => $index === 1 ? NoteType::ADMISSION : NoteType::PROGRESS,
                'subject' => 'Note subject '.$index,
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Factory clinical note '.$index],
                            ],
                        ],
                    ],
                ],
            ]));
        }

        return $records;
    },
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'note_type' => NoteType::PROGRESS->value,
        'status' => NoteStatus::DRAFT->value,
        'subject' => 'Follow-up note',
        'content' => '<p>Factory clinical note for testing.</p>',
    ],
    'updateForm' => fn (): array => [
        'subject' => 'Updated clinical note',
        'status' => NoteStatus::SIGNED->value,
    ],
    'schemaState' => fn (mixed $test, ClinicalNote $record): array => [
        'note_type' => $record->note_type,
        'status' => $record->status,
    ],
    'requiredValidation' => [
        'note type is required' => [['note_type' => null], ['note_type' => 'required']],
        'status is required' => [['status' => null], ['status' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'patient_id' => $payload['patient_id'],
        'note_type' => $payload['note_type'],
        'status' => $payload['status'],
    ],
]);
