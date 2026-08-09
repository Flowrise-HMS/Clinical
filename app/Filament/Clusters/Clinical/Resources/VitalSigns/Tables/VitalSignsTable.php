<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;
use Modules\Clinical\Policies\VitalSignPolicy;
use Modules\Core\Support\SuperAdmin;

class VitalSignsTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('#')->rowIndex(),
            TextColumn::make('recorded_at')->label('Date/Time')->dateTime()->sortable(),
            TextColumn::make('blood_pressure')
                ->suffix('mmHg')
                ->color(fn ($record) => $record->isAbnormalBloodPressure() ? 'text-warning-600 font-medium' : 'text-gray-900'),
            TextColumn::make('heart_rate')->suffix('bpm'),
            TextColumn::make('temperature'),
            TextColumn::make('spo2')->label('SpO₂'),
            TextColumn::make('respiratory_rate'),
            TextColumn::make('recordedBy.name'),
        ];
    }

    /**
     * @return array<int, Action|ViewAction|EditAction|DeleteAction>
     */
    public static function recordActions(bool $includeActivities = true): array
    {
        $actions = [
            ViewAction::make()
                ->visible(fn ($record): bool => app(VitalSignPolicy::class)->view(Auth::user(), $record)),
            EditAction::make()
                ->visible(fn ($record): bool => app(VitalSignPolicy::class)->update(Auth::user(), $record)),
            DeleteAction::make()
                ->visible(fn ($record): bool => app(VitalSignPolicy::class)->delete(Auth::user(), $record)),
        ];

        if ($includeActivities) {
            $actions[] = Action::make('activities')
                ->visible(fn (): bool => SuperAdmin::check())
                ->label('Activities')
                ->icon('heroicon-o-bell-alert')
                ->url(fn ($record) => VitalSignResource::getUrl('activities', ['record' => $record]));
        }

        return $actions;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->emptyStateHeading('No vitals recorded')
            ->filters([
                //
            ])
            ->recordActions(self::recordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
