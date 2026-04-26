<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\AllergyResource;

class CreateAllergy extends CreateRecord
{
    protected static string $resource = AllergyResource::class;
}
