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

    /**
     * Schemas are built lazily per tab, so every tab is opened and checked: a form
     * seeded only after its tab rendered would pass a single-render check and still
     * drop its nested keys on the next request.
     */
    public function test_every_mounted_form_field_has_a_livewire_state_key(): void
    {
        foreach ([
            'Create ClinicalNote',
            'Create EncounterDiagnosis',
            'Create Encounter',
            'Update Encounter',
            'View Encounter',
            'Update Patient',
            'View Patient',
            'Create VitalSign',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->user->givePermissionTo($permission);
        }

        $component = Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id]);

        $page = $component->instance();
        $this->assertInstanceOf(ClinicalWorkspace::class, $page);

        $tabs = array_keys($page->getClinicianTabs());
        $this->assertContains('vitals', $tabs);
        $this->assertContains('adt', $tabs);
        $this->assertContains('diagnosis', $tabs);
        $this->assertContains('patient-details', $tabs);

        $visited = [];

        foreach ($tabs as $tab) {
            $component->set('activeTab', $tab);

            $page = $component->instance();
            $visited[$tab] = array_keys($page->getCachedSchemas());

            $this->assertSame([], $this->missingStateKeys($page), "Tab [{$tab}]: Livewire cannot entangle Filament fields whose nested keys are missing.");
        }

        // Each tab really built its own schema (lazy resolution is wired up).
        $this->assertContains('vitalsForm', $visited['vitals']);
        $this->assertContains('diagnosisForm', $visited['diagnosis']);
        $this->assertContains('patientDetailsForm', $visited['patient-details']);
        $this->assertContains('adtAdmitForm', $visited['adt']);
        // ...and did not drag the previous tab's schema along.
        $this->assertNotContains('patientDetailsForm', $visited['vitals']);
    }

    public function test_only_the_active_tab_schemas_stay_discovered_between_requests(): void
    {
        Permission::findOrCreate('Update Patient', 'web');
        Permission::findOrCreate('View Patient', 'web');
        $this->user->givePermissionTo(['Update Patient', 'View Patient']);

        $component = Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('activeTab', 'patient-details');

        $this->assertContains('patientDetailsForm', $component->instance()->discoveredSchemaNames);

        $component->set('activeTab', 'vitals');

        $discovered = $component->instance()->discoveredSchemaNames;

        $this->assertContains('vitalsForm', $discovered);
        $this->assertNotContains('patientDetailsForm', $discovered);
    }

    /**
     * @return list<string>
     */
    protected function missingStateKeys(ClinicalWorkspace $page): array
    {
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

        return $missing;
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
