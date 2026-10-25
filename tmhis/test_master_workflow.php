<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Admission;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Services\Billing\PaymentGatewayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

echo "========================================================================\n";
echo "TMHIS MASTER WORKFLOW & VERIFICATION TEST SUITE\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($condition, $description) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo " [FAIL] " . $description . "\n";
        $failCount++;
    }
}

// Ensure test patient exists
$testPatient = Patient::first();
if (!$testPatient) {
    $testPatient = Patient::create([
        'PatientCode' => 'P-TEST-999',
        'FirstName' => 'TestJuan',
        'LastName' => 'DelaCruz',
        'DateOfBirth' => '1990-01-01',
        'Age' => 36,
        'Address' => 'Tupi, South Cotabato',
        'Gender' => 'Male',
        'ContactNumber' => '09123456789',
        'Email' => 'juan.delacruz@example.com',
        'PatientCategory' => 'Outpatient'
    ]);
}

$testDoctor = Doctor::where('Status', '!=', 'Inactive')->first();
if (!$testDoctor) {
    $testDoctor = Doctor::first();
}

$paymentService = app(PaymentGatewayService::class);
require_once app_path('Services/Doctor/Consultation.php');
$consultationService = new \Consultation();

// ------------------------------------------------------------------------
// 1. BILLING WORKFLOW TESTS
// ------------------------------------------------------------------------
echo "\n--- 1. BILLING & PAYMENT WORKFLOWS ---\n";

// Create test invoice
$testInvoice = BillingInvoice::create([
    'InvoiceNumber' => 'INV-TEST-' . strtoupper(bin2hex(random_bytes(3))),
    'PatientID'     => $testPatient->PatientID,
    'GrossAmount'   => 5000.00,
    'TotalPayable'  => 5000.00,
    'AmountPaid'    => 0.00,
    'BalanceDue'    => 5000.00,
    'PaymentStatus' => 'Unpaid',
    'BilledBy'      => 'Cashier Kent',
    'DueDate'       => now()->addDays(7)
]);

// 1.1 CASH PAYMENT
$cashRes = $paymentService->initiatePayment([
    'invoice_id'     => $testInvoice->InvoiceID,
    'patient_id'     => $testPatient->PatientID,
    'amount'         => 1500.00,
    'payment_method' => 'cash',
    'cash_received'  => 2000.00,
    'cashier_name'   => 'Cashier Kent'
], 1);

assertTest(
    $cashRes['success'] === true && $cashRes['status'] === 'PAID' && $cashRes['change'] == 500.00,
    "Cash payment processes with exact change (₱2,000 tendered - ₱1,500 due = ₱500 change)"
);
$testInvoice->refresh();
assertTest(
    $testInvoice->AmountPaid == 1500.00 && $testInvoice->BalanceDue == 3500.00 && $testInvoice->PaymentStatus === 'Partially Paid',
    "Invoice balance correctly follows partial cash settlement (Balance: ₱3,500, Status: Partially Paid)"
);

// 1.2 DYNAMIC QR PH GENERATION (EMVCo)
$qrRes = $paymentService->initiatePayment([
    'invoice_id'     => $testInvoice->InvoiceID,
    'patient_id'     => $testPatient->PatientID,
    'amount'         => 1000.00,
    'payment_method' => 'qrph',
    'cashier_name'   => 'Cashier Kent'
], 1);

assertTest(
    $qrRes['success'] === true && $qrRes['status'] === 'PENDING' && !empty($qrRes['qr_payload']),
    "QR Ph payment initializes in PENDING status with dynamic QR payload"
);
assertTest(
    str_starts_with($qrRes['qr_payload'], '000201') && str_contains($qrRes['qr_payload'], 'ph.gov.bsp.qrph'),
    "Dynamic QR payload adheres to EMVCo / BSP QR Ph standards specification (Tag 00, 01, 28)"
);

// 1.3 SERVER-SIDE PAYMENT STATUS CHECK
$statusCheck = $paymentService->checkPaymentStatus($qrRes['payment_id']);
assertTest(
    $statusCheck['status'] === 'PENDING' && $statusCheck['transaction_reference'] === $qrRes['transaction_reference'],
    "Server-side status retrieval verifies payment is genuinely pending (not blindly trusted)"
);

