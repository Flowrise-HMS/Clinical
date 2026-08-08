<?php

namespace Modules\Clinical\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\ServiceRequest;
use Tests\TestCase;

class ServiceRequestAggregateAttributesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing', 'Pharmacy']);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_aggregate_attributes_resolve_while_lazy_loading_is_disabled(): void
    {
        $request = $this->createRequestWithItems();

        Model::preventLazyLoading();

        $fresh = ServiceRequest::query()->findOrFail($request->id);

        $this->assertSame(1, $fresh->pending_items_count);
        $this->assertSame(2, $fresh->completed_items_count);
        $this->assertSame(66.7, $fresh->progress_percentage);
        $this->assertSame(60.0, $fresh->total_amount);
        $this->assertFalse($fresh->isFullyFulfilled());
    }

    public function test_aggregate_attributes_use_query_aggregates_when_they_are_selected(): void
    {
        $request = $this->createRequestWithItems();

        Model::preventLazyLoading();

        $loaded = ServiceRequest::query()
            ->withSum('items as items_total_amount', 'total_price')
            ->withCount([
                'items',
                'items as completed_items_count' => fn (Builder $query) => $query->where(
                    'status',
                    RequestItemStatus::COMPLETED,
                ),
            ])
            ->findOrFail($request->id);

        $this->assertSame(2, $loaded->completed_items_count);
        $this->assertSame(66.7, $loaded->progress_percentage);
        $this->assertSame(60.0, $loaded->total_amount);
    }

    public function test_request_is_fully_fulfilled_once_every_item_reaches_a_terminal_status(): void
    {
        $request = ServiceRequestFactory::new()->create();

        RequestItemFactory::new()->completed()->forRequest($request)->create();
        RequestItemFactory::new()->cancelled()->forRequest($request)->create();

        Model::preventLazyLoading();

        $fresh = ServiceRequest::query()->findOrFail($request->id);

        $this->assertTrue($fresh->isFullyFulfilled());
    }

    protected function createRequestWithItems(): ServiceRequest
    {
        $request = ServiceRequestFactory::new()->create();

        RequestItemFactory::new()->pending()->forRequest($request)->create($this->pricedAt(10));
        RequestItemFactory::new()->completed()->forRequest($request)->create($this->pricedAt(20));
        RequestItemFactory::new()->completed()->forRequest($request)->create($this->pricedAt(30));

        return $request;
    }

    /**
     * The model recalculates `total_price` on save, so the price has to be driven by `unit_price`.
     *
     * @return array<string, int>
     */
    protected function pricedAt(int $unitPrice): array
    {
        return [
            'unit_price' => $unitPrice,
            'quantity' => 1,
            'discount_amount' => 0,
        ];
    }
}
