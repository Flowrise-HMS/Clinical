<?php

namespace Modules\Clinical\Support;

use Modules\Core\Support\AppSettings;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;

/**
 * Who receives a patient-facing notification: the patient, plus their
 * emergency contacts when the organisation has opted in.
 */
class PatientNotificationAudience
{
    /**
     * @return list<Patient|EmergencyContact>
     */
    public static function for(Patient $patient): array
    {
        $audience = [$patient];

        if (! self::includesEmergencyContacts()) {
            return $audience;
        }

        $patient->loadMissing('emergencyContacts');

        return array_merge($audience, $patient->emergencyContacts->all());
    }

    /**
     * @return list<EmergencyContact>
     */
    public static function emergencyContactsOnly(Patient $patient): array
    {
        if (! self::includesEmergencyContacts()) {
            return [];
        }

        $patient->loadMissing('emergencyContacts');

        return $patient->emergencyContacts->all();
    }

    protected static function includesEmergencyContacts(): bool
    {
        try {
            return (bool) app(AppSettings::class)->notifications()->include_emergency_contacts;
        } catch (\Throwable) {
            return true;
        }
    }
}
