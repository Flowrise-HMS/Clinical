<?php

namespace Modules\Clinical\Tests\Unit\DataTransferObjects;

use Modules\Clinical\DataTransferObjects\EncounterData;
use Tests\TestCase;

class EncounterDataTest extends TestCase
{
    public function test_from_array_creates_dto(): void
    {
        $data = EncounterData::fromArray([
            'branch_id' => 'branch-1',
            'type' => 'inpatient',
            'patient_id' => 'patient-1',
            'location_id' => 'loc-1',
            'department_id' => 'dept-1',
            'status' => 'active',
        ]);

        $this->assertSame('branch-1', $data->branchId);
        $this->assertSame('inpatient', $data->type);
        $this->assertSame('patient-1', $data->patientId);
        $this->assertSame('active', $data->status);
    }

    public function test_to_array_round_trip(): void
    {
        $original = [
            'branch_id' => 'branch-1',
            'type' => 'outpatient',
            'patient_id' => 'patient-1',
            'chief_complaint' => 'Headache',
        ];

        $result = EncounterData::fromArray($original)->toArray();

        $this->assertSame($original['branch_id'], $result['branch_id']);
        $this->assertSame($original['type'], $result['type']);
        $this->assertSame($original['patient_id'], $result['patient_id']);
        $this->assertSame($original['chief_complaint'], $result['chief_complaint']);
    }

    public function test_to_array_omits_nulls(): void
    {
        $result = EncounterData::fromArray([
            'branch_id' => 'branch-1',
            'type' => 'emergency',
        ])->toArray();

        $this->assertArrayNotHasKey('patient_id', $result);
        $this->assertArrayNotHasKey('location_id', $result);
        $this->assertArrayNotHasKey('department_id', $result);
        $this->assertArrayNotHasKey('status', $result);
    }
}
