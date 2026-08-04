<?php

return [
    'name' => 'Clinical',

    'mar_payment' => [
        'require_before_mar' => true,
        'emergency_exempt' => true,
    ],

    'mar_allergy' => [
        'block_on_match' => false,
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
