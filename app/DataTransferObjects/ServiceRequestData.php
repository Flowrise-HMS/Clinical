<?php

namespace Modules\Clinical\DataTransferObjects;

readonly class ServiceRequestData
{
    public function __construct(
        public string $branchId,
        public ?string $patientId,
        public ?string $encounterId,
        public ?string $status,
        public ?string $priority,
        public ?string $notes,
        public ?string $guestName,
        public ?string $guestPhone,
        public ?string $guestEmail,
        public ?int $orderedBy,
        public ?array $items,
        public ?array $metadata,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            branchId: $data['branch_id'],
            patientId: $data['patient_id'] ?? null,
            encounterId: $data['encounter_id'] ?? null,
            status: $data['status'] ?? null,
            priority: $data['priority'] ?? null,
            notes: $data['notes'] ?? null,
            guestName: $data['guest_name'] ?? null,
            guestPhone: $data['guest_phone'] ?? null,
            guestEmail: $data['guest_email'] ?? null,
            orderedBy: $data['ordered_by'] ?? null,
            items: $data['items'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'patient_id' => $this->patientId,
            'encounter_id' => $this->encounterId,
            'branch_id' => $this->branchId,
            'status' => $this->status,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'guest_name' => $this->guestName,
            'guest_phone' => $this->guestPhone,
            'guest_email' => $this->guestEmail,
            'ordered_by' => $this->orderedBy,
            'items' => $this->items,
            'metadata' => $this->metadata,
        ], fn ($value) => ! is_null($value));
    }
}
