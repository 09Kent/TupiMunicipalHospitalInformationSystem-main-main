<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "====================================================\n";
echo "TMHIMS UNIVERSAL PRINT ENGINE COMPREHENSIVE TEST\n";
echo "====================================================\n";

$initialReq = \Illuminate\Http\Request::create('/', 'GET');
$app->instance('request', $initialReq);
$adminUser = \App\Models\User::first() ?: new \App\Models\User(['UserID' => 1, 'Username' => 'admin', 'Role' => 'Admin']);
auth()->setUser($adminUser);

// Ensure test patient exists
$patient = \App\Models\Patient::first();
if (!$patient) {
    $patient = \App\Models\Patient::create([
        'PatientCode' => 'TMH-TEST-001',
        'FirstName' => 'Test',
        'LastName' => 'Patient',
        'DateOfBirth' => '1995-05-15',
        'Gender' => 'Male',
        'BloodType' => 'O+',
        'PatientCategory' => 'Inpatient',
        'Status' => 'Admitted'
    ]);
}
$patientId = $patient->PatientID;

$tests = [
    'patient' => $patientId,
    'registration' => $patientId,
    'consultation' => 1,
    'medical-records' => $patientId,
    'laboratory' => 1,
    'prescription' => 1,
    'billing' => 1,
    'payment' => 1,
    'referral' => 1,
    'reports' => 1,
    'logs' => 1,
];

$passed = 0;
$failed = 0;

foreach ($tests as $type => $id) {
    $url = "/print/{$type}/{$id}";
    $request = \Illuminate\Http\Request::create($url, 'GET');
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $content = $response->getContent();

    $hasHeader = strpos($content, 'TUPI MUNICIPAL HOSPITAL') !== false;
    $hasPrintScript = strpos($content, 'window.print()') !== false;
    $hasDocTitle = strpos($content, 'OFFICIAL') !== false || strpos($content, 'DOCUMENT') !== false || strpos($content, 'REPORT') !== false || strpos($content, 'STATEMENT') !== false;

    if ($status === 200 && $hasHeader && $hasPrintScript) {
        echo "[PASS] Type: {$type} (URL: {$url}) -> HTTP 200, Header OK, Print Script OK\n";
        $passed++;
    } else {
        echo "[FAIL] Type: {$type} (URL: {$url}) -> HTTP {$status}, Header: " . ($hasHeader ? 'YES' : 'NO') . ", Print Script: " . ($hasPrintScript ? 'YES' : 'NO') . "\n";
        $failed++;
    }
}

// Check print_logs table
$logCount = \Illuminate\Support\Facades\DB::table('print_logs')->count();
echo "\nTotal Print Logs in database: {$logCount}\n";

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";

if ($failed === 0) {
    echo ">>> ALL 11 UNIVERSAL PRINT TYPES WORKING PERFECTLY! <<<\n";
    exit(0);
} else {
    exit(1);
}
