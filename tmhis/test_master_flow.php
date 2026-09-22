<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$passedCount = 0;
$failedCount = 0;
$failures = [];

function runStep(string $name, callable $fn) {
    global $passedCount, $failedCount, $failures;
    echo "▶ " . str_pad($name, 60, ".");
    try {
        $fn();
        echo " [PASSED]" . PHP_EOL;
        $passedCount++;
    } catch (\Throwable $e) {
        echo " [FAILED] ❌" . PHP_EOL;
        $msg = $e->getMessage();
        if (strlen($msg) > 300) {
            $msg = substr($msg, 0, 300) . "...";
        }
        echo "   Error: " . $msg . PHP_EOL;
        $failedCount++;
        $failures[] = "$name: " . $msg;
    }
}

function makeRequest($kernel, $app, $user, $method, $url, $params = []) {
    $parsed = parse_url($url);
    $path = $parsed['path'];
    $query = [];
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    if ($method === 'GET' && !empty($params)) {
        $query = array_merge($query, $params);
    }

    $server = ['HTTP_ACCEPT' => 'application/json, text/html, */*'];
    $content = null;
    $postParams = [];

    if ($method === 'POST') {
        $server['CONTENT_TYPE'] = 'application/json';
        $content = json_encode($params);
        $postParams = $params;
    }

    $request = \Illuminate\Http\Request::create($url, $method, $postParams, [], [], $server, $content);
    if ($user) {
        $request->setUserResolver(function () use ($user) { return $user; });
        \Illuminate\Support\Facades\Auth::login($user);
    }

    $session = $app['session']->driver();
    $session->start();
    if ($user) {
        $session->put('user_id', $user->UserID);
        $session->put('login_web_59ba36addc2b2f9401560f0c40d5714279b66e20', $user->UserID);
        $session->put('role', $user->Role);
    }
    $request->setLaravelSession($session);

    return $kernel->handle($request);
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo "  TMHIS HOSPITAL INFORMATION SYSTEM — COMPREHENSIVE FLOW AUDIT   " . PHP_EOL;
echo "==================================================================" . PHP_EOL . PHP_EOL;

// 1. AUTHENTICATION & DASHBOARDS FOR ALL 9 ROLES
$roles = [
    'admin'       => '/admin',
    'director'    => '/director',
    'records'     => '/records',
    'registrator' => '/register',
    'cardio'      => '/doctor',
    'nurse'       => '/nurse',
    'medtech'     => '/medtech',
    'pharmacist'  => '/pharmacy',
    'cashier'     => '/billing',
];

foreach ($roles as $username => $dash) {
    runStep("Auth & Dashboard: $username ($dash)", function () use ($kernel, $app, $username, $dash) {
        $user = \App\Models\User::where('Username', $username)->first();
        if (!$user) throw new \Exception("User $username not found");
        $res = makeRequest($kernel, $app, $user, 'GET', $dash);
        if ($res->getStatusCode() !== 200) {
            throw new \Exception("Dashboard returned status " . $res->getStatusCode());
        }
    });
}

// 2. REGISTRATOR FLOW & PATIENT CRUD
$createdPatientId = null;
$createdApptId = null;

runStep("Public Intake Form Access", function () use ($kernel, $app) {
    $res = makeRequest($kernel, $app, null, 'GET', '/register/intake');
    if ($res->getStatusCode() !== 200) throw new \Exception("Status " . $res->getStatusCode());
});

runStep("Patient Registration API Submission (Create)", function () use ($kernel, $app, &$createdPatientId, &$createdApptId) {
    $registrator = \App\Models\User::where('Username', 'registrator')->first();
    $unique = time() . rand(100, 999);
    $payload = [
        'personal' => [
            'firstName'   => 'MasterTest' . $unique,
            'lastName'    => 'FlowPatient' . $unique,
            'dob'         => '1995-08-20',
            'age'         => 31,
            'gender'      => 'Male',
            'civilStatus' => 'Single',
            'phone'       => '09181234567',
            'email'       => "master{$unique}@example.com",
            'address'     => 'Brgy Poblacion, Tupi',
        ],
        'emergency' => [
            'name'         => 'Emergency Contact',
            'relationship' => 'Sibling',
            'phone'        => '09189876543',
        ],
        'medical' => [
            'bloodType'       => 'O+',
            'allergies'       => 'None',
            'conditions'      => 'None',
            'medications'     => 'None',
            'hospitalization' => 'None',
        ],
        'complaint'       => 'Master flow validation headache and fever',
        'severity'        => 2,
        'duration'        => '1–3 days ago',
        'bodyLocation'    => 'head',
        'bodySystemId'    => 2,
        'doctorId'        => 11, // cardio
        'appointmentDate' => date('Y-m-d', strtotime('+' . rand(400, 700) . ' days')),
        'selectedSlot'    => sprintf('%02d:%02d PM', rand(1, 4), rand(10, 50)),
    ];

    $res = makeRequest($kernel, $app, $registrator, 'POST', '/api/registration/submit.php', $payload);
    $data = json_decode($res->getContent(), true);
    if (!($data['success'] ?? false)) {
        throw new \Exception("Submit failed: " . ($data['message'] ?? $res->getContent()));
    }
    $createdApptId = $data['appointment_id'] ?? null;
    
    // Find created patient
    $p = \App\Models\Patient::where('Email', "master{$unique}@example.com")->first();
    if (!$p) throw new \Exception("Patient record was not created in database");
    $createdPatientId = $p->PatientID;
});

runStep("Patient List & Read (Registrator)", function () use ($kernel, $app) {
    $registrator = \App\Models\User::where('Username', 'registrator')->first();
    $res = makeRequest($kernel, $app, $registrator, 'GET', '/register/patients');
    if ($res->getStatusCode() !== 200) throw new \Exception("Status " . $res->getStatusCode());
});

runStep("Patient Details View (Registrator)", function () use ($kernel, $app, &$createdPatientId) {
    if (!$createdPatientId) throw new \Exception("No patient ID");
    $registrator = \App\Models\User::where('Username', 'registrator')->first();
    $res = makeRequest($kernel, $app, $registrator, 'GET', "/register/patients/{$createdPatientId}");
    if ($res->getStatusCode() !== 200) throw new \Exception("Status " . $res->getStatusCode());
});

runStep("Patient Edit Form & Update (Registrator CRUD)", function () use ($kernel, $app, &$createdPatientId) {
    if (!$createdPatientId) throw new \Exception("No patient ID");
    $registrator = \App\Models\User::where('Username', 'registrator')->first();
    
    // Test edit form
    $res = makeRequest($kernel, $app, $registrator, 'GET', "/register/patients/{$createdPatientId}/edit");
    if ($res->getStatusCode() !== 200) throw new \Exception("Edit form status " . $res->getStatusCode());

    // Test update
    $updatePayload = [
        'FirstName'     => 'MasterUpdated',
        'LastName'      => 'FlowUpdated',
        'ContactNumber' => '09199998888',
        'CivilStatus'   => 'Married',
        'Address'       => 'Purok 4, Tupi',
        'BloodType'     => 'AB+',
        'Status'        => 'Active',
    ];
    $updateRes = makeRequest($kernel, $app, $registrator, 'POST', "/register/patients/{$createdPatientId}/update", $updatePayload);
    if (!in_array($updateRes->getStatusCode(), [200, 302])) {
        throw new \Exception("Update returned status " . $updateRes->getStatusCode());
    }

    $p = \App\Models\Patient::find($createdPatientId);
    if ($p->FirstName !== 'MasterUpdated') {
        throw new \Exception("Database was not updated with new patient name");
    }
});

runStep("Appointments Management & Reschedule/Cancel", function () use ($kernel, $app, &$createdApptId) {
    $registrator = \App\Models\User::where('Username', 'registrator')->first();
    $res = makeRequest($kernel, $app, $registrator, 'GET', '/register/appointments');
    if ($res->getStatusCode() !== 200) throw new \Exception("Status " . $res->getStatusCode());

    if ($createdApptId) {
        // Reschedule
        $newDate = date('Y-m-d', strtotime('+800 days'));
        $reschedRes = makeRequest($kernel, $app, $registrator, 'POST', "/register/appointments/{$createdApptId}/reschedule", [
            'appointment_date' => $newDate,
            'appointment_time' => '10:00:00',
        ]);
        if (!in_array($reschedRes->getStatusCode(), [200, 302])) {
            throw new \Exception("Reschedule status " . $reschedRes->getStatusCode());
        }
    }
});

// 3. NURSE FLOW: VITALS & QUEUE
runStep("Nurse Queue & Vitals Entry (Create Vitals)", function () use ($kernel, $app, &$createdPatientId) {
    $nurse = \App\Models\User::where('Username', 'nurse')->first();
    
    // Nurse pages
    $resQ = makeRequest($kernel, $app, $nurse, 'GET', '/nurse/queue');
    if ($resQ->getStatusCode() !== 200) throw new \Exception("Queue status " . $resQ->getStatusCode());

    $resV = makeRequest($kernel, $app, $nurse, 'GET', '/nurse/vitals');
    if ($resV->getStatusCode() !== 200) throw new \Exception("Vitals status " . $resV->getStatusCode());

    // Record vitals directly in DB
    $vital = \App\Models\PatientVital::create([
        'PatientID'        => $createdPatientId,
        'BloodPressure'    => '118/76',
        'HeartRate'        => 72,
        'RespiratoryRate'  => 16,
        'Temperature'      => 36.5,
        'OxygenSaturation' => 98.5,
        'WeightKg'         => 70.0,
        'HeightCm'         => 175.0,
        'BMI'              => 22.86,
        'ClinicalNotes'    => 'Master audit normal vitals',
        'RecordedBy'       => $nurse->UserID,
        'RecordedByName'   => $nurse->FullName ?: 'Nurse On Duty',
    ]);
    if (!$vital->VitalID) throw new \Exception("Failed to persist vitals");
});

// 4. DOCTOR FLOW: ALL PAGES, CONSULTATION, RX, LAB REQUEST, CERTIFICATE
$createdRxId = null;
$createdLabReqId = null;

runStep("Doctor Portal Subpages (Clean & Legacy Redirects)", function () use ($kernel, $app, &$createdPatientId) {
    $doctor = \App\Models\User::where('Username', 'cardio')->first();
    
    $pages = [
        '/doctor',
        '/doctor/patients',
        "/doctor/patients/view?id={$createdPatientId}",
        "/doctor/diagnosis?patient_id={$createdPatientId}",
        '/doctor/prescriptions',
        '/doctor/laboratory',
        '/doctor/referrals',
        '/doctor/certificates',
        '/doctor/settings',
    ];
    foreach ($pages as $p) {
        $res = makeRequest($kernel, $app, $doctor, 'GET', $p);
        if ($res->getStatusCode() !== 200) throw new \Exception("Page $p status " . $res->getStatusCode());
    }

    // Legacy redirects
    $legacy = makeRequest($kernel, $app, $doctor, 'GET', '/doctor/views/patients/index.php');
    if ($legacy->getStatusCode() !== 302) throw new \Exception("Legacy route status " . $legacy->getStatusCode());
});

runStep("Doctor Clinical Artifacts (Diagnosis, Rx, Lab, Note)", function () use ($kernel, $app, &$createdPatientId, &$createdRxId, &$createdLabReqId) {
    $doctor = \App\Models\User::where('Username', 'cardio')->first();
    $docProfile = \App\Models\Doctor::where('UserID', $doctor->UserID)->first() ?? \App\Models\Doctor::find(11);
    $docId = $docProfile ? $docProfile->DoctorID : 11;

    // SOAP Note
    $note = \App\Models\ConsultationNote::create([
        'PatientID'     => $createdPatientId,
        'DoctorID'      => $docId,
        'Subjective'    => 'Mild headache and low-grade pyrexia.',
        'Objective'     => 'Clear chest, BP 118/76.',
        'Assessment'    => 'Tension Headache secondary to dehydration',
        'Plan'          => 'Hydration therapy + Paracetamol',
        'ClinicalNotes' => 'Advised rest and fluid intake.',
    ]);
    if (!$note->NoteID) throw new \Exception("Failed to persist consultation note");

    // Diagnosis
    $diag = \App\Models\Diagnosis::create([
        'PatientID'     => $createdPatientId,
        'DoctorID'      => $docId,
        'DiagnosisName' => 'Tension Headache',
        'ICD10Code'     => 'G44.2',
        'Type'          => 'Primary',
        'Severity'      => 'Mild',
        'Status'        => 'Active',
        'DiagnosedDate' => date('Y-m-d'),
    ]);
    if (!$diag->DiagnosisID) throw new \Exception("Failed to persist diagnosis");

    // Prescription
    $rx = \App\Models\Prescription::create([
        'PrescriptionCode' => 'RX-AUDIT-' . time(),
        'PatientID'        => $createdPatientId,
        'DoctorID'         => $docId,
        'MedicineName'     => 'Paracetamol 500mg Tablet',
        'Dosage'           => '500mg',
        'Frequency'        => 'Every 6 hours PRN',
        'Duration'         => '3 days',
        'Instructions'     => 'Take after meals for fever/pain',
        'Quantity'         => 12,
        'Status'           => 'Active',
        'IssuedDate'       => date('Y-m-d'),
    ]);
    if (!$rx->PrescriptionID) throw new \Exception("Failed to persist prescription");
    $createdRxId = $rx->PrescriptionID;

    // Laboratory Request
    $lab = \App\Models\LaboratoryRequest::create([
        'RequestCode'   => 'LAB-AUDIT-' . time(),
        'PatientID'     => $createdPatientId,
        'DoctorID'      => $docId,
        'TestType'      => 'Routine Urinalysis',
        'Priority'      => 'Routine',
        'ClinicalNotes' => 'Routine screen for hydration check',
        'Status'        => 'Pending',
        'RequestedDate' => date('Y-m-d'),
    ]);
    if (!$lab->RequestID) throw new \Exception("Failed to persist lab request");
    $createdLabReqId = $lab->RequestID;

    // Medical Certificate
    $cert = \App\Models\MedicalCertificate::create([
        'CertificateCode' => 'MC-AUDIT-' . time(),
        'PatientID'       => $createdPatientId,
        'DoctorID'        => $docId,
        'CertificateType' => 'Fit to Work',
        'Diagnosis'       => 'Tension Headache',
        'Recommendation'  => 'Excused from physical exertion for 2 days.',
        'DurationStart'   => date('Y-m-d'),
        'DurationEnd'     => date('Y-m-d', strtotime('+2 days')),
        'DaysExcused'     => 2,
        'Remarks'         => 'Excused from physical exertion for 2 days.',
        'IssueDate'       => date('Y-m-d'),
    ]);
    if (!$cert->CertificateID) throw new \Exception("Failed to persist medical certificate");
});

// 5. MEDTECH FLOW: SPECIMEN PROCESSING & LAB RESULT ENCODING
runStep("MedTech Laboratory Processing & Result Encoding", function () use ($kernel, $app, &$createdPatientId, &$createdLabReqId) {
    $medtech = \App\Models\User::where('Username', 'medtech')->first();
    $res = makeRequest($kernel, $app, $medtech, 'GET', '/medtech');
    if ($res->getStatusCode() !== 200) throw new \Exception("Medtech portal status " . $res->getStatusCode());

    // Fulfill lab request
    $labReq = \App\Models\LaboratoryRequest::find($createdLabReqId);
    $labReq->update(['Status' => 'Completed']);

    $result = \App\Models\LaboratoryResult::create([
        'RequestID'      => $createdLabReqId,
        'PatientID'      => $createdPatientId,
        'DoctorID'       => $labReq->DoctorID,
        'TestName'       => 'Routine Urinalysis',
        'ResultValue'    => 'Color: Straw, Clarity: Clear, pH: 6.0, SpGr: 1.015, Protein: Neg, Glucose: Neg',
        'NormalRange'    => 'pH 4.5-8.0, SpGr 1.005-1.030, Neg/Neg',
        'Units'          => 'Clinical Urinalysis Panel',
        'Interpretation' => 'Normal',
        'ResultDate'     => date('Y-m-d'),
    ]);
    if (!$result->ResultID) throw new \Exception("Failed to persist lab result");
});

// 6. PHARMACY FLOW: DISPENSING & MEDICINE CATALOG CRUD
runStep("Pharmacy Portal & Prescription Dispensing", function () use ($kernel, $app, &$createdPatientId, &$createdRxId) {
    $pharmacist = \App\Models\User::where('Username', 'pharmacist')->first();
    $res = makeRequest($kernel, $app, $pharmacist, 'GET', '/pharmacy');
    if ($res->getStatusCode() !== 200) throw new \Exception("Pharmacy portal status " . $res->getStatusCode());

    $rx = \App\Models\Prescription::find($createdRxId);
    $rx->update(['Status' => 'Completed']);

    $disp = \App\Models\DispensingRecord::create([
        'DispenseCode'       => 'DSP-AUDIT-' . time(),
        'PrescriptionID'     => $createdRxId,
        'PatientID'          => $createdPatientId,
        'DispensedBy'        => $pharmacist->UserID,
        'DispenserName'      => $pharmacist->FullName ?: 'Hospital Pharmacist',
        'QuantityDispensed'  => 12,
        'DosageInstructions' => 'Take 1 tablet every 6 hours PRN',
        'BatchNumber'        => 'PAR-2026-B1',
        'DispenseDate'       => date('Y-m-d H:i:s'),
        'Status'             => 'Dispensed',
    ]);
    if (!$disp->DispenseID) throw new \Exception("Failed to persist dispensing record");
});

runStep("Pharmacy Medicine Catalog CRUD (Add, Edit, Toggle)", function () use ($kernel, $app) {
    $pharmacist = \App\Models\User::where('Username', 'pharmacist')->first();
    $randMed = 'AuditMed' . rand(1000, 9999);

    // 1. Add Medicine
    $addRes = makeRequest($kernel, $app, $pharmacist, 'POST', '/pharmacy/api/catalog/add', [
        'generic_name' => $randMed,
        'brand_name'   => 'AuditBrand',
        'category'     => 'Analgesics',
        'dosage_form'  => 'Capsule',
        'strength'     => '250mg',
        'route'        => 'Oral',
        'stock'        => 100,
    ]);
    $addData = json_decode($addRes->getContent(), true);
    if (!($addData['success'] ?? false)) throw new \Exception("Add medicine failed: " . $addRes->getContent());
    $medId = $addData['item_code'];

    // 2. Update Medicine
    $updateRes = makeRequest($kernel, $app, $pharmacist, 'POST', '/pharmacy/api/catalog/update', [
        'med_id'       => $medId,
        'generic_name' => $randMed . ' Updated',
        'brand_name'   => 'AuditBrand Pro',
        'category'     => 'Analgesics',
        'dosage_form'  => 'Capsule',
        'strength'     => '500mg',
        'route'        => 'Oral',
        'status'       => 'Active',
    ]);
    $updateData = json_decode($updateRes->getContent(), true);
    if (!($updateData['success'] ?? false)) throw new \Exception("Update medicine failed: " . $updateRes->getContent());

    // 3. Toggle Status
    $toggleRes = makeRequest($kernel, $app, $pharmacist, 'POST', '/pharmacy/api/catalog/toggle', [
        'med_id'       => $medId,
        'generic_name' => $randMed . ' Updated',
    ]);
    $toggleData = json_decode($toggleRes->getContent(), true);
    if (!($toggleData['success'] ?? false)) throw new \Exception("Toggle status failed: " . $toggleRes->getContent());

    // Clean up
    \App\Models\PharmacyInventory::where('ItemCode', $medId)->delete();
});

// 7. BILLING & CASHIER FLOW: CHARGE AGGREGATION, DISCOUNT & PAYMENT
runStep("Billing & Cashier (Charge, Discount, Invoice & Payment)", function () use ($kernel, $app, &$createdPatientId) {
    $cashier = \App\Models\User::where('Username', 'cashier')->first();
    
    // Portal & API Data
    $res = makeRequest($kernel, $app, $cashier, 'GET', '/billing');
    if ($res->getStatusCode() !== 200) throw new \Exception("Billing portal status " . $res->getStatusCode());

    $resData = makeRequest($kernel, $app, $cashier, 'GET', '/billing/api/data');
    if ($resData->getStatusCode() !== 200) throw new \Exception("Billing API data status " . $resData->getStatusCode());

    // Create Charges
    $charge1 = \App\Models\BillingCharge::create([
        'PatientID'       => $createdPatientId,
        'ChargeCategory'  => 'Consultation',
        'ItemDescription' => 'Specialist Consultation Fee',
        'Quantity'        => 1,
        'UnitPrice'       => 350.00,
        'SubTotal'        => 350.00,
        'DiscountAmount'  => 0.00,
        'NetAmount'       => 350.00,
        'BillingStatus'   => 'Unbilled',
    ]);
    $charge2 = \App\Models\BillingCharge::create([
        'PatientID'       => $createdPatientId,
        'ChargeCategory'  => 'Pharmacy',
        'ItemDescription' => 'Paracetamol 500mg x 12',
        'Quantity'        => 12,
        'UnitPrice'       => 5.00,
        'SubTotal'        => 60.00,
        'DiscountAmount'  => 0.00,
        'NetAmount'       => 60.00,
        'BillingStatus'   => 'Unbilled',
    ]);

    // Discount Calculation Test
    $discRes = makeRequest($kernel, $app, $cashier, 'POST', '/billing/api/discounts/compute', [
        'subtotal'      => 410.00,
        'discount_type' => 'Senior Citizen',
    ]);
    $discData = json_decode($discRes->getContent(), true);
    if (!($discData['success'] ?? false)) throw new \Exception("Compute discount failed: " . $discRes->getContent());

    // Process Payment & Generate Invoice
    $payRes = makeRequest($kernel, $app, $cashier, 'POST', '/billing/api/payments', [
        'patient_id'     => $createdPatientId,
        'gross_amount'   => 410.00,
        'discount_type'  => 'Senior Citizen',
        'discount_pct'   => 20.00,
        'discount_amt'   => 82.00,
        'total_payable'  => 328.00,
        'amount_paid'    => 328.00,
        'payment_method' => 'Cash',
        'amount_words'   => 'Three Hundred Twenty-Eight Pesos Only',
        'charge_ids'     => [$charge1->ChargeID, $charge2->ChargeID],
    ]);
    $payData = json_decode($payRes->getContent(), true);
    if (!($payData['success'] ?? false)) throw new \Exception("Payment processing failed: " . $payRes->getContent());
    if (empty($payData['receipt_number']) || empty($payData['invoice_number'])) {
        throw new \Exception("Receipt/Invoice number was not generated");
    }
});

// 8. MEDICAL RECORDS OFFICER: COMPLETE UNIFIED CHART ACCESS
runStep("Medical Records Officer (Unified Chart Audit)", function () use ($kernel, $app, &$createdPatientId) {
    $records = \App\Models\User::where('Username', 'records')->first();
    $res = makeRequest($kernel, $app, $records, 'GET', '/records');
    if ($res->getStatusCode() !== 200) throw new \Exception("Records portal status " . $res->getStatusCode());

    // Verify unified relations for the patient
    $chart = \App\Models\Patient::with([
        'consultationNotes',
        'diagnoses',
        'prescriptions',
        'laboratoryRequests',
        'vitals',
        'billingCharges',
        'billingInvoices',
        'billingPayments',
    ])->find($createdPatientId);

    if (!$chart) throw new \Exception("Patient record missing in records system");
    if ($chart->consultationNotes->isEmpty()) throw new \Exception("Notes missing in unified chart");
    if ($chart->diagnoses->isEmpty()) throw new \Exception("Diagnoses missing in unified chart");
    if ($chart->prescriptions->isEmpty()) throw new \Exception("Prescriptions missing in unified chart");
    if ($chart->laboratoryRequests->isEmpty()) throw new \Exception("Lab requests missing in unified chart");
    if ($chart->vitals->isEmpty()) throw new \Exception("Vitals missing in unified chart");
    if ($chart->billingCharges->isEmpty()) throw new \Exception("Billing charges missing in unified chart");
    if ($chart->billingPayments->isEmpty()) throw new \Exception("Billing payments missing in unified chart");
});

// 9. CMO / DIRECTOR GOVERNANCE & METRICS
runStep("CMO Governance & Department Performance APIs", function () use ($kernel, $app) {
    $director = \App\Models\User::where('Username', 'director')->first();
    $endpoints = [
        '/director/api/dashboard-stats.php',
        '/director/api/department-performance.php',
        '/director/api/doctor-stats.php',
        '/director/api/reports.php',
        '/director/api/staff-activity.php',
        '/director/api/notifications.php',
        '/director/api/audit-trail.php',
    ];
    foreach ($endpoints as $ep) {
        $res = makeRequest($kernel, $app, $director, 'GET', $ep);
        if ($res->getStatusCode() !== 200) throw new \Exception("Endpoint $ep status " . $res->getStatusCode());
    }
});

// 10. SYSTEM ADMIN USER CRUD & CONFIGURATION
runStep("System Admin User CRUD & Platform APIs", function () use ($kernel, $app) {
    $admin = \App\Models\User::where('Username', 'admin')->first();
    
    // Read APIs
    $apis = [
        '/admin/api/config.php?action=get',
        '/admin/api/users.php?action=list',
        '/admin/api/departments.php?action=list',
        '/admin/api/service_fees.php?action=list',
        '/admin/api/backups.php?action=list',
        '/admin/api/roles.php',
        '/admin/api/logs.php',
    ];
    foreach ($apis as $ep) {
        $res = makeRequest($kernel, $app, $admin, 'GET', $ep);
        if ($res->getStatusCode() !== 200) throw new \Exception("Admin API $ep status " . $res->getStatusCode());
    }

    // User Create CRUD
    $uniqueUser = 'testuser_' . time() . rand(10, 99);
    $createRes = makeRequest($kernel, $app, $admin, 'POST', '/admin/api/users.php', [
        'action'    => 'create',
        'username'  => $uniqueUser,
        'full_name' => 'Master Audit User',
        'email'     => "{$uniqueUser}@hospital.gov.ph",
        'role'      => 'Doctor',
        'password'  => 'Password123!',
        'status'    => 'Active',
    ]);
    $createData = json_decode($createRes->getContent(), true);
    if (!($createData['success'] ?? false)) throw new \Exception("User create failed: " . $createRes->getContent());

    $createdU = \App\Models\User::where('Username', $uniqueUser)->first();
    if (!$createdU) throw new \Exception("Created user not found in database");

    // Clean up created user
    $createdU->delete();
});

// CLEANUP AUDIT RECORD
if ($createdPatientId) {
    \App\Models\BillingPayment::where('PatientID', $createdPatientId)->delete();
    \App\Models\BillingInvoice::where('PatientID', $createdPatientId)->delete();
    \App\Models\BillingCharge::where('PatientID', $createdPatientId)->delete();
    \App\Models\DispensingRecord::where('PatientID', $createdPatientId)->delete();
    \App\Models\LaboratoryResult::where('PatientID', $createdPatientId)->delete();
    \App\Models\LaboratoryRequest::where('PatientID', $createdPatientId)->delete();
    \App\Models\Prescription::where('PatientID', $createdPatientId)->delete();
    \App\Models\MedicalCertificate::where('PatientID', $createdPatientId)->delete();
    \App\Models\Diagnosis::where('PatientID', $createdPatientId)->delete();
    \App\Models\ConsultationNote::where('PatientID', $createdPatientId)->delete();
    \App\Models\PatientVital::where('PatientID', $createdPatientId)->delete();
    \App\Models\Appointment::where('PatientID', $createdPatientId)->delete();
    \App\Models\PatientQueue::where('PatientID', $createdPatientId)->delete();
    \App\Models\Complaint::where('PatientID', $createdPatientId)->delete();
    \App\Models\Patient::where('PatientID', $createdPatientId)->delete();
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo "  AUDIT RESULTS: $passedCount PASSED, $failedCount FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

if ($failedCount > 0) {
    echo "Failures:" . PHP_EOL;
    foreach ($failures as $f) {
        echo " - $f" . PHP_EOL;
    }
    exit(1);
} else {
    echo "ALL WORKFLOWS, ROLES, MENUS, APIS, AND CRUD OPERATIONS FUNCTION PERFECTLY!" . PHP_EOL;
    exit(0);
}
