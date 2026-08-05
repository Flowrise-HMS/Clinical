<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

use Illuminate\Support\Facades\Log;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Throwable;

class IcdAutocodeService
{
    public function __construct(
        protected WhoIcdApiClient $client,
    ) {}

    public function suggest(string $text): ?DiagnosisCodeSearchResult
    {
        if (blank($text) || strlen(trim($text)) < 2) {
            return null;
        }

        if (! $this->client->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client->get($this->client->linearizationEndpoint('autocode'), [
                'searchText' => $text,
            ]);

            if ($response['status'] !== 200 || ! is_array($response['body'])) {
                return null;
            }

            return $this->mapResponse($response['body']);
        } catch (Throwable $e) {
            Log::warning('WHO ICD autocode failed', [
                'text' => $text,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function mapResponse(array $body): ?DiagnosisCodeSearchResult
    {
        $code = is_string($body['theCode'] ?? null) ? $body['theCode'] : null;
        $label = is_string($body['matchingText'] ?? null) ? $body['matchingText'] : null;
        $foundationUri = is_string($body['foundationURI'] ?? null) ? $body['foundationURI'] : null;
        $linearizationUri = is_string($body['linearizationURI'] ?? null) ? $body['linearizationURI'] : null;

        if (blank($code) && blank($foundationUri)) {
            return null;
        }

        return new DiagnosisCodeSearchResult(
            localId: null,
            code: $code,
            label: strip_tags((string) $label),
            externalId: $foundationUri !== null
                ? basename(parse_url($foundationUri, PHP_URL_PATH) ?: $foundationUri)
                : null,
            uri: $this->client->rewriteUri($linearizationUri ?? $foundationUri),
            source: 'who',
        );
    }
}
