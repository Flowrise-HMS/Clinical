<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * Database backstop for the MAR.
 *
 * The in-PHP duplicate check now runs inside a transaction under a row lock,
 * but the schema must still refuse a second GIVEN dose for the same slot if two
 * writers ever reach the insert together — medication_administrations
 * previously had no indexes or constraints at all.
 */
class MedicationAdministrationSlotGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
    }

    public function test_a_second_given_dose_for_the_same_slot_is_rejected_by_the_database(): void
    {
        [$item, $nurse] = $this->seedRequestItem();

        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, 1);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, 1);
    }

    public function test_different_slots_on_the_same_item_are_allowed(): void
    {
        [$item, $nurse] = $this->seedRequestItem();

        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, 1);
        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, 2);

        $this->assertSame(2, MedicationAdministration::query()
            ->where('request_item_id', $item->id)
            ->count());
    }

    public function test_an_omitted_dose_does_not_block_a_later_given_dose_for_that_slot(): void
    {
        [$item, $nurse] = $this->seedRequestItem();

        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::OMITTED, 1);
        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, 1);

        $this->assertSame(2, MedicationAdministration::query()
            ->where('request_item_id', $item->id)
            ->count());
    }

    /**
     * PRN orders have no schedule, so dose_slot_sequence stays null and repeat
     * doses must remain legal.
     */
    public function test_repeated_prn_doses_without_a_slot_are_allowed(): void
    {
        [$item, $nurse] = $this->seedRequestItem();

        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, null);
        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, null);
        $this->insertAdministration($item, $nurse, MedicationAdministrationStatus::GIVEN, null);

        $this->assertSame(3, MedicationAdministration::query()
            ->where('request_item_id', $item->id)
            ->count());
    }

    private function insertAdministration(
        RequestItem $item,
        User $nurse,
        MedicationAdministrationStatus $status,
        ?int $slot,
    ): void {
        DB::table('medication_administrations')->insert([
            'id' => (string) Str::uuid(),
            'request_item_id' => $item->id,
            'administered_by' => $nurse->id,
            'started_at' => now(),
            'ended_at' => now(),
            'quantity_given' => 1,
            'status' => $status->value,
            'dose_slot_sequence' => $slot,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{RequestItem, User}
     */
    private function seedRequestItem(): array
    {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $nurse = User::factory()->create(['branch_id' => $branch->id]);

        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $service = Service::factory()->create();

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $branch->id,
        ]);

        $item = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);

        return [$item, $nurse];
    }
}
