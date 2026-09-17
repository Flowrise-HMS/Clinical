<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Classes\Services\CanvasLayoutService;
use Modules\Clinical\Models\CanvasLayout;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $branch->id])
    );
    $this->user = User::factory()->create(['branch_id' => $branch->id]);
    $this->service = app(CanvasLayoutService::class);
});

function validCanvasLayout(): array
{
    return [
        'viewport' => ['x' => 12.5, 'y' => -40, 'zoom' => 0.8],
        'nodes' => [
            'encounter_1' => ['collapsed' => false, 'expanded' => true, 'dx' => 12, 'dy' => -4],
            'note_2' => ['collapsed' => true],
        ],
        'notes' => [
            ['id' => 'n1', 'x' => 100, 'y' => 100, 'w' => 200, 'h' => 120, 'text' => 'Review labs', 'color' => 'amber'],
        ],
        'edges' => [
            ['id' => 'e1', 'from' => 'encounter_1', 'to' => 'n1', 'label' => 'why'],
        ],
    ];
}

it('saves and reads back a layout for the owning user', function (): void {
    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, validCanvasLayout());

    $stored = $this->service->get($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id);

    expect($stored)->not->toBeNull()
        ->and($stored['viewport']['zoom'])->toBe(0.8)
        ->and($stored['nodes']['note_2']['collapsed'])->toBeTrue()
        ->and($stored['notes'][0]['text'])->toBe('Review labs')
        ->and($stored['edges'][0]['to'])->toBe('n1');
});

it('updates in place instead of creating duplicate rows', function (): void {
    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, validCanvasLayout());

    $updated = validCanvasLayout();
    $updated['viewport']['zoom'] = 1.5;
    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, $updated);

    expect(CanvasLayout::query()->count())->toBe(1)
        ->and($this->service->get($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id)['viewport']['zoom'])->toBe(1.5);
});

it('scopes layouts to the user, canvas and context', function (): void {
    $otherUser = User::factory()->create();
    $encounterId = (string) Str::uuid();

    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, validCanvasLayout());
    $this->service->save($this->user, CanvasLayout::KEY_MEDICATIONS, $this->patient->id, $encounterId, validCanvasLayout());

    expect($this->service->get($otherUser, CanvasLayout::KEY_TIMELINE, $this->patient->id))->toBeNull()
        ->and($this->service->get($this->user, CanvasLayout::KEY_MEDICATIONS, $this->patient->id))->toBeNull()
        ->and($this->service->get($this->user, CanvasLayout::KEY_MEDICATIONS, $this->patient->id, $encounterId))->not->toBeNull();
});

it('resets a layout', function (): void {
    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, validCanvasLayout());

    $this->service->reset($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id);

    expect($this->service->get($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id))->toBeNull();
});

it('rejects an unknown sticky note colour', function (): void {
    $layout = validCanvasLayout();
    $layout['notes'][0]['color'] = 'neon';

    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, $layout);
})->throws(ValidationException::class);

it('rejects more nodes than the cap', function (): void {
    $layout = validCanvasLayout();
    $layout['nodes'] = [];

    for ($i = 0; $i <= CanvasLayoutService::MAX_NODES; $i++) {
        $layout['nodes']["node_{$i}"] = ['collapsed' => true];
    }

    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, $layout);
})->throws(ValidationException::class);

it('rejects an oversized payload', function (): void {
    $layout = validCanvasLayout();
    $layout['notes'][0]['text'] = str_repeat('x', CanvasLayoutService::MAX_PAYLOAD_BYTES + 1);

    $this->service->save($this->user, CanvasLayout::KEY_TIMELINE, $this->patient->id, null, $layout);
})->throws(ValidationException::class);
