<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\CreateTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\EditTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\ListTasks;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\ViewTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Schemas\TaskForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Schemas\TaskInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Tables\TasksTable;
use Modules\Clinical\Models\Task;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

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
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