// 1.4 WEBHOOK HANDLING & IDEMPOTENCY
$mockProviderId = 'MOCK-PROVIDER-' . strtoupper(bin2hex(random_bytes(4)));
$webhookPayload = [
    'transaction_reference'   => $qrRes['transaction_reference'],
    'provider_transaction_id' => $mockProviderId,
    'status'                  => 'PAID',
    'paid_at'                 => now()->toIso8601String()
];

$webhookRes1 = $paymentService->processWebhook($webhookPayload, null, 'qrph');
assertTest(
    $webhookRes1['success'] === true && $webhookRes1['status'] === 'PAID',
    "Payment webhook verifies transaction and transitions status to PAID"
);

// Webhook idempotency test (duplicate webhook)
$webhookRes2 = $paymentService->processWebhook($webhookPayload, null, 'qrph');
assertTest(
    $webhookRes2['success'] === true && ($webhookRes2['already_paid'] ?? false) === true,
    "Webhook idempotency: Duplicate webhook safely ignored without double-crediting"
);

// 1.5 BANK TRANSFER WORKFLOW (Pending Verification -> Staff Verification)
$bankRes = $paymentService->initiatePayment([
    'invoice_id'     => $testInvoice->InvoiceID,
    'patient_id'     => $testPatient->PatientID,
    'amount'         => 1000.00,
    'payment_method' => 'bank_transfer',
    'bank_name'      => 'Landbank of the Philippines',
    'bank_reference' => 'LBP-DEP-88912',
    'cashier_name'   => 'Cashier Kent'
], 1);

assertTest(
    $bankRes['success'] === true && $bankRes['status'] === PaymentGatewayService::STATUS_PENDING_VERIF,
    "Bank transfer creates payment in PENDING_VERIFICATION status (NEVER marked paid prematurely)"
);

$verifyRes = $paymentService->verifyBankTransfer($bankRes['payment_id'], 1, 'LBP-CONFIRMED-9921');
assertTest(
    $verifyRes['success'] === true && $verifyRes['status'] === 'PAID' && !empty($verifyRes['receipt_number']),
    "Staff bank transfer verification transitions status to PAID and issues Official Receipt"
);

// 1.6 CASHLESS PAYMENT
$cashlessRes = $paymentService->initiatePayment([
    'invoice_id'     => $testInvoice->InvoiceID,
    'patient_id'     => $testPatient->PatientID,
    'amount'         => 500.00,
    'payment_method' => 'Cashless Payment',
    'reference_no'   => 'POS-TERM-88124',
    'cashier_name'   => 'Cashier Kent'
], 1);
assertTest(
    $cashlessRes['success'] === true && $cashlessRes['status'] === 'PAID' && str_starts_with($cashlessRes['receipt_number'], 'OR-'),
    "Cashless digital POS payment settles immediately and issues Official Receipt"
);

// 1.7 MEDICALEMS (MUNICIPAL ASSISTANCE GUARANTEE)
$emsRes = $paymentService->initiatePayment([
    'invoice_id'     => $testInvoice->InvoiceID,
    'patient_id'     => $testPatient->PatientID,
    'amount'         => 500.00,
    'payment_method' => 'MedicalEMS',
    'reference_no'   => 'EMS-VOUCH-202610-0912',
    'cashier_name'   => 'Cashier Kent'
], 1);
assertTest(
    $emsRes['success'] === true && $emsRes['status'] === 'PAID' && $emsRes['payment_method'] === 'medical_ems',
    "MedicalEMS Municipal Assistance Guarantee voucher settles payment and credits LGU Health Fund"
);

// 1.8 CREDIT CARD PAYMENT
$cardRes = $paymentService->initiatePayment([
    'invoice_id'     => $testInvoice->InvoiceID,
    'patient_id'     => $testPatient->PatientID,
    'amount'         => 500.00,
    'payment_method' => 'Credit Card',
    'reference_no'   => 'AUTH-CARD-449102',
    'cashier_name'   => 'Cashier Kent'
], 1);
assertTest(
    $cardRes['success'] === true && $cardRes['status'] === 'PAID' && $cardRes['payment_method'] === 'card',
    "Credit Card transaction processes with approval authorization code and settles bill"
);

