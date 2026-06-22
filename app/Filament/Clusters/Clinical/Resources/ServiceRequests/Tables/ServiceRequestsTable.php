<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('request_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->sortable()
                    ->placeholder('Guest'),

                TextColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'primary' => 'active',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->colors([
                        'danger' => 'emergency',
                        'warning' => 'urgent',
                        'primary' => 'routine',
                        'gray' => 'low',
                    ]),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                CurrencyColumn::make('total_amount')
                    ->label('Total')
                    ->sortable(),

                TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%'),

                TextColumn::make('orderedBy.name')
                    ->label('Ordered By')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RequestStatus::class),

                SelectFilter::make('priority')
                    ->options(RequestPriority::class),

            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    Action::make('activities')
                        ->label('Activities')
                        ->icon('heroicon-o-bell-alert')
                        ->url(fn ($record) => ServiceRequestResource::getUrl('activities', ['record' => $record])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession();
    }
}
