<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Support\SuperAdmin;

class EncountersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name'),
                TextColumn::make('encounter_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                ClientIdentityColumn::make(includeGuestSearch: true),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch')
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('type')
                    ->options(EncounterType::class),

                SelectFilter::make('status')
                    ->options(EncounterStatus::class),

                SelectFilter::make('priority')
                    ->options(EncounterPriority::class),

                Filter::make('admission_pending')
                    ->label('Admission pending')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('pendingAdmissionRequest')),
            ])
            ->recordActions([
                Action::make('accept_admission')
                    ->label('Accept')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->button()
                    ->size('xs')
                    ->visible(fn (Encounter $record): bool => EncounterActions::isAdmissionDecisionVisible($record)
                        && (Auth::user()?->can('update', $record) ?? false))
                    ->modalHeading(__('Accept admission'))
                    ->modalDescription(fn (Encounter $record): string => EncounterActions::pendingRequestSummary($record))
                    ->modalSubmitActionLabel(__('Admit to bed'))
                    ->slideOver()
                    ->schema(fn (Encounter $record): array => EncounterActions::acceptAdmissionSchema($record))
                    ->action(function (Encounter $record, array $data): void {
                        app(AdtService::class)->acceptAdmission(
                            $record->pendingAdmissionRequest()->firstOrFail(),
                            $data['bed_id'],
                            notes: $data['notes'] ?? null,
                        );
                        Notification::make()->title('Admission accepted — patient admitted to bed')->success()->send();
                    }),
                Action::make('reject_admission')
                    ->label('Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->button()
                    ->size('xs')
                    ->visible(fn (Encounter $record): bool => EncounterActions::isAdmissionDecisionVisible($record)
                        && (Auth::user()?->can('update', $record) ?? false))
                    ->modalHeading(__('Reject admission'))
                    ->modalDescription(fn (Encounter $record): string => EncounterActions::pendingRequestSummary($record))
                    ->modalSubmitActionLabel(__('Reject'))
                    ->schema(EncounterActions::rejectAdmissionSchema())
                    ->action(function (Encounter $record, array $data): void {
                        app(AdtService::class)->rejectAdmission(
                            $record->pendingAdmissionRequest()->firstOrFail(),
                            $data['reason'],
                        );
                        Notification::make()->title('Admission rejected')->warning()->send();
                    }),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    Action::make('activities')
                        ->visible(fn (): bool => SuperAdmin::check())
                        ->label('Activities')
                        ->icon('heroicon-o-bell-alert')
                        ->url(fn ($record) => EncounterResource::getUrl('activities', ['record' => $record])),
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