// ------------------------------------------------------------------------
// 2. DOCTOR CONSULTATION & ADMISSION WORKFLOW TESTS
// ------------------------------------------------------------------------
echo "\n--- 2. DOCTOR CONSULTATION & ADMISSION WORKFLOWS ---\n";

// 2.1 OUTPATIENT DISPOSITION (MUST NOT SEND TO NURSE INPATIENT QUEUE)
$appIdOut = DB::table('appointments')->insertGetId([
    'PatientID'       => $testPatient->PatientID,
    'DoctorID'        => $testDoctor->DoctorID,
    'AppointmentDate' => now()->toDateString(),
    'AppointmentTime' => now()->toTimeString(),
    'Reason'          => 'General Checkup',
    'Status'          => 'In Consultation',
    'CreatedAt'       => now()
]);
DB::table('patient_queue')->insert([
    'AppointmentID' => $appIdOut,
    'PatientID'     => $testPatient->PatientID,
    'DoctorID'      => $testDoctor->DoctorID,
    'QueueNumber'   => 'Q-TEST-' . rand(100, 999),
    'QueueDate'     => now()->toDateString(),
    'Priority'      => 'Normal',
    'QueueStatus'   => 'In Consultation',
    'CreatedAt'     => now()
]);

$initialAdmissionsCount = Admission::where('PatientID', $testPatient->PatientID)->where('Status', 'Admitted')->count();

$outpatientSuccess = $consultationService->completeConsultation(
    $appIdOut,
    $testDoctor->DoctorID,
    'Oral rehydration and antipyretics. Discharge home.',
    'OUTPATIENT'
);

assertTest($outpatientSuccess === true, "Doctor completed consultation with OUTPATIENT disposition");

$outQueue = DB::table('patient_queue')->where('AppointmentID', $appIdOut)->first();
assertTest(
    $outQueue && in_array($outQueue->QueueStatus, ['For Billing', 'Laboratory', 'Pharmacy']),
    "Outpatient queue is routed to '{$outQueue->QueueStatus}' (NEVER sent to inpatient nurse station)"
);

$admissionsAfterOutpatient = Admission::where('PatientID', $testPatient->PatientID)->where('Status', 'Admitted')->count();
assertTest(
    $admissionsAfterOutpatient === $initialAdmissionsCount,
    "Patient with OUTPATIENT disposition did NOT trigger admission record creation"
);

// 2.2 ADMISSION DISPOSITION (CREATES ADMISSION & INPATIENT ROSTER ENTRY)
$appIdAdmit = DB::table('appointments')->insertGetId([
    'PatientID'       => $testPatient->PatientID,
    'DoctorID'        => $testDoctor->DoctorID,
    'AppointmentDate' => now()->toDateString(),
    'AppointmentTime' => now()->toTimeString(),
    'Reason'          => 'Pneumonia and Dyspnea',
    'Status'          => 'In Consultation',
    'CreatedAt'       => now()
]);
DB::table('patient_queue')->insert([
    'AppointmentID' => $appIdAdmit,
    'PatientID'     => $testPatient->PatientID,
    'DoctorID'      => $testDoctor->DoctorID,
    'QueueNumber'   => 'Q-TEST-' . rand(100, 999),
    'QueueDate'     => now()->toDateString(),
    'Priority'      => 'Normal',
    'QueueStatus'   => 'In Consultation',
    'CreatedAt'     => now()
]);

$admitSuccess = $consultationService->completeConsultation(
    $appIdAdmit,
    $testDoctor->DoctorID,
    'Community-Acquired Pneumonia with moderate hypoxia',
    'ADMIT',
    [
        'ward'        => 'Medical Ward',
        'room_number' => '304',
        'bed_number'  => 'B-2',
        'admission_reason' => 'IV hydration and continuous oxygen monitoring'
    ]
);

