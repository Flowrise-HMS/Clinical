<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AdtDestinationType;
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
                    app(AdtService::class)->assignBed(
                        $record,
                        $data['bed_id'],
                        notes: $data['notes'] ?? null,
                    );
                    $this->refreshFormData(['status', 'bed_id', 'location_id', 'admitted_at']);
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

            EncounterActions::transferInternal($record)
                ->action(function (array $data) use ($record) {
                    app(AdtService::class)->transferInternal(
                        $record,
                        $data['bed_id'],
                        notes: $data['notes'] ?? null,
                    );
                    $this->refreshFormData(['bed_id', 'location_id', 'department_id']);
                    $this->notify('success', 'Patient transferred internally');
                }),

            EncounterActions::transferOut($record)
                ->action(function (array $data) use ($record) {
                    app(AdtService::class)->transferOut(
                        $record,
                        AdtDestinationType::from($data['destination_type']),
                        destinationLabel: $data['destination_label'] ?? null,
                        destinationBranchId: $data['destination_branch_id'] ?? null,
                        notes: $data['notes'] ?? null,
                    );
                    $this->refreshFormData(['status', 'discharge_disposition', 'discharged_at', 'transfer_destination', 'bed_id']);
                    $this->notify('success', 'Patient transferred out');
                }),

            EncounterActions::discharge($record)
                ->action(function (array $data) use ($record) {
                    app(AdtService::class)->discharge(
                        $record,
                        DischargeDisposition::from($data['discharge_disposition']),
                        $data['transfer_destination'] ?? null,
                        notes: $data['notes'] ?? null,
                    );
                    $this->refreshFormData(['status', 'discharge_disposition', 'discharged_at', 'bed_id']);
                    $this->notify('success', 'Patient discharged successfully');
                }),

            EncounterActions::cancel($record)
                ->action(function (array $data) use ($record) {
                    app(EncounterService::class)->cancelEncounter($record, $data['reason']);
                    $this->refreshFormData(['status', 'bed_id']);
                    $this->notify('warning', 'Encounter cancelled');
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
