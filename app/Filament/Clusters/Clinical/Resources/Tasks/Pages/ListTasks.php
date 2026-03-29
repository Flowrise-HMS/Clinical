<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\TaskResource;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;
}