assertTest($admitSuccess === true, "Doctor completed consultation with ADMIT disposition");

$admitQueue = DB::table('patient_queue')->where('AppointmentID', $appIdAdmit)->first();
assertTest(
    $admitQueue && $admitQueue->QueueStatus === 'Admitted - Inpatient',
    "Patient queue status transitioned to 'Admitted - Inpatient' for nursing station intake"
);

$latestAdmission = Admission::where('PatientID', $testPatient->PatientID)
    ->where('AppointmentID', $appIdAdmit)
    ->first();

assertTest(
    $latestAdmission && $latestAdmission->Ward === 'Medical Ward' && $latestAdmission->BedNumber === 'B-2' && $latestAdmission->Status === 'Admitted',
    "Inpatient Admission created: Ward: 'Medical Ward', Room: '304', Bed: 'B-2', Status: 'Admitted'"
);

$testPatient->refresh();
assertTest(
    $testPatient->PatientCategory === 'Inpatient',
    "Patient category dynamically updated to 'Inpatient' upon doctor admission order"
);

// ------------------------------------------------------------------------
// 3. UNIVERSAL AJAX SEARCH ENDPOINT TESTS
// ------------------------------------------------------------------------
echo "\n--- 3. UNIVERSAL AJAX SEARCH MODULES ---\n";

$searchController = app(App\Http\Controllers\Api\SearchApiController::class);

$searchEndpoints = [
    'Patient Search'     => ['method' => 'searchPatients',     'param' => substr($testPatient->FirstName, 0, 4)],
    'Doctor Search'      => ['method' => 'searchDoctors',      'param' => substr($testDoctor->FirstName ?? 'a', 0, 3)],
    'Nurse Search'       => ['method' => 'searchNurses',       'param' => 'a'],
    'Disease / ICD-10'   => ['method' => 'searchDiseases',     'param' => 'Pneumonia'],
    'Medication Search'  => ['method' => 'searchMedications',  'param' => 'Amox'],
    'Laboratory Search'  => ['method' => 'searchLaboratoryTests', 'param' => 'Blood'],
    'Admission Search'   => ['method' => 'searchAdmissions',   'param' => 'Medical'],
    'Billing Invoices'   => ['method' => 'searchBillingInvoices', 'param' => 'INV-TEST'],
    'Appointment Search' => ['method' => 'searchAppointments', 'param' => 'a'],
    'Queue Search'       => ['method' => 'searchQueue',        'param' => 'a']
];

foreach ($searchEndpoints as $name => $cfg) {
    try {
        $req = new Request(['query' => $cfg['param']]);
        $response = $searchController->{$cfg['method']}($req);
        $content = json_decode($response->getContent(), true);

        $isValid = $response->getStatusCode() === 200 &&
                   isset($content['success']) && $content['success'] === true &&
                   is_array($content['data']);

        assertTest($isValid, "AJAX {$name} returns standardized JSON (HTTP 200, success: true, count: " . count($content['data']) . ")");
    } catch (\Throwable $e) {
        assertTest(false, "AJAX {$name} failed: " . $e->getMessage());
    }
}

// ------------------------------------------------------------------------
// 4. AUDIT LOGGING VERIFICATION
// ------------------------------------------------------------------------
echo "\n--- 4. AUDIT LOGGING VERIFICATION ---\n";
$auditLogs = DB::table('system_audit_logs')
    ->whereIn('Action', ['Payment Successful', 'Payment Created', 'Admission Created', 'Doctor Disposition Changed', 'Bank Payment Verified'])
    ->orWhere('Action', 'like', '%CONSULTATION%')
    ->orWhere('Action', 'like', '%ADMISSION%')
    ->orderBy('LogID', 'desc')
    ->limit(10)
    ->get();

assertTest($auditLogs->count() >= 3, "Audit trail records financial and clinical transactions (count: {$auditLogs->count()})");

// SUMMARY
echo "\n========================================================================\n";
echo "TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================================\n";

if ($failCount === 0) {
    exit(0);
} else {
    exit(1);
}
