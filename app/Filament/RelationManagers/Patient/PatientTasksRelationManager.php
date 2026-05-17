<?php

namespace Modules\Clinical\Filament\RelationManagers\Patient;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Schemas\TaskInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Tables\TasksTable;

class PatientTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Tasks');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return TasksTable::configure($table)
            ->recordActions([
                ViewAction::make()
                    ->schema(fn (Schema $schema) => TaskInfolist::configure($schema))
                    ->slideOver(),
            ]);
    }
}
