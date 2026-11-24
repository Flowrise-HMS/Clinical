<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Models\ClinicalNote;

/**
 * @group Clinical
 *
 * Clinical notes. Signed notes cannot be edited.
 */
class ClinicalNoteController extends ClinicalApiController
{
    protected string $branchScope = 'via:patient';

    public function __construct(protected ClinicalNoteService $notes) {}

    public static function model(): string
    {
        return ClinicalNote::class;
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'encounter_id', 'service_request_id', 'note_type', 'status', 'subject', 'content',
            'author_id', 'is_signed', 'signed_at', 'signed_by', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'encounter_id' => 'encounter_id', 'note_type' => 'note_type', 'is_signed' => 'is_signed'];
    }

    public function searchable(): array
    {
        return ['subject'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'encounter_id' => ['nullable', 'uuid'],
            'note_type' => ['required', Rule::enum(NoteType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'string', 'max:20000'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $patient = $this->patient($data['patient_id']);

        return $this->notes->record($patient, $data, $this->encounterIdFor($patient, $data['encounter_id'] ?? null));
    }

    protected function updateRecord(Model $record, array $data): Model
    {
        if ($record->is_signed) {
            throw new \DomainException('Signed notes cannot be edited.');
        }

        return parent::updateRecord($record, $data);
    }

    /**
     * Sign the note (locks it).
     */
    public function sign(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (ClinicalNote $note) => $this->notes->sign($note));
    }
}
