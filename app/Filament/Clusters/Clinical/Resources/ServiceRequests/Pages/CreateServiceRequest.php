<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
