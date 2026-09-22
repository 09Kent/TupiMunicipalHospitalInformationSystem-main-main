-- ============================================================================
-- TUPI MUNICIPAL HOSPITAL INFORMATION MANAGEMENT SYSTEM (TMHIS)
-- SUPABASE (POSTGRESQL) DEPLOYMENT SCRIPT
-- Target: Supabase SQL Editor / PostgreSQL 15+
-- ============================================================================

SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

-- SECTION 1: CREATE TABLES (57 tables)

CREATE TABLE IF NOT EXISTS "roles" (
    "RoleID" SERIAL,
    "RoleCode" VARCHAR(50) NOT NULL,
    "RoleName" VARCHAR(100) NOT NULL,
    "Description" TEXT NULL,
    "IsSystemRole" BOOLEAN DEFAULT FALSE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("RoleID")
);

CREATE TABLE IF NOT EXISTS "permissions" (
    "PermissionID" SERIAL,
    "PermissionCode" VARCHAR(100) NOT NULL,
    "PermissionName" VARCHAR(150) NOT NULL,
    "Module" VARCHAR(100) NOT NULL,
    "Description" TEXT NULL,
    PRIMARY KEY ("PermissionID")
);

CREATE TABLE IF NOT EXISTS "role_permissions" (
    "RoleID" INTEGER NOT NULL,
    "PermissionID" INTEGER NOT NULL,
    PRIMARY KEY ("RoleID", "PermissionID")
);

CREATE TABLE IF NOT EXISTS "users" (
    "UserID" SERIAL,
    "FirstName" VARCHAR(100) NOT NULL,
    "LastName" VARCHAR(100) NOT NULL,
    "Username" VARCHAR(60) NOT NULL,
    "PasswordHash" VARCHAR(255) NOT NULL,
    "Role" VARCHAR(50) DEFAULT 'Registrator' NOT NULL,
    "Email" VARCHAR(120) NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "remember_token" VARCHAR(100) NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("UserID")
);

CREATE TABLE IF NOT EXISTS "departments" (
    "DepartmentID" SERIAL,
    "DepartmentCode" VARCHAR(50) NOT NULL,
    "DepartmentName" VARCHAR(150) NOT NULL,
    "DepartmentType" VARCHAR(100) DEFAULT 'Clinical' NOT NULL,
    "HeadOfDepartment" VARCHAR(150) NULL,
    "Location" VARCHAR(150) DEFAULT 'Main Building' NOT NULL,
    "ContactExtension" VARCHAR(20) NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("DepartmentID")
);

CREATE TABLE IF NOT EXISTS "specialties" (
    "SpecialtyID" SERIAL,
    "SpecialtyName" VARCHAR(100) NOT NULL,
    "SpecialtyCode" VARCHAR(50) NOT NULL,
    "Description" TEXT NULL,
    "BodySystemID" INTEGER NULL,
    "Icon" VARCHAR(50) DEFAULT 'stethoscope' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    PRIMARY KEY ("SpecialtyID")
);

CREATE TABLE IF NOT EXISTS "body_systems" (
    "BodySystemID" SERIAL,
    "SystemName" VARCHAR(100) NOT NULL,
    "SystemCode" VARCHAR(50) NOT NULL,
    "Icon" VARCHAR(50) DEFAULT 'activity' NOT NULL,
    "Emoji" VARCHAR(10) DEFAULT '­ƒ®║' NOT NULL,
    "Description" TEXT NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    PRIMARY KEY ("BodySystemID")
);

CREATE TABLE IF NOT EXISTS "body_locations" (
    "BodyLocationID" SERIAL,
    "BodySystemID" INTEGER NULL,
    "LocationName" VARCHAR(100) NOT NULL,
    "LocationCode" VARCHAR(50) NOT NULL,
    "FrontBack" VARCHAR(100) DEFAULT 'Front' NOT NULL,
    "SubRegion" VARCHAR(150) NOT NULL,
    "Description" TEXT NULL,
    PRIMARY KEY ("BodyLocationID")
);

CREATE TABLE IF NOT EXISTS "symptoms" (
    "SymptomID" SERIAL,
    "SymptomName" VARCHAR(100) NOT NULL,
    "Description" VARCHAR(255) NULL,
    "Icon" VARCHAR(50) DEFAULT 'activity' NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    PRIMARY KEY ("SymptomID")
);

CREATE TABLE IF NOT EXISTS "doctors" (
    "DoctorID" SERIAL,
    "UserID" INTEGER NULL,
    "FirstName" VARCHAR(100) NOT NULL,
    "LastName" VARCHAR(100) NOT NULL,
    "SpecialtyID" INTEGER NULL,
    "Title" VARCHAR(50) DEFAULT 'MD' NOT NULL,
    "Specialty" VARCHAR(120) NOT NULL,
    "LicenseNumber" VARCHAR(60) NOT NULL,
    "ContactNumber" VARCHAR(30) NOT NULL,
    "Email" VARCHAR(120) NOT NULL,
    "ExperienceYears" INTEGER DEFAULT 5 NOT NULL,
    "Clinic" VARCHAR(150) NOT NULL,
    "ClinicRoom" VARCHAR(100) DEFAULT 'Suite 101' NOT NULL,
    "ProfileImage" VARCHAR(255) NULL,
    "Rating" NUMERIC(3,2) DEFAULT 4.90 NOT NULL,
    "ReviewsCount" INTEGER DEFAULT 50 NOT NULL,
    "ConsultationFee" VARCHAR(50) DEFAULT '$80.00' NOT NULL,
    "Bio" TEXT NULL,
    "Education" TEXT NULL,
    "Languages" VARCHAR(255) DEFAULT 'English' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Available' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("DoctorID")
);

CREATE TABLE IF NOT EXISTS "doctor_specialties" (
    "DoctorSpecialtyID" SERIAL,
    "DoctorID" INTEGER NOT NULL,
    "SpecialtyID" INTEGER NOT NULL,
    "IsPrimary" BOOLEAN DEFAULT TRUE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("DoctorSpecialtyID")
);

CREATE TABLE IF NOT EXISTS "doctor_body_systems" (
    "DoctorSystemID" SERIAL,
    "DoctorID" INTEGER NOT NULL,
    "BodySystemID" INTEGER NOT NULL,
    PRIMARY KEY ("DoctorSystemID")
);

CREATE TABLE IF NOT EXISTS "patients" (
    "PatientID" SERIAL,
    "PatientCode" VARCHAR(30) NOT NULL,
    "FirstName" VARCHAR(100) NOT NULL,
    "MiddleName" VARCHAR(100) NULL,
    "LastName" VARCHAR(100) NOT NULL,
    "DateOfBirth" DATE NOT NULL,
    "Age" INTEGER NOT NULL,
    "Gender" VARCHAR(100) NOT NULL,
    "CivilStatus" VARCHAR(100) DEFAULT 'Single' NOT NULL,
    "ContactNumber" VARCHAR(30) NOT NULL,
    "Email" VARCHAR(120) NOT NULL,
    "Address" TEXT NOT NULL,
    "BloodType" VARCHAR(10) DEFAULT 'Unknown' NOT NULL,
    "PatientCategory" VARCHAR(100) DEFAULT 'Outpatient' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "RegisteredBy" INTEGER NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("PatientID")
);

CREATE TABLE IF NOT EXISTS "emergency_contacts" (
    "EmergencyContactID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "ContactName" VARCHAR(150) NOT NULL,
    "Relationship" VARCHAR(60) NOT NULL,
    "ContactNumber" VARCHAR(30) NOT NULL,
    PRIMARY KEY ("EmergencyContactID")
);

CREATE TABLE IF NOT EXISTS "medical_histories" (
    "MedicalHistoryID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "Allergies" TEXT NULL,
    "ExistingConditions" TEXT NULL,
    "CurrentMedications" TEXT NULL,
    "PreviousHospitalization" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("MedicalHistoryID")
);

CREATE TABLE IF NOT EXISTS "patient_registration_history" (
    "HistoryID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "UserID" INTEGER NULL,
    "Action" VARCHAR(100) NOT NULL,
    "Description" TEXT NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("HistoryID")
);

CREATE TABLE IF NOT EXISTS "appointments" (
    "AppointmentID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "AppointmentDate" DATE NOT NULL,
    "AppointmentTime" VARCHAR(30) NOT NULL,
    "ConsultationType" VARCHAR(100) DEFAULT 'In-Person Consultation' NOT NULL,
    "Reason" TEXT NOT NULL,
    "Priority" VARCHAR(100) DEFAULT 'Normal' NOT NULL,
    "Notes" TEXT NULL,
    "Status" VARCHAR(100) DEFAULT 'Scheduled' NOT NULL,
    "CreatedBy" INTEGER NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("AppointmentID")
);

CREATE TABLE IF NOT EXISTS "patient_queue" (
    "QueueID" SERIAL,
    "AppointmentID" INTEGER NULL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "QueueNumber" VARCHAR(10) NOT NULL,
    "QueueDate" DATE NOT NULL,
    "QueueStatus" VARCHAR(100) DEFAULT 'Waiting' NOT NULL,
    "Priority" VARCHAR(100) DEFAULT 'Normal' NOT NULL,
    "CalledAt" TIMESTAMPTZ NULL,
    "ConsultationStartedAt" TIMESTAMPTZ NULL,
    "CompletedAt" TIMESTAMPTZ NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("QueueID")
);

CREATE TABLE IF NOT EXISTS "patient_vitals" (
    "VitalID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "BloodPressure" VARCHAR(30) NOT NULL,
    "HeartRate" INTEGER NOT NULL,
    "RespiratoryRate" INTEGER DEFAULT 18 NOT NULL,
    "Temperature" NUMERIC(4,1) NOT NULL,
    "OxygenSaturation" INTEGER DEFAULT 98 NOT NULL,
    "PainScale" INTEGER DEFAULT 0 NOT NULL,
    "WeightKg" NUMERIC(5,2) NULL,
    "HeightCm" NUMERIC(5,2) NULL,
    "BMI" NUMERIC(4,1) NULL,
    "ClinicalNotes" TEXT NULL,
    "RecordedBy" INTEGER NULL,
    "RecordedByName" VARCHAR(100) DEFAULT 'Nurse Elena Gomez, RN' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("VitalID")
);

CREATE TABLE IF NOT EXISTS "complaints" (
    "ComplaintID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "ComplaintDescription" TEXT NOT NULL,
    "Severity" INTEGER DEFAULT 3 NOT NULL,
    "Duration" VARCHAR(50) DEFAULT '1ÔÇô3 days ago' NOT NULL,
    "AggravatingFactors" TEXT NULL,
    "RelievingFactors" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("ComplaintID")
);

CREATE TABLE IF NOT EXISTS "complaint_conditions" (
    "ComplaintConditionID" SERIAL,
    "ComplaintID" INTEGER NOT NULL,
    "ConditionID" INTEGER NOT NULL,
    PRIMARY KEY ("ComplaintConditionID")
);

CREATE TABLE IF NOT EXISTS "possible_conditions" (
    "ConditionID" SERIAL,
    "ConditionName" VARCHAR(150) NOT NULL,
    "BodySystemID" INTEGER NOT NULL,
    "Description" TEXT NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    PRIMARY KEY ("ConditionID")
);

CREATE TABLE IF NOT EXISTS "patient_symptoms" (
    "PatientSymptomID" SERIAL,
    "ComplaintID" INTEGER NOT NULL,
    "SymptomID" INTEGER NOT NULL,
    PRIMARY KEY ("PatientSymptomID")
);

CREATE TABLE IF NOT EXISTS "complaint_analysis" (
    "AnalysisID" SERIAL,
    "ComplaintID" INTEGER NOT NULL,
    "BodySystemID" INTEGER NOT NULL,
    "BodyLocationID" INTEGER NULL,
    "RelevanceLevel" INTEGER DEFAULT 90 NOT NULL,
    "ConfidenceLevel" VARCHAR(30) DEFAULT 'High (95%)' NOT NULL,
    "ClinicalNotes" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("AnalysisID")
);

CREATE TABLE IF NOT EXISTS "diagnoses" (
    "DiagnosisID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "DiagnosisName" VARCHAR(255) NOT NULL,
    "ICD10Code" VARCHAR(30) NULL,
    "Type" VARCHAR(100) DEFAULT 'Primary' NOT NULL,
    "Severity" VARCHAR(100) DEFAULT 'Moderate' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "Notes" TEXT NULL,
    "DiagnosedDate" DATE DEFAULT CURRENT_DATE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("DiagnosisID")
);

CREATE TABLE IF NOT EXISTS "consultation_notes" (
    "NoteID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "Subjective" TEXT NULL,
    "Objective" TEXT NULL,
    "Assessment" TEXT NULL,
    "Plan" TEXT NULL,
    "ClinicalNotes" TEXT NOT NULL,
    "VitalSigns" JSONB NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("NoteID")
);

CREATE TABLE IF NOT EXISTS "treatment_plans" (
    "PlanID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "DiagnosisID" INTEGER NULL,
    "Goal" VARCHAR(255) NOT NULL,
    "LifestyleRecommendations" TEXT NULL,
    "MedicationPlan" TEXT NULL,
    "FollowUpSchedule" VARCHAR(100) NULL,
    "FollowUpDate" DATE NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "Notes" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("PlanID")
);

CREATE TABLE IF NOT EXISTS "medical_certificates" (
    "CertificateID" SERIAL,
    "CertificateCode" VARCHAR(50) NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "CertificateType" VARCHAR(100) DEFAULT 'Fit to Work' NOT NULL,
    "Diagnosis" TEXT NOT NULL,
    "Recommendation" TEXT NULL,
    "DurationStart" DATE NOT NULL,
    "DurationEnd" DATE NOT NULL,
    "DaysExcused" INTEGER DEFAULT 1 NOT NULL,
    "Remarks" TEXT NULL,
    "IssueDate" DATE DEFAULT CURRENT_DATE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("CertificateID")
);

CREATE TABLE IF NOT EXISTS "referrals" (
    "ReferralID" SERIAL,
    "ReferralCode" VARCHAR(50) NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "ReferringDoctorID" INTEGER NOT NULL,
    "TargetSpecialtyID" INTEGER NOT NULL,
    "TargetDoctorID" INTEGER NULL,
    "Reason" TEXT NOT NULL,
    "ClinicalSummary" TEXT NULL,
    "Priority" VARCHAR(100) DEFAULT 'Routine' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Pending' NOT NULL,
    "ResponseNotes" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("ReferralID")
);

CREATE TABLE IF NOT EXISTS "test_catalog" (
    "CatalogID" SERIAL,
    "TestCode" VARCHAR(50) NOT NULL,
    "TestName" VARCHAR(200) NOT NULL,
    "Category" VARCHAR(100) DEFAULT 'Clinical Chemistry' NOT NULL,
    "SpecimenType" VARCHAR(100) DEFAULT 'Whole Blood / Serum' NOT NULL,
    "TurnaroundTime" VARCHAR(50) DEFAULT '2ÔÇô4 Hours' NOT NULL,
    "StandardPrice" NUMERIC(10,2) DEFAULT 350.00 NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    PRIMARY KEY ("CatalogID")
);

CREATE TABLE IF NOT EXISTS "reference_ranges" (
    "RangeID" SERIAL,
    "CatalogID" INTEGER NOT NULL,
    "ParameterName" VARCHAR(100) NOT NULL,
    "Unit" VARCHAR(50) NOT NULL,
    "MaleRange" VARCHAR(100) NOT NULL,
    "FemaleRange" VARCHAR(100) NOT NULL,
    "CriticalLow" VARCHAR(50) NULL,
    "CriticalHigh" VARCHAR(50) NULL,
    PRIMARY KEY ("RangeID")
);

CREATE TABLE IF NOT EXISTS "laboratory_requests" (
    "RequestID" SERIAL,
    "RequestCode" VARCHAR(50) NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "TestType" VARCHAR(150) NOT NULL,
    "Priority" VARCHAR(100) DEFAULT 'Routine' NOT NULL,
    "ClinicalNotes" TEXT NULL,
    "Status" VARCHAR(100) DEFAULT 'Pending' NOT NULL,
    "RequestedDate" DATE DEFAULT CURRENT_DATE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("RequestID")
);

CREATE TABLE IF NOT EXISTS "laboratory_samples" (
    "SampleID" SERIAL,
    "SampleBarcode" VARCHAR(50) NOT NULL,
    "RequestID" INTEGER NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "SpecimenType" VARCHAR(100) NOT NULL,
    "CollectionDate" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "CollectedBy" VARCHAR(100) DEFAULT 'Clarisse Mae Santos, RMT' NOT NULL,
    "ProcessingStatus" VARCHAR(100) DEFAULT 'Collected' NOT NULL,
    "StorageLocation" VARCHAR(100) DEFAULT 'Rack A-04 (Centrifuge Bay)' NOT NULL,
    "Notes" TEXT NULL,
    PRIMARY KEY ("SampleID")
);

CREATE TABLE IF NOT EXISTS "laboratory_results" (
    "ResultID" SERIAL,
    "RequestID" INTEGER NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "TestName" VARCHAR(150) NOT NULL,
    "ResultValue" VARCHAR(255) NOT NULL,
    "NormalRange" VARCHAR(150) NOT NULL,
    "Units" VARCHAR(50) NULL,
    "Interpretation" VARCHAR(100) DEFAULT 'Normal' NOT NULL,
    "Notes" TEXT NULL,
    "AttachmentPath" VARCHAR(255) NULL,
    "ResultDate" DATE DEFAULT CURRENT_DATE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("ResultID")
);

CREATE TABLE IF NOT EXISTS "pharmacy_inventory" (
    "InventoryID" SERIAL,
    "ItemCode" VARCHAR(50) NOT NULL,
    "GenericName" VARCHAR(200) NOT NULL,
    "BrandName" VARCHAR(150) NULL,
    "DosageForm" VARCHAR(80) DEFAULT 'Tablet' NOT NULL,
    "Strength" VARCHAR(80) NOT NULL,
    "Category" VARCHAR(100) DEFAULT 'Essential Medicine' NOT NULL,
    "UnitCost" NUMERIC(10,2) DEFAULT 5.00 NOT NULL,
    "SellingPrice" NUMERIC(10,2) DEFAULT 8.50 NOT NULL,
    "CurrentStock" INTEGER DEFAULT 100 NOT NULL,
    "ReorderLevel" INTEGER DEFAULT 30 NOT NULL,
    "BatchNumber" VARCHAR(80) DEFAULT 'B-2026-001' NOT NULL,
    "ExpiryDate" DATE NOT NULL,
    "Supplier" VARCHAR(150) DEFAULT 'DOH Central Depot / Mercury Drug Wholesale' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'In Stock' NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("InventoryID")
);

CREATE TABLE IF NOT EXISTS "pharmacy_stock_movements" (
    "MovementID" SERIAL,
    "InventoryID" INTEGER NOT NULL,
    "MovementType" VARCHAR(100) NOT NULL,
    "Quantity" INTEGER NOT NULL,
    "ReferenceCode" VARCHAR(100) NULL,
    "RecordedBy" VARCHAR(100) DEFAULT 'Pharmacist Staff' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("MovementID")
);

CREATE TABLE IF NOT EXISTS "prescriptions" (
    "PrescriptionID" SERIAL,
    "PrescriptionCode" VARCHAR(50) NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "MedicineName" VARCHAR(200) NOT NULL,
    "Dosage" VARCHAR(100) NOT NULL,
    "Frequency" VARCHAR(100) NOT NULL,
    "Duration" VARCHAR(100) NOT NULL,
    "Instructions" TEXT NOT NULL,
    "Quantity" VARCHAR(50) DEFAULT '1 Box' NULL,
    "Refills" INTEGER DEFAULT 0 NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "IssuedDate" DATE DEFAULT CURRENT_DATE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("PrescriptionID")
);

CREATE TABLE IF NOT EXISTS "dispensing_records" (
    "DispenseID" SERIAL,
    "DispenseCode" VARCHAR(50) NOT NULL,
    "PrescriptionID" INTEGER NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "DispensedBy" INTEGER NULL,
    "DispenserName" VARCHAR(100) DEFAULT 'Kareen Joy Ramos, RPh' NOT NULL,
    "QuantityDispensed" VARCHAR(50) NOT NULL,
    "DosageInstructions" TEXT NOT NULL,
    "BatchNumber" VARCHAR(80) NULL,
    "DispenseDate" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Dispensed' NOT NULL,
    "Notes" TEXT NULL,
    PRIMARY KEY ("DispenseID")
);

CREATE TABLE IF NOT EXISTS "service_fees" (
    "FeeID" SERIAL,
    "ServiceCode" VARCHAR(50) NOT NULL,
    "ServiceName" VARCHAR(200) NOT NULL,
    "Category" VARCHAR(100) DEFAULT 'Consultation' NOT NULL,
    "DepartmentID" INTEGER NULL,
    "StandardRate" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "PhilHealthCoveredRate" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "DiscountEligible" BOOLEAN DEFAULT TRUE NOT NULL,
    "Description" TEXT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("FeeID")
);

CREATE TABLE IF NOT EXISTS "billing_charges" (
    "ChargeID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "AppointmentID" INTEGER NULL,
    "FeeID" INTEGER NULL,
    "ChargeCategory" VARCHAR(100) DEFAULT 'Consultation' NOT NULL,
    "ItemDescription" VARCHAR(255) NOT NULL,
    "Quantity" INTEGER DEFAULT 1 NOT NULL,
    "UnitPrice" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "SubTotal" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "DiscountAmount" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "NetAmount" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "BillingStatus" VARCHAR(100) DEFAULT 'Unbilled' NOT NULL,
    "CreatedBy" VARCHAR(100) DEFAULT 'Billing Staff' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("ChargeID")
);

CREATE TABLE IF NOT EXISTS "billing_invoices" (
    "InvoiceID" SERIAL,
    "InvoiceNumber" VARCHAR(50) NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "GrossAmount" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "DiscountType" VARCHAR(100) DEFAULT 'None' NOT NULL,
    "DiscountAmount" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "PhilHealthDeduction" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "TotalPayable" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "AmountPaid" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "BalanceDue" NUMERIC(10,2) DEFAULT 0.00 NOT NULL,
    "PaymentStatus" VARCHAR(100) DEFAULT 'Unpaid' NOT NULL,
    "BilledBy" VARCHAR(100) DEFAULT 'Maria Santos (Cashier)' NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "DueDate" DATE NOT NULL,
    PRIMARY KEY ("InvoiceID")
);

CREATE TABLE IF NOT EXISTS "billing_payments" (
    "PaymentID" SERIAL,
    "ReceiptNumber" VARCHAR(50) NOT NULL,
    "InvoiceID" INTEGER NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "AmountPaid" NUMERIC(10,2) NOT NULL,
    "PaymentMethod" VARCHAR(100) DEFAULT 'Cash' NOT NULL,
    "ReferenceNumber" VARCHAR(100) NULL,
    "AmountInWords" TEXT NOT NULL,
    "CashierName" VARCHAR(100) DEFAULT 'Maria Santos' NOT NULL,
    "PaymentDate" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("PaymentID")
);

CREATE TABLE IF NOT EXISTS "nurse_tasks" (
    "TaskID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "NurseID" INTEGER NULL,
    "DoctorID" INTEGER NULL,
    "TaskTitle" VARCHAR(200) NOT NULL,
    "Category" VARCHAR(100) DEFAULT 'Vital Check' NOT NULL,
    "DueTime" VARCHAR(30) NOT NULL,
    "Priority" VARCHAR(100) DEFAULT 'Normal' NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Pending' NOT NULL,
    "Remarks" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "CompletedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("TaskID")
);

CREATE TABLE IF NOT EXISTS "allergy_records" (
    "AllergyID" SERIAL,
    "PatientID" INTEGER NOT NULL,
    "DoctorID" INTEGER NOT NULL,
    "Allergen" VARCHAR(150) NOT NULL,
    "AllergyType" VARCHAR(100) DEFAULT 'Drug' NOT NULL,
    "Severity" VARCHAR(100) DEFAULT 'Moderate' NOT NULL,
    "Reaction" VARCHAR(255) NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Active' NOT NULL,
    "ConfirmedDate" DATE DEFAULT CURRENT_DATE NOT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("AllergyID")
);

CREATE TABLE IF NOT EXISTS "record_release_requests" (
    "RequestID" SERIAL,
    "RequestNumber" VARCHAR(50) NOT NULL,
    "PatientID" INTEGER NOT NULL,
    "RequestType" VARCHAR(100) NOT NULL,
    "RequestorName" VARCHAR(150) NOT NULL,
    "RelationshipToPatient" VARCHAR(80) DEFAULT 'Self' NOT NULL,
    "PurposeOfRequest" VARCHAR(255) NOT NULL,
    "Status" VARCHAR(100) DEFAULT 'Pending Review' NOT NULL,
    "ProcessedBy" VARCHAR(100) NULL,
    "RequestedDate" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "ReleasedDate" TIMESTAMPTZ NULL,
    PRIMARY KEY ("RequestID")
);

CREATE TABLE IF NOT EXISTS "hospital_info" (
    "HospitalInfoID" SERIAL,
    "HospitalName" VARCHAR(200) DEFAULT 'Tupi Municipal Hospital' NOT NULL,
    "HospitalCode" VARCHAR(50) DEFAULT 'TMH-REG-12' NOT NULL,
    "TaxID" VARCHAR(50) DEFAULT '004-982-114-000' NOT NULL,
    "Address" TEXT NOT NULL,
    "ContactNumber" VARCHAR(50) DEFAULT '+63 (083) 228-0001' NOT NULL,
    "EmergencyHotline" VARCHAR(50) DEFAULT '911 / (083) 228-0002' NOT NULL,
    "Email" VARCHAR(120) DEFAULT 'admin@tupihospital.gov.ph' NOT NULL,
    "DOHAccreditation" VARCHAR(100) DEFAULT 'DOH-ACCRED-L1-2026-084' NOT NULL,
    "PhilHealthAccreditation" VARCHAR(100) DEFAULT 'PH-HOSP-1209384' NOT NULL,
    "BedCapacity" INTEGER DEFAULT 50 NOT NULL,
    "MedicalDirector" VARCHAR(150) DEFAULT 'Dr. Maria Santos, MD, MHA' NOT NULL,
    "LogoPath" VARCHAR(255) DEFAULT 'assets/logo.png' NULL,
    "UpdatedAt" TIMESTAMPTZ NULL,
    PRIMARY KEY ("HospitalInfoID")
);

