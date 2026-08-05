<?php

use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\Livewire;
use Modules\Clinical\Filament\Concerns\CanAutocodeIcd;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

class IcdAutocodeTestComponent extends Component
{
    use CanAutocodeIcd;

    public function render(): string
    {
        return <<<'HTML'
        <div>
            <input type="text" wire:model="autocodeText">
            <span>{{ $this->autocodeSuggestion['code'] ?? '' }}</span>
            <span>{{ $this->autocodeSuggestion['label'] ?? '' }}</span>
        </div>
        HTML;
    }
}

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
        'id.who.int/*/mms/autocode*' => Http::response([
            'matchingText' => 'Cholera',
            'theCode' => '1A00',
            'foundationURI' => 'http://id.who.int/icd/entity/123',
            'linearizationURI' => 'http://id.who.int/icd/release/11/2026-01/mms/1A00',
        ]),
    ]);
});

it('suggests a code as the description text changes', function (): void {
    Livewire::test(IcdAutocodeTestComponent::class)
        ->set('autocodeText', 'Cholera')
        ->assertSet('autocodeSuggestion.code', '1A00')
        ->assertSet('autocodeSuggestion.label', 'Cholera')
        ->assertSet('autocodeSuggestion.source', 'who');
});

it('keeps the suggestion empty for short text', function (): void {
    Livewire::test(IcdAutocodeTestComponent::class)
        ->set('autocodeText', 'A')
        ->assertSet('autocodeSuggestion', null);
});

it('clears the suggestion via the clear method', function (): void {
    Livewire::test(IcdAutocodeTestComponent::class)
        ->set('autocodeText', 'Cholera')
        ->assertSet('autocodeSuggestion.code', '1A00')
        ->call('clearIcdAutocode')
        ->assertSet('autocodeSuggestion', null)
        ->assertSet('autocodeText', '');
});

it('accepts and resets the suggestion', function (): void {
    Livewire::test(IcdAutocodeTestComponent::class)
        ->set('autocodeText', 'Cholera')
        ->assertSet('autocodeSuggestion.code', '1A00')
        ->call('acceptIcdAutocode')
        ->assertSet('autocodeSuggestion', null)
        ->assertSet('autocodeText', '');
});
