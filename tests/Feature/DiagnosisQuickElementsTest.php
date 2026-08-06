<?php

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Component;
use Livewire\Livewire;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas\EncounterDiagnosisForm;
use Modules\Clinical\Models\DiagnosisCode;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    resetDiagnosisSearchResultCache();
});

function resetDiagnosisSearchResultCache(): void
{
    $property = (new ReflectionClass(EncounterDiagnosisForm::class))->getProperty('searchResultCache');

    $property->setValue(null, []);
}

function diagnosisQuickElements(): array
{
    return collect(EncounterDiagnosisForm::itemElements())
        ->filter(fn (object $component): bool => method_exists($component, 'getName'))
        ->keyBy(fn (object $component): string => (string) $component->getName())
        ->all();
}

it('exposes a single searchable diagnosis search field', function (): void {
    $elements = diagnosisQuickElements();

    expect($elements)->toHaveKey('code_search')
        ->and($elements['code_search']->getLabel())->toBe('Search Diagnosis')
        ->and($elements['code_search']->isSearchable())->toBeTrue();
});

it('hides the icd code fields and removes the legacy nhis selector', function (): void {
    $elements = diagnosisQuickElements();

    foreach (['diagnosis_code_id', 'icd_entity_id', 'icd_uri', 'icd_code', 'icd10_code'] as $hiddenField) {
        expect($elements)->toHaveKey($hiddenField)
            ->and($elements[$hiddenField]->isHidden())->toBeTrue();
    }

    expect($elements)->toHaveKey('description')
        ->and($elements['description']->isHidden())->toBeFalse()
        ->and($elements)->not->toHaveKey('local_icd10_search');
});

it('searches the local catalogue by name and icd-10 code when who is unreachable', function (): void {
    $malaria = DiagnosisCode::factory()->create([
        'code' => 'B54',
        'description' => 'Unspecified malaria',
        'source' => 'nhis',
        'nhis_covered' => true,
    ]);

    DiagnosisCode::factory()->create([
        'code' => 'A00.0',
        'description' => 'Cholera',
        'source' => 'who',
        'icd_entity_id' => 'a00-cholera-entity',
    ]);

    $byName = app(DiagnosisCodeSearchContract::class)->search('mal', limit: 15);

    expect($byName)->not->toBeEmpty()
        ->and($byName->pluck('code'))->toContain('B54')
        ->and($byName->firstWhere('localId', $malaria->id)->optionKey())->toBe('local:'.$malaria->id);

    $byCode = app(DiagnosisCodeSearchContract::class)->search('A00', limit: 15);

    expect($byCode)->not->toBeEmpty()
        ->and($byCode->pluck('code'))->toContain('A00.0');
});

it('autofills the hidden code fields when a local diagnosis is selected', function (): void {
    $vivax = DiagnosisCode::factory()->create([
        'code' => 'B53',
        'description' => 'Malaria (Plasmodium vivax)',
        'source' => 'nhis',
        'nhis_covered' => true,
    ]);

    $livewire = Livewire::test(DiagnosisQuickElementHarness::class);
    $search = $livewire->instance()->getSchema('diagnosis')->getComponent('code_search');

    $results = $search->getSearchResults('vivax');

    expect($results)->toHaveKey('local:'.$vivax->id);

    $livewire->set('data.code_search', 'local:'.$vivax->id);

    $livewire->assertSet('data.diagnosis_code_id', $vivax->id)
        ->assertSet('data.icd_entity_id', null)
        ->assertSet('data.icd_uri', null)
        ->assertSet('data.icd_code', 'B53')
        ->assertSet('data.icd10_code', 'B53')
        ->assertSet('data.description', 'Malaria (Plasmodium vivax)');
});

it('resolves a persisted who selection to a friendly label on rehydration', function (): void {
    $code = 'QA'.fake()->unique()->numerify('##');

    DiagnosisCode::factory()->create([
        'code' => $code,
        'description' => 'Cholera',
        'source' => 'who',
        'icd_entity_id' => strtolower($code).'-cholera-entity',
        'icd_uri' => 'https://id.who.int/icd/entity/'.strtolower($code).'-cholera-entity',
    ]);

    $livewire = Livewire::test(DiagnosisQuickElementHarness::class);
    $search = $livewire->instance()->getSchema('diagnosis')->getComponent('code_search');

    $search->state('who:'.strtolower($code).'-cholera-entity');

    expect($search->getOptionLabel())->toBe($code.' - Cholera');
});

it('resolves a persisted local selection to a friendly label on rehydration', function (): void {
    $vivax = DiagnosisCode::factory()->create([
        'code' => 'B52',
        'description' => 'Malaria (Plasmodium malariae)',
        'source' => 'nhis',
        'nhis_covered' => true,
    ]);

    $livewire = Livewire::test(DiagnosisQuickElementHarness::class);
    $search = $livewire->instance()->getSchema('diagnosis')->getComponent('code_search');

    $search->state('local:'.$vivax->id);

    expect($search->getOptionLabel())->toBe('B52 - Malaria (Plasmodium malariae)');
});

class DiagnosisQuickElementHarness extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public array $data = [];

    public function mount(): void
    {
        $this->getSchema('diagnosis');
    }

    public function diagnosisSchema(Schema $schema): Schema
    {
        return $schema
            ->schema(EncounterDiagnosisForm::itemElements())
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
