<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhoIcdApiClient
{
    public function isConfigured(): bool
    {
        return filled(config('clinical.icd.client_id'))
            && filled(config('clinical.icd.client_secret'));
    }

    /**
     * OAuth2 client-credentials token using HTTP Basic Auth (WHO ICD API Authentication docs).
     */
    public function accessToken(): ?string
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

    public function timeout(): int
    {
        return (int) config('clinical.icd.timeout', 5);
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('clinical.icd.base_url', 'https://id.who.int'), '/');
    }

    public function releaseId(): string
    {
        return trim((string) config('clinical.icd.release_id', '2026-01'), '/');
    }

    public function linearization(): string
    {
        return trim((string) config('clinical.icd.linearization', 'mms'), '/');
    }

    /**
     * MMS linearization endpoint URL, e.g. {base}/icd/release/11/{release}/mms/search.
     */
    public function linearizationEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint, '/');

        return "{$this->linearizationRoot()}/{$endpoint}";
    }

    /**
     * Root of the MMS linearization, e.g. {base}/icd/release/11/{release}/mms.
     */
    public function linearizationRoot(): string
    {
        return "{$this->baseUrl()}/icd/release/11/{$this->releaseId()}/{$this->linearization()}";
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Accept-Language' => (string) config('clinical.icd.language', 'en'),
            'API-Version' => (string) config('clinical.icd.api_version', 'v2'),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: mixed}
     */
    public function get(string $url, array $query = []): array
    {
        $token = $this->accessToken();

        if ($token === null) {
            return ['status' => 401, 'body' => null];
        }

        $response = Http::timeout($this->timeout())
            ->withToken($token)
            ->acceptJson()
            ->withHeaders($this->headers())
            ->get($url, $query);

        return ['status' => $response->status(), 'body' => $response->json()];
    }

    public function rewriteUri(?string $uri): ?string
    {
        if (! is_string($uri) || $uri === '') {
            return null;
        }

        return str_replace('http://', 'https://', $uri);
    }
}
