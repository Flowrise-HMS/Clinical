<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\CreateClinicalNote;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\EditClinicalNote;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\ListClinicalNotes;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Pages\ViewClinicalNote;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas\ClinicalNoteForm;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas\ClinicalNoteInfolist;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Tables\ClinicalNotesTable;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Core\Enums\NavigationGroup;

class ClinicalNoteResource extends Resource
{
    protected static ?string $model = ClinicalNote::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = ClinicalCluster::class;

    public static function form(Schema $schema): Schema
    {
        return ClinicalNoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClinicalNoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClinicalNotesTable::configure($table);
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
            'index' => ListClinicalNotes::route('/'),
            'create' => CreateClinicalNote::route('/create'),
            'view' => ViewClinicalNote::route('/{record}'),
            'edit' => EditClinicalNote::route('/{record}/edit'),
        ];
    }
}
