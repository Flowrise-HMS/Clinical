<?php

namespace Modules\Clinical\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Clinical\Models\DiagnosisCode;

class DiagnosisCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // Infectious & Parasitic
            ['code' => 'A09', 'description' => 'Infectious gastroenteritis / diarrhoea', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'A15', 'description' => 'Respiratory tuberculosis', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'A74', 'description' => 'Chlamydial infection', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B00', 'description' => 'Herpesviral infection', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B01', 'description' => 'Varicella (chickenpox)', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B02', 'description' => 'Zoster (shingles)', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B05', 'description' => 'Measles', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B15', 'description' => 'Hepatitis A', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B16', 'description' => 'Hepatitis B', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B17', 'description' => 'Hepatitis C', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B20', 'description' => 'HIV disease', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B50', 'description' => 'Malaria (Plasmodium falciparum)', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B54', 'description' => 'Unspecified malaria', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B77', 'description' => 'Ascariasis', 'category' => 'Infectious', 'nhis_covered' => true],
            ['code' => 'B86', 'description' => 'Scabies', 'category' => 'Infectious', 'nhis_covered' => true],

            // Neoplasms
            ['code' => 'C50', 'description' => 'Malignant neoplasm of breast', 'category' => 'Neoplasms', 'nhis_covered' => true],
            ['code' => 'C53', 'description' => 'Malignant neoplasm of cervix uteri', 'category' => 'Neoplasms', 'nhis_covered' => true],
            ['code' => 'C61', 'description' => 'Malignant neoplasm of prostate', 'category' => 'Neoplasms', 'nhis_covered' => true],
            ['code' => 'D25', 'description' => 'Leiomyoma of uterus (fibroids)', 'category' => 'Neoplasms', 'nhis_covered' => true],
            ['code' => 'D64', 'description' => 'Anaemia (unspecified)', 'category' => 'Neoplasms', 'nhis_covered' => true],

            // Endocrine & Metabolic
            ['code' => 'E10', 'description' => 'Type 1 diabetes mellitus', 'category' => 'Endocrine', 'nhis_covered' => true],
            ['code' => 'E11', 'description' => 'Type 2 diabetes mellitus', 'category' => 'Endocrine', 'nhis_covered' => true],
            ['code' => 'E14', 'description' => 'Unspecified diabetes mellitus', 'category' => 'Endocrine', 'nhis_covered' => true],
            ['code' => 'E66', 'description' => 'Obesity', 'category' => 'Endocrine', 'nhis_covered' => true],
            ['code' => 'E78', 'description' => 'Hyperlipidaemia', 'category' => 'Endocrine', 'nhis_covered' => true],
            ['code' => 'E86', 'description' => 'Dehydration', 'category' => 'Endocrine', 'nhis_covered' => true],

            // Mental & Behavioural
            ['code' => 'F32', 'description' => 'Depressive episode', 'category' => 'Mental', 'nhis_covered' => true],
            ['code' => 'F41', 'description' => 'Anxiety disorder', 'category' => 'Mental', 'nhis_covered' => true],
            ['code' => 'F43', 'description' => 'Reaction to severe stress / adjustment disorder', 'category' => 'Mental', 'nhis_covered' => true],
            ['code' => 'F51', 'description' => 'Nonorganic sleep disorder', 'category' => 'Mental', 'nhis_covered' => true],

            // Nervous System
            ['code' => 'G40', 'description' => 'Epilepsy', 'category' => 'Nervous', 'nhis_covered' => true],
            ['code' => 'G43', 'description' => 'Migraine', 'category' => 'Nervous', 'nhis_covered' => true],
            ['code' => 'G44', 'description' => 'Tension-type headache', 'category' => 'Nervous', 'nhis_covered' => true],

            // Eye & Adnexa
            ['code' => 'H10', 'description' => 'Conjunctivitis', 'category' => 'Eye', 'nhis_covered' => true],
            ['code' => 'H25', 'description' => 'Senile cataract', 'category' => 'Eye', 'nhis_covered' => true],
            ['code' => 'H52', 'description' => 'Refractive error', 'category' => 'Eye', 'nhis_covered' => true],

            // Ear & Mastoid
            ['code' => 'H60', 'description' => 'Otitis externa', 'category' => 'Ear', 'nhis_covered' => true],
            ['code' => 'H65', 'description' => 'Otitis media (non-suppurative)', 'category' => 'Ear', 'nhis_covered' => true],
            ['code' => 'H66', 'description' => 'Otitis media (suppurative)', 'category' => 'Ear', 'nhis_covered' => true],
            ['code' => 'H90', 'description' => 'Hearing loss (conductive / sensorineural)', 'category' => 'Ear', 'nhis_covered' => true],

            // Circulatory System
            ['code' => 'I10', 'description' => 'Essential (primary) hypertension', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I11', 'description' => 'Hypertensive heart disease', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I20', 'description' => 'Angina pectoris', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I21', 'description' => 'Acute myocardial infarction', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I25', 'description' => 'Chronic ischaemic heart disease', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I48', 'description' => 'Atrial fibrillation', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I50', 'description' => 'Heart failure', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I64', 'description' => 'Stroke (non-specified as haemorrhage or infarct)', 'category' => 'Circulatory', 'nhis_covered' => true],
            ['code' => 'I95', 'description' => 'Hypotension', 'category' => 'Circulatory', 'nhis_covered' => true],

            // Respiratory System
            ['code' => 'J00', 'description' => 'Acute nasopharyngitis (common cold)', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J01', 'description' => 'Acute sinusitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J02', 'description' => 'Acute pharyngitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J03', 'description' => 'Acute tonsillitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J04', 'description' => 'Acute laryngitis / tracheitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J06', 'description' => 'Acute upper respiratory infection (unspecified)', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J15', 'description' => 'Bacterial pneumonia', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J18', 'description' => 'Pneumonia (unspecified organism)', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J20', 'description' => 'Acute bronchitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J30', 'description' => 'Allergic rhinitis (hay fever)', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J32', 'description' => 'Chronic sinusitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J40', 'description' => 'Unspecified bronchitis (not acute or chronic)', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J42', 'description' => 'Chronic bronchitis', 'category' => 'Respiratory', 'nhis_covered' => true],
            ['code' => 'J45', 'description' => 'Asthma', 'category' => 'Respiratory', 'nhis_covered' => true],

            // Digestive System
            ['code' => 'K04', 'description' => 'Pulpitis / periapical abscess', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K05', 'description' => 'Gingivitis / periodontitis', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K08', 'description' => 'Loss of teeth / denture problems', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K21', 'description' => 'Gastro-oesophageal reflux disease', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K25', 'description' => 'Gastric ulcer', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K26', 'description' => 'Duodenal ulcer', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K29', 'description' => 'Gastritis / duodenitis', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K30', 'description' => 'Functional dyspepsia', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K35', 'description' => 'Acute appendicitis', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K40', 'description' => 'Inguinal hernia', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K52', 'description' => 'Non-infective gastroenteritis / colitis', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K59', 'description' => 'Constipation', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K76', 'description' => 'Chronic liver disease (not alcoholic)', 'category' => 'Digestive', 'nhis_covered' => true],
            ['code' => 'K80', 'description' => 'Cholelithiasis (gallstones)', 'category' => 'Digestive', 'nhis_covered' => true],

            // Skin & Subcutaneous
            ['code' => 'L02', 'description' => 'Cutaneous abscess / furuncle', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L03', 'description' => 'Cellulitis', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L20', 'description' => 'Atopic dermatitis (eczema)', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L23', 'description' => 'Allergic contact dermatitis', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L29', 'description' => 'Pruritus (itching)', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L30', 'description' => 'Unspecified dermatitis', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L40', 'description' => 'Psoriasis', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L70', 'description' => 'Acne vulgaris', 'category' => 'Skin', 'nhis_covered' => true],
            ['code' => 'L89', 'description' => 'Pressure ulcer (bedsore)', 'category' => 'Skin', 'nhis_covered' => true],

            // Musculoskeletal & Connective Tissue
            ['code' => 'M06', 'description' => 'Rheumatoid arthritis (unspecified)', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M10', 'description' => 'Gout', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M16', 'description' => 'Coxarthrosis (hip osteoarthritis)', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M17', 'description' => 'Gonarthrosis (knee osteoarthritis)', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M19', 'description' => 'Osteoarthritis (unspecified)', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M25', 'description' => 'Joint pain (unspecified)', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M42', 'description' => 'Spinal osteochondrosis', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M43', 'description' => 'Low back pain / lumbago', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M54', 'description' => 'Dorsalgia / back pain (unspecified)', 'category' => 'Musculoskeletal', 'nhis_covered' => true],
            ['code' => 'M79', 'description' => 'Myalgia / soft tissue pain', 'category' => 'Musculoskeletal', 'nhis_covered' => true],

            // Genitourinary
            ['code' => 'N10', 'description' => 'Acute pyelonephritis', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N18', 'description' => 'Chronic kidney disease', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N20', 'description' => 'Renal / ureteric calculus', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N30', 'description' => 'Cystitis (urinary tract infection)', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N39', 'description' => 'Urinary tract infection (unspecified)', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N40', 'description' => 'Benign prostatic hyperplasia', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N70', 'description' => 'Salpingitis / oophoritis (PID)', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N73', 'description' => 'Pelvic inflammatory disease (unspecified)', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N76', 'description' => 'Vaginitis / vulvitis', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N92', 'description' => 'Menstrual disorder (heavy/frequent/irregular)', 'category' => 'Genitourinary', 'nhis_covered' => true],
            ['code' => 'N95', 'description' => 'Menopausal disorder', 'category' => 'Genitourinary', 'nhis_covered' => true],

            // Pregnancy & Childbirth
            ['code' => 'O10', 'description' => 'Pre-existing hypertension in pregnancy', 'category' => 'Obstetric', 'nhis_covered' => true],
            ['code' => 'O13', 'description' => 'Gestational hypertension', 'category' => 'Obstetric', 'nhis_covered' => true],
            ['code' => 'O14', 'description' => 'Pre-eclampsia', 'category' => 'Obstetric', 'nhis_covered' => true],
            ['code' => 'O20', 'description' => 'Threatened abortion (vaginal bleeding in early pregnancy)', 'category' => 'Obstetric', 'nhis_covered' => true],
            ['code' => 'O80', 'description' => 'Normal delivery (singleton, spontaneous)', 'category' => 'Obstetric', 'nhis_covered' => true],
            ['code' => 'O82', 'description' => 'Delivery by caesarean section', 'category' => 'Obstetric', 'nhis_covered' => true],
            ['code' => 'O99', 'description' => 'Maternal disease complicating pregnancy', 'category' => 'Obstetric', 'nhis_covered' => true],

            // Perinatal
            ['code' => 'P03', 'description' => 'Newborn affected by complications of labour', 'category' => 'Perinatal', 'nhis_covered' => true],
            ['code' => 'P07', 'description' => 'Preterm / low birth weight newborn', 'category' => 'Perinatal', 'nhis_covered' => true],
            ['code' => 'P23', 'description' => 'Congenital pneumonia', 'category' => 'Perinatal', 'nhis_covered' => true],
            ['code' => 'P59', 'description' => 'Neonatal jaundice', 'category' => 'Perinatal', 'nhis_covered' => true],

            // Congenital
            ['code' => 'Q21', 'description' => 'Congenital heart septal defect', 'category' => 'Congenital', 'nhis_covered' => true],

            // Symptoms / Signs
            ['code' => 'R05', 'description' => 'Cough', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R06', 'description' => 'Dyspnoea / breathlessness', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R07', 'description' => 'Chest pain (unspecified)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R10', 'description' => 'Abdominal pain', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R11', 'description' => 'Nausea / vomiting', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R31', 'description' => 'Haematuria (blood in urine)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R50', 'description' => 'Fever of unknown origin', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R51', 'description' => 'Headache', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R52', 'description' => 'Pain (unspecified / chronic)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R53', 'description' => 'Malaise / fatigue', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R55', 'description' => 'Syncope / collapse (fainting)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R57', 'description' => 'Shock (unspecified)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R59', 'description' => 'Lymphadenopathy (swollen lymph nodes)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R80', 'description' => 'Proteinuria (protein in urine)', 'category' => 'Symptoms', 'nhis_covered' => true],
            ['code' => 'R81', 'description' => 'Glycosuria (sugar in urine)', 'category' => 'Symptoms', 'nhis_covered' => true],

            // Injury & Poisoning
            ['code' => 'S00', 'description' => 'Superficial injury of head', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S01', 'description' => 'Open wound of head', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S20', 'description' => 'Superficial injury of thorax', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S30', 'description' => 'Superficial injury of abdomen / back', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S40', 'description' => 'Superficial injury of shoulder / upper arm', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S50', 'description' => 'Superficial injury of forearm / elbow', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S60', 'description' => 'Superficial injury of wrist / hand', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S70', 'description' => 'Superficial injury of hip / thigh', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S80', 'description' => 'Superficial injury of lower leg', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S90', 'description' => 'Superficial injury of ankle / foot', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'S93', 'description' => 'Ankle sprain / dislocation', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'T14', 'description' => 'Injury of unspecified body region', 'category' => 'Injury', 'nhis_covered' => true],
            ['code' => 'T78', 'description' => 'Allergic reaction (unspecified)', 'category' => 'Injury', 'nhis_covered' => true],

            // External Causes
            ['code' => 'V89', 'description' => 'Road traffic accident (unspecified)', 'category' => 'External', 'nhis_covered' => true],
            ['code' => 'W19', 'description' => 'Fall (unspecified)', 'category' => 'External', 'nhis_covered' => true],
            ['code' => 'X59', 'description' => 'Exposure to unspecified factor causing injury', 'category' => 'External', 'nhis_covered' => true],

            // Special / Screening
            ['code' => 'Z00', 'description' => 'General medical examination / check-up', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z01', 'description' => 'Special examination (eye, dental, gynae)', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z03', 'description' => 'Observation for suspected disease', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z23', 'description' => 'Immunisation (single bacterial/viral)', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z30', 'description' => 'Contraceptive management', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z32', 'description' => 'Pregnancy test / confirmation', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z34', 'description' => 'Normal pregnancy supervision', 'category' => 'Screening', 'nhis_covered' => true],
            ['code' => 'Z36', 'description' => 'Antenatal screening', 'category' => 'Screening', 'nhis_covered' => true],
        ];

        foreach ($codes as $code) {
            DiagnosisCode::firstOrCreate(
                ['code' => $code['code'], 'source' => 'nhis'],
                $code
            );
        }
    }
}
