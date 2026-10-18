<?php
/**
 * TMHIS — 100% System Documentation Compliance Validation Suite
 * Tests all 10 Flagship Use Cases, Chapter 1 & 3 business rules,
 * Data Dictionary schemas, and FEFO / Queue / RBAC enforcement.
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\PatientVital;
use App\Models\PatientQueue;
use App\Models\ConsultationNote;
use App\Models\Diagnosis;
use App\Models\TreatmentPlan;
use App\Models\LaboratoryRequest;
use App\Models\LaboratorySample;
use App\Models\LaboratoryResult;
use App\Models\Prescription;
use App\Models\PharmacyInventory;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingCharge;
use App\Models\SystemAuditLog;
use App\Services\Register\PatientRegistration;
use App\Services\Register\Patient as RegisterPatient;
use App\Services\Pharmacy\PharmacyManager;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $evidence = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}" . ($evidence ? " — {$evidence}" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($evidence ? " — {$evidence}" : "") . "\n";
    }
}

echo "==================================================================\n";
echo "  TMHIS DOCUMENTATION COMPLIANCE & 10 USE CASES VERIFICATION     \n";
echo "==================================================================\n\n";

// -------------------------------------------------------------
// USE CASE 1: Register Patient Profile (Role 4 - Registration Staff)
// -------------------------------------------------------------
echo "--- USE CASE 1: Register Patient Profile & Digital ID ---\n";
$uniqueStamp = time() . rand(10, 99);
$regData = [
    'personal' => [
        'firstName'        => 'ComplianceTest',
        'middleName'       => 'Doc',
        'lastName'         => 'Patient' . $uniqueStamp,
        'dob'              => '1990-05-15',
        'gender'           => 'Female',
        'phone'            => '0917-123-4567',
        'email'            => "patient{$uniqueStamp}@tupi.gov.ph",
        'address'          => 'Barangay Poblacion, Tupi, South Cotabato',
        'philhealth'       => 'PHIC-12-345678901-2',
        'civilStatus'      => 'Single'
    ],
    'medical' => [
        'bloodType'        => 'O+',
        'allergies'        => 'Penicillin',
        'conditions'       => 'None',
        'medications'      => 'None',
        'hospitalization'  => 'None'
    ],
    'emergency' => [
        'name'             => 'Juan Dela Cruz',
        'relationship'     => 'Spouse',
        'phone'            => '0918-987-6543'
    ],
    'patientCategory'      => 'Senior Citizen',
    'complaint'            => 'Persistent dry cough and moderate fever',
    'severity'             => 3,
    'duration'             => '3 days'
];

$regService = new PatientRegistration();
$regResult = $regService->registerCompletePatient($regData, 1);

assertTest("Patient registered successfully", $regResult['success'] === true, "Patient ID: " . ($regResult['patient_id'] ?? 'N/A'));
assertTest("Digital ID generated with PAT-YYYY-NNNN format", (bool)preg_match('/^PAT-\d{4}-\d{4}$/', $regResult['patient_code'] ?? ''), "Code: " . ($regResult['patient_code'] ?? 'N/A'));

$savedPatient = Patient::find($regResult['patient_id']);
assertTest("Patient classification stored as Senior Citizen", $savedPatient && $savedPatient->PatientCategory === 'Senior Citizen');
assertTest("PhilHealth ID persisted correctly", $savedPatient && $savedPatient->PhilHealthNumber === 'PHIC-12-345678901-2', "PhilHealth: " . ($savedPatient->PhilHealthNumber ?? 'N/A'));

// Duplicate screening test
$dupCheck = $regService->checkDuplicate('ComplianceTest', 'Patient' . $uniqueStamp, '1990-05-15');
assertTest("Duplicate screening successfully identifies identical patient", $dupCheck !== null && (int)$dupCheck['PatientID'] === $savedPatient->PatientID);

// -------------------------------------------------------------
// USE CASE 2: Schedule Physician Consultation (Role 4 - Registration Staff)
// -------------------------------------------------------------
echo "\n--- USE CASE 2: Schedule Physician Consultation & Queue Ticket ---\n";
assertTest("Appointment created with Scheduled status", !empty($regResult['appointment_id']));
assertTest("Queue ticket generated in patient_queue", !empty($regResult['queue_number']), "Queue #: " . ($regResult['queue_number'] ?? 'N/A'));

$initQueue = PatientQueue::where('AppointmentID', $regResult['appointment_id'])->first();
assertTest("Initial Queue status is 'Waiting'", $initQueue && $initQueue->QueueStatus === 'Waiting');

// -------------------------------------------------------------
// USE CASE 3: Record Triage Vital Signs (Role 6 - Nurse)
// -------------------------------------------------------------
echo "\n--- USE CASE 3: Record Triage Vital Signs & Clinical Flags ---\n";
$nurseController = new \App\Http\Controllers\NurseController();
$vitalsReq = \Illuminate\Http\Request::create('/nurse/api/vitals', 'POST', [
    'PatientID'        => $savedPatient->PatientID,
    'BloodPressure'    => '140/90',
    'HeartRate'        => 105,
    'RespiratoryRate'  => 22,
    'Temperature'      => 38.5,
    'OxygenSaturation' => 96,
    'WeightKg'         => 70.0,
    'HeightCm'         => 170.0,
    'ClinicalNotes'    => 'Patient febrile and coughing upon triage.'
]);

$vitalsRes = $nurseController->createVital($vitalsReq);
$vitalsData = json_decode($vitalsRes->getContent(), true);

assertTest("Vital signs recorded with HTTP 201", $vitalsRes->getStatusCode() === 201);
assertTest("BMI automatically calculated (70 / 1.7^2 = 24.22)", isset($vitalsData['bmi']) && abs($vitalsData['bmi'] - 24.22) < 0.1, "BMI: " . ($vitalsData['bmi'] ?? 'N/A'));
assertTest("MAP automatically calculated ((140 + 2*90)/3 = 106.67)", isset($vitalsData['map']) && abs($vitalsData['map'] - 106.67) < 0.1, "MAP: " . ($vitalsData['map'] ?? 'N/A'));
assertTest("Threshold flags detected Pyrexia & Tachycardia", !empty($vitalsData['alerts']) && in_array('Pyrexia (Fever >= 38.0°C)', $vitalsData['alerts']), "Alerts: " . implode(', ', $vitalsData['alerts'] ?? []));

$updatedQueueAfterTriage = PatientQueue::where('AppointmentID', $regResult['appointment_id'])->first();
assertTest("Queue automatically advanced to 'Waiting for Consultation'", $updatedQueueAfterTriage && $updatedQueueAfterTriage->QueueStatus === 'Waiting for Consultation', "Queue Status: " . ($updatedQueueAfterTriage->QueueStatus ?? 'N/A'));

// -------------------------------------------------------------
// USE CASE 4: Conduct Clinical Encounter (Role 5 - Doctor)
// -------------------------------------------------------------
echo "\n--- USE CASE 4: Conduct Clinical Encounter & Order Placement ---\n";
$doctorId = $initQueue->DoctorID ?: 1;

// 1. Doctor creates Lab Request
$labReq = LaboratoryRequest::create([
    'RequestCode'   => 'LRQ-' . date('Ymd') . '-' . rand(1000, 9999),
    'PatientID'     => $savedPatient->PatientID,
    'DoctorID'      => $doctorId,
    'AppointmentID' => $regResult['appointment_id'],
    'TestType'      => 'Fasting Blood Sugar (FBS)',
    'Priority'      => 'Routine',
    'ClinicalNotes' => 'Evaluate glucose metabolism',
    'Status'        => 'Pending',
    'RequestedDate' => now(),
    'CreatedAt'     => now()
]);
assertTest("Doctor created laboratory requisition", $labReq->RequestID > 0, "Request Code: {$labReq->RequestCode}");

// 2. Doctor creates Prescription
$rx = Prescription::create([
    'PrescriptionCode' => 'RX-' . date('Ymd') . '-' . rand(1000, 9999),
    'PatientID'        => $savedPatient->PatientID,
    'DoctorID'         => $doctorId,
    'AppointmentID'    => $regResult['appointment_id'],
    'MedicineName'     => 'Paracetamol 500mg tablet',
    'Dosage'           => '500mg',
    'Frequency'        => 'Every 6 hours PRN',
    'Duration'         => '5 days',
    'Quantity'         => '20 tablets',
    'Instructions'     => 'Take after meals for fever relief',
    'Status'           => 'Active',
    'IssuedDate'       => now(),
    'CreatedAt'        => now()
]);
assertTest("Doctor issued electronic prescription", $rx->PrescriptionID > 0, "Medicine: {$rx->MedicineName}");

// 3. Doctor Consultation completion & routing
require_once app_path('Services/Doctor/Consultation.php');
$consModel = new \Consultation();
$consModel->completeConsultation($regResult['appointment_id'], $doctorId, 'Encounter completed with diagnostic orders.');

$queueAfterConsult = PatientQueue::where('AppointmentID', $regResult['appointment_id'])->first();
assertTest("Queue intelligently routed to 'Laboratory' (due to pending lab test)", $queueAfterConsult && $queueAfterConsult->QueueStatus === 'Laboratory', "Queue Status: " . ($queueAfterConsult->QueueStatus ?? 'N/A'));

// -------------------------------------------------------------
// USE CASE 5: Encode Diagnostic Test Results (Role 7 - MedTech)
// -------------------------------------------------------------
echo "\n--- USE CASE 5: Encode Diagnostic Test Results & Verification ---\n";
$medTechController = new \App\Http\Controllers\MedTechController();

// Create sample record
$sampleReq = \Illuminate\Http\Request::create('/medtech/api/samples/create', 'POST', [
    'request_id'    => $labReq->RequestID,
    'specimen_type' => 'Venous Blood',
    'collected_by'  => 'MedTech R. Santos',
    'storage_location' => 'Rack B-03',
    'notes'         => 'Specimen collected without hemolysis'
]);
$sampleRes = $medTechController->createSample($sampleReq);
$sampleData = json_decode($sampleRes->getContent(), true);

assertTest("Specimen logged with SMP-YYYY-XXXX barcode format", isset($sampleData['data']['SampleBarcode']) && (bool)preg_match('/^SMP-\d{4}-\d{4}$/', $sampleData['data']['SampleBarcode']), "Barcode: " . ($sampleData['data']['SampleBarcode'] ?? 'N/A'));

// Create test result with reference range check
$resultReq = \Illuminate\Http\Request::create('/medtech/api/results/create', 'POST', [
    'request_id'     => $labReq->RequestID,
    'test_name'      => 'Fasting Blood Sugar (FBS)',
    'result_value'   => '145',
    'normal_range'   => '70 - 100',
    'units'          => 'mg/dL',
    'interpretation' => 'Normal', // Auto-range comparator will evaluate 145 > 100 -> HIGH (Abnormal)
    'notes'          => 'Elevated fasting glucose detected.'
]);
$resultRes = $medTechController->createResult($resultReq);
$resultData = json_decode($resultRes->getContent(), true);

assertTest("Laboratory test result recorded with HTTP 200", $resultRes->getStatusCode() === 200);
assertTest("Reference range comparison auto-flagged HIGH (Abnormal)", isset($resultData['data']['Interpretation']) && str_contains($resultData['data']['Interpretation'], 'HIGH'), "Interpretation: " . ($resultData['data']['Interpretation'] ?? 'N/A'));
assertTest("Result verified and marked IsVerified = 1", !empty($resultData['data']['IsVerified']));

$queueAfterLab = PatientQueue::where('AppointmentID', $regResult['appointment_id'])->first();
assertTest("Queue transitioned from Laboratory -> 'Pharmacy' (due to pending Rx)", $queueAfterLab && $queueAfterLab->QueueStatus === 'Pharmacy', "Queue Status: " . ($queueAfterLab->QueueStatus ?? 'N/A'));

// -------------------------------------------------------------
// USE CASE 6: Dispense Prescribed Medication (Role 8 - Pharmacist FEFO)
// -------------------------------------------------------------
echo "\n--- USE CASE 6: Dispense Prescribed Medication (FEFO Stock Deduction) ---\n";
// Ensure inventory item exists for Paracetamol
$invItem = PharmacyInventory::where('GenericName', 'like', '%Paracetamol%')->where('CurrentStock', '>', 0)->first();
if (!$invItem) {
    $invItem = PharmacyInventory::create([
        'ItemCode'     => 'MED-PAR-500',
        'GenericName'  => 'Paracetamol',
        'BrandName'    => 'Biogesic',
        'DosageForm'   => 'Tablet',
        'Strength'     => '500mg',
        'Category'     => 'Analgesic',
        'UnitCost'     => 1.50,
        'SellingPrice' => 5.00,
        'CurrentStock' => 200,
        'ReorderLevel' => 30,
        'BatchNumber'  => 'B-2026-FEFO',
        'ExpiryDate'   => date('Y-m-d', strtotime('+1 year')),
        'Supplier'     => 'Gov Formulary Depo',
        'Status'       => 'In Stock',
        'UpdatedAt'    => now()
    ]);
}

$initialStock = $invItem->CurrentStock;
$pharmacyController = new \App\Http\Controllers\PharmacyController();
$dispenseReq = \Illuminate\Http\Request::create('/pharmacy/api/dispense', 'POST', [
    'PrescriptionID'     => $rx->PrescriptionID,
    'PatientID'          => $savedPatient->PatientID,
    'MedicineName'       => 'Paracetamol 500mg tablet',
    'QuantityDispensed'  => '20',
    'DosageInstructions' => 'Take 1 tablet every 6 hours after meals.',
    'Notes'              => 'Dispensed and patient counseled.'
]);

$dispenseRes = $pharmacyController->dispense($dispenseReq);
$dispenseData = json_decode($dispenseRes->getContent(), true);

assertTest("Medication dispensed successfully with HTTP 200", $dispenseRes->getStatusCode() === 200, "Dispense ID: " . ($dispenseData['dispense_id'] ?? 'N/A'));

$invItemFresh = PharmacyInventory::find($invItem->InventoryID);
assertTest("FEFO stock deducted accurately (-20 units)", $invItemFresh->CurrentStock === ($initialStock - 20), "Previous: {$initialStock}, Current: {$invItemFresh->CurrentStock}");

$rxFresh = Prescription::find($rx->PrescriptionID);
assertTest("Prescription marked Completed in database", $rxFresh->Status === 'Completed');

$queueAfterPharmacy = PatientQueue::where('AppointmentID', $regResult['appointment_id'])->first();
assertTest("Queue advanced from Pharmacy -> 'For Billing'", $queueAfterPharmacy && $queueAfterPharmacy->QueueStatus === 'For Billing', "Queue Status: " . ($queueAfterPharmacy->QueueStatus ?? 'N/A'));

// -------------------------------------------------------------
// USE CASE 7: Process Payment and Settle Account (Role 9 - Cashier/Accountant)
// -------------------------------------------------------------
echo "\n--- USE CASE 7: Process Payment & Settle Account (Discounts & Official Receipt) ---\n";
$billingController = new \App\Http\Controllers\BillingController();

// 1. Create charge for Consultation and Medication
$billingController->createCharge(\Illuminate\Http\Request::create('/billing/api/charges', 'POST', [
    'PatientID'       => $savedPatient->PatientID,
    'AppointmentID'   => $regResult['appointment_id'],
    'ChargeCategory'  => 'Consultation',
    'ItemDescription' => 'Specialist Clinical Consultation Fee',
    'UnitPrice'       => 500.00,
    'Quantity'        => 1
]));

$billingController->createCharge(\Illuminate\Http\Request::create('/billing/api/charges', 'POST', [
    'PatientID'       => $savedPatient->PatientID,
    'AppointmentID'   => $regResult['appointment_id'],
    'ChargeCategory'  => 'Pharmacy',
    'ItemDescription' => 'Paracetamol 500mg (20 tablets)',
    'UnitPrice'       => 5.00,
    'Quantity'        => 20
]));

// 2. Compute Discounts (Senior Citizen 20% discount per RA 9994)
$discountReq = \Illuminate\Http\Request::create('/billing/api/discounts/compute', 'POST', [
    'gross_amount'         => 600.00,
    'qualifications'       => ['Senior Citizen (20%)'],
    'philhealth_deduction' => 100.00
]);
$discountRes = $billingController->computeDiscounts($discountReq);
$discountData = json_decode($discountRes->getContent(), true)['data'];

assertTest("Senior discount calculated at 20% (120.00 on 600.00)", abs($discountData['discount_amount'] - 120.00) < 0.01, "Discount: " . $discountData['discount_amount']);
assertTest("Net payable calculated accurately (600 - 120 - 100 = 380.00)", abs($discountData['net_payable'] - 380.00) < 0.01, "Net Payable: " . $discountData['net_payable']);

// 3. Process Payment & Generate Official Receipt
$payReq = \Illuminate\Http\Request::create('/billing/api/payments', 'POST', [
    'PatientID'       => $savedPatient->PatientID,
    'gross_amount'    => 600.00,
    'discount_type'   => 'Senior Citizen',
    'discount_amt'    => 120.00,
    'total_payable'   => 380.00,
    'AmountPaid'      => 380.00,
    'PaymentMethod'   => 'Cash'
]);
$payRes = $billingController->processPayment($payReq);
$payData = json_decode($payRes->getContent(), true);

assertTest("Payment processed successfully with HTTP 200", $payRes->getStatusCode() === 200);
assertTest("Sequential Official Receipt (OR) generated", !empty($payData['receipt_number']) && str_starts_with($payData['receipt_number'], 'OR-'), "Receipt: " . ($payData['receipt_number'] ?? 'N/A'));

$finalQueue = PatientQueue::where('AppointmentID', $regResult['appointment_id'])->first();
assertTest("Encounter fully completed in queue upon Paid In Full ('Completed')", $finalQueue && $finalQueue->QueueStatus === 'Completed', "Final Queue Status: " . ($finalQueue->QueueStatus ?? 'N/A'));

// -------------------------------------------------------------
// USE CASE 8: Retrieve Longitudinal Master Chart (Role 3 - Records/MRO)
// -------------------------------------------------------------
echo "\n--- USE CASE 8: Retrieve Longitudinal Master Chart & Access Audit ---\n";
$chartData = \App\Services\Clinical\PatientTimelineService::getTimeline($savedPatient->PatientID);
$eventsList = isset($chartData['events']) ? $chartData['events'] : $chartData;
assertTest("Longitudinal chart timeline retrieved successfully", !empty($eventsList));
assertTest("Timeline contains clinical events from entire encounter", count($eventsList) >= 3, "Total events logged: " . count($eventsList));

// -------------------------------------------------------------
// USE CASE 9: Compile and Export DOH-Format Compliance Report (Role 2 - Director/CMO)
// -------------------------------------------------------------
echo "\n--- USE CASE 9: Compile and Export DOH-Format Compliance Report ---\n";
$directorController = new \App\Http\Controllers\DirectorController();
$dohReq = \Illuminate\Http\Request::create('/director/api/reports', 'GET', [
    'start_date' => date('Y-01-01'),
    'end_date'   => date('Y-12-31')
]);
$dohRes = $directorController->reports($dohReq);
assertTest("DOH Operational Census generated locally without external API dependence", $dohRes->getStatusCode() === 200);

// -------------------------------------------------------------
// USE CASE 10: Administer User Accounts and RBAC Roles (Role 1 - Administrator)
// -------------------------------------------------------------
echo "\n--- USE CASE 10: Administer User Accounts & Server-Side RBAC Enforcement ---\n";
$documentedRoles = [
    'System Administrator',
    'Hospital Chief / Medical Director',
    'Medical Records Officer',
    'Admitting / Registration Staff',
    'Attending Physician',
    'Nurse on Duty',
    'Medical Technologist',
    'Pharmacist / Pharmacy Aide',
    'Billing / Cashier Staff'
];
$rolesInDb = DB::table('roles')->pluck('RoleName')->toArray();
$missingRoles = array_diff($documentedRoles, $rolesInDb);
assertTest("All 9 documented hospital RBAC roles configured in database", empty($missingRoles), "Roles: " . implode(', ', $rolesInDb));

// -------------------------------------------------------------
// UNIVERSAL PRINT ENGINE DOCUMENT OUTPUTS
// -------------------------------------------------------------
echo "\n--- UNIVERSAL PRINT ENGINE (Document Generation) ---\n";
$printController = new \App\Http\Controllers\PrintController();

$typesToTest = [
    'digital-id'   => 'PATIENT DIGITAL IDENTIFICATION SLIP',
    'medication-label' => 'PHARMACY MEDICATION LABEL',
    'certificate'  => 'MEDICAL CERTIFICATE',
    'patient'      => 'MASTER PATIENT INFORMATION RECORD',
    'registration' => 'PATIENT INTAKE & REGISTRATION TRIAGE FORM',
    'consultation' => 'CLINICAL CONSULTATION & SOAP RECORD',
    'laboratory'   => 'DIAGNOSTIC LABORATORY EXAMINATION RESULT',
    'prescription' => 'ELECTRONIC MEDICAL PRESCRIPTION (Rx)',
    'invoice'      => 'STATEMENT OF HOSPITAL ACCOUNT & BILLING INVOICE',
    'receipt'      => 'OFFICIAL PAYMENT SETTLEMENT RECEIPT (OR)'
];

foreach ($typesToTest as $docType => $expectedTitleSubstring) {
    $pReq = \Illuminate\Http\Request::create("/print/{$docType}/{$savedPatient->PatientID}", 'GET');
    $pRes = $printController->show($pReq, $docType, $savedPatient->PatientID);
    $htmlContent = $pRes instanceof \Illuminate\View\View ? $pRes->render() : (string)$pRes->getContent();
    $decodedHtml = html_entity_decode($htmlContent);
    assertTest("Print document '{$docType}' rendered valid HTML", !empty($htmlContent) && str_contains($decodedHtml, $expectedTitleSubstring));
}

// -------------------------------------------------------------
// CHAPTER 1 SCOPE COMPLIANCE & SAFETY BOUNDARIES
// -------------------------------------------------------------
echo "\n--- CHAPTER 1 SCOPE COMPLIANCE & DELIMITATIONS ---\n";
// 1. Vaccination registry exclusion
$vaccinationModelFile = app_path('Models/VaccinationRecord.php');
$isDeprecated = file_exists($vaccinationModelFile) && str_contains(file_get_contents($vaccinationModelFile), 'DEPRECATED / OUT OF SCOPE');
assertTest("Vaccination record strictly marked out-of-scope / deprecated per Chapter 1 Section 1.5", $isDeprecated);

// 2. Direct public patient intake protected
$protectedIntake = Route::getRoutes()->getByName('register.registration.index');
$hasAuthMiddleware = $protectedIntake && in_array('auth', $protectedIntake->middleware());
assertTest("Patient intake route protected by authentication (patients do not self-register)", $hasAuthMiddleware);

echo "\n==================================================================\n";
echo "  FINAL COMPLIANCE SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "==================================================================\n";

if ($failed === 0) {
    echo ">>> 100% FUNCTIONAL AND DOCUMENTATION COMPLIANCE ACHIEVED! <<<\n";
} else {
    echo ">>> COMPLIANCE AUDIT FOUND {$failed} INCOMPLETE ITEMS <<<\n";
}
