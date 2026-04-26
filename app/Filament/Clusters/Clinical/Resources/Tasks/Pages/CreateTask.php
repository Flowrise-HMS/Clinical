<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\TaskResource;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;
}
