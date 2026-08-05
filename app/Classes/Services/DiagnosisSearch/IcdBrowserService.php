<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

class IcdBrowserService
{
    public function __construct(
        protected WhoIcdApiClient $client,
    ) {}

    /**
     * Resolve the child entities of a node, or the chapters when $parentUri is null.
     *
     * @return list<array{uri: string, id: string, code: ?string, label: string, class_kind: ?string, has_children: bool}>
     */
    public function children(?string $parentUri = null): array
    {
        if (! $this->client->isConfigured()) {
            return [];
        }

        $childUris = $this->childUris($parentUri);

        if ($childUris === null) {
            return [];
        }

        return collect($childUris)
            ->map(fn (mixed $uri): ?array => is_string($uri) ? $this->resolveEntity($uri) : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>|null
     */
    protected function childUris(?string $parentUri): ?array
    {
        if ($parentUri === null) {
            $response = $this->client->get($this->client->linearizationRoot());

            if ($response['status'] !== 200 || ! is_array($response['body'])) {
                return null;
            }

            $child = $response['body']['child'] ?? null;

            return is_array($child) ? array_values(array_filter($child, 'is_string')) : [];
        }

        $id = basename(parse_url($parentUri, PHP_URL_PATH) ?: $parentUri);
        $response = $this->client->get($this->client->linearizationEndpoint($id));

        if ($response['status'] !== 200 || ! is_array($response['body'])) {
            return null;
        }

        $child = $response['body']['child'] ?? null;

        return is_array($child) ? array_values(array_filter($child, 'is_string')) : [];
    }

    /**
     * @return array{uri: string, id: string, code: ?string, label: string, class_kind: ?string, has_children: bool}|null
     */
    protected function resolveEntity(string $uri): ?array
    {
        $id = basename(parse_url($uri, PHP_URL_PATH) ?: $uri);
        $response = $this->client->get($this->client->linearizationEndpoint($id));

        if ($response['status'] !== 200 || ! is_array($response['body'])) {
            return null;
        }

        $body = $response['body'];
        $label = $this->extractLabel($body['title'] ?? null);

        return [
            'uri' => $this->client->rewriteUri($uri) ?? $uri,
            'id' => $id,
            'code' => is_string($body['code'] ?? null) ? $body['code'] : null,
            'label' => $label ?? $id,
            'class_kind' => is_string($body['classKind'] ?? null) ? $body['classKind'] : null,
            'has_children' => filled($body['child'] ?? null),
        ];
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
