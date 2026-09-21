<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\ClinicalNoteResource;

class CreateClinicalNote extends CreateRecord
{
    protected static string $resource = ClinicalNoteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] ??= auth()->id();

        return $data;
    }
}
