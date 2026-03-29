<?php

namespace Modules\Clinical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;

class EncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $encounterId = $this->route('encounter')?->id;

        return [
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'type' => ['required', Rule::in(EncounterType::values())],
            'status' => ['nullable', Rule::in(EncounterStatus::values())],
            'priority' => ['nullable', Rule::in(EncounterPriority::values())],
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'admitted_by' => ['nullable', 'integer', 'exists:users,id'],
            'discharged_by' => ['nullable', 'integer', 'exists:users,id'],
            'discharge_disposition' => ['nullable', 'string'],
            'transfer_destination' => ['nullable', 'string', 'max:255'],
            'admitted_at' => ['nullable', 'date'],
            'discharged_at' => ['nullable', 'date', 'after_or_equal:admitted_at'],
            'bed_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.exists' => 'The selected patient does not exist.',
            'branch_id.required' => 'Branch is required.',
            'type.required' => 'Encounter type is required.',
        ];
    }
}
