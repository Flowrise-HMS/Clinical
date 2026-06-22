<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Models\ClinicalNote;

class PatientNotesWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Clinical Notes';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'clinical::filament.widgets.collapsible-table-widget';

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    protected function getTableQuery(): Builder
    {
        return ClinicalNote::query()
            ->where('patient_id', $this->patientId)
            ->when($this->encounterId, fn ($q) => $q->where('encounter_id', $this->encounterId))
            ->with(['author'])
            ->orderBy('created_at', 'desc')
            ->limit(20);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('note_type')
                ->label('Type')
                ->badge()
                ->color(fn ($state) => match ($state?->value) {
                    'progress' => 'primary',
                    'discharge' => 'success',
                    'admission' => 'warning',
                    default => 'gray',
                })
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'General'),
            BadgeColumn::make('is_signed')
                ->label('Status')
                ->formatStateUsing(fn ($state) => $state ? 'Signed' : 'Draft')
                ->color(fn ($state) => $state ? 'success' : 'gray'),
            TextColumn::make('content')
                ->limit(100)
                ->html(),
            TextColumn::make('author.name')->label('Author'),
            TextColumn::make('created_at')->label('Date')->dateTime()->sortable(),
        ];
    }

    protected function getTableRecordActions(): array
    {
        return [
            ViewAction::make()
                ->modalHeading('Clinical Note')
                ->modalWidth('lg')
                ->infolist([
                    Section::make()
                        ->schema([
                            TextEntry::make('note_type')
                                ->label('Type')
                                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'General')
                                ->badge(),
                            TextEntry::make('is_signed')
                                ->label('Status')
                                ->formatStateUsing(fn ($state) => $state ? 'Signed' : 'Draft')
                                ->badged()
                                ->color(fn ($state) => $state ? 'success' : 'gray'),
                            TextEntry::make('content')
                                ->label('')
                                ->html()
                                ->columnSpanFull(),
                            TextEntry::make('author.name')->label('Author'),
                            TextEntry::make('created_at')->label('Date')->dateTime(),
                            TextEntry::make('signed_at')->label('Signed At')->dateTime()->visible(fn ($record) => $record?->is_signed),
                        ]),
                ]),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No clinical notes';
    }

    protected function getTablePollingInterval(): ?string
    {
        return null;
    }
}
