<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Patient\Models\Patient;

class ClinicalNoteService
{
    public function create(
        Patient $patient,
        NoteType $noteType,
        string $subject,
        array $content,
        ?string $encounterId = null,
        ?string $serviceRequestId = null,
        ?int $authorId = null
    ): ClinicalNote {
        return ClinicalNote::create([
            'patient_id' => $patient->id,
            'note_type' => $noteType,
            'subject' => $subject,
            'content' => $content,
            'author_id' => $authorId ?? auth()->id(),
            'encounter_id' => $encounterId,
            'service_request_id' => $serviceRequestId,
            'status' => NoteStatus::DRAFT,
        ]);
    }

    public function createStructuredNote(
        Patient $patient,
        NoteType $noteType,
        string $subject,
        array $structuredContent,
        ?string $encounterId = null,
        ?int $authorId = null
    ): ClinicalNote {
        $content = $this->formatStructuredContent($noteType, $structuredContent);

        return $this->create($patient, $noteType, $subject, $content, $encounterId, null, $authorId);
    }

    protected function formatStructuredContent(NoteType $noteType, array $data): array
    {
        $content = [];

        switch ($noteType) {
            case NoteType::SOAP:
                $content = [
                    'subjective' => $data['subjective'] ?? '',
                    'objective' => $data['objective'] ?? '',
                    'assessment' => $data['assessment'] ?? '',
                    'plan' => $data['plan'] ?? '',
                ];
                break;

            case NoteType::SURGERY:
                $content = [
                    'procedure_performed' => $data['procedure_performed'] ?? '',
                    'findings' => $data['findings'] ?? '',
                    'complications' => $data['complications'] ?? '',
                    'anaesthesia_type' => $data['anaesthesia_type'] ?? '',
                    'duration_minutes' => $data['duration_minutes'] ?? null,
                    'outcome' => $data['outcome'] ?? '',
                    'post_op_instructions' => $data['post_op_instructions'] ?? '',
                ];
                break;

            case NoteType::NURSING:
                $content = [
                    'procedure' => $data['procedure'] ?? '',
                    'patient_response' => $data['patient_response'] ?? '',
                    'vital_signs' => $data['vital_signs'] ?? [],
                    'observations' => $data['observations'] ?? '',
                ];
                break;

            case NoteType::LAB:
            case NoteType::RADIOLOGY:
                $content = [
                    'test_performed' => $data['test_performed'] ?? '',
                    'results' => $data['results'] ?? [],
                    'interpretation' => $data['interpretation'] ?? '',
                    'attachments' => $data['attachments'] ?? [],
                ];
                break;

            case NoteType::MEDICATION:
                $content = [
                    'medication' => $data['medication'] ?? '',
                    'dosage' => $data['dosage'] ?? '',
                    'route' => $data['route'] ?? '',
                    'time_given' => $data['time_given'] ?? null,
                    'patient_response' => $data['patient_response'] ?? '',
                    'next_due' => $data['next_due'] ?? null,
                ];
                break;

            default:
                $content = $data;
        }

        return $content;
    }

    public function sign(ClinicalNote $note): ClinicalNote
    {
        if ($note->isSigned()) {
            throw new \InvalidArgumentException('Note is already signed');
        }

        $note->sign(auth()->id());

        return $note->fresh();
    }

    public function amend(ClinicalNote $note, string $reason): ClinicalNote
    {
        if (! $note->isSigned()) {
            throw new \InvalidArgumentException('Only signed notes can be amended');
        }

        $note->amend($reason, auth()->id());

        return $note->fresh();
    }

    public function getPatientNotes(Patient $patient, int $limit = 20): Collection
    {
        return ClinicalNote::where('patient_id', $patient->id)
            ->with(['author', 'encounter'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getEncounterNotes(string $encounterId): Collection
    {
        return ClinicalNote::where('encounter_id', $encounterId)
            ->with(['author'])
            ->orderBy('created_at')
            ->get();
    }

    public function getServiceRequestNotes(string $serviceRequestId): Collection
    {
        return ClinicalNote::where('service_request_id', $serviceRequestId)
            ->with(['author'])
            ->orderBy('created_at')
            ->get();
    }

    public function getUnsignedNotes(?int $authorId = null): Collection
    {
        $query = ClinicalNote::where('status', NoteStatus::DRAFT)
            ->with(['patient', 'author']);

        if ($authorId) {
            $query->where('author_id', $authorId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
