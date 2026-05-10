<?php

namespace Modules\Clinical\Observers;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Events\EncounterFinished;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\PatientAdmittedNotification;
use Modules\Clinical\Notifications\PatientDischargedNotification;
use Modules\Patient\Models\Patient;

class EncounterObserver
{
    public function created(Encounter $encounter): void
    {
        $this->maybeNotifyAdmitted($encounter);
    }

    public function updated(Encounter $encounter): void
    {
        $this->maybeNotifyAdmitted($encounter);

        if ($encounter->wasChanged('status') && $encounter->status === EncounterStatus::FINISHED) {
            $this->notifyDischarged($encounter);
            EncounterFinished::dispatch($encounter->fresh());
        }
    }

    protected function maybeNotifyAdmitted(Encounter $encounter): void
    {
        if (! in_array($encounter->status, [EncounterStatus::ARRIVED, EncounterStatus::IN_PROGRESS], true)) {
            return;
        }

        $meta = $encounter->metadata ?? [];
        if (! empty($meta['notified_admitted'])) {
            return;
        }

        if ($encounter->patient_id === null) {
            return;
        }

        $encounter->loadMissing('patient.emergencyContacts', 'branch', 'department');

        $patient = $encounter->patient;
        if (! $patient instanceof Patient) {
            return;
        }

        Notification::send(
            $this->encounterAudience($patient),
            new PatientAdmittedNotification($encounter->fresh(['patient', 'branch', 'department']))
        );

        $merged = array_merge($encounter->metadata ?? [], ['notified_admitted' => true]);
        $encounter->forceFill(['metadata' => $merged])->saveQuietly();
    }

    protected function notifyDischarged(Encounter $encounter): void
    {
        if ($encounter->patient_id === null) {
            return;
        }

        $encounter->loadMissing('patient.emergencyContacts', 'branch');

        $patient = $encounter->patient;
        if (! $patient instanceof Patient) {
            return;
        }

        Notification::send(
            $this->encounterAudience($patient),
            new PatientDischargedNotification($encounter->fresh(['patient', 'branch']))
        );
    }

    /**
     * @return array<int, \Modules\Patient\Models\Patient|\Modules\Patient\Models\EmergencyContact>
     */
    protected function encounterAudience(Patient $patient): array
    {
        $patient->loadMissing('emergencyContacts');

        return array_merge([$patient], $patient->emergencyContacts->all());
    }
}
