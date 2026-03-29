<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Enums\NavigationGroup;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\ListTasks;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\ViewTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Schemas\TaskInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Tables\TasksTable;
use Modules\Clinical\Models\Task;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $slug = 'clinical/tasks';

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'view' => ViewTask::route('/{record}'),
        ];
    }
}
