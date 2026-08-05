<?php

namespace Modules\Clinical\Filament\Concerns;

use Modules\Clinical\Classes\Services\DiagnosisSearch\IcdAutocodeService;

trait CanAutocodeIcd
{
    public string $autocodeText = '';

    /**
     * @var array{local_id: ?string, code: ?string, label: string, external_id: ?string, uri: ?string, source: string}|null
     */
    public ?array $autocodeSuggestion = null;

    public function updatedAutocodeText(): void
    {
        $this->suggestIcdCode();
    }

    public function suggestIcdCode(?string $text = null): void
    {
        $this->autocodeSuggestion = null;

        $text ??= $this->autocodeText;

        if (blank($text) || strlen(trim($text)) < 2) {
            return;
        }

        $result = app(IcdAutocodeService::class)->suggest($text);

        $this->autocodeSuggestion = $result?->toArray();
    }

    public function clearIcdAutocode(): void
    {
        $this->autocodeSuggestion = null;
        $this->autocodeText = '';
    }

    /**
     * Return the current suggestion and reset the autocode state.
     *
     * @return array{local_id: ?string, code: ?string, label: string, external_id: ?string, uri: ?string, source: string}|null
     */
    public function acceptIcdAutocode(): ?array
    {
        $suggestion = $this->autocodeSuggestion;

        $this->clearIcdAutocode();

        return $suggestion;
    }
}
