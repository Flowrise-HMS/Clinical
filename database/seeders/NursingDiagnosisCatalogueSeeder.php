<?php

namespace Modules\Clinical\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class NursingDiagnosisCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $diagnoses = [
            [
                'code' => 'NANDA-00132',
                'label' => 'Acute Pain',
                'definition' => 'An unpleasant sensory and emotional experience associated with actual or potential tissue damage, with sudden or slow onset and an anticipated end.',
            ],
            [
                'code' => 'NANDA-00004',
                'label' => 'Risk for Infection',
                'definition' => 'Susceptible to invasion and multiplication of pathogenic organisms, which may compromise health.',
            ],
            [
                'code' => 'NANDA-00046',
                'label' => 'Impaired Skin Integrity',
                'definition' => 'Damage to the epidermis and/or dermis.',
            ],
            [
                'code' => 'NANDA-00146',
                'label' => 'Anxiety',
                'definition' => 'A vague, uneasy feeling of discomfort or dread accompanied by an autonomic response to an anticipated threat.',
            ],
            [
                'code' => 'NANDA-00126',
                'label' => 'Deficient Knowledge',
                'definition' => 'Absence or deficiency of cognitive information related to a specific topic.',
            ],
            [
                'code' => 'NANDA-00032',
                'label' => 'Ineffective Breathing Pattern',
                'definition' => 'Inspiration and/or expiration that does not provide adequate ventilation.',
            ],
            [
                'code' => 'NANDA-00155',
                'label' => 'Risk for Falls',
                'definition' => 'Susceptible to an increased likelihood of falling that may cause physical harm.',
            ],
            [
                'code' => 'NANDA-00002',
                'label' => 'Imbalanced Nutrition',
                'definition' => 'Nutrient intake that is insufficient or excessive to meet metabolic needs.',
            ],
        ];

        foreach ($diagnoses as $diagnosis) {
            NursingDiagnosisCatalogue::query()->updateOrCreate(
                ['code' => $diagnosis['code']],
                [...$diagnosis, 'is_active' => true],
            );
        }
    }
}
