<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Service;
use Modules\Diagnostics\Classes\Services\DiagnosticResultService;
use Tests\TestCase;

class FulfillmentContextLazyLoadingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Diagnostics', 'Billing', 'Pharmacy']);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_fulfillment_context_resolves_without_any_eager_loading(): void
    {
        $item = $this->createRequestItem();

        Model::preventLazyLoading();

        $context = app(FulfillmentService::class)
            ->getContextInfo(RequestItem::query()->findOrFail($item->id));

        $this->assertSame('Full Blood Count', $context['service_name']);
        $this->assertSame('Dr Ama', $context['ordered_by']);
    }

    public function test_fulfillment_context_resolves_when_only_the_service_request_is_loaded(): void
    {
        $item = $this->createRequestItem();

        Model::preventLazyLoading();

        $partiallyLoaded = RequestItem::query()
            ->with(['service', 'serviceRequest'])
            ->findOrFail($item->id);

        $context = app(FulfillmentService::class)->getContextInfo($partiallyLoaded);

        $this->assertSame('Dr Ama', $context['ordered_by']);
    }

    public function test_fulfillment_type_resolves_without_any_eager_loading(): void
    {
        $item = $this->createRequestItem();

        Model::preventLazyLoading();

        $type = app(FulfillmentService::class)
            ->getType(RequestItem::query()->findOrFail($item->id));

        $this->assertSame('generic', $type);
    }

    public function test_diagnostic_context_resolves_without_any_eager_loading(): void
    {
        $item = $this->createRequestItem();

        Model::preventLazyLoading();

        $context = app(DiagnosticResultService::class)
            ->getContextInfo(RequestItem::query()->findOrFail($item->id));

        $this->assertSame('Full Blood Count', $context['service_name']);
        $this->assertSame('Dr Ama', $context['ordered_by']);
    }

    protected function createRequestItem(): RequestItem
    {
        $prescriber = User::factory()->create(['name' => 'Dr Ama']);
        $service = Service::factory()->create(['name' => 'Full Blood Count']);
        $request = ServiceRequest::factory()->create(['ordered_by' => $prescriber->id]);

        return RequestItem::factory()
            ->forRequest($request)
            ->forService($service)
            ->create();
    }
}
