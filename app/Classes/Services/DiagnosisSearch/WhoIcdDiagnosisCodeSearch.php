<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Throwable;

class WhoIcdDiagnosisCodeSearch implements DiagnosisCodeSearchContract
{
    public function __construct(
        protected WhoIcdApiClient $client,
    ) {}

    public function search(string $term, int $limit = 15): Collection
    {
        if (blank($term) || strlen($term) < 2) {
            return collect();
        }

        if (! $this->client->isConfigured()) {
            return collect();
        }

        try {
            $response = $this->client->get($this->client->linearizationEndpoint('search'), [
                'q' => $term,
                'flatResults' => 'true',
            ]);

            if ($response['status'] !== 200) {
                return collect();
            }

            /** @var array<int, array<string, mixed>> $entities */
            $entities = $response['body']['destinationEntities'] ?? [];

            return collect($entities)
                ->take($limit)
                ->map(fn (array $entity): ?DiagnosisCodeSearchResult => $this->mapEntity($entity))
                ->filter()
                ->values();
        } catch (Throwable $e) {
            Log::warning('WHO ICD search failed', [
                'term' => $term,
                'message' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function mapEntity(array $entity): ?DiagnosisCodeSearchResult
    {
        $uri = is_string($entity['id'] ?? null) ? $entity['id'] : null;
        $title = $this->extractLabel(
            $entity['title']
            ?? $entity['prefLabel']
            ?? $entity['http://www.w3.org/2004/02/skos/core#prefLabel']
            ?? null
        );
        $code = $this->extractCode($entity);
        $externalId = $uri !== null ? basename(parse_url($uri, PHP_URL_PATH) ?: $uri) : null;

        if (blank($title)) {
            return null;
        }

        return new DiagnosisCodeSearchResult(
            localId: null,
            code: $code,
            label: strip_tags($title),
            externalId: $externalId,
            uri: $this->client->rewriteUri($uri),
            source: 'who',
        );
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function extractCode(array $entity): ?string
    {
        foreach (['theCode', 'code', 'http://id.who.int/icd/schema/code'] as $key) {
            $value = $entity[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_array($value) && isset($value['@value']) && is_string($value['@value'])) {
                return $value['@value'];
            }
        }

        return null;
    }

    protected function extractLabel(mixed $title): ?string
    {
        if (is_string($title)) {
            return $title;
        }

        if (! is_array($title)) {
            return null;
        }

        if (isset($title['@value']) && is_string($title['@value'])) {
            return $title['@value'];
        }

        // Language map: { "en": "Cholera" } or { "en": [{ "@value": "Cholera" }] }
        $language = (string) config('clinical.icd.language', 'en');
        $localized = $title[$language] ?? $title['en'] ?? null;

        if (is_string($localized)) {
            return $localized;
        }

        if (is_array($localized)) {
            if (isset($localized['@value']) && is_string($localized['@value'])) {
                return $localized['@value'];
            }

            $first = $localized[0] ?? null;
            if (is_string($first)) {
                return $first;
            }
            if (is_array($first) && isset($first['@value']) && is_string($first['@value'])) {
                return $first['@value'];
            }
        }

        return null;
    }
}
