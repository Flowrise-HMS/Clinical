<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;

class EditEncounter extends EditRecord
{
    protected static string $resource = EncounterResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Action::make('admit')
                ->label('Admit Patient')
                ->icon('heroicon-m-arrow-right-start-on-rectangle')
                ->color('success')
                ->visible(fn () => $record->canTransitionTo(EncounterStatus::ARRIVED))
                ->action(function () {
                    app(EncounterService::class)->admitPatient($this->getRecord());
                    $this->refreshFormData(['status']);
                    $this->notify('success', 'Patient admitted successfully');
                }),

            Action::make('triage')
                ->label('Triage')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->visible(fn () => $record->status === EncounterStatus::ARRIVED)
                ->schema([
                    Select::make('priority')
                        ->label('Priority')
                        ->options(EncounterPriority::class)
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(EncounterService::class)->triage(
                        $this->getRecord(),
                        EncounterPriority::from($data['priority'])
                    );
                    $this->refreshFormData(['status', 'priority']);
                    $this->notify('success', 'Patient triaged successfully');
                }),

            Action::make('discharge')
                ->label('Discharge')
                ->icon('heroicon-m-arrow-left-end-on-rectangle')
                ->color('danger')
                ->visible(fn () => $record->canTransitionTo(EncounterStatus::FINISHED))
                ->schema([
                    Select::make('discharge_disposition')
                        ->label('Disposition')
                        ->options(DischargeDisposition::class)
                        ->default('completed')
                        ->required(),
                    TextInput::make('transfer_destination')
                        ->label('Transfer Destination')
                        ->visible(fn (callable $get) => $get('discharge_disposition') === 'transferred'),
                ])
                ->action(function (array $data) {
                    app(EncounterService::class)->discharge(
                        $this->getRecord(),
                        DischargeDisposition::from($data['discharge_disposition']),
                        $data['transfer_destination'] ?? null
                    );
                    $this->refreshFormData(['status', 'discharge_disposition', 'discharged_at']);
                    $this->notify('success', 'Patient discharged successfully');
                }),

            Action::make('cancel')
                ->label('Cancel Encounter')
                ->icon('heroicon-m-x-circle')
                ->color('gray')
                ->visible(fn () => ! $record->isCompleted())
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason for Cancellation')
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(EncounterService::class)->cancelEncounter($this->getRecord(), $data['reason']);
                    $this->refreshFormData(['status']);
                    $this->notify('warning', 'Encounter cancelled');
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
