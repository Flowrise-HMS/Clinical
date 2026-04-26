<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\AllergyResource;

class ListAllergies extends ListRecords
{
    protected static string $resource = AllergyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
