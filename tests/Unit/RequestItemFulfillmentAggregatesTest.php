<?php

namespace Modules\Clinical\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Modules\Billing\Services\PatientFinancialHoldService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Database\Factories\MedicationAdministrationFactory;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Database\Factories\ServiceFactory;
use Tests\TestCase;

class RequestItemFulfillmentAggregatesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing', 'Pharmacy']);
    }

    public function test_with_fulfillment_aggregates_sums_given_doses_without_extra_queries(): void
    {
        $branch = BranchFactory::new()->create();
        $service = ServiceFactory::new()->create();
        $request = ServiceRequestFactory::new()->create(['branch_id' => $branch->id]);
        $item = RequestItemFactory::new()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);

        MedicationAdministrationFactory::new()->create([
            'request_item_id' => $item->id,
            'status' => MedicationAdministrationStatus::GIVEN,
            'quantity_given' => 2,
        ]);

        MedicationAdministrationFactory::new()->create([
            'request_item_id' => $item->id,
            'status' => MedicationAdministrationStatus::GIVEN,
            'quantity_given' => 3,
        ]);

        MedicationAdministrationFactory::new()->create([
            'request_item_id' => $item->id,
            'status' => MedicationAdministrationStatus::REFUSED,
            'quantity_given' => 0,
        ]);

        $loaded = RequestItem::query()
            ->withFulfillmentAggregates()
            ->findOrFail($item->id);

        $policy = app(MedicationFulfillmentPolicy::class);

        $this->assertSame(5, $policy->givenDosesCount($loaded));
    }

    public function test_resolve_financial_holds_for_request_items_batches_lookup(): void
    {
        $branch = BranchFactory::new()->create();
        $service = ServiceFactory::new()->create(['requires_payment_before' => true]);
        $request = ServiceRequestFactory::new()->create(['branch_id' => $branch->id]);
        $item = RequestItemFactory::new()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);

        $loaded = RequestItem::query()
            ->with(['serviceRequest.encounter'])
            ->findOrFail($item->id);

        $flags = app(PatientFinancialHoldService::class)
            ->resolveFinancialHoldsForRequestItems(Collection::make([$loaded]));

        $this->assertSame($loaded->hasActiveFinancialHold(), $flags[(string) $item->id]);
    }
}
