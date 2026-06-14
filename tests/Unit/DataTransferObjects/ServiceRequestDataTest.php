<?php

namespace Modules\Clinical\Tests\Unit\DataTransferObjects;

use Modules\Clinical\DataTransferObjects\ServiceRequestData;
use Tests\TestCase;

class ServiceRequestDataTest extends TestCase
{
    public function test_from_array_creates_dto(): void
    {
        $data = ServiceRequestData::fromArray([
            'branch_id' => 'branch-1',
            'patient_id' => 'patient-1',
            'encounter_id' => 'enc-1',
            'status' => 'ordered',
            'priority' => 'urgent',
            'notes' => 'Please review',
        ]);

        $this->assertSame('branch-1', $data->branchId);
        $this->assertSame('patient-1', $data->patientId);
        $this->assertSame('ordered', $data->status);
        $this->assertSame('urgent', $data->priority);
    }

    public function test_to_array_round_trip(): void
    {
        $original = [
            'branch_id' => 'branch-1',
            'patient_id' => 'patient-1',
            'status' => 'completed',
        ];

        $result = ServiceRequestData::fromArray($original)->toArray();

        $this->assertSame($original['branch_id'], $result['branch_id']);
        $this->assertSame($original['patient_id'], $result['patient_id']);
        $this->assertSame($original['status'], $result['status']);
    }

    public function test_to_array_omits_nulls(): void
    {
        $result = ServiceRequestData::fromArray([
            'branch_id' => 'branch-1',
        ])->toArray();

        $this->assertArrayNotHasKey('patient_id', $result);
        $this->assertArrayNotHasKey('encounter_id', $result);
        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayNotHasKey('notes', $result);
    }
}
