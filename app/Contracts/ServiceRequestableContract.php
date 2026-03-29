<?php

namespace Modules\Clinical\Contracts;

use Illuminate\Support\Collection;
use Modules\Clinical\Models\ServiceRequest;

interface ServiceRequestableContract
{
    public function getServiceRequest(): ?ServiceRequest;

    public function getServiceRequestId(): ?string;

    public function getDisplayName(): string;

    public function getPatientIdentifier(): ?string;

    public function getTotalAmount(): float;

    public function getPendingItems(): Collection;

    public function getCompletedItems(): Collection;
}
