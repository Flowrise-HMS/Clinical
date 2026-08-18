<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalWorkspaceFormEntangleKeysTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        $this->branch = Branch::factory()->default()->create();
        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        Permission::findOrCreate('View ClinicalWorkspace', 'web');
        $this->user = User::factory()->create()->givePermissionTo('View ClinicalWorkspace');
    }

    public function test_every_mounted_form_field_has_a_livewire_state_key(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id]);

        $page = $component->instance();
        $this->assertInstanceOf(ClinicalWorkspace::class, $page);

        $missing = [];

        foreach ($page->getCachedSchemas() as $schema) {
            if (! $schema instanceof Schema) {
                continue;
            }

            $root = $schema->getStatePath();

            if (! is_string($root) || $root === '' || ! property_exists($page, $root)) {
                continue;
            }

            $state = $page->{$root};

            foreach ($schema->getFlatComponents(withHidden: true) as $field) {
                if (! $field instanceof Field) {
                    continue;
                }

                $absolute = $field->getStatePath();

                if (! is_string($absolute) || ! str_starts_with($absolute, $root.'.')) {
                    continue;
                }

                $relative = substr($absolute, strlen($root) + 1);
                $segments = explode('.', $relative);

                if ($this->isUnrenderedRepeaterChild($state, $segments, $field)) {
                    continue;
                }

                if (! $this->stateHasPath($state, $segments)) {
                    $missing[] = $absolute;
                }
            }
        }

        $this->assertSame([], $missing, 'Livewire cannot entangle Filament fields whose nested keys are missing.');
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<string>  $segments
     */
    protected function isUnrenderedRepeaterChild(array $state, array $segments, Field $field): bool
    {
        if ($field instanceof Repeater) {
            return false;
        }

        if (count($segments) < 3) {
            return false;
        }

        $repeaterState = $state[$segments[0]] ?? null;

        if (! is_array($repeaterState) || $repeaterState === []) {
            return true;
        }

        $firstItem = reset($repeaterState);

        return is_array($firstItem) && ! array_key_exists($segments[1], $repeaterState);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<string>  $segments
     */
    protected function stateHasPath(array $state, array $segments): bool
    {
        $cursor = $state;

        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }
}
