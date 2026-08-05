<?php

use Illuminate\Support\Facades\Http;
use Modules\Clinical\Classes\Services\DiagnosisSearch\IcdAutocodeService;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Clinical']);
    Branch::factory()->default()->create();
});

function configureWhoIcd(): void
{
    config([
        'clinical.icd.client_id' => 'test-client',
        'clinical.icd.client_secret' => 'test-secret',
        'clinical.icd.token_url' => 'https://icdaccessmanagement.who.int/connect/token',
        'clinical.icd.base_url' => 'https://id.who.int',
        'clinical.icd.release_id' => '2026-01',
        'clinical.icd.linearization' => 'mms',
    ]);
}

it('returns null when the api is not configured', function (): void {
    config([
        'clinical.icd.client_id' => null,
        'clinical.icd.client_secret' => null,
    ]);

    expect(app(IcdAutocodeService::class)->suggest('Cholera'))->toBeNull();
});

it('returns null for blank or too-short text', function (): void {
    configureWhoIcd();

    $service = app(IcdAutocodeService::class);

    expect($service->suggest(''))->toBeNull()
        ->and($service->suggest('A'))->toBeNull();
});

it('returns the best matching who code for the given text', function (): void {
    configureWhoIcd();

    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ]),
        'id.who.int/*/mms/autocode*' => Http::response([
            'searchText' => 'Cholera',
            'matchingText' => 'Cholera',
            'theCode' => '1A00',
            'foundationURI' => 'http://id.who.int/icd/entity/123',
            'linearizationURI' => 'http://id.who.int/icd/release/11/2026-01/mms/1A00',
            'matchScore' => 0.98,
        ]),
    ]);

    $result = app(IcdAutocodeService::class)->suggest('Cholera');

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe('1A00')
        ->and($result->label)->toBe('Cholera')
        ->and($result->externalId)->toBe('123')
        ->and($result->source)->toBe('who')
        ->and($result->uri)->toStartWith('https://');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'mms/autocode')
        && str_contains($request->url(), 'searchText=Cholera'));
});

it('returns null when the api errors', function (): void {
    configureWhoIcd();

    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response(['error' => 'denied'], 401),
        '*' => Http::response([], 500),
    ]);

    expect(app(IcdAutocodeService::class)->suggest('Cholera'))->toBeNull();
});
