<?php

namespace Modules\Clinical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(TaskStatus::values())],
            'outcome' => ['nullable', Rule::in(TaskOutcome::values())],
            'performed_by' => ['nullable', 'integer', 'exists:users,id'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'results' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
