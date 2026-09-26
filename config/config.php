<?php

return [
    'name' => 'Clinical',

    'permissions' => [
        'manage_clinical_settings' => 'ManageClinicalSettings',
        'manage_bed_status' => 'Manage Bed Status',
        'sign_discharge_summary' => 'Sign Discharge Summary',
        'print_discharge_summary' => 'Print Discharge Summary',
        'override_triage_category' => 'Override Triage Category',
    ],

    /*
     * Inpatient (IPD) behaviour.
     */
    'admissions' => [
        // Pending admission requests expire after this many hours; null disables expiry.
        'request_expiry_hours' => 24,
        // Length of stay (days) after which an inpatient is flagged as a long stay.
        'long_stay_days' => 7,
    ],

    'beds' => [
        // Discharge/transfer leaves the bed in "cleaning" (true) or straight back to "available".
        'cleaning_on_discharge' => true,
        // Requesting admission with a preferred bed reserves it until the request is decided.
        'reserve_on_request' => true,
    ],

    'wards' => [
        // Roles (in the ward's branch) that receive ward-targeted staff notifications.
        'notify_roles' => ['nurse'],
    ],

    // Channels for staff-facing ADT notifications (admission requests, decisions, transfers).
    'adt_notifications' => [
        'channels' => ['database', 'mail'],
    ],

    'discharge' => [
        'enforce_readiness' => true,
        'require_signed_summary' => true,
        // Readiness item => blocking|warning|info
        'readiness' => [
            'pending_medication_doses' => 'blocking',
            'undispensed_take_home_meds' => 'warning',
            'pending_diagnostics' => 'blocking',
            'financial_hold' => 'blocking',
            'unsigned_notes' => 'warning',
            'discharge_diagnosis' => 'blocking',
            'discharge_summary_signed' => 'blocking',
            'follow_up_booked' => 'info',
        ],
    ],

    'mar_payment' => [
        'require_before_mar' => true,
        'emergency_exempt' => true,
    ],

    'mar_allergy' => [
        'block_on_match' => false,

        /*
         * Names that refer to the same substance. Each group is matched as a
         * whole, so an allergy recorded as "paracetamol" blocks an order for
         * "acetaminophen 500 MG [Panadol]". Extend per facility formulary.
         */
        'synonyms' => [
            ['paracetamol', 'acetaminophen', 'panadol', 'tylenol'],
            ['adrenaline', 'epinephrine'],
            ['salbutamol', 'albuterol'],
            ['glibenclamide', 'glyburide'],
            ['frusemide', 'furosemide', 'lasix'],
            ['pethidine', 'meperidine'],
            ['lignocaine', 'lidocaine'],
            ['amoxicillin', 'amoxycillin', 'augmentin', 'co-amoxiclav'],
            ['penicillin', 'benzylpenicillin', 'phenoxymethylpenicillin', 'ampicillin', 'amoxicillin', 'amoxycillin', 'flucloxacillin', 'cloxacillin', 'piperacillin'],
            ['sulphonamide', 'sulfonamide', 'sulfamethoxazole', 'sulphamethoxazole', 'co-trimoxazole', 'cotrimoxazole', 'septrin'],
            ['aspirin', 'acetylsalicylic acid'],
            ['ibuprofen', 'brufen'],
            ['diclofenac', 'voltaren'],
        ],
    ],

    'mar_schedule' => [
        'mode' => 'fixed_from_order',
        'stat_duration_days' => 1,
        'grace_minutes' => 30,
    ],

    'mar_default_times' => [
        'qd' => ['08:00'],
        'bid' => ['08:00', '20:00'],
        'tid' => ['08:00', '14:00', '20:00'],
        'qid' => ['06:00', '12:00', '18:00', '22:00'],
    ],

    'mar_reminders' => [
        'enabled' => true,
        'lead_minutes' => 15,
        'grace_minutes' => 30,
        'channels' => ['database', 'mail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | WHO ICD API (https://icd.who.int/docs/icd-api/)
    |--------------------------------------------------------------------------
    |
    | OAuth2 client-credentials against icdaccessmanagement.who.int.
    | Token request uses HTTP Basic Auth with client id/secret per WHO docs.
    | Coding search uses the MMS linearization (entities with ICD codes).
    |
    */
    'icd' => [
        'client_id' => env('ICD_ClientId'),
        'client_secret' => env('ICD_ClientSecret'),
        'token_url' => env('ICD_TOKEN_URL', 'https://icdaccessmanagement.who.int/connect/token'),
        'base_url' => env('ICD_BASE_URL', 'https://id.who.int'),
        'scope' => env('ICD_SCOPE', 'icdapi_access'),
        'api_version' => env('ICD_apiVersion', 'v2'),
        'language' => env('ICD_acceptedLanguage', 'en'),
        'release_id' => env('ICD_releaseId', '2026-01'),
        'linearization' => env('ICD_LINEARIZATION', 'mms'),
        'timeout' => (int) env('ICD_TIMEOUT', 5),
    ],
];
