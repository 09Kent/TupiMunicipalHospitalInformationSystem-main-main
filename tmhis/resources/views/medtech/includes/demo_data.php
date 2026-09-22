<?php
// Med_Tech/includes/live_data.php (formerly demo_data.php)
// Live MySQL database integration for Medical Technologist Portal
// Tupi Municipal Hospital Information Management System

require_once __DIR__ . '/../../config/Database.php';

if (!function_exists('getDemoPatients')) {
function getDemoPatients(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT p.PatientID, p.PatientCode, p.FirstName, p.LastName, p.Age, p.Gender, p.ContactNumber, p.PatientCategory,
                   d.FirstName as DocFirst, d.LastName as DocLast, d.Specialty
            FROM patients p
            LEFT JOIN doctors d ON (p.PatientID % 5) + 11 = d.DoctorID
            ORDER BY p.PatientID ASC
            LIMIT 50
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $isAdmitted = ($r['PatientCategory'] === 'Admitted' || $r['PatientCategory'] === 'Inpatient');
            $roomNumber = $isAdmitted ? ('Room ' . (200 + ($r['PatientID'] % 20))) : 'OPD Triage';
            $doc = !empty($r['DocLast']) ? "Dr. {$r['DocFirst']} {$r['DocLast']} ({$r['Specialty']})" : "Dr. Michael Reyes, MD (Internal Medicine)";

            $result[] = [
                'id'         => $r['PatientCode'] ?: ('P-2026-' . str_pad((string)$r['PatientID'], 4, '0', STR_PAD_LEFT)),
                'name'       => $r['FirstName'] . ' ' . $r['LastName'],
                'age'        => (int)$r['Age'],
                'gender'     => $r['Gender'],
                'contact'    => $r['ContactNumber'] ?: '+63 917 123 4567',
                'blood_type' => 'O+',
                'room'       => $roomNumber,
                'doctor'     => $doc,
                'diagnosis'  => 'Clinical laboratory evaluation'
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoTestCatalog')) {
function getDemoTestCatalog(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT CatalogID, TestCode, TestName, Category, SpecimenType, TurnaroundTime, StandardPrice, Status
            FROM test_catalog
            ORDER BY CatalogID ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'test_id'     => $r['TestCode'] ?: ('TST-' . str_pad((string)$r['CatalogID'], 3, '0', STR_PAD_LEFT)),
                'test_name'   => $r['TestName'],
                'category'    => $r['Category'],
                'specimen'    => $r['SpecimenType'] ?: 'Whole Blood (EDTA)',
                'volume'      => '2.0 mL',
                'tat'         => $r['TurnaroundTime'] ?: '1 - 2 Hours',
                'section'     => $r['Category'] . ' Section',
                'price'       => '₱ ' . number_format((float)$r['StandardPrice'], 2),
                'status'      => $r['Status'],
                'description' => 'Standard clinical laboratory test protocol.'
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoReferenceRanges')) {
function getDemoReferenceRanges(): array
{
    return [
        ['test' => 'Complete Blood Count', 'parameter' => 'Hemoglobin', 'gender' => 'Male', 'age_range' => '18–65', 'min' => 13.0, 'max' => 17.5, 'unit' => 'g/dL', 'crit_low' => 7.0, 'crit_high' => 20.0],
        ['test' => 'Complete Blood Count', 'parameter' => 'Hemoglobin', 'gender' => 'Female', 'age_range' => '18–65', 'min' => 12.0, 'max' => 15.5, 'unit' => 'g/dL', 'crit_low' => 7.0, 'crit_high' => 19.0],
        ['test' => 'Complete Blood Count', 'parameter' => 'Hematocrit', 'gender' => 'Male', 'age_range' => '18–65', 'min' => 40.0, 'max' => 52.0, 'unit' => '%', 'crit_low' => 21.0, 'crit_high' => 60.0],
        ['test' => 'Complete Blood Count', 'parameter' => 'Hematocrit', 'gender' => 'Female', 'age_range' => '18–65', 'min' => 36.0, 'max' => 48.0, 'unit' => '%', 'crit_low' => 21.0, 'crit_high' => 55.0],
        ['test' => 'Complete Blood Count', 'parameter' => 'WBC Count', 'gender' => 'All', 'age_range' => 'Adult', 'min' => 4.5, 'max' => 11.0, 'unit' => 'x10⁹/L', 'crit_low' => 2.0, 'crit_high' => 30.0],
        ['test' => 'Complete Blood Count', 'parameter' => 'Platelet Count', 'gender' => 'All', 'age_range' => 'Adult', 'min' => 150.0, 'max' => 450.0, 'unit' => 'x10⁹/L', 'crit_low' => 50.0, 'crit_high' => 1000.0],
        ['test' => 'Fasting Blood Sugar', 'parameter' => 'Glucose (FBS)', 'gender' => 'All', 'age_range' => 'Adult', 'min' => 70.0, 'max' => 100.0, 'unit' => 'mg/dL', 'crit_low' => 45.0, 'crit_high' => 400.0],
        ['test' => 'Lipid Profile', 'parameter' => 'Total Cholesterol', 'gender' => 'All', 'age_range' => 'Adult', 'min' => 0.0, 'max' => 200.0, 'unit' => 'mg/dL', 'crit_low' => 0.0, 'crit_high' => 350.0],
        ['test' => 'Kidney Function Panel', 'parameter' => 'Serum Creatinine', 'gender' => 'Male', 'age_range' => 'Adult', 'min' => 0.7, 'max' => 1.3, 'unit' => 'mg/dL', 'crit_low' => 0.4, 'crit_high' => 5.0],
        ['test' => 'Liver Function Test', 'parameter' => 'SGPT / ALT', 'gender' => 'All', 'age_range' => 'Adult', 'min' => 0.0, 'max' => 45.0, 'unit' => 'U/L', 'crit_low' => 0.0, 'crit_high' => 300.0],
        ['test' => 'Electrolytes', 'parameter' => 'Serum Potassium (K+)', 'gender' => 'All', 'age_range' => 'Adult', 'min' => 3.5, 'max' => 5.1, 'unit' => 'mmol/L', 'crit_low' => 2.8, 'crit_high' => 6.2]
    ];
}
}

if (!function_exists('getDemoLabRequests')) {
function getDemoLabRequests(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT r.RequestID, r.RequestCode, r.TestType, r.Priority, r.ClinicalNotes, r.Status, r.RequestedDate, r.CreatedAt,
                   p.PatientCode, p.FirstName as PatFirst, p.LastName as PatLast, p.Age, p.Gender,
                   d.FirstName as DocFirst, d.LastName as DocLast,
                   s.SampleBarcode
            FROM laboratory_requests r
            JOIN patients p ON r.PatientID = p.PatientID
            LEFT JOIN doctors d ON r.DoctorID = d.DoctorID
            LEFT JOIN laboratory_samples s ON r.RequestID = s.RequestID
            ORDER BY r.RequestID DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $docName = !empty($r['DocLast']) ? "Dr. {$r['DocFirst']} {$r['DocLast']}" : "Dr. Michael Reyes";
            $sampleCode = $r['SampleBarcode'] ?: ('SMP-2026-' . str_pad((string)$r['RequestID'], 5, '0', STR_PAD_LEFT));
            
            // Map status
            $status = $r['Status'];
            if ($status === 'Sample Collected') {
                $status = 'Received';
            } elseif ($status === 'In Progress') {
                $status = 'Processing';
            }

            $result[] = [
                'request_id'     => $r['RequestCode'] ?: ('LAB-2026-' . str_pad((string)$r['RequestID'], 3, '0', STR_PAD_LEFT)),
                'patient_id'     => $r['PatientCode'] ?: ('P-2026-' . str_pad((string)$r['RequestID'], 4, '0', STR_PAD_LEFT)),
                'patient_name'   => $r['PatFirst'] . ' ' . $r['PatLast'],
                'age'            => (int)$r['Age'],
                'gender'         => $r['Gender'],
                'doctor'         => $docName,
                'test'           => $r['TestType'],
                'test_id'        => 'TST-' . str_pad((string)($r['RequestID'] % 12 + 1), 3, '0', STR_PAD_LEFT),
                'priority'       => $r['Priority'],
                'date'           => $r['RequestedDate'] ?: substr((string)$r['CreatedAt'], 0, 10),
                'time'           => date('g:i A', strtotime($r['CreatedAt'] ?: 'now')),
                'status'         => $status,
                'sample_id'      => $sampleCode,
                'clinical_notes' => $r['ClinicalNotes'] ?: 'Evaluate clinical laboratory indicators.',
                'category'       => 'Laboratory Analysis'
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoSamples')) {
function getDemoSamples(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT s.SampleID, s.SampleBarcode, s.SpecimenType, s.CollectionDate, s.CollectedBy, s.ProcessingStatus, s.StorageLocation, s.Notes,
                   r.RequestCode, r.TestType,
                   p.PatientCode, p.FirstName as PatFirst, p.LastName as PatLast
            FROM laboratory_samples s
            JOIN laboratory_requests r ON s.RequestID = r.RequestID
            JOIN patients p ON s.PatientID = p.PatientID
            ORDER BY s.SampleID DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $s) {
            $stage = 2;
            $status = $s['ProcessingStatus'];
            if ($status === 'Collected') {
                $stage = 0;
            } elseif ($status === 'In Lab') {
                $stage = 1;
                $status = 'Received';
            } elseif ($status === 'Processing') {
                $stage = 2;
            } elseif ($status === 'Analyzed') {
                $stage = 3;
                $status = 'For Verification';
            } elseif ($status === 'Completed') {
                $stage = 4;
            } elseif ($status === 'Rejected') {
                $stage = 5;
            }

            $result[] = [
                'sample_id'       => $s['SampleBarcode'] ?: ('SMP-2026-' . str_pad((string)$s['SampleID'], 5, '0', STR_PAD_LEFT)),
                'request_id'      => $s['RequestCode'] ?: ('LAB-2026-' . str_pad((string)$s['SampleID'], 3, '0', STR_PAD_LEFT)),
                'patient_name'    => $s['PatFirst'] . ' ' . $s['PatLast'],
                'patient_id'      => $s['PatientCode'] ?: ('P-2026-' . str_pad((string)$s['SampleID'], 4, '0', STR_PAD_LEFT)),
                'test'            => $s['TestType'] ?: 'Clinical Chemistry',
                'sample_type'     => $s['SpecimenType'] ?: 'Serum (SST)',
                'collection_date' => substr((string)$s['CollectionDate'], 0, 10),
                'collection_time' => date('g:i A', strtotime((string)$s['CollectionDate'])),
                'collected_by'    => $s['CollectedBy'] ?: 'Nurse On Duty, RN',
                'condition'       => 'Acceptable',
                'status'          => $status,
                'stage_index'     => $stage,
                'section'         => 'Chemistry Section',
                'rack_location'   => $s['StorageLocation'] ?: 'RACK-CHEM-A01',
                'notes'           => $s['Notes'] ?: 'Specimen handled according to laboratory protocol.'
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoLabResults')) {
function getDemoLabResults(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT res.ResultID, res.TestName, res.ResultValue, res.NormalRange, res.Units, res.Interpretation, res.Notes, res.ResultDate, res.CreatedAt,
                   r.RequestCode,
                   p.PatientCode, p.FirstName as PatFirst, p.LastName as PatLast, p.Age, p.Gender,
                   d.FirstName as DocFirst, d.LastName as DocLast,
                   s.SampleBarcode
            FROM laboratory_results res
            JOIN laboratory_requests r ON res.RequestID = r.RequestID
            JOIN patients p ON res.PatientID = p.PatientID
            LEFT JOIN doctors d ON res.DoctorID = d.DoctorID
            LEFT JOIN laboratory_samples s ON res.RequestID = s.RequestID
            ORDER BY res.ResultID DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $res) {
            $status = ($res['Interpretation'] === 'Normal' || $res['Interpretation'] === 'Critical') ? 'Completed' : 'For Verification';
            $docName = !empty($res['DocLast']) ? "Dr. {$res['DocFirst']} {$res['DocLast']}" : "Dr. Michael Reyes";

            $result[] = [
                'result_id'      => 'RES-2026-' . str_pad((string)$res['ResultID'], 3, '0', STR_PAD_LEFT),
                'request_id'     => $res['RequestCode'] ?: ('LAB-2026-' . str_pad((string)$res['ResultID'], 3, '0', STR_PAD_LEFT)),
                'sample_id'      => $res['SampleBarcode'] ?: ('SMP-2026-' . str_pad((string)$res['ResultID'], 5, '0', STR_PAD_LEFT)),
                'patient_id'     => $res['PatientCode'] ?: ('P-2026-' . str_pad((string)$res['ResultID'], 4, '0', STR_PAD_LEFT)),
                'patient_name'   => $res['PatFirst'] . ' ' . $res['PatLast'],
                'age'            => (int)$res['Age'],
                'gender'         => $res['Gender'],
                'doctor'         => $docName,
                'test'           => $res['TestName'],
                'date'           => $res['ResultDate'] ?: substr((string)$res['CreatedAt'], 0, 10),
                'time'           => date('g:i A', strtotime((string)$res['CreatedAt'])),
                'technologist'   => 'Robert Santos, RMT (PRC: 0084920)',
                'pathologist'    => 'Dr. Vicente Gomez, MD, FPSP (Pathologist)',
                'status'         => $status,
                'summary'        => "{$res['TestName']}: {$res['ResultValue']} {$res['Units']} ({$res['Interpretation']})",
                'parameters'     => [
                    [
                        'name'  => $res['TestName'],
                        'value' => $res['ResultValue'],
                        'unit'  => $res['Units'],
                        'range' => $res['NormalRange'],
                        'flag'  => $res['Interpretation']
                    ]
                ],
                'interpretation' => $res['Notes'] ?: "Diagnostic evaluation shows {$res['TestName']} at {$res['ResultValue']} {$res['Units']}."
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoRecentActivity')) {
function getDemoRecentActivity(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT LogID, Action, Details, CreatedAt
            FROM system_audit_logs
            ORDER BY LogID DESC
            LIMIT 6
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'type'   => 'result',
                'title'  => $r['Action'],
                'detail' => $r['Details'] ?: 'Laboratory audit event logged',
                'time'   => date('g:i A', strtotime($r['CreatedAt'])),
                'icon'   => 'file-check'
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoNotifications')) {
function getDemoNotifications(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT r.RequestID, r.RequestCode, r.TestType, r.Priority, p.FirstName, p.LastName
            FROM laboratory_requests r
            JOIN patients p ON r.PatientID = p.PatientID
            WHERE r.Priority IN ('STAT', 'Urgent')
            ORDER BY r.RequestID DESC
            LIMIT 4
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'id'          => 'NOTIF-' . $r['RequestID'],
                'title'       => "{$r['Priority']} Laboratory Request",
                'message'     => "Patient {$r['FirstName']} {$r['LastName']} requires {$r['TestType']}.",
                'time'        => 'Recent',
                'type'        => ($r['Priority'] === 'STAT') ? 'critical' : 'warning',
                'unread'      => true,
                'action_link' => 'requests'
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}
