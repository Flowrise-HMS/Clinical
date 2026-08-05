<?php

use Illuminate\Support\Facades\Http;
use Modules\Clinical\Classes\Services\DiagnosisSearch\CompositeDiagnosisCodeSearch;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Clinical']);
    Branch::factory()->default()->create();

    DiagnosisCode::query()->delete();
});

it('falls back to local diagnosis search when who api is not configured', function (): void {
    config([
        'clinical.icd.client_id' => null,
        'clinical.icd.client_secret' => null,
    ]);

    $code = DiagnosisCode::factory()->create([
        'code' => 'A00',
        'description' => 'Cholera',
        'is_active' => true,
    ]);

    $results = app(CompositeDiagnosisCodeSearch::class)->search('Cholera', 10);

    expect($results)->not->toBeEmpty()
        ->and($results->first()->localId)->toBe($code->id)
        ->and($results->first()->source)->toBe('local');
});

it('uses who mms results when the live api responds', function (): void {
    config([
        'clinical.icd.client_id' => 'test-client',
        'clinical.icd.client_secret' => 'test-secret',
        'clinical.icd.token_url' => 'https://icdaccessmanagement.who.int/connect/token',
        'clinical.icd.base_url' => 'https://id.who.int',
        'clinical.icd.release_id' => '2026-01',
        'clinical.icd.linearization' => 'mms',
    ]);

    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ]),
        'id.who.int/*/mms/search*' => Http::response([
            'destinationEntities' => [
                [
                    'id' => 'http://id.who.int/icd/entity/123',
                    'theCode' => '1A00',
                    'title' => ['@value' => 'Cholera'],
                ],
            ],
        ]),
    ]);

    $results = app(CompositeDiagnosisCodeSearch::class)->search('Cholera', 10);

    expect($results)->not->toBeEmpty()
        ->and($results->first()->source)->toBe('who')
        ->and($results->first()->code)->toBe('1A00')
        ->and($results->first()->externalId)->toBe('123')
        ->and($results->first()->uri)->toStartWith('https://');

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'connect/token')) {
            return false;
        }

        return $request->hasHeader('Authorization')
            && str_starts_with((string) $request->header('Authorization')[0], 'Basic ');
    });
});

it('falls back to local when who api errors', function (): void {
    config([
        'clinical.icd.client_id' => 'test-client',
        'clinical.icd.client_secret' => 'test-secret',
    ]);

    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response(['error' => 'denied'], 401),
        '*' => Http::response([], 500),
    ]);

    $code = DiagnosisCode::factory()->create([
        'code' => 'B50',
        'description' => 'Malaria',
        'is_active' => true,
    ]);

    $results = app(CompositeDiagnosisCodeSearch::class)->search('Malaria', 10);

    expect($results->first()?->localId)->toBe($code->id)
        ->and($results->first()?->source)->toBe('local');
});
