<?php

namespace Modules\Clinical\Data;

readonly class DiagnosisCodeSearchResult
{
    public function __construct(
        public ?string $localId,
        public ?string $code,
        public string $label,
        public ?string $externalId = null,
        public ?string $uri = null,
        public string $source = 'local',
    ) {}

    public function optionKey(): string
    {
        if (filled($this->localId)) {
            return 'local:'.$this->localId;
        }

        if (filled($this->externalId)) {
            return 'who:'.$this->externalId;
        }

        if (filled($this->uri)) {
            return 'uri:'.md5($this->uri);
        }

        return 'custom:'.md5($this->label);
    }

    public function optionLabel(): string
    {
        return filled($this->code)
            ? $this->code.' - '.$this->label
            : $this->label;
    }

    /**
     * @return array{local_id: ?string, code: ?string, label: string, external_id: ?string, uri: ?string, source: string}
     */
    public function toArray(): array
    {
        return [
            'local_id' => $this->localId,
            'code' => $this->code,
            'label' => $this->label,
            'external_id' => $this->externalId,
            'uri' => $this->uri,
            'source' => $this->source,
        ];
    }
}
