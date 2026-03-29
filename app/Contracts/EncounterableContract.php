<?php

namespace Modules\Clinical\Contracts;

use Modules\Clinical\Models\Encounter;

interface EncounterableContract
{
    public function getEncounter(): ?Encounter;

    public function getEncounterId(): ?string;

    public function getDisplayName(): string;

    public function getPatientIdentifier(): ?string;
}
