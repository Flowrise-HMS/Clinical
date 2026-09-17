<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\BedStatusBackfillService;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class BedStatusBackfillServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
    }

    public function test_marks_held_beds_occupied_and_untouched_beds_available(): void
    {
        $branch = Branch::factory()->create();
        $ward = Location::factory()->room()->create(['branch_id' => $branch->id]);
        $held = Location::factory()->bed()->create(['branch_id' => $branch->id, 'parent_id' => $ward->id, 'status' => null]);
        $free = Location::factory()->bed()->create(['branch_id' => $branch->id, 'parent_id' => $ward->id, 'status' => null]);
        $blocked = Location::factory()->bed()->withStatus(BedStatus::BLOCKED)->create(['branch_id' => $branch->id, 'parent_id' => $ward->id]);
        $released = Location::factory()->bed()->withStatus(BedStatus::OCCUPIED, 'stale')->create(['branch_id' => $branch->id, 'parent_id' => $ward->id]);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $encounter = Encounter::factory()->forPatient($patient)->inpatient()->active()->create([
            'branch_id' => $branch->id,
            'bed_id' => $held->id,
            'location_id' => $ward->id,
        ]);

        $result = app(BedStatusBackfillService::class)->run();

        $this->assertSame(['occupied' => 1, 'available' => 1], $result);
        $this->assertSame(BedStatus::OCCUPIED, $held->fresh()->bedStatus());
        $this->assertSame($encounter->id, $held->fresh()->status_reference);
        $this->assertSame(BedStatus::AVAILABLE, $free->fresh()->bedStatus());
        $this->assertSame(BedStatus::BLOCKED, $blocked->fresh()->bedStatus(), 'explicit statuses are left alone');
        $this->assertSame(BedStatus::OCCUPIED, $released->fresh()->bedStatus(), 'stale occupied beds are not touched here');
    }

    public function test_is_idempotent(): void
    {
        $branch = Branch::factory()->create();
        $bed = Location::factory()->bed()->create(['branch_id' => $branch->id, 'status' => null]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        Encounter::factory()->forPatient($patient)->inpatient()->active()->create(['branch_id' => $branch->id, 'bed_id' => $bed->id]);

        $first = app(BedStatusBackfillService::class)->run();
        $second = app(BedStatusBackfillService::class)->run();

        $this->assertSame(1, $first['occupied']);
        $this->assertSame(['occupied' => 0, 'available' => 0], $second);
    }
}
