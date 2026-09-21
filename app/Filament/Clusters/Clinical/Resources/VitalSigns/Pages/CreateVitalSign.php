<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;

class CreateVitalSign extends CreateRecord
{
    protected static string $resource = VitalSignResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] ??= auth()->id();
        $data['recorded_at'] ??= now();

        return $data;
    }
}
