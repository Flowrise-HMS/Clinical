<?php

namespace Modules\Clinical\Filament\Components;

use Illuminate\View\View;
use Livewire\Component;
use Modules\Clinical\Classes\Services\DiagnosisSearch\CompositeDiagnosisCodeSearch;
use Modules\Clinical\Classes\Services\DiagnosisSearch\IcdBrowserService;
use Modules\Clinical\Classes\Services\IcdCatalogueService;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;

class IcdBrowser extends Component
{
    public string $searchTerm = '';

    /**
     * @var list<array{uri: ?string, id: ?string, code: ?string, label: string, class_kind: ?string, has_children: bool}>
     */
    public array $entries = [];

    /**
     * @var list<string>
     */
    public array $breadcrumbs = [];

    /**
     * @var list<string>
     */
    public array $breadcrumbUris = [];

    public function mount(): void
    {
        $this->loadChildren();
    }

    public function updatedSearchTerm(): void
    {
        $this->search();
    }

    public function search(): void
    {
        $term = trim($this->searchTerm);

        if (strlen($term) < 2) {
            $this->loadChildren();

            return;
        }

        $results = app(CompositeDiagnosisCodeSearch::class)->search($term, 25);

        $this->entries = $results
            ->map(fn (DiagnosisCodeSearchResult $result): array => $this->searchEntry($result))
            ->all();
        $this->breadcrumbs = [];
        $this->breadcrumbUris = [];
    }

    public function loadChildren(?string $parentUri = null): void
    {
        $this->searchTerm = '';

        $this->entries = app(IcdBrowserService::class)->children($parentUri);
    }

    public function drill(string $uri, string $label): void
    {
        $this->breadcrumbs[] = $label;
        $this->breadcrumbUris[] = $uri;
        $this->loadChildren($uri);
    }

    public function goToRoot(): void
    {
        $this->breadcrumbs = [];
        $this->breadcrumbUris = [];
        $this->loadChildren();
    }

    public function goToBreadcrumb(int $index): void
    {
        $this->breadcrumbs = array_slice($this->breadcrumbs, 0, $index);
        $this->breadcrumbUris = array_slice($this->breadcrumbUris, 0, $index);

        $parentUri = $this->breadcrumbUris[$index - 1] ?? null;

        $this->loadChildren($parentUri);
    }

    public function selectEntry(string $uri): void
    {
        $entry = collect($this->entries)
            ->first(fn (array $e): bool => ($e['uri'] ?? null) === $uri);

        if (! is_array($entry)) {
            return;
        }

        $result = new DiagnosisCodeSearchResult(
            localId: null,
            code: $entry['code'],
            label: $entry['label'],
            externalId: $entry['id'],
            uri: $entry['uri'],
            source: 'who',
        );

        $localId = app(IcdCatalogueService::class)->localId($result);

        $this->dispatch('icd-diagnosis-selected', suggestion: $result->toArray(), localId: $localId);
    }

    /**
     * @return array{uri: ?string, id: ?string, code: ?string, label: string, class_kind: null, has_children: bool}
     */
    protected function searchEntry(DiagnosisCodeSearchResult $result): array
    {
        return [
            'uri' => $result->uri,
            'id' => $result->externalId,
            'code' => $result->code,
            'label' => $result->label,
            'class_kind' => null,
            'has_children' => false,
        ];
    }

    public function render(): View
    {
        return view('clinical::filament.components.icd-browser');
    }
}
