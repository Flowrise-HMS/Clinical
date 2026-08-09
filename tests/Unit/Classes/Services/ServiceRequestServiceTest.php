<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\RequestItemForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use ReflectionObject;
use Tests\TestCase;

class ServiceRequestServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ServiceRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);
        $this->service = app(ServiceRequestService::class);
    }

    public function test_locked_quick_elements_omit_status_fields(): void
    {
        $lockedNames = $this->componentNames(ServiceRequestForm::quickElements(lockStatuses: true));
        $unlockedNames = $this->componentNames(ServiceRequestForm::quickElements());

        $this->assertNotContains('status', $lockedNames);
        $this->assertContains('status', $unlockedNames);
        $this->assertContains('priority', $lockedNames);

        $lockedItemNames = $this->componentNames(RequestItemForm::getFormSchema(lockStatuses: true));
        $unlockedItemNames = $this->componentNames(RequestItemForm::getFormSchema());

        $this->assertNotContains('status', $lockedItemNames);
        $this->assertContains('status', $unlockedItemNames);
        $this->assertContains('service_id', $lockedItemNames);
    }

    public function test_record_with_locked_statuses_forces_active_and_pending(): void
    {
        $user = User::factory()->create();
        $patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create());
        $service = Service::factory()->create();

        $request = $this->service->record(
            $patient,
            [
                'status' => RequestStatus::CANCELLED->value,
                'items' => [
                    [
                        'service_id' => $service->id,
                        'quantity' => 1,
                        'status' => RequestItemStatus::COMPLETED->value,
                    ],
                ],
            ],
            orderedBy: $user->id,
            lockStatuses: true,
        );

        $this->assertSame(RequestStatus::ACTIVE, $request->status);
        $this->assertCount(1, $request->items);
        $this->assertSame(RequestItemStatus::PENDING, $request->items->first()->status);
    }

    public function test_record_without_lock_respects_request_status(): void
    {
        $user = User::factory()->create();
        $patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create());

        $request = $this->service->record(
            $patient,
            ['status' => RequestStatus::CANCELLED->value],
            orderedBy: $user->id,
        );

        $this->assertSame(RequestStatus::CANCELLED, $request->status);
    }

    /**
     * Walk Filament component trees without mounting them into a Livewire schema container.
     *
     * @param  array<int, mixed>  $components
     * @return list<string>
     */
    protected function componentNames(array $components): array
    {
        $names = [];
        $stack = $components;

        while ($stack !== []) {
            $component = array_shift($stack);

            if (! is_object($component)) {
                continue;
            }

            if (method_exists($component, 'getName') && filled($component->getName())) {
                $names[] = (string) $component->getName();
            }

            $reflection = new ReflectionObject($component);

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $prop = $reflection->getProperty('childComponents');
            $childSchemas = $prop->getValue($component);

            if (! is_array($childSchemas)) {
                continue;
            }

            foreach ($childSchemas as $children) {
                if (is_array($children)) {
                    array_push($stack, ...$children);
                }
            }
        }

        return array_values(array_unique($names));
    }
}
