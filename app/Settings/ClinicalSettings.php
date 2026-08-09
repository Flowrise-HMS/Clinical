<?php

namespace Modules\Clinical\Settings;

use Spatie\LaravelSettings\Settings;

class ClinicalSettings extends Settings
{
    public bool $mar_require_payment_before = true;

    public bool $mar_emergency_exempt = true;

    public bool $mar_allergy_block_on_match = false;

    public string $mar_schedule_mode = 'fixed_from_order';

    public int $mar_schedule_grace_minutes = 30;

    public int $mar_stat_duration_days = 1;

    /** @phpstan-var array<string, list<string>> */
    public array $mar_default_times = [
        'qd' => ['08:00'],
        'bid' => ['08:00', '20:00'],
        'tid' => ['08:00', '14:00', '20:00'],
        'qid' => ['06:00', '12:00', '18:00', '22:00'],
    ];

    public bool $mar_reminders_enabled = true;

    public int $mar_reminders_lead_minutes = 15;

    public int $mar_reminders_grace_minutes = 30;

    /** @var array<int, string> */
    public array $mar_reminders_channels = ['database', 'mail'];

    public string $default_encounter_type = 'outpatient';

    public string $default_encounter_class = 'routine';

    public string $default_encounter_status = 'planned';

    public bool $default_requires_payment_before = false;

    public bool $default_requires_prescription = false;

    public bool $controlled_substances_witness_required = true;

    public static function group(): string
    {
        return 'clinical';
    }
}
