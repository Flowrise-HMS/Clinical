<?php

namespace Modules\Clinical\Tests\Unit;

use Modules\Clinical\Filament\Schemas\EncounterCoverageSchema;
use Tests\TestCase;

class EncounterCoverageSchemaTest extends TestCase
{
    public function test_coverage_field_is_required_and_reactive(): void
    {
        $field = EncounterCoverageSchema::coverageField();

        $this->assertSame('coverage_type', $field->getName());
        $this->assertTrue($field->isRequired());
        $this->assertTrue($field->isLive());
    }

    public function test_claim_check_code_field_accepts_5_or_13_character_codes(): void
    {
        $field = EncounterCoverageSchema::claimCheckCodeField();

        $this->assertSame('claim_check_code', $field->getName());
    }
}
