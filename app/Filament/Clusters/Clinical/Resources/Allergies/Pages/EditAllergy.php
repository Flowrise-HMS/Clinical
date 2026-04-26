<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\AllergyResource;

class EditAllergy extends EditRecord
{
    protected static string $resource = AllergyResource::class;
}
