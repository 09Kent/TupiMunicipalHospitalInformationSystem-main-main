<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('icd10_catalog')) {
            Schema::create('icd10_catalog', function (Blueprint $table) {
                $table->increments('CatalogID');
                $table->string('ICD10Code', 30)->index();
                $table->string('DiseaseName', 255)->index();
                $table->string('Category', 100)->nullable()->index();
                $table->unsignedInteger('BodySystemID')->nullable();
                $table->text('Description')->nullable();
                $table->enum('DefaultSeverity', ['Mild', 'Moderate', 'Severe', 'Critical'])->default('Moderate');
                $table->enum('Status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();
            });
        }

        // Add ICD10Code column to possible_conditions if not present
        if (Schema::hasTable('possible_conditions') && !Schema::hasColumn('possible_conditions', 'ICD10Code')) {
            Schema::table('possible_conditions', function (Blueprint $table) {
                $table->string('ICD10Code', 30)->nullable()->after('ConditionName');
            });
        }

        // Comprehensive seed of common clinical diagnoses & ICD-10 codes for primary and municipal healthcare
        $diseases = [
            // Infectious & Parasitic Diseases (A00-B99)
            ['ICD10Code' => 'A09', 'DiseaseName' => 'Diarrhea and Gastroenteritis (Presumed Infectious Origin)', 'Category' => 'Infectious & Parasitic', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Acute loose/watery stools with nausea, vomiting, or abdominal cramps; common in food/waterborne exposure.'],
            ['ICD10Code' => 'A09.0', 'DiseaseName' => 'Acute Gastroenteritis with Mild Dehydration', 'Category' => 'Infectious & Parasitic', 'BodySystemID' => 1, 'DefaultSeverity' => 'Mild', 'Description' => 'Infectious gastroenteritis with stable hemodynamics and mild mucosal dehydration.'],
            ['ICD10Code' => 'A09.1', 'DiseaseName' => 'Acute Gastroenteritis with Moderate Dehydration', 'Category' => 'Infectious & Parasitic', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Infectious diarrhea requiring oral rehydration salt (ORS) therapy or intravenous fluids.'],
            ['ICD10Code' => 'A90', 'DiseaseName' => 'Dengue Fever without Warning Signs', 'Category' => 'Vector-Borne Infection', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Acute viral flavivirus infection transmitted by Aedes aegypti; high fever, myalgia, arthralgia, retro-orbital pain.'],
            ['ICD10Code' => 'A91', 'DiseaseName' => 'Dengue Hemorrhagic Fever with Warning Signs', 'Category' => 'Vector-Borne Infection', 'BodySystemID' => 9, 'DefaultSeverity' => 'Severe', 'Description' => 'Dengue infection with persistent vomiting, mucosal bleeding, abdominal tenderness, or thrombocytopenia.'],
            ['ICD10Code' => 'A01.0', 'DiseaseName' => 'Typhoid Fever (Salmonella Typhi)', 'Category' => 'Infectious & Parasitic', 'BodySystemID' => 1, 'DefaultSeverity' => 'Severe', 'Description' => 'Systemic enteric fever characterized by step-ladder pyrexia, hepatosplenomegaly, and headache.'],
            ['ICD10Code' => 'A06.0', 'DiseaseName' => 'Acute Amoebic Dysentery (Amoebiasis)', 'Category' => 'Infectious & Parasitic', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Entamoeba histolytica intestinal infection causing blood and mucus in stool with tenesmus.'],
            ['ICD10Code' => 'A15.0', 'DiseaseName' => 'Tuberculosis of Lung (Pulmonary TB)', 'Category' => 'Mycobacterial Infection', 'BodySystemID' => 4, 'DefaultSeverity' => 'Moderate', 'Description' => 'Chronic Mycobacterium tuberculosis lung infection with productive cough >2 weeks, night sweats, and weight loss.'],
            ['ICD10Code' => 'A27.9', 'DiseaseName' => 'Leptospirosis, Unspecified', 'Category' => 'Zoonotic Infection', 'BodySystemID' => 9, 'DefaultSeverity' => 'Severe', 'Description' => 'Spirochetal infection following exposure to contaminated flood water; calf tenderness, conjunctival suffusion.'],
            ['ICD10Code' => 'B01.9', 'DiseaseName' => 'Varicella (Chickenpox) without Complication', 'Category' => 'Viral Exanthem', 'BodySystemID' => 6, 'DefaultSeverity' => 'Mild', 'Description' => 'Varicella-zoster virus presenting with polymorphic pruritic maculopapular and vesicular skin eruptions.'],
            ['ICD10Code' => 'B34.9', 'DiseaseName' => 'Viral Infection / Viral Syndrome, Unspecified', 'Category' => 'Viral Disease', 'BodySystemID' => 9, 'DefaultSeverity' => 'Mild', 'Description' => 'Self-limiting febrile viral illness with malaise, body aches, and low-grade temperature.'],
            ['ICD10Code' => 'B35.4', 'DiseaseName' => 'Tinea Corporis (Ringworm)', 'Category' => 'Dermatology / Fungal', 'BodySystemID' => 6, 'DefaultSeverity' => 'Mild', 'Description' => 'Superficial dermatophyte fungal infection characterized by annular erythematous scaly plaques.'],
            ['ICD10Code' => 'B86', 'DiseaseName' => 'Scabies Infestation', 'Category' => 'Parasitic Infestation', 'BodySystemID' => 6, 'DefaultSeverity' => 'Moderate', 'Description' => 'Sarcoptes scabiei mite infestation with nocturnal pruritus and web-space burrows.'],
            ['ICD10Code' => 'U07.1', 'DiseaseName' => 'COVID-19 (SARS-CoV-2 Acute Respiratory Disease)', 'Category' => 'Viral Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Moderate', 'Description' => 'Coronavirus disease with acute respiratory symptoms, loss of taste/smell, and fever.'],

            // Endocrine, Nutritional & Metabolic Diseases (E00-E89)
            ['ICD10Code' => 'E11.9', 'DiseaseName' => 'Type 2 Diabetes Mellitus without Complications', 'Category' => 'Endocrine & Metabolic', 'BodySystemID' => 8, 'DefaultSeverity' => 'Moderate', 'Description' => 'Chronic metabolic disorder characterized by persistent hyperglycemia and relative insulin deficiency.'],
            ['ICD10Code' => 'E11.51', 'DiseaseName' => 'Type 2 Diabetes Mellitus with Peripheral Angiopathy / Neuropathy', 'Category' => 'Endocrine & Metabolic', 'BodySystemID' => 8, 'DefaultSeverity' => 'Severe', 'Description' => 'Diabetes mellitus complicated by distal sensory neuropathy or impaired peripheral microcirculation.'],
            ['ICD10Code' => 'E10.9', 'DiseaseName' => 'Type 1 Diabetes Mellitus without Complications', 'Category' => 'Endocrine & Metabolic', 'BodySystemID' => 8, 'DefaultSeverity' => 'Severe', 'Description' => 'Autoimmune pancreatic beta-cell destruction leading to absolute insulin deficiency.'],
            ['ICD10Code' => 'E03.9', 'DiseaseName' => 'Hypothyroidism, Unspecified', 'Category' => 'Endocrine & Metabolic', 'BodySystemID' => 8, 'DefaultSeverity' => 'Mild', 'Description' => 'Subnormal circulating thyroid hormone levels resulting in cold intolerance, fatigue, and bradycardia.'],
            ['ICD10Code' => 'E05.90', 'DiseaseName' => 'Hyperthyroidism / Thyrotoxicosis, Unspecified', 'Category' => 'Endocrine & Metabolic', 'BodySystemID' => 8, 'DefaultSeverity' => 'Moderate', 'Description' => 'Excessive thyroid hormone secretion causing tremors, weight loss, heat intolerance, and tachycardia.'],
            ['ICD10Code' => 'E78.0', 'DiseaseName' => 'Pure Hypercholesterolemia / Dyslipidemia', 'Category' => 'Endocrine & Metabolic', 'BodySystemID' => 8, 'DefaultSeverity' => 'Moderate', 'Description' => 'Elevated low-density lipoprotein (LDL) or total cholesterol posing premature cardiovascular risk.'],
            ['ICD10Code' => 'E86.0', 'DiseaseName' => 'Dehydration / Intravascular Volume Depletion', 'Category' => 'Metabolic / Fluid Balance', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Extracellular fluid volume deficit from decreased oral intake or increased gastrointestinal/sweat losses.'],
            ['ICD10Code' => 'E87.6', 'DiseaseName' => 'Hypokalemia', 'Category' => 'Metabolic / Electrolyte', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Serum potassium < 3.5 mEq/L causing muscle weakness, cramps, or cardiac repolarization changes.'],

            // Diseases of the Circulatory System (I00-I99)
            ['ICD10Code' => 'I10', 'DiseaseName' => 'Essential (Primary) Hypertension', 'Category' => 'Cardiovascular', 'BodySystemID' => 2, 'DefaultSeverity' => 'Moderate', 'Description' => 'Persistent systemic arterial blood pressure elevation >= 140/90 mmHg without secondary organic etiology.'],
            ['ICD10Code' => 'I11.9', 'DiseaseName' => 'Hypertensive Heart Disease without Heart Failure', 'Category' => 'Cardiovascular', 'BodySystemID' => 2, 'DefaultSeverity' => 'Severe', 'Description' => 'Left ventricular concentric hypertrophy resulting from chronic poorly-controlled hypertension.'],
            ['ICD10Code' => 'I20.9', 'DiseaseName' => 'Angina Pectoris, Unspecified (Stable Angina)', 'Category' => 'Cardiovascular', 'BodySystemID' => 2, 'DefaultSeverity' => 'Moderate', 'Description' => 'Exertional retrosternal chest tightness relieved by rest or sublingual nitroglycerin.'],
            ['ICD10Code' => 'I25.10', 'DiseaseName' => 'Atherosclerotic Heart Disease (Coronary Artery Disease)', 'Category' => 'Cardiovascular', 'BodySystemID' => 2, 'DefaultSeverity' => 'Severe', 'Description' => 'Fixed coronary atherosclerotic narrowing in patients with chronic ischemic heart disease.'],
            ['ICD10Code' => 'I50.9', 'DiseaseName' => 'Heart Failure, Unspecified (Congestive Heart Failure)', 'Category' => 'Cardiovascular', 'BodySystemID' => 2, 'DefaultSeverity' => 'Severe', 'Description' => 'Impaired ventricular filling or ejection leading to exertional dyspnea, orthopnea, and peripheral edema.'],
            ['ICD10Code' => 'R00.2', 'DiseaseName' => 'Cardiac Palpitations', 'Category' => 'Cardiovascular / Symptoms', 'BodySystemID' => 2, 'DefaultSeverity' => 'Moderate', 'Description' => 'Awareness of rapid, pounding, or skipped heartbeats requiring rhythm assessment.'],

            // Diseases of the Respiratory System (J00-J99)
            ['ICD10Code' => 'J00', 'DiseaseName' => 'Acute Nasopharyngitis (Common Cold)', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Mild', 'Description' => 'Viral rhinitis with clear rhinorrhea, sneezing, nasal congestion, and mild sore throat.'],
            ['ICD10Code' => 'J02.9', 'DiseaseName' => 'Acute Pharyngitis, Unspecified', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Mild', 'Description' => 'Acute erythematous mucosal inflammation of the posterior oropharynx with dysphagia.'],
            ['ICD10Code' => 'J03.9', 'DiseaseName' => 'Acute Tonsillitis, Unspecified', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Moderate', 'Description' => 'Enlarged erythematous palatine tonsils with or without purulent exudates and cervical lymphadenopathy.'],
            ['ICD10Code' => 'J06.9', 'DiseaseName' => 'Acute Upper Respiratory Tract Infection (URTI)', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Mild', 'Description' => 'Acute viral or bacterial inflammation affecting nose, pharynx, and upper airway passages.'],
            ['ICD10Code' => 'J20.9', 'DiseaseName' => 'Acute Bronchitis, Unspecified', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Moderate', 'Description' => 'Inflammation of bronchial tree causing persistent productive or dry cough following upper respiratory viral prodrome.'],
            ['ICD10Code' => 'J18.9', 'DiseaseName' => 'Community-Acquired Pneumonia (CAP), Unspecified', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Severe', 'Description' => 'Acute pulmonary parenchymal infection with fever, productive cough, crackles/rales on auscultation, and consolidation.'],
            ['ICD10Code' => 'J45.9', 'DiseaseName' => 'Bronchial Asthma, Unspecified', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Moderate', 'Description' => 'Chronic airway hyperresponsiveness and bronchospasm with expiratory wheezing, chest tightness, and dyspnea.'],
            ['ICD10Code' => 'J45.901', 'DiseaseName' => 'Bronchial Asthma with (Acute) Exacerbation', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Severe', 'Description' => 'Sudden acute worsening of airway obstruction requiring nebulized bronchodilators and systemic corticosteroids.'],
            ['ICD10Code' => 'J44.9', 'DiseaseName' => 'Chronic Obstructive Pulmonary Disease (COPD)', 'Category' => 'Respiratory', 'BodySystemID' => 4, 'DefaultSeverity' => 'Severe', 'Description' => 'Progressive airflow limitation from chronic bronchitis or emphysema, commonly associated with tobacco exposure.'],
            ['ICD10Code' => 'J30.9', 'DiseaseName' => 'Allergic Rhinitis, Unspecified', 'Category' => 'Respiratory / Allergy', 'BodySystemID' => 4, 'DefaultSeverity' => 'Mild', 'Description' => 'IgE-mediated nasal mucosal hypersensitivity provoking paroxysmal sneezing, itchy palate, and clear discharge.'],

            // Diseases of the Digestive System (K00-K95)
            ['ICD10Code' => 'K29.7', 'DiseaseName' => 'Gastritis, Unspecified', 'Category' => 'Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Erythema or mucosal erosion of gastric lining presenting with epigastric discomfort, nausea, and early satiety.'],
            ['ICD10Code' => 'K21.9', 'DiseaseName' => 'Gastro-Esophageal Reflux Disease (GERD)', 'Category' => 'Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Incompetence of lower esophageal sphincter leading to acid regurgitation and substernal pyrosis.'],
            ['ICD10Code' => 'K27.9', 'DiseaseName' => 'Peptic Ulcer Disease, Site Unspecified', 'Category' => 'Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Discrete ulceration in gastric or duodenal mucosa often linked to H. pylori or NSAID therapy.'],
            ['ICD10Code' => 'K30', 'DiseaseName' => 'Functional Dyspepsia / Indigestion', 'Category' => 'Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Mild', 'Description' => 'Postprandial fullness, early satiety, or epigastric epigastralgia lacking identifiable structural pathology.'],
            ['ICD10Code' => 'K35.80', 'DiseaseName' => 'Acute Appendicitis, Other / Unspecified', 'Category' => 'Surgical / Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Critical', 'Description' => 'Acute inflammation of vermiform appendix with right lower quadrant (McBurney point) tenderness and rebound guarding.'],
            ['ICD10Code' => 'K80.20', 'DiseaseName' => 'Cholelithiasis (Gallstones) without Cholecystitis', 'Category' => 'Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Moderate', 'Description' => 'Gallbladder calculus causing intermittent biliary colic following fatty meal ingestion.'],
            ['ICD10Code' => 'K81.0', 'DiseaseName' => 'Acute Cholecystitis', 'Category' => 'Surgical / Digestive', 'BodySystemID' => 1, 'DefaultSeverity' => 'Severe', 'Description' => 'Acute cystic duct obstruction and gallbladder wall inflammation with positive Murphy sign and fever.'],

            // Diseases of the Nervous System (G00-G99)
            ['ICD10Code' => 'G44.2', 'DiseaseName' => 'Tension-Type Headache, Unspecified', 'Category' => 'Nervous System', 'BodySystemID' => 3, 'DefaultSeverity' => 'Mild', 'Description' => 'Bilateral, band-like, pressing or tightening headache without nausea or photophobia.'],
            ['ICD10Code' => 'G43.0', 'DiseaseName' => 'Migraine without Aura (Common Migraine)', 'Category' => 'Nervous System', 'BodySystemID' => 3, 'DefaultSeverity' => 'Severe', 'Description' => 'Unilateral, throbbing headache of moderate to severe intensity accompanied by photophobia, phonophobia, or nausea.'],
            ['ICD10Code' => 'G43.1', 'DiseaseName' => 'Migraine with Aura (Classic Migraine)', 'Category' => 'Nervous System', 'BodySystemID' => 3, 'DefaultSeverity' => 'Severe', 'Description' => 'Migraine preceded by transient focal neurological visual scintillations or sensory symptoms.'],
            ['ICD10Code' => 'H81.10', 'DiseaseName' => 'Benign Paroxysmal Positional Vertigo (BPPV)', 'Category' => 'Vestibular / Nervous', 'BodySystemID' => 3, 'DefaultSeverity' => 'Moderate', 'Description' => 'Canalithiasis provoking brief rotational vertigo triggered by sudden changes in head orientation.'],
            ['ICD10Code' => 'G51.0', 'DiseaseName' => 'Bell Palsy (Idiopathic Facial Paralysis)', 'Category' => 'Nervous System', 'BodySystemID' => 3, 'DefaultSeverity' => 'Moderate', 'Description' => 'Acute unilateral peripheral facial nerve (CN VII) paresis causing facial drooping and incomplete eyelid closure.'],
            ['ICD10Code' => 'G40.909', 'DiseaseName' => 'Epilepsy / Seizure Disorder, Unspecified', 'Category' => 'Nervous System', 'BodySystemID' => 3, 'DefaultSeverity' => 'Severe', 'Description' => 'Paroxysmal cerebral electrical discharge presenting with generalized tonic-clonic or focal motor events.'],

            // Diseases of the Musculoskeletal System & Connective Tissue (M00-M99)
            ['ICD10Code' => 'M54.5', 'DiseaseName' => 'Low Back Pain / Lumbago (Mechanical Lumbar Strain)', 'Category' => 'Musculoskeletal', 'BodySystemID' => 5, 'DefaultSeverity' => 'Moderate', 'Description' => 'Acute or chronic non-radicular myofascial pain localized between the twelfth ribs and inferior gluteal folds.'],
            ['ICD10Code' => 'M19.90', 'DiseaseName' => 'Osteoarthritis, Unspecified Site (Joint Wear)', 'Category' => 'Musculoskeletal', 'BodySystemID' => 5, 'DefaultSeverity' => 'Moderate', 'Description' => 'Progressive loss of articular cartilage, osteophyte formation, and joint stiffness aggravated by weight bearing.'],
            ['ICD10Code' => 'M10.9', 'DiseaseName' => 'Gouty Arthritis / Hyperuricemia, Unspecified', 'Category' => 'Musculoskeletal / Metabolic', 'BodySystemID' => 5, 'DefaultSeverity' => 'Severe', 'Description' => 'Acute monoarticular sodium urate crystal-induced synovitis, classically affecting the first metatarsophalangeal joint.'],
            ['ICD10Code' => 'M79.1', 'DiseaseName' => 'Myalgia / Fibromyalgia Pain', 'Category' => 'Musculoskeletal', 'BodySystemID' => 5, 'DefaultSeverity' => 'Mild', 'Description' => 'Generalized muscular tenderness, stiffness, and body soreness after physical exertion or viral infection.'],

            // Diseases of the Genitourinary System (N00-N99)
            ['ICD10Code' => 'N39.0', 'DiseaseName' => 'Urinary Tract Infection (UTI), Site Not Specified', 'Category' => 'Urinary System', 'BodySystemID' => 7, 'DefaultSeverity' => 'Moderate', 'Description' => 'Bacterial multiplication in the urinary tract producing dysuria, frequency, urgency, and cloudy urine.'],
            ['ICD10Code' => 'N30.0', 'DiseaseName' => 'Acute Cystitis', 'Category' => 'Urinary System', 'BodySystemID' => 7, 'DefaultSeverity' => 'Moderate', 'Description' => 'Acute lower urinary bladder infection presenting with suprapubic tenderness, burning micturition, and pyuria.'],
            ['ICD10Code' => 'N10', 'DiseaseName' => 'Acute Pyelonephritis', 'Category' => 'Urinary System', 'BodySystemID' => 7, 'DefaultSeverity' => 'Severe', 'Description' => 'Ascending upper urinary infection affecting the renal parenchyma; high spiking fevers, chills, and costovertebral tenderness.'],
            ['ICD10Code' => 'N20.9', 'DiseaseName' => 'Urinary Calculus (Nephrolithiasis / Urolithiasis)', 'Category' => 'Urinary System', 'BodySystemID' => 7, 'DefaultSeverity' => 'Severe', 'Description' => 'Calculus in kidney or ureter eliciting severe colicky flank pain radiating to the groin with hematuria.'],
            ['ICD10Code' => 'N94.6', 'DiseaseName' => 'Dysmenorrhea, Unspecified', 'Category' => 'Gynecological', 'BodySystemID' => 7, 'DefaultSeverity' => 'Mild', 'Description' => 'Painful spasmodic lower abdominal cramping associated with menstrual flow.'],

            // Diseases of Skin & Subcutaneous Tissue (L00-L99)
            ['ICD10Code' => 'L25.9', 'DiseaseName' => 'Contact Dermatitis, Unspecified', 'Category' => 'Integumentary', 'BodySystemID' => 6, 'DefaultSeverity' => 'Mild', 'Description' => 'Cutaneous inflammatory reaction triggered by direct contact with chemical irritants or allergens.'],
            ['ICD10Code' => 'L20.9', 'DiseaseName' => 'Atopic Dermatitis, Unspecified', 'Category' => 'Integumentary', 'BodySystemID' => 6, 'DefaultSeverity' => 'Moderate', 'Description' => 'Chronic pruritic relapsing eczematous skin lesions in flexural surfaces with epidermal barrier disruption.'],
            ['ICD10Code' => 'L50.0', 'DiseaseName' => 'Allergic Urticaria (Hives)', 'Category' => 'Integumentary / Allergy', 'BodySystemID' => 6, 'DefaultSeverity' => 'Moderate', 'Description' => 'Transient edematous, intensely pruritic wheals with erythematous borders.'],
            ['ICD10Code' => 'L03.90', 'DiseaseName' => 'Cellulitis, Unspecified', 'Category' => 'Integumentary / Bacterial', 'BodySystemID' => 6, 'DefaultSeverity' => 'Severe', 'Description' => 'Spreading acute bacterial dermis and subcutaneous tissue infection with warmth, erythema, and tenderness.'],
            ['ICD10Code' => 'L01.0', 'DiseaseName' => 'Impetigo', 'Category' => 'Integumentary / Bacterial', 'BodySystemID' => 6, 'DefaultSeverity' => 'Mild', 'Description' => 'Superficial staphylococcal or streptococcal skin infection characterized by honey-colored crusted erosions.'],

            // Injury, Poisoning & External Causes (S00-T88, W00-Y99, Z00-Z99)
            ['ICD10Code' => 'T14.1', 'DiseaseName' => 'Open Wound / Laceration, Unspecified', 'Category' => 'Trauma & Emergency', 'BodySystemID' => 6, 'DefaultSeverity' => 'Moderate', 'Description' => 'Traumatic breach of skin and soft tissue requiring debridement, hemostasis, or surgical suturing.'],
            ['ICD10Code' => 'T14.0', 'DiseaseName' => 'Superficial Injury / Abrasion / Contusion', 'Category' => 'Trauma & Emergency', 'BodySystemID' => 6, 'DefaultSeverity' => 'Mild', 'Description' => 'Superficial epidermal loss or subcutaneous ecchymosis without full-thickness skin disruption.'],
            ['ICD10Code' => 'Z20.3', 'DiseaseName' => 'Contact / Exposure to Rabies (Animal Bite Exposure)', 'Category' => 'Preventive / Emergency', 'BodySystemID' => 9, 'DefaultSeverity' => 'Severe', 'Description' => 'Potential lyssavirus exposure from mammalian bite or scratch requiring anti-rabies vaccination protocol.'],
            ['ICD10Code' => 'W54.0', 'DiseaseName' => 'Bitten by Dog', 'Category' => 'External Cause', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Canine bite wound requiring wound irrigation, tetanus booster, and post-exposure prophylaxis evaluation.'],
            ['ICD10Code' => 'W55.0', 'DiseaseName' => 'Contact with Cat (Cat Scratch / Bite)', 'Category' => 'External Cause', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Feline puncture wound or scratch carrying risk of Bartonella henselae and bacterial superinfection.'],
            ['ICD10Code' => 'S82.8', 'DiseaseName' => 'Closed Fracture of Lower Leg / Ankle', 'Category' => 'Orthopedic Trauma', 'BodySystemID' => 5, 'DefaultSeverity' => 'Severe', 'Description' => 'Disruption of bone cortex without open communication to external cutaneous surface.'],
            ['ICD10Code' => 'S93.4', 'DiseaseName' => 'Sprain and Strain of Ankle Ligament', 'Category' => 'Orthopedic Trauma', 'BodySystemID' => 5, 'DefaultSeverity' => 'Moderate', 'Description' => 'Acute inversion injury of talofibular or calcaneofibular lateral ankle ligament complex.'],

            // Pregnancy, Childbirth & Puerperium (O00-O9A)
            ['ICD10Code' => 'O80', 'DiseaseName' => 'Single Spontaneous Normal Delivery', 'Category' => 'Obstetrics', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Full-term uncomplicated singleton vaginal delivery of living infant in cephalic presentation.'],
            ['ICD10Code' => 'O60.0', 'DiseaseName' => 'Threatened Preterm Labor without Delivery', 'Category' => 'Obstetrics', 'BodySystemID' => 9, 'DefaultSeverity' => 'Severe', 'Description' => 'Regular uterine contractions with cervical changes occurring prior to 37 completed gestational weeks.'],
            ['ICD10Code' => 'O20.0', 'DiseaseName' => 'Threatened Abortion / Early Pregnancy Bleeding', 'Category' => 'Obstetrics', 'BodySystemID' => 9, 'DefaultSeverity' => 'Severe', 'Description' => 'Uterine bleeding prior to 20 weeks gestational age with closed cervical os and viable intrauterine embryo.'],

            // Blood & Blood-Forming Organs (D50-D89)
            ['ICD10Code' => 'D50.9', 'DiseaseName' => 'Iron Deficiency Anemia, Unspecified', 'Category' => 'Hematology', 'BodySystemID' => 9, 'DefaultSeverity' => 'Moderate', 'Description' => 'Microcytic hypochromic anemia resulting from inadequate total body iron stores or chronic blood loss.']
        ];

        foreach ($diseases as $d) {
            DB::table('icd10_catalog')->updateOrInsert(
                ['ICD10Code' => $d['ICD10Code']],
                [
                    'DiseaseName'     => $d['DiseaseName'],
                    'Category'        => $d['Category'],
                    'BodySystemID'    => $d['BodySystemID'],
                    'Description'     => $d['Description'],
                    'DefaultSeverity' => $d['DefaultSeverity'],
                    'Status'          => 'Active',
                    'updated_at'      => now(),
                    'created_at'      => now()
                ]
            );
        }

        // Backfill ICD10Code in possible_conditions if empty
        $conditionMap = [
            'Gastritis'                        => 'K29.7',
            'Gastroesophageal Reflux (GERD)'   => 'K21.9',
            'Gastroenteritis'                  => 'A09',
            'Peptic Ulcer Disease'             => 'K27.9',
            'Functional Dyspepsia'             => 'K30',
            'Cardiac Palpitations'             => 'R00.2',
            'Chest Wall Discomfort'            => 'R07.89',
            'Hypertensive Response'            => 'I10',
            'Tension-Type Headache'            => 'G44.2',
            'Migraine with/without Aura'       => 'G43.9',
            'Benign Positional Vertigo'        => 'H81.10',
            'Upper Respiratory Infection'      => 'J06.9',
            'Acute Bronchitis'                 => 'J20.9',
            'Reactive Airway / Asthma'         => 'J45.9',
            'Mechanical Lumbar Strain'         => 'M54.5',
            'Osteoarthritis / Joint Wear'      => 'M19.90',
            'Contact Dermatitis'               => 'L25.9',
            'Urinary Tract Infection'          => 'N39.0',
            'Thyroid Axis Imbalance'           => 'E03.9',
            'Viral Syndrome / Malaise'         => 'B34.9'
        ];

        if (Schema::hasTable('possible_conditions') && Schema::hasColumn('possible_conditions', 'ICD10Code')) {
            foreach ($conditionMap as $condName => $code) {
                DB::table('possible_conditions')
                    ->where('ConditionName', $condName)
                    ->update(['ICD10Code' => $code]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('icd10_catalog');
    }
};
