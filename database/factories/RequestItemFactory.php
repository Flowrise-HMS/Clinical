<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Service;

class RequestItemFactory extends Factory
{
    protected $model = RequestItem::class;

    public function definition(): array
    {
        $service = Service::factory()->create();

        return [
            'service_request_id' => ServiceRequest::factory(),
            'service_id' => $service->id,
            'service_variant_id' => null,
            'quantity' => 1,
            'unit_price' => $service->getDefaultPrice(),
            'discount_amount' => 0,
            'total_price' => $service->getDefaultPrice(),
            'status' => RequestItemStatus::PENDING,
            'fulfilled_by' => null,
            'fulfilled_at' => null,
            'notes' => null,
        ];
    }

    public function forRequest(ServiceRequest $request): static
    {
        return $this->state(fn (array $attributes) => [
            'service_request_id' => $request->id,
        ]);
    }

    public function forService(Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $service->id,
            'unit_price' => $service->getDefaultPrice(),
            'total_price' => $service->getDefaultPrice(),
        ]);
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitPrice = $attributes['unit_price'] ?? 0;

            return [
                'quantity' => $quantity,
                'total_price' => $unitPrice * $quantity,
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::PENDING,
            'fulfilled_by' => null,
            'fulfilled_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::IN_PROGRESS,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::COMPLETED,
            'fulfilled_by' => User::factory()->create()->id,
            'fulfilled_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::CANCELLED,
        ]);
    }
}
