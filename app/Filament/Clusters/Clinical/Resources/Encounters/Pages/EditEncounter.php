<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;

class EditEncounter extends EditRecord
{
    protected static string $resource = EncounterResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            EncounterActions::admit($record)
                ->action(function (array $data) use ($record) {
                    app(EncounterService::class)->admitPatient(
                        $record,
                        bedId: $data['bed_id'] ?? null
                    );
                    $this->refreshFormData(['status', 'bed_id']);
                    $this->notify('success', 'Patient admitted successfully');
                }),

            EncounterActions::triage($record)
                ->action(function (array $data) use ($record) {
                    app(EncounterService::class)->triage(
                        $record,
                        EncounterPriority::from($data['priority'])
                    );
                    $this->refreshFormData(['status', 'priority']);
                    $this->notify('success', 'Patient triaged successfully');
                }),

            EncounterActions::discharge($record)
                ->action(function (array $data) use ($record) {
                    app(EncounterService::class)->discharge(
                        $record,
                        DischargeDisposition::from($data['discharge_disposition']),
                        $data['transfer_destination'] ?? null
                    );
                    $this->refreshFormData(['status', 'discharge_disposition', 'discharged_at']);
                    $this->notify('success', 'Patient discharged successfully');
                }),

            EncounterActions::cancel($record)
                ->action(function (array $data) use ($record) {
                    app(EncounterService::class)->cancelEncounter($record, $data['reason']);
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
