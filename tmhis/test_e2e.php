<?php
/**
 * Comprehensive End-to-End Flow Test for TMHIS
 * Tests all roles, navigation, CRUD, and business logic
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

// Bootstrap
$kernel->bootstrap();

$passed = 0;
$failed = 0;
$warnings = 0;
$results = [];

function test($name, $condition, $detail = '') {
    global $passed, $failed, $results;
    if ($condition) {
        $passed++;
        echo "  [PASS] $name\n";
    } else {
        $failed++;
        echo "  [FAIL] $name" . ($detail ? " -- $detail" : "") . "\n";
    }
    $results[] = ['name' => $name, 'pass' => $condition, 'detail' => $detail];
}

function warn($msg) {
    global $warnings;
    $warnings++;
    echo "  [WARN] $msg\n";
}

// ============================================================
echo "============================================================\n";
echo " TMHIS COMPREHENSIVE END-TO-END FLOW TEST\n";
echo "============================================================\n\n";

// ============================================================
// 1. DATABASE INTEGRITY
// ============================================================
echo "--- 1. DATABASE INTEGRITY ---\n";

// Check core tables exist
$coreTables = [
    'users', 'patients', 'appointments', 'laboratory_requests', 
    'laboratory_results', 'prescriptions', 'pharmacy_inventory',
    'billing_invoices', 'medical_certificates', 'departments',
    'consultation_notes', 'patient_vitals', 'referrals',
];
foreach ($coreTables as $t) {
    test("Table '$t' exists", Schema::hasTable($t));
}

// Check key columns
test("users.Role column exists", Schema::hasColumn('users', 'Role'));
test("users.PasswordHash column exists", Schema::hasColumn('users', 'PasswordHash'));
test("patients.PatientCode exists", Schema::hasColumn('patients', 'PatientCode'));
test("patients.Status exists", Schema::hasColumn('patients', 'Status'));

// ============================================================
// 2. USER ACCOUNTS & AUTHENTICATION
// ============================================================
echo "\n--- 2. USER ACCOUNTS & AUTHENTICATION ---\n";

$roles = ['Admin', 'Doctor', 'Nurse', 'MedTech', 'Pharmacist', 'Billing', 'Records', 'Register', 'Chief'];
foreach ($roles as $role) {
    $user = DB::table('users')->where('Role', $role)->first();
    test("User with role '$role' exists", $user !== null, $user ? "Username: {$user->Username}" : "No user found");
    if ($user) {
        // Check password is hashed (not plaintext)
        $isHashed = str_starts_with($user->PasswordHash ?? '', '$2y$') || str_starts_with($user->PasswordHash ?? '', '$2a$');
        test("  '$role' password is properly hashed", $isHashed, $isHashed ? '' : 'PasswordHash: ' . substr($user->PasswordHash ?? 'NULL', 0, 20));
    }
}

// Test login route exists and works
$loginRoute = app('router')->getRoutes()->match(
    \Illuminate\Http\Request::create('/login', 'GET')
);
test("GET /login route exists", $loginRoute !== null);

$logoutRoute = app('router')->getRoutes()->match(
    \Illuminate\Http\Request::create('/logout', 'GET')
);
test("GET /logout route exists", $logoutRoute !== null);

// ============================================================
// 3. DASHBOARD ROUTES FOR ALL ROLES
// ============================================================
echo "\n--- 3. DASHBOARD ROUTES ---\n";

$dashboardRoutes = [
    '/admin' => 'Admin dashboard',
    '/doctor' => 'Doctor dashboard',
    '/nurse' => 'Nurse dashboard',
    '/medtech' => 'MedTech dashboard',
    '/pharmacy' => 'Pharmacy dashboard',
    '/billing' => 'Billing dashboard',
    '/records' => 'Records dashboard',
    '/register' => 'Registration dashboard',
    '/director' => 'Director dashboard',
];

foreach ($dashboardRoutes as $uri => $label) {
    try {
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create($uri, 'GET')
        );
        test("$label route ($uri) exists", $route !== null);
    } catch (\Exception $e) {
        test("$label route ($uri) exists", false, $e->getMessage());
    }
}

// ============================================================
// 4. REGISTRATION CRUD (FDD: Registration Staff)
// ============================================================
echo "\n--- 4. REGISTRATION MODULE ---\n";

// Test patient creation
$testPatientData = [
    'PatientCode' => 'TEST-' . time(),
    'FirstName' => 'Test',
    'MiddleName' => 'M',
    'LastName' => 'Patient',
    'DateOfBirth' => '1990-01-01',
    'Age' => 35,
    'Gender' => 'Male',
    'CivilStatus' => 'Single',
    'ContactNumber' => '09171234567',
    'Email' => 'test@test.com',
    'Address' => '123 Test Street',
    'BloodType' => 'O+',
    'PatientCategory' => 'Outpatient',
    'Status' => 'Active',
    'RegisteredBy' => 1,
    'CreatedAt' => now(),
];

try {
    $patientId = DB::table('patients')->insertGetId($testPatientData);
    test("Create patient (INSERT)", $patientId > 0);
    
    // Read
    $patient = DB::table('patients')->where('PatientID', $patientId)->first();
    test("Read patient (SELECT)", $patient !== null && $patient->FirstName === 'Test');
    
    // Update
    $updated = DB::table('patients')->where('PatientID', $patientId)->update(['LastName' => 'UpdatedPatient']);
    test("Update patient (UPDATE)", $updated === 1);
    
    $verify = DB::table('patients')->where('PatientID', $patientId)->first();
    test("Verify update", $verify->LastName === 'UpdatedPatient');
    
    // Delete
    $deleted = DB::table('patients')->where('PatientID', $patientId)->delete();
    test("Delete patient (DELETE)", $deleted === 1);
    
    $gone = DB::table('patients')->where('PatientID', $patientId)->first();
    test("Verify delete", $gone === null);
} catch (\Exception $e) {
    test("Patient CRUD", false, $e->getMessage());
}

// ============================================================
// 5. APPOINTMENT / TRIAGE (FDD: Nurse)
// ============================================================
echo "\n--- 5. APPOINTMENT & TRIAGE MODULE ---\n";

// Create test patient for subsequent tests
try {
    $testPid = DB::table('patients')->insertGetId([
        'PatientCode' => 'FLOW-' . time(),
        'FirstName' => 'Flow', 'MiddleName' => 'T', 'LastName' => 'TestPat',
        'DateOfBirth' => '1985-06-15', 'Age' => 40, 'Gender' => 'Female',
        'CivilStatus' => 'Married', 'ContactNumber' => '09181234567',
        'Email' => 'flowtest' . time() . '@example.com',
        'Address' => 'Test Address', 'PatientCategory' => 'Outpatient',
        'Status' => 'Active', 'RegisteredBy' => 1, 'CreatedAt' => now(),
    ]);
    test("Create flow-test patient", $testPid > 0);
} catch (\Exception $e) {
    test("Create flow-test patient", false, $e->getMessage());
    $testPid = null;
}

// Get doctor record (for doctor_id foreign keys)
$doctorRecord = DB::table('doctors')->first();
$doctorId = $doctorRecord ? $doctorRecord->DoctorID : 1;

// Get nurse user
$nurse = DB::table('users')->where('Role', 'Nurse')->first();
$nurseId = $nurse ? $nurse->UserID : null;

// Create appointment
if ($testPid && $doctorId) {
    try {
        $apptId = DB::table('appointments')->insertGetId([
            'PatientID' => $testPid,
            'DoctorID' => $doctorId,
            'AppointmentDate' => now()->format('Y-m-d'),
            'AppointmentTime' => '10:00:00',
            'Status' => 'Scheduled',
            'Reason' => 'General Checkup',
            'CreatedAt' => now(),
        ]);
        test("Create appointment", $apptId > 0);
        
        // Read
        $appt = DB::table('appointments')->where('AppointmentID', $apptId)->first();
        test("Read appointment", $appt !== null);
        
        // Update status
        DB::table('appointments')->where('AppointmentID', $apptId)->update(['Status' => 'In Consultation']);
        $appt2 = DB::table('appointments')->where('AppointmentID', $apptId)->first();
        test("Update appointment status", $appt2->Status === 'In Consultation');
        
    } catch (\Exception $e) {
        test("Appointment CRUD", false, $e->getMessage());
        $apptId = null;
    }
    
    // Vital signs (patient_vitals table)
    if (Schema::hasTable('patient_vitals')) {
        try {
            $vitalsId = DB::table('patient_vitals')->insertGetId([
                'PatientID' => $testPid,
                'Temperature' => 36.5,
                'BloodPressure' => '120/80',
                'HeartRate' => 72,
                'RespiratoryRate' => 16,
                'OxygenSaturation' => 98,
                'WeightKg' => 65.0,
                'HeightCm' => 165.0,
                'RecordedBy' => $nurseId ?? 1,
                'RecordedByName' => 'Nurse Elena Gomez, RN',
                'CreatedAt' => now(),
            ]);
            test("Record vital signs", $vitalsId > 0);
        } catch (\Exception $e) {
            test("Record vital signs", false, $e->getMessage());
        }
    } else {
        warn("patient_vitals table missing - using alternative");
    }
}

// ============================================================
// 6. CONSULTATION (FDD: Doctor)
// ============================================================
echo "\n--- 6. CONSULTATION MODULE ---\n";

if ($testPid && $doctorId) {
    // consultation_notes table
    if (Schema::hasTable('consultation_notes')) {
        try {
            $cols = Schema::getColumnListing('consultation_notes');
            echo "  consultation_notes columns: " . implode(', ', $cols) . "\n";
            
            $noteData = [
                'PatientID' => $testPid,
                'DoctorID' => $doctorId,
                'CreatedAt' => now(),
            ];
            // Add columns dynamically based on what exists
            if (in_array('ChiefComplaint', $cols)) $noteData['ChiefComplaint'] = 'Headache and fever';
            if (in_array('Subjective', $cols)) $noteData['Subjective'] = 'Patient reports headache for 2 days';
            if (in_array('Objective', $cols)) $noteData['Objective'] = 'T:37.5, BP:130/85';
            if (in_array('Assessment', $cols)) $noteData['Assessment'] = 'Probable viral infection';
            if (in_array('Plan', $cols)) $noteData['Plan'] = 'Paracetamol 500mg, rest, follow-up';
            if (in_array('ClinicalNotes', $cols)) $noteData['ClinicalNotes'] = 'Standard clinical consultation notes';
            if (in_array('Notes', $cols)) $noteData['Notes'] = 'Test consultation note';
            if (in_array('AppointmentID', $cols) && isset($apptId)) $noteData['AppointmentID'] = $apptId;
            
            $noteId = DB::table('consultation_notes')->insertGetId($noteData);
            test("Create consultation note", $noteId > 0);
            
            $note = DB::table('consultation_notes')->where('NoteID', $noteId)->first();
            test("Read consultation note", $note !== null);
        } catch (\Exception $e) {
            test("Consultation notes CRUD", false, $e->getMessage());
        }
    } else {
        warn("consultation_notes table missing");
    }
}

// ============================================================
// 7. PRESCRIPTIONS (FDD: Doctor → Pharmacy)
// ============================================================
echo "\n--- 7. PRESCRIPTION MODULE ---\n";

if ($testPid && $doctorId) {
    try {
        $rxId = DB::table('prescriptions')->insertGetId([
            'PrescriptionCode' => 'RX-' . time(),
            'PatientID' => $testPid,
            'DoctorID' => $doctorId,
            'AppointmentID' => $apptId ?? null,
            'MedicineName' => 'Paracetamol',
            'Dosage' => '500mg',
            'Frequency' => 'Every 6 hours',
            'Duration' => '5 days',
            'Instructions' => 'Take after meals',
            'Quantity' => 20,
            'Refills' => 0,
            'Status' => 'Active',
            'IssuedDate' => now()->format('Y-m-d'),
            'CreatedAt' => now(),
        ]);
        test("Create prescription", $rxId > 0);
        
        $rx = DB::table('prescriptions')->where('PrescriptionID', $rxId)->first();
        test("Read prescription", $rx !== null && $rx->MedicineName === 'Paracetamol');
        
        // Update
        DB::table('prescriptions')->where('PrescriptionID', $rxId)->update(['Status' => 'Completed']);
        $rx2 = DB::table('prescriptions')->where('PrescriptionID', $rxId)->first();
        test("Update prescription status", $rx2->Status === 'Completed');
        
    } catch (\Exception $e) {
        test("Prescription CRUD", false, $e->getMessage());
    }
}

// ============================================================
// 8. LABORATORY (FDD: MedTech)
// ============================================================
echo "\n--- 8. LABORATORY MODULE ---\n";

if ($testPid && $doctorId) {
    try {
        // Create lab request
        $cols = Schema::getColumnListing('laboratory_requests');
        echo "  laboratory_requests columns: " . implode(', ', $cols) . "\n";
        
        $labReqData = ['PatientID' => $testPid, 'CreatedAt' => now()];
        if (in_array('DoctorID', $cols)) $labReqData['DoctorID'] = $doctorId;
        if (in_array('RequestedBy', $cols)) $labReqData['RequestedBy'] = $doctorId;
        if (in_array('TestName', $cols)) $labReqData['TestName'] = 'Complete Blood Count';
        if (in_array('TestType', $cols)) $labReqData['TestType'] = 'Hematology';
        if (in_array('Status', $cols)) $labReqData['Status'] = 'Pending';
        if (in_array('RequestDate', $cols)) $labReqData['RequestDate'] = now()->format('Y-m-d');
        if (in_array('Priority', $cols)) $labReqData['Priority'] = 'Routine';
        if (in_array('RequestCode', $cols)) $labReqData['RequestCode'] = 'LAB-' . time();
        
        $labReqId = DB::table('laboratory_requests')->insertGetId($labReqData);
        test("Create lab request", $labReqId > 0);
        
        // Create lab result
        $labResultId = DB::table('laboratory_results')->insertGetId([
            'RequestID' => $labReqId,
            'PatientID' => $testPid,
            'DoctorID' => $doctorId,
            'TestName' => 'Complete Blood Count',
            'ResultValue' => 'WBC: 7.5, RBC: 4.8, Hgb: 14.2',
            'NormalRange' => 'WBC: 4.5-11.0, RBC: 4.0-5.5',
            'Units' => '10^9/L',
            'Interpretation' => 'Normal',
            'Notes' => 'All values within normal range',
            'ResultDate' => now()->format('Y-m-d'),
            'CreatedAt' => now(),
        ]);
        test("Create lab result", $labResultId > 0);
        
        $result = DB::table('laboratory_results')->where('ResultID', $labResultId)->first();
        test("Read lab result", $result !== null);
        
    } catch (\Exception $e) {
        test("Laboratory CRUD", false, $e->getMessage());
    }
}

// ============================================================
// 9. PHARMACY INVENTORY (FDD: Pharmacist)
// ============================================================
echo "\n--- 9. PHARMACY MODULE ---\n";

if (Schema::hasTable('pharmacy_inventory')) {
    try {
        $cols = Schema::getColumnListing('pharmacy_inventory');
        echo "  pharmacy_inventory columns: " . implode(', ', $cols) . "\n";
        
        $invData = [];
        if (in_array('ItemCode', $cols)) $invData['ItemCode'] = 'MED-TEST-' . time();
        if (in_array('MedicineName', $cols)) $invData['MedicineName'] = 'Test Medicine ' . time();
        if (in_array('GenericName', $cols)) $invData['GenericName'] = 'Testamol';
        if (in_array('BrandName', $cols)) $invData['BrandName'] = 'TestBrand';
        if (in_array('Category', $cols)) $invData['Category'] = 'General';
        if (in_array('DosageForm', $cols)) $invData['DosageForm'] = 'Tablet';
        if (in_array('Strength', $cols)) $invData['Strength'] = '500mg';
        if (in_array('CurrentStock', $cols)) $invData['CurrentStock'] = 100;
        if (in_array('Quantity', $cols)) $invData['Quantity'] = 100;
        if (in_array('QuantityInStock', $cols)) $invData['QuantityInStock'] = 100;
        if (in_array('UnitPrice', $cols)) $invData['UnitPrice'] = 5.50;
        if (in_array('SellingPrice', $cols)) $invData['SellingPrice'] = 8.50;
        if (in_array('ExpiryDate', $cols)) $invData['ExpiryDate'] = '2027-12-31';
        if (in_array('ReorderLevel', $cols)) $invData['ReorderLevel'] = 20;
        if (in_array('Status', $cols)) $invData['Status'] = 'In Stock';
        if (in_array('Supplier', $cols)) $invData['Supplier'] = 'Test Supplier';
        if (in_array('UpdatedAt', $cols)) $invData['UpdatedAt'] = now();
        
        $invId = DB::table('pharmacy_inventory')->insertGetId($invData);
        test("Create pharmacy inventory item", $invId > 0);
        
        $inv = DB::table('pharmacy_inventory')->where('InventoryID', $invId)->first();
        test("Read pharmacy inventory item", $inv !== null);
        
        // Update stock
        if (in_array('CurrentStock', $cols)) {
            DB::table('pharmacy_inventory')->where('InventoryID', $invId)->update(['CurrentStock' => 90]);
            $inv2 = DB::table('pharmacy_inventory')->where('InventoryID', $invId)->first();
            test("Update pharmacy stock", $inv2->CurrentStock == 90);
        } elseif (in_array('QuantityInStock', $cols)) {
            DB::table('pharmacy_inventory')->where('InventoryID', $invId)->update(['QuantityInStock' => 90]);
            $inv2 = DB::table('pharmacy_inventory')->where('InventoryID', $invId)->first();
            test("Update pharmacy stock", $inv2->QuantityInStock == 90);
        }
        
    } catch (\Exception $e) {
        test("Pharmacy CRUD", false, $e->getMessage());
    }
}

// ============================================================
// 10. BILLING (FDD: Accountant/Billing)
// ============================================================
echo "\n--- 10. BILLING MODULE ---\n";

if ($testPid) {
    try {
        $invoiceId = DB::table('billing_invoices')->insertGetId([
            'InvoiceNumber' => 'INV-' . time(),
            'PatientID' => $testPid,
            'GrossAmount' => 1500.00,
            'DiscountType' => 'None',
            'DiscountAmount' => 0,
            'PhilHealthDeduction' => 0,
            'TotalPayable' => 1500.00,
            'AmountPaid' => 0,
            'BalanceDue' => 1500.00,
            'PaymentStatus' => 'Unpaid',
            'DueDate' => now()->addDays(30)->format('Y-m-d'),
            'BilledBy' => 1,
            'CreatedAt' => now(),
        ]);
        test("Create billing invoice", $invoiceId > 0);
        
        $inv = DB::table('billing_invoices')->where('InvoiceID', $invoiceId)->first();
        test("Read billing invoice", $inv !== null);
        
        // Update - partial payment
        DB::table('billing_invoices')->where('InvoiceID', $invoiceId)->update([
            'AmountPaid' => 500.00,
            'BalanceDue' => 1000.00,
            'PaymentStatus' => 'Partially Paid',
        ]);
        $inv2 = DB::table('billing_invoices')->where('InvoiceID', $invoiceId)->first();
        test("Update billing (partial payment)", $inv2->PaymentStatus === 'Partially Paid');
        
        // Full payment
        DB::table('billing_invoices')->where('InvoiceID', $invoiceId)->update([
            'AmountPaid' => 1500.00,
            'BalanceDue' => 0,
            'PaymentStatus' => 'Paid In Full',
        ]);
        $inv3 = DB::table('billing_invoices')->where('InvoiceID', $invoiceId)->first();
        test("Update billing (full payment)", $inv3->PaymentStatus === 'Paid In Full');
        
    } catch (\Exception $e) {
        test("Billing CRUD", false, $e->getMessage());
    }
}

// ============================================================
// 11. MEDICAL CERTIFICATES (FDD: Doctor)
// ============================================================
echo "\n--- 11. MEDICAL CERTIFICATES ---\n";

if ($testPid && $doctorId) {
    try {
        $certId = DB::table('medical_certificates')->insertGetId([
            'CertificateCode' => 'CERT-' . time(),
            'PatientID' => $testPid,
            'DoctorID' => $doctorId,
            'CertificateType' => 'Fit to Work',
            'Diagnosis' => 'Recovered from viral infection',
            'DurationStart' => now()->format('Y-m-d'),
            'DurationEnd' => now()->addDays(1)->format('Y-m-d'),
            'DaysExcused' => 3,
            'Remarks' => 'Patient cleared for duty',
            'IssueDate' => now()->format('Y-m-d'),
            'CreatedAt' => now(),
        ]);
        test("Create medical certificate", $certId > 0);
        
        $cert = DB::table('medical_certificates')->where('CertificateID', $certId)->first();
        test("Read medical certificate", $cert !== null);
        
    } catch (\Exception $e) {
        test("Medical certificate CRUD", false, $e->getMessage());
    }
}

// ============================================================
// 12. REFERRALS (FDD: Doctor)
// ============================================================
echo "\n--- 12. REFERRALS ---\n";

if (Schema::hasTable('referrals') && $testPid && $doctorId) {
    try {
        $cols = Schema::getColumnListing('referrals');
        echo "  referrals columns: " . implode(', ', $cols) . "\n";
        
        $refData = ['PatientID' => $testPid, 'CreatedAt' => now()];
        if (in_array('DoctorID', $cols)) $refData['DoctorID'] = $doctorId;
        if (in_array('ReferringDoctorID', $cols)) $refData['ReferringDoctorID'] = $doctorId;
        if (in_array('TargetSpecialtyID', $cols)) $refData['TargetSpecialtyID'] = 1;
        if (in_array('ReferralCode', $cols)) $refData['ReferralCode'] = 'REF-' . time();
        if (in_array('ReferredTo', $cols)) $refData['ReferredTo'] = 'Specialist Hospital';
        if (in_array('ReferredToDoctor', $cols)) $refData['ReferredToDoctor'] = 'Dr. Smith';
        if (in_array('Reason', $cols)) $refData['Reason'] = 'Further evaluation needed';
        if (in_array('Status', $cols)) $refData['Status'] = 'Pending';
        if (in_array('Priority', $cols)) $refData['Priority'] = 'Routine';
        if (in_array('ReferralDate', $cols)) $refData['ReferralDate'] = now()->format('Y-m-d');
        
        $refId = DB::table('referrals')->insertGetId($refData);
        test("Create referral", $refId > 0);
        
    } catch (\Exception $e) {
        test("Referral CRUD", false, $e->getMessage());
    }
}

// ============================================================
// 13. RECORDS MANAGEMENT (FDD: Records)
// ============================================================
echo "\n--- 13. RECORDS MANAGEMENT ---\n";

if (Schema::hasTable('record_release_requests') && $testPid) {
    try {
        $cols = Schema::getColumnListing('record_release_requests');
        echo "  record_release_requests columns: " . implode(', ', $cols) . "\n";
        
        $reqData = ['PatientID' => $testPid];
        if (in_array('RequestNumber', $cols)) $reqData['RequestNumber'] = 'REQ-' . time();
        if (in_array('RequestCode', $cols)) $reqData['RequestCode'] = 'REC-' . time();
        if (in_array('RequestType', $cols)) $reqData['RequestType'] = 'Complete Medical History';
        if (in_array('RequestorName', $cols)) $reqData['RequestorName'] = 'Test Patient Self';
        if (in_array('RequestedBy', $cols)) $reqData['RequestedBy'] = 1;
        if (in_array('Purpose', $cols)) $reqData['Purpose'] = 'Insurance claim';
        if (in_array('PurposeOfRequest', $cols)) $reqData['PurposeOfRequest'] = 'Insurance claim';
        if (in_array('Status', $cols)) $reqData['Status'] = 'Pending Review';
        if (in_array('RequestedDate', $cols)) $reqData['RequestedDate'] = now();
        if (in_array('CreatedAt', $cols)) $reqData['CreatedAt'] = now();
        
        $recId = DB::table('record_release_requests')->insertGetId($reqData);
        test("Create record release request", $recId > 0);
        
    } catch (\Exception $e) {
        test("Record release CRUD", false, $e->getMessage());
    }
} else {
    warn("record_release_requests table may be missing");
}

// ============================================================
// 14. HTTP ROUTE TESTING (Simulated)
// ============================================================
echo "\n--- 14. HTTP ROUTE ACCESSIBILITY ---\n";

// Test each major route by simulating a request
$testRoutes = [
    ['GET', '/login', 200, 'Login page'],
    ['GET', '/admin', [200, 302], 'Admin dashboard'],
    ['GET', '/doctor', [200, 302], 'Doctor dashboard'],
    ['GET', '/nurse', [200, 302], 'Nurse dashboard'],
    ['GET', '/medtech', [200, 302], 'MedTech dashboard'],
    ['GET', '/pharmacy', [200, 302], 'Pharmacy dashboard'],
    ['GET', '/billing', [200, 302], 'Billing dashboard'],
    ['GET', '/records', [200, 302], 'Records dashboard'],
    ['GET', '/register', [200, 302], 'Registration dashboard'],
    ['GET', '/director', [200, 302], 'Director dashboard'],
];

foreach ($testRoutes as [$method, $uri, $expectedCodes, $label]) {
    try {
        $request = \Illuminate\Http\Request::create($uri, $method);
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $expected = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
        $ok = in_array($status, $expected);
        test("$label ($uri) returns valid status", $ok, "Got: $status, Expected: " . implode('|', $expected));
        
        // Check for 500 errors specifically
        if ($status === 500) {
            $content = $response->getContent();
            // Extract error message
            if (preg_match('/<title>(.*?)<\/title>/s', $content, $m)) {
                echo "    Error: " . trim($m[1]) . "\n";
            }
        }
    } catch (\Exception $e) {
        test("$label ($uri) accessible", false, substr($e->getMessage(), 0, 100));
    }
}

// ============================================================
// 15. VIEW FILES INTEGRITY
// ============================================================
echo "\n--- 15. VIEW FILES CHECK ---\n";

$viewDirs = [
    'admin', 'doctor', 'nurse', 'medtech', 'pharmacy', 
    'accountant', 'medical_officer', 'register', 'cmo',
];

foreach ($viewDirs as $dir) {
    $path = resource_path("views/$dir");
    $exists = is_dir($path);
    test("View directory '$dir' exists", $exists);
    if ($exists) {
        $files = glob($path . '/*.php') ?: [];
        $bladeFiles = glob($path . '/*.blade.php') ?: [];
        $allFiles = array_merge($files, $bladeFiles);
        echo "    Files: " . count($allFiles) . "\n";
    }
}

// ============================================================
// 16. SIDEBAR / NAVIGATION LINKS
// ============================================================
echo "\n--- 16. SIDEBAR NAVIGATION ---\n";

$sidebarFiles = [
    'doctor/includes/sidebar.php',
    'nurse/includes/sidebar.php',
    'medtech/includes/sidebar.php',
    'pharmacy/includes/sidebar.php',
    'medical_officer/includes/sidebar.php',
    'cmo/includes/sidebar.php',
    'register/includes/navbar.php',
];

foreach ($sidebarFiles as $sf) {
    $path = resource_path("views/$sf");
    $exists = file_exists($path);
    test("Sidebar: $sf exists", $exists);
    if ($exists) {
        $content = file_get_contents($path);
        // Check for logout link
        $hasLogout = str_contains($content, '/logout') || str_contains($content, 'logout');
        test("  Has logout link", $hasLogout);
    }
}

// ============================================================
// 17. INTER-ROLE WORKFLOW (End-to-End Patient Flow)
// ============================================================
echo "\n--- 17. INTER-ROLE WORKFLOW SIMULATION ---\n";

try {
    // Step 1: Registration creates patient
    $wfPid = DB::table('patients')->insertGetId([
        'PatientCode' => 'WF-' . time(),
        'FirstName' => 'Workflow', 'LastName' => 'Patient',
        'DateOfBirth' => '1995-03-20', 'Age' => 30, 'Gender' => 'Male',
        'CivilStatus' => 'Single', 'ContactNumber' => '09191234567',
        'Email' => 'wf-' . time() . '@example.com',
        'Address' => 'Workflow Test', 'PatientCategory' => 'Outpatient',
        'Status' => 'Active', 'RegisteredBy' => 1, 'CreatedAt' => now(),
    ]);
    test("WF Step 1: Registration creates patient", $wfPid > 0);
    
    // Step 2: Nurse records vitals
    if (Schema::hasTable('patient_vitals')) {
        $wfVitals = DB::table('patient_vitals')->insertGetId([
            'PatientID' => $wfPid,
            'Temperature' => 38.2,
            'BloodPressure' => '140/90',
            'HeartRate' => 88,
            'RespiratoryRate' => 20,
            'OxygenSaturation' => 96,
            'WeightKg' => 70,
            'HeightCm' => 170,
            'RecordedBy' => $nurseId ?? 1,
            'RecordedByName' => 'Nurse Elena Gomez, RN',
            'CreatedAt' => now(),
        ]);
        test("WF Step 2: Nurse records vitals", $wfVitals > 0);
    }
    
    // Step 3: Doctor creates appointment & consults
    $wfAppt = DB::table('appointments')->insertGetId([
        'PatientID' => $wfPid,
        'DoctorID' => $doctorId,
        'AppointmentDate' => now()->format('Y-m-d'),
        'AppointmentTime' => '14:00:00',
        'Status' => 'Completed',
        'Reason' => 'Workflow Test Consultation',
        'CreatedAt' => now(),
    ]);
    test("WF Step 3: Doctor creates appointment", $wfAppt > 0);
    
    // Step 4: Doctor orders lab tests
    $wfLabCols = Schema::getColumnListing('laboratory_requests');
    $wfLabReqData = ['PatientID' => $wfPid, 'CreatedAt' => now()];
    if (in_array('DoctorID', $wfLabCols)) $wfLabReqData['DoctorID'] = $doctorId;
    if (in_array('RequestedBy', $wfLabCols)) $wfLabReqData['RequestedBy'] = $doctorId;
    if (in_array('TestName', $wfLabCols)) $wfLabReqData['TestName'] = 'Urinalysis';
    if (in_array('TestType', $wfLabCols)) $wfLabReqData['TestType'] = 'Clinical Chemistry';
    if (in_array('Status', $wfLabCols)) $wfLabReqData['Status'] = 'Pending';
    if (in_array('RequestDate', $wfLabCols)) $wfLabReqData['RequestDate'] = now()->format('Y-m-d');
    if (in_array('Priority', $wfLabCols)) $wfLabReqData['Priority'] = 'Urgent';
    if (in_array('RequestCode', $wfLabCols)) $wfLabReqData['RequestCode'] = 'WF-LAB-' . time();
    
    $wfLabReq = DB::table('laboratory_requests')->insertGetId($wfLabReqData);
    test("WF Step 4: Doctor orders lab test", $wfLabReq > 0);
    
    // Step 5: MedTech processes lab result
    $wfLabRes = DB::table('laboratory_results')->insertGetId([
        'RequestID' => $wfLabReq,
        'PatientID' => $wfPid,
        'DoctorID' => $doctorId,
        'TestName' => 'Urinalysis',
        'ResultValue' => 'pH: 6.0, Protein: Negative',
        'NormalRange' => 'pH: 4.5-8.0',
        'Units' => '-',
        'Interpretation' => 'Normal',
        'Notes' => 'Normal urinalysis',
        'ResultDate' => now()->format('Y-m-d'),
        'CreatedAt' => now(),
    ]);
    test("WF Step 5: MedTech records lab result", $wfLabRes > 0);
    
    // Step 6: Doctor prescribes
    $wfRx = DB::table('prescriptions')->insertGetId([
        'PrescriptionCode' => 'WF-RX-' . time(),
        'PatientID' => $wfPid,
        'DoctorID' => $doctorId,
        'AppointmentID' => $wfAppt,
        'MedicineName' => 'Amoxicillin',
        'Dosage' => '500mg',
        'Frequency' => 'Every 8 hours',
        'Duration' => '7 days',
        'Instructions' => 'Take with food',
        'Quantity' => 21,
        'Refills' => 0,
        'Status' => 'Active',
        'IssuedDate' => now()->format('Y-m-d'),
        'CreatedAt' => now(),
    ]);
    test("WF Step 6: Doctor issues prescription", $wfRx > 0);
    
    // Step 7: Pharmacist dispenses
    if (Schema::hasTable('dispensing_records')) {
        $dispCols = Schema::getColumnListing('dispensing_records');
        echo "  dispensing_records columns: " . implode(', ', $dispCols) . "\n";
        
        $dispData = ['PrescriptionID' => $wfRx];
        if (in_array('PatientID', $dispCols)) $dispData['PatientID'] = $wfPid;
        if (in_array('DispenseCode', $dispCols)) $dispData['DispenseCode'] = 'WF-DISP-' . time();
        if (in_array('DispensedBy', $dispCols)) $dispData['DispensedBy'] = 1;
        if (in_array('QuantityDispensed', $dispCols)) $dispData['QuantityDispensed'] = '21';
        if (in_array('DosageInstructions', $dispCols)) $dispData['DosageInstructions'] = 'Take 1 capsule every 8 hours for 7 days';
        if (in_array('DispenseDate', $dispCols)) $dispData['DispenseDate'] = now();
        if (in_array('Status', $dispCols)) $dispData['Status'] = 'Dispensed';
        if (in_array('CreatedAt', $dispCols)) $dispData['CreatedAt'] = now();
        
        $wfDisp = DB::table('dispensing_records')->insertGetId($dispData);
        test("WF Step 7: Pharmacist dispenses medication", $wfDisp > 0);
    }
    
    // Step 8: Billing creates invoice
    $wfInv = DB::table('billing_invoices')->insertGetId([
        'InvoiceNumber' => 'WF-INV-' . time(),
        'PatientID' => $wfPid,
        'GrossAmount' => 2500.00,
        'DiscountType' => 'None',
        'DiscountAmount' => 0,
        'PhilHealthDeduction' => 0,
        'TotalPayable' => 2500.00,
        'AmountPaid' => 2500.00,
        'BalanceDue' => 0,
        'PaymentStatus' => 'Paid In Full',
        'DueDate' => now()->addDays(30)->format('Y-m-d'),
        'BilledBy' => 1,
        'CreatedAt' => now(),
    ]);
    test("WF Step 8: Billing creates & settles invoice", $wfInv > 0);
    
    // Step 9: Doctor issues medical certificate
    $wfCert = DB::table('medical_certificates')->insertGetId([
        'CertificateCode' => 'WF-CERT-' . time(),
        'PatientID' => $wfPid,
        'DoctorID' => $doctorId,
        'CertificateType' => 'Medical Leave',
        'Diagnosis' => 'Urinary tract infection',
        'DurationStart' => now()->subDays(2)->format('Y-m-d'),
        'DurationEnd' => now()->format('Y-m-d'),
        'DaysExcused' => 3,
        'Remarks' => 'Rest advised',
        'IssueDate' => now()->format('Y-m-d'),
        'CreatedAt' => now(),
    ]);
    test("WF Step 9: Doctor issues medical certificate", $wfCert > 0);
    
    echo "\n  >> Full patient workflow (Registration → Triage → Consult → Lab → Rx → Dispense → Billing → Certificate) COMPLETED\n";
    
} catch (\Exception $e) {
    test("Workflow simulation", false, $e->getMessage());
}

// ============================================================
// CLEANUP
// ============================================================
echo "\n--- CLEANUP ---\n";
// Clean up test data
try {
    // Delete workflow test data
    if (isset($wfPid)) {
        DB::table('medical_certificates')->where('PatientID', $wfPid)->delete();
        DB::table('billing_invoices')->where('PatientID', $wfPid)->delete();
        DB::table('dispensing_records')->where('PatientID', $wfPid)->delete();
        DB::table('prescriptions')->where('PatientID', $wfPid)->delete();
        DB::table('laboratory_results')->where('PatientID', $wfPid)->delete();
        DB::table('laboratory_requests')->where('PatientID', $wfPid)->delete();
        DB::table('appointments')->where('PatientID', $wfPid)->delete();
        if (Schema::hasTable('patient_vitals')) DB::table('patient_vitals')->where('PatientID', $wfPid)->delete();
        DB::table('patients')->where('PatientID', $wfPid)->delete();
    }
    
    // Delete earlier test data
    if (isset($testPid)) {
        DB::table('medical_certificates')->where('PatientID', $testPid)->delete();
        DB::table('billing_invoices')->where('PatientID', $testPid)->delete();
        DB::table('prescriptions')->where('PatientID', $testPid)->delete();
        DB::table('laboratory_results')->where('PatientID', $testPid)->delete();
        DB::table('laboratory_requests')->where('PatientID', $testPid)->delete();
        DB::table('appointments')->where('PatientID', $testPid)->delete();
        if (Schema::hasTable('consultation_notes')) DB::table('consultation_notes')->where('PatientID', $testPid)->delete();
        if (Schema::hasTable('patient_vitals')) DB::table('patient_vitals')->where('PatientID', $testPid)->delete();
        if (Schema::hasTable('referrals')) DB::table('referrals')->where('PatientID', $testPid)->delete();
        if (Schema::hasTable('record_release_requests')) DB::table('record_release_requests')->where('PatientID', $testPid)->delete();
        DB::table('patients')->where('PatientID', $testPid)->delete();
    }
    
    // Delete test pharmacy inventory
    if (isset($invId)) {
        DB::table('pharmacy_inventory')->where('InventoryID', $invId)->delete();
    }
    
    echo "  Cleanup completed.\n";
} catch (\Exception $e) {
    echo "  Cleanup error (non-critical): " . $e->getMessage() . "\n";
}

// ============================================================
// SUMMARY
// ============================================================
echo "\n============================================================\n";
echo " TEST SUMMARY\n";
echo "============================================================\n";
echo "  PASSED: $passed\n";
echo "  FAILED: $failed\n";
echo "  WARNINGS: $warnings\n";
echo "  TOTAL: " . ($passed + $failed) . "\n";
echo "  RESULT: " . ($failed === 0 ? "ALL TESTS PASSED ✓" : "$failed TESTS FAILED ✗") . "\n";
echo "============================================================\n";
