-- ============================================================================
-- TUPI MUNICIPAL HOSPITAL INFORMATION MANAGEMENT SYSTEM (TMHIS)
-- UNIFIED PRODUCTION DEPLOYMENT & INSTALLATION DATABASE SCRIPT
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `MedicalRegistrationDB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `MedicalRegistrationDB`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------------
-- 1. CORE REGISTRATION & TAXONOMY SCHEMA
-- --------------------------------------------------------------------------
-- Medical Registration & Pre-Consultation Database Schema
-- Database: MedicalRegistrationDB




-- 1. Users Table (System Registrators, Admins, Doctors)
CREATE TABLE IF NOT EXISTS `users` (
  `UserID` INT AUTO_INCREMENT PRIMARY KEY,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `Username` VARCHAR(60) NOT NULL UNIQUE,
  `PasswordHash` VARCHAR(255) NOT NULL,
  `Role` VARCHAR(50) NOT NULL DEFAULT 'Registrator',
  `Email` VARCHAR(120) NULL,
  `Status` ENUM('Active', 'Inactive', 'Suspended') NOT NULL DEFAULT 'Active',
  `remember_token` VARCHAR(100) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_login` (`Username`, `Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Patients Table
CREATE TABLE IF NOT EXISTS `patients` (
  `PatientID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientCode` VARCHAR(30) NOT NULL UNIQUE,
  `FirstName` VARCHAR(100) NOT NULL,
  `MiddleName` VARCHAR(100) NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `DateOfBirth` DATE NOT NULL,
  `Age` INT NOT NULL,
  `Gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `CivilStatus` ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL DEFAULT 'Single',
  `ContactNumber` VARCHAR(30) NOT NULL,
  `Email` VARCHAR(120) NOT NULL,
  `Address` TEXT NOT NULL,
  `BloodType` VARCHAR(10) NOT NULL DEFAULT 'Unknown',
  `PatientCategory` ENUM('Outpatient', 'Emergency', 'Inpatient', 'Consultation', 'Admitted') NOT NULL DEFAULT 'Outpatient',
  `Status` ENUM('Active', 'Inactive', 'Discharged', 'Transferred') NOT NULL DEFAULT 'Active',
  `RegisteredBy` INT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`RegisteredBy`) REFERENCES `users`(`UserID`) ON DELETE SET NULL,
  INDEX `idx_patient_search` (`FirstName`, `LastName`, `ContactNumber`),
  INDEX `idx_patient_code` (`PatientCode`),
  INDEX `idx_patient_category` (`PatientCategory`),
  INDEX `idx_patient_created` (`CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Emergency Contacts Table
CREATE TABLE IF NOT EXISTS `emergency_contacts` (
  `EmergencyContactID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `ContactName` VARCHAR(150) NOT NULL,
  `Relationship` VARCHAR(60) NOT NULL,
  `ContactNumber` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  INDEX `idx_emg_patient` (`PatientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Medical Histories Table
CREATE TABLE IF NOT EXISTS `medical_histories` (
  `MedicalHistoryID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `Allergies` TEXT NULL,
  `ExistingConditions` TEXT NULL,
  `CurrentMedications` TEXT NULL,
  `PreviousHospitalization` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  INDEX `idx_med_patient` (`PatientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Body Systems Table
CREATE TABLE IF NOT EXISTS `body_systems` (
  `BodySystemID` INT AUTO_INCREMENT PRIMARY KEY,
  `SystemName` VARCHAR(100) NOT NULL UNIQUE,
  `SystemCode` VARCHAR(50) NOT NULL UNIQUE,
  `Icon` VARCHAR(50) NOT NULL DEFAULT 'activity',
  `Emoji` VARCHAR(10) NOT NULL DEFAULT '🩺',
  `Description` TEXT NOT NULL,
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Body Locations Table
CREATE TABLE IF NOT EXISTS `body_locations` (
  `BodyLocationID` INT AUTO_INCREMENT PRIMARY KEY,
  `BodySystemID` INT NULL,
  `LocationName` VARCHAR(100) NOT NULL,
  `LocationCode` VARCHAR(50) NOT NULL UNIQUE,
  `FrontBack` ENUM('Front', 'Back', 'Both') NOT NULL DEFAULT 'Front',
  `SubRegion` VARCHAR(150) NOT NULL,
  `Description` TEXT NULL,
  FOREIGN KEY (`BodySystemID`) REFERENCES `body_systems`(`BodySystemID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Symptoms Master Table
CREATE TABLE IF NOT EXISTS `symptoms` (
  `SymptomID` INT AUTO_INCREMENT PRIMARY KEY,
  `SymptomName` VARCHAR(100) NOT NULL UNIQUE,
  `Description` VARCHAR(255) NULL,
  `Icon` VARCHAR(50) NULL DEFAULT 'activity',
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Complaints Table
CREATE TABLE IF NOT EXISTS `complaints` (
  `ComplaintID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `ComplaintDescription` TEXT NOT NULL,
  `Severity` INT NOT NULL DEFAULT 3,
  `Duration` VARCHAR(50) NOT NULL DEFAULT '1–3 days ago',
  `AggravatingFactors` TEXT NULL,
  `RelievingFactors` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  INDEX `idx_complaint_patient` (`PatientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Patient Symptoms Junction Table
CREATE TABLE IF NOT EXISTS `patient_symptoms` (
  `PatientSymptomID` INT AUTO_INCREMENT PRIMARY KEY,
  `ComplaintID` INT NOT NULL,
  `SymptomID` INT NOT NULL,
  FOREIGN KEY (`ComplaintID`) REFERENCES `complaints`(`ComplaintID`) ON DELETE CASCADE,
  FOREIGN KEY (`SymptomID`) REFERENCES `symptoms`(`SymptomID`) ON DELETE CASCADE,
  UNIQUE KEY `unique_complaint_symptom` (`ComplaintID`, `SymptomID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Complaint Analysis Table (Symptom Classification Results)
CREATE TABLE IF NOT EXISTS `complaint_analysis` (
  `AnalysisID` INT AUTO_INCREMENT PRIMARY KEY,
  `ComplaintID` INT NOT NULL,
  `BodySystemID` INT NOT NULL,
  `BodyLocationID` INT NULL,
  `RelevanceLevel` INT NOT NULL DEFAULT 90,
  `ConfidenceLevel` VARCHAR(30) NOT NULL DEFAULT 'High (95%)',
  `ClinicalNotes` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ComplaintID`) REFERENCES `complaints`(`ComplaintID`) ON DELETE CASCADE,
  FOREIGN KEY (`BodySystemID`) REFERENCES `body_systems`(`BodySystemID`) ON DELETE RESTRICT,
  FOREIGN KEY (`BodyLocationID`) REFERENCES `body_locations`(`BodyLocationID`) ON DELETE SET NULL,
  INDEX `idx_analysis_complaint` (`ComplaintID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Possible Conditions Master Table
CREATE TABLE IF NOT EXISTS `possible_conditions` (
  `ConditionID` INT AUTO_INCREMENT PRIMARY KEY,
  `ConditionName` VARCHAR(150) NOT NULL,
  `BodySystemID` INT NOT NULL,
  `Description` TEXT NOT NULL,
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  FOREIGN KEY (`BodySystemID`) REFERENCES `body_systems`(`BodySystemID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Complaint Conditions Junction Table
CREATE TABLE IF NOT EXISTS `complaint_conditions` (
  `ComplaintConditionID` INT AUTO_INCREMENT PRIMARY KEY,
  `ComplaintID` INT NOT NULL,
  `ConditionID` INT NOT NULL,
  FOREIGN KEY (`ComplaintID`) REFERENCES `complaints`(`ComplaintID`) ON DELETE CASCADE,
  FOREIGN KEY (`ConditionID`) REFERENCES `possible_conditions`(`ConditionID`) ON DELETE CASCADE,
  UNIQUE KEY `unique_complaint_condition` (`ComplaintID`, `ConditionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Doctors Table
CREATE TABLE IF NOT EXISTS `doctors` (
  `DoctorID` INT AUTO_INCREMENT PRIMARY KEY,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `Title` VARCHAR(50) NOT NULL DEFAULT 'MD',
  `Specialty` VARCHAR(120) NOT NULL,
  `LicenseNumber` VARCHAR(60) NOT NULL UNIQUE,
  `ContactNumber` VARCHAR(30) NOT NULL,
  `Email` VARCHAR(120) NOT NULL,
  `ExperienceYears` INT NOT NULL DEFAULT 5,
  `Clinic` VARCHAR(150) NOT NULL,
  `ClinicRoom` VARCHAR(100) NOT NULL DEFAULT 'Suite 101',
  `ProfileImage` VARCHAR(255) NULL,
  `Rating` DECIMAL(3,2) NOT NULL DEFAULT 4.90,
  `ReviewsCount` INT NOT NULL DEFAULT 50,
  `ConsultationFee` VARCHAR(50) NOT NULL DEFAULT '$80.00',
  `Bio` TEXT NULL,
  `Education` TEXT NULL,
  `Languages` VARCHAR(255) NOT NULL DEFAULT 'English',
  `Status` ENUM('Available', 'Busy', 'On Leave', 'Inactive') NOT NULL DEFAULT 'Available',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_doc_specialty` (`Specialty`),
  INDEX `idx_doc_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Doctor Body Systems Junction Table
CREATE TABLE IF NOT EXISTS `doctor_body_systems` (
  `DoctorSystemID` INT AUTO_INCREMENT PRIMARY KEY,
  `DoctorID` INT NOT NULL,
  `BodySystemID` INT NOT NULL,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE CASCADE,
  FOREIGN KEY (`BodySystemID`) REFERENCES `body_systems`(`BodySystemID`) ON DELETE CASCADE,
  UNIQUE KEY `unique_doctor_system` (`DoctorID`, `BodySystemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Appointments Table
CREATE TABLE IF NOT EXISTS `appointments` (
  `AppointmentID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `AppointmentDate` DATE NOT NULL,
  `AppointmentTime` VARCHAR(30) NOT NULL,
  `ConsultationType` ENUM('In-Person Consultation', 'Secure Telehealth Video', 'Follow-up Review', 'Emergency Triage') NOT NULL DEFAULT 'In-Person Consultation',
  `Reason` TEXT NOT NULL,
  `Priority` ENUM('Normal', 'Urgent', 'High Priority', 'Follow-up') NOT NULL DEFAULT 'Normal',
  `Notes` TEXT NULL,
  `Status` ENUM('Scheduled', 'Confirmed', 'Waiting', 'In Consultation', 'Completed', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Scheduled',
  `CreatedBy` INT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`CreatedBy`) REFERENCES `users`(`UserID`) ON DELETE SET NULL,
  INDEX `idx_app_date` (`AppointmentDate`, `Status`),
  INDEX `idx_app_patient` (`PatientID`),
  INDEX `idx_app_doctor` (`DoctorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Patient Queue Table
CREATE TABLE IF NOT EXISTS `patient_queue` (
  `QueueID` INT AUTO_INCREMENT PRIMARY KEY,
  `AppointmentID` INT NULL,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `QueueNumber` VARCHAR(10) NOT NULL,
  `QueueDate` DATE NOT NULL,
  `QueueStatus` ENUM('Waiting', 'Called', 'In Consultation', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Waiting',
  `Priority` ENUM('Normal', 'Priority', 'Emergency') NOT NULL DEFAULT 'Normal',
  `CalledAt` DATETIME NULL,
  `ConsultationStartedAt` DATETIME NULL,
  `CompletedAt` DATETIME NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE SET NULL,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  INDEX `idx_queue_daily` (`QueueDate`, `QueueStatus`),
  INDEX `idx_queue_num` (`QueueDate`, `QueueNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Patient Registration History Table
CREATE TABLE IF NOT EXISTS `patient_registration_history` (
  `HistoryID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `UserID` INT NULL,
  `Action` VARCHAR(100) NOT NULL,
  `Description` TEXT NOT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`UserID`) REFERENCES `users`(`UserID`) ON DELETE SET NULL,
  INDEX `idx_hist_patient` (`PatientID`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 2. CLINICAL DOCTOR & EMR SCHEMA
-- --------------------------------------------------------------------------
-- Doctor Portal Database Schema & Enhancements


SET FOREIGN_KEY_CHECKS=0;

-- 1. Specialties Table
CREATE TABLE IF NOT EXISTS `specialties` (
  `SpecialtyID` INT AUTO_INCREMENT PRIMARY KEY,
  `SpecialtyName` VARCHAR(100) NOT NULL UNIQUE,
  `SpecialtyCode` VARCHAR(50) NOT NULL UNIQUE,
  `Description` TEXT NULL,
  `BodySystemID` INT NULL,
  `Icon` VARCHAR(50) NOT NULL DEFAULT 'stethoscope',
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  FOREIGN KEY (`BodySystemID`) REFERENCES `body_systems`(`BodySystemID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Body Systems Master
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

-- Seed Body Locations
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

-- Seed Symptoms Master
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

-- Seed Possible Conditions Master
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

-- Seed Specialties (All 15 as requested)
INSERT INTO `specialties` (`SpecialtyID`, `SpecialtyName`, `SpecialtyCode`, `Description`, `BodySystemID`, `Icon`) VALUES
(1, 'General Practitioner', 'gp', 'Primary healthcare and general medical complaints', 9, 'stethoscope'),
(2, 'Cardiologist', 'cardio', 'Heart, blood pressure, and cardiovascular system', 2, 'heart-pulse'),
(3, 'Neurologist', 'neuro', 'Brain, spinal cord, nerves, and neurological conditions', 3, 'brain'),
(4, 'Pulmonologist', 'pulmo', 'Lungs, airways, and respiratory conditions', 4, 'wind'),
(5, 'Gastroenterologist', 'gastro', 'Digestive tract, stomach, intestines, liver, gallbladder', 1, 'activity'),
(6, 'Orthopedic', 'ortho', 'Bones, joints, muscles, and musculoskeletal trauma', 5, 'bone'),
(7, 'Pediatrician', 'pedia', 'Comprehensive child healthcare and pediatric development', 9, 'baby'),
(8, 'General Surgeon', 'surgeon', 'Surgical consultations, wound management, operative care', 9, 'scissors'),
(9, 'Dermatologist', 'derma', 'Skin conditions, cutaneous infections, dermal health', 6, 'shield'),
(10, 'ENT', 'ent', 'Ear, nose, throat, head and neck conditions', 3, 'headphones'),
(11, 'Urologist', 'uro', 'Urinary tract, bladder, and male reproductive health', 7, 'droplet'),
(12, 'OB-GYN', 'obgyn', 'Obstetrics, gynecology, and female reproductive health', 9, 'users'),
(13, 'Psychiatrist', 'psych', 'Mental health, behavioral wellness, and cognitive support', 3, 'smile'),
(14, 'Ophthalmologist', 'opht', 'Vision care, ocular diagnostics, and eye conditions', 3, 'eye'),
(15, 'Nephrologist', 'nephro', 'Kidney function, renal disease, and electrolyte balance', 7, 'droplets')
ON DUPLICATE KEY UPDATE `SpecialtyName`=VALUES(`SpecialtyName`), `BodySystemID`=VALUES(`BodySystemID`);

-- Update/Alter doctors table to support UserID and SpecialtyID
SET @dbname = DATABASE();
SET @tablename = "doctors";
SET @columnname = "UserID";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `doctors` ADD COLUMN `UserID` INT NULL AFTER `DoctorID`, ADD CONSTRAINT `fk_doc_user` FOREIGN KEY (`UserID`) REFERENCES `users`(`UserID`) ON DELETE SET NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "SpecialtyID";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `doctors` ADD COLUMN `SpecialtyID` INT NULL AFTER `LastName`, ADD CONSTRAINT `fk_doc_specialty` FOREIGN KEY (`SpecialtyID`) REFERENCES `specialties`(`SpecialtyID`) ON DELETE SET NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Doctor Specialties Table (For multiple specialties in future)
CREATE TABLE IF NOT EXISTS `doctor_specialties` (
  `DoctorSpecialtyID` INT AUTO_INCREMENT PRIMARY KEY,
  `DoctorID` INT NOT NULL,
  `SpecialtyID` INT NOT NULL,
  `IsPrimary` TINYINT(1) NOT NULL DEFAULT 1,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE CASCADE,
  FOREIGN KEY (`SpecialtyID`) REFERENCES `specialties`(`SpecialtyID`) ON DELETE CASCADE,
  UNIQUE KEY `unique_doc_spec` (`DoctorID`, `SpecialtyID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Consultation Notes Table
CREATE TABLE IF NOT EXISTS `consultation_notes` (
  `NoteID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `Subjective` TEXT NULL,
  `Objective` TEXT NULL,
  `Assessment` TEXT NULL,
  `Plan` TEXT NULL,
  `ClinicalNotes` TEXT NOT NULL,
  `VitalSigns` JSON NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE SET NULL,
  INDEX `idx_note_patient` (`PatientID`),
  INDEX `idx_note_doctor` (`DoctorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Diagnoses Table
CREATE TABLE IF NOT EXISTS `diagnoses` (
  `DiagnosisID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `DiagnosisName` VARCHAR(255) NOT NULL,
  `ICD10Code` VARCHAR(30) NULL,
  `Type` ENUM('Primary', 'Secondary', 'Differential', 'Provisional') NOT NULL DEFAULT 'Primary',
  `Severity` ENUM('Mild', 'Moderate', 'Severe', 'Critical') NOT NULL DEFAULT 'Moderate',
  `Status` ENUM('Active', 'Resolved', 'Chronic', 'Under Investigation') NOT NULL DEFAULT 'Active',
  `Notes` TEXT NULL,
  `DiagnosedDate` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE SET NULL,
  INDEX `idx_diag_patient` (`PatientID`),
  INDEX `idx_diag_doctor` (`DoctorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Treatment Plans Table
CREATE TABLE IF NOT EXISTS `treatment_plans` (
  `PlanID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `DiagnosisID` INT NULL,
  `Goal` VARCHAR(255) NOT NULL,
  `LifestyleRecommendations` TEXT NULL,
  `MedicationPlan` TEXT NULL,
  `FollowUpSchedule` VARCHAR(100) NULL,
  `FollowUpDate` DATE NULL,
  `Status` ENUM('Active', 'Completed', 'Discontinued', 'Modified') NOT NULL DEFAULT 'Active',
  `Notes` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE SET NULL,
  FOREIGN KEY (`DiagnosisID`) REFERENCES `diagnoses`(`DiagnosisID`) ON DELETE SET NULL,
  INDEX `idx_treat_patient` (`PatientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Prescriptions Table
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `PrescriptionID` INT AUTO_INCREMENT PRIMARY KEY,
  `PrescriptionCode` VARCHAR(50) NOT NULL UNIQUE,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `MedicineName` VARCHAR(200) NOT NULL,
  `Dosage` VARCHAR(100) NOT NULL,
  `Frequency` VARCHAR(100) NOT NULL,
  `Duration` VARCHAR(100) NOT NULL,
  `Instructions` TEXT NOT NULL,
  `Quantity` VARCHAR(50) NULL DEFAULT '1 Box',
  `Refills` INT NOT NULL DEFAULT 0,
  `Status` ENUM('Active', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Active',
  `IssuedDate` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE SET NULL,
  INDEX `idx_rx_patient` (`PatientID`),
  INDEX `idx_rx_doctor` (`DoctorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Laboratory Requests Table
CREATE TABLE IF NOT EXISTS `laboratory_requests` (
  `RequestID` INT AUTO_INCREMENT PRIMARY KEY,
  `RequestCode` VARCHAR(50) NOT NULL UNIQUE,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `TestType` VARCHAR(150) NOT NULL,
  `Priority` ENUM('Routine', 'Urgent', 'STAT') NOT NULL DEFAULT 'Routine',
  `ClinicalNotes` TEXT NULL,
  `Status` ENUM('Pending', 'Sample Collected', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `RequestedDate` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`AppointmentID`) REFERENCES `appointments`(`AppointmentID`) ON DELETE SET NULL,
  INDEX `idx_lab_patient` (`PatientID`),
  INDEX `idx_lab_doctor` (`DoctorID`),
  INDEX `idx_lab_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Laboratory Results Table
CREATE TABLE IF NOT EXISTS `laboratory_results` (
  `ResultID` INT AUTO_INCREMENT PRIMARY KEY,
  `RequestID` INT NOT NULL,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `TestName` VARCHAR(150) NOT NULL,
  `ResultValue` VARCHAR(255) NOT NULL,
  `NormalRange` VARCHAR(150) NOT NULL,
  `Units` VARCHAR(50) NULL,
  `Interpretation` ENUM('Normal', 'High', 'Low', 'Critical', 'Abnormal') NOT NULL DEFAULT 'Normal',
  `Notes` TEXT NULL,
  `AttachmentPath` VARCHAR(255) NULL,
  `ResultDate` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`RequestID`) REFERENCES `laboratory_requests`(`RequestID`) ON DELETE CASCADE,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  INDEX `idx_res_request` (`RequestID`),
  INDEX `idx_res_patient` (`PatientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Referrals Table
CREATE TABLE IF NOT EXISTS `referrals` (
  `ReferralID` INT AUTO_INCREMENT PRIMARY KEY,
  `ReferralCode` VARCHAR(50) NOT NULL UNIQUE,
  `PatientID` INT NOT NULL,
  `ReferringDoctorID` INT NOT NULL,
  `TargetSpecialtyID` INT NOT NULL,
  `TargetDoctorID` INT NULL,
  `Reason` TEXT NOT NULL,
  `ClinicalSummary` TEXT NULL,
  `Priority` ENUM('Routine', 'Urgent', 'Emergency') NOT NULL DEFAULT 'Routine',
  `Status` ENUM('Pending', 'Accepted', 'Completed', 'Declined') NOT NULL DEFAULT 'Pending',
  `ResponseNotes` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`ReferringDoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  FOREIGN KEY (`TargetSpecialtyID`) REFERENCES `specialties`(`SpecialtyID`) ON DELETE RESTRICT,
  FOREIGN KEY (`TargetDoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE SET NULL,
  INDEX `idx_ref_patient` (`PatientID`),
  INDEX `idx_ref_doctor` (`ReferringDoctorID`),
  INDEX `idx_ref_target_spec` (`TargetSpecialtyID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Medical Certificates Table
CREATE TABLE IF NOT EXISTS `medical_certificates` (
  `CertificateID` INT AUTO_INCREMENT PRIMARY KEY,
  `CertificateCode` VARCHAR(50) NOT NULL UNIQUE,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `CertificateType` ENUM('Fit to Work', 'Fit to School', 'Medical Leave', 'General Medical Certificate') NOT NULL DEFAULT 'Fit to Work',
  `Diagnosis` TEXT NOT NULL,
  `Recommendation` TEXT NULL,
  `DurationStart` DATE NOT NULL,
  `DurationEnd` DATE NOT NULL,
  `DaysExcused` INT NOT NULL DEFAULT 1,
  `Remarks` TEXT NULL,
  `IssueDate` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  INDEX `idx_cert_patient` (`PatientID`),
  INDEX `idx_cert_doctor` (`DoctorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Allergy Records Table
CREATE TABLE IF NOT EXISTS `allergy_records` (
  `AllergyID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `DoctorID` INT NOT NULL,
  `Allergen` VARCHAR(150) NOT NULL,
  `AllergyType` ENUM('Drug', 'Food', 'Environmental', 'Latex', 'Insect', 'Other') NOT NULL DEFAULT 'Drug',
  `Severity` ENUM('Mild', 'Moderate', 'Severe', 'Life-threatening') NOT NULL DEFAULT 'Moderate',
  `Reaction` VARCHAR(255) NOT NULL,
  `Status` ENUM('Active', 'Inactive', 'Suspected') NOT NULL DEFAULT 'Active',
  `ConfirmedDate` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`DoctorID`) REFERENCES `doctors`(`DoctorID`) ON DELETE RESTRICT,
  INDEX `idx_allg_patient` (`PatientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Create Users for all Doctor Roles (Password: password123)
-- Hash: $2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm
INSERT INTO `users` (`UserID`, `FirstName`, `LastName`, `Username`, `PasswordHash`, `Role`, `Email`, `Status`) VALUES
(101, 'Daniel', 'Lewis', 'cardio', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'cardio@hospital.com', 'Active'),
(102, 'Elena', 'Villanueva', 'neuro', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'neuro@hospital.com', 'Active'),
(103, 'Sophia', 'Miller', 'pedia', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'pedia@hospital.com', 'Active'),
(104, 'James', 'Wilson', 'surgeon', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'surgeon@hospital.com', 'Active'),
(105, 'Maria', 'Santos', 'gastro', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'gastro@hospital.com', 'Active'),
(106, 'Marcus', 'Tan', 'pulmo', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'pulmo@hospital.com', 'Active'),
(107, 'Gabriel', 'Navarro', 'ortho', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'ortho@hospital.com', 'Active'),
(108, 'Kenneth', 'Garcia', 'gp', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Doctor', 'gp@hospital.com', 'Active'),
(109, 'Hospital', 'Registrator', 'registrator_main', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Registrator', 'registrator@hospital.com', 'Active')
ON DUPLICATE KEY UPDATE `Email`=VALUES(`Email`), `Role`=VALUES(`Role`);

-- Seed Doctors mapped to Users & Specialties
INSERT INTO `doctors` (`DoctorID`, `UserID`, `FirstName`, `LastName`, `SpecialtyID`, `Title`, `Specialty`, `LicenseNumber`, `ContactNumber`, `Email`, `ExperienceYears`, `Clinic`, `ClinicRoom`, `ProfileImage`, `Rating`, `ReviewsCount`, `ConsultationFee`, `Bio`, `Education`, `Languages`, `Status`) VALUES
(11, 101, 'Daniel', 'Lewis', 2, 'MD, FACC', 'Cardiologist', 'LIC-MED-CARD-101', '+1 (555) 441-2001', 'cardio@hospital.com', 15, 'TMHIS Heart Center', 'Suite 501, Cardiology Wing', 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300&h=300', 4.98, 240, '$120.00', 'Senior Cardiologist specializing in ischemic heart disease, hypertension, cardiovascular diagnostics, and heart rhythm management.', 'Harvard Medical School (MD), Cleveland Clinic (Cardiology Fellowship)', 'English, Spanish', 'Available'),
(12, 102, 'Elena', 'Villanueva', 3, 'MD, FCN', 'Neurologist', 'LIC-MED-NEUR-102', '+1 (555) 441-2002', 'neuro@hospital.com', 12, 'Tupi Municipal Hospital', 'Suite 302, North Wing', 'https://images.unsplash.com/photo-1594824813570-5b12852b7a4b?auto=format&fit=crop&q=80&w=300&h=300', 4.92, 165, '$95.00', 'Dedicated to comprehensive headache diagnostics, migraine therapeutics, peripheral neuropathy, and vertigo management.', 'Columbia University Medical Center (Fellowship), St. Luke\'s College of Medicine (MD)', 'English, Tagalog', 'Available'),
(13, 103, 'Sophia', 'Miller', 7, 'MD, FAAP', 'Pediatrician', 'LIC-MED-PED-103', '+1 (555) 441-2003', 'pedia@hospital.com', 11, 'TMHIS Childrens Center', 'Suite 104, Pediatric Wing', 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300&h=300', 4.96, 180, '$75.00', 'Expert in infant and adolescent medicine, immunization schedules, growth tracking, and acute childhood illnesses.', 'Boston Children\'s Hospital (Fellowship), Johns Hopkins (MD)', 'English, French', 'Available'),
(14, 104, 'James', 'Wilson', 8, 'MD, FACS', 'General Surgeon', 'LIC-MED-SURG-104', '+1 (555) 441-2004', 'surgeon@hospital.com', 18, 'TMHIS Surgical Pavilion', 'Suite 601, Surgical Wing', 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=300&h=300', 4.94, 290, '$150.00', 'Board-certified General Surgeon specializing in laparoscopic procedures, trauma surgery, and soft tissue interventions.', 'Mayo Clinic (Surgical Residency), Yale School of Medicine (MD)', 'English', 'Available'),
(15, 105, 'Maria', 'Santos', 5, 'MD, FPCP, FPSG', 'Gastroenterologist', 'LIC-MED-GAST-105', '+1 (555) 441-2005', 'gastro@hospital.com', 14, 'Tupi Municipal Hospital', 'Suite 405, East Wing', 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300&h=300', 4.95, 142, '$85.00', 'Board-certified specialist in digestive wellness, endoscopy, acid reflux management, gastritis, and inflammatory bowel disorders.', 'Johns Hopkins Medicine (Fellowship), UST Faculty of Medicine (MD)', 'English, Tagalog, Spanish', 'Available'),
(16, 106, 'Marcus', 'Tan', 4, 'MD, FPCCP', 'Pulmonologist', 'LIC-MED-PULM-106', '+1 (555) 441-2006', 'pulmo@hospital.com', 15, 'TMHIS Lung Center', 'Room 108, Pavilion B', 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=300&h=300', 4.89, 130, '$90.00', 'Specializing in bronchial asthma, acute and persistent cough evaluation, post-viral respiratory care, and breathing disorders.', 'Mayo Clinic College of Medicine (Pulmonary Fellowship), UP-PGH (MD)', 'English, Hokkien', 'Available'),
(17, 107, 'Gabriel', 'Navarro', 6, 'MD, FPOA', 'Orthopedic', 'LIC-MED-ORTH-107', '+1 (555) 441-2007', 'ortho@hospital.com', 13, 'Tupi Municipal Hospital', 'Suite 101, Ortho Pavilion', 'https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&q=80&w=300&h=300', 4.93, 175, '$85.00', 'Expert in back pain rehabilitation, spine ergonomics, knee and shoulder joint injuries, arthritis, and sports-related strains.', 'Singapore General Hospital (Fellowship), UST Medicine (MD)', 'English, Tagalog', 'Available'),
(18, 108, 'Kenneth', 'Garcia', 1, 'MD, FAFP', 'General Practitioner', 'LIC-MED-GP-108', '+1 (555) 441-2008', 'gp@hospital.com', 8, 'Tupi Municipal Hospital', 'Room 102, Ground Floor', 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=300&h=300', 4.85, 89, '$55.00', 'Comprehensive family medicine, holistic health checks, initial symptom evaluation, and multi-specialty coordination.', 'St. Luke\'s Medical Center (Residency), Ateneo SOM (MD)', 'English, Tagalog', 'Available')
ON DUPLICATE KEY UPDATE `UserID`=VALUES(`UserID`), `SpecialtyID`=VALUES(`SpecialtyID`), `Email`=VALUES(`Email`);

-- Update doctor_specialties
INSERT INTO `doctor_specialties` (`DoctorID`, `SpecialtyID`, `IsPrimary`) VALUES
(11, 2, 1),
(12, 3, 1),
(13, 7, 1),
(14, 8, 1),
(15, 5, 1),
(16, 4, 1),
(17, 6, 1),
(18, 1, 1)
ON DUPLICATE KEY UPDATE `IsPrimary`=VALUES(`IsPrimary`);

-- Link doctor_body_systems for automatic routing
INSERT INTO `doctor_body_systems` (`DoctorID`, `BodySystemID`) VALUES
(11, 2), -- Dr. Lewis -> Cardiovascular
(12, 3), -- Dr. Villanueva -> Nervous
(13, 9), -- Dr. Miller -> Pediatric / General
(14, 9), -- Dr. Wilson -> Surgery / General
(15, 1), -- Dr. Santos -> Digestive
(16, 4), -- Dr. Tan -> Respiratory
(17, 5), -- Dr. Navarro -> Musculoskeletal
(18, 9)  -- Dr. Garcia -> General
ON DUPLICATE KEY UPDATE `DoctorID`=`DoctorID`;

-- Connect existing and new demo appointments for Dr. Daniel Lewis (Cardiologist) & Dr. Elena Villanueva (Neurologist)
INSERT INTO `appointments` (`AppointmentID`, `PatientID`, `DoctorID`, `AppointmentDate`, `AppointmentTime`, `ConsultationType`, `Reason`, `Priority`, `Notes`, `Status`, `CreatedBy`) VALUES
(101, 3, 11, CURDATE(), '09:00 AM', 'In-Person Consultation', 'Precordial chest tightness, fluttering palpitations during light exertion', 'High Priority', 'Requires 12-lead ECG review and blood pressure evaluation.', 'Waiting', 1),
(102, 6, 11, CURDATE(), '10:30 AM', 'In-Person Consultation', 'Cardiovascular follow-up for chronic hypertension and cholesterol review', 'Normal', 'Scheduled fasting lipid profile requested.', 'Scheduled', 1),
(103, 8, 11, CURDATE(), '02:00 PM', 'In-Person Consultation', 'Exertional dyspnea and tachycardia when climbing stairs', 'Urgent', 'Arrhythmia assessment.', 'Scheduled', 1),
(104, 10, 11, CURDATE(), '03:30 PM', 'In-Person Consultation', 'Routine cardiac wellness audit and post-infarction rehabilitation follow-up', 'Normal', 'Echo results to be reviewed.', 'Completed', 1),
(105, 2, 12, CURDATE(), '09:30 AM', 'In-Person Consultation', 'Severe unilateral throbbing migraine with photophobia and dizziness', 'Urgent', 'Quiet environment requested.', 'In Consultation', 1),
(106, 7, 12, CURDATE(), '11:15 AM', 'In-Person Consultation', 'Recurring tension headaches and cervical neck stiffness', 'Normal', 'Postural and stress assessment.', 'Waiting', 1)
ON DUPLICATE KEY UPDATE `DoctorID`=VALUES(`DoctorID`), `Status`=VALUES(`Status`), `AppointmentDate`=VALUES(`AppointmentDate`);

-- Queue Entries
INSERT INTO `patient_queue` (`QueueID`, `AppointmentID`, `PatientID`, `DoctorID`, `QueueNumber`, `QueueDate`, `QueueStatus`, `Priority`, `CalledAt`, `ConsultationStartedAt`) VALUES
(101, 101, 3, 11, 'C-01', CURDATE(), 'Waiting', 'Priority', NULL, NULL),
(102, 102, 6, 11, 'C-02', CURDATE(), 'Waiting', 'Normal', NULL, NULL),
(103, 103, 8, 11, 'C-03', CURDATE(), 'Waiting', 'Emergency', NULL, NULL),
(104, 104, 10, 11, 'C-04', CURDATE(), 'Completed', 'Normal', '2026-08-16 08:30:00', '2026-08-16 08:35:00'),
(105, 105, 2, 12, 'N-01', CURDATE(), 'In Consultation', 'Priority', '2026-08-16 09:30:00', '2026-08-16 09:35:00'),
(106, 106, 7, 12, 'N-02', CURDATE(), 'Waiting', 'Normal', NULL, NULL)
ON DUPLICATE KEY UPDATE `DoctorID`=VALUES(`DoctorID`), `QueueStatus`=VALUES(`QueueStatus`), `QueueDate`=VALUES(`QueueDate`);

-- Consultation Notes Sample
INSERT INTO `consultation_notes` (`NoteID`, `PatientID`, `DoctorID`, `AppointmentID`, `Subjective`, `Objective`, `Assessment`, `Plan`, `ClinicalNotes`, `VitalSigns`) VALUES
(1, 3, 11, 101, 'Patient reports sudden onset of palpitations and mid-sternal tightness after climbing stairs.', 'BP: 138/88 mmHg, HR: 88 bpm, SpO2: 98%, Heart sounds normal S1/S2 without murmurs.', 'Suspected Paroxysmal Tachycardia vs Stress-induced Palpitations.', '12-lead ECG, Lipid Profile, Metoprolol 25mg daily, avoid excessive caffeine.', 'Patient is alert and oriented. Mid-chest flutter exacerbated by stress and caffeine.', '{"bp": "138/88", "hr": "88", "spo2": "98%", "temp": "36.7°C", "weight": "74 kg"}'),
(2, 10, 11, 104, 'Routine 6-month cardiac check. No angina episodes reported. Adherent to statin therapy.', 'BP: 122/78 mmHg, HR: 68 bpm regular, clear lung fields.', 'Stable Coronary Artery Disease - well controlled.', 'Maintain current regimen, continue daily 30-min walking exercise, re-check in 6 months.', 'Good functional capacity. Echocardiogram shows preserved EF 55%.', '{"bp": "122/78", "hr": "68", "spo2": "99%", "temp": "36.5°C", "weight": "80 kg"}')
ON DUPLICATE KEY UPDATE `DoctorID`=VALUES(`DoctorID`);

-- Diagnoses Sample
INSERT INTO `diagnoses` (`DiagnosisID`, `PatientID`, `DoctorID`, `AppointmentID`, `DiagnosisName`, `ICD10Code`, `Type`, `Severity`, `Status`, `Notes`) VALUES
(1, 3, 11, 101, 'Cardiac Palpitations', 'R00.2', 'Primary', 'Moderate', 'Active', 'Fluttering sensations triggered by exertion/stress.'),
(2, 3, 11, 101, 'Essential (Primary) Hypertension', 'I10', 'Secondary', 'Mild', 'Active', 'Stage 1 hypertension noted during triage.'),
(3, 10, 11, 104, 'Atherosclerotic Heart Disease', 'I25.1', 'Primary', 'Moderate', 'Chronic', 'Post-stent stable follow-up.'),
(4, 2, 12, 105, 'Migraine without Aura', 'G43.0', 'Primary', 'Severe', 'Active', 'Unilateral throbbing with severe photophobia and nausea.')
ON DUPLICATE KEY UPDATE `DiagnosisName`=VALUES(`DiagnosisName`);

-- Treatment Plans Sample
INSERT INTO `treatment_plans` (`PlanID`, `PatientID`, `DoctorID`, `AppointmentID`, `DiagnosisID`, `Goal`, `LifestyleRecommendations`, `MedicationPlan`, `FollowUpSchedule`, `FollowUpDate`, `Status`) VALUES
(1, 3, 11, 101, 1, 'Restore stable resting rhythm and keep BP < 130/80 mmHg', 'Reduce sodium intake to < 2g/day. Limit caffeine and energy drinks. Implement daily cardiovascular aerobic walking.', 'Metoprolol Tartrate 25mg PO BID with meals. Amlodipine 5mg PO OD morning.', 'Follow-up in 2 weeks with ECG results', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Active'),
(2, 10, 11, 104, 3, 'Maintain long-term cardiovascular stability and LDL < 70 mg/dL', 'Mediterranean heart-healthy diet, regular physical exercise 150 min/week, maintain smoking cessation.', 'Atorvastatin 40mg PO at bedtime. Aspirin 81mg PO daily after breakfast.', 'Annual cardiac audit in 6 months', DATE_ADD(CURDATE(), INTERVAL 180 DAY), 'Active')
ON DUPLICATE KEY UPDATE `Goal`=VALUES(`Goal`);

-- Prescriptions Sample
INSERT INTO `prescriptions` (`PrescriptionID`, `PrescriptionCode`, `PatientID`, `DoctorID`, `AppointmentID`, `MedicineName`, `Dosage`, `Frequency`, `Duration`, `Instructions`, `Quantity`, `Refills`, `Status`, `IssuedDate`) VALUES
(1, 'RX-2026-0001', 3, 11, 101, 'Metoprolol Tartrate', '25mg', 'Twice daily (Every 12 hours)', '30 Days', 'Take with or immediately following a meal. Monitor resting heart rate.', '60 Tablets', 1, 'Active', CURDATE()),
(2, 'RX-2026-0002', 3, 11, 101, 'Amlodipine Besylate', '5mg', 'Once daily (Every 24 hours)', '30 Days', 'Take in the morning with a full glass of water.', '30 Tablets', 2, 'Active', CURDATE()),
(3, 'RX-2026-0003', 10, 11, 104, 'Atorvastatin Calcium', '40mg', 'Once daily at bedtime', '90 Days', 'Take in the evening. Avoid grapefruit juice.', '90 Tablets', 3, 'Active', CURDATE()),
(4, 'RX-2026-0004', 2, 12, 105, 'Sumatriptan Succinate', '50mg', 'At onset of migraine attack', 'As needed (PRN)', 'Take 1 tablet at initial onset. May repeat once after 2 hours if headache persists. Max 200mg/24h.', '6 Tablets', 1, 'Active', CURDATE())
ON DUPLICATE KEY UPDATE `PrescriptionCode`=VALUES(`PrescriptionCode`);

-- Laboratory Requests Sample
INSERT INTO `laboratory_requests` (`RequestID`, `RequestCode`, `PatientID`, `DoctorID`, `AppointmentID`, `TestType`, `Priority`, `ClinicalNotes`, `Status`, `RequestedDate`) VALUES
(1, 'LAB-2026-0001', 3, 11, 101, '12-Lead Electrocardiogram (ECG)', 'Urgent', 'Evaluate rhythm flutter, rule out supraventricular tachycardia or ischemic changes.', 'In Progress', CURDATE()),
(2, 'LAB-2026-0002', 3, 11, 101, 'Lipid Profile & Fasting Blood Sugar', 'Routine', 'Baseline cardiovascular lipid risk assessment (Total Chol, HDL, LDL, Triglycerides).', 'Pending', CURDATE()),
(3, 'LAB-2026-0003', 10, 11, 104, 'Complete Blood Count (CBC)', 'Routine', 'Annual routine hematology audit.', 'Completed', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(4, 'LAB-2026-0004', 2, 12, 105, 'Brain MRI (without contrast)', 'Urgent', 'Rule out structural vascular lesions or intracranial hypertension.', 'Pending', CURDATE())
ON DUPLICATE KEY UPDATE `RequestCode`=VALUES(`RequestCode`);

-- Laboratory Results Sample
INSERT INTO `laboratory_results` (`ResultID`, `RequestID`, `PatientID`, `DoctorID`, `TestName`, `ResultValue`, `NormalRange`, `Units`, `Interpretation`, `Notes`, `ResultDate`) VALUES
(1, 3, 10, 11, 'Hemoglobin', '14.8', '13.5 - 17.5', 'g/dL', 'Normal', 'Adequate oxygen-carrying capacity.', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(2, 3, 10, 11, 'White Blood Cells (WBC)', '6.4', '4.5 - 11.0', 'x10^3/uL', 'Normal', 'No signs of acute leukocytosis or active infection.', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(3, 3, 10, 11, 'Platelet Count', '245', '150 - 450', 'x10^3/uL', 'Normal', 'Normal coagulation reserve.', DATE_SUB(CURDATE(), INTERVAL 1 DAY))
ON DUPLICATE KEY UPDATE `RequestID`=VALUES(`RequestID`);

-- Referrals Sample
INSERT INTO `referrals` (`ReferralID`, `ReferralCode`, `PatientID`, `ReferringDoctorID`, `TargetSpecialtyID`, `TargetDoctorID`, `Reason`, `ClinicalSummary`, `Priority`, `Status`, `ResponseNotes`) VALUES
(1, 'REF-2026-0001', 3, 11, 3, 12, 'Patient with palpitations also exhibits bilateral tension headaches and lightheadedness. Requesting comprehensive neurological review.', 'Patient has mild BP elevation and occasional dizziness during exertion. Normal S1/S2. Need to rule out neuro-vascular etiology.', 'Routine', 'Pending', NULL),
(2, 'REF-2026-0002', 2, 12, 2, 11, 'Severe migraine accompanied by transient chest racing during acute pain episodes. Cardiovascular clearance requested.', '29 yo female with recurrent migraines. Episodes accompanied by sinus tachycardia.', 'Routine', 'Accepted', 'Scheduled for cardio assessment on next clinic day.')
ON DUPLICATE KEY UPDATE `ReferralCode`=VALUES(`ReferralCode`);

-- Medical Certificates Sample
INSERT INTO `medical_certificates` (`CertificateID`, `CertificateCode`, `PatientID`, `DoctorID`, `CertificateType`, `Diagnosis`, `DurationStart`, `DurationEnd`, `DaysExcused`, `Remarks`, `IssueDate`) VALUES
(1, 'MED-CERT-2026-0001', 3, 11, 'Medical Leave', 'Paroxysmal Tachycardia and Exertional Palpitations', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is advised strict rest and avoidance of physical or psychological workplace stress during cardiac diagnostics.', CURDATE()),
(2, 'MED-CERT-2026-0002', 10, 11, 'Fit to Work', 'Stable Coronary Artery Disease (Post-Rehab)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 365, 'Patient has been clinically re-evaluated and cleared for normal sedentary office employment duties.', CURDATE())
ON DUPLICATE KEY UPDATE `CertificateCode`=VALUES(`CertificateCode`);

-- Allergy Records Sample
INSERT INTO `allergy_records` (`AllergyID`, `PatientID`, `DoctorID`, `Allergen`, `AllergyType`, `Severity`, `Reaction`, `Status`, `ConfirmedDate`) VALUES
(1, 1, 15, 'Penicillin', 'Drug', 'Moderate', 'Erythematous skin rash, pruritus, mild facial flushing', 'Active', '2026-08-15'),
(2, 3, 11, 'Sulfa Drugs / Sulfonamides', 'Drug', 'Severe', 'Urticarial hives, periorbital edema, mild wheezing', 'Active', '2026-08-16'),
(3, 4, 17, 'Latex', 'Other', 'Moderate', 'Contact dermatitis, localized erythema and itching upon exposure', 'Active', '2026-08-15'),
(4, 5, 15, 'Aspirin (NSAIDs)', 'Drug', 'Severe', 'Acute epigastric burning and bronchospasm', 'Active', '2026-08-15')
ON DUPLICATE KEY UPDATE `PatientID`=VALUES(`PatientID`), `Allergen`=VALUES(`Allergen`);

SET FOREIGN_KEY_CHECKS=1;

-- --------------------------------------------------------------------------
-- 3. MASTER HOSPITAL ROLES (NURSE, LAB, PHARMACY, BILLING, AUDIT) SCHEMA
-- --------------------------------------------------------------------------
-- Tupi Municipal Hospital Information Management System
-- Master Relational Database Schema Expansion
-- Target Database: MedicalRegistrationDB



SET FOREIGN_KEY_CHECKS=0;

-- =========================================================================
-- 1. SYSTEM CONFIGURATION & ADMIN TABLES (Role 1: System Administrator)
-- =========================================================================

-- Hospital Information
CREATE TABLE IF NOT EXISTS `hospital_info` (
  `HospitalInfoID` INT AUTO_INCREMENT PRIMARY KEY,
  `HospitalName` VARCHAR(200) NOT NULL DEFAULT 'Tupi Municipal Hospital',
  `HospitalCode` VARCHAR(50) NOT NULL DEFAULT 'TMH-REG-12',
  `TaxID` VARCHAR(50) NOT NULL DEFAULT '004-982-114-000',
  `Address` TEXT NOT NULL,
  `ContactNumber` VARCHAR(50) NOT NULL DEFAULT '+63 (083) 228-0001',
  `EmergencyHotline` VARCHAR(50) NOT NULL DEFAULT '911 / (083) 228-0002',
  `Email` VARCHAR(120) NOT NULL DEFAULT 'admin@tupihospital.gov.ph',
  `DOHAccreditation` VARCHAR(100) NOT NULL DEFAULT 'DOH-ACCRED-L1-2026-084',
  `PhilHealthAccreditation` VARCHAR(100) NOT NULL DEFAULT 'PH-HOSP-1209384',
  `BedCapacity` INT NOT NULL DEFAULT 50,
  `MedicalDirector` VARCHAR(150) NOT NULL DEFAULT 'Dr. Maria Santos, MD, MHA',
  `LogoPath` VARCHAR(255) NULL DEFAULT 'assets/logo.png',
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default hospital info
INSERT INTO `hospital_info` (`HospitalInfoID`, `HospitalName`, `HospitalCode`, `TaxID`, `Address`, `ContactNumber`, `EmergencyHotline`, `Email`, `DOHAccreditation`, `PhilHealthAccreditation`, `BedCapacity`, `MedicalDirector`)
VALUES (1, 'Tupi Municipal Hospital', 'TMH-REG-12', '004-982-114-000', 'National Highway, Poblacion, Tupi, South Cotabato 9505, Philippines', '+63 (083) 228-0001', '+63 (083) 228-0002', 'admin@tupihospital.gov.ph', 'DOH-ACCRED-L1-2026-084', 'PH-HOSP-1209384', 50, 'Dr. Maria Santos, MD, MHA')
ON DUPLICATE KEY UPDATE `HospitalName`=VALUES(`HospitalName`);

-- Departments Management
CREATE TABLE IF NOT EXISTS `departments` (
  `DepartmentID` INT AUTO_INCREMENT PRIMARY KEY,
  `DepartmentCode` VARCHAR(50) NOT NULL UNIQUE,
  `DepartmentName` VARCHAR(150) NOT NULL,
  `DepartmentType` ENUM('Clinical', 'Administrative', 'Diagnostic', 'Support') NOT NULL DEFAULT 'Clinical',
  `HeadOfDepartment` VARCHAR(150) NULL,
  `Location` VARCHAR(150) NOT NULL DEFAULT 'Main Building',
  `ContactExtension` VARCHAR(20) NULL,
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed standard hospital departments
INSERT INTO `departments` (`DepartmentID`, `DepartmentCode`, `DepartmentName`, `DepartmentType`, `HeadOfDepartment`, `Location`, `ContactExtension`, `Status`) VALUES
(1, 'DEP-EMR', 'Emergency Department', 'Clinical', 'Dr. James Wilson, MD', 'Ground Floor, ER Wing', '101', 'Active'),
(2, 'DEP-OPD', 'Outpatient Department', 'Clinical', 'Dr. Kenneth Garcia, MD', 'Ground Floor, East Wing', '102', 'Active'),
(3, 'DEP-INT', 'Internal Medicine', 'Clinical', 'Dr. Daniel Lewis, MD', '2nd Floor, Medical Wing', '201', 'Active'),
(4, 'DEP-PED', 'Pediatrics Department', 'Clinical', 'Dr. Sophia Miller, MD', '2nd Floor, Pediatric Wing', '202', 'Active'),
(5, 'DEP-SUR', 'General Surgery', 'Clinical', 'Dr. James Wilson, MD', '3rd Floor, Surgical Suite', '301', 'Active'),
(6, 'DEP-OBG', 'Obstetrics & Gynecology', 'Clinical', 'Dr. Elena Villanueva, MD', '2nd Floor, Maternal Wing', '203', 'Active'),
(7, 'DEP-LAB', 'Clinical Laboratory', 'Diagnostic', 'Clarisse Mae Santos, RMT', 'Ground Floor, Diagnostic Lab', '105', 'Active'),
(8, 'DEP-PHA', 'Hospital Pharmacy', 'Support', 'Kareen Joy Ramos, RPh', 'Ground Floor, Main Lobby', '104', 'Active'),
(9, 'DEP-BIL', 'Billing & Cashier Division', 'Administrative', 'Maria Santos', 'Ground Floor, Cashier Booth', '106', 'Active'),
(10, 'DEP-REC', 'Medical Records Department (HIRM)', 'Administrative', 'Mark Anthony Valenzuela, RMT', '1st Floor, Records Archive', '108', 'Active'),
(11, 'DEP-NUR', 'Nursing Services Administration', 'Support', 'Elena Gomez, RN', '2nd Floor, Nursing Station', '205', 'Active'),
(12, 'DEP-ADM', 'Hospital Administration & IT', 'Administrative', 'System Administrator', '4th Floor, Executive Suite', '401', 'Active')
ON DUPLICATE KEY UPDATE `DepartmentName`=VALUES(`DepartmentName`);

-- Service Fees Management
CREATE TABLE IF NOT EXISTS `service_fees` (
  `FeeID` INT AUTO_INCREMENT PRIMARY KEY,
  `ServiceCode` VARCHAR(50) NOT NULL UNIQUE,
  `ServiceName` VARCHAR(200) NOT NULL,
  `Category` ENUM('Consultation', 'Laboratory', 'Radiology', 'Pharmacy', 'Nursing/Ward', 'Surgical/Procedure', 'Administrative', 'Emergency') NOT NULL DEFAULT 'Consultation',
  `DepartmentID` INT NULL,
  `StandardRate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `PhilHealthCoveredRate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `DiscountEligible` TINYINT(1) NOT NULL DEFAULT 1,
  `Description` TEXT NULL,
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed standard TMH service fees
INSERT INTO `service_fees` (`FeeID`, `ServiceCode`, `ServiceName`, `Category`, `DepartmentID`, `StandardRate`, `PhilHealthCoveredRate`, `DiscountEligible`, `Description`, `Status`) VALUES
(1, 'FEE-CON-GP', 'General Medical Consultation', 'Consultation', 2, 250.00, 250.00, 1, 'Standard primary care consultation', 'Active'),
(2, 'FEE-CON-SP', 'Specialist Consultation', 'Consultation', 3, 500.00, 400.00, 1, 'Consultation with medical specialist (Cardio, Pulmo, Neuro, etc.)', 'Active'),
(3, 'FEE-LAB-CBC', 'Complete Blood Count (CBC) with Platelet', 'Laboratory', 7, 280.00, 280.00, 1, 'Routine hematology screening', 'Active'),
(4, 'FEE-LAB-LIP', 'Lipid Profile Panel', 'Laboratory', 7, 650.00, 500.00, 1, 'Total cholesterol, HDL, LDL, Triglycerides', 'Active'),
(5, 'FEE-LAB-FBS', 'Fasting Blood Sugar (FBS)', 'Laboratory', 7, 180.00, 180.00, 1, 'Glucose monitoring test', 'Active'),
(6, 'FEE-LAB-URN', 'Urinalysis Routine Examination', 'Laboratory', 7, 150.00, 150.00, 1, 'Standard urine microscopy', 'Active'),
(7, 'FEE-RAD-XRAY', 'Chest X-Ray (PA View)', 'Radiology', 7, 450.00, 400.00, 1, 'Digital chest radiography', 'Active'),
(8, 'FEE-RAD-ECG', '12-Lead Electrocardiogram (ECG)', 'Radiology', 3, 350.00, 350.00, 1, 'Cardiac electrical activity recording', 'Active'),
(9, 'FEE-NUR-ADM', 'Ward Nursing & Accommodation (Daily)', 'Nursing/Ward', 11, 800.00, 600.00, 1, 'Routine inpatient nursing care and bed fee', 'Active'),
(10, 'FEE-EMR-FEE', 'Emergency Room Triage & Service Fee', 'Emergency', 1, 450.00, 450.00, 1, 'Immediate ER stabilization and intake fee', 'Active'),
(11, 'FEE-MED-CERT', 'Medical Certificate Issuance Fee', 'Administrative', 10, 150.00, 0.00, 1, 'Official Fit to Work / School certificate', 'Active')
ON DUPLICATE KEY UPDATE `ServiceName`=VALUES(`ServiceName`), `StandardRate`=VALUES(`StandardRate`);

-- Roles Master Table
CREATE TABLE IF NOT EXISTS `roles` (
  `RoleID` INT AUTO_INCREMENT PRIMARY KEY,
  `RoleCode` VARCHAR(50) NOT NULL UNIQUE,
  `RoleName` VARCHAR(100) NOT NULL,
  `Description` TEXT NULL,
  `IsSystemRole` TINYINT(1) NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions Table
CREATE TABLE IF NOT EXISTS `permissions` (
  `PermissionID` INT AUTO_INCREMENT PRIMARY KEY,
  `PermissionCode` VARCHAR(100) NOT NULL UNIQUE,
  `PermissionName` VARCHAR(150) NOT NULL,
  `Module` VARCHAR(100) NOT NULL,
  `Description` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Role Permissions Junction Table
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `RoleID` INT NOT NULL,
  `PermissionID` INT NOT NULL,
  PRIMARY KEY (`RoleID`, `PermissionID`),
  FOREIGN KEY (`RoleID`) REFERENCES `roles`(`RoleID`) ON DELETE CASCADE,
  FOREIGN KEY (`PermissionID`) REFERENCES `permissions`(`PermissionID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed 9 Standard FDD Roles
INSERT INTO `roles` (`RoleID`, `RoleCode`, `RoleName`, `Description`, `IsSystemRole`) VALUES
(1, 'ADMIN', 'System Administrator', 'Full access to system configuration, user accounts, and monitoring', 1),
(2, 'CHIEF', 'Hospital Chief / Medical Director', 'Operational oversight, institutional performance tracking, and reports', 1),
(3, 'RECORDS', 'Medical Records Officer', 'Patient records administration, validation, and historical summaries', 1),
(4, 'REGISTER', 'Admitting / Registration Staff', 'Patient registration, appointment scheduling, and queue coordination', 1),
(5, 'DOCTOR', 'Attending Physician', 'Consultations, SOAP notes, diagnosis, e-prescriptions, and lab requests', 1),
(6, 'NURSE', 'Nurse on Duty', 'Vital signs logging, patient bed monitoring, and task execution', 1),
(7, 'MEDTECH', 'Medical Technologist', 'Lab requests processing, specimen tracking, and test results entry', 1),
(8, 'PHARMACIST', 'Pharmacist / Pharmacy Aide', 'Prescription validation, drug dispensing, and inventory management', 1),
(9, 'BILLING', 'Billing / Cashier Staff', 'Charge computations, discount processing, payments, and receipts', 1)
ON DUPLICATE KEY UPDATE `RoleName`=VALUES(`RoleName`);

-- System Global Audit Log Table
CREATE TABLE IF NOT EXISTS `system_audit_logs` (
  `LogID` INT AUTO_INCREMENT PRIMARY KEY,
  `UserID` INT NULL,
  `UserName` VARCHAR(100) NOT NULL,
  `UserRole` VARCHAR(50) NOT NULL,
  `Action` VARCHAR(100) NOT NULL,
  `Module` VARCHAR(80) NOT NULL,
  `RecordID` VARCHAR(50) NULL,
  `Details` TEXT NULL,
  `IPAddress` VARCHAR(50) NULL,
  `UserAgent` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_user` (`UserID`),
  INDEX `idx_audit_module` (`Module`),
  INDEX `idx_audit_date` (`CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Error Log Table
CREATE TABLE IF NOT EXISTS `system_error_logs` (
  `ErrorID` INT AUTO_INCREMENT PRIMARY KEY,
  `ErrorCode` VARCHAR(50) NULL,
  `ErrorMessage` TEXT NOT NULL,
  `Module` VARCHAR(80) NULL,
  `StackTrace` TEXT NULL,
  `RequestUri` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Database Backup Operations History
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `BackupID` INT AUTO_INCREMENT PRIMARY KEY,
  `FileName` VARCHAR(255) NOT NULL,
  `FileSize` VARCHAR(50) NOT NULL,
  `BackupType` ENUM('Full Database', 'Schema Only', 'Data Only') NOT NULL DEFAULT 'Full Database',
  `CreatedBy` INT NULL,
  `Status` ENUM('Completed', 'Failed', 'In Progress', 'Restored') NOT NULL DEFAULT 'Completed',
  `Notes` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure System Administrator User exists in `users` (Password: admin123)
-- Hash: $2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm (password123 / admin123)
INSERT INTO `users` (`UserID`, `FirstName`, `LastName`, `Username`, `PasswordHash`, `Role`, `Email`, `Status`) VALUES
(1, 'System', 'Administrator', 'admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Admin', 'admin@tupihospital.gov.ph', 'Active'),
(2, 'Maria', 'Santos', 'director', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Staff', 'director@tupihospital.gov.ph', 'Active'),
(3, 'Mark Anthony', 'Valenzuela', 'records', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Staff', 'records@tupihospital.gov.ph', 'Active'),
(4, 'Elena', 'Gomez', 'nurse', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Staff', 'nurse@tupihospital.gov.ph', 'Active'),
(5, 'Clarisse Mae', 'Santos', 'medtech', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Staff', 'medtech@tupihospital.gov.ph', 'Active'),
(6, 'Kareen Joy', 'Ramos', 'pharmacist', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Staff', 'pharmacist@tupihospital.gov.ph', 'Active'),
(7, 'Maria', 'Santos', 'cashier', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Staff', 'cashier@tupihospital.gov.ph', 'Active')
ON DUPLICATE KEY UPDATE `Email`=VALUES(`Email`);


-- =========================================================================
-- 2. NURSE PORTAL TABLES (Role 6: Nurse on Duty)
-- =========================================================================

CREATE TABLE IF NOT EXISTS `patient_vitals` (
  `VitalID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `BloodPressure` VARCHAR(30) NOT NULL,
  `HeartRate` INT NOT NULL,
  `RespiratoryRate` INT NOT NULL DEFAULT 18,
  `Temperature` DECIMAL(4,1) NOT NULL,
  `OxygenSaturation` INT NOT NULL DEFAULT 98,
  `PainScale` INT NOT NULL DEFAULT 0,
  `WeightKg` DECIMAL(5,2) NULL,
  `HeightCm` DECIMAL(5,2) NULL,
  `BMI` DECIMAL(4,1) NULL,
  `ClinicalNotes` TEXT NULL,
  `RecordedBy` INT NULL,
  `RecordedByName` VARCHAR(100) NOT NULL DEFAULT 'Nurse Elena Gomez, RN',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  INDEX `idx_vit_patient` (`PatientID`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `nurse_tasks` (
  `TaskID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `NurseID` INT NULL,
  `DoctorID` INT NULL,
  `TaskTitle` VARCHAR(200) NOT NULL,
  `Category` ENUM('Medication Admin', 'Vital Check', 'IV Fluid Replacement', 'Wound Dressing', 'Pre-op Prep', 'General Care') NOT NULL DEFAULT 'Vital Check',
  `DueTime` VARCHAR(30) NOT NULL,
  `Priority` ENUM('Normal', 'Urgent', 'STAT') NOT NULL DEFAULT 'Normal',
  `Status` ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `Remarks` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CompletedAt` DATETIME NULL,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed demo vitals
INSERT INTO `patient_vitals` (`VitalID`, `PatientID`, `BloodPressure`, `HeartRate`, `RespiratoryRate`, `Temperature`, `OxygenSaturation`, `PainScale`, `WeightKg`, `HeightCm`, `BMI`, `ClinicalNotes`, `RecordedByName`) VALUES
(1, 1, '120/80', 74, 16, 36.6, 99, 0, 68.5, 172.0, 23.2, 'Patient is resting comfortably, normal sinus rhythm.', 'Elena Gomez, RN'),
(2, 2, '130/85', 82, 18, 36.8, 98, 4, 58.0, 160.0, 22.7, 'Moderate tension headache reported.', 'Elena Gomez, RN'),
(3, 3, '138/88', 88, 20, 36.7, 98, 2, 74.0, 168.0, 26.2, 'Fluttering sensation, monitoring requested by Dr. Lewis.', 'Elena Gomez, RN'),
(4, 4, '118/76', 70, 16, 36.5, 99, 0, 62.0, 165.0, 22.8, 'Routine pre-consultation vitals.', 'Elena Gomez, RN'),
(5, 5, '124/82', 76, 17, 36.6, 99, 1, 70.0, 170.0, 24.2, 'Stable baseline vitals.', 'Elena Gomez, RN')
ON DUPLICATE KEY UPDATE `BloodPressure`=VALUES(`BloodPressure`);


-- =========================================================================
-- 3. MEDICAL TECHNOLOGIST TABLES (Role 7: Medical Technologist)
-- =========================================================================

CREATE TABLE IF NOT EXISTS `test_catalog` (
  `CatalogID` INT AUTO_INCREMENT PRIMARY KEY,
  `TestCode` VARCHAR(50) NOT NULL UNIQUE,
  `TestName` VARCHAR(200) NOT NULL,
  `Category` ENUM('Hematology', 'Clinical Chemistry', 'Urinalysis', 'Microbiology', 'Serology', 'Immunology', 'Blood Banking') NOT NULL DEFAULT 'Clinical Chemistry',
  `SpecimenType` VARCHAR(100) NOT NULL DEFAULT 'Whole Blood / Serum',
  `TurnaroundTime` VARCHAR(50) NOT NULL DEFAULT '2–4 Hours',
  `StandardPrice` DECIMAL(10,2) NOT NULL DEFAULT 350.00,
  `Status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reference_ranges` (
  `RangeID` INT AUTO_INCREMENT PRIMARY KEY,
  `CatalogID` INT NOT NULL,
  `ParameterName` VARCHAR(100) NOT NULL,
  `Unit` VARCHAR(50) NOT NULL,
  `MaleRange` VARCHAR(100) NOT NULL,
  `FemaleRange` VARCHAR(100) NOT NULL,
  `CriticalLow` VARCHAR(50) NULL,
  `CriticalHigh` VARCHAR(50) NULL,
  FOREIGN KEY (`CatalogID`) REFERENCES `test_catalog`(`CatalogID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `laboratory_samples` (
  `SampleID` INT AUTO_INCREMENT PRIMARY KEY,
  `SampleBarcode` VARCHAR(50) NOT NULL UNIQUE,
  `RequestID` INT NOT NULL,
  `PatientID` INT NOT NULL,
  `SpecimenType` VARCHAR(100) NOT NULL,
  `CollectionDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CollectedBy` VARCHAR(100) NOT NULL DEFAULT 'Clarisse Mae Santos, RMT',
  `ProcessingStatus` ENUM('Collected', 'In Lab', 'Processing', 'Analyzed', 'Rejected') NOT NULL DEFAULT 'Collected',
  `StorageLocation` VARCHAR(100) NOT NULL DEFAULT 'Rack A-04 (Centrifuge Bay)',
  `Notes` TEXT NULL,
  FOREIGN KEY (`RequestID`) REFERENCES `laboratory_requests`(`RequestID`) ON DELETE CASCADE,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed standard catalog
INSERT INTO `test_catalog` (`CatalogID`, `TestCode`, `TestName`, `Category`, `SpecimenType`, `TurnaroundTime`, `StandardPrice`) VALUES
(1, 'LAB-CBC', 'Complete Blood Count (CBC)', 'Hematology', 'EDTA Whole Blood', '1–2 Hours', 280.00),
(2, 'LAB-LIP', 'Lipid Profile Panel', 'Clinical Chemistry', 'Serum (Fasting 10-12h)', '3–4 Hours', 650.00),
(3, 'LAB-FBS', 'Fasting Blood Sugar (FBS)', 'Clinical Chemistry', 'Fluoride Oxalate / Serum', '1–2 Hours', 180.00),
(4, 'LAB-URN', 'Routine Urinalysis', 'Urinalysis', 'Midstream Clean Catch Urine', '1 Hour', 150.00),
(5, 'LAB-KID', 'Kidney Function (BUN, Creatinine)', 'Clinical Chemistry', 'Serum', '2–3 Hours', 420.00),
(6, 'LAB-LIV', 'Liver Function Panel (SGOT, SGPT)', 'Clinical Chemistry', 'Serum', '2–3 Hours', 550.00)
ON DUPLICATE KEY UPDATE `TestName`=VALUES(`TestName`);


-- =========================================================================
-- 4. PHARMACY INVENTORY & DISPENSING TABLES (Role 8: Pharmacist)
-- =========================================================================

CREATE TABLE IF NOT EXISTS `pharmacy_inventory` (
  `InventoryID` INT AUTO_INCREMENT PRIMARY KEY,
  `ItemCode` VARCHAR(50) NOT NULL UNIQUE,
  `GenericName` VARCHAR(200) NOT NULL,
  `BrandName` VARCHAR(150) NULL,
  `DosageForm` VARCHAR(80) NOT NULL DEFAULT 'Tablet',
  `Strength` VARCHAR(80) NOT NULL,
  `Category` VARCHAR(100) NOT NULL DEFAULT 'Essential Medicine',
  `UnitCost` DECIMAL(10,2) NOT NULL DEFAULT 5.00,
  `SellingPrice` DECIMAL(10,2) NOT NULL DEFAULT 8.50,
  `CurrentStock` INT NOT NULL DEFAULT 100,
  `ReorderLevel` INT NOT NULL DEFAULT 30,
  `BatchNumber` VARCHAR(80) NOT NULL DEFAULT 'B-2026-001',
  `ExpiryDate` DATE NOT NULL,
  `Supplier` VARCHAR(150) NOT NULL DEFAULT 'DOH Central Depot / Mercury Drug Wholesale',
  `Status` VARCHAR(50) NOT NULL DEFAULT 'In Stock',
  `UpdatedAt` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dispensing_records` (
  `DispenseID` INT AUTO_INCREMENT PRIMARY KEY,
  `DispenseCode` VARCHAR(50) NOT NULL UNIQUE,
  `PrescriptionID` INT NOT NULL,
  `PatientID` INT NOT NULL,
  `DispensedBy` INT NULL,
  `DispenserName` VARCHAR(100) NOT NULL DEFAULT 'Kareen Joy Ramos, RPh',
  `QuantityDispensed` VARCHAR(50) NOT NULL,
  `DosageInstructions` TEXT NOT NULL,
  `BatchNumber` VARCHAR(80) NULL,
  `DispenseDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Status` ENUM('Dispensed', 'Partially Dispensed', 'Cancelled') NOT NULL DEFAULT 'Dispensed',
  `Notes` TEXT NULL,
  FOREIGN KEY (`PrescriptionID`) REFERENCES `prescriptions`(`PrescriptionID`) ON DELETE CASCADE,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pharmacy_stock_movements` (
  `MovementID` INT AUTO_INCREMENT PRIMARY KEY,
  `InventoryID` INT NOT NULL,
  `MovementType` ENUM('Stock In', 'Dispensed', 'Damaged', 'Expired Disposal', 'Adjustment') NOT NULL,
  `Quantity` INT NOT NULL,
  `ReferenceCode` VARCHAR(100) NULL,
  `RecordedBy` VARCHAR(100) NOT NULL DEFAULT 'Pharmacist Staff',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`InventoryID`) REFERENCES `pharmacy_inventory`(`InventoryID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed standard medicine inventory
INSERT INTO `pharmacy_inventory` (`InventoryID`, `ItemCode`, `GenericName`, `BrandName`, `DosageForm`, `Strength`, `Category`, `UnitCost`, `SellingPrice`, `CurrentStock`, `ReorderLevel`, `BatchNumber`, `ExpiryDate`, `Status`) VALUES
(1, 'MED-AMOX-500', 'Amoxicillin Trihydrate', 'Amoxil', 'Capsule', '500mg', 'Antibiotic', 4.50, 7.50, 450, 100, 'B-2026-081', DATE_ADD(CURDATE(), INTERVAL 540 DAY), 'In Stock'),
(2, 'MED-METO-50', 'Metoprolol Tartrate', 'Betaloc', 'Tablet', '50mg', 'Antihypertensive', 5.20, 9.00, 280, 50, 'B-2026-042', DATE_ADD(CURDATE(), INTERVAL 620 DAY), 'In Stock'),
(3, 'MED-AMLO-5', 'Amlodipine Besylate', 'Norvasc', 'Tablet', '5mg', 'Antihypertensive', 3.80, 6.50, 320, 60, 'B-2026-019', DATE_ADD(CURDATE(), INTERVAL 480 DAY), 'In Stock'),
(4, 'MED-ATOR-40', 'Atorvastatin Calcium', 'Lipitor', 'Tablet', '40mg', 'Cardiovascular / Statin', 12.00, 18.50, 190, 40, 'B-2026-092', DATE_ADD(CURDATE(), INTERVAL 400 DAY), 'In Stock'),
(5, 'MED-LOSA-50', 'Losartan Potassium', 'Cozaar', 'Tablet', '50mg', 'Antihypertensive', 6.00, 10.00, 310, 50, 'B-2026-033', DATE_ADD(CURDATE(), INTERVAL 500 DAY), 'In Stock'),
(6, 'MED-METF-500', 'Metformin Hydrochloride', 'Glucophage', 'Tablet', '500mg', 'Antidiabetic', 2.50, 4.50, 500, 100, 'B-2026-055', DATE_ADD(CURDATE(), INTERVAL 700 DAY), 'In Stock'),
(7, 'MED-PARA-500', 'Paracetamol', 'Biogesic', 'Tablet', '500mg', 'Analgesic / Antipyretic', 1.80, 3.50, 40, 50, 'B-2026-012', DATE_ADD(CURDATE(), INTERVAL 360 DAY), 'Low Stock')
ON DUPLICATE KEY UPDATE `GenericName`=VALUES(`GenericName`), `CurrentStock`=VALUES(`CurrentStock`);


-- =========================================================================
-- 5. BILLING & CASHIER TABLES (Role 9: Billing / Cashier Staff)
-- =========================================================================

CREATE TABLE IF NOT EXISTS `billing_charges` (
  `ChargeID` INT AUTO_INCREMENT PRIMARY KEY,
  `PatientID` INT NOT NULL,
  `AppointmentID` INT NULL,
  `FeeID` INT NULL,
  `ChargeCategory` ENUM('Consultation', 'Laboratory', 'Radiology', 'Pharmacy', 'Nursing/Ward', 'Surgical/Procedure', 'Administrative', 'Emergency') NOT NULL DEFAULT 'Consultation',
  `ItemDescription` VARCHAR(255) NOT NULL,
  `Quantity` INT NOT NULL DEFAULT 1,
  `UnitPrice` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `SubTotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `DiscountAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `NetAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `BillingStatus` ENUM('Unbilled', 'Invoiced', 'Billed', 'Paid', 'Waived') NOT NULL DEFAULT 'Unbilled',
  `CreatedBy` VARCHAR(100) NOT NULL DEFAULT 'Billing Staff',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE,
  FOREIGN KEY (`FeeID`) REFERENCES `service_fees`(`FeeID`) ON DELETE SET NULL,
  INDEX `idx_chg_patient` (`PatientID`, `BillingStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `billing_invoices` (
  `InvoiceID` INT AUTO_INCREMENT PRIMARY KEY,
  `InvoiceNumber` VARCHAR(50) NOT NULL UNIQUE,
  `PatientID` INT NOT NULL,
  `GrossAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `DiscountType` ENUM('None', 'Senior Citizen (20%)', 'PWD (20%)', 'Indigent / Barangay (100%)', 'Hospital Employee (50%)') NOT NULL DEFAULT 'None',
  `DiscountAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `PhilHealthDeduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `TotalPayable` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `AmountPaid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `BalanceDue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `PaymentStatus` ENUM('Unpaid', 'Partially Paid', 'Paid In Full', 'Cancelled') NOT NULL DEFAULT 'Unpaid',
  `BilledBy` VARCHAR(100) NOT NULL DEFAULT 'Maria Santos (Cashier)',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `DueDate` DATE NOT NULL,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `billing_payments` (
  `PaymentID` INT AUTO_INCREMENT PRIMARY KEY,
  `ReceiptNumber` VARCHAR(50) NOT NULL UNIQUE,
  `InvoiceID` INT NOT NULL,
  `PatientID` INT NOT NULL,
  `AmountPaid` DECIMAL(10,2) NOT NULL,
  `PaymentMethod` ENUM('Cash', 'GCash / Maya', 'Credit/Debit Card', 'Bank Transfer', 'PhilHealth Direct') NOT NULL DEFAULT 'Cash',
  `ReferenceNumber` VARCHAR(100) NULL,
  `AmountInWords` TEXT NOT NULL,
  `CashierName` VARCHAR(100) NOT NULL DEFAULT 'Maria Santos',
  `PaymentDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`InvoiceID`) REFERENCES `billing_invoices`(`InvoiceID`) ON DELETE CASCADE,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed demo initial charges
INSERT INTO `billing_charges` (`ChargeID`, `PatientID`, `ChargeCategory`, `ItemDescription`, `Quantity`, `UnitPrice`, `SubTotal`, `DiscountAmount`, `NetAmount`, `BillingStatus`) VALUES
(1, 3, 'Consultation', 'Specialist Consultation - Cardiology (Dr. Lewis)', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled'),
(2, 3, 'Laboratory', '12-Lead Electrocardiogram (ECG)', 1, 350.00, 350.00, 0.00, 350.00, 'Unbilled'),
(3, 3, 'Pharmacy', 'Metoprolol Tartrate 50mg (60 Tabs)', 60, 9.00, 540.00, 0.00, 540.00, 'Unbilled'),
(4, 10, 'Consultation', 'Cardiovascular Follow-up Consultation', 1, 500.00, 500.00, 100.00, 400.00, 'Paid'),
(5, 2, 'Consultation', 'Neurology Consultation (Dr. Villanueva)', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled')
ON DUPLICATE KEY UPDATE `ItemDescription`=VALUES(`ItemDescription`);


-- =========================================================================
-- 6. MEDICAL RECORDS OFFICER TABLES (Role 3: Medical Records Officer)
-- =========================================================================

CREATE TABLE IF NOT EXISTS `record_release_requests` (
  `RequestID` INT AUTO_INCREMENT PRIMARY KEY,
  `RequestNumber` VARCHAR(50) NOT NULL UNIQUE,
  `PatientID` INT NOT NULL,
  `RequestType` ENUM('Complete Medical History', 'Clinical Summary', 'Laboratory Results Only', 'Certificate of Confinement', 'Insurance Abstract') NOT NULL,
  `RequestorName` VARCHAR(150) NOT NULL,
  `RelationshipToPatient` VARCHAR(80) NOT NULL DEFAULT 'Self',
  `PurposeOfRequest` VARCHAR(255) NOT NULL,
  `Status` ENUM('Pending Review', 'Approved / Processing', 'Released', 'Rejected') NOT NULL DEFAULT 'Pending Review',
  `ProcessedBy` VARCHAR(100) NULL,
  `RequestedDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ReleasedDate` DATETIME NULL,
  FOREIGN KEY (`PatientID`) REFERENCES `patients`(`PatientID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `record_release_requests` (`RequestID`, `RequestNumber`, `PatientID`, `RequestType`, `RequestorName`, `RelationshipToPatient`, `PurposeOfRequest`, `Status`, `ProcessedBy`) VALUES
(1, 'REQ-2026-001', 3, 'Clinical Summary', 'Eduardo Bautista', 'Self', 'PhilHealth Claims & Employer Sickness Benefit', 'Approved / Processing', 'Mark Anthony Valenzuela, RMT'),
(2, 'REQ-2026-002', 10, 'Complete Medical History', 'Angelica Morales', 'Self', 'Personal Health Record & Specialist Transfer', 'Released', 'Mark Anthony Valenzuela, RMT')
ON DUPLICATE KEY UPDATE `RequestNumber`=VALUES(`RequestNumber`);

SET FOREIGN_KEY_CHECKS=1;

-- --------------------------------------------------------------------------
-- 4. MASTER COMPREHENSIVE SEED DATA (ALL 9 ROLES & 30+ REALISTIC RECORDS)
-- --------------------------------------------------------------------------
-- Tupi Municipal Hospital Information Management System
-- Comprehensive Realistic Test Dataset (30+ records per major table)


SET FOREIGN_KEY_CHECKS=0;

-- ============================================================================
-- 1. USER ACCOUNTS & FDD ROLES
-- ============================================================================
ALTER TABLE `users` MODIFY COLUMN `Role` VARCHAR(50) NOT NULL DEFAULT 'Registrator';

INSERT INTO `users` (`UserID`, `FirstName`, `LastName`, `Username`, `PasswordHash`, `Role`, `Email`, `Status`) VALUES
(1, 'System', 'Administrator', 'admin', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Admin', 'admin@tupihospital.gov.ph', 'Active'),
(2, 'Maria', 'Santos', 'director', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Chief', 'director@tupihospital.gov.ph', 'Active'),
(3, 'Mark Anthony', 'Valenzuela', 'records', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Records', 'records@tupihospital.gov.ph', 'Active'),
(4, 'Sarah', 'Jenkins', 'registrator', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Register', 'registrator@tupihospital.gov.ph', 'Active'),
(5, 'Kenneth', 'Garcia', 'doctor', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Doctor', 'doctor@tupihospital.gov.ph', 'Active'),
(6, 'Elena', 'Gomez', 'nurse', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Nurse', 'nurse@tupihospital.gov.ph', 'Active'),
(7, 'Clarisse Mae', 'Santos', 'medtech', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'MedTech', 'medtech@tupihospital.gov.ph', 'Active'),
(8, 'Kareen Joy', 'Ramos', 'pharmacist', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Pharmacist', 'pharmacist@tupihospital.gov.ph', 'Active'),
(9, 'Maria', 'Castillo', 'cashier', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Billing', 'cashier@tupihospital.gov.ph', 'Active'),
(10, 'Daniel', 'Lewis', 'dr.lewis', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Doctor', 'dr.lewis@tupihospital.gov.ph', 'Active'),
(11, 'Elena', 'Villanueva', 'dr.villanueva', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Doctor', 'dr.villanueva@tupihospital.gov.ph', 'Active'),
(12, 'James', 'Wilson', 'dr.wilson', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Doctor', 'dr.wilson@tupihospital.gov.ph', 'Active'),
(13, 'Sophia', 'Miller', 'dr.miller', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Doctor', 'dr.miller@tupihospital.gov.ph', 'Active'),
(14, 'Carlos', 'Reyes', 'staff.reyes', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Nurse', 'carlos.reyes@tupihospital.gov.ph', 'Active'),
(15, 'Ana', 'Bautista', 'staff.bautista', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Nurse', 'ana.bautista@tupihospital.gov.ph', 'Active'),
(16, 'Michael', 'Chang', 'registrator2', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Register', 'michael.chang@tupihospital.gov.ph', 'Active'),
(17, 'Joy', 'Mendoza', 'medtech2', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'MedTech', 'joy.mendoza@tupihospital.gov.ph', 'Active'),
(18, 'Grace', 'DelaCruz', 'pharmacist2', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Pharmacist', 'grace.delacruz@tupihospital.gov.ph', 'Active'),
(19, 'Arthur', 'Morales', 'cashier2', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Billing', 'arthur.morales@tupihospital.gov.ph', 'Active'),
(20, 'Teresa', 'Aquino', 'records2', '$2y$10$sQ1r8ZdEa2qFDzL5jpKoc.ZWL1nQZ2hdbcHmdj8FmZiYn8buCtzvy', 'Records', 'teresa.aquino@tupihospital.gov.ph', 'Active')
ON DUPLICATE KEY UPDATE `FirstName`=VALUES(`FirstName`), `LastName`=VALUES(`LastName`), `Username`=VALUES(`Username`), `Role`=VALUES(`Role`), `Email`=VALUES(`Email`), `PasswordHash`=VALUES(`PasswordHash`), `Status`=VALUES(`Status`);

-- ============================================================================
-- 2. PATIENTS MASTER (35 Realistic Tupi Patients)
-- ============================================================================
INSERT INTO `patients` (`PatientID`, `PatientCode`, `FirstName`, `MiddleName`, `LastName`, `DateOfBirth`, `Age`, `Gender`, `CivilStatus`, `ContactNumber`, `Email`, `Address`, `BloodType`, `PatientCategory`, `Status`, `RegisteredBy`, `CreatedAt`) VALUES
(1, 'PAT-2026-0001', 'Juan', 'Ocampo', 'Reyes', '1952-02-04', 74, 'Male', 'Married', '+63 918 117 1073', 'juan.reyes1@example.com', 'Purok 2, Brgy. Acmonan, Tupi, South Cotabato 9505', 'A+', 'Consultation', 'Active', 4, '2026-08-02 09:07:00'),
(2, 'PAT-2026-0002', 'Corazon', 'Garcia', 'Bautista', '1954-03-07', 72, 'Female', 'Widowed', '+63 919 134 1146', 'corazon.bautista2@example.com', 'Purok 3, Brgy. Bololmala, Tupi, South Cotabato 9505', 'B+', 'Admitted', 'Active', 4, '2026-08-03 10:14:00'),
(3, 'PAT-2026-0003', 'Jose', 'Mendoza', 'Ocampo', '1956-04-10', 70, 'Male', 'Divorced', '+63 920 151 1219', 'jose.ocampo3@example.com', 'Purok 4, Brgy. Bunao, Tupi, South Cotabato 9505', 'AB+', 'Emergency', 'Active', 4, '2026-08-04 11:21:00'),
(4, 'PAT-2026-0004', 'Teresa', 'Torres', 'Garcia', '1958-05-13', 68, 'Female', 'Single', '+63 921 168 1292', 'teresa.garcia4@example.com', 'Purok 5, Brgy. Crossing Rubber, Tupi, South Cotabato 9505', 'O-', 'Inpatient', 'Active', 4, '2026-08-05 12:28:00'),
(5, 'PAT-2026-0005', 'Ramon', 'Tomas', 'Mendoza', '1960-06-16', 66, 'Male', 'Married', '+63 922 185 1365', 'ramon.mendoza5@example.com', 'Purok 6, Brgy. Kablon, Tupi, South Cotabato 9505', 'A-', 'Outpatient', 'Active', 4, '2026-08-06 13:35:00'),
(6, 'PAT-2026-0006', 'Rosario', 'Andrada', 'Torres', '1962-07-19', 64, 'Female', 'Widowed', '+63 923 202 1438', 'rosario.torres6@example.com', 'Purok 7, Brgy. Kalkam, Tupi, South Cotabato 9505', 'B-', 'Consultation', 'Active', 4, '2026-08-07 14:42:00'),
(7, 'PAT-2026-0007', 'Eduardo', 'Castro', 'Tomas', '1964-08-22', 62, 'Male', 'Divorced', '+63 924 219 1511', 'eduardo.tomas7@example.com', 'Purok 1, Brgy. Linan, Tupi, South Cotabato 9505', 'O+', 'Admitted', 'Active', 4, '2026-08-08 15:49:00'),
(8, 'PAT-2026-0008', 'Christina', 'Flores', 'Andrada', '1966-09-25', 60, 'Female', 'Single', '+63 925 236 1584', 'christina.andrada8@example.com', 'Purok 2, Brgy. Lunen, Tupi, South Cotabato 9505', 'A+', 'Emergency', 'Active', 4, '2026-08-09 08:56:00'),
(9, 'PAT-2026-0009', 'Gabriel', 'Gonzales', 'Castro', '1968-10-28', 58, 'Male', 'Married', '+63 926 253 1657', 'gabriel.castro9@example.com', 'Purok 3, Brgy. Polonuling, Tupi, South Cotabato 9505', 'B+', 'Inpatient', 'Active', 4, '2026-08-10 09:03:00'),
(10, 'PAT-2026-0010', 'Patricia', 'Ramos', 'Flores', '1970-11-03', 56, 'Female', 'Widowed', '+63 917 270 1730', 'patricia.flores10@example.com', 'Purok 4, Brgy. Simbo, Tupi, South Cotabato 9505', 'AB+', 'Outpatient', 'Active', 4, '2026-08-11 10:10:00'),
(11, 'PAT-2026-0011', 'Antonio', 'Lopez', 'Gonzales', '1972-12-06', 54, 'Male', 'Divorced', '+63 918 287 1803', 'antonio.gonzales11@example.com', 'Purok 5, Brgy. Tubo, Tupi, South Cotabato 9505', 'O-', 'Consultation', 'Active', 4, '2026-08-12 11:17:00'),
(12, 'PAT-2026-0012', 'Eileen', 'Mercado', 'Ramos', '1974-01-09', 52, 'Female', 'Single', '+63 919 304 1876', 'eileen.ramos12@example.com', 'Purok 6, Brgy. Poblacion, Tupi, South Cotabato 9505', 'A-', 'Admitted', 'Active', 4, '2026-08-13 12:24:00'),
(13, 'PAT-2026-0013', 'Ricardo', 'Aquino', 'Lopez', '1976-02-12', 50, 'Male', 'Married', '+63 920 321 1949', 'ricardo.lopez13@example.com', 'Purok 7, Brgy. Acmonan, Tupi, South Cotabato 9505', 'B-', 'Emergency', 'Active', 4, '2026-08-14 13:31:00'),
(14, 'PAT-2026-0014', 'Rowena', 'Morales', 'Mercado', '1978-03-15', 48, 'Female', 'Widowed', '+63 921 338 2022', 'rowena.mercado14@example.com', 'Purok 1, Brgy. Bololmala, Tupi, South Cotabato 9505', 'O+', 'Inpatient', 'Active', 4, '2026-08-15 14:38:00'),
(15, 'PAT-2026-0015', 'Christian', 'Villanueva', 'Aquino', '1980-04-18', 46, 'Male', 'Divorced', '+63 922 355 2095', 'christian.aquino15@example.com', 'Purok 2, Brgy. Bunao, Tupi, South Cotabato 9505', 'A+', 'Outpatient', 'Active', 4, '2026-08-16 15:45:00'),
(16, 'PAT-2026-0016', 'Maria', 'Fernandez', 'Morales', '1982-05-21', 44, 'Female', 'Single', '+63 923 372 2168', 'maria.morales16@example.com', 'Purok 3, Brgy. Crossing Rubber, Tupi, South Cotabato 9505', 'B+', 'Consultation', 'Active', 4, '2026-08-17 08:52:00'),
(17, 'PAT-2026-0017', 'Pedro', 'Dela Cruz', 'Villanueva', '1984-06-24', 42, 'Male', 'Married', '+63 924 389 2241', 'pedro.villanueva17@example.com', 'Purok 4, Brgy. Kablon, Tupi, South Cotabato 9505', 'AB+', 'Admitted', 'Active', 4, '2026-08-18 09:59:00'),
(18, 'PAT-2026-0018', 'Lourdes', 'Santos', 'Fernandez', '1986-07-27', 40, 'Female', 'Widowed', '+63 925 406 2314', 'lourdes.fernandez18@example.com', 'Purok 5, Brgy. Kalkam, Tupi, South Cotabato 9505', 'O-', 'Emergency', 'Active', 4, '2026-08-19 10:06:00'),
(19, 'PAT-2026-0019', 'Manuel', 'Reyes', 'Dela Cruz', '1988-08-02', 38, 'Male', 'Divorced', '+63 926 423 2387', 'manuel.delacruz19@example.com', 'Purok 6, Brgy. Linan, Tupi, South Cotabato 9505', 'A-', 'Inpatient', 'Active', 4, '2026-08-20 11:13:00'),
(20, 'PAT-2026-0020', 'Carmela', 'Bautista', 'Santos', '1990-09-05', 36, 'Female', 'Single', '+63 917 440 2460', 'carmela.santos20@example.com', 'Purok 7, Brgy. Lunen, Tupi, South Cotabato 9505', 'B-', 'Outpatient', 'Active', 4, '2026-08-21 12:20:00'),
(21, 'PAT-2026-0021', 'Danilo', 'Ocampo', 'Reyes', '1992-10-08', 34, 'Male', 'Married', '+63 918 457 2533', 'danilo.reyes21@example.com', 'Purok 1, Brgy. Polonuling, Tupi, South Cotabato 9505', 'O+', 'Consultation', 'Active', 4, '2026-08-22 13:27:00'),
(22, 'PAT-2026-0022', 'Angelica', 'Garcia', 'Bautista', '1994-11-11', 32, 'Female', 'Widowed', '+63 919 474 2606', 'angelica.bautista22@example.com', 'Purok 2, Brgy. Simbo, Tupi, South Cotabato 9505', 'A+', 'Admitted', 'Active', 4, '2026-08-23 14:34:00'),
(23, 'PAT-2026-0023', 'Roberto', 'Mendoza', 'Ocampo', '1996-12-14', 30, 'Male', 'Divorced', '+63 920 491 2679', 'roberto.ocampo23@example.com', 'Purok 3, Brgy. Tubo, Tupi, South Cotabato 9505', 'B+', 'Emergency', 'Active', 4, '2026-08-24 15:41:00'),
(24, 'PAT-2026-0024', 'Jasmine', 'Torres', 'Garcia', '1998-01-17', 28, 'Female', 'Single', '+63 921 508 2752', 'jasmine.garcia24@example.com', 'Purok 4, Brgy. Poblacion, Tupi, South Cotabato 9505', 'AB+', 'Inpatient', 'Active', 4, '2026-08-25 08:48:00'),
(25, 'PAT-2026-0025', 'Rolando', 'Tomas', 'Mendoza', '2000-02-20', 26, 'Male', 'Married', '+63 922 525 2825', 'rolando.mendoza25@example.com', 'Purok 5, Brgy. Acmonan, Tupi, South Cotabato 9505', 'O-', 'Outpatient', 'Active', 4, '2026-08-26 09:55:00'),
(26, 'PAT-2026-0026', 'Divina', 'Andrada', 'Torres', '1950-03-23', 76, 'Female', 'Widowed', '+63 923 542 2898', 'divina.torres26@example.com', 'Purok 6, Brgy. Bololmala, Tupi, South Cotabato 9505', 'A-', 'Consultation', 'Active', 4, '2026-08-27 10:02:00'),
(27, 'PAT-2026-0027', 'Ferdinand', 'Castro', 'Tomas', '1952-04-26', 74, 'Male', 'Divorced', '+63 924 559 2971', 'ferdinand.tomas27@example.com', 'Purok 7, Brgy. Bunao, Tupi, South Cotabato 9505', 'B-', 'Admitted', 'Active', 4, '2026-08-28 11:09:00'),
(28, 'PAT-2026-0028', 'Rochelle', 'Flores', 'Andrada', '1954-05-01', 72, 'Female', 'Single', '+63 925 576 3044', 'rochelle.andrada28@example.com', 'Purok 1, Brgy. Crossing Rubber, Tupi, South Cotabato 9505', 'O+', 'Emergency', 'Active', 4, '2026-08-01 12:16:00'),
(29, 'PAT-2026-0029', 'Angelo', 'Gonzales', 'Castro', '1956-06-04', 70, 'Male', 'Married', '+63 926 593 3117', 'angelo.castro29@example.com', 'Purok 2, Brgy. Kablon, Tupi, South Cotabato 9505', 'A+', 'Inpatient', 'Active', 4, '2026-08-02 13:23:00'),
(30, 'PAT-2026-0030', 'Beatriz', 'Ramos', 'Flores', '1958-07-07', 68, 'Female', 'Widowed', '+63 917 610 3190', 'beatriz.flores30@example.com', 'Purok 3, Brgy. Kalkam, Tupi, South Cotabato 9505', 'B+', 'Outpatient', 'Active', 4, '2026-08-03 14:30:00'),
(31, 'PAT-2026-0031', 'Juan', 'Lopez', 'Gonzales', '1960-08-10', 66, 'Male', 'Divorced', '+63 918 627 3263', 'juan.gonzales31@example.com', 'Purok 4, Brgy. Linan, Tupi, South Cotabato 9505', 'AB+', 'Consultation', 'Active', 4, '2026-08-04 15:37:00'),
(32, 'PAT-2026-0032', 'Corazon', 'Mercado', 'Ramos', '1962-09-13', 64, 'Female', 'Single', '+63 919 644 3336', 'corazon.ramos32@example.com', 'Purok 5, Brgy. Lunen, Tupi, South Cotabato 9505', 'O-', 'Admitted', 'Active', 4, '2026-08-05 08:44:00'),
(33, 'PAT-2026-0033', 'Jose', 'Aquino', 'Lopez', '1964-10-16', 62, 'Male', 'Married', '+63 920 661 3409', 'jose.lopez33@example.com', 'Purok 6, Brgy. Polonuling, Tupi, South Cotabato 9505', 'A-', 'Emergency', 'Active', 4, '2026-08-06 09:51:00'),
(34, 'PAT-2026-0034', 'Teresa', 'Morales', 'Mercado', '1966-11-19', 60, 'Female', 'Widowed', '+63 921 678 3482', 'teresa.mercado34@example.com', 'Purok 7, Brgy. Simbo, Tupi, South Cotabato 9505', 'B-', 'Inpatient', 'Active', 4, '2026-08-07 10:58:00'),
(35, 'PAT-2026-0035', 'Ramon', 'Villanueva', 'Aquino', '1968-12-22', 58, 'Male', 'Divorced', '+63 922 695 3555', 'ramon.aquino35@example.com', 'Purok 1, Brgy. Tubo, Tupi, South Cotabato 9505', 'O+', 'Outpatient', 'Discharged', 4, '2026-08-08 11:05:00')
ON DUPLICATE KEY UPDATE `PatientCode`=VALUES(`PatientCode`), `Status`=VALUES(`Status`);

-- ============================================================================
-- 3. APPOINTMENTS (35 Records)
-- ============================================================================
INSERT INTO `appointments` (`AppointmentID`, `PatientID`, `DoctorID`, `AppointmentDate`, `AppointmentTime`, `ConsultationType`, `Reason`, `Priority`, `Notes`, `Status`, `CreatedBy`, `CreatedAt`) VALUES
(1, 1, 2, '2026-09-02', '09:15:00', 'Secure Telehealth Video', 'Follow-up blood pressure check', 'Urgent', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, NOW()),
(2, 2, 3, '2026-09-03', '10:30:00', 'Follow-up Review', 'Routine prenatal checkup', 'High Priority', 'Routine clinical evaluation scheduled.', 'Waiting', 4, NOW()),
(3, 3, 4, '2026-09-04', '11:45:00', 'Emergency Triage', 'Persistent dry cough evaluation', 'Follow-up', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, NOW()),
(4, 4, 5, '2026-09-05', '12:00:00', 'In-Person Consultation', 'Mild joint pains in both knees', 'Normal', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(5, 5, 6, '2026-09-06', '13:15:00', 'Secure Telehealth Video', 'Fasting blood sugar follow-up', 'Urgent', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(6, 6, 7, '2026-09-07', '14:30:00', 'Follow-up Review', 'Headache and dizziness consultation', 'High Priority', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, NOW()),
(7, 7, 8, '2026-09-08', '15:45:00', 'Emergency Triage', 'Post-operative wound dressing review', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, NOW()),
(8, 8, 9, '2026-09-09', '08:00:00', 'In-Person Consultation', 'Pediatric immunization follow-up', 'Normal', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, NOW()),
(9, 9, 10, '2026-09-10', '09:15:00', 'Secure Telehealth Video', 'Chest tightness and ECG assessment', 'Urgent', 'Routine clinical evaluation scheduled.', 'Waiting', 4, NOW()),
(10, 10, 1, '2026-09-11', '10:30:00', 'Follow-up Review', 'Abdominal pain after fatty meals', 'High Priority', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, NOW()),
(11, 11, 2, '2026-09-12', '11:45:00', 'Emergency Triage', 'Hypertension monitoring and medication review', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(12, 12, 3, '2026-09-13', '12:00:00', 'In-Person Consultation', 'Follow-up blood pressure check', 'Normal', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(13, 13, 4, '2026-09-14', '13:15:00', 'Secure Telehealth Video', 'Routine prenatal checkup', 'Urgent', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, NOW()),
(14, 14, 5, '2026-09-15', '14:30:00', 'Follow-up Review', 'Persistent dry cough evaluation', 'High Priority', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, NOW()),
(15, 15, 6, '2026-09-16', '15:45:00', 'Emergency Triage', 'Mild joint pains in both knees', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, NOW()),
(16, 16, 7, '2026-09-17', '08:00:00', 'In-Person Consultation', 'Fasting blood sugar follow-up', 'Normal', 'Routine clinical evaluation scheduled.', 'Waiting', 4, NOW()),
(17, 17, 8, '2026-09-18', '09:15:00', 'Secure Telehealth Video', 'Headache and dizziness consultation', 'Urgent', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, NOW()),
(18, 18, 9, '2026-09-19', '10:30:00', 'Follow-up Review', 'Post-operative wound dressing review', 'High Priority', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(19, 19, 10, '2026-09-20', '11:45:00', 'Emergency Triage', 'Pediatric immunization follow-up', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(20, 20, 1, '2026-09-21', '12:00:00', 'In-Person Consultation', 'Chest tightness and ECG assessment', 'Normal', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, NOW()),
(21, 21, 2, '2026-09-22', '13:15:00', 'Secure Telehealth Video', 'Abdominal pain after fatty meals', 'Urgent', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, NOW()),
(22, 22, 3, '2026-09-23', '14:30:00', 'Follow-up Review', 'Hypertension monitoring and medication review', 'High Priority', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, NOW()),
(23, 23, 4, '2026-09-24', '15:45:00', 'Emergency Triage', 'Follow-up blood pressure check', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Waiting', 4, NOW()),
(24, 24, 5, '2026-09-25', '08:00:00', 'In-Person Consultation', 'Routine prenatal checkup', 'Normal', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, NOW()),
(25, 25, 6, '2026-09-26', '09:15:00', 'Secure Telehealth Video', 'Persistent dry cough evaluation', 'Urgent', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(26, 26, 7, '2026-09-27', '10:30:00', 'Follow-up Review', 'Mild joint pains in both knees', 'High Priority', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(27, 27, 8, '2026-09-28', '11:45:00', 'Emergency Triage', 'Fasting blood sugar follow-up', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, NOW()),
(28, 28, 9, '2026-09-01', '12:00:00', 'In-Person Consultation', 'Headache and dizziness consultation', 'Normal', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, NOW()),
(29, 29, 10, '2026-09-02', '13:15:00', 'Secure Telehealth Video', 'Post-operative wound dressing review', 'Urgent', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, NOW()),
(30, 30, 1, '2026-09-03', '14:30:00', 'Follow-up Review', 'Pediatric immunization follow-up', 'High Priority', 'Routine clinical evaluation scheduled.', 'Waiting', 4, NOW()),
(31, 31, 2, '2026-09-04', '15:45:00', 'Emergency Triage', 'Chest tightness and ECG assessment', 'Follow-up', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, NOW()),
(32, 32, 3, '2026-09-05', '08:00:00', 'In-Person Consultation', 'Abdominal pain after fatty meals', 'Normal', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(33, 33, 4, '2026-09-06', '09:15:00', 'Secure Telehealth Video', 'Hypertension monitoring and medication review', 'Urgent', 'Routine clinical evaluation scheduled.', 'Completed', 4, NOW()),
(34, 34, 5, '2026-09-07', '10:30:00', 'Follow-up Review', 'Follow-up blood pressure check', 'High Priority', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, NOW()),
(35, 35, 6, '2026-09-08', '11:45:00', 'Emergency Triage', 'Routine prenatal checkup', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, NOW())
ON DUPLICATE KEY UPDATE `Status`=VALUES(`Status`);

-- ============================================================================
-- 4. PATIENT QUEUE (25 Records)
-- ============================================================================
INSERT INTO `patient_queue` (`QueueID`, `AppointmentID`, `PatientID`, `DoctorID`, `QueueNumber`, `QueueDate`, `QueueStatus`, `Priority`, `CalledAt`, `ConsultationStartedAt`, `CompletedAt`, `CreatedAt`) VALUES
(1, 1, 1, 2, 'Q-001', CURDATE(), 'Called', 'Normal', NULL, NULL, NULL, NOW()),
(2, 2, 2, 3, 'Q-002', CURDATE(), 'In Consultation', 'Normal', NULL, NULL, NULL, NOW()),
(3, 3, 3, 4, 'Q-003', CURDATE(), 'Completed', 'Normal', NULL, NULL, NULL, NOW()),
(4, 4, 4, 5, 'Q-004', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(5, 5, 5, 6, 'Q-005', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(6, 6, 6, 7, 'Q-006', CURDATE(), 'Called', 'Normal', NULL, NULL, NULL, NOW()),
(7, 7, 7, 8, 'Q-007', CURDATE(), 'In Consultation', 'Normal', NULL, NULL, NULL, NOW()),
(8, 8, 8, 9, 'Q-008', CURDATE(), 'Completed', 'Normal', NULL, NULL, NULL, NOW()),
(9, 9, 9, 10, 'Q-009', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(10, 10, 10, 1, 'Q-010', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(11, 11, 11, 2, 'Q-011', CURDATE(), 'Called', 'Normal', NULL, NULL, NULL, NOW()),
(12, 12, 12, 3, 'Q-012', CURDATE(), 'In Consultation', 'Normal', NULL, NULL, NULL, NOW()),
(13, 13, 13, 4, 'Q-013', CURDATE(), 'Completed', 'Normal', NULL, NULL, NULL, NOW()),
(14, 14, 14, 5, 'Q-014', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(15, 15, 15, 6, 'Q-015', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(16, 16, 16, 7, 'Q-016', CURDATE(), 'Called', 'Normal', NULL, NULL, NULL, NOW()),
(17, 17, 17, 8, 'Q-017', CURDATE(), 'In Consultation', 'Normal', NULL, NULL, NULL, NOW()),
(18, 18, 18, 9, 'Q-018', CURDATE(), 'Completed', 'Normal', NULL, NULL, NULL, NOW()),
(19, 19, 19, 10, 'Q-019', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(20, 20, 20, 1, 'Q-020', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(21, 21, 21, 2, 'Q-021', CURDATE(), 'Called', 'Normal', NULL, NULL, NULL, NOW()),
(22, 22, 22, 3, 'Q-022', CURDATE(), 'In Consultation', 'Normal', NULL, NULL, NULL, NOW()),
(23, 23, 23, 4, 'Q-023', CURDATE(), 'Completed', 'Normal', NULL, NULL, NULL, NOW()),
(24, 24, 24, 5, 'Q-024', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW()),
(25, 25, 25, 6, 'Q-025', CURDATE(), 'Waiting', 'Normal', NULL, NULL, NULL, NOW())
ON DUPLICATE KEY UPDATE `QueueNumber`=VALUES(`QueueNumber`);

-- ============================================================================
-- 5. PATIENT VITAL SIGNS (30 Records)
-- ============================================================================
INSERT INTO `patient_vitals` (`VitalID`, `PatientID`, `AppointmentID`, `BloodPressure`, `HeartRate`, `RespiratoryRate`, `Temperature`, `OxygenSaturation`, `PainScale`, `WeightKg`, `HeightCm`, `BMI`, `ClinicalNotes`, `RecordedBy`, `RecordedByName`, `CreatedAt`) VALUES
(1, 1, 1, '113/72', 71, 17, 36.5, 97, 1, 51.5, 153.2, 21.9, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(2, 2, 2, '116/74', 74, 18, 36.7, 98, 2, 53.0, 154.4, 22.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(3, 3, 3, '119/76', 77, 19, 36.9, 99, 3, 54.5, 155.6, 22.5, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(4, 4, 4, '122/78', 80, 20, 37.0, 96, 4, 56.0, 156.8, 22.8, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(5, 5, 5, '125/80', 83, 21, 37.1, 97, 5, 57.5, 158.0, 23.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(6, 6, 6, '128/82', 86, 16, 37.3, 98, 0, 59.0, 159.2, 23.3, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(7, 7, 7, '131/84', 89, 17, 37.4, 99, 1, 60.5, 160.4, 23.5, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(8, 8, 8, '134/86', 92, 18, 37.6, 96, 2, 62.0, 161.6, 23.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(9, 9, 9, '137/88', 95, 19, 37.8, 97, 3, 63.5, 162.8, 24.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(10, 10, 10, '140/90', 98, 20, 36.4, 98, 4, 65.0, 164.0, 24.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(11, 11, 11, '143/92', 69, 21, 36.5, 99, 5, 66.5, 165.2, 24.4, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(12, 12, 12, '146/94', 72, 16, 36.7, 96, 0, 68.0, 166.4, 24.6, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(13, 13, 13, '149/71', 75, 17, 36.9, 97, 1, 69.5, 167.6, 24.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(14, 14, 14, '152/73', 78, 18, 37.0, 98, 2, 71.0, 168.8, 24.9, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(15, 15, 15, '110/75', 81, 19, 37.1, 99, 3, 72.5, 170.0, 25.1, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(16, 16, 16, '113/77', 84, 20, 37.3, 96, 4, 74.0, 171.2, 25.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(17, 17, 17, '116/79', 87, 21, 37.4, 97, 5, 75.5, 172.4, 25.4, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(18, 18, 18, '119/81', 90, 16, 37.6, 98, 0, 77.0, 173.6, 25.6, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(19, 19, 19, '122/83', 93, 17, 37.8, 99, 1, 78.5, 174.8, 25.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(20, 20, 20, '125/85', 96, 18, 36.4, 96, 2, 80.0, 176.0, 25.8, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(21, 21, 21, '128/87', 99, 19, 36.5, 97, 3, 81.5, 177.2, 26.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(22, 22, 22, '131/89', 70, 20, 36.7, 98, 4, 83.0, 178.4, 26.1, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(23, 23, 23, '134/91', 73, 21, 36.9, 99, 5, 84.5, 179.6, 26.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(24, 24, 24, '137/93', 76, 16, 37.0, 96, 0, 86.0, 152.8, 36.8, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(25, 25, 25, '140/70', 79, 17, 37.1, 97, 1, 87.5, 154.0, 36.9, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(26, 26, 26, '143/72', 82, 18, 37.3, 98, 2, 51.0, 155.2, 21.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(27, 27, 27, '146/74', 85, 19, 37.4, 99, 3, 52.5, 156.4, 21.5, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW()),
(28, 28, 28, '149/76', 88, 20, 37.6, 96, 4, 54.0, 157.6, 21.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', NOW()),
(29, 29, 29, '152/78', 91, 21, 37.8, 97, 5, 55.5, 158.8, 22.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', NOW()),
(30, 30, 30, '110/80', 94, 16, 36.4, 98, 0, 57.0, 160.0, 22.3, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', NOW())
ON DUPLICATE KEY UPDATE `BloodPressure`=VALUES(`BloodPressure`);

-- ============================================================================
-- 6. NURSING TASKS (25 Records)
-- ============================================================================
INSERT INTO `nurse_tasks` (`TaskID`, `PatientID`, `NurseID`, `DoctorID`, `TaskTitle`, `Category`, `DueTime`, `Priority`, `Status`, `Remarks`, `CreatedAt`) VALUES
(1, 1, 6, 5, 'Vital Check for Patient #PAT-2026-0001', 'Vital Check', '02:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(2, 2, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0002', 'IV Fluid Replacement', '03:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(3, 3, 6, 5, 'Wound Dressing for Patient #PAT-2026-0003', 'Wound Dressing', '04:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(4, 4, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0004', 'Pre-op Prep', '05:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(5, 5, 6, 5, 'General Care for Patient #PAT-2026-0005', 'General Care', '06:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(6, 6, 6, 5, 'Medication Admin for Patient #PAT-2026-0006', 'Medication Admin', '07:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(7, 7, 6, 5, 'Vital Check for Patient #PAT-2026-0007', 'Vital Check', '08:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(8, 8, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0008', 'IV Fluid Replacement', '09:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(9, 9, 6, 5, 'Wound Dressing for Patient #PAT-2026-0009', 'Wound Dressing', '10:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(10, 10, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0010', 'Pre-op Prep', '11:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(11, 11, 6, 5, 'General Care for Patient #PAT-2026-0011', 'General Care', '12:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(12, 12, 6, 5, 'Medication Admin for Patient #PAT-2026-0012', 'Medication Admin', '01:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(13, 13, 6, 5, 'Vital Check for Patient #PAT-2026-0013', 'Vital Check', '02:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(14, 14, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0014', 'IV Fluid Replacement', '03:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(15, 15, 6, 5, 'Wound Dressing for Patient #PAT-2026-0015', 'Wound Dressing', '04:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(16, 16, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0016', 'Pre-op Prep', '05:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(17, 17, 6, 5, 'General Care for Patient #PAT-2026-0017', 'General Care', '06:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(18, 18, 6, 5, 'Medication Admin for Patient #PAT-2026-0018', 'Medication Admin', '07:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(19, 19, 6, 5, 'Vital Check for Patient #PAT-2026-0019', 'Vital Check', '08:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(20, 20, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0020', 'IV Fluid Replacement', '09:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(21, 21, 6, 5, 'Wound Dressing for Patient #PAT-2026-0021', 'Wound Dressing', '10:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(22, 22, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0022', 'Pre-op Prep', '11:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW()),
(23, 23, 6, 5, 'General Care for Patient #PAT-2026-0023', 'General Care', '12:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', NOW()),
(24, 24, 6, 5, 'Medication Admin for Patient #PAT-2026-0024', 'Medication Admin', '01:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', NOW()),
(25, 25, 6, 5, 'Vital Check for Patient #PAT-2026-0025', 'Vital Check', '02:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', NOW())
ON DUPLICATE KEY UPDATE `TaskTitle`=VALUES(`TaskTitle`);

-- ============================================================================
-- 7. ELECTRONIC PRESCRIPTIONS (25 Records)
-- ============================================================================
INSERT INTO `prescriptions` (`PrescriptionID`, `PrescriptionCode`, `PatientID`, `DoctorID`, `AppointmentID`, `MedicineName`, `Dosage`, `Frequency`, `Duration`, `Instructions`, `Quantity`, `Refills`, `Status`, `IssuedDate`, `CreatedAt`) VALUES
(1, 'RX-2026-0001', 1, 5, 1, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Active', CURDATE(), NOW()),
(2, 'RX-2026-0002', 2, 5, 2, 'Norvasc (Amlodipine Besylate)', '5mg', 'Once daily in morning', '30 days', 'Monitor morning blood pressure daily.', '30 Tablets', 0, 'Completed', CURDATE(), NOW()),
(3, 'RX-2026-0003', 3, 5, 3, 'Glucophage (Metformin HCl)', '500mg', 'Twice daily with meals', '30 days', 'Take with breakfast and dinner.', '60 Tablets', 0, 'Completed', CURDATE(), NOW()),
(4, 'RX-2026-0004', 4, 5, 4, 'Biogesic (Paracetamol)', '500mg', 'Every 4-6 hours PRN', '5 days', 'Take for pain or fever greater than 38C.', '15 Tablets', 0, 'Cancelled', CURDATE(), NOW()),
(5, 'RX-2026-0005', 5, 5, 5, 'Cozaar (Losartan Potassium)', '50mg', 'Once daily in morning', '30 days', 'Maintain blood pressure journal.', '30 Tablets', 0, 'Active', CURDATE(), NOW()),
(6, 'RX-2026-0006', 6, 5, 6, 'Betaloc (Metoprolol Tartrate)', '50mg', 'Once daily', '30 days', 'Do not abruptly discontinue.', '30 Tablets', 0, 'Active', CURDATE(), NOW()),
(7, 'RX-2026-0007', 7, 5, 7, 'Lipitor (Atorvastatin Calcium)', '40mg', 'Once daily at bedtime', '30 days', 'Avoid grapefruit juice.', '30 Tablets', 0, 'Completed', CURDATE(), NOW()),
(8, 'RX-2026-0008', 8, 5, 8, 'Ventolin (Salbutamol Sulfate)', '2mg/5ml', 'Every 8 hours PRN', '7 days', 'Take when wheezing or dyspneic.', '1 Bottle', 0, 'Completed', CURDATE(), NOW()),
(9, 'RX-2026-0009', 9, 5, 9, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Cancelled', CURDATE(), NOW()),
(10, 'RX-2026-0010', 10, 5, 10, 'Norvasc (Amlodipine Besylate)', '5mg', 'Once daily in morning', '30 days', 'Monitor morning blood pressure daily.', '30 Tablets', 0, 'Active', CURDATE(), NOW()),
(11, 'RX-2026-0011', 11, 5, 11, 'Glucophage (Metformin HCl)', '500mg', 'Twice daily with meals', '30 days', 'Take with breakfast and dinner.', '60 Tablets', 0, 'Active', CURDATE(), NOW()),
(12, 'RX-2026-0012', 12, 5, 12, 'Biogesic (Paracetamol)', '500mg', 'Every 4-6 hours PRN', '5 days', 'Take for pain or fever greater than 38C.', '15 Tablets', 0, 'Completed', CURDATE(), NOW()),
(13, 'RX-2026-0013', 13, 5, 13, 'Cozaar (Losartan Potassium)', '50mg', 'Once daily in morning', '30 days', 'Maintain blood pressure journal.', '30 Tablets', 0, 'Completed', CURDATE(), NOW()),
(14, 'RX-2026-0014', 14, 5, 14, 'Betaloc (Metoprolol Tartrate)', '50mg', 'Once daily', '30 days', 'Do not abruptly discontinue.', '30 Tablets', 0, 'Cancelled', CURDATE(), NOW()),
(15, 'RX-2026-0015', 15, 5, 15, 'Lipitor (Atorvastatin Calcium)', '40mg', 'Once daily at bedtime', '30 days', 'Avoid grapefruit juice.', '30 Tablets', 0, 'Active', CURDATE(), NOW()),
(16, 'RX-2026-0016', 16, 5, 16, 'Ventolin (Salbutamol Sulfate)', '2mg/5ml', 'Every 8 hours PRN', '7 days', 'Take when wheezing or dyspneic.', '1 Bottle', 0, 'Active', CURDATE(), NOW()),
(17, 'RX-2026-0017', 17, 5, 17, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Completed', CURDATE(), NOW()),
(18, 'RX-2026-0018', 18, 5, 18, 'Norvasc (Amlodipine Besylate)', '5mg', 'Once daily in morning', '30 days', 'Monitor morning blood pressure daily.', '30 Tablets', 0, 'Completed', CURDATE(), NOW()),
(19, 'RX-2026-0019', 19, 5, 19, 'Glucophage (Metformin HCl)', '500mg', 'Twice daily with meals', '30 days', 'Take with breakfast and dinner.', '60 Tablets', 0, 'Cancelled', CURDATE(), NOW()),
(20, 'RX-2026-0020', 20, 5, 20, 'Biogesic (Paracetamol)', '500mg', 'Every 4-6 hours PRN', '5 days', 'Take for pain or fever greater than 38C.', '15 Tablets', 0, 'Active', CURDATE(), NOW()),
(21, 'RX-2026-0021', 21, 5, 21, 'Cozaar (Losartan Potassium)', '50mg', 'Once daily in morning', '30 days', 'Maintain blood pressure journal.', '30 Tablets', 0, 'Active', CURDATE(), NOW()),
(22, 'RX-2026-0022', 22, 5, 22, 'Betaloc (Metoprolol Tartrate)', '50mg', 'Once daily', '30 days', 'Do not abruptly discontinue.', '30 Tablets', 0, 'Completed', CURDATE(), NOW()),
(23, 'RX-2026-0023', 23, 5, 23, 'Lipitor (Atorvastatin Calcium)', '40mg', 'Once daily at bedtime', '30 days', 'Avoid grapefruit juice.', '30 Tablets', 0, 'Completed', CURDATE(), NOW()),
(24, 'RX-2026-0024', 24, 5, 24, 'Ventolin (Salbutamol Sulfate)', '2mg/5ml', 'Every 8 hours PRN', '7 days', 'Take when wheezing or dyspneic.', '1 Bottle', 0, 'Cancelled', CURDATE(), NOW()),
(25, 'RX-2026-0025', 25, 5, 25, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Active', CURDATE(), NOW())
ON DUPLICATE KEY UPDATE `PrescriptionCode`=VALUES(`PrescriptionCode`);

-- ============================================================================
-- 8. TEST CATALOG & REFERENCE RANGES (20 Records)
-- ============================================================================
INSERT INTO `test_catalog` (`CatalogID`, `TestCode`, `TestName`, `Category`, `SpecimenType`, `TurnaroundTime`, `StandardPrice`, `Status`) VALUES
(1, 'LAB-CBC', 'Complete Blood Count (CBC) with Platelet', 'Hematology', 'EDTA Whole Blood', '1–2 Hours', 280.00, 'Active'),
(2, 'LAB-LIP', 'Lipid Profile Panel (Total Chol, HDL, LDL, Trig)', 'Clinical Chemistry', 'Serum (Fasting 10-12h)', '3–4 Hours', 650.00, 'Active'),
(3, 'LAB-FBS', 'Fasting Blood Sugar (FBS)', 'Clinical Chemistry', 'Fluoride Oxalate / Serum', '1–2 Hours', 180.00, 'Active'),
(4, 'LAB-URN', 'Routine Urinalysis', 'Urinalysis', 'Midstream Clean Catch Urine', '1 Hour', 150.00, 'Active'),
(5, 'LAB-KID', 'Kidney Function Test (BUN, Creatinine)', 'Clinical Chemistry', 'Serum', '2–3 Hours', 420.00, 'Active'),
(6, 'LAB-LIV', 'Liver Function Panel (SGOT, SGPT)', 'Clinical Chemistry', 'Serum', '2–3 Hours', 550.00, 'Active'),
(7, 'LAB-HBA1C', 'Glycated Hemoglobin (HbA1c)', 'Clinical Chemistry', 'EDTA Whole Blood', '2–3 Hours', 680.00, 'Active'),
(8, 'LAB-URIC', 'Serum Uric Acid', 'Clinical Chemistry', 'Serum', '2 Hours', 220.00, 'Active'),
(9, 'LAB-FEC', 'Routine Fecalysis', 'Microbiology', 'Fresh Stool Sample', '1 Hour', 120.00, 'Active'),
(10, 'LAB-ELECT', 'Serum Electrolytes (Na, K, Cl)', 'Clinical Chemistry', 'Serum', '2–3 Hours', 480.00, 'Active'),
(11, 'LAB-BLDGRP', 'Blood Typing (ABO & Rh Factor)', 'Blood Banking', 'EDTA Whole Blood', '1 Hour', 200.00, 'Active'),
(12, 'LAB-DENGUE', 'Dengue NS1 Antigen & Duo (IgG/IgM)', 'Serology', 'Serum', '1 Hour', 850.00, 'Active'),
(13, 'LAB-HBSAG', 'Hepatitis B Surface Antigen (HBsAg)', 'Serology', 'Serum', '1–2 Hours', 300.00, 'Active'),
(14, 'LAB-PTINR', 'Prothrombin Time / INR', 'Hematology', 'Sodium Citrate Whole Blood', '2 Hours', 380.00, 'Active'),
(15, 'LAB-PREG', 'Urine Pregnancy Test (hCG)', 'Urinalysis', 'First Morning Urine', '30 Mins', 150.00, 'Active'),
(16, 'LAB-TSH', 'Thyroid Stimulating Hormone (TSH)', 'Immunology', 'Serum', '4–6 Hours', 750.00, 'Active'),
(17, 'LAB-FT4', 'Free Thyroxine (FT4)', 'Immunology', 'Serum', '4–6 Hours', 700.00, 'Active'),
(18, 'LAB-GRAM', 'Gram Staining Examination', 'Microbiology', 'Swab / Exudate', '2 Hours', 200.00, 'Active'),
(19, 'LAB-AFB', 'Acid-Fast Bacilli (AFB) Sputum Smear', 'Microbiology', 'Deep Productive Sputum', '24 Hours', 250.00, 'Active'),
(20, 'LAB-COVID', 'COVID-19 Rapid Antigen Screening', 'Serology', 'Nasopharyngeal Swab', '30 Mins', 500.00, 'Active')
ON DUPLICATE KEY UPDATE `TestName`=VALUES(`TestName`), `StandardPrice`=VALUES(`StandardPrice`);

-- 9. Laboratory Requests, Samples, Results (25 records each)
INSERT INTO `laboratory_requests` (`RequestID`, `RequestCode`, `PatientID`, `DoctorID`, `AppointmentID`, `TestType`, `Priority`, `ClinicalNotes`, `Status`, `RequestedDate`, `CreatedAt`) VALUES
(1, 'LAB-REQ-2026-0001', 1, 5, 1, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', CURDATE(), NOW()),
(2, 'LAB-REQ-2026-0002', 2, 5, 2, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', CURDATE(), NOW()),
(3, 'LAB-REQ-2026-0003', 3, 5, 3, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(4, 'LAB-REQ-2026-0004', 4, 5, 4, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(5, 'LAB-REQ-2026-0005', 5, 5, 5, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', CURDATE(), NOW()),
(6, 'LAB-REQ-2026-0006', 6, 5, 6, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', CURDATE(), NOW()),
(7, 'LAB-REQ-2026-0007', 7, 5, 7, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', CURDATE(), NOW()),
(8, 'LAB-REQ-2026-0008', 8, 5, 8, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(9, 'LAB-REQ-2026-0009', 9, 5, 9, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(10, 'LAB-REQ-2026-0010', 10, 5, 10, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', CURDATE(), NOW()),
(11, 'LAB-REQ-2026-0011', 11, 5, 11, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', CURDATE(), NOW()),
(12, 'LAB-REQ-2026-0012', 12, 5, 12, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', CURDATE(), NOW()),
(13, 'LAB-REQ-2026-0013', 13, 5, 13, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(14, 'LAB-REQ-2026-0014', 14, 5, 14, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(15, 'LAB-REQ-2026-0015', 15, 5, 15, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', CURDATE(), NOW()),
(16, 'LAB-REQ-2026-0016', 16, 5, 16, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', CURDATE(), NOW()),
(17, 'LAB-REQ-2026-0017', 17, 5, 17, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', CURDATE(), NOW()),
(18, 'LAB-REQ-2026-0018', 18, 5, 18, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(19, 'LAB-REQ-2026-0019', 19, 5, 19, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(20, 'LAB-REQ-2026-0020', 20, 5, 20, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', CURDATE(), NOW()),
(21, 'LAB-REQ-2026-0021', 21, 5, 21, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', CURDATE(), NOW()),
(22, 'LAB-REQ-2026-0022', 22, 5, 22, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', CURDATE(), NOW()),
(23, 'LAB-REQ-2026-0023', 23, 5, 23, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(24, 'LAB-REQ-2026-0024', 24, 5, 24, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', CURDATE(), NOW()),
(25, 'LAB-REQ-2026-0025', 25, 5, 25, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', CURDATE(), NOW())
ON DUPLICATE KEY UPDATE `RequestCode`=VALUES(`RequestCode`);

INSERT INTO `laboratory_samples` (`SampleID`, `SampleBarcode`, `RequestID`, `PatientID`, `SpecimenType`, `CollectionDate`, `CollectedBy`, `ProcessingStatus`, `StorageLocation`, `Notes`) VALUES
(1, 'SMP-2026-0001', 1, 1, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-2', 'Sample intact and unhemolyzed.'),
(2, 'SMP-2026-0002', 2, 2, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-3', 'Sample intact and unhemolyzed.'),
(3, 'SMP-2026-0003', 3, 3, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-4', 'Sample intact and unhemolyzed.'),
(4, 'SMP-2026-0004', 4, 4, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-5', 'Sample intact and unhemolyzed.'),
(5, 'SMP-2026-0005', 5, 5, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-6', 'Sample intact and unhemolyzed.'),
(6, 'SMP-2026-0006', 6, 6, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-7', 'Sample intact and unhemolyzed.'),
(7, 'SMP-2026-0007', 7, 7, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-8', 'Sample intact and unhemolyzed.'),
(8, 'SMP-2026-0008', 8, 8, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-9', 'Sample intact and unhemolyzed.'),
(9, 'SMP-2026-0009', 9, 9, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-10', 'Sample intact and unhemolyzed.'),
(10, 'SMP-2026-0010', 10, 10, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-1', 'Sample intact and unhemolyzed.'),
(11, 'SMP-2026-0011', 11, 11, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-2', 'Sample intact and unhemolyzed.'),
(12, 'SMP-2026-0012', 12, 12, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-3', 'Sample intact and unhemolyzed.'),
(13, 'SMP-2026-0013', 13, 13, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-4', 'Sample intact and unhemolyzed.'),
(14, 'SMP-2026-0014', 14, 14, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-5', 'Sample intact and unhemolyzed.'),
(15, 'SMP-2026-0015', 15, 15, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-6', 'Sample intact and unhemolyzed.'),
(16, 'SMP-2026-0016', 16, 16, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-7', 'Sample intact and unhemolyzed.'),
(17, 'SMP-2026-0017', 17, 17, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-8', 'Sample intact and unhemolyzed.'),
(18, 'SMP-2026-0018', 18, 18, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-9', 'Sample intact and unhemolyzed.'),
(19, 'SMP-2026-0019', 19, 19, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-10', 'Sample intact and unhemolyzed.'),
(20, 'SMP-2026-0020', 20, 20, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-1', 'Sample intact and unhemolyzed.'),
(21, 'SMP-2026-0021', 21, 21, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-2', 'Sample intact and unhemolyzed.'),
(22, 'SMP-2026-0022', 22, 22, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-3', 'Sample intact and unhemolyzed.'),
(23, 'SMP-2026-0023', 23, 23, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-4', 'Sample intact and unhemolyzed.'),
(24, 'SMP-2026-0024', 24, 24, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-5', 'Sample intact and unhemolyzed.'),
(25, 'SMP-2026-0025', 25, 25, 'Whole Blood / Serum', NOW(), 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-6', 'Sample intact and unhemolyzed.')
ON DUPLICATE KEY UPDATE `SampleBarcode`=VALUES(`SampleBarcode`);

INSERT INTO `laboratory_results` (`ResultID`, `RequestID`, `PatientID`, `DoctorID`, `TestName`, `ResultValue`, `NormalRange`, `Units`, `Interpretation`, `Notes`, `AttachmentPath`, `ResultDate`, `CreatedAt`) VALUES
(1, 1, 1, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(2, 2, 2, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(3, 3, 3, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(4, 4, 4, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(5, 5, 5, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(6, 6, 6, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(7, 7, 7, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(8, 8, 8, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(9, 9, 9, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(10, 10, 10, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(11, 11, 11, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(12, 12, 12, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(13, 13, 13, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(14, 14, 14, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(15, 15, 15, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(16, 16, 16, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(17, 17, 17, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(18, 18, 18, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(19, 19, 19, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(20, 20, 20, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(21, 21, 21, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(22, 22, 22, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(23, 23, 23, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(24, 24, 24, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW()),
(25, 25, 25, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, CURDATE(), NOW())
ON DUPLICATE KEY UPDATE `TestName`=VALUES(`TestName`);

-- ============================================================================
-- 10. REFERRALS (20 Records)
-- ============================================================================
INSERT INTO `referrals` (`ReferralID`, `ReferralCode`, `PatientID`, `ReferringDoctorID`, `TargetSpecialtyID`, `TargetDoctorID`, `Reason`, `ClinicalSummary`, `Priority`, `Status`, `ResponseNotes`, `CreatedAt`) VALUES
(1, 'REF-2026-0001', 1, 5, 2, 3, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Accepted', 'Consultation scheduled with recipient clinical division.', NOW()),
(2, 'REF-2026-0002', 2, 5, 3, 4, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Completed', 'Consultation scheduled with recipient clinical division.', NOW()),
(3, 'REF-2026-0003', 3, 5, 4, 5, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Declined', 'Consultation scheduled with recipient clinical division.', NOW()),
(4, 'REF-2026-0004', 4, 5, 5, 6, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Pending', 'Consultation scheduled with recipient clinical division.', NOW()),
(5, 'REF-2026-0005', 5, 5, 6, 7, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Accepted', 'Consultation scheduled with recipient clinical division.', NOW()),
(6, 'REF-2026-0006', 6, 5, 7, 8, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Completed', 'Consultation scheduled with recipient clinical division.', NOW()),
(7, 'REF-2026-0007', 7, 5, 8, 9, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Declined', 'Consultation scheduled with recipient clinical division.', NOW()),
(8, 'REF-2026-0008', 8, 5, 9, 10, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Pending', 'Consultation scheduled with recipient clinical division.', NOW()),
(9, 'REF-2026-0009', 9, 5, 10, 1, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Accepted', 'Consultation scheduled with recipient clinical division.', NOW()),
(10, 'REF-2026-0010', 10, 5, 11, 2, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Completed', 'Consultation scheduled with recipient clinical division.', NOW()),
(11, 'REF-2026-0011', 11, 5, 12, 3, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Declined', 'Consultation scheduled with recipient clinical division.', NOW()),
(12, 'REF-2026-0012', 12, 5, 13, 4, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Pending', 'Consultation scheduled with recipient clinical division.', NOW()),
(13, 'REF-2026-0013', 13, 5, 14, 5, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Accepted', 'Consultation scheduled with recipient clinical division.', NOW()),
(14, 'REF-2026-0014', 14, 5, 15, 6, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Completed', 'Consultation scheduled with recipient clinical division.', NOW()),
(15, 'REF-2026-0015', 15, 5, 1, 7, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Declined', 'Consultation scheduled with recipient clinical division.', NOW()),
(16, 'REF-2026-0016', 16, 5, 2, 8, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Pending', 'Consultation scheduled with recipient clinical division.', NOW()),
(17, 'REF-2026-0017', 17, 5, 3, 9, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Accepted', 'Consultation scheduled with recipient clinical division.', NOW()),
(18, 'REF-2026-0018', 18, 5, 4, 10, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Completed', 'Consultation scheduled with recipient clinical division.', NOW()),
(19, 'REF-2026-0019', 19, 5, 5, 1, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Declined', 'Consultation scheduled with recipient clinical division.', NOW()),
(20, 'REF-2026-0020', 20, 5, 6, 2, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Pending', 'Consultation scheduled with recipient clinical division.', NOW())
ON DUPLICATE KEY UPDATE `ReferralCode`=VALUES(`ReferralCode`);

-- ============================================================================
-- 11. MEDICAL CERTIFICATES (20 Records)
-- ============================================================================
INSERT INTO `medical_certificates` (`CertificateID`, `CertificateCode`, `PatientID`, `DoctorID`, `CertificateType`, `Diagnosis`, `DurationStart`, `DurationEnd`, `DaysExcused`, `Remarks`, `IssueDate`, `CreatedAt`) VALUES
(1, 'MED-CERT-2026-0001', 1, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(2, 'MED-CERT-2026-0002', 2, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(3, 'MED-CERT-2026-0003', 3, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(4, 'MED-CERT-2026-0004', 4, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(5, 'MED-CERT-2026-0005', 5, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(6, 'MED-CERT-2026-0006', 6, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(7, 'MED-CERT-2026-0007', 7, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(8, 'MED-CERT-2026-0008', 8, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(9, 'MED-CERT-2026-0009', 9, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(10, 'MED-CERT-2026-0010', 10, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(11, 'MED-CERT-2026-0011', 11, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(12, 'MED-CERT-2026-0012', 12, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(13, 'MED-CERT-2026-0013', 13, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(14, 'MED-CERT-2026-0014', 14, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(15, 'MED-CERT-2026-0015', 15, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(16, 'MED-CERT-2026-0016', 16, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(17, 'MED-CERT-2026-0017', 17, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(18, 'MED-CERT-2026-0018', 18, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(19, 'MED-CERT-2026-0019', 19, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW()),
(20, 'MED-CERT-2026-0020', 20, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 3, 'Patient is physically fit to resume normal duties.', CURDATE(), NOW())
ON DUPLICATE KEY UPDATE `CertificateCode`=VALUES(`CertificateCode`);

-- ============================================================================
-- 12. ALLERGY RECORDS (20 Records)
-- ============================================================================
INSERT INTO `allergy_records` (`AllergyID`, `PatientID`, `DoctorID`, `Allergen`, `AllergyType`, `Severity`, `Reaction`, `Status`, `ConfirmedDate`, `CreatedAt`) VALUES
(1, 1, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', CURDATE(), NOW()),
(2, 2, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', CURDATE(), NOW()),
(3, 3, 5, 'Sulfa Antibiotics', 'Drug', 'Life-threatening', 'Anaphylaxis and bronchospasm', 'Active', CURDATE(), NOW()),
(4, 4, 5, 'Shrimp / Crustaceans', 'Food', 'Moderate', 'Perioral swelling and nausea', 'Active', CURDATE(), NOW()),
(5, 5, 5, 'Natural Rubber Latex', 'Latex', 'Moderate', 'Contact dermatitis and localized pruritus', 'Active', CURDATE(), NOW()),
(6, 6, 5, 'Peanuts / Tree Nuts', 'Food', 'Severe', 'Shortness of breath and generalized hives', 'Active', CURDATE(), NOW()),
(7, 7, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', CURDATE(), NOW()),
(8, 8, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', CURDATE(), NOW()),
(9, 9, 5, 'Sulfa Antibiotics', 'Drug', 'Life-threatening', 'Anaphylaxis and bronchospasm', 'Active', CURDATE(), NOW()),
(10, 10, 5, 'Shrimp / Crustaceans', 'Food', 'Moderate', 'Perioral swelling and nausea', 'Active', CURDATE(), NOW()),
(11, 11, 5, 'Natural Rubber Latex', 'Latex', 'Moderate', 'Contact dermatitis and localized pruritus', 'Active', CURDATE(), NOW()),
(12, 12, 5, 'Peanuts / Tree Nuts', 'Food', 'Severe', 'Shortness of breath and generalized hives', 'Active', CURDATE(), NOW()),
(13, 13, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', CURDATE(), NOW()),
(14, 14, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', CURDATE(), NOW()),
(15, 15, 5, 'Sulfa Antibiotics', 'Drug', 'Life-threatening', 'Anaphylaxis and bronchospasm', 'Active', CURDATE(), NOW()),
(16, 16, 5, 'Shrimp / Crustaceans', 'Food', 'Moderate', 'Perioral swelling and nausea', 'Active', CURDATE(), NOW()),
(17, 17, 5, 'Natural Rubber Latex', 'Latex', 'Moderate', 'Contact dermatitis and localized pruritus', 'Active', CURDATE(), NOW()),
(18, 18, 5, 'Peanuts / Tree Nuts', 'Food', 'Severe', 'Shortness of breath and generalized hives', 'Active', CURDATE(), NOW()),
(19, 19, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', CURDATE(), NOW()),
(20, 20, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', CURDATE(), NOW())
ON DUPLICATE KEY UPDATE `Allergen`=VALUES(`Allergen`);

-- ============================================================================
-- 13. PHARMACY INVENTORY & DISPENSING (25 Records)
-- ============================================================================
INSERT INTO `pharmacy_inventory` (`InventoryID`, `ItemCode`, `GenericName`, `BrandName`, `DosageForm`, `Strength`, `Category`, `UnitCost`, `SellingPrice`, `CurrentStock`, `ReorderLevel`, `BatchNumber`, `ExpiryDate`, `Status`) VALUES
(1, 'MED-AMOX-500', 'Amoxicillin Trihydrate', 'Amoxil', 'Capsule', '500mg', 'Antibiotic', 4.50, 7.50, 450, 100, 'B-2026-081', DATE_ADD(CURDATE(), INTERVAL 540 DAY), 'In Stock'),
(2, 'MED-METO-50', 'Metoprolol Tartrate', 'Betaloc', 'Tablet', '50mg', 'Antihypertensive', 5.20, 9.00, 280, 50, 'B-2026-042', DATE_ADD(CURDATE(), INTERVAL 620 DAY), 'In Stock'),
(3, 'MED-AMLO-5', 'Amlodipine Besylate', 'Norvasc', 'Tablet', '5mg', 'Antihypertensive', 3.80, 6.50, 320, 60, 'B-2026-019', DATE_ADD(CURDATE(), INTERVAL 480 DAY), 'In Stock'),
(4, 'MED-ATOR-40', 'Atorvastatin Calcium', 'Lipitor', 'Tablet', '40mg', 'Cardiovascular / Statin', 12.00, 18.50, 190, 40, 'B-2026-092', DATE_ADD(CURDATE(), INTERVAL 400 DAY), 'In Stock'),
(5, 'MED-LOSA-50', 'Losartan Potassium', 'Cozaar', 'Tablet', '50mg', 'Antihypertensive', 6.00, 10.00, 310, 50, 'B-2026-033', DATE_ADD(CURDATE(), INTERVAL 500 DAY), 'In Stock'),
(6, 'MED-METF-500', 'Metformin Hydrochloride', 'Glucophage', 'Tablet', '500mg', 'Antidiabetic', 2.50, 4.50, 500, 100, 'B-2026-055', DATE_ADD(CURDATE(), INTERVAL 700 DAY), 'In Stock'),
(7, 'MED-PARA-500', 'Paracetamol', 'Biogesic', 'Tablet', '500mg', 'Analgesic / Antipyretic', 1.80, 3.50, 40, 50, 'B-2026-012', DATE_ADD(CURDATE(), INTERVAL 360 DAY), 'Low Stock'),
(8, 'MED-OMEP-20', 'Omeprazole', 'Losec', 'Capsule', '20mg', 'Gastrointestinal', 6.50, 11.00, 220, 50, 'B-2026-015', DATE_ADD(CURDATE(), INTERVAL 450 DAY), 'In Stock'),
(9, 'MED-CETA-10', 'Cetirizine Hydrochloride', 'Virlix', 'Tablet', '10mg', 'Antihistamine', 4.00, 7.00, 180, 40, 'B-2026-022', DATE_ADD(CURDATE(), INTERVAL 520 DAY), 'In Stock'),
(10, 'MED-IBUP-400', 'Ibuprofen', 'Advil', 'Softgel', '400mg', 'NSAID / Analgesic', 5.00, 8.50, 260, 50, 'B-2026-034', DATE_ADD(CURDATE(), INTERVAL 410 DAY), 'In Stock'),
(11, 'MED-SALB-2', 'Salbutamol Sulfate', 'Ventolin', 'Syrup', '2mg/5ml', 'Respiratory / Bronchodilator', 45.00, 68.00, 75, 20, 'B-2026-077', DATE_ADD(CURDATE(), INTERVAL 380 DAY), 'In Stock'),
(12, 'MED-CIPR-500', 'Ciprofloxacin HCl', 'Ciprobay', 'Tablet', '500mg', 'Antibiotic / Fluoroquinolone', 8.50, 14.00, 140, 30, 'B-2026-061', DATE_ADD(CURDATE(), INTERVAL 600 DAY), 'In Stock'),
(13, 'MED-AZIT-500', 'Azithromycin Dihydrate', 'Zithromax', 'Tablet', '500mg', 'Macrolide Antibiotic', 28.00, 45.00, 90, 25, 'B-2026-088', DATE_ADD(CURDATE(), INTERVAL 490 DAY), 'In Stock'),
(14, 'MED-CO-AMOX', 'Co-Amoxiclav', 'Augmentin', 'Tablet', '625mg', 'Antibiotic', 22.00, 36.00, 110, 30, 'B-2026-104', DATE_ADD(CURDATE(), INTERVAL 510 DAY), 'In Stock'),
(15, 'MED-TRAN-500', 'Tranexamic Acid', 'Hemostan', 'Capsule', '500mg', 'Hemostatic Agent', 14.00, 22.00, 85, 20, 'B-2026-044', DATE_ADD(CURDATE(), INTERVAL 430 DAY), 'In Stock'),
(16, 'MED-MEFE-500', 'Mefenamic Acid', 'Ponstan', 'Capsule', '500mg', 'NSAID / Analgesic', 4.20, 7.50, 340, 60, 'B-2026-059', DATE_ADD(CURDATE(), INTERVAL 570 DAY), 'In Stock'),
(17, 'MED-HYDR-25', 'Hydrochlorothiazide', 'HCTZ', 'Tablet', '25mg', 'Diuretic', 3.00, 5.50, 210, 40, 'B-2026-018', DATE_ADD(CURDATE(), INTERVAL 650 DAY), 'In Stock'),
(18, 'MED-ASPI-80', 'Aspirin (Low Dose)', 'Aspilets', 'Enteric Tablet', '80mg', 'Antiplatelet', 2.00, 3.80, 400, 80, 'B-2026-009', DATE_ADD(CURDATE(), INTERVAL 720 DAY), 'In Stock'),
(19, 'MED-DICY-10', 'Dicycloverine HCl', 'Relestal', 'Tablet', '10mg', 'Antispasmodic', 3.50, 6.00, 160, 30, 'B-2026-027', DATE_ADD(CURDATE(), INTERVAL 460 DAY), 'In Stock'),
(20, 'MED-LORA-10', 'Loratadine', 'Claritin', 'Tablet', '10mg', 'Antihistamine', 7.50, 12.00, 195, 40, 'B-2026-067', DATE_ADD(CURDATE(), INTERVAL 540 DAY), 'In Stock'),
(21, 'MED-ALUM-MAG', 'Aluminum Hydroxide / Magnesium', 'Kremil-S', 'Chewable Tablet', 'Standard', 'Antacid', 2.80, 5.00, 380, 70, 'B-2026-031', DATE_ADD(CURDATE(), INTERVAL 480 DAY), 'In Stock'),
(22, 'MED-DOLO-NEU', 'Vitamin B-Complex + Paracetamol', 'Dolo-Neurobion', 'Tablet', 'Standard', 'Analgesic / Neurotropic', 11.00, 18.00, 150, 35, 'B-2026-051', DATE_ADD(CURDATE(), INTERVAL 500 DAY), 'In Stock'),
(23, 'MED-CLOP-75', 'Clopidogrel Bisulfate', 'Plavix', 'Tablet', '75mg', 'Antiplatelet', 15.00, 24.00, 120, 30, 'B-2026-083', DATE_ADD(CURDATE(), INTERVAL 420 DAY), 'In Stock'),
(24, 'MED-INS-HUM', 'Human Regular Insulin', 'Humulin R', 'Vial 10ml', '100 IU/ml', 'Antidiabetic Insulin', 350.00, 480.00, 35, 10, 'B-2026-099', DATE_ADD(CURDATE(), INTERVAL 300 DAY), 'In Stock'),
(25, 'MED-MULTIVIT', 'Multivitamins + Iron', 'Iberet', 'Tablet', 'Standard', 'Nutritional Supplement', 6.00, 10.00, 500, 100, 'B-2026-011', DATE_ADD(CURDATE(), INTERVAL 680 DAY), 'In Stock')
ON DUPLICATE KEY UPDATE `GenericName`=VALUES(`GenericName`), `CurrentStock`=VALUES(`CurrentStock`);

-- Dispensing Records (25 records)
INSERT INTO `dispensing_records` (`DispenseID`, `DispenseCode`, `PrescriptionID`, `PatientID`, `DispensedBy`, `DispenserName`, `QuantityDispensed`, `DosageInstructions`, `BatchNumber`, `DispenseDate`, `Status`, `Notes`) VALUES
(1, 'DISP-2026-0001', 1, 1, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-011', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(2, 'DISP-2026-0002', 2, 2, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-012', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(3, 'DISP-2026-0003', 3, 3, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-013', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(4, 'DISP-2026-0004', 4, 4, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-014', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(5, 'DISP-2026-0005', 5, 5, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-015', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(6, 'DISP-2026-0006', 6, 6, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-016', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(7, 'DISP-2026-0007', 7, 7, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-017', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(8, 'DISP-2026-0008', 8, 8, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-018', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(9, 'DISP-2026-0009', 9, 9, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-019', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(10, 'DISP-2026-0010', 10, 10, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-020', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(11, 'DISP-2026-0011', 11, 11, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-021', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(12, 'DISP-2026-0012', 12, 12, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-022', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(13, 'DISP-2026-0013', 13, 13, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-023', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(14, 'DISP-2026-0014', 14, 14, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-024', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(15, 'DISP-2026-0015', 15, 15, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-025', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(16, 'DISP-2026-0016', 16, 16, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-026', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(17, 'DISP-2026-0017', 17, 17, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-027', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(18, 'DISP-2026-0018', 18, 18, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-028', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(19, 'DISP-2026-0019', 19, 19, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-029', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(20, 'DISP-2026-0020', 20, 20, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-030', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(21, 'DISP-2026-0021', 21, 21, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-031', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(22, 'DISP-2026-0022', 22, 22, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-032', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(23, 'DISP-2026-0023', 23, 23, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-033', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(24, 'DISP-2026-0024', 24, 24, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-034', NOW(), 'Dispensed', 'Verified against original electronic prescription.'),
(25, 'DISP-2026-0025', 25, 25, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-035', NOW(), 'Dispensed', 'Verified against original electronic prescription.')
ON DUPLICATE KEY UPDATE `DispenseCode`=VALUES(`DispenseCode`);

-- ============================================================================
-- 14. BILLING CHARGES, INVOICES & PAYMENTS (30 Records)
-- ============================================================================
INSERT INTO `billing_charges` (`ChargeID`, `PatientID`, `AppointmentID`, `FeeID`, `ChargeCategory`, `ItemDescription`, `Quantity`, `UnitPrice`, `SubTotal`, `DiscountAmount`, `NetAmount`, `BillingStatus`, `CreatedBy`, `CreatedAt`) VALUES
(1, 1, 1, 2, 'Laboratory', 'Laboratory Service Charge for Patient #1', 1, 300, 300, 0.00, 300, 'Invoiced', 'Maria Castillo (Billing)', NOW()),
(2, 2, 2, 3, 'Pharmacy', 'Pharmacy Service Charge for Patient #2', 1, 350, 350, 0.00, 350, 'Paid', 'Maria Castillo (Billing)', NOW()),
(3, 3, 3, 4, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #3', 1, 400, 400, 0.00, 400, 'Paid', 'Maria Castillo (Billing)', NOW()),
(4, 4, 4, 5, 'Radiology', 'Radiology Service Charge for Patient #4', 1, 450, 450, 0.00, 450, 'Paid', 'Maria Castillo (Billing)', NOW()),
(5, 5, 5, 6, 'Consultation', 'Consultation Service Charge for Patient #5', 1, 500, 500, 0.00, 500, 'Unbilled', 'Maria Castillo (Billing)', NOW()),
(6, 6, 6, 7, 'Laboratory', 'Laboratory Service Charge for Patient #6', 1, 550, 550, 0.00, 550, 'Invoiced', 'Maria Castillo (Billing)', NOW()),
(7, 7, 7, 8, 'Pharmacy', 'Pharmacy Service Charge for Patient #7', 1, 600, 600, 0.00, 600, 'Paid', 'Maria Castillo (Billing)', NOW()),
(8, 8, 8, 9, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #8', 1, 650, 650, 0.00, 650, 'Paid', 'Maria Castillo (Billing)', NOW()),
(9, 9, 9, 10, 'Radiology', 'Radiology Service Charge for Patient #9', 1, 700, 700, 0.00, 700, 'Paid', 'Maria Castillo (Billing)', NOW()),
(10, 10, 10, 11, 'Consultation', 'Consultation Service Charge for Patient #10', 1, 750, 750, 0.00, 750, 'Unbilled', 'Maria Castillo (Billing)', NOW()),
(11, 11, 11, 1, 'Laboratory', 'Laboratory Service Charge for Patient #11', 1, 800, 800, 0.00, 800, 'Invoiced', 'Maria Castillo (Billing)', NOW()),
(12, 12, 12, 2, 'Pharmacy', 'Pharmacy Service Charge for Patient #12', 1, 250, 250, 0.00, 250, 'Paid', 'Maria Castillo (Billing)', NOW()),
(13, 13, 13, 3, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #13', 1, 300, 300, 0.00, 300, 'Paid', 'Maria Castillo (Billing)', NOW()),
(14, 14, 14, 4, 'Radiology', 'Radiology Service Charge for Patient #14', 1, 350, 350, 0.00, 350, 'Paid', 'Maria Castillo (Billing)', NOW()),
(15, 15, 15, 5, 'Consultation', 'Consultation Service Charge for Patient #15', 1, 400, 400, 0.00, 400, 'Unbilled', 'Maria Castillo (Billing)', NOW()),
(16, 16, 16, 6, 'Laboratory', 'Laboratory Service Charge for Patient #16', 1, 450, 450, 0.00, 450, 'Invoiced', 'Maria Castillo (Billing)', NOW()),
(17, 17, 17, 7, 'Pharmacy', 'Pharmacy Service Charge for Patient #17', 1, 500, 500, 0.00, 500, 'Paid', 'Maria Castillo (Billing)', NOW()),
(18, 18, 18, 8, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #18', 1, 550, 550, 0.00, 550, 'Paid', 'Maria Castillo (Billing)', NOW()),
(19, 19, 19, 9, 'Radiology', 'Radiology Service Charge for Patient #19', 1, 600, 600, 0.00, 600, 'Paid', 'Maria Castillo (Billing)', NOW()),
(20, 20, 20, 10, 'Consultation', 'Consultation Service Charge for Patient #20', 1, 650, 650, 0.00, 650, 'Unbilled', 'Maria Castillo (Billing)', NOW()),
(21, 21, 21, 11, 'Laboratory', 'Laboratory Service Charge for Patient #21', 1, 700, 700, 0.00, 700, 'Invoiced', 'Maria Castillo (Billing)', NOW()),
(22, 22, 22, 1, 'Pharmacy', 'Pharmacy Service Charge for Patient #22', 1, 750, 750, 0.00, 750, 'Paid', 'Maria Castillo (Billing)', NOW()),
(23, 23, 23, 2, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #23', 1, 800, 800, 0.00, 800, 'Paid', 'Maria Castillo (Billing)', NOW()),
(24, 24, 24, 3, 'Radiology', 'Radiology Service Charge for Patient #24', 1, 250, 250, 0.00, 250, 'Paid', 'Maria Castillo (Billing)', NOW()),
(25, 25, 25, 4, 'Consultation', 'Consultation Service Charge for Patient #25', 1, 300, 300, 0.00, 300, 'Unbilled', 'Maria Castillo (Billing)', NOW()),
(26, 26, 26, 5, 'Laboratory', 'Laboratory Service Charge for Patient #26', 1, 350, 350, 0.00, 350, 'Invoiced', 'Maria Castillo (Billing)', NOW()),
(27, 27, 27, 6, 'Pharmacy', 'Pharmacy Service Charge for Patient #27', 1, 400, 400, 0.00, 400, 'Paid', 'Maria Castillo (Billing)', NOW()),
(28, 28, 28, 7, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #28', 1, 450, 450, 0.00, 450, 'Paid', 'Maria Castillo (Billing)', NOW()),
(29, 29, 29, 8, 'Radiology', 'Radiology Service Charge for Patient #29', 1, 500, 500, 0.00, 500, 'Paid', 'Maria Castillo (Billing)', NOW()),
(30, 30, 30, 9, 'Consultation', 'Consultation Service Charge for Patient #30', 1, 550, 550, 0.00, 550, 'Unbilled', 'Maria Castillo (Billing)', NOW())
ON DUPLICATE KEY UPDATE `ItemDescription`=VALUES(`ItemDescription`);

INSERT INTO `billing_invoices` (`InvoiceID`, `InvoiceNumber`, `PatientID`, `GrossAmount`, `DiscountType`, `DiscountAmount`, `PhilHealthDeduction`, `TotalPayable`, `AmountPaid`, `BalanceDue`, `PaymentStatus`, `BilledBy`, `CreatedAt`, `DueDate`) VALUES
(1, 'INV-2026-0001', 1, 300, 'None', 0.00, 0.00, 300, 300, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(2, 'INV-2026-0002', 2, 350, 'None', 0.00, 0.00, 350, 350, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(3, 'INV-2026-0003', 3, 400, 'None', 0.00, 0.00, 400, 0, 400, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(4, 'INV-2026-0004', 4, 450, 'None', 0.00, 0.00, 450, 450, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(5, 'INV-2026-0005', 5, 500, 'None', 0.00, 0.00, 500, 500, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(6, 'INV-2026-0006', 6, 550, 'None', 0.00, 0.00, 550, 0, 550, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(7, 'INV-2026-0007', 7, 600, 'None', 0.00, 0.00, 600, 600, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(8, 'INV-2026-0008', 8, 650, 'None', 0.00, 0.00, 650, 650, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(9, 'INV-2026-0009', 9, 700, 'None', 0.00, 0.00, 700, 0, 700, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(10, 'INV-2026-0010', 10, 750, 'None', 0.00, 0.00, 750, 750, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(11, 'INV-2026-0011', 11, 800, 'None', 0.00, 0.00, 800, 800, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(12, 'INV-2026-0012', 12, 250, 'None', 0.00, 0.00, 250, 0, 250, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(13, 'INV-2026-0013', 13, 300, 'None', 0.00, 0.00, 300, 300, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(14, 'INV-2026-0014', 14, 350, 'None', 0.00, 0.00, 350, 350, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(15, 'INV-2026-0015', 15, 400, 'None', 0.00, 0.00, 400, 0, 400, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(16, 'INV-2026-0016', 16, 450, 'None', 0.00, 0.00, 450, 450, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(17, 'INV-2026-0017', 17, 500, 'None', 0.00, 0.00, 500, 500, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(18, 'INV-2026-0018', 18, 550, 'None', 0.00, 0.00, 550, 0, 550, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(19, 'INV-2026-0019', 19, 600, 'None', 0.00, 0.00, 600, 600, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(20, 'INV-2026-0020', 20, 650, 'None', 0.00, 0.00, 650, 650, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(21, 'INV-2026-0021', 21, 700, 'None', 0.00, 0.00, 700, 0, 700, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(22, 'INV-2026-0022', 22, 750, 'None', 0.00, 0.00, 750, 750, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(23, 'INV-2026-0023', 23, 800, 'None', 0.00, 0.00, 800, 800, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(24, 'INV-2026-0024', 24, 250, 'None', 0.00, 0.00, 250, 0, 250, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(25, 'INV-2026-0025', 25, 300, 'None', 0.00, 0.00, 300, 300, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(26, 'INV-2026-0026', 26, 350, 'None', 0.00, 0.00, 350, 350, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(27, 'INV-2026-0027', 27, 400, 'None', 0.00, 0.00, 400, 0, 400, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(28, 'INV-2026-0028', 28, 450, 'None', 0.00, 0.00, 450, 450, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(29, 'INV-2026-0029', 29, 500, 'None', 0.00, 0.00, 500, 500, 0, 'Paid In Full', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(30, 'INV-2026-0030', 30, 550, 'None', 0.00, 0.00, 550, 0, 550, 'Unpaid', 'Maria Castillo (Cashier)', NOW(), DATE_ADD(CURDATE(), INTERVAL 14 DAY))
ON DUPLICATE KEY UPDATE `InvoiceNumber`=VALUES(`InvoiceNumber`);

INSERT INTO `billing_payments` (`PaymentID`, `ReceiptNumber`, `InvoiceID`, `PatientID`, `AmountPaid`, `PaymentMethod`, `ReferenceNumber`, `AmountInWords`, `CashierName`, `PaymentDate`) VALUES
(1, 'OR-2026-0001', 1, 1, 300, 'Cash', 'CASH-REC-0001', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(2, 'OR-2026-0002', 2, 2, 350, 'Cash', 'CASH-REC-0002', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(4, 'OR-2026-0004', 4, 4, 450, 'Cash', 'CASH-REC-0004', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(5, 'OR-2026-0005', 5, 5, 500, 'Cash', 'CASH-REC-0005', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(7, 'OR-2026-0007', 7, 7, 600, 'Cash', 'CASH-REC-0007', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(8, 'OR-2026-0008', 8, 8, 650, 'Cash', 'CASH-REC-0008', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(10, 'OR-2026-0010', 10, 10, 750, 'Cash', 'CASH-REC-0010', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(11, 'OR-2026-0011', 11, 11, 800, 'Cash', 'CASH-REC-0011', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(13, 'OR-2026-0013', 13, 13, 300, 'Cash', 'CASH-REC-0013', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(14, 'OR-2026-0014', 14, 14, 350, 'Cash', 'CASH-REC-0014', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(16, 'OR-2026-0016', 16, 16, 450, 'Cash', 'CASH-REC-0016', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(17, 'OR-2026-0017', 17, 17, 500, 'Cash', 'CASH-REC-0017', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(19, 'OR-2026-0019', 19, 19, 600, 'Cash', 'CASH-REC-0019', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(20, 'OR-2026-0020', 20, 20, 650, 'Cash', 'CASH-REC-0020', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(22, 'OR-2026-0022', 22, 22, 750, 'Cash', 'CASH-REC-0022', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(23, 'OR-2026-0023', 23, 23, 800, 'Cash', 'CASH-REC-0023', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(25, 'OR-2026-0025', 25, 25, 300, 'Cash', 'CASH-REC-0025', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(26, 'OR-2026-0026', 26, 26, 350, 'Cash', 'CASH-REC-0026', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(28, 'OR-2026-0028', 28, 28, 450, 'Cash', 'CASH-REC-0028', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW()),
(29, 'OR-2026-0029', 29, 29, 500, 'Cash', 'CASH-REC-0029', 'Paid full balance in Philippine Pesos', 'Maria Castillo', NOW())
ON DUPLICATE KEY UPDATE `ReceiptNumber`=VALUES(`ReceiptNumber`);

-- ============================================================================
-- 15. SYSTEM AUDIT & ERROR LOGS (30 Records)
-- ============================================================================
INSERT INTO `system_audit_logs` (`LogID`, `UserID`, `UserName`, `UserRole`, `Action`, `Module`, `RecordID`, `Details`, `IPAddress`, `UserAgent`, `CreatedAt`) VALUES
(1, 1, 'Staff Member', 'Staff', 'UPDATE_VITALS', 'Consultations', 'REC-1', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(2, 1, 'System Administrator', 'Admin', 'ORDER_LAB_TEST', 'Laboratory', 'REC-2', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(3, 1, 'Staff Member', 'Staff', 'DISPENSE_RX', 'Pharmacy', 'REC-3', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(4, 1, 'System Administrator', 'Admin', 'PROCESS_PAYMENT', 'Billing', 'REC-4', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(5, 1, 'Staff Member', 'Staff', 'USER_LOGIN', 'User Admin', 'REC-5', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(6, 1, 'System Administrator', 'Admin', 'BACKUP_DB', 'System Config', 'REC-6', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(7, 1, 'Staff Member', 'Staff', 'CREATE_PATIENT', 'Patient Records', 'REC-7', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(8, 1, 'System Administrator', 'Admin', 'UPDATE_VITALS', 'Consultations', 'REC-8', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(9, 1, 'Staff Member', 'Staff', 'ORDER_LAB_TEST', 'Laboratory', 'REC-9', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(10, 1, 'System Administrator', 'Admin', 'DISPENSE_RX', 'Pharmacy', 'REC-10', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(11, 1, 'Staff Member', 'Staff', 'PROCESS_PAYMENT', 'Billing', 'REC-11', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(12, 1, 'System Administrator', 'Admin', 'USER_LOGIN', 'User Admin', 'REC-12', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(13, 1, 'Staff Member', 'Staff', 'BACKUP_DB', 'System Config', 'REC-13', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(14, 1, 'System Administrator', 'Admin', 'CREATE_PATIENT', 'Patient Records', 'REC-14', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(15, 1, 'Staff Member', 'Staff', 'UPDATE_VITALS', 'Consultations', 'REC-15', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(16, 1, 'System Administrator', 'Admin', 'ORDER_LAB_TEST', 'Laboratory', 'REC-16', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(17, 1, 'Staff Member', 'Staff', 'DISPENSE_RX', 'Pharmacy', 'REC-17', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(18, 1, 'System Administrator', 'Admin', 'PROCESS_PAYMENT', 'Billing', 'REC-18', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(19, 1, 'Staff Member', 'Staff', 'USER_LOGIN', 'User Admin', 'REC-19', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(20, 1, 'System Administrator', 'Admin', 'BACKUP_DB', 'System Config', 'REC-20', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(21, 1, 'Staff Member', 'Staff', 'CREATE_PATIENT', 'Patient Records', 'REC-21', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(22, 1, 'System Administrator', 'Admin', 'UPDATE_VITALS', 'Consultations', 'REC-22', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(23, 1, 'Staff Member', 'Staff', 'ORDER_LAB_TEST', 'Laboratory', 'REC-23', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(24, 1, 'System Administrator', 'Admin', 'DISPENSE_RX', 'Pharmacy', 'REC-24', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(25, 1, 'Staff Member', 'Staff', 'PROCESS_PAYMENT', 'Billing', 'REC-25', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(26, 1, 'System Administrator', 'Admin', 'USER_LOGIN', 'User Admin', 'REC-26', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(27, 1, 'Staff Member', 'Staff', 'BACKUP_DB', 'System Config', 'REC-27', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(28, 1, 'System Administrator', 'Admin', 'CREATE_PATIENT', 'Patient Records', 'REC-28', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(29, 1, 'Staff Member', 'Staff', 'UPDATE_VITALS', 'Consultations', 'REC-29', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(30, 1, 'System Administrator', 'Admin', 'ORDER_LAB_TEST', 'Laboratory', 'REC-30', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW())
ON DUPLICATE KEY UPDATE `Action`=VALUES(`Action`);

-- ============================================================================
-- 16. DATA BACKUP LOGS (10 Records)
-- ============================================================================
INSERT INTO `backup_logs` (`BackupID`, `FileName`, `FileSize`, `BackupType`, `CreatedBy`, `Status`, `Notes`, `CreatedAt`) VALUES
(1, 'backup_tmhmis_2026_09_01.sql.gz', '4.5 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(2, 'backup_tmhmis_2026_09_02.sql.gz', '4.8 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(3, 'backup_tmhmis_2026_09_03.sql.gz', '5.1 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(4, 'backup_tmhmis_2026_09_04.sql.gz', '5.4 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(5, 'backup_tmhmis_2026_09_05.sql.gz', '5.7 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(6, 'backup_tmhmis_2026_09_06.sql.gz', '6.0 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(7, 'backup_tmhmis_2026_09_07.sql.gz', '6.3 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(8, 'backup_tmhmis_2026_09_08.sql.gz', '6.6 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(9, 'backup_tmhmis_2026_09_09.sql.gz', '6.9 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW()),
(10, 'backup_tmhmis_2026_09_10.sql.gz', '7.2 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', NOW())
ON DUPLICATE KEY UPDATE `FileName`=VALUES(`FileName`);


-- Clinical and operational additions populated successfully.

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS = 1;
