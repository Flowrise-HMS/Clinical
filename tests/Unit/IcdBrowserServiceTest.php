<?php

use Illuminate\Support\Facades\Http;
use Modules\Clinical\Classes\Services\DiagnosisSearch\IcdBrowserService;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Clinical']);
    Branch::factory()->default()->create();

    config([
        'clinical.icd.client_id' => 'test-client',
        'clinical.icd.client_secret' => 'test-secret',
        'clinical.icd.token_url' => 'https://icdaccessmanagement.who.int/connect/token',
        'clinical.icd.base_url' => 'https://id.who.int',
        'clinical.icd.release_id' => '2026-01',
        'clinical.icd.linearization' => 'mms',
    ]);
});

function browserFake(): void
{
    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ]),
        'id.who.int/*/mms' => Http::response([
            'child' => [
                'http://id.who.int/icd/release/11/2026-01/mms/1',
                'http://id.who.int/icd/release/11/2026-01/mms/2',
            ],
        ]),
        'id.who.int/*/mms/1' => Http::response([
            '@id' => 'http://id.who.int/icd/release/11/2026-01/mms/1',
            'title' => ['@value' => 'Certain infectious or parasitic diseases'],
            'code' => null,
            'classKind' => 'chapter',
            'child' => ['http://id.who.int/icd/release/11/2026-01/mms/1A00'],
        ]),
        'id.who.int/*/mms/2' => Http::response([
            '@id' => 'http://id.who.int/icd/release/11/2026-01/mms/2',
            'title' => ['@value' => 'Neoplasms'],
            'code' => null,
            'classKind' => 'chapter',
            'child' => [],
        ]),
        'id.who.int/*/mms/1A00' => Http::response([
            '@id' => 'http://id.who.int/icd/release/11/2026-01/mms/1A00',
            'title' => ['@value' => 'Cholera'],
            'code' => '1A00',
            'classKind' => 'category',
            'child' => [],
        ]),
    ]);
}

it('returns the chapters at the root level', function (): void {
    browserFake();

    $entries = app(IcdBrowserService::class)->children(null);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['code'])->toBeNull()
        ->and($entries[0]['label'])->toBe('Certain infectious or parasitic diseases')
        ->and($entries[0]['class_kind'])->toBe('chapter')
        ->and($entries[0]['has_children'])->toBeTrue()
        ->and($entries[1]['label'])->toBe('Neoplasms')
        ->and($entries[1]['has_children'])->toBeFalse();
});

it('returns child categories when a node is expanded', function (): void {
    browserFake();

    $entries = app(IcdBrowserService::class)->children('https://id.who.int/icd/release/11/2026-01/mms/1');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['code'])->toBe('1A00')
        ->and($entries[0]['label'])->toBe('Cholera')
        ->and($entries[0]['class_kind'])->toBe('category');
});

it('returns empty when the api is not configured', function (): void {
    config([
        'clinical.icd.client_id' => null,
        'clinical.icd.client_secret' => null,
    ]);

    expect(app(IcdBrowserService::class)->children(null))->toBeEmpty();
});

it('returns empty when the api errors', function (): void {
    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response(['error' => 'denied'], 401),
        '*' => Http::response([], 500),
    ]);

    expect(app(IcdBrowserService::class)->children(null))->toBeEmpty();
});
