<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;

class EditServiceRequest extends EditRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
