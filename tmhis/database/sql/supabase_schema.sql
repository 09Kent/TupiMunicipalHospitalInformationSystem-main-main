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