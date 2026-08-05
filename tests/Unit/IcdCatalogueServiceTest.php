<?php

use Modules\Clinical\Classes\Services\IcdCatalogueService;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Clinical']);
    Branch::factory()->default()->create();
});

function whoResult(?string $code = '1A00', ?string $externalId = '123'): DiagnosisCodeSearchResult
{
    return new DiagnosisCodeSearchResult(
        localId: null,
        code: $code,
        label: 'Cholera',
        externalId: $externalId,
        uri: $externalId !== null ? "https://id.who.int/icd/entity/{$externalId}" : null,
        source: 'who',
    );
}

it('caches a who search result into the local catalogue on selection', function (): void {
    $code = app(IcdCatalogueService::class)->remember(whoResult());

    expect($code)->not->toBeNull()
        ->and($code->code)->toBe('1A00')
        ->and($code->description)->toBe('Cholera')
        ->and($code->icd_entity_id)->toBe('123')
        ->and($code->icd_uri)->toBe('https://id.who.int/icd/entity/123')
        ->and($code->source)->toBe('who')
        ->and($code->is_active)->toBeTrue();
});

it('is idempotent when the same who entity is selected again', function (): void {
    $service = app(IcdCatalogueService::class);
    $result = whoResult();

    $first = $service->remember($result);
    $second = $service->remember($result);

    expect($second->id)->toBe($first->id)
        ->and(DiagnosisCode::where('source', 'who')->where('icd_entity_id', '123')->count())->toBe(1);
});

it('returns the cached catalogue id for a who result', function (): void {
    $id = app(IcdCatalogueService::class)->localId(whoResult());

    expect($id)->toBeString()
        ->and(DiagnosisCode::find($id))->not->toBeNull();
});

it('does not cache a result that has no code uri or entity id', function (): void {
    $result = new DiagnosisCodeSearchResult(
        localId: null,
        code: null,
        label: 'Custom note',
        source: 'custom',
    );

    expect(app(IcdCatalogueService::class)->remember($result))->toBeNull();
});
