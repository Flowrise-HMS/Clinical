<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Throwable;

class WhoIcdDiagnosisCodeSearch implements DiagnosisCodeSearchContract
{
    public function search(string $term, int $limit = 15): Collection
    {
        if (blank($term) || strlen($term) < 2) {
            return collect();
        }

        if (! $this->isConfigured()) {
            return collect();
        }

        try {
            $token = $this->accessToken();

            if ($token === null) {
                return collect();
            }

            $response = Http::timeout($this->timeout())
                ->withToken($token)
                ->acceptJson()
                ->withHeaders([
                    'Accept-Language' => (string) config('clinical.icd.language', 'en'),
                    'API-Version' => (string) config('clinical.icd.api_version', 'v2'),
                ])
                ->get($this->searchUrl(), [
                    'q' => $term,
                    'flatResults' => 'true',
                ]);

            if (! $response->successful()) {
                return collect();
            }

            /** @var array<int, array<string, mixed>> $entities */
            $entities = $response->json('destinationEntities') ?? [];

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

    public function isConfigured(): bool
    {
        return filled(config('clinical.icd.client_id'))
            && filled(config('clinical.icd.client_secret'));
    }

    /**
     * OAuth2 client-credentials token using HTTP Basic Auth (WHO ICD API Authentication docs).
     */
    protected function accessToken(): ?string
    {
        $cacheKey = 'clinical_icd_access_token';

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('clinical.icd.client_id');
        $clientSecret = (string) config('clinical.icd.client_secret');

        $response = Http::asForm()
            ->timeout($this->timeout())
            ->withBasicAuth($clientId, $clientSecret)
            ->post((string) config('clinical.icd.token_url'), [
                'grant_type' => 'client_credentials',
                'scope' => (string) config('clinical.icd.scope', 'icdapi_access'),
            ]);

        if (! $response->successful()) {
            Log::warning('WHO ICD token request failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $token = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);

        if (! is_string($token) || $token === '') {
            return null;
        }

        // Tokens are valid ~1 hour; refresh slightly early.
        Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    /**
     * Prefer MMS linearization search so results include ICD codes (WHO API v2 guidance).
     */
    protected function searchUrl(): string
    {
        $base = rtrim((string) config('clinical.icd.base_url', 'https://id.who.int'), '/');
        $releaseId = trim((string) config('clinical.icd.release_id', '2026-01'), '/');
        $linearization = trim((string) config('clinical.icd.linearization', 'mms'), '/');

        return "{$base}/icd/release/11/{$releaseId}/{$linearization}/search";
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
            uri: $uri !== null ? str_replace('http://', 'https://', $uri) : null,
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

    protected function timeout(): int
    {
        return (int) config('clinical.icd.timeout', 5);
    }
}
