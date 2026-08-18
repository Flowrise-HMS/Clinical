<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Concerns;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

/**
 * Filament fields bind with Livewire `$entangle('statePath.field')`. Missing nested
 * keys look filled in the UI but drop on the next request, so required validation fails.
 */
trait SeedsSchemaEntangleKeys
{
    public function rendering(): void
    {
        $this->ensureSchemaEntangleKeysExist();
    }

    protected function ensureSchemaEntangleKeysExist(): void
    {
        foreach ($this->getCachedSchemas() as $schema) {
            if ($schema instanceof Schema) {
                $this->seedStateKeysForSchema($schema);
            }
        }
    }

    protected function seedStateKeysForSchema(Schema $schema): void
    {
        $root = $schema->getStatePath();

        if (! is_string($root) || $root === '' || ! property_exists($this, $root) || ! is_array($this->{$root})) {
            return;
        }

        foreach ($schema->getFlatComponents(withHidden: true) as $component) {
            if (! $component instanceof Field) {
                continue;
            }

            $absolute = $component->getStatePath();

            if (! is_string($absolute) || ! str_starts_with($absolute, $root.'.')) {
                continue;
            }

            $relative = substr($absolute, strlen($root) + 1);
            $segments = explode('.', $relative);

            if ($segments === [] || $segments[0] === '') {
                continue;
            }

            if ($this->shouldSkipMissingRepeaterItemPath($this->{$root}, $segments)) {
                continue;
            }

            $leaf = $component instanceof Repeater ? [] : null;
            $this->ensureNestedStateKey($this->{$root}, $segments, $leaf);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<string>  $segments
     */
    protected function shouldSkipMissingRepeaterItemPath(array $state, array $segments): bool
    {
        if (count($segments) < 3) {
            return false;
        }

        $repeaterState = $state[$segments[0]] ?? null;

        if (! is_array($repeaterState) || $repeaterState === []) {
            return true;
        }

        $firstItem = reset($repeaterState);

        if (! is_array($firstItem)) {
            return false;
        }

        return ! array_key_exists($segments[1], $repeaterState);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<string>  $segments
     */
    protected function ensureNestedStateKey(array &$state, array $segments, mixed $leaf): void
    {
        $cursor = &$state;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($index === $lastIndex) {
                if (! array_key_exists($segment, $cursor)) {
                    $cursor[$segment] = $leaf;
                }

                return;
            }

            if (! array_key_exists($segment, $cursor) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }
    }
}
