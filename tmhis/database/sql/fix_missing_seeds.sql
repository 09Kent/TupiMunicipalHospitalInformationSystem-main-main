USE `MedicalRegistrationDB`;
SET FOREIGN_KEY_CHECKS=0;

-- 2. Body Systems Master
INSERT INTO `body_systems` (`BodySystemID`, `SystemName`, `SystemCode`, `Icon`, `Emoji`, `Description`, `Status`) VALUES
(1, 'Digestive System', 'digestive', 'activity', '🩺', 'Involves the esophagus, stomach, intestines, liver, pancreas, and gallbladder.', 'Active'),
(2, 'Cardiovascular System', 'cardiovascular', 'heart-pulse', '🫀', 'Involves the heart, blood vessels, circulation, and blood pressure regulation.', 'Active'),
(3, 'Nervous System', 'nervous', 'zap', '🧠', 'Involves the brain, spinal cord, nerves, sensation, and neuromuscular coordination.', 'Active'),
(4, 'Respiratory System', 'respiratory', 'wind', '🫁', 'Involves airways, lungs, trachea, bronchi, and respiratory gas exchange.', 'Active'),
(5, 'Musculoskeletal System', 'musculoskeletal', 'bone', '🦴', 'Involves bones, joints, muscles, tendons, ligaments, and vertebral column.', 'Active'),
(6, 'Integumentary System', 'integumentary', 'shield', '🧴', 'Involves skin, dermis, cutaneous tissue, hair, nails, and surface barrier.', 'Active'),
(7, 'Urinary System', 'urinary', 'droplets', '🧪', 'Involves kidneys, ureters, urinary bladder, urethra, and fluid homeostasis.', 'Active'),
(8, 'Endocrine System', 'endocrine', 'sparkles', '🧬', 'Involves thyroid, adrenal glands, pancreas islet cells, and hormonal balance.', 'Active'),
(9, 'General / Multisystem', 'general', 'cross', '🏥', 'Involves generalized constitutional symptoms, systemic immune, or metabolic factors.', 'Active')
ON DUPLICATE KEY UPDATE `SystemCode`=VALUES(`SystemCode`);

-- 3. Body Locations
INSERT INTO `body_locations` (`BodyLocationID`, `BodySystemID`, `LocationName`, `LocationCode`, `FrontBack`, `SubRegion`, `Description`) VALUES
(1, 3, 'Head & Cranium', 'head', 'Both', 'Cranial, Frontal & Temporal Area', 'Brain, cranial nerves, eyes, ENT, and scalp.'),
(2, 4, 'Neck & Throat', 'neck', 'Both', 'Anterior & Posterior Cervical Region', 'Cervical vertebrae, pharynx, thyroid, and carotid path.'),
(3, 2, 'Chest & Thorax', 'chest', 'Front', 'Anterior Chest / Precordial Area', 'Heart, pericardium, lungs, pleura, and sternal wall.'),
(4, 1, 'Abdomen', 'abdomen', 'Front', 'Epigastric & Umbilical Quadrants', 'Stomach, intestines, liver, gallbladder, and abdominal wall.'),
(5, 5, 'Back & Spine', 'back', 'Back', 'Thoracic & Lumbar Column', 'Paraspinal musculature, vertebrae, and dorsal roots.'),
(6, 5, 'Arms & Shoulders', 'arms', 'Both', 'Brachial & Forearm Segments', 'Humerus, biceps, triceps, elbow, and radial nerve.'),
(7, 5, 'Hands & Wrists', 'hands', 'Both', 'Carpal & Digital Extremities', 'Carpal tunnel, metacarpal joints, and digital tendons.'),
(8, 7, 'Pelvis & Groin', 'pelvis', 'Both', 'Suprapubic & Inguinal Floor', 'Urinary bladder, pelvic floor, and inguinal canal.'),
(9, 5, 'Legs & Knees', 'legs', 'Both', 'Femoral & Patellar Articulations', 'Quadriceps, hamstrings, knee joints, and calves.'),
(10, 5, 'Feet & Ankles', 'feet', 'Both', 'Plantar & Tarsal Complex', 'Ankles, Achilles tendon, and plantar fascia.'),
(11, 9, 'Whole Body', 'whole_body', 'Both', 'Generalized Systemic Distribution', 'Diffuse, constitutional, or multi-organ presentation.'),
(12, 9, 'Other Area', 'other', 'Both', 'Non-standard Anatomical Area', 'Specific region to be clarified during clinical exam.')
ON DUPLICATE KEY UPDATE `LocationCode`=VALUES(`LocationCode`);

