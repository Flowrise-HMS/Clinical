<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Services\NhisClaimCodeGateway;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;

class CreateEncounter extends CreateRecord
{
    protected static string $resource = EncounterResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] ??= Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $gateway = app(NhisClaimCodeGateway::class);
        $gateway->notify($gateway->generateFor($this->record->fresh()));
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
