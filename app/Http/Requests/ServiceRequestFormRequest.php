<?php

namespace Modules\Clinical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;

class ServiceRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requestId = $this->route('service_request')?->id;

        return [
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'uuid', 'exists:encounters,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'status' => ['nullable', Rule::in(RequestStatus::values())],
            'priority' => ['nullable', Rule::in(RequestPriority::values())],
            'notes' => ['nullable', 'string', 'max:10000'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'ordered_by' => ['nullable', 'integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],

            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required_with:items', 'uuid', 'exists:services,id'],
            'items.*.service_variant_id' => ['nullable', 'uuid', 'exists:service_variants,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.exists' => 'The selected patient does not exist.',
            'branch_id.required' => 'Branch is required.',
            'items.*.service_id.exists' => 'One or more selected services do not exist.',
        ];
    }
}
