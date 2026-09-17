<?php

namespace Modules\Clinical\Support;

/**
 * Result of a discharge readiness assessment: one item per check, each
 * carrying whether it passed and how serious a failure is.
 */
final class DischargeReadiness
{
    /**
     * @param  list<array{key: string, label: string, passed: bool, severity: string, detail: ?string}>  $items
     */
    public function __construct(public readonly array $items) {}

    /**
     * @return list<array{key: string, label: string, passed: bool, severity: string, detail: ?string}>
     */
    public function blocking(): array
    {
        return array_values(array_filter($this->items, fn (array $item): bool => ! $item['passed'] && $item['severity'] === 'blocking'));
    }

    /**
     * @return list<array{key: string, label: string, passed: bool, severity: string, detail: ?string}>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->items, fn (array $item): bool => ! $item['passed'] && $item['severity'] === 'warning'));
    }

    public function isReady(): bool
    {
        return $this->blocking() === [];
    }

    /**
     * @return array{ready: bool, items: list<array{key: string, label: string, passed: bool, severity: string, detail: ?string}>}
     */
    public function toArray(): array
    {
        return ['ready' => $this->isReady(), 'items' => $this->items];
    }

    public function summary(): string
    {
        return implode('; ', array_map(fn (array $item): string => $item['label'].($item['detail'] ? ' ('.$item['detail'].')' : ''), $this->blocking()));
    }
}