CREATE TABLE IF NOT EXISTS "system_audit_logs" (
    "LogID" SERIAL,
    "UserID" INTEGER NULL,
    "UserName" VARCHAR(100) NOT NULL,
    "UserRole" VARCHAR(50) NOT NULL,
    "Action" VARCHAR(100) NOT NULL,
    "Module" VARCHAR(80) NOT NULL,
    "RecordID" VARCHAR(50) NULL,
    "Details" TEXT NULL,
    "IPAddress" VARCHAR(50) NULL,
    "UserAgent" VARCHAR(255) NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("LogID")
);

CREATE TABLE IF NOT EXISTS "system_error_logs" (
    "ErrorID" SERIAL,
    "ErrorCode" VARCHAR(50) NULL,
    "ErrorMessage" TEXT NOT NULL,
    "Module" VARCHAR(80) NULL,
    "StackTrace" TEXT NULL,
    "RequestUri" VARCHAR(255) NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("ErrorID")
);

CREATE TABLE IF NOT EXISTS "backup_logs" (
    "BackupID" SERIAL,
    "FileName" VARCHAR(255) NOT NULL,
    "FileSize" VARCHAR(50) NOT NULL,
    "BackupType" VARCHAR(100) DEFAULT 'Full Database' NOT NULL,
    "CreatedBy" INTEGER NULL,
    "Status" VARCHAR(100) DEFAULT 'Completed' NOT NULL,
    "Notes" TEXT NULL,
    "CreatedAt" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("BackupID")
);