-- 4. Symptoms Master
INSERT INTO `symptoms` (`SymptomID`, `SymptomName`, `Description`, `Icon`, `Status`) VALUES
(1, 'Fever', 'Elevated core body temperature above 37.8°C', 'thermometer', 'Active'),
(2, 'Headache', 'Pain or throbbing pressure in the cranial region', 'brain', 'Active'),
(3, 'Dizziness', 'Sensation of lightheadedness, unsteadiness, or vertigo', 'rotate-cw', 'Active'),
(4, 'Nausea', 'Sensation of unease in the stomach with urge to vomit', 'frown', 'Active'),
(5, 'Vomiting', 'Forceful expulsion of stomach contents', 'alert-circle', 'Active'),
(6, 'Fatigue', 'Overwhelming feeling of tiredness and low energy', 'battery-low', 'Active'),
(7, 'Cough', 'Sudden, repetitive reflex to clear the airways of mucus/irritants', 'wind', 'Active'),
(8, 'Difficulty Breathing', 'Shortness of breath or dyspneic sensation during exertion/rest', 'activity', 'Active'),
(9, 'Diarrhea', 'Frequent, loose, or watery bowel movements', 'droplets', 'Active'),
(10, 'Constipation', 'Infrequent bowel movements or difficulty passing stool', 'lock', 'Active'),
(11, 'Chest Pain / Pressure', 'Discomfort, squeezing, or pain localized in the thoracic cage', 'heart', 'Active'),
(12, 'Muscle Pain', 'Aching, soreness, or cramping in muscular tissue', 'dumbbell', 'Active'),
(13, 'Joint Pain', 'Stiffness, swelling, or pain in articular joints', 'bone', 'Active'),
(14, 'Skin Rash / Itching', 'Cutaneous erythema, itchy hives, bumps, or dry scaling', 'shield', 'Active'),
(15, 'Sore Throat', 'Pain, scratchiness, or irritation of the pharynx', 'message-circle', 'Active'),
(16, 'Indigestion / Bloating', 'Fullness, epigastric burning, or excessive abdominal gas', 'flame', 'Active')
ON DUPLICATE KEY UPDATE `SymptomName`=VALUES(`SymptomName`);

-- 5. Possible Conditions Master
INSERT INTO `possible_conditions` (`ConditionID`, `ConditionName`, `BodySystemID`, `Description`, `Status`) VALUES
(1, 'Gastritis', 1, 'Irritation, erosion, or inflammation of the protective stomach mucosal lining.', 'Active'),
(2, 'Gastroesophageal Reflux (GERD)', 1, 'Acidic stomach contents backing up into the esophagus causing pyrosis.', 'Active'),
(3, 'Gastroenteritis', 1, 'Short-term inflammation of the gastrointestinal tract commonly from food or viral sources.', 'Active'),
(4, 'Peptic Ulcer Disease', 1, 'Discrete sores developing on the inner lining of stomach or duodenum.', 'Active'),
(5, 'Functional Dyspepsia', 1, 'Chronic recurrent indigestion without identifiable organic structural pathology.', 'Active'),
(6, 'Cardiac Palpitations', 2, 'Perception of rapid, fluttering, or irregular heartbeats requiring ECG audit.', 'Active'),
(7, 'Chest Wall Discomfort', 2, 'Musculoskeletal or costochondral thoracic sensitivity.', 'Active'),
(8, 'Hypertensive Response', 2, 'Systemic blood pressure elevation requiring clinical screening and observation.', 'Active'),
(9, 'Tension-Type Headache', 3, 'Bilateral band-like pressure around the forehead and occipital region.', 'Active'),
(10, 'Migraine with/without Aura', 3, 'Moderate to severe throbbing unilateral headache with photophobia.', 'Active'),
(11, 'Benign Positional Vertigo', 3, 'Transient spinning sensation triggered by head positional alterations.', 'Active'),
(12, 'Upper Respiratory Infection', 4, 'Viral inflammation of the nasal, pharyngeal, and laryngeal mucosa.', 'Active'),
(13, 'Acute Bronchitis', 4, 'Inflammation of tracheobronchial tree with productive or dry cough.', 'Active'),
(14, 'Reactive Airway / Asthma', 4, 'Bronchospasm causing wheezing, chest tightness, or dyspnea.', 'Active'),
(15, 'Mechanical Lumbar Strain', 5, 'Acute muscular or ligamentous strain in the lumbosacral spine region.', 'Active'),
(16, 'Osteoarthritis / Joint Wear', 5, 'Degenerative joint disease with cartilage erosion and morning stiffness.', 'Active'),
(17, 'Contact Dermatitis', 6, 'Eczematous skin reaction provoked by allergen or irritant contact.', 'Active'),
(18, 'Urinary Tract Infection', 7, 'Bacterial proliferation in the bladder or urethra causing dysuria.', 'Active'),
(19, 'Thyroid Axis Imbalance', 8, 'Hypothyroidism or hyperthyroidism affecting systemic metabolic rate.', 'Active'),
(20, 'Viral Syndrome / Malaise', 9, 'Constitutional viral illness with body aches, fatigue, and low-grade pyrexia.', 'Active')
ON DUPLICATE KEY UPDATE `ConditionName`=VALUES(`ConditionName`);

-- 6. Ensure Required User Accounts Exist with Proper Roles and Usernames
-- Password hash: $2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy (Password123)
INSERT INTO `users` (`UserID`, `FirstName`, `LastName`, `Username`, `PasswordHash`, `Role`, `Email`, `Status`) VALUES
(4, 'Sarah', 'Jenkins', 'registrator', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Register', 'registrator@tupihospital.gov.ph', 'Active'),
(6, 'Elena', 'Gomez', 'nurse', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Nurse', 'nurse@tupihospital.gov.ph', 'Active'),
(7, 'Clarisse Mae', 'Santos', 'medtech', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'MedTech', 'medtech@tupihospital.gov.ph', 'Active'),
(8, 'Kareen Joy', 'Ramos', 'pharmacist', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Pharmacist', 'pharmacist@tupihospital.gov.ph', 'Active'),
(9, 'Maria', 'Castillo', 'cashier', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Billing', 'cashier@tupihospital.gov.ph', 'Active')
ON DUPLICATE KEY UPDATE `FirstName`=VALUES(`FirstName`), `LastName`=VALUES(`LastName`), `Username`=VALUES(`Username`), `Role`=VALUES(`Role`), `Email`=VALUES(`Email`), `PasswordHash`=VALUES(`PasswordHash`), `Status`=VALUES(`Status`);

SET FOREIGN_KEY_CHECKS=1;
