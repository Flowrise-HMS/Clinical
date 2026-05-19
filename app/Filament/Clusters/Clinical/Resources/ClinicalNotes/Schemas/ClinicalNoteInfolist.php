<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClinicalNoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Note Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('note_type')
                            ->label('Note Type')
                            ->badge(),
                        TextEntry::make('subject')
                            ->label('Subject')
                            ->placeholder('No Subject'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ]),

                Section::make('Note Content')
                    ->schema([
                        TextEntry::make('content')
                            ->hiddenLabel()
                            ->html()
                            ->placeholder('No content recorded'),
                    ]),

                Section::make('Author')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('author.name')
                            ->label('Author'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ]),

                Section::make('Signature')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('is_signed')
                            ->label('Signed')
                            ->boolean(),
                        TextEntry::make('signed_at')
                            ->label('Signed At')
                            ->dateTime()
                            ->placeholder('Not signed yet'),
                        TextEntry::make('signedBy.name')
                            ->label('Signed By')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
