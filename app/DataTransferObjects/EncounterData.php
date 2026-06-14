<?php

namespace Modules\Clinical\DataTransferObjects;

readonly class EncounterData
{
    public function __construct(
        public string $branchId,
        public string $type,
        public ?string $patientId,
        public ?string $locationId,
        public ?string $departmentId,
        public ?string $status,
        public ?string $priority,
        public ?string $chiefComplaint,
        public ?int $admittedBy,
        public ?int $dischargedBy,
        public ?string $dischargeDisposition,
        public ?string $transferDestination,
        public ?string $admittedAt,
        public ?string $dischargedAt,
        public ?string $bedId,
        public ?string $guestName,
        public ?string $guestPhone,
        public ?string $guestEmail,
        public ?string $notes,
        public ?array $metadata,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            branchId: $data['branch_id'],
            type: $data['type'],
            patientId: $data['patient_id'] ?? null,
            locationId: $data['location_id'] ?? null,
            departmentId: $data['department_id'] ?? null,
            status: $data['status'] ?? null,
            priority: $data['priority'] ?? null,
            chiefComplaint: $data['chief_complaint'] ?? null,
            admittedBy: $data['admitted_by'] ?? null,
            dischargedBy: $data['discharged_by'] ?? null,
            dischargeDisposition: $data['discharge_disposition'] ?? null,
            transferDestination: $data['transfer_destination'] ?? null,
            admittedAt: $data['admitted_at'] ?? null,
            dischargedAt: $data['discharged_at'] ?? null,
            bedId: $data['bed_id'] ?? null,
            guestName: $data['guest_name'] ?? null,
            guestPhone: $data['guest_phone'] ?? null,
            guestEmail: $data['guest_email'] ?? null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'patient_id' => $this->patientId,
            'branch_id' => $this->branchId,
            'location_id' => $this->locationId,
            'department_id' => $this->departmentId,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'chief_complaint' => $this->chiefComplaint,
            'admitted_by' => $this->admittedBy,
            'discharged_by' => $this->dischargedBy,
            'discharge_disposition' => $this->dischargeDisposition,
            'transfer_destination' => $this->transferDestination,
            'admitted_at' => $this->admittedAt,
            'discharged_at' => $this->dischargedAt,
            'bed_id' => $this->bedId,
            'guest_name' => $this->guestName,
            'guest_phone' => $this->guestPhone,
            'guest_email' => $this->guestEmail,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ], fn ($value) => ! is_null($value));
    }
}
