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
];
