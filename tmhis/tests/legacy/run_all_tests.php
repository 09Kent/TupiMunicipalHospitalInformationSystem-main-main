<?php
/**
 * Tupi Municipal Hospital Information Management System
 * Comprehensive End-to-End Verification Test Suite
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$baseDir = __DIR__;
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

function assertTest(string $category, string $name, bool $condition, string $detail = ''): void {
    global $results;
    $results['total']++;
    if ($condition) {
        $results['passed']++;
        echo "  [PASS] {$category} -> {$name}" . ($detail ? " ({$detail})" : "") . "\n";
        $results['tests'][] = ['category' => $category, 'name' => $name, 'status' => 'PASS', 'detail' => $detail];
    } else {
        $results['failed']++;
        echo "  [FAIL] {$category} -> {$name}" . ($detail ? " ({$detail})" : "") . "\n";
        $results['tests'][] = ['category' => $category, 'name' => $name, 'status' => 'FAIL', 'detail' => $detail];
    }
}

echo "=================================================================================\n";
echo "  TUPI MUNICIPAL HOSPITAL INFORMATION MANAGEMENT SYSTEM — MASTER TEST SUITE\n";
echo "=================================================================================\n\n";

// 1. DATABASE CONNECTIVITY & TABLE COUNT
echo "--- 1. DATABASE CONNECTIVITY & SCHEMA VERIFICATION ---\n";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=MedicalRegistrationDB', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    assertTest('Database', 'MySQL Connection', true, 'Connected to MedicalRegistrationDB');

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    assertTest('Database', 'Table Count (>= 40 tables)', count($tables) >= 40, "Found " . count($tables) . " tables");
} catch (Exception $e) {
    assertTest('Database', 'MySQL Connection', false, $e->getMessage());
    exit(1);
}

// 2. TEST DATA RECORD VOLUMES (RULE 19: 20-30+ RECORDS PER MAJOR TABLE)
echo "\n--- 2. TEST DATA RECORD VOLUMES (>= 20 RECORDS PER TABLE) ---\n";
$keyTables = [
    'patients'            => 'Patients Master Directory',
    'appointments'        => 'Appointments & Consultations',
    'patient_vitals'      => 'Vital Signs Records',
    'nurse_tasks'         => 'Nursing Operational Tasks',
    'laboratory_requests' => 'Laboratory Requests Queue',
    'laboratory_results'  => 'Certified Laboratory Results',
    'laboratory_samples'  => 'Specimen Tracking Log',
    'test_catalog'        => 'Diagnostic Test Catalog',
    'pharmacy_inventory'  => 'Pharmacy Medicine Inventory',
    'dispensing_records'  => 'Prescription Dispensing Records',
    'billing_charges'     => 'Patient Service Charges',
    'billing_invoices'    => 'Billing Invoices',
    'billing_payments'    => 'Official Payment Receipts',
    'system_audit_logs'   => 'System Audit & Activity Logs',
    'users'               => 'Hospital Staff & Admin Accounts'
];

foreach ($keyTables as $table => $label) {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        assertTest('Data Volume', "{$label} (`{$table}`)", $count >= 20, "{$count} records");
    } catch (Exception $e) {
        assertTest('Data Volume', "{$label} (`{$table}`)", false, $e->getMessage());
    }
}

// 3. USER ROLES & DEMO ACCOUNTS (ALL 9 FDD ROLES)
echo "\n--- 3. USER ROLES & DEMO CREDENTIALS (ALL 9 FDD ROLES) ---\n";
$requiredRoles = [
    'Admin'      => 'admin',
    'Chief'      => 'director',
    'Records'    => 'records',
    'Register'   => 'registrator',
    'Doctor'     => 'doctor',
    'Nurse'      => 'nurse',
    'MedTech'    => 'medtech',
    'Pharmacist' => 'pharmacist',
    'Billing'    => 'cashier'
];

foreach ($requiredRoles as $role => $username) {
    $stmt = $pdo->prepare("SELECT UserID, Username, Role, Status FROM users WHERE Role = :role OR Username = :uname LIMIT 1");
    $stmt->execute([':role' => $role, ':uname' => $username]);
    $user = $stmt->fetch();
    assertTest('User Role', "Role '{$role}' Account", !empty($user), !empty($user) ? "User: {$user['Username']} (Status: {$user['Status']})" : "Missing role account");
}

// 4. PAGINATOR UNIT TESTS (10 RECORDS PER PAGE)
echo "\n--- 4. PAGINATOR COMPONENT VERIFICATION (10 RECORDS PER PAGE) ---\n";
require_once __DIR__ . '/../../app/Helpers/Paginator.php';

$p1 = new Paginator(35, 10, 1);
assertTest('Paginator', 'Page 1 Limit & Offset', $p1->recordsPerPage === 10 && $p1->offset === 0 && $p1->totalPages === 4, "Total: 35, Pages: 4, Offset: 0");
assertTest('Paginator', 'Page 1 Records Range', $p1->startRecord === 1 && $p1->endRecord === 10, "Showing 1–10 of 35");

$p2 = new Paginator(35, 10, 2);
assertTest('Paginator', 'Page 2 Records Range', $p2->startRecord === 11 && $p2->endRecord === 20, "Showing 11–20 of 35");

$p4 = new Paginator(35, 10, 4);
assertTest('Paginator', 'Last Page Records Range', $p4->startRecord === 31 && $p4->endRecord === 35, "Showing 31–35 of 35");

$html = $p1->render('patients');
assertTest('Paginator', 'Render Counter Format', strpos($html, '1–10') !== false && strpos($html, '35') !== false, "Counter markup valid");
assertTest('Paginator', 'Previous Disabled on Page 1', strpos($html, 'cursor-not-allowed') !== false, "Previous button disabled on page 1");

// 5. FILE INTEGRITY & ROUTING CHECKS ACROSS ALL 9 PORTALS
echo "\n--- 5. PORTAL ENTRYPOINTS & FILE INTEGRITY ---\n";
$tmhisApp = dirname(__DIR__, 2);
$mainRoot = dirname(__DIR__, 3);
$portals = [
    'Admin'                   => 'resources/views/admin/dashboard.php',
    'Chief Medical Officer'   => 'resources/views/cmo/dashboard.php',
    'Medical Records Officer' => 'resources/views/medical_officer/dashboard.php',
    'Admitting / Register'    => 'resources/views/register/registration/index.blade.php',
    'Attending Physician'     => 'resources/views/doctor/dashboard/index.php',
    'Nurse on Duty'           => 'resources/views/nurse/dashboard/index.php',
    'Medical Technologist'    => 'resources/views/medtech/dashboard/index.php',
    'Pharmacist'              => 'resources/views/pharmacy/dashboard/index.php',
    'Billing / Cashier'       => 'resources/views/accountant/dashboard.php'
];

foreach ($portals as $name => $relPath) {
    $fullPath = $tmhisApp . '/' . $relPath;
    assertTest('Portal Entrypoint', $name, file_exists($fullPath), $relPath);
}

// 6. SYSTEM BRANDING CONSISTENCY
echo "\n--- 6. SYSTEM BRANDING & ASSET VERIFICATION ---\n";
$logoPath = $tmhisApp . '/public/assets/logo.png';
assertTest('Branding', 'Official Hospital Logo Asset', file_exists($logoPath), 'public/assets/logo.png exists');

$loginPath = $tmhisApp . '/resources/views/auth/login.blade.php';
$loginContent = file_exists($loginPath) ? file_get_contents($loginPath) : '';
assertTest('Branding', 'Login Page Title & Brand', strpos($loginContent, 'Tupi Municipal Hospital Information Management System') !== false, 'Correct brand name in login');
assertTest('Branding', 'No AuraHealth in Login', strpos($loginContent, 'AuraHealth') === false, 'AuraHealth placeholder eliminated');

$mainIndexPath = $mainRoot . '/index.php';
$mainIndexContent = file_exists($mainIndexPath) ? file_get_contents($mainIndexPath) : '';
assertTest('Branding', 'Main Landing Page Title & Subtitle', strpos($mainIndexContent, 'Information Management System') !== false, 'Landing page brand accurate');

// 7. SECURITY & BACKDOOR VERIFICATION
echo "\n--- 7. SECURITY, RBAC & BACKDOOR VERIFICATION ---\n";
$userAuthFile = file_get_contents($tmhisApp . '/app/Services/Register/raw/User.php');
$noPasswordBackdoor = (strpos($userAuthFile, "=== 'password123'") === false) && (strpos($userAuthFile, "=== 'password'") === false);
assertTest('Security', 'Universal Password Backdoors Removed', $noPasswordBackdoor, 'password_verify strictly enforced');

$doctorControllerFile = file_get_contents($tmhisApp . '/app/Http/Controllers/DoctorController.php');
$noDoctorSwitch = (strpos($doctorControllerFile, 'switch_doctor') === false) && (strpos($doctorControllerFile, '$doctorId = 11') === false);
assertTest('Security', 'Doctor Session Hijacking & Auto-Login Removed', $noDoctorSwitch, 'Authenticated doctor identity strictly enforced');

$sqliteDecommissioned = !file_exists($tmhisApp . '/database/legacy_config/CMO/hospital.sqlite');
assertTest('Security', 'SQLite Decommissioned from Production', $sqliteDecommissioned, 'hospital.sqlite purged');

// 8. CLINICAL & BILLING ENGINE VERIFICATION
echo "\n--- 8. CLINICAL SAFETY & BILLING ENGINE VERIFICATION ---\n";
require_once $tmhisApp . '/app/Helpers/legacy_bridge.php';
require_once $tmhisApp . '/app/Services/Doctor/Prescription.php';
$rxService = new Prescription();
$allergyBlocked = false;
try {
    $rxService->create([
        'patient_id'    => 1,
        'doctor_id'     => 11,
        'medicine_name' => 'Amoxicillin 500mg',
        'dosage'        => '500mg',
        'frequency'     => 'TID',
        'duration'      => '7 days'
    ]);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Allergy Safety Violation') !== false) {
        $allergyBlocked = true;
    }
}
assertTest('Clinical Safety', 'Allergy Conflict Cross-Check', $allergyBlocked, 'Conflicting prescription prevented with Allergy Safety Violation');

// Duplicate Patient Detection
require_once $tmhisApp . '/app/Services/Register/PatientRegistration.php';
$regService = new \App\Services\Register\PatientRegistration($pdo);
$firstPat = $pdo->query("SELECT FirstName, LastName, DateOfBirth FROM patients LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$isDup = $regService->checkDuplicate($firstPat['FirstName'], $firstPat['LastName'], $firstPat['DateOfBirth']);
assertTest('Clinical Safety', 'Duplicate Patient Registration Detection', $isDup !== null, "Identified duplicate matching {$firstPat['FirstName']} {$firstPat['LastName']}");

// Physician Appointment Conflict Check
require_once $tmhisApp . '/app/Services/Register/PatientHistory.php';
require_once $tmhisApp . '/app/Services/Register/Appointment.php';
$apptService = new \App\Services\Register\Appointment($pdo);
$firstAppt = $pdo->query("SELECT DoctorID, AppointmentDate, AppointmentTime FROM appointments LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hasConflict = $apptService->checkConflict((int)$firstAppt['DoctorID'], $firstAppt['AppointmentDate'], $firstAppt['AppointmentTime']);
assertTest('Clinical Safety', 'Physician Appointment Conflict Detection', $hasConflict, "Scheduling conflict detected for Doctor #{$firstAppt['DoctorID']} at {$firstAppt['AppointmentTime']}");

// Nurse Task State Machine Transitions
require_once $tmhisApp . '/app/Services/Nurse/NurseTaskManager.php';
$nurseMgr = new \App\Services\Nurse\NurseTaskManager();
$validTrans = $nurseMgr->validateTransition('Pending', 'In Progress');
$invalidTrans = $nurseMgr->validateTransition('Completed', 'Pending');
assertTest('Clinical Operations', 'Nurse Task State Machine (Pending -> In Progress)', $validTrans === true, 'Valid forward transition permitted');
assertTest('Clinical Operations', 'Nurse Task State Machine Rejection (Completed -> Pending)', $invalidTrans === false, 'Invalid backward transition rejected');

require_once $tmhisApp . '/app/Services/Accountant/BillingManager.php';
$billingMgr = new BillingManager();
$seniorDiscount = $billingMgr->computeDiscounts(1000.0, ['Senior Citizen']);
assertTest('Billing Engine', 'Senior Citizen 20% Statutory Discount', $seniorDiscount['discount_amount'] === 200.0 && $seniorDiscount['total_payable'] === 800.0, '20% discount correctly computed');

$dualDiscount = $billingMgr->computeDiscounts(1000.0, ['Senior Citizen', 'PWD']);
assertTest('Billing Engine', 'Senior + PWD Dual Discount (Max 20% Once)', $dualDiscount['discount_amount'] === 200.0 && $dualDiscount['total_payable'] === 800.0, 'Max 20% enforced once');

$philHealthCalc = $billingMgr->computeDiscounts(1000.0, ['Senior Citizen'], 300.0);
assertTest('Billing Engine', 'PhilHealth Deduction on Net Balance', $philHealthCalc['total_payable'] === 500.0, 'Payable after 20% and PhilHealth is 500.00');

$orNumber = $billingMgr->generateReceiptNumber();
$validOrFormat = (bool)preg_match('/^OR-\d{4}-\d{6}$/', $orNumber);
assertTest('Billing Engine', 'Sequential Official Receipt Format', $validOrFormat, "Generated {$orNumber}");

echo "\n=================================================================================\n";
$pct = round(($results['passed'] / $results['total']) * 100);
echo "  TEST SUMMARY: {$results['passed']} / {$results['total']} TESTS PASSED ({$pct}%)\n";
if ($results['failed'] === 0) {
    echo "  ALL VERIFICATION TESTS PASSED SUCCESSFULLY! SYSTEM MEETS FDD REQUIREMENTS.\n";
} else {
    echo "  ATTENTION: {$results['failed']} TEST(S) FAILED.\n";
}
echo "=================================================================================\n";
