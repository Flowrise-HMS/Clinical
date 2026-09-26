<?php

namespace Modules\Clinical\Classes\Support\Triage;

use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;

/**
 * SATS clinical discriminators (SATS training manual 2012, adult and paediatric charts).
 * Any emergency sign makes the patient Red, a very urgent sign Orange and an urgent
 * sign Yellow, whatever the TEWS.
 */
final class SatsDiscriminators
{
    /**
     * @var array<string, array<string, string>> category => [key => label]
     */
    private const ADULT = [
        'red' => [
            'airway_obstructed' => 'Obstructed airway / not breathing',
            'seizure_current' => 'Seizure - current',
            'burn_facial_inhalation' => 'Burn - facial / inhalation',
            'hypoglycaemia_under_3' => 'Hypoglycaemia - glucose less than 3 mmol/L',
            'cardiac_arrest' => 'Cardiac arrest',
        ],
        'orange' => [
            'high_energy_transfer' => 'High energy transfer (severe mechanism of injury)',
            'shortness_of_breath_acute' => 'Shortness of breath - acute',
            'reduced_consciousness' => 'Level of consciousness reduced / confused',
            'coughing_blood' => 'Coughing blood',
            'chest_pain' => 'Chest pain',
            'stabbed_neck' => 'Stabbed neck',
            'haemorrhage_uncontrolled' => 'Haemorrhage - uncontrolled (arterial bleed)',
            'seizure_post_ictal' => 'Seizure - post ictal',
            'focal_neurology_acute' => 'Focal neurology - acute (stroke)',
            'aggression' => 'Aggression',
            'threatened_limb' => 'Threatened limb',
            'eye_injury' => 'Eye injury',
            'dislocation_large_joint' => 'Dislocation of larger joint (not finger or toe)',
            'fracture_compound' => 'Fracture - compound (break in skin)',
            'burn_over_20' => 'Burn over 20%',
            'burn_electrical' => 'Burn - electrical',
            'burn_circumferential' => 'Burn - circumferential',
            'burn_chemical' => 'Burn - chemical',
            'poisoning_overdose' => 'Poisoning / overdose',
            'diabetic_ketonuria' => 'Diabetic - glucose over 11 mmol/L and ketonuria',
            'vomiting_fresh_blood' => 'Vomiting fresh blood',
            'pregnancy_abdominal_trauma' => 'Pregnancy and abdominal trauma',
            'pregnancy_abdominal_pain' => 'Pregnancy and abdominal pain',
            'severe_pain' => 'Severe pain',
        ],
        'yellow' => [
            'haemorrhage_controlled' => 'Haemorrhage - controlled',
            'dislocation_finger_toe' => 'Dislocation of finger or toe',
            'fracture_closed' => 'Fracture - closed (no break in skin)',
            'burn_other' => 'Burn - other',
            'abdominal_pain' => 'Abdominal pain',
            'diabetic_over_17' => 'Diabetic - glucose over 17 mmol/L (no ketonuria)',
            'vomiting_persistently' => 'Vomiting persistently',
            'pregnancy_trauma' => 'Pregnancy and trauma',
            'pregnancy_pv_bleed' => 'Pregnancy and PV bleed',
            'moderate_pain' => 'Moderate pain',
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const PAEDIATRIC = [
        'red' => [
            'not_breathing' => 'Not breathing or reported apnoea',
            'obstructed_breathing' => 'Obstructed breathing',
            'cyanosis_or_spo2_under_92' => 'Central cyanosis or SpO2 less than 92%',
            'severe_respiratory_distress' => 'Respiratory distress (severe)',
            'shock_signs' => 'Cold hands with 2+ of: weak fast pulse, capillary refill over 3 s, lethargy',
            'haemorrhage_uncontrolled' => 'Uncontrolled bleeding (not nose bleed)',
            'convulsing' => 'Convulsing or immediately post-ictal and not alert',
            'coma' => 'AVPU responds only to pain or unresponsive; confusion',
            'severe_dehydration' => 'Diarrhoea with 2+ of: lethargy / floppy infant, very sunken eyes, skin pinch 2 s or longer',
            'burn_facial_inhalation' => 'Facial / inhalation burn',
            'hypoglycaemia_under_3' => 'Hypoglycaemia - glucose less than 3 mmol/L',
            'purpuric_rash' => 'Purpuric rash',
        ],
        'orange' => [
            'tiny_baby' => 'Tiny baby - younger than 2 months',
            'inconsolable_crying' => 'Inconsolable crying / severe pain',
            'more_sleepy' => 'Presenting complaint - more sleepy than normal',
            'poisoning_overdose' => 'Poisoning or overdose',
            'focal_neurology_acute' => 'Focal neurology - acute',
            'severe_mechanism_of_injury' => 'Severe mechanism of injury',
            'burns_10_percent' => 'Burns 10% or more, circumferential, electrical or chemical',
            'eye_injury' => 'Eye injury',
            'fracture_open_threatened_limb' => 'Fracture - open, or threatened limb',
            'dislocation_large_joint' => 'Dislocation of larger joint (not finger or toe)',
        ],
        'yellow' => [
            'some_respiratory_distress' => 'Some respiratory distress',
            'some_dehydration' => 'Some dehydration: diarrhoea or vomiting with 1+ of: restless / irritable, thirsty / less urine, crying without tears, skin pinch slow',
            'unable_to_drink' => 'Unable to drink / feed or vomits everything',
            'malnutrition_wasting' => 'Malnutrition - visible severe wasting',
            'malnutrition_oedema' => 'Malnutrition - pitting oedema of both feet',
            'unwell_diabetic' => 'Unwell child with known diabetes',
            'burn_other' => 'Any other burn',
            'fracture_closed' => 'Closed fracture',
            'dislocation_finger_toe' => 'Dislocation of finger or toe',
        ],
    ];

    /**
     * @return array<string, string> key => label for one category
     */
    public static function options(TriageAgeBand $band, TriageCategory $category): array
    {
        return self::catalogue($band)[$category->value] ?? [];
    }

    /**
     * The most severe category among the selected discriminators, or null when none apply.
     *
     * @param  array<int, string>  $keys
     */
    public static function categoryFor(TriageAgeBand $band, array $keys): ?TriageCategory
    {
        $found = [];

        foreach (self::catalogue($band) as $category => $items) {
            if (array_intersect($keys, array_keys($items)) !== []) {
                $found[] = enum_from(TriageCategory::class, $category);
            }
        }

        return TriageCategory::mostSevere(...$found);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, label: string, category: TriageCategory}>
     */
    public static function describe(TriageAgeBand $band, array $keys): array
    {
        $described = [];

        foreach (self::catalogue($band) as $category => $items) {
            foreach ($items as $key => $label) {
                if (in_array($key, $keys, true)) {
                    $described[] = ['key' => $key, 'label' => $label, 'category' => enum_from(TriageCategory::class, $category)];
                }
            }
        }

        return $described;
    }

    /**
     * Keeps only keys that exist for the band, so a band change cannot leave stale selections.
     *
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    public static function filter(TriageAgeBand $band, array $keys): array
    {
        $valid = array_merge(...array_map('array_keys', array_values(self::catalogue($band))));

        return array_values(array_intersect(array_map('strval', array_filter($keys, 'is_scalar')), $valid));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function catalogue(TriageAgeBand $band): array
    {
        return $band->isPaediatric() ? self::PAEDIATRIC : self::ADULT;
    }
}
