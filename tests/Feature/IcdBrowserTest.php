<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Pages\IcdBrowserPage;
use Modules\Clinical\Filament\Components\IcdBrowser;
use Modules\Clinical\Models\DiagnosisCode;
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

    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ]),
        'id.who.int/*/mms' => Http::response([
            'child' => ['http://id.who.int/icd/release/11/2026-01/mms/1'],
        ]),
        'id.who.int/*/mms/1' => Http::response([
            '@id' => 'http://id.who.int/icd/release/11/2026-01/mms/1',
            'title' => ['@value' => 'Certain infectious or parasitic diseases'],
            'code' => null,
            'classKind' => 'chapter',
            'child' => ['http://id.who.int/icd/release/11/2026-01/mms/2A00'],
        ]),
        'id.who.int/*/mms/2A00' => Http::response([
            '@id' => 'http://id.who.int/icd/release/11/2026-01/mms/2A00',
            'title' => ['@value' => 'Cholera'],
            'code' => '2A00',
            'classKind' => 'category',
            'child' => [],
        ]),
        'id.who.int/*/mms/search*' => Http::response([
            'destinationEntities' => [
                [
                    'id' => 'http://id.who.int/icd/entity/123',
                    'theCode' => '2A00',
                    'title' => ['@value' => 'Cholera'],
                ],
            ],
        ]),
    ]);
});

it('loads the chapters on mount', function (): void {
    Livewire::test(IcdBrowser::class)
        ->assertSet('entries.0.label', 'Certain infectious or parasitic diseases')
        ->assertSet('entries.0.has_children', true);
});

it('searches the who mms linearization when a term is typed', function (): void {
    Livewire::test(IcdBrowser::class)
        ->set('searchTerm', 'Cholera')
        ->assertSet('entries.0.code', '2A00')
        ->assertSet('entries.0.label', 'Cholera')
        ->assertSet('breadcrumbs', []);
});

it('drills down into a child node', function (): void {
    Livewire::test(IcdBrowser::class)
        ->call('drill', 'https://id.who.int/icd/release/11/2026-01/mms/1', 'Certain infectious or parasitic diseases')
        ->assertSet('entries.0.code', '2A00')
        ->assertSet('entries.0.label', 'Cholera')
        ->assertSet('breadcrumbs.0', 'Certain infectious or parasitic diseases');
});

it('selecting an entry dispatches the selection and caches it locally', function (): void {
    Livewire::test(IcdBrowser::class)
        ->call('drill', 'https://id.who.int/icd/release/11/2026-01/mms/1', 'Certain infectious or parasitic diseases')
        ->call('selectEntry', 'https://id.who.int/icd/release/11/2026-01/mms/2A00')
        ->assertDispatched('icd-diagnosis-selected');

    $cached = DiagnosisCode::where('source', 'who')->where('icd_entity_id', '2A00')->first();

    expect($cached)->not->toBeNull()
        ->and($cached->code)->toBe('2A00')
        ->and($cached->description)->toBe('Cholera');
});

it('renders the icd browser embedded in the standalone page via @livewire', function (): void {
    Livewire::test(IcdBrowserPage::class)
        ->assertOk()
        ->assertSee('ICD-11 Browser')
        ->assertSee('Search ICD-11');
});
