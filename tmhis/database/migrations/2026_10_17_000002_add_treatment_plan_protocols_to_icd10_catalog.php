<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('icd10_catalog')) {
            Schema::table('icd10_catalog', function (Blueprint $table) {
                if (!Schema::hasColumn('icd10_catalog', 'DefaultGoal')) {
                    $table->string('DefaultGoal', 255)->nullable()->after('DefaultSeverity');
                }
                if (!Schema::hasColumn('icd10_catalog', 'DefaultLifestyle')) {
                    $table->text('DefaultLifestyle')->nullable()->after('DefaultGoal');
                }
                if (!Schema::hasColumn('icd10_catalog', 'DefaultMedication')) {
                    $table->text('DefaultMedication')->nullable()->after('DefaultLifestyle');
                }
                if (!Schema::hasColumn('icd10_catalog', 'DefaultFollowUp')) {
                    $table->string('DefaultFollowUp', 100)->nullable()->after('DefaultMedication');
                }
                if (!Schema::hasColumn('icd10_catalog', 'DefaultFollowUpDays')) {
                    $table->integer('DefaultFollowUpDays')->default(14)->after('DefaultFollowUp');
                }
            });

            // Populate clinical evidence-based treatment plans for common diagnoses
            $protocols = [
                // Respiratory
                'J02.9' => [
                    'goal'      => 'Symptom resolution, eradicate pharyngeal pathogen, and prevent suppurative complications.',
                    'lifestyle' => 'Warm saline gargle 3-4x daily; Oral fluid intake >2.5L/day; Soft non-acidic diet; Rest.',
                    'meds'      => 'Paracetamol 500mg PO q6h PRN for throat pain/fever; Amoxicillin 500mg PO TID x 7 days (if bacterial); Throat lozenges.',
                    'follow_up' => '5-7 Days',
                    'days'      => 7
                ],
                'J03.9' => [
                    'goal'      => 'Reduce tonsillar edema, eliminate exudate, and restore comfortable oral swallowing.',
                    'lifestyle' => 'Warm salt water gargles; Soft, cool foods; Avoid spicy, hot, and acidic foods; Adequate rest.',
                    'meds'      => 'Co-amoxiclav 625mg PO BID x 7 days OR Amoxicillin 500mg PO TID x 7 days; Paracetamol 500mg PO q6h PRN.',
                    'follow_up' => '5-7 Days',
                    'days'      => 7
                ],
                'J00' => [
                    'goal'      => 'Relieve nasal congestion and rhinorrhea, prevent secondary sinus infection.',
                    'lifestyle' => 'Steam inhalation twice daily; Increase oral fluids; Vitamin C rich fruits; Proper rest.',
                    'meds'      => 'Paracetamol 500mg PO q6h PRN; Phenylephrine + Chlorphenamine PO TID; Normal saline nasal spray PRN.',
                    'follow_up' => '5-7 Days',
                    'days'      => 7
                ],
                'J06.9' => [
                    'goal'      => 'Alleviate upper airway mucosal inflammation, clear congestion, and ensure swift clinical recovery.',
                    'lifestyle' => 'Adequate oral hydration (>2L/day); Rest; Avoid cold drinks and dusty environments.',
                    'meds'      => 'Paracetamol 500mg PO q6h PRN for fever/body aches; Cetirizine 10mg PO OD at bedtime; Saline nasal drops.',
                    'follow_up' => '5-7 Days',
                    'days'      => 7
                ],
                'J20.9' => [
                    'goal'      => 'Reduce bronchial tree inflammation, facilitate sputum expectoration, and relieve paroxysmal cough.',
                    'lifestyle' => 'Adequate oral hydration to thin secretions; Humidified room air; Avoid tobacco smoke exposure.',
                    'meds'      => 'Ambroxol 30mg PO TID x 7 days; Salbutamol 2mg PO TID if bronchospasm present; Paracetamol 500mg PRN.',
                    'follow_up' => '1-2 Weeks',
                    'days'      => 10
                ],
                'J18.9' => [
                    'goal'      => 'Eradicate pulmonary infection, clear alveolar consolidation, and maintain SpO2 > 95%.',
                    'lifestyle' => 'Chest physiotherapy / deep breathing exercises; High-calorie, high-protein diet; Bed rest.',
                    'meds'      => 'Amoxicillin-Clavulanic Acid 625mg PO TID x 7 days + Azithromycin 500mg PO OD x 3 days; Salbutamol nebulization PRN.',
                    'follow_up' => '3-5 Days',
                    'days'      => 5
                ],
                'J45.9' => [
                    'goal'      => 'Reverse bronchospasm, prevent nocturnal wheezing, and maintain normal daily physical activity.',
                    'lifestyle' => 'Identify and eliminate environmental allergen triggers (dust, fur, smoke); Follow personal Asthma Action Plan.',
                    'meds'      => 'Salbutamol MDI 2 puffs q4-6h PRN wheezing; Budesonide/Formoterol maintenance inhaler 1 puff BID.',
                    'follow_up' => '1-2 Weeks',
                    'days'      => 14
                ],
                'J45.901' => [
                    'goal'      => 'Rapidly relieve acute bronchospasm, improve oxygenation, and abort asthma flare.',
                    'lifestyle' => 'Immediate trigger avoidance; Rest in upright position; Monitor peak expiratory flow rate (PEFR).',
                    'meds'      => 'Salbutamol nebulization 2.5mg q20min x 3 doses; Prednisone 20mg PO OD x 5 days; Regular maintenance inhaler.',
                    'follow_up' => '3-5 Days',
                    'days'      => 4
                ],

                // Infectious & Dengue
                'A90' => [
                    'goal'      => 'Maintain normovolemic fluid status, monitor hematocrit/platelet progression, and prevent complications.',
                    'lifestyle' => 'Strict oral fluid replacement with ORS, fruit juice, coconut water; Mosquito netting; Absolute bed rest.',
                    'meds'      => 'Paracetamol 500mg PO q6h PRN (Strictly AVOID NSAIDs/Aspirin/Mefenamic); Oral Rehydration Salts (ORS) ad libitum.',
                    'follow_up' => 'Daily CBC & Platelet Audit',
                    'days'      => 1
                ],
                'A91' => [
                    'goal'      => 'Immediate hospital stabilization, monitor plasma leakage, and maintain stable pulse pressure and urine output.',
                    'lifestyle' => 'Inpatient observation; Strict fluid balance input/output recording; Bed rest.',
                    'meds'      => 'Intravenous fluid therapy (IVF Plain LRS / D5LRS); Paracetamol IV/PO PRN fever; Daily serial CBC/platelet monitoring.',
                    'follow_up' => 'Daily Inpatient Rounds',
                    'days'      => 1
                ],
                'A09' => [
                    'goal'      => 'Restore intestinal electrolyte balance, halt dehydration, and normalize stool consistency.',
                    'lifestyle' => 'BRAT diet (Banana, Rice, Applesauce, Toast); Avoid fatty, dairy, and caffeine products; Frequent sips of ORS.',
                    'meds'      => 'Oral Rehydration Salts (ORS) 1 sachet per 1L boiled water; Zinc Sulfate 20mg PO OD x 14 days; Paracetamol 500mg PRN.',
                    'follow_up' => '3-5 Days',
                    'days'      => 3
                ],
                'A09.0' => [
                    'goal'      => 'Manage mild fluid loss, control nausea, and maintain adequate oral hydration.',
                    'lifestyle' => 'Continue normal age-appropriate feeding; Oral fluid intake with ORS after every loose bowel movement.',
                    'meds'      => 'Oral Rehydration Salts (ORS); Zinc Sulfate 20mg OD x 14 days; Probiotics 1 cap PO BID.',
                    'follow_up' => '3 Days',
                    'days'      => 3
                ],
                'A09.1' => [
                    'goal'      => 'Correct moderate fluid deficit, restore tissue turgor, and prevent circulatory collapse.',
                    'lifestyle' => 'Close fluid replacement therapy; Rice water / coconut water; Monitored oral fluid protocol.',
                    'meds'      => 'Oral Rehydration Therapy (75ml/kg over 4 hours); Zinc 20mg OD; Metoclopramide 10mg PO PRN vomiting.',
                    'follow_up' => '2-3 Days',
                    'days'      => 2
                ],
                'A01.0' => [
                    'goal'      => 'Eradicate Salmonella enteric infection, clear systemic pyrexia, and prevent intestinal perforation.',
                    'lifestyle' => 'Boiled water consumption only; Strict food sanitation; High-protein soft diet; Adequate rest.',
                    'meds'      => 'Ciprofloxacin 500mg PO BID x 10 days OR Azithromycin 1g PO Day 1 then 500mg OD x 6 days; Paracetamol 500mg PRN.',
                    'follow_up' => '5-7 Days',
                    'days'      => 7
                ],
                'A06.0' => [
                    'goal'      => 'Eliminate Entamoeba histolytica trophozoites and cysts, relieve tenesmus, and heal mucosal ulcerations.',
                    'lifestyle' => 'Strict personal and food hygiene; Drink treated/boiled water; Avoid raw vegetables during treatment.',
                    'meds'      => 'Metronidazole 500mg PO TID x 7-10 days; Followed by oral rehydration and gut supportive care.',
                    'follow_up' => '7-10 Days',
                    'days'      => 8
                ],
                'A15.0' => [
                    'goal'      => 'Achieve sputum conversion, eliminate active TB bacilli, and complete DOTS treatment protocol.',
                    'lifestyle' => 'Well-ventilated room; High-protein diet; Cover mouth when coughing; DOTS adherence counseling.',
                    'meds'      => 'Fixed-dose combination anti-TB regimen (Rifampicin + Isoniazid + Pyrazinamide + Ethambutol) daily on an empty stomach.',
                    'follow_up' => 'Monthly DOTS Review',
                    'days'      => 30
                ],

                // Cardiovascular
                'I10' => [
                    'goal'      => 'Achieve target blood pressure < 130/80 mmHg and reduce long-term cardiovascular risk.',
                    'lifestyle' => 'DASH diet: restrict sodium < 2g/day; 30 minutes daily aerobic physical activity; Weight control; Avoid smoking.',
                    'meds'      => 'Amlodipine 5mg PO OD morning OR Losartan 50mg PO OD morning; Record daily morning blood pressure log.',
                    'follow_up' => '2-4 Weeks',
                    'days'      => 14
                ],
                'I11.9' => [
                    'goal'      => 'Control systemic pressure, regress left ventricular hypertrophy, and prevent onset of congestive failure.',
                    'lifestyle' => 'Strict low-salt low-fat diet; Fluid moderation; Maintain ideal body weight; Smoking cessation.',
                    'meds'      => 'Losartan 50mg PO OD + Amlodipine 5mg PO OD; Maintain daily blood pressure and heart rate monitoring.',
                    'follow_up' => '2 Weeks',
                    'days'      => 14
                ],
                'I20.9' => [
                    'goal'      => 'Prevent angina episodes, optimize myocardial perfusion, and improve exercise tolerance.',
                    'lifestyle' => 'Cardiac rehabilitation exercise; Low-cholesterol Mediterranean diet; Avoid sudden cold exposure and unaccustomed strain.',
                    'meds'      => 'Aspirin 81mg PO OD + Atorvastatin 20mg PO OD HS + Metoprolol 25mg PO BID; Sublingual Nitroglycerin PRN chest pain.',
                    'follow_up' => '2 Weeks',
                    'days'      => 14
                ],
                'I25.10' => [
                    'goal'      => 'Stabilize coronary plaque, optimize LDL-C < 70 mg/dL, and prevent acute coronary events.',
                    'lifestyle' => 'Heart-healthy diet; 150 minutes/week moderate aerobic activity; Smoking cessation; Stress reduction.',
                    'meds'      => 'Atorvastatin 40mg PO OD HS; Aspirin 81mg PO OD after breakfast; Clopidogrel 75mg PO OD; Metoprolol 50mg PO BID.',
                    'follow_up' => '1 Month',
                    'days'      => 30
                ],
                'I50.9' => [
                    'goal'      => 'Relieve congestion, improve ventricular performance, and maintain euvolemic body weight.',
                    'lifestyle' => 'Strict fluid restriction to 1.5L/day; Sodium < 1.5g/day; Daily morning weight tracking (report >2kg gain in 48h).',
                    'meds'      => 'Furosemide 20-40mg PO OD morning; Losartan 25mg PO OD; Carvedilol 6.25mg PO BID; Spironolactone 25mg PO OD.',
                    'follow_up' => '1-2 Weeks',
                    'days'      => 10
                ],
                'R00.2' => [
                    'goal'      => 'Identify and treat underlying rhythm trigger, stabilize heart rate 60-90 bpm.',
                    'lifestyle' => 'Eliminate caffeinated drinks, energy drinks, and tobacco; Stress management and mindfulness breathing.',
                    'meds'      => 'Metoprolol Tartrate 25mg PO BID; 12-lead ECG audit and serum potassium/magnesium assessment.',
                    'follow_up' => '1-2 Weeks',
                    'days'      => 14
                ],

                // Endocrine
                'E11.9' => [
                    'goal'      => 'Maintain glycemic control: Fasting Blood Sugar 80-130 mg/dL and HbA1c < 7.0%.',
                    'lifestyle' => 'Low glycemic index meals; Restrict refined sugars; 150 min/week structured physical exercise; Daily foot inspection.',
                    'meds'      => 'Metformin 500mg PO BID with meals; Monitor self-monitoring blood glucose (SMBG) log twice weekly.',
                    'follow_up' => '1 Month',
                    'days'      => 30
                ],
                'E10.9' => [
                    'goal'      => 'Optimize physiological basal-bolus insulin coverage and prevent acute hypoglycemia or ketoacidosis.',
                    'lifestyle' => 'Carbohydrate counting; Consistent meal timings; Carry fast-acting glucose tablets; Daily foot inspection.',
                    'meds'      => 'Basal-bolus insulin regimen (Insulin Glargine + Insulin Lispro); Blood glucose monitoring QID (before meals and bedtime).',
                    'follow_up' => '2-4 Weeks',
                    'days'      => 21
                ],
                'E78.0' => [
                    'goal'      => 'Reduce serum LDL cholesterol by >= 50% and lower cardiovascular atherogenic risk.',
                    'lifestyle' => 'Heart-healthy diet: reduce saturated and trans fats; Increase dietary soluble fiber; Regular physical exercise.',
                    'meds'      => 'Atorvastatin 20mg PO OD at bedtime; Repeat fasting lipid panel after 8-12 weeks.',
                    'follow_up' => '8-12 Weeks',
                    'days'      => 60
                ],

                // Digestive
                'K29.7' => [
                    'goal'      => 'Suppress gastric acid secretion, heal eroded mucosal lining, and relieve epigastric discomfort.',
                    'lifestyle' => 'Avoid skipping meals; Small, frequent meals; Avoid spicy, acidic, oily foods, alcohol, and caffeine.',
                    'meds'      => 'Omeprazole 20-40mg PO OD 30 minutes before breakfast x 14 days; Antacid suspension 10ml PO PRN after meals.',
                    'follow_up' => '2 Weeks',
                    'days'      => 14
                ],
                'K21.9' => [
                    'goal'      => 'Eliminate acid regurgitation and substernal pyrosis, prevent esophageal mucosal erosion.',
                    'lifestyle' => 'Do not lie down within 3 hours after meals; Elevate head of bed 15cm; Avoid tight clothing; Weight reduction.',
                    'meds'      => 'Esomeprazole 40mg PO OD 30 mins before breakfast x 14-28 days; Aluminum/Magnesium Antacid 10ml PRN.',
                    'follow_up' => '2-4 Weeks',
                    'days'      => 21
                ],
                'K27.9' => [
                    'goal'      => 'Promote complete peptic mucosal ulcer healing and prevent gastrointestinal bleeding.',
                    'lifestyle' => 'Strict avoidance of NSAIDs, aspirin, and steroid medications; Avoid acidic and spicy food; Regular meals.',
                    'meds'      => 'Pantoprazole 40mg PO BID x 4 weeks; Sucralfate 1g PO QID 1 hour before meals and bedtime.',
                    'follow_up' => '2-4 Weeks',
                    'days'      => 28
                ],
                'K30' => [
                    'goal'      => 'Relieve postprandial fullness, early satiety, and functional epigastric tenderness.',
                    'lifestyle' => 'Chew food thoroughly; Small, frequent bland meals; Avoid carbonated drinks and chewing gum; Stress relief.',
                    'meds'      => 'Domperidone 10mg PO TID 15 mins before meals; Famotidine 20mg PO BID or Omeprazole 20mg OD x 14 days.',
                    'follow_up' => '2 Weeks',
                    'days'      => 14
                ],

                // Urinary
                'N39.0' => [
                    'goal'      => 'Eradicate urinary bacterial proliferation, relieve dysuria and frequency, normalize urinalysis.',
                    'lifestyle' => 'Increase oral fluid intake > 3 liters/day; Do not delay urination; Good perineal hygiene; Urinate after intercourse.',
                    'meds'      => 'Ciprofloxacin 500mg PO BID x 7 days OR Co-amoxiclav 625mg PO BID x 7 days; Phenazopyridine 100mg TID x 2 days PRN.',
                    'follow_up' => '1 Week',
                    'days'      => 7
                ],
                'N30.0' => [
                    'goal'      => 'Resolve acute bladder inflammation and burning micturition, restore sterile urine.',
                    'lifestyle' => 'Copious oral hydration; Unsweetened cranberry juice; Avoid bladder irritants (caffeine, alcohol, spicy foods).',
                    'meds'      => 'Nitrofurantoin 100mg PO BID x 5 days OR Cefuroxime 500mg PO BID x 7 days; Paracetamol 500mg PRN for pelvic discomfort.',
                    'follow_up' => '5-7 Days',
                    'days'      => 7
                ],

                // Nervous System & Musculoskeletal
                'G44.2' => [
                    'goal'      => 'Relieve pericranial myofascial tension and eliminate band-like headache pressure.',
                    'lifestyle' => 'Stress management; Ergonomic desk posture; Regular sleep schedule 7-8 hours; Neck/shoulder stretching.',
                    'meds'      => 'Paracetamol 500mg + Ibuprofen 200mg PO with meals PRN; Eperisone 50mg PO TID if neck spasm present.',
                    'follow_up' => '2 Weeks',
                    'days'      => 14
                ],
                'G43.0' => [
                    'goal'      => 'Abort acute throbbing migraine attack, relieve associated photophobia and nausea.',
                    'lifestyle' => 'Rest in dark, quiet room during attack; Apply cold compress to forehead; Avoid dietary triggers (aged cheese, MSG, wine).',
                    'meds'      => 'Mefenamic Acid 500mg PO or Naproxen 500mg PO at onset of aura/headache; Metoclopramide 10mg PO PRN for nausea.',
                    'follow_up' => '2-3 Weeks',
                    'days'      => 14
                ],
                'M54.5' => [
                    'goal'      => 'Relieve lumbar muscle spasm, reduce lower back pain score, and restore spinal mobility.',
                    'lifestyle' => 'Apply warm compress to lumbar area 20 min TID; Avoid heavy lifting; Sleep on firm mattress with pillow under knees.',
                    'meds'      => 'Mefenamic Acid 500mg PO TID with meals x 5 days; Eperisone 50mg PO TID x 5 days; Topical Diclofenac gel BID.',
                    'follow_up' => '1-2 Weeks',
                    'days'      => 10
                ],
                'M19.90' => [
                    'goal'      => 'Reduce articular joint pain, preserve joint function, and delay degenerative cartilage erosion.',
                    'lifestyle' => 'Low-impact exercise (swimming, cycling, walking); Weight management to reduce joint load; Assistive device if needed.',
                    'meds'      => 'Paracetamol 500mg PO TID-QID as first-line; Celecoxib 200mg PO OD with meals PRN severe pain; Topical NSAID gel.',
                    'follow_up' => '1 Month',
                    'days'      => 30
                ],
                'M10.9' => [
                    'goal'      => 'Abort acute monoarticular joint inflammation and maintain serum uric acid < 6.0 mg/dL.',
                    'lifestyle' => 'Strict low-purine diet: avoid red meat, organ meats, beer, shellfish; Increase water intake > 3L/day.',
                    'meds'      => 'Colchicine 0.5mg PO BID x 3 days + Naproxen 500mg PO BID with meals; Allopurinol 100mg OD started after acute flare settles.',
                    'follow_up' => '2 Weeks',
                    'days'      => 14
                ],

                // Skin & Trauma
                'L25.9' => [
                    'goal'      => 'Stop cutaneous pruritus, resolve erythematous eczematous plaques, and restore epidermal barrier.',
                    'lifestyle' => 'Avoid exposure to suspected allergen/chemical irritant; Use mild fragrance-free soap; Apply moisturizer within 3 mins of bathing.',
                    'meds'      => 'Hydrocortisone 1% or Betamethasone cream apply thin layer BID x 7 days; Cetirizine 10mg PO OD at bedtime.',
                    'follow_up' => '1-2 Weeks',
                    'days'      => 10
                ],
                'Z20.3' => [
                    'goal'      => 'Prevent rabies virus invasion following animal exposure and prevent secondary bacterial wound infection.',
                    'lifestyle' => 'Wound cleaning with soap and running water for 15 minutes; Observe biting animal for 14 days if domestic.',
                    'meds'      => 'Anti-Rabies Vaccine (PCECV) on Day 0, 3, 7, 28; Tetanus Toxoid 0.5ml IM; Co-amoxiclav 625mg PO BID x 5 days.',
                    'follow_up' => 'Day 3 Rabies Dose',
                    'days'      => 3
                ]
            ];

            foreach ($protocols as $code => $proto) {
                DB::table('icd10_catalog')
                    ->where('ICD10Code', $code)
                    ->update([
                        'DefaultGoal'         => $proto['goal'],
                        'DefaultLifestyle'    => $proto['lifestyle'],
                        'DefaultMedication'   => $proto['meds'],
                        'DefaultFollowUp'     => $proto['follow_up'],
                        'DefaultFollowUpDays' => $proto['days'],
                        'updated_at'          => now()
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('icd10_catalog')) {
            Schema::table('icd10_catalog', function (Blueprint $table) {
                $table->dropColumn(['DefaultGoal', 'DefaultLifestyle', 'DefaultMedication', 'DefaultFollowUp', 'DefaultFollowUpDays']);
            });
        }
    }
};
