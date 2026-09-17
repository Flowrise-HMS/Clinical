<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Models\CanvasLayout;

/**
 * Persists a user's arrangement of an interactive canvas. Ownership is enforced
 * here: every call is scoped to the given user, so one user can never read or
 * overwrite another's layout.
 */
class CanvasLayoutService
{
    public const MAX_PAYLOAD_BYTES = 262144;

    public const MAX_NODES = 500;

    public const MAX_NOTES = 200;

    public const MAX_EDGES = 300;

    /**
     * @var list<string>
     */
    public const NOTE_COLORS = ['amber', 'sky', 'emerald', 'rose', 'violet'];

    /**
     * @return array<string, mixed>|null
     */
    public function get(User $user, string $canvasKey, string $patientId, ?string $contextId = null): ?array
    {
        return CanvasLayout::query()
            ->forOwner($user->id, $patientId, $canvasKey, $contextId)
            ->value('layout');
    }

    /**
     * @param  array<string, mixed>  $layout
     *
     * @throws ValidationException
     */
    public function save(User $user, string $canvasKey, string $patientId, ?string $contextId, array $layout): CanvasLayout
    {
        $validated = $this->validate($layout);

        return CanvasLayout::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'patient_id' => $patientId,
                'canvas_key' => $canvasKey,
                'context_id' => $contextId,
            ],
            ['layout' => $validated],
        );
    }

    public function reset(User $user, string $canvasKey, string $patientId, ?string $contextId = null): void
    {
        CanvasLayout::query()
            ->forOwner($user->id, $patientId, $canvasKey, $contextId)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validate(array $layout): array
    {
        if (strlen((string) json_encode($layout)) > self::MAX_PAYLOAD_BYTES) {
            throw ValidationException::withMessages([
                'layout' => 'The canvas layout is too large to save.',
            ]);
        }

        $validator = Validator::make($layout, [
            'viewport' => ['nullable', 'array'],
            'viewport.x' => ['nullable', 'numeric'],
            'viewport.y' => ['nullable', 'numeric'],
            'viewport.zoom' => ['nullable', 'numeric', 'between:0.1,4'],
            'nodes' => ['nullable', 'array', 'max:'.self::MAX_NODES],
            'nodes.*' => ['array'],
            'nodes.*.collapsed' => ['nullable', 'boolean'],
            'nodes.*.expanded' => ['nullable', 'boolean'],
            'nodes.*.dx' => ['nullable', 'integer'],
            'nodes.*.dy' => ['nullable', 'integer'],
            'notes' => ['nullable', 'array', 'max:'.self::MAX_NOTES],
            'notes.*' => ['array'],
            'notes.*.id' => ['required', 'string', 'max:64'],
            'notes.*.x' => ['required', 'integer'],
            'notes.*.y' => ['required', 'integer'],
            'notes.*.w' => ['required', 'integer', 'min:40'],
            'notes.*.h' => ['required', 'integer', 'min:40'],
            'notes.*.text' => ['nullable', 'string', 'max:2000'],
            'notes.*.color' => ['required', 'string', 'in:'.implode(',', self::NOTE_COLORS)],
            'edges' => ['nullable', 'array', 'max:'.self::MAX_EDGES],
            'edges.*' => ['array'],
            'edges.*.id' => ['required', 'string', 'max:64'],
            'edges.*.from' => ['required', 'string', 'max:64'],
            'edges.*.to' => ['required', 'string', 'max:64'],
            'edges.*.label' => ['nullable', 'string', 'max:120'],
        ]);

        $validated = $validator->validate();

        return [
            'viewport' => [
                'x' => (float) ($validated['viewport']['x'] ?? 0),
                'y' => (float) ($validated['viewport']['y'] ?? 0),
                'zoom' => (float) ($validated['viewport']['zoom'] ?? 1),
            ],
            'nodes' => $validated['nodes'] ?? [],
            'notes' => array_values($validated['notes'] ?? []),
            'edges' => array_values($validated['edges'] ?? []),
        ];
    }
}
