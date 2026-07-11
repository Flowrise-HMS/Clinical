<?php

namespace Modules\Clinical\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Database\Factories\ServiceFactory;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class MedicationFulfillmentPolicyCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
    }

    public function test_medication_lookup_is_cached_for_repeated_service_checks(): void
    {
        $branch = BranchFactory::new()->create();
        $service = ServiceFactory::new()->create();
        $request = ServiceRequestFactory::new()->create(['branch_id' => $branch->id]);
        $item = RequestItemFactory::new()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);

        Medication::factory()->create(['service_id' => $service->id]);

        $policy = app(MedicationFulfillmentPolicy::class);

        $this->assertFalse($policy->isControlledMedication($item));
        $policy->isControlledMedication($item);

        $reflection = new \ReflectionProperty(MedicationFulfillmentPolicy::class, 'medicationByServiceId');
        $reflection->setAccessible(true);

        $this->assertCount(1, $reflection->getValue($policy));
    }
}