CREATE TABLE IF NOT EXISTS "migrations" (
    "id" SERIAL,
    "migration" VARCHAR(255) NOT NULL,
    "batch" INTEGER NOT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "password_reset_tokens" (
    "email" VARCHAR(255) NOT NULL,
    "token" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMPTZ NULL,
    PRIMARY KEY ("email")
);

CREATE TABLE IF NOT EXISTS "sessions" (
    "id" VARCHAR(255) NOT NULL,
    "user_id" BIGINT NULL,
    "ip_address" VARCHAR(45) NULL,
    "user_agent" TEXT NULL,
    "payload" TEXT NOT NULL,
    "last_activity" INTEGER NOT NULL,
    PRIMARY KEY ("id")
);

CREATE INDEX IF NOT EXISTS "sessions_user_id_index" ON "sessions" ("user_id");
CREATE INDEX IF NOT EXISTS "sessions_last_activity_index" ON "sessions" ("last_activity");

CREATE TABLE IF NOT EXISTS "cache" (
    "key" VARCHAR(255) NOT NULL,
    "value" TEXT NOT NULL,
    "expiration" BIGINT NOT NULL,
    PRIMARY KEY ("key")
);

CREATE TABLE IF NOT EXISTS "cache_locks" (
    "key" VARCHAR(255) NOT NULL,
    "owner" VARCHAR(255) NOT NULL,
    "expiration" BIGINT NOT NULL,
    PRIMARY KEY ("key")
);

CREATE TABLE IF NOT EXISTS "jobs" (
    "id" BIGSERIAL,
    "queue" VARCHAR(255) NOT NULL,
    "payload" TEXT NOT NULL,
    "attempts" SMALLINT NOT NULL,
    "reserved_at" INTEGER NULL,
    "available_at" INTEGER NOT NULL,
    "created_at" INTEGER NOT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "job_batches" (
    "id" VARCHAR(255) NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "total_jobs" INTEGER NOT NULL,
    "pending_jobs" INTEGER NOT NULL,
    "failed_jobs" INTEGER NOT NULL,
    "failed_job_ids" TEXT NOT NULL,
    "options" TEXT NULL,
    "cancelled_at" INTEGER NULL,
    "created_at" INTEGER NOT NULL,
    "finished_at" INTEGER NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "failed_jobs" (
    "id" BIGSERIAL,
    "uuid" VARCHAR(255) NOT NULL,
    "connection" VARCHAR(255) NOT NULL,
    "queue" VARCHAR(255) NOT NULL,
    "payload" TEXT NOT NULL,
    "exception" TEXT NOT NULL,
    "failed_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY ("id")
);

-- SECTION 2: SEED DATA

-- Data for: "roles" (9 records)
INSERT INTO "roles" ("RoleID", "RoleCode", "RoleName", "Description", "IsSystemRole", "CreatedAt") VALUES
    (1, 'ADMIN', 'System Administrator', 'Full access to system configuration, user accounts, and monitoring', TRUE, '2026-09-21 22:50:03'),
    (2, 'CHIEF', 'Hospital Chief / Medical Director', 'Operational oversight, institutional performance tracking, and reports', TRUE, '2026-09-21 22:50:03'),
    (3, 'RECORDS', 'Medical Records Officer', 'Patient records administration, validation, and historical summaries', TRUE, '2026-09-21 22:50:03'),
    (4, 'REGISTER', 'Admitting / Registration Staff', 'Patient registration, appointment scheduling, and queue coordination', TRUE, '2026-09-21 22:50:03'),
    (5, 'DOCTOR', 'Attending Physician', 'Consultations, SOAP notes, diagnosis, e-prescriptions, and lab requests', TRUE, '2026-09-21 22:50:03'),
    (6, 'NURSE', 'Nurse on Duty', 'Vital signs logging, patient bed monitoring, and task execution', TRUE, '2026-09-21 22:50:03'),
    (7, 'MEDTECH', 'Medical Technologist', 'Lab requests processing, specimen tracking, and test results entry', TRUE, '2026-09-21 22:50:03'),
    (8, 'PHARMACIST', 'Pharmacist / Pharmacy Aide', 'Prescription validation, drug dispensing, and inventory management', TRUE, '2026-09-21 22:50:03'),
    (9, 'BILLING', 'Billing / Cashier Staff', 'Charge computations, discount processing, payments, and receipts', TRUE, '2026-09-21 22:50:03')
ON CONFLICT DO NOTHING;

-- Data for: "users" (29 records)
INSERT INTO "users" ("UserID", "FirstName", "LastName", "Username", "PasswordHash", "Role", "Email", "Status", "remember_token", "CreatedAt", "UpdatedAt") VALUES
    (1, 'System', 'Administrator', 'admin', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Admin', 'admin@tupihospital.gov.ph', 'Active', 'ZrQ7Q3i9xCNYL57LWi7IcEe7wAFF4FQcNRwIE5j3qtQYITnVsXH4DzkfuDaf', '2026-09-21 22:50:03', '2026-09-22 02:11:16'),
    (2, 'Maria', 'Santos', 'director', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Chief', 'director@tupihospital.gov.ph', 'Active', 'FTt3dmyHiZBIfCnvOUSjGuiKWJgrvDBVcV3b5acRSQCYPlTmpqRdWFDJy1yr', '2026-09-21 22:50:03', '2026-09-22 02:11:22'),
    (3, 'Mark Anthony', 'Valenzuela', 'records', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Records', 'records@tupihospital.gov.ph', 'Active', 'etDH0g1teYgmC0ktIGShC648Sh0fZUTUmEJ3tv9Rs62GK98pYuskgLM0EMRk', '2026-09-21 22:50:03', '2026-09-22 02:11:21'),
    (4, 'Elena', 'Gomez', 'nurse', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Nurse', 'nurse@tupihospital.gov.ph', 'Active', 'CdpqCDyZZ6X8EXXRHDp8sdE9kbWLMWcb98iy6LEaKRLmYyA2pDKiRbKLH9eC', '2026-09-21 22:50:03', '2026-09-22 02:11:15'),
    (5, 'Clarisse Mae', 'Santos', 'medtech', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'MedTech', 'medtech@tupihospital.gov.ph', 'Active', 'uKLON2jglMzWptmKcPyoblR0D5lzRMmdMBkE1jnuksEizeApsOwutLqe0DuO', '2026-09-21 22:50:03', '2026-09-22 02:11:19'),
    (6, 'Kareen Joy', 'Ramos', 'pharmacist', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Pharmacist', 'pharmacist@tupihospital.gov.ph', 'Active', 'Hx6QmjEochxo8w4EqcL7LTPMnUVLwSsDcOD0jAiK1JlXoy3iO979E9FLcWDm', '2026-09-21 22:50:03', '2026-09-22 02:11:19'),
    (7, 'Maria', 'Santos', 'cashier', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Billing', 'cashier@tupihospital.gov.ph', 'Active', 'qtwpZOlXoGsHRNQVQskYAbp64wa6lKh1SW1hyJP42ofvMu7om4UAALhhZ82n', '2026-09-21 22:50:03', '2026-09-22 02:11:20'),
    (10, 'Daniel', 'Lewis', 'dr.lewis', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'dr.lewis@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (11, 'Elena', 'Villanueva', 'dr.villanueva', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'dr.villanueva@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (12, 'James', 'Wilson', 'dr.wilson', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'dr.wilson@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (13, 'Sophia', 'Miller', 'dr.miller', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'dr.miller@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (14, 'Carlos', 'Reyes', 'staff.reyes', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Nurse', 'carlos.reyes@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (15, 'Ana', 'Bautista', 'staff.bautista', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Nurse', 'ana.bautista@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (16, 'Michael', 'Chang', 'registrator2', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Register', 'michael.chang@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (17, 'Joy', 'Mendoza', 'medtech2', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'MedTech', 'joy.mendoza@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (18, 'Grace', 'DelaCruz', 'pharmacist2', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Pharmacist', 'grace.delacruz@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (19, 'Arthur', 'Morales', 'cashier2', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Billing', 'arthur.morales@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (20, 'Teresa', 'Aquino', 'records2', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Records', 'teresa.aquino@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:04', '2026-09-21 23:40:50'),
    (101, 'Daniel', 'Lewis', 'cardio', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'cardio@hospital.com', 'Active', 'h7Jh15g27OwuR2eFE4AgUeSB93UilpD5LBG59fcOYnH44k3Qbcp2nQf2MWhh', '2026-09-21 22:50:03', '2026-09-22 01:47:54'),
    (102, 'Elena', 'Villanueva', 'neuro', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'neuro@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (103, 'Sophia', 'Miller', 'pedia', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'pedia@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (104, 'James', 'Wilson', 'surgeon', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'surgeon@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (105, 'Maria', 'Santos', 'gastro', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'gastro@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (106, 'Marcus', 'Tan', 'pulmo', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'pulmo@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (107, 'Gabriel', 'Navarro', 'ortho', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'ortho@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (108, 'Kenneth', 'Garcia', 'gp', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'gp@hospital.com', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (109, 'Hospital', 'Registrator', 'registrator_main', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Registrator', 'registrator_main@tupihospital.gov.ph', 'Active', NULL, '2026-09-21 22:50:03', '2026-09-21 23:40:50'),
    (110, 'Test', 'User', 'test_user', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Doctor', 'test@example.com', 'Active', 'IM4w2AHrqa', '2026-09-21 15:21:57', '2026-09-21 23:40:50'),
    (111, 'Hospital', 'Registrator', 'registrator', '$2y$10$74xVzBu1ZstMPvd.FFA4quj6k1CmlRkm1HEQofwB2OBOTRCXTL7c.', 'Registrator', 'registrator@tupihospital.gov.ph', 'Active', 'hCd99q2xigQSGFHVGGTZ0KbZoF0kTHmGEHOzuncpOO8oFYidFIcBcyr4CgVu', '2026-09-21 23:40:50', '2026-09-22 02:11:18')
ON CONFLICT DO NOTHING;

-- Data for: "departments" (11 records)
INSERT INTO "departments" ("DepartmentID", "DepartmentCode", "DepartmentName", "DepartmentType", "HeadOfDepartment", "Location", "ContactExtension", "Status", "CreatedAt", "UpdatedAt") VALUES
    (1, 'DEP-EMR', 'Emergency Department', 'Clinical', 'Dr. James Wilson, MD', 'Ground Floor, ER Wing', 101, 'Active', '2026-09-21 22:50:03', NULL),
    (2, 'DEP-OPD', 'Outpatient Department', 'Clinical', 'Dr. Kenneth Garcia, MD', 'Ground Floor, East Wing', 102, 'Active', '2026-09-21 22:50:03', NULL),
    (3, 'DEP-INT', 'Internal Medicine', 'Clinical', 'Dr. Daniel Lewis, MD', '2nd Floor, Medical Wing', 201, 'Active', '2026-09-21 22:50:03', NULL),
    (4, 'DEP-PED', 'Pediatrics Department', 'Clinical', 'Dr. Sophia Miller, MD', '2nd Floor, Pediatric Wing', 202, 'Active', '2026-09-21 22:50:03', NULL),
    (5, 'DEP-SUR', 'General Surgery', 'Clinical', 'Dr. James Wilson, MD', '3rd Floor, Surgical Suite', 301, 'Active', '2026-09-21 22:50:03', NULL),
    (6, 'DEP-OBG', 'Obstetrics & Gynecology', 'Clinical', 'Dr. Elena Villanueva, MD', '2nd Floor, Maternal Wing', 203, 'Active', '2026-09-21 22:50:03', NULL),
    (7, 'DEP-LAB', 'Clinical Laboratory', 'Diagnostic', 'Clarisse Mae Santos, RMT', 'Ground Floor, Diagnostic Lab', 105, 'Active', '2026-09-21 22:50:03', NULL),
    (8, 'DEP-PHA', 'Hospital Pharmacy', 'Support', 'Kareen Joy Ramos, RPh', 'Ground Floor, Main Lobby', 104, 'Active', '2026-09-21 22:50:03', NULL),
    (9, 'DEP-BIL', 'Billing & Cashier Division', 'Administrative', 'Maria Santos', 'Ground Floor, Cashier Booth', 106, 'Active', '2026-09-21 22:50:03', NULL),
    (10, 'DEP-REC', 'Medical Records Department (HIRM)', 'Administrative', 'Mark Anthony Valenzuela, RMT', '1st Floor, Records Archive', 108, 'Active', '2026-09-21 22:50:03', NULL),
    (12, 'DEP-ADM', 'Hospital Administration & IT', 'Administrative', 'System Administrator', '4th Floor, Executive Suite', 401, 'Active', '2026-09-21 22:50:03', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "specialties" (15 records)
INSERT INTO "specialties" ("SpecialtyID", "SpecialtyName", "SpecialtyCode", "Description", "BodySystemID", "Icon", "Status") VALUES
    (1, 'General Practitioner', 'gp', 'Primary healthcare and general medical complaints', 9, 'stethoscope', 'Active'),
    (2, 'Cardiologist', 'cardio', 'Heart, blood pressure, and cardiovascular system', 2, 'heart-pulse', 'Active'),
    (3, 'Neurologist', 'neuro', 'Brain, spinal cord, nerves, and neurological conditions', 3, 'brain', 'Active'),
    (4, 'Pulmonologist', 'pulmo', 'Lungs, airways, and respiratory conditions', 4, 'wind', 'Active'),
    (5, 'Gastroenterologist', 'gastro', 'Digestive tract, stomach, intestines, liver, gallbladder', 1, 'activity', 'Active'),
    (6, 'Orthopedic', 'ortho', 'Bones, joints, muscles, and musculoskeletal trauma', 5, 'bone', 'Active'),
    (7, 'Pediatrician', 'pedia', 'Comprehensive child healthcare and pediatric development', 9, 'baby', 'Active'),
    (8, 'General Surgeon', 'surgeon', 'Surgical consultations, wound management, operative care', 9, 'scissors', 'Active'),
    (9, 'Dermatologist', 'derma', 'Skin conditions, cutaneous infections, dermal health', 6, 'shield', 'Active'),
    (10, 'ENT', 'ent', 'Ear, nose, throat, head and neck conditions', 3, 'headphones', 'Active'),
    (11, 'Urologist', 'uro', 'Urinary tract, bladder, and male reproductive health', 7, 'droplet', 'Active'),
    (12, 'OB-GYN', 'obgyn', 'Obstetrics, gynecology, and female reproductive health', 9, 'users', 'Active'),
    (13, 'Psychiatrist', 'psych', 'Mental health, behavioral wellness, and cognitive support', 3, 'smile', 'Active'),
    (14, 'Ophthalmologist', 'opht', 'Vision care, ocular diagnostics, and eye conditions', 3, 'eye', 'Active'),
    (15, 'Nephrologist', 'nephro', 'Kidney function, renal disease, and electrolyte balance', 7, 'droplets', 'Active')
ON CONFLICT DO NOTHING;

-- Data for: "body_systems" (9 records)
INSERT INTO "body_systems" ("BodySystemID", "SystemName", "SystemCode", "Icon", "Emoji", "Description", "Status") VALUES
    (1, 'Digestive System', 'digestive', 'activity', '🩺', 'Involves the esophagus, stomach, intestines, liver, pancreas, and gallbladder.', 'Active'),
    (2, 'Cardiovascular System', 'cardiovascular', 'heart-pulse', '🫀', 'Involves the heart, blood vessels, circulation, and blood pressure regulation.', 'Active'),
    (3, 'Nervous System', 'nervous', 'zap', '🧠', 'Involves the brain, spinal cord, nerves, sensation, and neuromuscular coordination.', 'Active'),
    (4, 'Respiratory System', 'respiratory', 'wind', '🫁', 'Involves airways, lungs, trachea, bronchi, and respiratory gas exchange.', 'Active'),
    (5, 'Musculoskeletal System', 'musculoskeletal', 'bone', '🦴', 'Involves bones, joints, muscles, tendons, ligaments, and vertebral column.', 'Active'),
    (6, 'Integumentary System', 'integumentary', 'shield', '🧴', 'Involves skin, dermis, cutaneous tissue, hair, nails, and surface barrier.', 'Active'),
    (7, 'Urinary System', 'urinary', 'droplets', '🧪', 'Involves kidneys, ureters, urinary bladder, urethra, and fluid homeostasis.', 'Active'),
    (8, 'Endocrine System', 'endocrine', 'sparkles', '🧬', 'Involves thyroid, adrenal glands, pancreas islet cells, and hormonal balance.', 'Active'),
    (9, 'General / Multisystem', 'general', 'cross', '🏥', 'Involves generalized constitutional symptoms, systemic immune, or metabolic factors.', 'Active')
ON CONFLICT DO NOTHING;

-- Data for: "body_locations" (12 records)
INSERT INTO "body_locations" ("BodyLocationID", "BodySystemID", "LocationName", "LocationCode", "FrontBack", "SubRegion", "Description") VALUES
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
ON CONFLICT DO NOTHING;

-- Data for: "symptoms" (16 records)
INSERT INTO "symptoms" ("SymptomID", "SymptomName", "Description", "Icon", "Status") VALUES
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
ON CONFLICT DO NOTHING;

-- Data for: "doctors" (18 records)
INSERT INTO "doctors" ("DoctorID", "UserID", "FirstName", "LastName", "SpecialtyID", "Title", "Specialty", "LicenseNumber", "ContactNumber", "Email", "ExperienceYears", "Clinic", "ClinicRoom", "ProfileImage", "Rating", "ReviewsCount", "ConsultationFee", "Bio", "Education", "Languages", "Status", "CreatedAt") VALUES
    (1, NULL, 'Maria', 'Santos', NULL, 'MD, FPCP, FPSG', 'Gastroenterologist', 'LIC-MED-84910', '+1 (555) 201-9081', 'dr.santos@tupimunicipal.gov.ph', 14, 'Tupi Municipal Hospital', 'Suite 405, East Wing', 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300&h=300', 4.95, 142, '$85.00', 'Board-certified specialist in digestive wellness, endoscopy, acid reflux management, gastritis, and inflammatory bowel disorders.', 'Johns Hopkins Medicine (Fellowship), UST Faculty of Medicine (MD)', 'English, Tagalog, Spanish', 'Available', '2026-09-22 01:12:46'),
    (2, NULL, 'John', 'Reyes', NULL, 'MD, FPCP', 'Internal Medicine Specialist', 'LIC-MED-77412', '+1 (555) 201-9082', 'dr.reyes@tupimunicipal.gov.ph', 10, 'Tupi Municipal Hospital', 'Room 210, Main Tower', 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300&h=300', 4.88, 98, '$65.00', 'Comprehensive adult disease management, digestive health screening, metabolic evaluation, and preventative primary healthcare.', 'Philippine General Hospital (Residency), UP College of Medicine (MD)', 'English, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (3, NULL, 'Alexander', 'Chen', NULL, 'MD, FACC, FSCAI', 'Cardiologist', 'LIC-MED-99301', '+1 (555) 201-9083', 'dr.chen@tupimunicipal.gov.ph', 16, 'Tupi Municipal Hospital', 'Cardiology Center, 5th Floor', 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=300&h=300', 4.96, 210, '$110.00', 'Expert in cardiovascular evaluations, arrhythmias, chest discomfort triage, hypertension, and advanced heart health.', 'Stanford Medicine (Cardiology Fellowship), Harvard Medical School (MD)', 'English, Mandarin', 'Available', '2026-09-22 01:12:46'),
    (4, NULL, 'Elena', 'Villanueva', NULL, 'MD, FCN', 'Neurologist', 'LIC-MED-66281', '+1 (555) 201-9084', 'dr.villanueva@tupimunicipal.gov.ph', 12, 'Tupi Municipal Hospital', 'Suite 302, North Wing', 'https://images.unsplash.com/photo-1594824813570-5b12852b7a4b?auto=format&fit=crop&q=80&w=300&h=300', 4.92, 165, '$95.00', 'Dedicated to comprehensive headache diagnostics, migraine therapeutics, peripheral neuropathy, and vertigo management.', 'Columbia University Medical Center (Fellowship), St. Luke''s College of Medicine (MD)', 'English, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (5, NULL, 'Marcus', 'Tan', NULL, 'MD, FPCCP', 'Pulmonologist', 'LIC-MED-55194', '+1 (555) 201-9085', 'dr.tan@tupimunicipal.gov.ph', 15, 'Tupi Municipal Hospital', 'Room 108, Pavilion B', 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=300&h=300', 4.89, 130, '$90.00', 'Specializing in bronchial asthma, acute and persistent cough evaluation, post-viral respiratory care, and breathing disorders.', 'Mayo Clinic College of Medicine (Pulmonary Fellowship), UP-PGH (MD)', 'English, Hokkien, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (6, NULL, 'Gabriel', 'Navarro', NULL, 'MD, FPOA', 'Orthopedic Specialist', 'LIC-MED-44029', '+1 (555) 201-9086', 'dr.navarro@tupimunicipal.gov.ph', 13, 'Tupi Municipal Hospital', 'Suite 101, Ortho Pavilion', 'https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&q=80&w=300&h=300', 4.93, 175, '$85.00', 'Expert in back pain rehabilitation, spine ergonomics, knee and shoulder joint injuries, arthritis, and sports-related strains.', 'Singapore General Hospital (Fellowship), UST Medicine (MD)', 'English, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (7, NULL, 'Patricia', 'Lim', NULL, 'MD, FPDS', 'Dermatologist', 'LIC-MED-33918', '+1 (555) 201-9087', 'dr.lim@tupimunicipal.gov.ph', 11, 'Tupi Municipal Hospital', 'Room 304, Wellness Wing', 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300&h=300', 4.91, 154, '$80.00', 'Specializing in acute rashes, contact dermatitis, allergic eczema, skin lesions, and preventative dermal health.', 'Mount Sinai Hospital (Fellowship), Ateneo SOM (MD)', 'English, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (8, NULL, 'Roberto', 'Alvarez', NULL, 'MD, FPUA', 'Urologist', 'LIC-MED-22874', '+1 (555) 201-9088', 'dr.alvarez@tupimunicipal.gov.ph', 15, 'Tupi Municipal Hospital', 'Suite 220, South Tower', 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300&h=300', 4.87, 112, '$95.00', 'Focused on urinary tract diagnostics, kidney stones, bladder health, dysuria management, and renal screening.', 'UCSF Health (Fellowship), UP-PGH (MD)', 'English, Tagalog, Spanish', 'Available', '2026-09-22 01:12:46'),
    (9, NULL, 'Sofia', 'Gonzales', NULL, 'MD, FPCP, FPSEDM', 'Endocrinologist', 'LIC-MED-11763', '+1 (555) 201-9089', 'dr.gonzales@tupimunicipal.gov.ph', 13, 'Tupi Municipal Hospital', 'Suite 412, Medical Plaza', 'https://images.unsplash.com/photo-1594824813570-5b12852b7a4b?auto=format&fit=crop&q=80&w=300&h=300', 4.94, 168, '$90.00', 'Expertise in thyroid disorders, metabolic fatigue, blood glucose regulation, hormonal imbalances, and weight physiology.', 'Cleveland Clinic (Fellowship), UST Faculty of Medicine (MD)', 'English, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (10, NULL, 'Kenneth', 'Garcia', NULL, 'MD, FAFP', 'Family & General Medicine', 'LIC-MED-10045', '+1 (555) 201-9090', 'dr.garcia@tupimunicipal.gov.ph', 8, 'Tupi Municipal Hospital', 'Room 102, Ground Floor', 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=300&h=300', 4.85, 89, '$55.00', 'Comprehensive family medicine, holistic health checks, initial symptom evaluation, and multi-specialty coordination.', 'St. Luke''s Medical Center (Residency), Ateneo SOM (MD)', 'English, Tagalog', 'Available', '2026-09-22 01:12:46'),
    (11, 101, 'Daniel', 'Lewis', 2, 'MD, FACC', 'Cardiologist', 'LIC-MED-CARD-101', '+1 (555) 441-2001', 'cardio@hospital.com', 15, 'TMHIS Heart Center', 'Suite 501, Cardiology Wing', 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300&h=300', 4.98, 240, '$120.00', 'Senior Cardiologist specializing in ischemic heart disease, hypertension, cardiovascular diagnostics, and heart rhythm management.', 'Harvard Medical School (MD), Cleveland Clinic (Cardiology Fellowship)', 'English, Spanish', 'Busy', '2026-09-21 22:50:03'),
    (12, 102, 'Elena', 'Villanueva', 3, 'MD, FCN', 'Neurologist', 'LIC-MED-NEUR-102', '+1 (555) 441-2002', 'neuro@hospital.com', 12, 'Tupi Municipal Hospital', 'Suite 302, North Wing', 'https://images.unsplash.com/photo-1594824813570-5b12852b7a4b?auto=format&fit=crop&q=80&w=300&h=300', 4.92, 165, '$95.00', 'Dedicated to comprehensive headache diagnostics, migraine therapeutics, peripheral neuropathy, and vertigo management.', 'Columbia University Medical Center (Fellowship), St. Luke''s College of Medicine (MD)', 'English, Tagalog', 'Available', '2026-09-21 22:50:03'),
    (13, 103, 'Sophia', 'Miller', 7, 'MD, FAAP', 'Pediatrician', 'LIC-MED-PED-103', '+1 (555) 441-2003', 'pedia@hospital.com', 11, 'TMHIS Childrens Center', 'Suite 104, Pediatric Wing', 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300&h=300', 4.96, 180, '$75.00', 'Expert in infant and adolescent medicine, immunization schedules, growth tracking, and acute childhood illnesses.', 'Boston Children''s Hospital (Fellowship), Johns Hopkins (MD)', 'English, French', 'Available', '2026-09-21 22:50:03'),
    (14, 104, 'James', 'Wilson', 8, 'MD, FACS', 'General Surgeon', 'LIC-MED-SURG-104', '+1 (555) 441-2004', 'surgeon@hospital.com', 18, 'TMHIS Surgical Pavilion', 'Suite 601, Surgical Wing', 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=300&h=300', 4.94, 290, '$150.00', 'Board-certified General Surgeon specializing in laparoscopic procedures, trauma surgery, and soft tissue interventions.', 'Mayo Clinic (Surgical Residency), Yale School of Medicine (MD)', 'English', 'Available', '2026-09-21 22:50:03'),
    (15, 105, 'Maria', 'Santos', 5, 'MD, FPCP, FPSG', 'Gastroenterologist', 'LIC-MED-GAST-105', '+1 (555) 441-2005', 'gastro@hospital.com', 14, 'Tupi Municipal Hospital', 'Suite 405, East Wing', 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300&h=300', 4.95, 142, '$85.00', 'Board-certified specialist in digestive wellness, endoscopy, acid reflux management, gastritis, and inflammatory bowel disorders.', 'Johns Hopkins Medicine (Fellowship), UST Faculty of Medicine (MD)', 'English, Tagalog, Spanish', 'Available', '2026-09-21 22:50:03'),
    (16, 106, 'Marcus', 'Tan', 4, 'MD, FPCCP', 'Pulmonologist', 'LIC-MED-PULM-106', '+1 (555) 441-2006', 'pulmo@hospital.com', 15, 'TMHIS Lung Center', 'Room 108, Pavilion B', 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=300&h=300', 4.89, 130, '$90.00', 'Specializing in bronchial asthma, acute and persistent cough evaluation, post-viral respiratory care, and breathing disorders.', 'Mayo Clinic College of Medicine (Pulmonary Fellowship), UP-PGH (MD)', 'English, Hokkien', 'Available', '2026-09-21 22:50:03'),
    (17, 107, 'Gabriel', 'Navarro', 6, 'MD, FPOA', 'Orthopedic', 'LIC-MED-ORTH-107', '+1 (555) 441-2007', 'ortho@hospital.com', 13, 'Tupi Municipal Hospital', 'Suite 101, Ortho Pavilion', 'https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&q=80&w=300&h=300', 4.93, 175, '$85.00', 'Expert in back pain rehabilitation, spine ergonomics, knee and shoulder joint injuries, arthritis, and sports-related strains.', 'Singapore General Hospital (Fellowship), UST Medicine (MD)', 'English, Tagalog', 'Available', '2026-09-21 22:50:03'),
    (18, 108, 'Kenneth', 'Garcia', 1, 'MD, FAFP', 'General Practitioner', 'LIC-MED-GP-108', '+1 (555) 441-2008', 'gp@hospital.com', 8, 'Tupi Municipal Hospital', 'Room 102, Ground Floor', 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=300&h=300', 4.85, 89, '$55.00', 'Comprehensive family medicine, holistic health checks, initial symptom evaluation, and multi-specialty coordination.', 'St. Luke''s Medical Center (Residency), Ateneo SOM (MD)', 'English, Tagalog', 'Available', '2026-09-21 22:50:03')
ON CONFLICT DO NOTHING;

-- Data for: "doctor_specialties" (8 records)
INSERT INTO "doctor_specialties" ("DoctorSpecialtyID", "DoctorID", "SpecialtyID", "IsPrimary", "CreatedAt") VALUES
    (1, 11, 2, TRUE, '2026-09-21 22:50:03'),
    (2, 12, 3, TRUE, '2026-09-21 22:50:03'),
    (3, 13, 7, TRUE, '2026-09-21 22:50:03'),
    (4, 14, 8, TRUE, '2026-09-21 22:50:03'),
    (5, 15, 5, TRUE, '2026-09-21 22:50:03'),
    (6, 16, 4, TRUE, '2026-09-21 22:50:03'),
    (7, 17, 6, TRUE, '2026-09-21 22:50:03'),
    (8, 18, 1, TRUE, '2026-09-21 22:50:03')
ON CONFLICT DO NOTHING;

-- Data for: "doctor_body_systems" (20 records)
INSERT INTO "doctor_body_systems" ("DoctorSystemID", "DoctorID", "BodySystemID") VALUES
    (9, 1, 1),
    (10, 2, 1),
    (11, 2, 9),
    (12, 3, 2),
    (13, 4, 3),
    (14, 5, 4),
    (15, 6, 5),
    (16, 7, 6),
    (17, 8, 7),
    (18, 9, 8),
    (20, 10, 1),
    (19, 10, 9),
    (1, 11, 2),
    (2, 12, 3),
    (3, 13, 9),
    (4, 14, 9),
    (5, 15, 1),
    (6, 16, 4),
    (7, 17, 5),
    (8, 18, 9)
ON CONFLICT DO NOTHING;

-- Data for: "patients" (48 records)
INSERT INTO "patients" ("PatientID", "PatientCode", "FirstName", "MiddleName", "LastName", "DateOfBirth", "Age", "Gender", "CivilStatus", "ContactNumber", "Email", "Address", "BloodType", "PatientCategory", "Status", "RegisteredBy", "CreatedAt", "UpdatedAt") VALUES
    (1, 'PAT-2026-0001', 'Juan', 'Ocampo', 'Reyes', '1952-02-04', 74, 'Male', 'Married', '+63 918 117 1073', 'juan.reyes1@example.com', 'Purok 2, Brgy. Acmonan, Tupi, South Cotabato 9505', 'A+', 'Consultation', 'Active', 4, '2026-08-02 09:07:00', NULL),
    (2, 'PAT-2026-0002', 'Corazon', 'Garcia', 'Bautista', '1954-03-07', 72, 'Female', 'Widowed', '+63 919 134 1146', 'corazon.bautista2@example.com', 'Purok 3, Brgy. Bololmala, Tupi, South Cotabato 9505', 'B+', 'Admitted', 'Active', 4, '2026-08-03 10:14:00', NULL),
    (3, 'PAT-2026-0003', 'Jose', 'Mendoza', 'Ocampo', '1956-04-10', 70, 'Male', 'Divorced', '+63 920 151 1219', 'jose.ocampo3@example.com', 'Purok 4, Brgy. Bunao, Tupi, South Cotabato 9505', 'AB+', 'Emergency', 'Active', 4, '2026-08-04 11:21:00', NULL),
    (4, 'PAT-2026-0004', 'Teresa', 'Torres', 'Garcia', '1958-05-13', 68, 'Female', 'Single', '+63 921 168 1292', 'teresa.garcia4@example.com', 'Purok 5, Brgy. Crossing Rubber, Tupi, South Cotabato 9505', 'O-', 'Inpatient', 'Active', 4, '2026-08-05 12:28:00', NULL),
    (5, 'PAT-2026-0005', 'Ramon', 'Tomas', 'Mendoza', '1960-06-16', 66, 'Male', 'Married', '+63 922 185 1365', 'ramon.mendoza5@example.com', 'Purok 6, Brgy. Kablon, Tupi, South Cotabato 9505', 'A-', 'Outpatient', 'Active', 4, '2026-08-06 13:35:00', NULL),
    (6, 'PAT-2026-0006', 'Rosario', 'Andrada', 'Torres', '1962-07-19', 64, 'Female', 'Widowed', '+63 923 202 1438', 'rosario.torres6@example.com', 'Purok 7, Brgy. Kalkam, Tupi, South Cotabato 9505', 'B-', 'Consultation', 'Active', 4, '2026-08-07 14:42:00', NULL),
    (7, 'PAT-2026-0007', 'Eduardo', 'Castro', 'Tomas', '1964-08-22', 62, 'Male', 'Divorced', '+63 924 219 1511', 'eduardo.tomas7@example.com', 'Purok 1, Brgy. Linan, Tupi, South Cotabato 9505', 'O+', 'Admitted', 'Active', 4, '2026-08-08 15:49:00', NULL),
    (8, 'PAT-2026-0008', 'Christina', 'Flores', 'Andrada', '1966-09-25', 60, 'Female', 'Single', '+63 925 236 1584', 'christina.andrada8@example.com', 'Purok 2, Brgy. Lunen, Tupi, South Cotabato 9505', 'A+', 'Emergency', 'Active', 4, '2026-08-09 08:56:00', NULL),
    (9, 'PAT-2026-0009', 'Gabriel', 'Gonzales', 'Castro', '1968-10-28', 58, 'Male', 'Married', '+63 926 253 1657', 'gabriel.castro9@example.com', 'Purok 3, Brgy. Polonuling, Tupi, South Cotabato 9505', 'B+', 'Inpatient', 'Active', 4, '2026-08-10 09:03:00', NULL),
    (10, 'PAT-2026-0010', 'Patricia', 'Ramos', 'Flores', '1970-11-03', 56, 'Female', 'Widowed', '+63 917 270 1730', 'patricia.flores10@example.com', 'Purok 4, Brgy. Simbo, Tupi, South Cotabato 9505', 'AB+', 'Outpatient', 'Active', 4, '2026-08-11 10:10:00', NULL),
    (11, 'PAT-2026-0011', 'Antonio', 'Lopez', 'Gonzales', '1972-12-06', 54, 'Male', 'Divorced', '+63 918 287 1803', 'antonio.gonzales11@example.com', 'Purok 5, Brgy. Tubo, Tupi, South Cotabato 9505', 'O-', 'Consultation', 'Active', 4, '2026-08-12 11:17:00', NULL),
    (12, 'PAT-2026-0012', 'Eileen', 'Mercado', 'Ramos', '1974-01-09', 52, 'Female', 'Single', '+63 919 304 1876', 'eileen.ramos12@example.com', 'Purok 6, Brgy. Poblacion, Tupi, South Cotabato 9505', 'A-', 'Admitted', 'Active', 4, '2026-08-13 12:24:00', NULL),
    (13, 'PAT-2026-0013', 'Ricardo', 'Aquino', 'Lopez', '1976-02-12', 50, 'Male', 'Married', '+63 920 321 1949', 'ricardo.lopez13@example.com', 'Purok 7, Brgy. Acmonan, Tupi, South Cotabato 9505', 'B-', 'Emergency', 'Active', 4, '2026-08-14 13:31:00', NULL),
    (14, 'PAT-2026-0014', 'Rowena', 'Morales', 'Mercado', '1978-03-15', 48, 'Female', 'Widowed', '+63 921 338 2022', 'rowena.mercado14@example.com', 'Purok 1, Brgy. Bololmala, Tupi, South Cotabato 9505', 'O+', 'Inpatient', 'Active', 4, '2026-08-15 14:38:00', NULL),
    (15, 'PAT-2026-0015', 'Christian', 'Villanueva', 'Aquino', '1980-04-18', 46, 'Male', 'Divorced', '+63 922 355 2095', 'christian.aquino15@example.com', 'Purok 2, Brgy. Bunao, Tupi, South Cotabato 9505', 'A+', 'Outpatient', 'Active', 4, '2026-08-16 15:45:00', NULL),
    (16, 'PAT-2026-0016', 'Maria', 'Fernandez', 'Morales', '1982-05-21', 44, 'Female', 'Single', '+63 923 372 2168', 'maria.morales16@example.com', 'Purok 3, Brgy. Crossing Rubber, Tupi, South Cotabato 9505', 'B+', 'Consultation', 'Active', 4, '2026-08-17 08:52:00', NULL),
    (17, 'PAT-2026-0017', 'Pedro', 'Dela Cruz', 'Villanueva', '1984-06-24', 42, 'Male', 'Married', '+63 924 389 2241', 'pedro.villanueva17@example.com', 'Purok 4, Brgy. Kablon, Tupi, South Cotabato 9505', 'AB+', 'Admitted', 'Active', 4, '2026-08-18 09:59:00', NULL),
    (18, 'PAT-2026-0018', 'Lourdes', 'Santos', 'Fernandez', '1986-07-27', 40, 'Female', 'Widowed', '+63 925 406 2314', 'lourdes.fernandez18@example.com', 'Purok 5, Brgy. Kalkam, Tupi, South Cotabato 9505', 'O-', 'Emergency', 'Active', 4, '2026-08-19 10:06:00', NULL),
    (19, 'PAT-2026-0019', 'Manuel', 'Reyes', 'Dela Cruz', '1988-08-02', 38, 'Male', 'Divorced', '+63 926 423 2387', 'manuel.delacruz19@example.com', 'Purok 6, Brgy. Linan, Tupi, South Cotabato 9505', 'A-', 'Inpatient', 'Active', 4, '2026-08-20 11:13:00', NULL),
    (20, 'PAT-2026-0020', 'Carmela', 'Bautista', 'Santos', '1990-09-05', 36, 'Female', 'Single', '+63 917 440 2460', 'carmela.santos20@example.com', 'Purok 7, Brgy. Lunen, Tupi, South Cotabato 9505', 'B-', 'Outpatient', 'Active', 4, '2026-08-21 12:20:00', NULL),
    (21, 'PAT-2026-0021', 'Danilo', 'Ocampo', 'Reyes', '1992-10-08', 34, 'Male', 'Married', '+63 918 457 2533', 'danilo.reyes21@example.com', 'Purok 1, Brgy. Polonuling, Tupi, South Cotabato 9505', 'O+', 'Consultation', 'Active', 4, '2026-08-22 13:27:00', NULL),
    (22, 'PAT-2026-0022', 'Angelica', 'Garcia', 'Bautista', '1994-11-11', 32, 'Female', 'Widowed', '+63 919 474 2606', 'angelica.bautista22@example.com', 'Purok 2, Brgy. Simbo, Tupi, South Cotabato 9505', 'A+', 'Admitted', 'Active', 4, '2026-08-23 14:34:00', NULL),
    (23, 'PAT-2026-0023', 'Roberto', 'Mendoza', 'Ocampo', '1996-12-14', 30, 'Male', 'Divorced', '+63 920 491 2679', 'roberto.ocampo23@example.com', 'Purok 3, Brgy. Tubo, Tupi, South Cotabato 9505', 'B+', 'Emergency', 'Active', 4, '2026-08-24 15:41:00', NULL),
    (24, 'PAT-2026-0024', 'Jasmine', 'Torres', 'Garcia', '1998-01-17', 28, 'Female', 'Single', '+63 921 508 2752', 'jasmine.garcia24@example.com', 'Purok 4, Brgy. Poblacion, Tupi, South Cotabato 9505', 'AB+', 'Inpatient', 'Active', 4, '2026-08-25 08:48:00', NULL),
    (25, 'PAT-2026-0025', 'Rolando', 'Tomas', 'Mendoza', '2000-02-20', 26, 'Male', 'Married', '+63 922 525 2825', 'rolando.mendoza25@example.com', 'Purok 5, Brgy. Acmonan, Tupi, South Cotabato 9505', 'O-', 'Outpatient', 'Active', 4, '2026-08-26 09:55:00', NULL),
    (26, 'PAT-2026-0026', 'Divina', 'Andrada', 'Torres', '1950-03-23', 76, 'Female', 'Widowed', '+63 923 542 2898', 'divina.torres26@example.com', 'Purok 6, Brgy. Bololmala, Tupi, South Cotabato 9505', 'A-', 'Consultation', 'Active', 4, '2026-08-27 10:02:00', NULL),
    (27, 'PAT-2026-0027', 'Ferdinand', 'Castro', 'Tomas', '1952-04-26', 74, 'Male', 'Divorced', '+63 924 559 2971', 'ferdinand.tomas27@example.com', 'Purok 7, Brgy. Bunao, Tupi, South Cotabato 9505', 'B-', 'Admitted', 'Active', 4, '2026-08-28 11:09:00', NULL),
    (28, 'PAT-2026-0028', 'Rochelle', 'Flores', 'Andrada', '1954-05-01', 72, 'Female', 'Single', '+63 925 576 3044', 'rochelle.andrada28@example.com', 'Purok 1, Brgy. Crossing Rubber, Tupi, South Cotabato 9505', 'O+', 'Emergency', 'Active', 4, '2026-08-01 12:16:00', NULL),
    (29, 'PAT-2026-0029', 'Angelo', 'Gonzales', 'Castro', '1956-06-04', 70, 'Male', 'Married', '+63 926 593 3117', 'angelo.castro29@example.com', 'Purok 2, Brgy. Kablon, Tupi, South Cotabato 9505', 'A+', 'Inpatient', 'Active', 4, '2026-08-02 13:23:00', NULL),
    (30, 'PAT-2026-0030', 'Beatriz', 'Ramos', 'Flores', '1958-07-07', 68, 'Female', 'Widowed', '+63 917 610 3190', 'beatriz.flores30@example.com', 'Purok 3, Brgy. Kalkam, Tupi, South Cotabato 9505', 'B+', 'Outpatient', 'Active', 4, '2026-08-03 14:30:00', NULL),
    (31, 'PAT-2026-0031', 'Juan', 'Lopez', 'Gonzales', '1960-08-10', 66, 'Male', 'Divorced', '+63 918 627 3263', 'juan.gonzales31@example.com', 'Purok 4, Brgy. Linan, Tupi, South Cotabato 9505', 'AB+', 'Consultation', 'Active', 4, '2026-08-04 15:37:00', NULL),
    (32, 'PAT-2026-0032', 'Corazon', 'Mercado', 'Ramos', '1962-09-13', 64, 'Female', 'Single', '+63 919 644 3336', 'corazon.ramos32@example.com', 'Purok 5, Brgy. Lunen, Tupi, South Cotabato 9505', 'O-', 'Admitted', 'Active', 4, '2026-08-05 08:44:00', NULL),
    (33, 'PAT-2026-0033', 'Jose', 'Aquino', 'Lopez', '1964-10-16', 62, 'Male', 'Married', '+63 920 661 3409', 'jose.lopez33@example.com', 'Purok 6, Brgy. Polonuling, Tupi, South Cotabato 9505', 'A-', 'Emergency', 'Active', 4, '2026-08-06 09:51:00', NULL),
    (34, 'PAT-2026-0034', 'Teresa', 'Morales', 'Mercado', '1966-11-19', 60, 'Female', 'Widowed', '+63 921 678 3482', 'teresa.mercado34@example.com', 'Purok 7, Brgy. Simbo, Tupi, South Cotabato 9505', 'B-', 'Inpatient', 'Active', 4, '2026-08-07 10:58:00', NULL),
    (35, 'PAT-2026-0035', 'Ramon', 'Villanueva', 'Aquino', '1968-12-22', 58, 'Male', 'Divorced', '+63 922 695 3555', 'ramon.aquino35@example.com', 'Purok 1, Brgy. Tubo, Tupi, South Cotabato 9505', 'O+', 'Outpatient', 'Discharged', 4, '2026-08-08 11:05:00', NULL),
    (36, 'P-TEST-1790008767', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008767@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:39:27', '2026-09-21 16:39:27'),
    (37, 'P-TEST-1790008806', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008806@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:40:06', '2026-09-21 16:40:06'),
    (38, 'P-TEST-1790008822', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008822@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:40:22', '2026-09-21 16:40:22'),
    (39, 'P-TEST-1790008837', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008837@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:40:37', '2026-09-21 16:40:37'),
    (40, 'P-TEST-1790008869', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008869@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:41:09', '2026-09-21 16:41:09'),
    (41, 'P-TEST-1790008885', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008885@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:41:25', '2026-09-21 16:41:25'),
    (42, 'P-TEST-1790008912', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008912@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:41:52', '2026-09-21 16:41:52'),
    (43, 'P-TEST-1790008941', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008941@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:42:21', '2026-09-21 16:42:21'),
    (44, 'P-TEST-1790008960', 'Juan', 'Protacio', 'Dela Cruz', '1990-05-15', 36, 'Male', 'Married', '09171234567', 'juan.delacruz.1790008960@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 111, '2026-09-21 16:42:40', '2026-09-21 16:42:40'),
    (49, 'PAT-2026-0036', 'TestFirst1790011107', NULL, 'TestLast1790011107', '1995-05-15', 31, 'Female', 'Single', '09123456789', 'test1790011107@example.com', 'Poblacion, Tupi, South Cotabato', 'O+', 'Outpatient', 'Active', 1, '2026-09-22 01:18:27', NULL),
    (52, 'PAT-2026-0037', 'Intake179001123896', NULL, 'Integrity179001123896', '1992-04-12', 34, 'Male', 'Married', '09171234567', 'intake179001123896@example.com', 'Brays, Tupi, South Cotabato', 'A+', 'Outpatient', 'Active', 111, '2026-09-22 01:20:38', NULL),
    (58, 'PAT-2026-0038', 'Intake179001132127', NULL, 'Integrity179001132127', '1992-04-12', 34, 'Male', 'Married', '09171234567', 'intake179001132127@example.com', 'Brays, Tupi, South Cotabato', 'A+', 'Outpatient', 'Active', 111, '2026-09-22 01:22:01', NULL),
    (64, 'PAT-2026-0039', 'Intake179001260826', NULL, 'Integrity179001260826', '1992-04-12', 34, 'Male', 'Married', '09171234567', 'intake179001260826@example.com', 'Brays, Tupi, South Cotabato', 'A+', 'Outpatient', 'Active', 111, '2026-09-22 01:43:28', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "emergency_contacts" (9 records)
INSERT INTO "emergency_contacts" ("EmergencyContactID", "PatientID", "ContactName", "Relationship", "ContactNumber") VALUES
    (1, 1, 'Carmen Amit', 'Parent', '+1 (555) 892-1145'),
    (2, 2, 'Marcus Reyes', 'Sibling', '+1 (555) 782-9901'),
    (3, 3, 'Sarah Miller', 'Spouse', '+1 (555) 234-9989'),
    (4, 4, 'Robert Vance', 'Parent', '+1 (555) 671-9900'),
    (5, 5, 'Michael Johnson', 'Spouse', '+1 (555) 431-7701'),
    (6, 49, 'Emergency Contact', 'Parent', '09987654321'),
    (7, 52, 'Jane Integrity', 'Spouse', '09177654321'),
    (11, 58, 'Jane Integrity', 'Spouse', '09177654321'),
    (15, 64, 'Jane Integrity', 'Spouse', '09177654321')
ON CONFLICT DO NOTHING;

-- Data for: "medical_histories" (9 records)
INSERT INTO "medical_histories" ("MedicalHistoryID", "PatientID", "Allergies", "ExistingConditions", "CurrentMedications", "PreviousHospitalization", "CreatedAt", "UpdatedAt") VALUES
    (1, 1, 'Penicillin (Mild rash)', 'Occasional Acid Reflux', 'Omeprazole 20mg PRN', 'None', '2026-09-22 01:12:46', NULL),
    (2, 2, 'None known', 'History of photophobia', 'Ibuprofen 400mg occasionally', 'None', '2026-09-22 01:12:46', NULL),
    (3, 3, 'Sulfa drugs', 'Mild Hypertension', 'Amlodipine 5mg daily', 'Appendectomy (2018)', '2026-09-22 01:12:46', NULL),
    (4, 4, 'Latex', 'None', 'None currently', 'None', '2026-09-22 01:12:46', NULL),
    (5, 5, 'Aspirin', 'Gastric Ulcer history', 'Antacids', 'Gallbladder removal (2021)', '2026-09-22 01:12:46', NULL),
    (6, 49, 'None', 'None', 'None', 'None', '2026-09-22 01:18:27', NULL),
    (7, 52, 'Penicillin', 'Hypertension', 'Amlodipine 5mg', 'None', '2026-09-22 01:20:38', NULL),
    (11, 58, 'Penicillin', 'Hypertension', 'Amlodipine 5mg', 'None', '2026-09-22 01:22:01', NULL),
    (15, 64, 'Penicillin', 'Hypertension', 'Amlodipine 5mg', 'None', '2026-09-22 01:43:28', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "patient_registration_history" (31 records)
INSERT INTO "patient_registration_history" ("HistoryID", "PatientID", "UserID", "Action", "Description", "CreatedAt") VALUES
    (1, 1, 1, 'Patient Registered', 'Patient Kent Carl Amit registered with digital code PAT-2026-0001.', '2026-08-15 08:30:00'),
    (2, 1, 1, 'Complaint Added', 'Chief complaint recorded: Sharp burning stomach pain and nausea.', '2026-08-15 08:32:00'),
    (3, 1, 1, 'Symptom Classification', 'Automated symptom correlation classified primary system as Digestive System (96% relevance).', '2026-08-15 08:33:00'),
    (4, 1, 1, 'Doctor Assigned', 'Consultation booked with Dr. Maria Santos (Gastroenterology).', '2026-08-15 08:35:00'),
    (5, 1, 1, 'Queue Generated', 'Assigned Queue Ticket #02 for Today.', '2026-08-15 08:35:00'),
    (6, 2, 1, 'Patient Registered', 'Patient Sophia Marie Reyes registered with digital code PAT-2026-0002.', '2026-08-15 09:15:00'),
    (7, 2, 1, 'Doctor Assigned', 'Consultation booked with Dr. Elena Villanueva (Neurology). Queue #01.', '2026-08-15 09:20:00'),
    (8, 49, 1, 'Consultation Scheduled', 'Consultation appointment #107 booked for 2026-09-21 at 10:00 AM.', '2026-09-22 01:18:27'),
    (9, 49, 1, 'Patient Registered', 'Patient TestFirst1790011107 TestLast1790011107 registered with Digital Code PAT-2026-0036.', '2026-09-22 01:18:27'),
    (10, 49, 1, 'Complaint Recorded', 'Chief complaint recorded: Severe chest pain radiating to left arm', '2026-09-22 01:18:27'),
    (11, 49, 1, 'Symptom Classification', 'Pre-consultation analysis matched to 95% relevance.', '2026-09-22 01:18:27'),
    (12, 49, 1, 'Doctor Assigned', 'Consultation booked with Doctor ID #1 for 2026-09-21 10:00 AM.', '2026-09-22 01:18:27'),
    (13, 49, 1, 'Queue Generated', 'Assigned Daily Queue Ticket #01.', '2026-09-22 01:18:27'),
    (14, 52, 111, 'Consultation Scheduled', 'Consultation appointment #108 booked for 2026-09-21 at 09:00 AM.', '2026-09-22 01:20:38'),
    (15, 52, 111, 'Patient Registered', 'Patient Intake179001123896 Integrity179001123896 registered with Digital Code PAT-2026-0037.', '2026-09-22 01:20:38'),
    (16, 52, 111, 'Complaint Recorded', 'Chief complaint recorded: Severe epigastric pain and acid reflux', '2026-09-22 01:20:38'),
    (17, 52, 111, 'Symptom Classification', 'Pre-consultation analysis matched to 95% relevance.', '2026-09-22 01:20:38'),
    (18, 52, 111, 'Doctor Assigned', 'Consultation booked with Doctor ID #1 for 2026-09-21 09:00 AM.', '2026-09-22 01:20:38'),
    (19, 52, 111, 'Queue Generated', 'Assigned Daily Queue Ticket #02.', '2026-09-22 01:20:38'),
    (20, 58, 111, 'Consultation Scheduled', 'Consultation appointment #109 booked for 2026-11-05 at 03:45 PM.', '2026-09-22 01:22:01'),
    (21, 58, 111, 'Patient Registered', 'Patient Intake179001132127 Integrity179001132127 registered with Digital Code PAT-2026-0038.', '2026-09-22 01:22:01'),
    (22, 58, 111, 'Complaint Recorded', 'Chief complaint recorded: Severe epigastric pain and acid reflux', '2026-09-22 01:22:01'),
    (23, 58, 111, 'Symptom Classification', 'Pre-consultation analysis matched to 95% relevance.', '2026-09-22 01:22:01'),
    (24, 58, 111, 'Doctor Assigned', 'Consultation booked with Doctor ID #1 for 2026-11-05 03:45 PM.', '2026-09-22 01:22:01'),
    (25, 58, 111, 'Queue Generated', 'Assigned Daily Queue Ticket #03.', '2026-09-22 01:22:01'),
    (26, 64, 111, 'Consultation Scheduled', 'Consultation appointment #110 booked for 2027-07-10 at 01:36 PM.', '2026-09-22 01:43:28'),
    (27, 64, 111, 'Patient Registered', 'Patient Intake179001260826 Integrity179001260826 registered with Digital Code PAT-2026-0039.', '2026-09-22 01:43:28'),
    (28, 64, 111, 'Complaint Recorded', 'Chief complaint recorded: Severe epigastric pain and acid reflux', '2026-09-22 01:43:28'),
    (29, 64, 111, 'Symptom Classification', 'Pre-consultation analysis matched to 95% relevance.', '2026-09-22 01:43:28'),
    (30, 64, 111, 'Doctor Assigned', 'Consultation booked with Doctor ID #1 for 2027-07-10 01:36 PM.', '2026-09-22 01:43:28'),
    (31, 64, 111, 'Queue Generated', 'Assigned Daily Queue Ticket #04.', '2026-09-22 01:43:28')
ON CONFLICT DO NOTHING;

-- Data for: "appointments" (45 records)
INSERT INTO "appointments" ("AppointmentID", "PatientID", "DoctorID", "AppointmentDate", "AppointmentTime", "ConsultationType", "Reason", "Priority", "Notes", "Status", "CreatedBy", "CreatedAt", "UpdatedAt") VALUES
    (1, 1, 2, '2026-09-02', '09:15:00', 'Secure Telehealth Video', 'Follow-up blood pressure check', 'Urgent', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, '2026-09-21 22:50:04', NULL),
    (2, 2, 3, '2026-09-03', '10:30:00', 'Follow-up Review', 'Routine prenatal checkup', 'High Priority', 'Routine clinical evaluation scheduled.', 'Waiting', 4, '2026-09-21 22:50:04', NULL),
    (3, 3, 4, '2026-09-04', '11:45:00', 'Emergency Triage', 'Persistent dry cough evaluation', 'Follow-up', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, '2026-09-21 22:50:04', NULL),
    (4, 4, 5, '2026-09-05', '12:00:00', 'In-Person Consultation', 'Mild joint pains in both knees', 'Normal', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (5, 5, 6, '2026-09-06', '13:15:00', 'Secure Telehealth Video', 'Fasting blood sugar follow-up', 'Urgent', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (6, 6, 7, '2026-09-07', '14:30:00', 'Follow-up Review', 'Headache and dizziness consultation', 'High Priority', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, '2026-09-21 22:50:04', NULL),
    (7, 7, 8, '2026-09-08', '15:45:00', 'Emergency Triage', 'Post-operative wound dressing review', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, '2026-09-21 22:50:04', NULL),
    (8, 8, 9, '2026-09-09', '08:00:00', 'In-Person Consultation', 'Pediatric immunization follow-up', 'Normal', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, '2026-09-21 22:50:04', NULL),
    (9, 9, 10, '2026-09-10', '09:15:00', 'Secure Telehealth Video', 'Chest tightness and ECG assessment', 'Urgent', 'Routine clinical evaluation scheduled.', 'Waiting', 4, '2026-09-21 22:50:04', NULL),
    (10, 10, 1, '2026-09-11', '10:30:00', 'Follow-up Review', 'Abdominal pain after fatty meals', 'High Priority', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, '2026-09-21 22:50:04', NULL),
    (11, 11, 2, '2026-09-12', '11:45:00', 'Emergency Triage', 'Hypertension monitoring and medication review', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (12, 12, 3, '2026-09-13', '12:00:00', 'In-Person Consultation', 'Follow-up blood pressure check', 'Normal', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (13, 13, 4, '2026-09-14', '13:15:00', 'Secure Telehealth Video', 'Routine prenatal checkup', 'Urgent', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, '2026-09-21 22:50:04', NULL),
    (14, 14, 5, '2026-09-15', '14:30:00', 'Follow-up Review', 'Persistent dry cough evaluation', 'High Priority', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, '2026-09-21 22:50:04', NULL),
    (15, 15, 6, '2026-09-16', '15:45:00', 'Emergency Triage', 'Mild joint pains in both knees', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, '2026-09-21 22:50:04', NULL),
    (16, 16, 7, '2026-09-17', '08:00:00', 'In-Person Consultation', 'Fasting blood sugar follow-up', 'Normal', 'Routine clinical evaluation scheduled.', 'Waiting', 4, '2026-09-21 22:50:04', NULL),
    (17, 17, 8, '2026-09-18', '09:15:00', 'Secure Telehealth Video', 'Headache and dizziness consultation', 'Urgent', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, '2026-09-21 22:50:04', NULL),
    (18, 18, 9, '2026-09-19', '10:30:00', 'Follow-up Review', 'Post-operative wound dressing review', 'High Priority', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (19, 19, 10, '2026-09-20', '11:45:00', 'Emergency Triage', 'Pediatric immunization follow-up', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (20, 20, 1, '2026-09-21', '12:00:00', 'In-Person Consultation', 'Chest tightness and ECG assessment', 'Normal', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, '2026-09-21 22:50:04', NULL),
    (21, 21, 2, '2026-09-22', '13:15:00', 'Secure Telehealth Video', 'Abdominal pain after fatty meals', 'Urgent', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, '2026-09-21 22:50:04', NULL),
    (22, 22, 3, '2026-09-23', '14:30:00', 'Follow-up Review', 'Hypertension monitoring and medication review', 'High Priority', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, '2026-09-21 22:50:04', NULL),
    (23, 23, 4, '2026-09-24', '15:45:00', 'Emergency Triage', 'Follow-up blood pressure check', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Waiting', 4, '2026-09-21 22:50:04', NULL),
    (24, 24, 5, '2026-09-25', '08:00:00', 'In-Person Consultation', 'Routine prenatal checkup', 'Normal', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, '2026-09-21 22:50:04', NULL),
    (25, 25, 6, '2026-09-26', '09:15:00', 'Secure Telehealth Video', 'Persistent dry cough evaluation', 'Urgent', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (26, 26, 7, '2026-09-27', '10:30:00', 'Follow-up Review', 'Mild joint pains in both knees', 'High Priority', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (27, 27, 8, '2026-09-28', '11:45:00', 'Emergency Triage', 'Fasting blood sugar follow-up', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, '2026-09-21 22:50:04', NULL),
    (28, 28, 9, '2026-09-01', '12:00:00', 'In-Person Consultation', 'Headache and dizziness consultation', 'Normal', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, '2026-09-21 22:50:04', NULL),
    (29, 29, 10, '2026-09-02', '13:15:00', 'Secure Telehealth Video', 'Post-operative wound dressing review', 'Urgent', 'Routine clinical evaluation scheduled.', 'Confirmed', 4, '2026-09-21 22:50:04', NULL),
    (30, 30, 1, '2026-09-03', '14:30:00', 'Follow-up Review', 'Pediatric immunization follow-up', 'High Priority', 'Routine clinical evaluation scheduled.', 'Waiting', 4, '2026-09-21 22:50:04', NULL),
    (31, 31, 2, '2026-09-04', '15:45:00', 'Emergency Triage', 'Chest tightness and ECG assessment', 'Follow-up', 'Routine clinical evaluation scheduled.', 'In Consultation', 4, '2026-09-21 22:50:04', NULL),
    (32, 32, 3, '2026-09-05', '08:00:00', 'In-Person Consultation', 'Abdominal pain after fatty meals', 'Normal', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (33, 33, 4, '2026-09-06', '09:15:00', 'Secure Telehealth Video', 'Hypertension monitoring and medication review', 'Urgent', 'Routine clinical evaluation scheduled.', 'Completed', 4, '2026-09-21 22:50:04', NULL),
    (34, 34, 5, '2026-09-07', '10:30:00', 'Follow-up Review', 'Follow-up blood pressure check', 'High Priority', 'Routine clinical evaluation scheduled.', 'Cancelled', 4, '2026-09-21 22:50:04', NULL),
    (35, 35, 6, '2026-09-08', '11:45:00', 'Emergency Triage', 'Routine prenatal checkup', 'Follow-up', 'Routine clinical evaluation scheduled.', 'Scheduled', 4, '2026-09-21 22:50:04', NULL),
    (101, 3, 11, '2026-09-21', '09:00 AM', 'In-Person Consultation', 'Precordial chest tightness, fluttering palpitations during light exertion', 'High Priority', 'Requires 12-lead ECG review and blood pressure evaluation.', 'Waiting', 1, '2026-09-21 22:50:03', NULL),
    (102, 6, 11, '2026-09-21', '10:30 AM', 'In-Person Consultation', 'Cardiovascular follow-up for chronic hypertension and cholesterol review', 'Normal', 'Scheduled fasting lipid profile requested.', 'Scheduled', 1, '2026-09-21 22:50:03', NULL),
    (103, 8, 11, '2026-09-21', '02:00 PM', 'In-Person Consultation', 'Exertional dyspnea and tachycardia when climbing stairs', 'Urgent', 'Arrhythmia assessment.', 'Scheduled', 1, '2026-09-21 22:50:03', NULL),
    (104, 10, 11, '2026-09-21', '03:30 PM', 'In-Person Consultation', 'Routine cardiac wellness audit and post-infarction rehabilitation follow-up', 'Normal', 'Echo results to be reviewed.', 'In Consultation', 1, '2026-09-21 22:50:03', '2026-09-22 01:47:31'),
    (105, 2, 12, '2026-09-21', '09:30 AM', 'In-Person Consultation', 'Severe unilateral throbbing migraine with photophobia and dizziness', 'Urgent', 'Quiet environment requested.', 'In Consultation', 1, '2026-09-21 22:50:03', NULL),
    (106, 7, 12, '2026-09-21', '11:15 AM', 'In-Person Consultation', 'Recurring tension headaches and cervical neck stiffness', 'Normal', 'Postural and stress assessment.', 'Waiting', 1, '2026-09-21 22:50:03', NULL),
    (107, 49, 1, '2026-09-21', '10:00 AM', 'In-Person Consultation', 'Severe chest pain radiating to left arm', 'Urgent', 'Pre-consultation digital intake registration.', 'Scheduled', 1, '2026-09-22 01:18:27', NULL),
    (108, 52, 1, '2026-09-21', '09:00 AM', 'In-Person Consultation', 'Severe epigastric pain and acid reflux', 'Normal', 'Pre-consultation digital intake registration.', 'Scheduled', 111, '2026-09-22 01:20:38', NULL),
    (109, 58, 1, '2026-11-05', '03:45 PM', 'In-Person Consultation', 'Severe epigastric pain and acid reflux', 'Normal', 'Pre-consultation digital intake registration.', 'Scheduled', 111, '2026-09-22 01:22:01', NULL),
    (110, 64, 1, '2027-07-10', '01:36 PM', 'In-Person Consultation', 'Severe epigastric pain and acid reflux', 'Normal', 'Pre-consultation digital intake registration.', 'Scheduled', 111, '2026-09-22 01:43:28', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "patient_queue" (42 records)
INSERT INTO "patient_queue" ("QueueID", "AppointmentID", "PatientID", "DoctorID", "QueueNumber", "QueueDate", "QueueStatus", "Priority", "CalledAt", "ConsultationStartedAt", "CompletedAt", "CreatedAt") VALUES
    (1, 1, 1, 2, 'Q-001', '2026-09-21', 'Called', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (2, 2, 2, 3, 'Q-002', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (3, 3, 3, 4, 'Q-003', '2026-09-21', 'Completed', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (4, 4, 4, 5, 'Q-004', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (5, 5, 5, 6, 'Q-005', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (6, 6, 6, 7, 'Q-006', '2026-09-21', 'Called', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (7, 7, 7, 8, 'Q-007', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (8, 8, 8, 9, 'Q-008', '2026-09-21', 'Completed', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (9, 9, 9, 10, 'Q-009', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (10, 10, 10, 1, 'Q-010', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (11, 11, 11, 2, 'Q-011', '2026-09-21', 'Called', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (12, 12, 12, 3, 'Q-012', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (13, 13, 13, 4, 'Q-013', '2026-09-21', 'Completed', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (14, 14, 14, 5, 'Q-014', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (15, 15, 15, 6, 'Q-015', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (16, 16, 16, 7, 'Q-016', '2026-09-21', 'Called', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (17, 17, 17, 8, 'Q-017', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (18, 18, 18, 9, 'Q-018', '2026-09-21', 'Completed', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (19, 19, 19, 10, 'Q-019', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (20, 20, 20, 1, 'Q-020', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (21, 21, 21, 2, 'Q-021', '2026-09-21', 'Called', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (22, 22, 22, 3, 'Q-022', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (23, 23, 23, 4, 'Q-023', '2026-09-21', 'Completed', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (24, 24, 24, 5, 'Q-024', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (25, 25, 25, 6, 'Q-025', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:04'),
    (101, 101, 3, 11, 'C-01', '2026-09-21', 'Waiting', 'Priority', NULL, NULL, NULL, '2026-09-21 22:50:03'),
    (102, 102, 6, 11, 'C-02', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:03'),
    (103, 103, 8, 11, 'C-03', '2026-09-21', 'Waiting', 'Emergency', NULL, NULL, NULL, '2026-09-21 22:50:03'),
    (104, 104, 10, 11, 'C-04', '2026-09-21', 'In Consultation', 'Normal', '2026-08-16 08:30:00', '2026-09-22 01:47:31', NULL, '2026-09-21 22:50:03'),
    (105, 105, 2, 12, 'N-01', '2026-09-21', 'In Consultation', 'Priority', '2026-08-16 09:30:00', '2026-08-16 09:35:00', NULL, '2026-09-21 22:50:03'),
    (106, 106, 7, 12, 'N-02', '2026-09-21', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-21 22:50:03'),
    (108, NULL, 38, 11, 'Q-187', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:40:22'),
    (109, NULL, 39, 11, 'Q-676', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:40:37'),
    (110, NULL, 40, 11, 'Q-975', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:41:09'),
    (111, NULL, 41, 11, 'Q-344', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:41:25'),
    (112, NULL, 42, 11, 'Q-498', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:41:52'),
    (113, NULL, 43, 11, 'Q-772', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:42:21'),
    (114, NULL, 44, 11, 'Q-493', '2026-09-21', 'In Consultation', 'Normal', NULL, NULL, NULL, '2026-09-21 16:42:40'),
    (117, 107, 49, 1, '01', '2026-09-22', 'Waiting', 'Priority', NULL, NULL, NULL, '2026-09-22 01:18:27'),
    (120, 108, 52, 1, '02', '2026-09-22', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-22 01:20:38'),
    (123, 109, 58, 1, '03', '2026-09-22', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-22 01:22:01'),
    (126, 110, 64, 1, '04', '2026-09-22', 'Waiting', 'Normal', NULL, NULL, NULL, '2026-09-22 01:43:28')
ON CONFLICT DO NOTHING;

-- Data for: "patient_vitals" (37 records)
INSERT INTO "patient_vitals" ("VitalID", "PatientID", "AppointmentID", "BloodPressure", "HeartRate", "RespiratoryRate", "Temperature", "OxygenSaturation", "PainScale", "WeightKg", "HeightCm", "BMI", "ClinicalNotes", "RecordedBy", "RecordedByName", "CreatedAt") VALUES
    (1, 1, NULL, '113/72', 74, 16, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Patient is resting comfortably, normal sinus rhythm.', NULL, 'Elena Gomez, RN', '2026-09-21 22:50:03'),
    (2, 2, NULL, '116/74', 82, 18, 36.8, 98, 4, 58.00, 160.00, 22.7, 'Moderate tension headache reported.', NULL, 'Elena Gomez, RN', '2026-09-21 22:50:03'),
    (3, 3, NULL, '119/76', 88, 20, 36.7, 98, 2, 74.00, 168.00, 26.2, 'Fluttering sensation, monitoring requested by Dr. Lewis.', NULL, 'Elena Gomez, RN', '2026-09-21 22:50:03'),
    (4, 4, NULL, '122/78', 70, 16, 36.5, 99, 0, 62.00, 165.00, 22.8, 'Routine pre-consultation vitals.', NULL, 'Elena Gomez, RN', '2026-09-21 22:50:03'),
    (5, 5, NULL, '125/80', 76, 17, 36.6, 99, 1, 70.00, 170.00, 24.2, 'Stable baseline vitals.', NULL, 'Elena Gomez, RN', '2026-09-21 22:50:03'),
    (6, 6, 6, '128/82', 86, 16, 37.3, 98, 0, 59.00, 159.20, 23.3, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (7, 7, 7, '131/84', 89, 17, 37.4, 99, 1, 60.50, 160.40, 23.5, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (8, 8, 8, '134/86', 92, 18, 37.6, 96, 2, 62.00, 161.60, 23.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (9, 9, 9, '137/88', 95, 19, 37.8, 97, 3, 63.50, 162.80, 24.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (10, 10, 10, '140/90', 98, 20, 36.4, 98, 4, 65.00, 164.00, 24.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (11, 11, 11, '143/92', 69, 21, 36.5, 99, 5, 66.50, 165.20, 24.4, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (12, 12, 12, '146/94', 72, 16, 36.7, 96, 0, 68.00, 166.40, 24.6, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (13, 13, 13, '149/71', 75, 17, 36.9, 97, 1, 69.50, 167.60, 24.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (14, 14, 14, '152/73', 78, 18, 37.0, 98, 2, 71.00, 168.80, 24.9, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (15, 15, 15, '110/75', 81, 19, 37.1, 99, 3, 72.50, 170.00, 25.1, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (16, 16, 16, '113/77', 84, 20, 37.3, 96, 4, 74.00, 171.20, 25.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (17, 17, 17, '116/79', 87, 21, 37.4, 97, 5, 75.50, 172.40, 25.4, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (18, 18, 18, '119/81', 90, 16, 37.6, 98, 0, 77.00, 173.60, 25.6, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (19, 19, 19, '122/83', 93, 17, 37.8, 99, 1, 78.50, 174.80, 25.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (20, 20, 20, '125/85', 96, 18, 36.4, 96, 2, 80.00, 176.00, 25.8, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (21, 21, 21, '128/87', 99, 19, 36.5, 97, 3, 81.50, 177.20, 26.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (22, 22, 22, '131/89', 70, 20, 36.7, 98, 4, 83.00, 178.40, 26.1, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (23, 23, 23, '134/91', 73, 21, 36.9, 99, 5, 84.50, 179.60, 26.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (24, 24, 24, '137/93', 76, 16, 37.0, 96, 0, 86.00, 152.80, 36.8, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (25, 25, 25, '140/70', 79, 17, 37.1, 97, 1, 87.50, 154.00, 36.9, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (26, 26, 26, '143/72', 82, 18, 37.3, 98, 2, 51.00, 155.20, 21.2, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (27, 27, 27, '146/74', 85, 19, 37.4, 99, 3, 52.50, 156.40, 21.5, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (28, 28, 28, '149/76', 88, 20, 37.6, 96, 4, 54.00, 157.60, 21.7, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Carlos Reyes, RN', '2026-09-21 22:50:04'),
    (29, 29, 29, '152/78', 91, 21, 37.8, 97, 5, 55.50, 158.80, 22.0, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Ana Bautista, RN', '2026-09-21 22:50:04'),
    (30, 30, 30, '110/80', 94, 16, 36.4, 98, 0, 57.00, 160.00, 22.3, 'Patient ambulatory. Alert and responsive. Vital signs stable.', 6, 'Elena Gomez, RN', '2026-09-21 22:50:04'),
    (31, 38, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:40:22'),
    (32, 39, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:40:37'),
    (33, 40, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:41:09'),
    (34, 41, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:41:25'),
    (35, 42, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:41:52'),
    (36, 43, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:42:21'),
    (37, 44, NULL, '120/80', 75, 18, 36.6, 99, 0, 68.50, 172.00, 23.2, 'Normal baseline vital signs', 4, 'Nurse on Duty', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "complaints" (9 records)
INSERT INTO "complaints" ("ComplaintID", "PatientID", "ComplaintDescription", "Severity", "Duration", "AggravatingFactors", "RelievingFactors", "CreatedAt") VALUES
    (1, 1, 'Sharp burning stomach pain right below the ribs since yesterday. The pain noticeably worsens after eating spicy meals, accompanied by frequent nausea and bloating.', 3, '1–3 days ago', 'Eating spicy/heavy meals, lying flat', 'Drinking warm water, over-the-counter antacids', '2026-08-15 08:32:00'),
    (2, 2, 'Intense throbbing pain on the left side of my head that began this morning. Bright lights and computer screens make it much worse, accompanied by dizziness and mild nausea.', 4, 'Today', 'Screen glare, loud sounds, bright sunlight', 'Resting in a quiet dark room, cold compress', '2026-08-15 09:18:00'),
    (3, 3, 'Rapid heartbeats and fluttering sensations in my chest during work earlier today. Mild tightness in the upper anterior chest area.', 3, 'Today', 'Caffeine intake, work stress, rapid walking', 'Sitting down, slow deep breathing', '2026-08-15 10:05:00'),
    (4, 4, 'Twisted right knee during a weekend run 3 days ago. The joint is swollen, stiff in the mornings, and painful when climbing stairs or bending.', 3, '1–3 days ago', 'Climbing stairs, prolonged standing', 'Ice packs, leg elevation, resting', '2026-08-15 11:35:00'),
    (5, 5, 'Severe upper abdominal cramps and heartburn after lunch.', 4, 'Today', 'Eating foods with high fat content', 'Sitting upright, drinking milk', '2026-08-15 13:05:00'),
    (6, 49, 'Severe chest pain radiating to left arm', 4, '1–3 days ago', NULL, NULL, '2026-09-22 01:18:27'),
    (7, 52, 'Severe epigastric pain and acid reflux', 3, '1–3 days ago', NULL, NULL, '2026-09-22 01:20:38'),
    (11, 58, 'Severe epigastric pain and acid reflux', 3, '1–3 days ago', NULL, NULL, '2026-09-22 01:22:01'),
    (15, 64, 'Severe epigastric pain and acid reflux', 3, '1–3 days ago', NULL, NULL, '2026-09-22 01:43:28')
ON CONFLICT DO NOTHING;

-- Data for: "complaint_conditions" (31 records)
INSERT INTO "complaint_conditions" ("ComplaintConditionID", "ComplaintID", "ConditionID") VALUES
    (1, 1, 1),
    (2, 1, 2),
    (3, 1, 3),
    (5, 2, 9),
    (4, 2, 10),
    (6, 3, 6),
    (7, 3, 7),
    (8, 4, 15),
    (9, 4, 16),
    (10, 5, 1),
    (11, 5, 2),
    (12, 6, 1),
    (13, 6, 2),
    (14, 6, 3),
    (15, 6, 4),
    (16, 6, 5),
    (17, 7, 1),
    (18, 7, 2),
    (19, 7, 3),
    (20, 7, 4),
    (21, 7, 5),
    (37, 11, 1),
    (38, 11, 2),
    (39, 11, 3),
    (40, 11, 4),
    (41, 11, 5),
    (57, 15, 1),
    (58, 15, 2),
    (59, 15, 3),
    (60, 15, 4),
    (61, 15, 5)
ON CONFLICT DO NOTHING;

-- Data for: "possible_conditions" (20 records)
INSERT INTO "possible_conditions" ("ConditionID", "ConditionName", "BodySystemID", "Description", "Status") VALUES
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
ON CONFLICT DO NOTHING;

-- Data for: "patient_symptoms" (13 records)
INSERT INTO "patient_symptoms" ("PatientSymptomID", "ComplaintID", "SymptomID") VALUES
    (1, 1, 4),
    (2, 1, 6),
    (3, 1, 10),
    (4, 1, 16),
    (5, 2, 2),
    (6, 2, 3),
    (7, 2, 4),
    (9, 3, 3),
    (8, 3, 11),
    (11, 4, 12),
    (10, 4, 13),
    (12, 5, 4),
    (13, 5, 16)
ON CONFLICT DO NOTHING;

-- Data for: "complaint_analysis" (9 records)
INSERT INTO "complaint_analysis" ("AnalysisID", "ComplaintID", "BodySystemID", "BodyLocationID", "RelevanceLevel", "ConfidenceLevel", "ClinicalNotes", "CreatedAt") VALUES
    (3, 1, 1, 4, 96, 'High (96%)', 'Epigastric gastric mucosal irritation with secondary dyspeptic presentation.', '2026-09-22 01:12:46'),
    (4, 2, 3, 1, 94, 'High (94%)', 'Unilateral cephalalgia with sensory photophobia indicative of migraine pathway.', '2026-09-22 01:12:46'),
    (5, 3, 2, 3, 92, 'High (92%)', 'Precordial rhythm flutter requiring baseline 12-lead ECG and cardiovascular evaluation.', '2026-09-22 01:12:46'),
    (6, 4, 5, 9, 93, 'High (93%)', 'Patellofemoral joint strain with mechanical inflammatory edema.', '2026-09-22 01:12:46'),
    (7, 5, 1, 4, 95, 'High (95%)', 'Upper gastrointestinal hyperacidity and acute gastric irritation.', '2026-09-22 01:12:46'),
    (8, 6, 1, 3, 95, 'High (95%)', 'Automated pre-consultation symptom classification matched to Chest & Thorax', '2026-09-22 01:18:27'),
    (9, 7, 1, 4, 95, 'High (95%)', 'Automated pre-consultation symptom classification matched to Abdomen', '2026-09-22 01:20:38'),
    (13, 11, 1, 4, 95, 'High (95%)', 'Automated pre-consultation symptom classification matched to Abdomen', '2026-09-22 01:22:01'),
    (17, 15, 1, 4, 95, 'High (95%)', 'Automated pre-consultation symptom classification matched to Abdomen', '2026-09-22 01:43:28')
ON CONFLICT DO NOTHING;

-- Data for: "diagnoses" (10 records)
INSERT INTO "diagnoses" ("DiagnosisID", "PatientID", "DoctorID", "AppointmentID", "DiagnosisName", "ICD10Code", "Type", "Severity", "Status", "Notes", "DiagnosedDate", "CreatedAt") VALUES
    (1, 3, 11, 101, 'Cardiac Palpitations', 'R00.2', 'Primary', 'Moderate', 'Active', 'Fluttering sensations triggered by exertion/stress.', '2026-09-21', '2026-09-21 22:50:03'),
    (2, 3, 11, 101, 'Essential (Primary) Hypertension', 'I10', 'Secondary', 'Mild', 'Active', 'Stage 1 hypertension noted during triage.', '2026-09-21', '2026-09-21 22:50:03'),
    (3, 10, 11, 104, 'Atherosclerotic Heart Disease', 'I25.1', 'Primary', 'Moderate', 'Chronic', 'Post-stent stable follow-up.', '2026-09-21', '2026-09-21 22:50:03'),
    (4, 2, 12, 105, 'Migraine without Aura', 'G43.0', 'Primary', 'Severe', 'Active', 'Unilateral throbbing with severe photophobia and nausea.', '2026-09-21', '2026-09-21 22:50:03'),
    (5, 39, 11, NULL, 'Acute Upper Respiratory Infection', 'J06.9', 'Primary', 'Moderate', 'Active', NULL, '2026-09-21', '2026-09-21 16:40:37'),
    (6, 40, 11, NULL, 'Acute Upper Respiratory Infection', 'J06.9', 'Primary', 'Moderate', 'Active', NULL, '2026-09-21', '2026-09-21 16:41:09'),
    (7, 41, 11, NULL, 'Acute Upper Respiratory Infection', 'J06.9', 'Primary', 'Moderate', 'Active', NULL, '2026-09-21', '2026-09-21 16:41:25'),
    (8, 42, 11, NULL, 'Acute Upper Respiratory Infection', 'J06.9', 'Primary', 'Moderate', 'Active', NULL, '2026-09-21', '2026-09-21 16:41:52'),
    (9, 43, 11, NULL, 'Acute Upper Respiratory Infection', 'J06.9', 'Primary', 'Moderate', 'Active', NULL, '2026-09-21', '2026-09-21 16:42:21'),
    (10, 44, 11, NULL, 'Acute Upper Respiratory Infection', 'J06.9', 'Primary', 'Moderate', 'Active', NULL, '2026-09-21', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "consultation_notes" (8 records)
INSERT INTO "consultation_notes" ("NoteID", "PatientID", "DoctorID", "AppointmentID", "Subjective", "Objective", "Assessment", "Plan", "ClinicalNotes", "VitalSigns", "CreatedAt", "UpdatedAt") VALUES
    (1, 3, 11, 101, 'Patient reports sudden onset of palpitations and mid-sternal tightness after climbing stairs.', 'BP: 138/88 mmHg, HR: 88 bpm, SpO2: 98%, Heart sounds normal S1/S2 without murmurs.', 'Suspected Paroxysmal Tachycardia vs Stress-induced Palpitations.', '12-lead ECG, Lipid Profile, Metoprolol 25mg daily, avoid excessive caffeine.', 'Patient is alert and oriented. Mid-chest flutter exacerbated by stress and caffeine.', '{"bp": "138/88", "hr": "88", "spo2": "98%", "temp": "36.7┬░C", "weight": "74 kg"}', '2026-09-21 22:50:03', NULL),
    (2, 10, 11, 104, 'Routine 6-month cardiac check. No angina episodes reported. Adherent to statin therapy.', 'BP: 122/78 mmHg, HR: 68 bpm regular, clear lung fields.', 'Stable Coronary Artery Disease - well controlled.', 'Maintain current regimen, continue daily 30-min walking exercise, re-check in 6 months.', 'Good functional capacity. Echocardiogram shows preserved EF 55%.', '{"bp": "122/78", "hr": "68", "spo2": "99%", "temp": "36.5┬░C", "weight": "80 kg"}', '2026-09-21 22:50:03', NULL),
    (3, 39, 11, NULL, 'Patient complains of fever and cough for 3 days.', 'Pharyngeal erythema noted. Lungs clear to auscultation.', 'Upper Respiratory Tract Infection', 'Prescribe antibiotics and order CBC with Platelet count.', 'Patient advised on rest and hydration.', NULL, '2026-09-21 16:40:37', '2026-09-21 16:40:37'),
    (4, 40, 11, NULL, 'Patient complains of fever and cough for 3 days.', 'Pharyngeal erythema noted. Lungs clear to auscultation.', 'Upper Respiratory Tract Infection', 'Prescribe antibiotics and order CBC with Platelet count.', 'Patient advised on rest and hydration.', NULL, '2026-09-21 16:41:09', '2026-09-21 16:41:09'),
    (5, 41, 11, NULL, 'Patient complains of fever and cough for 3 days.', 'Pharyngeal erythema noted. Lungs clear to auscultation.', 'Upper Respiratory Tract Infection', 'Prescribe antibiotics and order CBC with Platelet count.', 'Patient advised on rest and hydration.', NULL, '2026-09-21 16:41:25', '2026-09-21 16:41:25'),
    (6, 42, 11, NULL, 'Patient complains of fever and cough for 3 days.', 'Pharyngeal erythema noted. Lungs clear to auscultation.', 'Upper Respiratory Tract Infection', 'Prescribe antibiotics and order CBC with Platelet count.', 'Patient advised on rest and hydration.', NULL, '2026-09-21 16:41:52', '2026-09-21 16:41:52'),
    (7, 43, 11, NULL, 'Patient complains of fever and cough for 3 days.', 'Pharyngeal erythema noted. Lungs clear to auscultation.', 'Upper Respiratory Tract Infection', 'Prescribe antibiotics and order CBC with Platelet count.', 'Patient advised on rest and hydration.', NULL, '2026-09-21 16:42:21', '2026-09-21 16:42:21'),
    (8, 44, 11, NULL, 'Patient complains of fever and cough for 3 days.', 'Pharyngeal erythema noted. Lungs clear to auscultation.', 'Upper Respiratory Tract Infection', 'Prescribe antibiotics and order CBC with Platelet count.', 'Patient advised on rest and hydration.', NULL, '2026-09-21 16:42:40', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "treatment_plans" (2 records)
INSERT INTO "treatment_plans" ("PlanID", "PatientID", "DoctorID", "AppointmentID", "DiagnosisID", "Goal", "LifestyleRecommendations", "MedicationPlan", "FollowUpSchedule", "FollowUpDate", "Status", "Notes", "CreatedAt", "UpdatedAt") VALUES
    (1, 3, 11, 101, 1, 'Restore stable resting rhythm and keep BP < 130/80 mmHg', 'Reduce sodium intake to < 2g/day. Limit caffeine and energy drinks. Implement daily cardiovascular aerobic walking.', 'Metoprolol Tartrate 25mg PO BID with meals. Amlodipine 5mg PO OD morning.', 'Follow-up in 2 weeks with ECG results', '2026-10-05', 'Active', NULL, '2026-09-21 22:50:03', NULL),
    (2, 10, 11, 104, 3, 'Maintain long-term cardiovascular stability and LDL < 70 mg/dL', 'Mediterranean heart-healthy diet, regular physical exercise 150 min/week, maintain smoking cessation.', 'Atorvastatin 40mg PO at bedtime. Aspirin 81mg PO daily after breakfast.', 'Annual cardiac audit in 6 months', '2027-03-20', 'Active', NULL, '2026-09-21 22:50:03', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "medical_certificates" (20 records)
INSERT INTO "medical_certificates" ("CertificateID", "CertificateCode", "PatientID", "DoctorID", "CertificateType", "Diagnosis", "Recommendation", "DurationStart", "DurationEnd", "DaysExcused", "Remarks", "IssueDate", "CreatedAt") VALUES
    (1, 'MED-CERT-2026-0001', 3, 11, 'Medical Leave', 'Paroxysmal Tachycardia and Exertional Palpitations', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is advised strict rest and avoidance of physical or psychological workplace stress during cardiac diagnostics.', '2026-09-21', '2026-09-21 22:50:03'),
    (2, 'MED-CERT-2026-0002', 10, 11, 'Fit to Work', 'Stable Coronary Artery Disease (Post-Rehab)', NULL, '2026-09-21', '2027-09-21', 365, 'Patient has been clinically re-evaluated and cleared for normal sedentary office employment duties.', '2026-09-21', '2026-09-21 22:50:03'),
    (3, 'MED-CERT-2026-0003', 3, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (4, 'MED-CERT-2026-0004', 4, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (5, 'MED-CERT-2026-0005', 5, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (6, 'MED-CERT-2026-0006', 6, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (7, 'MED-CERT-2026-0007', 7, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (8, 'MED-CERT-2026-0008', 8, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (9, 'MED-CERT-2026-0009', 9, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (10, 'MED-CERT-2026-0010', 10, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (11, 'MED-CERT-2026-0011', 11, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (12, 'MED-CERT-2026-0012', 12, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (13, 'MED-CERT-2026-0013', 13, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (14, 'MED-CERT-2026-0014', 14, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (15, 'MED-CERT-2026-0015', 15, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (16, 'MED-CERT-2026-0016', 16, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (17, 'MED-CERT-2026-0017', 17, 5, 'Fit to School', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (18, 'MED-CERT-2026-0018', 18, 5, 'Medical Leave', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (19, 'MED-CERT-2026-0019', 19, 5, 'General Medical Certificate', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04'),
    (20, 'MED-CERT-2026-0020', 20, 5, 'Fit to Work', 'Acute Upper Respiratory Tract Infection (Recovered)', NULL, '2026-09-21', '2026-09-24', 3, 'Patient is physically fit to resume normal duties.', '2026-09-21', '2026-09-21 22:50:04')
ON CONFLICT DO NOTHING;

-- Data for: "referrals" (20 records)
INSERT INTO "referrals" ("ReferralID", "ReferralCode", "PatientID", "ReferringDoctorID", "TargetSpecialtyID", "TargetDoctorID", "Reason", "ClinicalSummary", "Priority", "Status", "ResponseNotes", "CreatedAt", "UpdatedAt") VALUES
    (1, 'REF-2026-0001', 3, 11, 3, 12, 'Patient with palpitations also exhibits bilateral tension headaches and lightheadedness. Requesting comprehensive neurological review.', 'Patient has mild BP elevation and occasional dizziness during exertion. Normal S1/S2. Need to rule out neuro-vascular etiology.', 'Routine', 'Pending', NULL, '2026-09-21 22:50:03', NULL),
    (2, 'REF-2026-0002', 2, 12, 2, 11, 'Severe migraine accompanied by transient chest racing during acute pain episodes. Cardiovascular clearance requested.', '29 yo female with recurrent migraines. Episodes accompanied by sinus tachycardia.', 'Routine', 'Accepted', 'Scheduled for cardio assessment on next clinic day.', '2026-09-21 22:50:03', NULL),
    (3, 'REF-2026-0003', 3, 5, 4, 5, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Declined', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (4, 'REF-2026-0004', 4, 5, 5, 6, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Pending', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (5, 'REF-2026-0005', 5, 5, 6, 7, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Accepted', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (6, 'REF-2026-0006', 6, 5, 7, 8, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Completed', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (7, 'REF-2026-0007', 7, 5, 8, 9, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Declined', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (8, 'REF-2026-0008', 8, 5, 9, 10, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Pending', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (9, 'REF-2026-0009', 9, 5, 10, 1, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Accepted', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (10, 'REF-2026-0010', 10, 5, 11, 2, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Completed', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (11, 'REF-2026-0011', 11, 5, 12, 3, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Declined', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (12, 'REF-2026-0012', 12, 5, 13, 4, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Pending', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (13, 'REF-2026-0013', 13, 5, 14, 5, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Accepted', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (14, 'REF-2026-0014', 14, 5, 15, 6, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Completed', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (15, 'REF-2026-0015', 15, 5, 1, 7, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Declined', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (16, 'REF-2026-0016', 16, 5, 2, 8, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Pending', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (17, 'REF-2026-0017', 17, 5, 3, 9, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Accepted', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (18, 'REF-2026-0018', 18, 5, 4, 10, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Routine', 'Completed', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (19, 'REF-2026-0019', 19, 5, 5, 1, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Urgent', 'Declined', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL),
    (20, 'REF-2026-0020', 20, 5, 6, 2, 'Specialist evaluation and management of secondary clinical concerns.', 'Patient is hemodynamically stable with good baseline parameters.', 'Emergency', 'Pending', 'Consultation scheduled with recipient clinical division.', '2026-09-21 22:50:04', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "test_catalog" (20 records)
INSERT INTO "test_catalog" ("CatalogID", "TestCode", "TestName", "Category", "SpecimenType", "TurnaroundTime", "StandardPrice", "Status") VALUES
    (1, 'LAB-CBC', 'Complete Blood Count (CBC) with Platelet', 'Hematology', 'EDTA Whole Blood', '1ÔÇô2 Hours', 280.00, 'Active'),
    (2, 'LAB-LIP', 'Lipid Profile Panel (Total Chol, HDL, LDL, Trig)', 'Clinical Chemistry', 'Serum (Fasting 10-12h)', '3ÔÇô4 Hours', 650.00, 'Active'),
    (3, 'LAB-FBS', 'Fasting Blood Sugar (FBS)', 'Clinical Chemistry', 'Fluoride Oxalate / Serum', '1ÔÇô2 Hours', 180.00, 'Active'),
    (4, 'LAB-URN', 'Routine Urinalysis', 'Urinalysis', 'Midstream Clean Catch Urine', '1 Hour', 150.00, 'Active'),
    (5, 'LAB-KID', 'Kidney Function Test (BUN, Creatinine)', 'Clinical Chemistry', 'Serum', '2ÔÇô3 Hours', 420.00, 'Active'),
    (6, 'LAB-LIV', 'Liver Function Panel (SGOT, SGPT)', 'Clinical Chemistry', 'Serum', '2ÔÇô3 Hours', 550.00, 'Active'),
    (7, 'LAB-HBA1C', 'Glycated Hemoglobin (HbA1c)', 'Clinical Chemistry', 'EDTA Whole Blood', '2ÔÇô3 Hours', 680.00, 'Active'),
    (8, 'LAB-URIC', 'Serum Uric Acid', 'Clinical Chemistry', 'Serum', '2 Hours', 220.00, 'Active'),
    (9, 'LAB-FEC', 'Routine Fecalysis', 'Microbiology', 'Fresh Stool Sample', '1 Hour', 120.00, 'Active'),
    (10, 'LAB-ELECT', 'Serum Electrolytes (Na, K, Cl)', 'Clinical Chemistry', 'Serum', '2ÔÇô3 Hours', 480.00, 'Active'),
    (11, 'LAB-BLDGRP', 'Blood Typing (ABO & Rh Factor)', 'Blood Banking', 'EDTA Whole Blood', '1 Hour', 200.00, 'Active'),
    (12, 'LAB-DENGUE', 'Dengue NS1 Antigen & Duo (IgG/IgM)', 'Serology', 'Serum', '1 Hour', 850.00, 'Active'),
    (13, 'LAB-HBSAG', 'Hepatitis B Surface Antigen (HBsAg)', 'Serology', 'Serum', '1ÔÇô2 Hours', 300.00, 'Active'),
    (14, 'LAB-PTINR', 'Prothrombin Time / INR', 'Hematology', 'Sodium Citrate Whole Blood', '2 Hours', 380.00, 'Active'),
    (15, 'LAB-PREG', 'Urine Pregnancy Test (hCG)', 'Urinalysis', 'First Morning Urine', '30 Mins', 150.00, 'Active'),
    (16, 'LAB-TSH', 'Thyroid Stimulating Hormone (TSH)', 'Immunology', 'Serum', '4ÔÇô6 Hours', 750.00, 'Active'),
    (17, 'LAB-FT4', 'Free Thyroxine (FT4)', 'Immunology', 'Serum', '4ÔÇô6 Hours', 700.00, 'Active'),
    (18, 'LAB-GRAM', 'Gram Staining Examination', 'Microbiology', 'Swab / Exudate', '2 Hours', 200.00, 'Active'),
    (19, 'LAB-AFB', 'Acid-Fast Bacilli (AFB) Sputum Smear', 'Microbiology', 'Deep Productive Sputum', '24 Hours', 250.00, 'Active'),
    (20, 'LAB-COVID', 'COVID-19 Rapid Antigen Screening', 'Serology', 'Nasopharyngeal Swab', '30 Mins', 500.00, 'Active')
ON CONFLICT DO NOTHING;

-- Data for: "laboratory_requests" (30 records)
INSERT INTO "laboratory_requests" ("RequestID", "RequestCode", "PatientID", "DoctorID", "AppointmentID", "TestType", "Priority", "ClinicalNotes", "Status", "RequestedDate", "CreatedAt") VALUES
    (1, 'LAB-REQ-2026-0001', 3, 11, 101, '12-Lead Electrocardiogram (ECG)', 'Urgent', 'Evaluate rhythm flutter, rule out supraventricular tachycardia or ischemic changes.', 'In Progress', '2026-09-21', '2026-09-21 22:50:03'),
    (2, 'LAB-REQ-2026-0002', 3, 11, 101, 'Lipid Profile & Fasting Blood Sugar', 'Routine', 'Baseline cardiovascular lipid risk assessment (Total Chol, HDL, LDL, Triglycerides).', 'Pending', '2026-09-21', '2026-09-21 22:50:03'),
    (3, 'LAB-REQ-2026-0003', 10, 11, 104, 'Complete Blood Count (CBC)', 'Routine', 'Annual routine hematology audit.', 'Completed', '2026-09-20', '2026-09-21 22:50:03'),
    (4, 'LAB-REQ-2026-0004', 2, 12, 105, 'Brain MRI (without contrast)', 'Urgent', 'Rule out structural vascular lesions or intracranial hypertension.', 'Pending', '2026-09-21', '2026-09-21 22:50:03'),
    (5, 'LAB-REQ-2026-0005', 5, 5, 5, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', '2026-09-21', '2026-09-21 22:50:04'),
    (6, 'LAB-REQ-2026-0006', 6, 5, 6, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', '2026-09-21', '2026-09-21 22:50:04'),
    (7, 'LAB-REQ-2026-0007', 7, 5, 7, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', '2026-09-21', '2026-09-21 22:50:04'),
    (8, 'LAB-REQ-2026-0008', 8, 5, 8, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (9, 'LAB-REQ-2026-0009', 9, 5, 9, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (10, 'LAB-REQ-2026-0010', 10, 5, 10, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', '2026-09-21', '2026-09-21 22:50:04'),
    (11, 'LAB-REQ-2026-0011', 11, 5, 11, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', '2026-09-21', '2026-09-21 22:50:04'),
    (12, 'LAB-REQ-2026-0012', 12, 5, 12, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', '2026-09-21', '2026-09-21 22:50:04'),
    (13, 'LAB-REQ-2026-0013', 13, 5, 13, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (14, 'LAB-REQ-2026-0014', 14, 5, 14, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (15, 'LAB-REQ-2026-0015', 15, 5, 15, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', '2026-09-21', '2026-09-21 22:50:04'),
    (16, 'LAB-REQ-2026-0016', 16, 5, 16, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', '2026-09-21', '2026-09-21 22:50:04'),
    (17, 'LAB-REQ-2026-0017', 17, 5, 17, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', '2026-09-21', '2026-09-21 22:50:04'),
    (18, 'LAB-REQ-2026-0018', 18, 5, 18, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (19, 'LAB-REQ-2026-0019', 19, 5, 19, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (20, 'LAB-REQ-2026-0020', 20, 5, 20, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', '2026-09-21', '2026-09-21 22:50:04'),
    (21, 'LAB-REQ-2026-0021', 21, 5, 21, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Sample Collected', '2026-09-21', '2026-09-21 22:50:04'),
    (22, 'LAB-REQ-2026-0022', 22, 5, 22, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'In Progress', '2026-09-21', '2026-09-21 22:50:04'),
    (23, 'LAB-REQ-2026-0023', 23, 5, 23, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (24, 'LAB-REQ-2026-0024', 24, 5, 24, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (25, 'LAB-REQ-2026-0025', 25, 5, 25, 'Complete Blood Count (CBC)', 'Routine', 'Diagnostic workup for hypertension/diabetes', 'Pending', '2026-09-21', '2026-09-21 22:50:04'),
    (26, 'LAB-6912', 40, 11, NULL, 'Complete Blood Count (CBC)', 'Routine', 'Evaluate for leukocytosis', 'Completed', '2026-09-21', '2026-09-21 16:41:09'),
    (27, 'LAB-9944', 41, 11, NULL, 'Complete Blood Count (CBC)', 'Routine', 'Evaluate for leukocytosis', 'Completed', '2026-09-21', '2026-09-21 16:41:25'),
    (28, 'LAB-9779', 42, 11, NULL, 'Complete Blood Count (CBC)', 'Routine', 'Evaluate for leukocytosis', 'Completed', '2026-09-21', '2026-09-21 16:41:52'),
    (29, 'LAB-7492', 43, 11, NULL, 'Complete Blood Count (CBC)', 'Routine', 'Evaluate for leukocytosis', 'Completed', '2026-09-21', '2026-09-21 16:42:21'),
    (30, 'LAB-5413', 44, 11, NULL, 'Complete Blood Count (CBC)', 'Routine', 'Evaluate for leukocytosis', 'Completed', '2026-09-21', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "laboratory_samples" (25 records)
INSERT INTO "laboratory_samples" ("SampleID", "SampleBarcode", "RequestID", "PatientID", "SpecimenType", "CollectionDate", "CollectedBy", "ProcessingStatus", "StorageLocation", "Notes") VALUES
    (1, 'SMP-2026-0001', 1, 1, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-2', 'Sample intact and unhemolyzed.'),
    (2, 'SMP-2026-0002', 2, 2, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-3', 'Sample intact and unhemolyzed.'),
    (3, 'SMP-2026-0003', 3, 3, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-4', 'Sample intact and unhemolyzed.'),
    (4, 'SMP-2026-0004', 4, 4, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-5', 'Sample intact and unhemolyzed.'),
    (5, 'SMP-2026-0005', 5, 5, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-6', 'Sample intact and unhemolyzed.'),
    (6, 'SMP-2026-0006', 6, 6, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-7', 'Sample intact and unhemolyzed.'),
    (7, 'SMP-2026-0007', 7, 7, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-8', 'Sample intact and unhemolyzed.'),
    (8, 'SMP-2026-0008', 8, 8, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-9', 'Sample intact and unhemolyzed.'),
    (9, 'SMP-2026-0009', 9, 9, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-10', 'Sample intact and unhemolyzed.'),
    (10, 'SMP-2026-0010', 10, 10, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-1', 'Sample intact and unhemolyzed.'),
    (11, 'SMP-2026-0011', 11, 11, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-2', 'Sample intact and unhemolyzed.'),
    (12, 'SMP-2026-0012', 12, 12, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-3', 'Sample intact and unhemolyzed.'),
    (13, 'SMP-2026-0013', 13, 13, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-4', 'Sample intact and unhemolyzed.'),
    (14, 'SMP-2026-0014', 14, 14, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-5', 'Sample intact and unhemolyzed.'),
    (15, 'SMP-2026-0015', 15, 15, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-6', 'Sample intact and unhemolyzed.'),
    (16, 'SMP-2026-0016', 16, 16, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-7', 'Sample intact and unhemolyzed.'),
    (17, 'SMP-2026-0017', 17, 17, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-8', 'Sample intact and unhemolyzed.'),
    (18, 'SMP-2026-0018', 18, 18, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-9', 'Sample intact and unhemolyzed.'),
    (19, 'SMP-2026-0019', 19, 19, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-10', 'Sample intact and unhemolyzed.'),
    (20, 'SMP-2026-0020', 20, 20, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-1', 'Sample intact and unhemolyzed.'),
    (21, 'SMP-2026-0021', 21, 21, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'In Lab', 'Rack Bay A-2', 'Sample intact and unhemolyzed.'),
    (22, 'SMP-2026-0022', 22, 22, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Processing', 'Rack Bay A-3', 'Sample intact and unhemolyzed.'),
    (23, 'SMP-2026-0023', 23, 23, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-4', 'Sample intact and unhemolyzed.'),
    (24, 'SMP-2026-0024', 24, 24, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Analyzed', 'Rack Bay A-5', 'Sample intact and unhemolyzed.'),
    (25, 'SMP-2026-0025', 25, 25, 'Whole Blood / Serum', '2026-09-21 22:50:04', 'Clarisse Mae Santos, RMT', 'Collected', 'Rack Bay A-6', 'Sample intact and unhemolyzed.')
ON CONFLICT DO NOTHING;

-- Data for: "laboratory_results" (29 records)
INSERT INTO "laboratory_results" ("ResultID", "RequestID", "PatientID", "DoctorID", "TestName", "ResultValue", "NormalRange", "Units", "Interpretation", "Notes", "AttachmentPath", "ResultDate", "CreatedAt") VALUES
    (1, 3, 10, 11, 'Complete Blood Count (CBC)', 14.8, '13.5 - 17.5', 'g/dL', 'Normal', 'Adequate oxygen-carrying capacity.', NULL, '2026-09-20', '2026-09-21 22:50:03'),
    (2, 3, 10, 11, 'Complete Blood Count (CBC)', 6.4, '4.5 - 11.0', 'x10^3/uL', 'Normal', 'No signs of acute leukocytosis or active infection.', NULL, '2026-09-20', '2026-09-21 22:50:03'),
    (3, 3, 10, 11, 'Complete Blood Count (CBC)', 245, '150 - 450', 'x10^3/uL', 'Normal', 'Normal coagulation reserve.', NULL, '2026-09-20', '2026-09-21 22:50:03'),
    (4, 4, 4, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (5, 5, 5, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (6, 6, 6, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (7, 7, 7, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (8, 8, 8, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (9, 9, 9, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (10, 10, 10, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (11, 11, 11, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (12, 12, 12, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (13, 13, 13, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (14, 14, 14, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (15, 15, 15, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (16, 16, 16, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (17, 17, 17, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (18, 18, 18, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (19, 19, 19, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (20, 20, 20, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (21, 21, 21, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (22, 22, 22, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (23, 23, 23, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (24, 24, 24, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (25, 25, 25, 5, 'Complete Blood Count (CBC)', 'WBC: 6.8 x10^9/L, Hemoglobin: 14.2 g/dL, Platelets: 240 x10^9/L', 'Hemoglobin 12.0-16.0 g/dL', 'g/dL', 'Normal', 'All hematology parameters within expected physiological limits.', NULL, '2026-09-21', '2026-09-21 22:50:04'),
    (26, 27, 41, 11, 'Hemoglobin & WBC', 'WBC: 8.5 x10^9/L, Hgb: 14.5 g/dL', 'WBC: 4.5-11.0, Hgb: 13.5-17.5', 'Standard clinical units', 'Normal', NULL, NULL, '2026-09-21', '2026-09-21 16:41:25'),
    (27, 28, 42, 11, 'Hemoglobin & WBC', 'WBC: 8.5 x10^9/L, Hgb: 14.5 g/dL', 'WBC: 4.5-11.0, Hgb: 13.5-17.5', 'Standard clinical units', 'Normal', NULL, NULL, '2026-09-21', '2026-09-21 16:41:52'),
    (28, 29, 43, 11, 'Hemoglobin & WBC', 'WBC: 8.5 x10^9/L, Hgb: 14.5 g/dL', 'WBC: 4.5-11.0, Hgb: 13.5-17.5', 'Standard clinical units', 'Normal', NULL, NULL, '2026-09-21', '2026-09-21 16:42:21'),
    (29, 30, 44, 11, 'Hemoglobin & WBC', 'WBC: 8.5 x10^9/L, Hgb: 14.5 g/dL', 'WBC: 4.5-11.0, Hgb: 13.5-17.5', 'Standard clinical units', 'Normal', NULL, NULL, '2026-09-21', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "pharmacy_inventory" (26 records)
INSERT INTO "pharmacy_inventory" ("InventoryID", "ItemCode", "GenericName", "BrandName", "DosageForm", "Strength", "Category", "UnitCost", "SellingPrice", "CurrentStock", "ReorderLevel", "BatchNumber", "ExpiryDate", "Supplier", "Status", "UpdatedAt") VALUES
    (1, 'MED-AMOX-500', 'Amoxicillin Trihydrate', 'Amoxil', 'Capsule', '500mg', 'Antibiotic', 4.50, 7.50, 450, 100, 'B-2026-081', '2028-03-14', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (2, 'MED-METO-50', 'Metoprolol Tartrate', 'Betaloc', 'Tablet', '50mg', 'Antihypertensive', 5.20, 9.00, 280, 50, 'B-2026-042', '2028-06-02', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (3, 'MED-AMLO-5', 'Amlodipine Besylate', 'Norvasc', 'Tablet', '5mg', 'Antihypertensive', 3.80, 6.50, 320, 60, 'B-2026-019', '2028-01-14', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (4, 'MED-ATOR-40', 'Atorvastatin Calcium', 'Lipitor', 'Tablet', '40mg', 'Cardiovascular / Statin', 12.00, 18.50, 190, 40, 'B-2026-092', '2027-10-26', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (5, 'MED-LOSA-50', 'Losartan Potassium', 'Cozaar', 'Tablet', '50mg', 'Antihypertensive', 6.00, 10.00, 310, 50, 'B-2026-033', '2028-02-03', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (6, 'MED-METF-500', 'Metformin Hydrochloride', 'Glucophage', 'Tablet', '500mg', 'Antidiabetic', 2.50, 4.50, 500, 100, 'B-2026-055', '2028-08-21', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (7, 'MED-PARA-500', 'Paracetamol', 'Biogesic', 'Tablet', '500mg', 'Analgesic / Antipyretic', 1.80, 3.50, 40, 50, 'B-2026-012', '2027-09-16', 'DOH Central Depot / Mercury Drug Wholesale', 'Low Stock', NULL),
    (8, 'MED-OMEP-20', 'Omeprazole', 'Losec', 'Capsule', '20mg', 'Gastrointestinal', 6.50, 11.00, 220, 50, 'B-2026-015', '2027-12-15', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (9, 'MED-CETA-10', 'Cetirizine Hydrochloride', 'Virlix', 'Tablet', '10mg', 'Antihistamine', 4.00, 7.00, 180, 40, 'B-2026-022', '2028-02-23', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (10, 'MED-IBUP-400', 'Ibuprofen', 'Advil', 'Softgel', '400mg', 'NSAID / Analgesic', 5.00, 8.50, 260, 50, 'B-2026-034', '2027-11-05', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (11, 'MED-SALB-2', 'Salbutamol Sulfate', 'Ventolin', 'Syrup', '2mg/5ml', 'Respiratory / Bronchodilator', 45.00, 68.00, 75, 20, 'B-2026-077', '2027-10-06', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (12, 'MED-CIPR-500', 'Ciprofloxacin HCl', 'Ciprobay', 'Tablet', '500mg', 'Antibiotic / Fluoroquinolone', 8.50, 14.00, 140, 30, 'B-2026-061', '2028-05-13', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (13, 'MED-AZIT-500', 'Azithromycin Dihydrate', 'Zithromax', 'Tablet', '500mg', 'Macrolide Antibiotic', 28.00, 45.00, 90, 25, 'B-2026-088', '2028-01-24', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (14, 'MED-CO-AMOX', 'Co-Amoxiclav', 'Augmentin', 'Tablet', '625mg', 'Antibiotic', 22.00, 36.00, 110, 30, 'B-2026-104', '2028-02-13', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (15, 'MED-TRAN-500', 'Tranexamic Acid', 'Hemostan', 'Capsule', '500mg', 'Hemostatic Agent', 14.00, 22.00, 85, 20, 'B-2026-044', '2027-11-25', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (16, 'MED-MEFE-500', 'Mefenamic Acid', 'Ponstan', 'Capsule', '500mg', 'NSAID / Analgesic', 4.20, 7.50, 340, 60, 'B-2026-059', '2028-04-13', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (17, 'MED-HYDR-25', 'Hydrochlorothiazide', 'HCTZ', 'Tablet', '25mg', 'Diuretic', 3.00, 5.50, 210, 40, 'B-2026-018', '2028-07-02', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (18, 'MED-ASPI-80', 'Aspirin (Low Dose)', 'Aspilets', 'Enteric Tablet', '80mg', 'Antiplatelet', 2.00, 3.80, 400, 80, 'B-2026-009', '2028-09-10', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (19, 'MED-DICY-10', 'Dicycloverine HCl', 'Relestal', 'Tablet', '10mg', 'Antispasmodic', 3.50, 6.00, 160, 30, 'B-2026-027', '2027-12-25', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (20, 'MED-LORA-10', 'Loratadine', 'Claritin', 'Tablet', '10mg', 'Antihistamine', 7.50, 12.00, 195, 40, 'B-2026-067', '2028-03-14', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (21, 'MED-ALUM-MAG', 'Aluminum Hydroxide / Magnesium', 'Kremil-S', 'Chewable Tablet', 'Standard', 'Antacid', 2.80, 5.00, 380, 70, 'B-2026-031', '2028-01-14', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (22, 'MED-DOLO-NEU', 'Vitamin B-Complex + Paracetamol', 'Dolo-Neurobion', 'Tablet', 'Standard', 'Analgesic / Neurotropic', 11.00, 18.00, 150, 35, 'B-2026-051', '2028-02-03', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (23, 'MED-CLOP-75', 'Clopidogrel Bisulfate', 'Plavix', 'Tablet', '75mg', 'Antiplatelet', 15.00, 24.00, 120, 30, 'B-2026-083', '2027-11-15', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (24, 'MED-INS-HUM', 'Human Regular Insulin', 'Humulin R', 'Vial 10ml', '100 IU/ml', 'Antidiabetic Insulin', 350.00, 480.00, 35, 10, 'B-2026-099', '2027-07-18', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (25, 'MED-MULTIVIT', 'Multivitamins + Iron', 'Iberet', 'Tablet', 'Standard', 'Nutritional Supplement', 6.00, 10.00, 500, 100, 'B-2026-011', '2028-08-01', 'DOH Central Depot / Mercury Drug Wholesale', 'In Stock', NULL),
    (30, 'MED-026', 'AuditMed5183', 'AuditBrand', 'Capsule', '250mg', 'Analgesics', 5.00, 0.00, 100, 20, 'B-2026-071', '2028-09-21', 'Hospital Formulary Stock', 'Active', '2026-09-21 18:09:10')
ON CONFLICT DO NOTHING;

-- Data for: "prescriptions" (30 records)
INSERT INTO "prescriptions" ("PrescriptionID", "PrescriptionCode", "PatientID", "DoctorID", "AppointmentID", "MedicineName", "Dosage", "Frequency", "Duration", "Instructions", "Quantity", "Refills", "Status", "IssuedDate", "CreatedAt") VALUES
    (1, 'RX-2026-0001', 3, 11, 101, 'Metoprolol Tartrate', '25mg', 'Twice daily (Every 12 hours)', '30 Days', 'Take with or immediately following a meal. Monitor resting heart rate.', '60 Tablets', 1, 'Active', '2026-09-21', '2026-09-21 22:50:03'),
    (2, 'RX-2026-0002', 3, 11, 101, 'Amlodipine Besylate', '5mg', 'Once daily (Every 24 hours)', '30 Days', 'Take in the morning with a full glass of water.', '30 Tablets', 2, 'Active', '2026-09-21', '2026-09-21 22:50:03'),
    (3, 'RX-2026-0003', 10, 11, 104, 'Atorvastatin Calcium', '40mg', 'Once daily at bedtime', '90 Days', 'Take in the evening. Avoid grapefruit juice.', '90 Tablets', 3, 'Active', '2026-09-21', '2026-09-21 22:50:03'),
    (4, 'RX-2026-0004', 2, 12, 105, 'Sumatriptan Succinate', '50mg', 'At onset of migraine attack', 'As needed (PRN)', 'Take 1 tablet at initial onset. May repeat once after 2 hours if headache persists. Max 200mg/24h.', '6 Tablets', 1, 'Active', '2026-09-21', '2026-09-21 22:50:03'),
    (5, 'RX-2026-0005', 5, 5, 5, 'Cozaar (Losartan Potassium)', '50mg', 'Once daily in morning', '30 days', 'Maintain blood pressure journal.', '30 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (6, 'RX-2026-0006', 6, 5, 6, 'Betaloc (Metoprolol Tartrate)', '50mg', 'Once daily', '30 days', 'Do not abruptly discontinue.', '30 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (7, 'RX-2026-0007', 7, 5, 7, 'Lipitor (Atorvastatin Calcium)', '40mg', 'Once daily at bedtime', '30 days', 'Avoid grapefruit juice.', '30 Tablets', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (8, 'RX-2026-0008', 8, 5, 8, 'Ventolin (Salbutamol Sulfate)', '2mg/5ml', 'Every 8 hours PRN', '7 days', 'Take when wheezing or dyspneic.', '1 Bottle', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (9, 'RX-2026-0009', 9, 5, 9, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Cancelled', '2026-09-21', '2026-09-21 22:50:04'),
    (10, 'RX-2026-0010', 10, 5, 10, 'Norvasc (Amlodipine Besylate)', '5mg', 'Once daily in morning', '30 days', 'Monitor morning blood pressure daily.', '30 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (11, 'RX-2026-0011', 11, 5, 11, 'Glucophage (Metformin HCl)', '500mg', 'Twice daily with meals', '30 days', 'Take with breakfast and dinner.', '60 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (12, 'RX-2026-0012', 12, 5, 12, 'Biogesic (Paracetamol)', '500mg', 'Every 4-6 hours PRN', '5 days', 'Take for pain or fever greater than 38C.', '15 Tablets', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (13, 'RX-2026-0013', 13, 5, 13, 'Cozaar (Losartan Potassium)', '50mg', 'Once daily in morning', '30 days', 'Maintain blood pressure journal.', '30 Tablets', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (14, 'RX-2026-0014', 14, 5, 14, 'Betaloc (Metoprolol Tartrate)', '50mg', 'Once daily', '30 days', 'Do not abruptly discontinue.', '30 Tablets', 0, 'Cancelled', '2026-09-21', '2026-09-21 22:50:04'),
    (15, 'RX-2026-0015', 15, 5, 15, 'Lipitor (Atorvastatin Calcium)', '40mg', 'Once daily at bedtime', '30 days', 'Avoid grapefruit juice.', '30 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (16, 'RX-2026-0016', 16, 5, 16, 'Ventolin (Salbutamol Sulfate)', '2mg/5ml', 'Every 8 hours PRN', '7 days', 'Take when wheezing or dyspneic.', '1 Bottle', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (17, 'RX-2026-0017', 17, 5, 17, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (18, 'RX-2026-0018', 18, 5, 18, 'Norvasc (Amlodipine Besylate)', '5mg', 'Once daily in morning', '30 days', 'Monitor morning blood pressure daily.', '30 Tablets', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (19, 'RX-2026-0019', 19, 5, 19, 'Glucophage (Metformin HCl)', '500mg', 'Twice daily with meals', '30 days', 'Take with breakfast and dinner.', '60 Tablets', 0, 'Cancelled', '2026-09-21', '2026-09-21 22:50:04'),
    (20, 'RX-2026-0020', 20, 5, 20, 'Biogesic (Paracetamol)', '500mg', 'Every 4-6 hours PRN', '5 days', 'Take for pain or fever greater than 38C.', '15 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (21, 'RX-2026-0021', 21, 5, 21, 'Cozaar (Losartan Potassium)', '50mg', 'Once daily in morning', '30 days', 'Maintain blood pressure journal.', '30 Tablets', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (22, 'RX-2026-0022', 22, 5, 22, 'Betaloc (Metoprolol Tartrate)', '50mg', 'Once daily', '30 days', 'Do not abruptly discontinue.', '30 Tablets', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (23, 'RX-2026-0023', 23, 5, 23, 'Lipitor (Atorvastatin Calcium)', '40mg', 'Once daily at bedtime', '30 days', 'Avoid grapefruit juice.', '30 Tablets', 0, 'Completed', '2026-09-21', '2026-09-21 22:50:04'),
    (24, 'RX-2026-0024', 24, 5, 24, 'Ventolin (Salbutamol Sulfate)', '2mg/5ml', 'Every 8 hours PRN', '7 days', 'Take when wheezing or dyspneic.', '1 Bottle', 0, 'Cancelled', '2026-09-21', '2026-09-21 22:50:04'),
    (25, 'RX-2026-0025', 25, 5, 25, 'Amoxil (Amoxicillin)', '500mg', 'Every 8 hours', '7 days', 'Take after meals. Complete full antibiotic course.', '21 Capsules', 0, 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (26, 'RX-4652', 40, 11, NULL, 'Amoxicillin 500mg Capsule', '500mg', 'Three times a day (TID)', '7 days', 'Take after meals', 21, 0, 'Active', '2026-09-21', '2026-09-21 16:41:09'),
    (27, 'RX-8277', 41, 11, NULL, 'Amoxicillin 500mg Capsule', '500mg', 'Three times a day (TID)', '7 days', 'Take after meals', 21, 0, 'Completed', '2026-09-21', '2026-09-21 16:41:25'),
    (28, 'RX-3530', 42, 11, NULL, 'Amoxicillin 500mg Capsule', '500mg', 'Three times a day (TID)', '7 days', 'Take after meals', 21, 0, 'Completed', '2026-09-21', '2026-09-21 16:41:52'),
    (29, 'RX-9618', 43, 11, NULL, 'Amoxicillin 500mg Capsule', '500mg', 'Three times a day (TID)', '7 days', 'Take after meals', 21, 0, 'Completed', '2026-09-21', '2026-09-21 16:42:21'),
    (30, 'RX-1089', 44, 11, NULL, 'Amoxicillin 500mg Capsule', '500mg', 'Three times a day (TID)', '7 days', 'Take after meals', 21, 0, 'Completed', '2026-09-21', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "dispensing_records" (29 records)
INSERT INTO "dispensing_records" ("DispenseID", "DispenseCode", "PrescriptionID", "PatientID", "DispensedBy", "DispenserName", "QuantityDispensed", "DosageInstructions", "BatchNumber", "DispenseDate", "Status", "Notes") VALUES
    (1, 'DISP-2026-0001', 1, 1, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-011', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (2, 'DISP-2026-0002', 2, 2, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-012', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (3, 'DISP-2026-0003', 3, 3, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-013', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (4, 'DISP-2026-0004', 4, 4, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-014', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (5, 'DISP-2026-0005', 5, 5, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-015', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (6, 'DISP-2026-0006', 6, 6, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-016', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (7, 'DISP-2026-0007', 7, 7, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-017', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (8, 'DISP-2026-0008', 8, 8, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-018', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (9, 'DISP-2026-0009', 9, 9, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-019', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (10, 'DISP-2026-0010', 10, 10, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-020', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (11, 'DISP-2026-0011', 11, 11, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-021', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (12, 'DISP-2026-0012', 12, 12, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-022', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (13, 'DISP-2026-0013', 13, 13, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-023', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (14, 'DISP-2026-0014', 14, 14, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-024', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (15, 'DISP-2026-0015', 15, 15, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-025', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (16, 'DISP-2026-0016', 16, 16, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-026', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (17, 'DISP-2026-0017', 17, 17, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-027', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (18, 'DISP-2026-0018', 18, 18, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-028', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (19, 'DISP-2026-0019', 19, 19, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-029', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (20, 'DISP-2026-0020', 20, 20, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-030', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (21, 'DISP-2026-0021', 21, 21, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-031', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (22, 'DISP-2026-0022', 22, 22, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-032', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (23, 'DISP-2026-0023', 23, 23, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-033', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (24, 'DISP-2026-0024', 24, 24, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-034', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (25, 'DISP-2026-0025', 25, 25, 8, 'Kareen Joy Ramos, RPh', '21 Tabs', 'Take exactly as labeled on container.', 'B-2026-035', '2026-09-21 22:50:04', 'Dispensed', 'Verified against original electronic prescription.'),
    (26, 'DSP-6684', 27, 41, 6, 'Hospital Pharmacist', 21, 'Take 1 capsule every 8 hours for 7 days', 'BATCH-AMX-2026-01', '2026-09-21 16:41:25', 'Dispensed', NULL),
    (27, 'DSP-8761', 28, 42, 6, 'Hospital Pharmacist', 21, 'Take 1 capsule every 8 hours for 7 days', 'BATCH-AMX-2026-01', '2026-09-21 16:41:52', 'Dispensed', NULL),
    (28, 'DSP-9023', 29, 43, 6, 'Hospital Pharmacist', 21, 'Take 1 capsule every 8 hours for 7 days', 'BATCH-AMX-2026-01', '2026-09-21 16:42:21', 'Dispensed', NULL),
    (29, 'DSP-7756', 30, 44, 6, 'Hospital Pharmacist', 21, 'Take 1 capsule every 8 hours for 7 days', 'BATCH-AMX-2026-01', '2026-09-21 16:42:40', 'Dispensed', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "service_fees" (11 records)
INSERT INTO "service_fees" ("FeeID", "ServiceCode", "ServiceName", "Category", "DepartmentID", "StandardRate", "PhilHealthCoveredRate", "DiscountEligible", "Description", "Status", "CreatedAt", "UpdatedAt") VALUES
    (1, 'FEE-CON-GP', 'General Medical Consultation', 'Consultation', 2, 250.00, 250.00, TRUE, 'Standard primary care consultation', 'Active', '2026-09-21 22:50:03', NULL),
    (2, 'FEE-CON-SP', 'Specialist Consultation', 'Consultation', 3, 500.00, 400.00, TRUE, 'Consultation with medical specialist (Cardio, Pulmo, Neuro, etc.)', 'Active', '2026-09-21 22:50:03', NULL),
    (3, 'FEE-LAB-CBC', 'Complete Blood Count (CBC) with Platelet', 'Laboratory', 7, 280.00, 280.00, TRUE, 'Routine hematology screening', 'Active', '2026-09-21 22:50:03', NULL),
    (4, 'FEE-LAB-LIP', 'Lipid Profile Panel', 'Laboratory', 7, 650.00, 500.00, TRUE, 'Total cholesterol, HDL, LDL, Triglycerides', 'Active', '2026-09-21 22:50:03', NULL),
    (5, 'FEE-LAB-FBS', 'Fasting Blood Sugar (FBS)', 'Laboratory', 7, 180.00, 180.00, TRUE, 'Glucose monitoring test', 'Active', '2026-09-21 22:50:03', NULL),
    (6, 'FEE-LAB-URN', 'Urinalysis Routine Examination', 'Laboratory', 7, 150.00, 150.00, TRUE, 'Standard urine microscopy', 'Active', '2026-09-21 22:50:03', NULL),
    (7, 'FEE-RAD-XRAY', 'Chest X-Ray (PA View)', 'Radiology', 7, 450.00, 400.00, TRUE, 'Digital chest radiography', 'Active', '2026-09-21 22:50:03', NULL),
    (8, 'FEE-RAD-ECG', '12-Lead Electrocardiogram (ECG)', 'Radiology', 3, 350.00, 350.00, TRUE, 'Cardiac electrical activity recording', 'Active', '2026-09-21 22:50:03', NULL),
    (9, 'FEE-NUR-ADM', 'Ward Nursing & Accommodation (Daily)', 'Nursing/Ward', NULL, 800.00, 600.00, TRUE, 'Routine inpatient nursing care and bed fee', 'Active', '2026-09-21 22:50:03', NULL),
    (10, 'FEE-EMR-FEE', 'Emergency Room Triage & Service Fee', 'Emergency', 1, 450.00, 450.00, TRUE, 'Immediate ER stabilization and intake fee', 'Active', '2026-09-21 22:50:03', NULL),
    (11, 'FEE-MED-CERT', 'Medical Certificate Issuance Fee', 'Administrative', 10, 150.00, 0.00, TRUE, 'Official Fit to Work / School certificate', 'Active', '2026-09-21 22:50:03', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "billing_charges" (33 records)
INSERT INTO "billing_charges" ("ChargeID", "PatientID", "AppointmentID", "FeeID", "ChargeCategory", "ItemDescription", "Quantity", "UnitPrice", "SubTotal", "DiscountAmount", "NetAmount", "BillingStatus", "CreatedBy", "CreatedAt") VALUES
    (1, 3, NULL, NULL, 'Consultation', 'Laboratory Service Charge for Patient #1', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled', 'Billing Staff', '2026-09-21 22:50:04'),
    (2, 3, NULL, NULL, 'Laboratory', 'Pharmacy Service Charge for Patient #2', 1, 350.00, 350.00, 0.00, 350.00, 'Unbilled', 'Billing Staff', '2026-09-21 22:50:04'),
    (3, 3, NULL, NULL, 'Pharmacy', 'Nursing/Ward Service Charge for Patient #3', 60, 9.00, 540.00, 0.00, 540.00, 'Unbilled', 'Billing Staff', '2026-09-21 22:50:04'),
    (4, 10, NULL, NULL, 'Consultation', 'Radiology Service Charge for Patient #4', 1, 500.00, 500.00, 100.00, 400.00, 'Paid', 'Billing Staff', '2026-09-21 22:50:04'),
    (5, 2, NULL, NULL, 'Consultation', 'Consultation Service Charge for Patient #5', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled', 'Billing Staff', '2026-09-21 22:50:04'),
    (6, 6, 6, 7, 'Laboratory', 'Laboratory Service Charge for Patient #6', 1, 550.00, 550.00, 0.00, 550.00, 'Invoiced', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (7, 7, 7, 8, 'Pharmacy', 'Pharmacy Service Charge for Patient #7', 1, 600.00, 600.00, 0.00, 600.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (8, 8, 8, 9, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #8', 1, 650.00, 650.00, 0.00, 650.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (9, 9, 9, 10, 'Radiology', 'Radiology Service Charge for Patient #9', 1, 700.00, 700.00, 0.00, 700.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (10, 10, 10, 11, 'Consultation', 'Consultation Service Charge for Patient #10', 1, 750.00, 750.00, 0.00, 750.00, 'Unbilled', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (11, 11, 11, 1, 'Laboratory', 'Laboratory Service Charge for Patient #11', 1, 800.00, 800.00, 0.00, 800.00, 'Invoiced', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (12, 12, 12, 2, 'Pharmacy', 'Pharmacy Service Charge for Patient #12', 1, 250.00, 250.00, 0.00, 250.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (13, 13, 13, 3, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #13', 1, 300.00, 300.00, 0.00, 300.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (14, 14, 14, 4, 'Radiology', 'Radiology Service Charge for Patient #14', 1, 350.00, 350.00, 0.00, 350.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (15, 15, 15, 5, 'Consultation', 'Consultation Service Charge for Patient #15', 1, 400.00, 400.00, 0.00, 400.00, 'Unbilled', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (16, 16, 16, 6, 'Laboratory', 'Laboratory Service Charge for Patient #16', 1, 450.00, 450.00, 0.00, 450.00, 'Invoiced', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (17, 17, 17, 7, 'Pharmacy', 'Pharmacy Service Charge for Patient #17', 1, 500.00, 500.00, 0.00, 500.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (18, 18, 18, 8, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #18', 1, 550.00, 550.00, 0.00, 550.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (19, 19, 19, 9, 'Radiology', 'Radiology Service Charge for Patient #19', 1, 600.00, 600.00, 0.00, 600.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (20, 20, 20, 10, 'Consultation', 'Consultation Service Charge for Patient #20', 1, 650.00, 650.00, 0.00, 650.00, 'Unbilled', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (21, 21, 21, 11, 'Laboratory', 'Laboratory Service Charge for Patient #21', 1, 700.00, 700.00, 0.00, 700.00, 'Invoiced', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (22, 22, 22, 1, 'Pharmacy', 'Pharmacy Service Charge for Patient #22', 1, 750.00, 750.00, 0.00, 750.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (23, 23, 23, 2, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #23', 1, 800.00, 800.00, 0.00, 800.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (24, 24, 24, 3, 'Radiology', 'Radiology Service Charge for Patient #24', 1, 250.00, 250.00, 0.00, 250.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (25, 25, 25, 4, 'Consultation', 'Consultation Service Charge for Patient #25', 1, 300.00, 300.00, 0.00, 300.00, 'Unbilled', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (26, 26, 26, 5, 'Laboratory', 'Laboratory Service Charge for Patient #26', 1, 350.00, 350.00, 0.00, 350.00, 'Invoiced', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (27, 27, 27, 6, 'Pharmacy', 'Pharmacy Service Charge for Patient #27', 1, 400.00, 400.00, 0.00, 400.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (28, 28, 28, 7, 'Nursing/Ward', 'Nursing/Ward Service Charge for Patient #28', 1, 450.00, 450.00, 0.00, 450.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (29, 29, 29, 8, 'Radiology', 'Radiology Service Charge for Patient #29', 1, 500.00, 500.00, 0.00, 500.00, 'Paid', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (30, 30, 30, 9, 'Consultation', 'Consultation Service Charge for Patient #30', 1, 550.00, 550.00, 0.00, 550.00, 'Unbilled', 'Maria Castillo (Billing)', '2026-09-21 22:50:04'),
    (31, 42, NULL, NULL, 'Consultation', 'General Consultation + CBC Test Fee', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled', 'Billing Staff', '2026-09-21 16:41:52'),
    (32, 43, NULL, NULL, 'Consultation', 'General Consultation + CBC Test Fee', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled', 'Billing Staff', '2026-09-21 16:42:21'),
    (33, 44, NULL, NULL, 'Consultation', 'General Consultation + CBC Test Fee', 1, 500.00, 500.00, 0.00, 500.00, 'Unbilled', 'Billing Staff', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "billing_invoices" (31 records)
INSERT INTO "billing_invoices" ("InvoiceID", "InvoiceNumber", "PatientID", "GrossAmount", "DiscountType", "DiscountAmount", "PhilHealthDeduction", "TotalPayable", "AmountPaid", "BalanceDue", "PaymentStatus", "BilledBy", "CreatedAt", "DueDate") VALUES
    (1, 'INV-2026-0001', 1, 300.00, 'None', 0.00, 0.00, 300.00, 300.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (2, 'INV-2026-0002', 2, 350.00, 'None', 0.00, 0.00, 350.00, 350.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (3, 'INV-2026-0003', 3, 400.00, 'None', 0.00, 0.00, 400.00, 0.00, 400.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (4, 'INV-2026-0004', 4, 450.00, 'None', 0.00, 0.00, 450.00, 450.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (5, 'INV-2026-0005', 5, 500.00, 'None', 0.00, 0.00, 500.00, 500.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (6, 'INV-2026-0006', 6, 550.00, 'None', 0.00, 0.00, 550.00, 0.00, 550.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (7, 'INV-2026-0007', 7, 600.00, 'None', 0.00, 0.00, 600.00, 600.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (8, 'INV-2026-0008', 8, 650.00, 'None', 0.00, 0.00, 650.00, 650.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (9, 'INV-2026-0009', 9, 700.00, 'None', 0.00, 0.00, 700.00, 0.00, 700.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (10, 'INV-2026-0010', 10, 750.00, 'None', 0.00, 0.00, 750.00, 750.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (11, 'INV-2026-0011', 11, 800.00, 'None', 0.00, 0.00, 800.00, 800.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (12, 'INV-2026-0012', 12, 250.00, 'None', 0.00, 0.00, 250.00, 0.00, 250.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (13, 'INV-2026-0013', 13, 300.00, 'None', 0.00, 0.00, 300.00, 300.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (14, 'INV-2026-0014', 14, 350.00, 'None', 0.00, 0.00, 350.00, 350.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (15, 'INV-2026-0015', 15, 400.00, 'None', 0.00, 0.00, 400.00, 0.00, 400.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (16, 'INV-2026-0016', 16, 450.00, 'None', 0.00, 0.00, 450.00, 450.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (17, 'INV-2026-0017', 17, 500.00, 'None', 0.00, 0.00, 500.00, 500.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (18, 'INV-2026-0018', 18, 550.00, 'None', 0.00, 0.00, 550.00, 0.00, 550.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (19, 'INV-2026-0019', 19, 600.00, 'None', 0.00, 0.00, 600.00, 600.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (20, 'INV-2026-0020', 20, 650.00, 'None', 0.00, 0.00, 650.00, 650.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (21, 'INV-2026-0021', 21, 700.00, 'None', 0.00, 0.00, 700.00, 0.00, 700.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (22, 'INV-2026-0022', 22, 750.00, 'None', 0.00, 0.00, 750.00, 750.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (23, 'INV-2026-0023', 23, 800.00, 'None', 0.00, 0.00, 800.00, 800.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (24, 'INV-2026-0024', 24, 250.00, 'None', 0.00, 0.00, 250.00, 0.00, 250.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (25, 'INV-2026-0025', 25, 300.00, 'None', 0.00, 0.00, 300.00, 300.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (26, 'INV-2026-0026', 26, 350.00, 'None', 0.00, 0.00, 350.00, 350.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (27, 'INV-2026-0027', 27, 400.00, 'None', 0.00, 0.00, 400.00, 0.00, 400.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (28, 'INV-2026-0028', 28, 450.00, 'None', 0.00, 0.00, 450.00, 450.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (29, 'INV-2026-0029', 29, 500.00, 'None', 0.00, 0.00, 500.00, 500.00, 0.00, 'Paid In Full', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (30, 'INV-2026-0030', 30, 550.00, 'None', 0.00, 0.00, 550.00, 0.00, 550.00, 'Unpaid', 'Maria Castillo (Cashier)', '2026-09-21 22:50:04', '2026-10-05'),
    (31, 'INV-1790008960', 44, 500.00, 'None', 0.00, 0.00, 500.00, 500.00, 0.00, 'Paid In Full', 7, '2026-09-21 16:42:40', '2026-10-21')
ON CONFLICT DO NOTHING;

-- Data for: "billing_payments" (21 records)
INSERT INTO "billing_payments" ("PaymentID", "ReceiptNumber", "InvoiceID", "PatientID", "AmountPaid", "PaymentMethod", "ReferenceNumber", "AmountInWords", "CashierName", "PaymentDate") VALUES
    (1, 'OR-2026-0001', 1, 1, 300.00, 'Cash', 'CASH-REC-0001', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (2, 'OR-2026-0002', 2, 2, 350.00, 'Cash', 'CASH-REC-0002', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (4, 'OR-2026-0004', 4, 4, 450.00, 'Cash', 'CASH-REC-0004', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (5, 'OR-2026-0005', 5, 5, 500.00, 'Cash', 'CASH-REC-0005', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (7, 'OR-2026-0007', 7, 7, 600.00, 'Cash', 'CASH-REC-0007', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (8, 'OR-2026-0008', 8, 8, 650.00, 'Cash', 'CASH-REC-0008', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (10, 'OR-2026-0010', 10, 10, 750.00, 'Cash', 'CASH-REC-0010', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (11, 'OR-2026-0011', 11, 11, 800.00, 'Cash', 'CASH-REC-0011', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (13, 'OR-2026-0013', 13, 13, 300.00, 'Cash', 'CASH-REC-0013', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (14, 'OR-2026-0014', 14, 14, 350.00, 'Cash', 'CASH-REC-0014', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (16, 'OR-2026-0016', 16, 16, 450.00, 'Cash', 'CASH-REC-0016', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (17, 'OR-2026-0017', 17, 17, 500.00, 'Cash', 'CASH-REC-0017', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (19, 'OR-2026-0019', 19, 19, 600.00, 'Cash', 'CASH-REC-0019', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (20, 'OR-2026-0020', 20, 20, 650.00, 'Cash', 'CASH-REC-0020', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (22, 'OR-2026-0022', 22, 22, 750.00, 'Cash', 'CASH-REC-0022', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (23, 'OR-2026-0023', 23, 23, 800.00, 'Cash', 'CASH-REC-0023', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (25, 'OR-2026-0025', 25, 25, 300.00, 'Cash', 'CASH-REC-0025', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (26, 'OR-2026-0026', 26, 26, 350.00, 'Cash', 'CASH-REC-0026', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (28, 'OR-2026-0028', 28, 28, 450.00, 'Cash', 'CASH-REC-0028', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (29, 'OR-2026-0029', 29, 29, 500.00, 'Cash', 'CASH-REC-0029', 'Paid full balance in Philippine Pesos', 'Maria Castillo', '2026-09-21 22:50:04'),
    (30, 'OR-1790008960', 31, 44, 500.00, 'Cash', NULL, 'Five Hundred Pesos Only', 'Cashier Officer', '2026-09-21 16:42:40')
ON CONFLICT DO NOTHING;

-- Data for: "nurse_tasks" (25 records)
INSERT INTO "nurse_tasks" ("TaskID", "PatientID", "NurseID", "DoctorID", "TaskTitle", "Category", "DueTime", "Priority", "Status", "Remarks", "CreatedAt", "CompletedAt") VALUES
    (1, 1, 6, 5, 'Vital Check for Patient #PAT-2026-0001', 'Vital Check', '02:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (2, 2, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0002', 'IV Fluid Replacement', '03:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (3, 3, 6, 5, 'Wound Dressing for Patient #PAT-2026-0003', 'Wound Dressing', '04:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (4, 4, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0004', 'Pre-op Prep', '05:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (5, 5, 6, 5, 'General Care for Patient #PAT-2026-0005', 'General Care', '06:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (6, 6, 6, 5, 'Medication Admin for Patient #PAT-2026-0006', 'Medication Admin', '07:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (7, 7, 6, 5, 'Vital Check for Patient #PAT-2026-0007', 'Vital Check', '08:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (8, 8, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0008', 'IV Fluid Replacement', '09:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (9, 9, 6, 5, 'Wound Dressing for Patient #PAT-2026-0009', 'Wound Dressing', '10:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (10, 10, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0010', 'Pre-op Prep', '11:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (11, 11, 6, 5, 'General Care for Patient #PAT-2026-0011', 'General Care', '12:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (12, 12, 6, 5, 'Medication Admin for Patient #PAT-2026-0012', 'Medication Admin', '01:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (13, 13, 6, 5, 'Vital Check for Patient #PAT-2026-0013', 'Vital Check', '02:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (14, 14, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0014', 'IV Fluid Replacement', '03:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (15, 15, 6, 5, 'Wound Dressing for Patient #PAT-2026-0015', 'Wound Dressing', '04:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (16, 16, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0016', 'Pre-op Prep', '05:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (17, 17, 6, 5, 'General Care for Patient #PAT-2026-0017', 'General Care', '06:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (18, 18, 6, 5, 'Medication Admin for Patient #PAT-2026-0018', 'Medication Admin', '07:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (19, 19, 6, 5, 'Vital Check for Patient #PAT-2026-0019', 'Vital Check', '08:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (20, 20, 6, 5, 'IV Fluid Replacement for Patient #PAT-2026-0020', 'IV Fluid Replacement', '09:00 PM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (21, 21, 6, 5, 'Wound Dressing for Patient #PAT-2026-0021', 'Wound Dressing', '10:00 AM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (22, 22, 6, 5, 'Pre-op Prep for Patient #PAT-2026-0022', 'Pre-op Prep', '11:00 PM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (23, 23, 6, 5, 'General Care for Patient #PAT-2026-0023', 'General Care', '12:00 AM', 'STAT', 'Completed', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (24, 24, 6, 5, 'Medication Admin for Patient #PAT-2026-0024', 'Medication Admin', '01:00 PM', 'Normal', 'Pending', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL),
    (25, 25, 6, 5, 'Vital Check for Patient #PAT-2026-0025', 'Vital Check', '02:00 AM', 'Urgent', 'In Progress', 'Follow standard nursing safety protocol.', '2026-09-21 22:50:04', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "allergy_records" (20 records)
INSERT INTO "allergy_records" ("AllergyID", "PatientID", "DoctorID", "Allergen", "AllergyType", "Severity", "Reaction", "Status", "ConfirmedDate", "CreatedAt") VALUES
    (1, 1, 15, 'Amoxicillin / Penicillin', 'Drug', 'Moderate', 'Erythematous skin rash, pruritus, mild facial flushing', 'Active', '2026-08-15', '2026-09-21 22:50:03'),
    (2, 3, 11, 'Aspirin / NSAIDs', 'Drug', 'Severe', 'Urticarial hives, periorbital edema, mild wheezing', 'Active', '2026-08-16', '2026-09-21 22:50:03'),
    (3, 4, 17, 'Sulfa Antibiotics', 'Other', 'Moderate', 'Contact dermatitis, localized erythema and itching upon exposure', 'Active', '2026-08-15', '2026-09-21 22:50:03'),
    (4, 5, 15, 'Shrimp / Crustaceans', 'Drug', 'Severe', 'Acute epigastric burning and bronchospasm', 'Active', '2026-08-15', '2026-09-21 22:50:03'),
    (5, 5, 5, 'Natural Rubber Latex', 'Latex', 'Moderate', 'Contact dermatitis and localized pruritus', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (6, 6, 5, 'Peanuts / Tree Nuts', 'Food', 'Severe', 'Shortness of breath and generalized hives', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (7, 7, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (8, 8, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (9, 9, 5, 'Sulfa Antibiotics', 'Drug', 'Life-threatening', 'Anaphylaxis and bronchospasm', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (10, 10, 5, 'Shrimp / Crustaceans', 'Food', 'Moderate', 'Perioral swelling and nausea', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (11, 11, 5, 'Natural Rubber Latex', 'Latex', 'Moderate', 'Contact dermatitis and localized pruritus', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (12, 12, 5, 'Peanuts / Tree Nuts', 'Food', 'Severe', 'Shortness of breath and generalized hives', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (13, 13, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (14, 14, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (15, 15, 5, 'Sulfa Antibiotics', 'Drug', 'Life-threatening', 'Anaphylaxis and bronchospasm', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (16, 16, 5, 'Shrimp / Crustaceans', 'Food', 'Moderate', 'Perioral swelling and nausea', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (17, 17, 5, 'Natural Rubber Latex', 'Latex', 'Moderate', 'Contact dermatitis and localized pruritus', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (18, 18, 5, 'Peanuts / Tree Nuts', 'Food', 'Severe', 'Shortness of breath and generalized hives', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (19, 19, 5, 'Amoxicillin / Penicillin', 'Drug', 'Severe', 'Diffuse pruritic maculopapular rash and facial edema', 'Active', '2026-09-21', '2026-09-21 22:50:04'),
    (20, 20, 5, 'Aspirin / NSAIDs', 'Drug', 'Moderate', 'Epigastric burning and urticaria', 'Active', '2026-09-21', '2026-09-21 22:50:04')
ON CONFLICT DO NOTHING;

-- Data for: "record_release_requests" (2 records)
INSERT INTO "record_release_requests" ("RequestID", "RequestNumber", "PatientID", "RequestType", "RequestorName", "RelationshipToPatient", "PurposeOfRequest", "Status", "ProcessedBy", "RequestedDate", "ReleasedDate") VALUES
    (1, 'REQ-2026-001', 3, 'Clinical Summary', 'Eduardo Bautista', 'Self', 'PhilHealth Claims & Employer Sickness Benefit', 'Approved / Processing', 'Mark Anthony Valenzuela, RMT', '2026-09-21 22:50:04', NULL),
    (2, 'REQ-2026-002', 10, 'Complete Medical History', 'Angelica Morales', 'Self', 'Personal Health Record & Specialist Transfer', 'Released', 'Mark Anthony Valenzuela, RMT', '2026-09-21 22:50:04', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "hospital_info" (1 records)
INSERT INTO "hospital_info" ("HospitalInfoID", "HospitalName", "HospitalCode", "TaxID", "Address", "ContactNumber", "EmergencyHotline", "Email", "DOHAccreditation", "PhilHealthAccreditation", "BedCapacity", "MedicalDirector", "LogoPath", "UpdatedAt") VALUES
    (1, 'Tupi Municipal Hospital', 'TMH-REG-12', '004-982-114-000', 'National Highway, Poblacion, Tupi, South Cotabato 9505, Philippines', '+63 (083) 228-0001', '+63 (083) 228-0002', 'admin@tupihospital.gov.ph', 'DOH-ACCRED-L1-2026-084', 'PH-HOSP-1209384', 50, 'Dr. Maria Santos, MD, MHA', 'assets/logo.png', NULL)
ON CONFLICT DO NOTHING;

-- Data for: "system_audit_logs" (38 records)
INSERT INTO "system_audit_logs" ("LogID", "UserID", "UserName", "UserRole", "Action", "Module", "RecordID", "Details", "IPAddress", "UserAgent", "CreatedAt") VALUES
    (1, 1, 'Staff Member', 'Staff', 'UPDATE_VITALS', 'Consultations', 'REC-1', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (2, 1, 'System Administrator', 'Admin', 'ORDER_LAB_TEST', 'Laboratory', 'REC-2', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (3, 1, 'Staff Member', 'Staff', 'DISPENSE_RX', 'Pharmacy', 'REC-3', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (4, 1, 'System Administrator', 'Admin', 'PROCESS_PAYMENT', 'Billing', 'REC-4', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (5, 1, 'Staff Member', 'Staff', 'USER_LOGIN', 'User Admin', 'REC-5', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (6, 1, 'System Administrator', 'Admin', 'BACKUP_DB', 'System Config', 'REC-6', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (7, 1, 'Staff Member', 'Staff', 'CREATE_PATIENT', 'Patient Records', 'REC-7', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (8, 1, 'System Administrator', 'Admin', 'UPDATE_VITALS', 'Consultations', 'REC-8', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (9, 1, 'Staff Member', 'Staff', 'ORDER_LAB_TEST', 'Laboratory', 'REC-9', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (10, 1, 'System Administrator', 'Admin', 'DISPENSE_RX', 'Pharmacy', 'REC-10', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (11, 1, 'Staff Member', 'Staff', 'PROCESS_PAYMENT', 'Billing', 'REC-11', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (12, 1, 'System Administrator', 'Admin', 'USER_LOGIN', 'User Admin', 'REC-12', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (13, 1, 'Staff Member', 'Staff', 'BACKUP_DB', 'System Config', 'REC-13', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (14, 1, 'System Administrator', 'Admin', 'CREATE_PATIENT', 'Patient Records', 'REC-14', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (15, 1, 'Staff Member', 'Staff', 'UPDATE_VITALS', 'Consultations', 'REC-15', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (16, 1, 'System Administrator', 'Admin', 'ORDER_LAB_TEST', 'Laboratory', 'REC-16', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (17, 1, 'Staff Member', 'Staff', 'DISPENSE_RX', 'Pharmacy', 'REC-17', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (18, 1, 'System Administrator', 'Admin', 'PROCESS_PAYMENT', 'Billing', 'REC-18', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (19, 1, 'Staff Member', 'Staff', 'USER_LOGIN', 'User Admin', 'REC-19', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (20, 1, 'System Administrator', 'Admin', 'BACKUP_DB', 'System Config', 'REC-20', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (21, 1, 'Staff Member', 'Staff', 'CREATE_PATIENT', 'Patient Records', 'REC-21', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (22, 1, 'System Administrator', 'Admin', 'UPDATE_VITALS', 'Consultations', 'REC-22', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (23, 1, 'Staff Member', 'Staff', 'ORDER_LAB_TEST', 'Laboratory', 'REC-23', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (24, 1, 'System Administrator', 'Admin', 'DISPENSE_RX', 'Pharmacy', 'REC-24', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (25, 1, 'Staff Member', 'Staff', 'PROCESS_PAYMENT', 'Billing', 'REC-25', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (26, 1, 'System Administrator', 'Admin', 'USER_LOGIN', 'User Admin', 'REC-26', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (27, 1, 'Staff Member', 'Staff', 'BACKUP_DB', 'System Config', 'REC-27', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (28, 1, 'System Administrator', 'Admin', 'CREATE_PATIENT', 'Patient Records', 'REC-28', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (29, 1, 'Staff Member', 'Staff', 'UPDATE_VITALS', 'Consultations', 'REC-29', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (30, 1, 'System Administrator', 'Admin', 'ORDER_LAB_TEST', 'Laboratory', 'REC-30', 'Operational activity recorded successfully in TMHMIS audit trail.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-21 22:50:04'),
    (31, NULL, 'system', 'System', 'Toggle User Status', 'User Account Administration', 1, 'Set user ID 1 status to Active', '127.0.0.1', '', '2026-09-22 00:19:37'),
    (32, NULL, 'system', 'System', 'Delete Department', 'Department Management', 11, 'Deleted department ID: 11', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-22 00:49:10'),
    (33, 101, 'cardio', 'Doctor', 'Create User Account', 'User Account Administration', 112, 'Created user:  (Role: )', '127.0.0.1', '', '2026-09-22 01:51:00'),
    (34, 101, 'cardio', 'Doctor', 'Create User Account', 'User Account Administration', 113, 'Created user:  (Role: )', '127.0.0.1', '', '2026-09-22 01:51:20'),
    (35, 101, 'cardio', 'Doctor', 'Create User Account', 'User Account Administration', 114, 'Created user:  (Role: )', '127.0.0.1', '', '2026-09-22 02:05:54'),
    (36, 101, 'cardio', 'Doctor', 'Create User Account', 'User Account Administration', 115, 'Created user:  (Role: )', '127.0.0.1', '', '2026-09-22 02:09:10'),
    (37, 101, 'cardio', 'Doctor', 'Create User Account', 'User Account Administration', 116, 'Created user:  (Role: )', '127.0.0.1', '', '2026-09-22 02:10:44'),
    (38, 101, 'cardio', 'Doctor', 'Create User Account', 'User Account Administration', 117, 'Created user:  (Role: )', '127.0.0.1', '', '2026-09-22 02:25:38')
ON CONFLICT DO NOTHING;

-- Data for: "backup_logs" (10 records)
INSERT INTO "backup_logs" ("BackupID", "FileName", "FileSize", "BackupType", "CreatedBy", "Status", "Notes", "CreatedAt") VALUES
    (1, 'backup_tmhmis_2026_09_01.sql.gz', '4.5 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (2, 'backup_tmhmis_2026_09_02.sql.gz', '4.8 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (3, 'backup_tmhmis_2026_09_03.sql.gz', '5.1 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (4, 'backup_tmhmis_2026_09_04.sql.gz', '5.4 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (5, 'backup_tmhmis_2026_09_05.sql.gz', '5.7 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (6, 'backup_tmhmis_2026_09_06.sql.gz', '6.0 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (7, 'backup_tmhmis_2026_09_07.sql.gz', '6.3 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (8, 'backup_tmhmis_2026_09_08.sql.gz', '6.6 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (9, 'backup_tmhmis_2026_09_09.sql.gz', '6.9 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04'),
    (10, 'backup_tmhmis_2026_09_10.sql.gz', '7.2 MB', 'Full Database', 1, 'Completed', 'Scheduled automated institutional database backup snapshot.', '2026-09-21 22:50:04')
ON CONFLICT DO NOTHING;

-- Data for: "migrations" (3 records)
INSERT INTO "migrations" ("id", "migration", "batch") VALUES
    (1, '0001_01_01_000000_create_users_table', 1),
    (2, '0001_01_01_000001_create_cache_table', 2),
    (3, '0001_01_01_000002_create_jobs_table', 2)
ON CONFLICT DO NOTHING;

-- SECTION 3: FOREIGN KEY CONSTRAINTS

DO $$ BEGIN
    ALTER TABLE "role_permissions" ADD CONSTRAINT "fk_role_permissions_RoleID" FOREIGN KEY ("RoleID") REFERENCES "roles" ("RoleID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "role_permissions" ADD CONSTRAINT "fk_role_permissions_PermissionID" FOREIGN KEY ("PermissionID") REFERENCES "permissions" ("PermissionID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "specialties" ADD CONSTRAINT "fk_specialties_BodySystemID" FOREIGN KEY ("BodySystemID") REFERENCES "body_systems" ("BodySystemID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "body_locations" ADD CONSTRAINT "fk_body_locations_BodySystemID" FOREIGN KEY ("BodySystemID") REFERENCES "body_systems" ("BodySystemID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "doctors" ADD CONSTRAINT "fk_doctors_SpecialtyID" FOREIGN KEY ("SpecialtyID") REFERENCES "specialties" ("SpecialtyID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "doctors" ADD CONSTRAINT "fk_doctors_UserID" FOREIGN KEY ("UserID") REFERENCES "users" ("UserID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "doctor_specialties" ADD CONSTRAINT "fk_doctor_specialties_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "doctor_specialties" ADD CONSTRAINT "fk_doctor_specialties_SpecialtyID" FOREIGN KEY ("SpecialtyID") REFERENCES "specialties" ("SpecialtyID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "doctor_body_systems" ADD CONSTRAINT "fk_doctor_body_systems_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "doctor_body_systems" ADD CONSTRAINT "fk_doctor_body_systems_BodySystemID" FOREIGN KEY ("BodySystemID") REFERENCES "body_systems" ("BodySystemID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patients" ADD CONSTRAINT "fk_patients_RegisteredBy" FOREIGN KEY ("RegisteredBy") REFERENCES "users" ("UserID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "emergency_contacts" ADD CONSTRAINT "fk_emergency_contacts_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "medical_histories" ADD CONSTRAINT "fk_medical_histories_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_registration_history" ADD CONSTRAINT "fk_patient_registration_history_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_registration_history" ADD CONSTRAINT "fk_patient_registration_history_UserID" FOREIGN KEY ("UserID") REFERENCES "users" ("UserID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "appointments" ADD CONSTRAINT "fk_appointments_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "appointments" ADD CONSTRAINT "fk_appointments_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "appointments" ADD CONSTRAINT "fk_appointments_CreatedBy" FOREIGN KEY ("CreatedBy") REFERENCES "users" ("UserID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_queue" ADD CONSTRAINT "fk_patient_queue_AppointmentID" FOREIGN KEY ("AppointmentID") REFERENCES "appointments" ("AppointmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_queue" ADD CONSTRAINT "fk_patient_queue_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_queue" ADD CONSTRAINT "fk_patient_queue_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_vitals" ADD CONSTRAINT "fk_patient_vitals_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "complaints" ADD CONSTRAINT "fk_complaints_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "complaint_conditions" ADD CONSTRAINT "fk_complaint_conditions_ComplaintID" FOREIGN KEY ("ComplaintID") REFERENCES "complaints" ("ComplaintID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "complaint_conditions" ADD CONSTRAINT "fk_complaint_conditions_ConditionID" FOREIGN KEY ("ConditionID") REFERENCES "possible_conditions" ("ConditionID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "possible_conditions" ADD CONSTRAINT "fk_possible_conditions_BodySystemID" FOREIGN KEY ("BodySystemID") REFERENCES "body_systems" ("BodySystemID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_symptoms" ADD CONSTRAINT "fk_patient_symptoms_ComplaintID" FOREIGN KEY ("ComplaintID") REFERENCES "complaints" ("ComplaintID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "patient_symptoms" ADD CONSTRAINT "fk_patient_symptoms_SymptomID" FOREIGN KEY ("SymptomID") REFERENCES "symptoms" ("SymptomID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "complaint_analysis" ADD CONSTRAINT "fk_complaint_analysis_ComplaintID" FOREIGN KEY ("ComplaintID") REFERENCES "complaints" ("ComplaintID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "complaint_analysis" ADD CONSTRAINT "fk_complaint_analysis_BodySystemID" FOREIGN KEY ("BodySystemID") REFERENCES "body_systems" ("BodySystemID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "complaint_analysis" ADD CONSTRAINT "fk_complaint_analysis_BodyLocationID" FOREIGN KEY ("BodyLocationID") REFERENCES "body_locations" ("BodyLocationID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "diagnoses" ADD CONSTRAINT "fk_diagnoses_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "diagnoses" ADD CONSTRAINT "fk_diagnoses_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "diagnoses" ADD CONSTRAINT "fk_diagnoses_AppointmentID" FOREIGN KEY ("AppointmentID") REFERENCES "appointments" ("AppointmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "consultation_notes" ADD CONSTRAINT "fk_consultation_notes_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "consultation_notes" ADD CONSTRAINT "fk_consultation_notes_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "consultation_notes" ADD CONSTRAINT "fk_consultation_notes_AppointmentID" FOREIGN KEY ("AppointmentID") REFERENCES "appointments" ("AppointmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "treatment_plans" ADD CONSTRAINT "fk_treatment_plans_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "treatment_plans" ADD CONSTRAINT "fk_treatment_plans_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "treatment_plans" ADD CONSTRAINT "fk_treatment_plans_AppointmentID" FOREIGN KEY ("AppointmentID") REFERENCES "appointments" ("AppointmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "treatment_plans" ADD CONSTRAINT "fk_treatment_plans_DiagnosisID" FOREIGN KEY ("DiagnosisID") REFERENCES "diagnoses" ("DiagnosisID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "medical_certificates" ADD CONSTRAINT "fk_medical_certificates_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "medical_certificates" ADD CONSTRAINT "fk_medical_certificates_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "referrals" ADD CONSTRAINT "fk_referrals_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "referrals" ADD CONSTRAINT "fk_referrals_ReferringDoctorID" FOREIGN KEY ("ReferringDoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "referrals" ADD CONSTRAINT "fk_referrals_TargetSpecialtyID" FOREIGN KEY ("TargetSpecialtyID") REFERENCES "specialties" ("SpecialtyID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "referrals" ADD CONSTRAINT "fk_referrals_TargetDoctorID" FOREIGN KEY ("TargetDoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "reference_ranges" ADD CONSTRAINT "fk_reference_ranges_CatalogID" FOREIGN KEY ("CatalogID") REFERENCES "test_catalog" ("CatalogID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_requests" ADD CONSTRAINT "fk_laboratory_requests_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_requests" ADD CONSTRAINT "fk_laboratory_requests_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_requests" ADD CONSTRAINT "fk_laboratory_requests_AppointmentID" FOREIGN KEY ("AppointmentID") REFERENCES "appointments" ("AppointmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_samples" ADD CONSTRAINT "fk_laboratory_samples_RequestID" FOREIGN KEY ("RequestID") REFERENCES "laboratory_requests" ("RequestID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_samples" ADD CONSTRAINT "fk_laboratory_samples_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_results" ADD CONSTRAINT "fk_laboratory_results_RequestID" FOREIGN KEY ("RequestID") REFERENCES "laboratory_requests" ("RequestID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_results" ADD CONSTRAINT "fk_laboratory_results_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "laboratory_results" ADD CONSTRAINT "fk_laboratory_results_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "pharmacy_stock_movements" ADD CONSTRAINT "fk_pharmacy_stock_movements_InventoryID" FOREIGN KEY ("InventoryID") REFERENCES "pharmacy_inventory" ("InventoryID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "prescriptions" ADD CONSTRAINT "fk_prescriptions_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "prescriptions" ADD CONSTRAINT "fk_prescriptions_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "prescriptions" ADD CONSTRAINT "fk_prescriptions_AppointmentID" FOREIGN KEY ("AppointmentID") REFERENCES "appointments" ("AppointmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "dispensing_records" ADD CONSTRAINT "fk_dispensing_records_PrescriptionID" FOREIGN KEY ("PrescriptionID") REFERENCES "prescriptions" ("PrescriptionID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "dispensing_records" ADD CONSTRAINT "fk_dispensing_records_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "service_fees" ADD CONSTRAINT "fk_service_fees_DepartmentID" FOREIGN KEY ("DepartmentID") REFERENCES "departments" ("DepartmentID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "billing_charges" ADD CONSTRAINT "fk_billing_charges_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "billing_charges" ADD CONSTRAINT "fk_billing_charges_FeeID" FOREIGN KEY ("FeeID") REFERENCES "service_fees" ("FeeID") ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "billing_invoices" ADD CONSTRAINT "fk_billing_invoices_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "billing_payments" ADD CONSTRAINT "fk_billing_payments_InvoiceID" FOREIGN KEY ("InvoiceID") REFERENCES "billing_invoices" ("InvoiceID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "billing_payments" ADD CONSTRAINT "fk_billing_payments_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "nurse_tasks" ADD CONSTRAINT "fk_nurse_tasks_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "allergy_records" ADD CONSTRAINT "fk_allergy_records_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "allergy_records" ADD CONSTRAINT "fk_allergy_records_DoctorID" FOREIGN KEY ("DoctorID") REFERENCES "doctors" ("DoctorID") ON DELETE RESTRICT;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
DO $$ BEGIN
    ALTER TABLE "record_release_requests" ADD CONSTRAINT "fk_record_release_requests_PatientID" FOREIGN KEY ("PatientID") REFERENCES "patients" ("PatientID") ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- SECTION 4: INDEXES

CREATE UNIQUE INDEX IF NOT EXISTS "idx_roles_RoleCode" ON "roles" ("RoleCode");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_permissions_PermissionCode" ON "permissions" ("PermissionCode");
CREATE INDEX IF NOT EXISTS "idx_role_permissions_PermissionID" ON "role_permissions" ("PermissionID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_users_Username" ON "users" ("Username");
CREATE INDEX IF NOT EXISTS "idx_users_idx_user_login" ON "users" ("Username", "Status");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_departments_DepartmentCode" ON "departments" ("DepartmentCode");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_specialties_SpecialtyName" ON "specialties" ("SpecialtyName");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_specialties_SpecialtyCode" ON "specialties" ("SpecialtyCode");
CREATE INDEX IF NOT EXISTS "idx_specialties_BodySystemID" ON "specialties" ("BodySystemID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_body_systems_SystemName" ON "body_systems" ("SystemName");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_body_systems_SystemCode" ON "body_systems" ("SystemCode");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_body_locations_LocationCode" ON "body_locations" ("LocationCode");
CREATE INDEX IF NOT EXISTS "idx_body_locations_BodySystemID" ON "body_locations" ("BodySystemID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_symptoms_SymptomName" ON "symptoms" ("SymptomName");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_doctors_LicenseNumber" ON "doctors" ("LicenseNumber");
CREATE INDEX IF NOT EXISTS "idx_doctors_idx_doc_specialty" ON "doctors" ("Specialty");
CREATE INDEX IF NOT EXISTS "idx_doctors_idx_doc_status" ON "doctors" ("Status");
CREATE INDEX IF NOT EXISTS "idx_doctors_fk_doc_user" ON "doctors" ("UserID");
CREATE INDEX IF NOT EXISTS "idx_doctors_fk_doc_specialty" ON "doctors" ("SpecialtyID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_doctor_specialties_unique_doc_spec" ON "doctor_specialties" ("DoctorID", "SpecialtyID");
CREATE INDEX IF NOT EXISTS "idx_doctor_specialties_SpecialtyID" ON "doctor_specialties" ("SpecialtyID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_doctor_body_systems_unique_doctor_system" ON "doctor_body_systems" ("DoctorID", "BodySystemID");
CREATE INDEX IF NOT EXISTS "idx_doctor_body_systems_BodySystemID" ON "doctor_body_systems" ("BodySystemID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_patients_PatientCode" ON "patients" ("PatientCode");
CREATE INDEX IF NOT EXISTS "idx_patients_RegisteredBy" ON "patients" ("RegisteredBy");
CREATE INDEX IF NOT EXISTS "idx_patients_idx_patient_search" ON "patients" ("FirstName", "LastName", "ContactNumber");
CREATE INDEX IF NOT EXISTS "idx_patients_idx_patient_code" ON "patients" ("PatientCode");
CREATE INDEX IF NOT EXISTS "idx_patients_idx_patient_category" ON "patients" ("PatientCategory");
CREATE INDEX IF NOT EXISTS "idx_patients_idx_patient_created" ON "patients" ("CreatedAt");
CREATE INDEX IF NOT EXISTS "idx_emergency_contacts_idx_emg_patient" ON "emergency_contacts" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_medical_histories_idx_med_patient" ON "medical_histories" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_patient_registration_hist_UserID" ON "patient_registration_history" ("UserID");
CREATE INDEX IF NOT EXISTS "idx_patient_registration_hist_idx_hist_patient" ON "patient_registration_history" ("PatientID", "CreatedAt");
CREATE INDEX IF NOT EXISTS "idx_appointments_CreatedBy" ON "appointments" ("CreatedBy");
CREATE INDEX IF NOT EXISTS "idx_appointments_idx_app_date" ON "appointments" ("AppointmentDate", "Status");
CREATE INDEX IF NOT EXISTS "idx_appointments_idx_app_patient" ON "appointments" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_appointments_idx_app_doctor" ON "appointments" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_patient_queue_AppointmentID" ON "patient_queue" ("AppointmentID");
CREATE INDEX IF NOT EXISTS "idx_patient_queue_PatientID" ON "patient_queue" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_patient_queue_DoctorID" ON "patient_queue" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_patient_queue_idx_queue_daily" ON "patient_queue" ("QueueDate", "QueueStatus");
CREATE INDEX IF NOT EXISTS "idx_patient_queue_idx_queue_num" ON "patient_queue" ("QueueDate", "QueueNumber");
CREATE INDEX IF NOT EXISTS "idx_patient_vitals_idx_vit_patient" ON "patient_vitals" ("PatientID", "CreatedAt");
CREATE INDEX IF NOT EXISTS "idx_complaints_idx_complaint_patient" ON "complaints" ("PatientID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_complaint_conditions_unique_complaint_conditio" ON "complaint_conditions" ("ComplaintID", "ConditionID");
CREATE INDEX IF NOT EXISTS "idx_complaint_conditions_ConditionID" ON "complaint_conditions" ("ConditionID");
CREATE INDEX IF NOT EXISTS "idx_possible_conditions_BodySystemID" ON "possible_conditions" ("BodySystemID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_patient_symptoms_unique_complaint_symptom" ON "patient_symptoms" ("ComplaintID", "SymptomID");
CREATE INDEX IF NOT EXISTS "idx_patient_symptoms_SymptomID" ON "patient_symptoms" ("SymptomID");
CREATE INDEX IF NOT EXISTS "idx_complaint_analysis_BodySystemID" ON "complaint_analysis" ("BodySystemID");
CREATE INDEX IF NOT EXISTS "idx_complaint_analysis_BodyLocationID" ON "complaint_analysis" ("BodyLocationID");
CREATE INDEX IF NOT EXISTS "idx_complaint_analysis_idx_analysis_complaint" ON "complaint_analysis" ("ComplaintID");
CREATE INDEX IF NOT EXISTS "idx_diagnoses_AppointmentID" ON "diagnoses" ("AppointmentID");
CREATE INDEX IF NOT EXISTS "idx_diagnoses_idx_diag_patient" ON "diagnoses" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_diagnoses_idx_diag_doctor" ON "diagnoses" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_consultation_notes_AppointmentID" ON "consultation_notes" ("AppointmentID");
CREATE INDEX IF NOT EXISTS "idx_consultation_notes_idx_note_patient" ON "consultation_notes" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_consultation_notes_idx_note_doctor" ON "consultation_notes" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_treatment_plans_DoctorID" ON "treatment_plans" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_treatment_plans_AppointmentID" ON "treatment_plans" ("AppointmentID");
CREATE INDEX IF NOT EXISTS "idx_treatment_plans_DiagnosisID" ON "treatment_plans" ("DiagnosisID");
CREATE INDEX IF NOT EXISTS "idx_treatment_plans_idx_treat_patient" ON "treatment_plans" ("PatientID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_medical_certificates_CertificateCode" ON "medical_certificates" ("CertificateCode");
CREATE INDEX IF NOT EXISTS "idx_medical_certificates_idx_cert_patient" ON "medical_certificates" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_medical_certificates_idx_cert_doctor" ON "medical_certificates" ("DoctorID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_referrals_ReferralCode" ON "referrals" ("ReferralCode");
CREATE INDEX IF NOT EXISTS "idx_referrals_TargetDoctorID" ON "referrals" ("TargetDoctorID");
CREATE INDEX IF NOT EXISTS "idx_referrals_idx_ref_patient" ON "referrals" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_referrals_idx_ref_doctor" ON "referrals" ("ReferringDoctorID");
CREATE INDEX IF NOT EXISTS "idx_referrals_idx_ref_target_spec" ON "referrals" ("TargetSpecialtyID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_test_catalog_TestCode" ON "test_catalog" ("TestCode");
CREATE INDEX IF NOT EXISTS "idx_reference_ranges_CatalogID" ON "reference_ranges" ("CatalogID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_laboratory_requests_RequestCode" ON "laboratory_requests" ("RequestCode");
CREATE INDEX IF NOT EXISTS "idx_laboratory_requests_AppointmentID" ON "laboratory_requests" ("AppointmentID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_requests_idx_lab_patient" ON "laboratory_requests" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_requests_idx_lab_doctor" ON "laboratory_requests" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_requests_idx_lab_status" ON "laboratory_requests" ("Status");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_laboratory_samples_SampleBarcode" ON "laboratory_samples" ("SampleBarcode");
CREATE INDEX IF NOT EXISTS "idx_laboratory_samples_RequestID" ON "laboratory_samples" ("RequestID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_samples_PatientID" ON "laboratory_samples" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_results_DoctorID" ON "laboratory_results" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_results_idx_res_request" ON "laboratory_results" ("RequestID");
CREATE INDEX IF NOT EXISTS "idx_laboratory_results_idx_res_patient" ON "laboratory_results" ("PatientID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_pharmacy_inventory_ItemCode" ON "pharmacy_inventory" ("ItemCode");
CREATE INDEX IF NOT EXISTS "idx_pharmacy_stock_movements_InventoryID" ON "pharmacy_stock_movements" ("InventoryID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_prescriptions_PrescriptionCode" ON "prescriptions" ("PrescriptionCode");
CREATE INDEX IF NOT EXISTS "idx_prescriptions_AppointmentID" ON "prescriptions" ("AppointmentID");
CREATE INDEX IF NOT EXISTS "idx_prescriptions_idx_rx_patient" ON "prescriptions" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_prescriptions_idx_rx_doctor" ON "prescriptions" ("DoctorID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_dispensing_records_DispenseCode" ON "dispensing_records" ("DispenseCode");
CREATE INDEX IF NOT EXISTS "idx_dispensing_records_PrescriptionID" ON "dispensing_records" ("PrescriptionID");
CREATE INDEX IF NOT EXISTS "idx_dispensing_records_PatientID" ON "dispensing_records" ("PatientID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_service_fees_ServiceCode" ON "service_fees" ("ServiceCode");
CREATE INDEX IF NOT EXISTS "idx_service_fees_DepartmentID" ON "service_fees" ("DepartmentID");
CREATE INDEX IF NOT EXISTS "idx_billing_charges_FeeID" ON "billing_charges" ("FeeID");
CREATE INDEX IF NOT EXISTS "idx_billing_charges_idx_chg_patient" ON "billing_charges" ("PatientID", "BillingStatus");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_billing_invoices_InvoiceNumber" ON "billing_invoices" ("InvoiceNumber");
CREATE INDEX IF NOT EXISTS "idx_billing_invoices_PatientID" ON "billing_invoices" ("PatientID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_billing_payments_ReceiptNumber" ON "billing_payments" ("ReceiptNumber");
CREATE INDEX IF NOT EXISTS "idx_billing_payments_InvoiceID" ON "billing_payments" ("InvoiceID");
CREATE INDEX IF NOT EXISTS "idx_billing_payments_PatientID" ON "billing_payments" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_nurse_tasks_PatientID" ON "nurse_tasks" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_allergy_records_DoctorID" ON "allergy_records" ("DoctorID");
CREATE INDEX IF NOT EXISTS "idx_allergy_records_idx_allg_patient" ON "allergy_records" ("PatientID");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_record_release_requests_RequestNumber" ON "record_release_requests" ("RequestNumber");
CREATE INDEX IF NOT EXISTS "idx_record_release_requests_PatientID" ON "record_release_requests" ("PatientID");
CREATE INDEX IF NOT EXISTS "idx_system_audit_logs_idx_audit_user" ON "system_audit_logs" ("UserID");
CREATE INDEX IF NOT EXISTS "idx_system_audit_logs_idx_audit_module" ON "system_audit_logs" ("Module");
CREATE INDEX IF NOT EXISTS "idx_system_audit_logs_idx_audit_date" ON "system_audit_logs" ("CreatedAt");
CREATE INDEX IF NOT EXISTS "idx_sessions_sessions_user_id_index" ON "sessions" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_sessions_sessions_last_activity_in" ON "sessions" ("last_activity");
CREATE INDEX IF NOT EXISTS "idx_cache_cache_expiration_index" ON "cache" ("expiration");
CREATE INDEX IF NOT EXISTS "idx_cache_locks_cache_locks_expiration_in" ON "cache_locks" ("expiration");
CREATE INDEX IF NOT EXISTS "idx_jobs_jobs_queue_index" ON "jobs" ("queue");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_failed_jobs_failed_jobs_uuid_unique" ON "failed_jobs" ("uuid");
CREATE INDEX IF NOT EXISTS "idx_failed_jobs_failed_jobs_connection_qu" ON "failed_jobs" ("connection", "queue", "failed_at");

-- SECTION 5: SEQUENCE RE-SYNCHRONIZATION

SELECT setval(pg_get_serial_sequence('"roles"', 'RoleID'), COALESCE((SELECT MAX("RoleID") FROM "roles"), 1), true);
SELECT setval(pg_get_serial_sequence('"permissions"', 'PermissionID'), COALESCE((SELECT MAX("PermissionID") FROM "permissions"), 1), true);
SELECT setval(pg_get_serial_sequence('"users"', 'UserID'), COALESCE((SELECT MAX("UserID") FROM "users"), 1), true);
SELECT setval(pg_get_serial_sequence('"departments"', 'DepartmentID'), COALESCE((SELECT MAX("DepartmentID") FROM "departments"), 1), true);
SELECT setval(pg_get_serial_sequence('"specialties"', 'SpecialtyID'), COALESCE((SELECT MAX("SpecialtyID") FROM "specialties"), 1), true);
SELECT setval(pg_get_serial_sequence('"body_systems"', 'BodySystemID'), COALESCE((SELECT MAX("BodySystemID") FROM "body_systems"), 1), true);
SELECT setval(pg_get_serial_sequence('"body_locations"', 'BodyLocationID'), COALESCE((SELECT MAX("BodyLocationID") FROM "body_locations"), 1), true);
SELECT setval(pg_get_serial_sequence('"symptoms"', 'SymptomID'), COALESCE((SELECT MAX("SymptomID") FROM "symptoms"), 1), true);
SELECT setval(pg_get_serial_sequence('"doctors"', 'DoctorID'), COALESCE((SELECT MAX("DoctorID") FROM "doctors"), 1), true);
SELECT setval(pg_get_serial_sequence('"doctor_specialties"', 'DoctorSpecialtyID'), COALESCE((SELECT MAX("DoctorSpecialtyID") FROM "doctor_specialties"), 1), true);
SELECT setval(pg_get_serial_sequence('"doctor_body_systems"', 'DoctorSystemID'), COALESCE((SELECT MAX("DoctorSystemID") FROM "doctor_body_systems"), 1), true);
SELECT setval(pg_get_serial_sequence('"patients"', 'PatientID'), COALESCE((SELECT MAX("PatientID") FROM "patients"), 1), true);
SELECT setval(pg_get_serial_sequence('"emergency_contacts"', 'EmergencyContactID'), COALESCE((SELECT MAX("EmergencyContactID") FROM "emergency_contacts"), 1), true);
SELECT setval(pg_get_serial_sequence('"medical_histories"', 'MedicalHistoryID'), COALESCE((SELECT MAX("MedicalHistoryID") FROM "medical_histories"), 1), true);
SELECT setval(pg_get_serial_sequence('"patient_registration_history"', 'HistoryID'), COALESCE((SELECT MAX("HistoryID") FROM "patient_registration_history"), 1), true);
SELECT setval(pg_get_serial_sequence('"appointments"', 'AppointmentID'), COALESCE((SELECT MAX("AppointmentID") FROM "appointments"), 1), true);
SELECT setval(pg_get_serial_sequence('"patient_queue"', 'QueueID'), COALESCE((SELECT MAX("QueueID") FROM "patient_queue"), 1), true);
SELECT setval(pg_get_serial_sequence('"patient_vitals"', 'VitalID'), COALESCE((SELECT MAX("VitalID") FROM "patient_vitals"), 1), true);
SELECT setval(pg_get_serial_sequence('"complaints"', 'ComplaintID'), COALESCE((SELECT MAX("ComplaintID") FROM "complaints"), 1), true);
SELECT setval(pg_get_serial_sequence('"complaint_conditions"', 'ComplaintConditionID'), COALESCE((SELECT MAX("ComplaintConditionID") FROM "complaint_conditions"), 1), true);
SELECT setval(pg_get_serial_sequence('"possible_conditions"', 'ConditionID'), COALESCE((SELECT MAX("ConditionID") FROM "possible_conditions"), 1), true);
SELECT setval(pg_get_serial_sequence('"patient_symptoms"', 'PatientSymptomID'), COALESCE((SELECT MAX("PatientSymptomID") FROM "patient_symptoms"), 1), true);
SELECT setval(pg_get_serial_sequence('"complaint_analysis"', 'AnalysisID'), COALESCE((SELECT MAX("AnalysisID") FROM "complaint_analysis"), 1), true);
SELECT setval(pg_get_serial_sequence('"diagnoses"', 'DiagnosisID'), COALESCE((SELECT MAX("DiagnosisID") FROM "diagnoses"), 1), true);
SELECT setval(pg_get_serial_sequence('"consultation_notes"', 'NoteID'), COALESCE((SELECT MAX("NoteID") FROM "consultation_notes"), 1), true);
SELECT setval(pg_get_serial_sequence('"treatment_plans"', 'PlanID'), COALESCE((SELECT MAX("PlanID") FROM "treatment_plans"), 1), true);
SELECT setval(pg_get_serial_sequence('"medical_certificates"', 'CertificateID'), COALESCE((SELECT MAX("CertificateID") FROM "medical_certificates"), 1), true);
SELECT setval(pg_get_serial_sequence('"referrals"', 'ReferralID'), COALESCE((SELECT MAX("ReferralID") FROM "referrals"), 1), true);
SELECT setval(pg_get_serial_sequence('"test_catalog"', 'CatalogID'), COALESCE((SELECT MAX("CatalogID") FROM "test_catalog"), 1), true);
SELECT setval(pg_get_serial_sequence('"reference_ranges"', 'RangeID'), COALESCE((SELECT MAX("RangeID") FROM "reference_ranges"), 1), true);
SELECT setval(pg_get_serial_sequence('"laboratory_requests"', 'RequestID'), COALESCE((SELECT MAX("RequestID") FROM "laboratory_requests"), 1), true);
SELECT setval(pg_get_serial_sequence('"laboratory_samples"', 'SampleID'), COALESCE((SELECT MAX("SampleID") FROM "laboratory_samples"), 1), true);
SELECT setval(pg_get_serial_sequence('"laboratory_results"', 'ResultID'), COALESCE((SELECT MAX("ResultID") FROM "laboratory_results"), 1), true);
SELECT setval(pg_get_serial_sequence('"pharmacy_inventory"', 'InventoryID'), COALESCE((SELECT MAX("InventoryID") FROM "pharmacy_inventory"), 1), true);
SELECT setval(pg_get_serial_sequence('"pharmacy_stock_movements"', 'MovementID'), COALESCE((SELECT MAX("MovementID") FROM "pharmacy_stock_movements"), 1), true);
SELECT setval(pg_get_serial_sequence('"prescriptions"', 'PrescriptionID'), COALESCE((SELECT MAX("PrescriptionID") FROM "prescriptions"), 1), true);
SELECT setval(pg_get_serial_sequence('"dispensing_records"', 'DispenseID'), COALESCE((SELECT MAX("DispenseID") FROM "dispensing_records"), 1), true);
SELECT setval(pg_get_serial_sequence('"service_fees"', 'FeeID'), COALESCE((SELECT MAX("FeeID") FROM "service_fees"), 1), true);
SELECT setval(pg_get_serial_sequence('"billing_charges"', 'ChargeID'), COALESCE((SELECT MAX("ChargeID") FROM "billing_charges"), 1), true);
SELECT setval(pg_get_serial_sequence('"billing_invoices"', 'InvoiceID'), COALESCE((SELECT MAX("InvoiceID") FROM "billing_invoices"), 1), true);
SELECT setval(pg_get_serial_sequence('"billing_payments"', 'PaymentID'), COALESCE((SELECT MAX("PaymentID") FROM "billing_payments"), 1), true);
SELECT setval(pg_get_serial_sequence('"nurse_tasks"', 'TaskID'), COALESCE((SELECT MAX("TaskID") FROM "nurse_tasks"), 1), true);
SELECT setval(pg_get_serial_sequence('"allergy_records"', 'AllergyID'), COALESCE((SELECT MAX("AllergyID") FROM "allergy_records"), 1), true);
SELECT setval(pg_get_serial_sequence('"record_release_requests"', 'RequestID'), COALESCE((SELECT MAX("RequestID") FROM "record_release_requests"), 1), true);
SELECT setval(pg_get_serial_sequence('"hospital_info"', 'HospitalInfoID'), COALESCE((SELECT MAX("HospitalInfoID") FROM "hospital_info"), 1), true);
SELECT setval(pg_get_serial_sequence('"system_audit_logs"', 'LogID'), COALESCE((SELECT MAX("LogID") FROM "system_audit_logs"), 1), true);
SELECT setval(pg_get_serial_sequence('"system_error_logs"', 'ErrorID'), COALESCE((SELECT MAX("ErrorID") FROM "system_error_logs"), 1), true);
SELECT setval(pg_get_serial_sequence('"backup_logs"', 'BackupID'), COALESCE((SELECT MAX("BackupID") FROM "backup_logs"), 1), true);
SELECT setval(pg_get_serial_sequence('"migrations"', 'id'), COALESCE((SELECT MAX("id") FROM "migrations"), 1), true);
SELECT setval(pg_get_serial_sequence('"jobs"', 'id'), COALESCE((SELECT MAX("id") FROM "jobs"), 1), true);
SELECT setval(pg_get_serial_sequence('"failed_jobs"', 'id'), COALESCE((SELECT MAX("id") FROM "failed_jobs"), 1), true);