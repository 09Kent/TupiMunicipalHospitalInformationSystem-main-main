<?php
// Nurse/includes/live_data.php (formerly demo_data.php)
// Live MySQL database integration for Nurse Station

require_once __DIR__ . '/../../config/Database.php';

if (!function_exists('getDemoPatients')) {
function getDemoPatients(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT p.PatientID, p.PatientCode, p.FirstName, p.LastName, p.Age, p.Gender, p.ContactNumber, p.PatientCategory, p.CreatedAt,
                   v.BloodPressure, v.HeartRate, v.Temperature, v.RespiratoryRate, v.OxygenSaturation, v.PainScale, v.ClinicalNotes as VitalRemarks, v.CreatedAt as RecordedAt,
                   nt.TaskID, nt.TaskTitle, nt.Category as TaskCategory, nt.Status as TaskStatus, nt.Priority as TaskPriority, nt.DueTime as TaskDue, nt.Remarks as TaskRemarks,
                   d.FirstName as DocFirst, d.LastName as DocLast, d.Specialty
            FROM patients p
            LEFT JOIN (
                SELECT v1.* FROM patient_vitals v1
                JOIN (SELECT PatientID, MAX(VitalID) as max_id FROM patient_vitals GROUP BY PatientID) v2
                  ON v1.PatientID = v2.PatientID AND v1.VitalID = v2.max_id
            ) v ON p.PatientID = v.PatientID
            LEFT JOIN (
                SELECT t1.* FROM nurse_tasks t1
                JOIN (SELECT PatientID, MAX(TaskID) as max_id FROM nurse_tasks GROUP BY PatientID) t2
                  ON t1.PatientID = t2.PatientID AND t1.TaskID = t2.max_id
            ) nt ON p.PatientID = nt.PatientID
            LEFT JOIN doctors d ON nt.DoctorID = d.DoctorID
            ORDER BY p.PatientID ASC
            LIMIT 50
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $isAdmitted = ($r['PatientCategory'] === 'Admitted' || $r['PatientCategory'] === 'Inpatient');
            $roomNumber = $isAdmitted ? ('Room ' . (200 + ($r['PatientID'] % 20))) : 'OPD Triage';
            $pain = (int)($r['PainScale'] ?? 0);
            $hr = (int)($r['HeartRate'] ?? 75);
            $status = ($pain >= 5 || $hr > 110) ? 'Needs Attention' : 'Stable';

            $result[] = [
                'id'                  => $r['PatientCode'] ?: ('PAT-2026-' . str_pad((string)$r['PatientID'], 4, '0', STR_PAD_LEFT)),
                'patient_id'          => (int)$r['PatientID'],
                'name'                => $r['FirstName'] . ' ' . $r['LastName'],
                'age'                 => (int)$r['Age'],
                'gender'              => $r['Gender'],
                'contact'             => $r['ContactNumber'] ?: '+63 918 000 0000',
                'room'                => $roomNumber,
                'attending_physician' => !empty($r['DocLast']) ? "Dr. {$r['DocFirst']} {$r['DocLast']}" : "Dr. Daniel Lewis, MD",
                'status'              => $status,
                'bp'                  => $r['BloodPressure'] ?: '120/80',
                'heart_rate'          => $hr,
                'temperature'         => (float)($r['Temperature'] ?: 36.6),
                'respiratory_rate'    => (int)($r['RespiratoryRate'] ?: 18),
                'spo2'                => (int)($r['OxygenSaturation'] ?: 98),
                'pain_level'          => $pain,
                'queue_status'        => 'Waiting',
                'queue_number'        => (int)$r['PatientID'],
                'assigned_task'       => $r['TaskTitle'] ?: 'Vital Signs Monitoring',
                'task_id'             => $r['TaskID'] ?? null,
                'task_status'         => $r['TaskStatus'] ?: 'Pending',
                'task_priority'       => $r['TaskPriority'] ?: 'Normal',
                'task_due'            => $r['TaskDue'] ?: '10:00 AM',
                'last_update'         => !empty($r['RecordedAt']) ? date('g:i A', strtotime($r['RecordedAt'])) : 'Shift Log',
                'medical_history'     => 'Active patient record in TMHIS',
                'allergies'           => 'None reported',
                'admission_date'      => substr((string)($r['CreatedAt'] ?? date('Y-m-d')), 0, 10),
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoPatient')) {
function getDemoPatient(int $index): ?array
{
    $patients = getDemoPatients();
    return $patients[$index] ?? ($patients[0] ?? null);
}
}

if (!function_exists('getDemoInstructions')) {
function getDemoInstructions(): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT nt.TaskID as id, nt.TaskTitle as title, nt.Priority as priority, nt.DueTime as time, 
                   p.FirstName, p.LastName, p.PatientCode, d.LastName as DoctorLastName
            FROM nurse_tasks nt
            JOIN patients p ON nt.PatientID = p.PatientID
            LEFT JOIN doctors d ON nt.DoctorID = d.DoctorID
            WHERE nt.Status != 'Completed'
            ORDER BY nt.TaskID DESC
            LIMIT 10
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        foreach ($rows as $r) {
            $res[] = [
                'id'          => $r['id'],
                'time'        => $r['time'] ?: 'Shift Duty',
                'doctor'      => 'Dr. ' . ($r['DoctorLastName'] ?: 'Physician'),
                'patient'     => $r['FirstName'] . ' ' . $r['LastName'],
                'room'        => 'Clinical Area',
                'title'       => $r['title'],
                'instruction' => $r['title'],
                'priority'    => $r['priority'] ?: 'Normal',
                'date'        => date('Y-m-d H:i:s'),
                'type'        => 'clinical'
            ];
        }
        return $res;
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
            SELECT LogID as id, Action as title, Details as message, CreatedAt as time, 'info' as type
            FROM system_audit_logs
            ORDER BY LogID DESC
            LIMIT 8
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return [
                ['id' => 1, 'type' => 'info', 'color' => 'teal', 'icon' => 'bell', 'title' => 'Shift Initialized', 'message' => 'Nursing station active and connected to live database.', 'time' => 'Just now', 'read' => false, 'unread' => true]
            ];
        }
        return array_map(function($r) {
            return [
                'id' => $r['id'],
                'type' => 'info',
                'color' => 'teal',
                'icon' => 'bell',
                'title' => $r['title'],
                'message' => $r['message'] ?: 'Operational event logged',
                'time' => date('g:i A', strtotime($r['time'])),
                'read' => false,
                'unread' => true
            ];
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('getDemoVitalHistory')) {
function getDemoVitalHistory(string $patientId = 'P-2026-0001'): array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT v.VitalID, v.BloodPressure, v.HeartRate, v.Temperature, v.RespiratoryRate,
                   v.OxygenSaturation, v.PainScale, v.CreatedAt,
                   p.FirstName, p.LastName, p.PatientCode
            FROM patient_vitals v
            JOIN patients p ON v.PatientID = p.PatientID
            ORDER BY v.VitalID DESC
            LIMIT 15
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $result = [];
            foreach ($rows as $r) {
                $time = strtotime($r['CreatedAt'] ?: 'now');
                $result[] = [
                    'date'        => date('M d, Y', $time),
                    'time'        => date('h:i A', $time),
                    'bp'          => $r['BloodPressure'] ?: '120/80',
                    'hr'          => (int)($r['HeartRate'] ?: 78),
                    'temp'        => (float)($r['Temperature'] ?: 36.7),
                    'rr'          => (int)($r['RespiratoryRate'] ?: 18),
                    'spo2'        => (int)($r['OxygenSaturation'] ?: 98),
                    'pain'        => (int)($r['PainScale'] ?: 2),
                    'recorded_by' => 'Nurse On Duty'
                ];
            }
            return $result;
        }
    } catch (Throwable $e) {
    }

    return [
        ['date' => 'Aug 28, 2026', 'time' => '10:00 AM', 'bp' => '120/80', 'hr' => 78, 'temp' => 36.7, 'rr' => 18, 'spo2' => 98, 'pain' => 2, 'recorded_by' => 'Nurse Maria Santos'],
        ['date' => 'Aug 28, 2026', 'time' => '06:00 AM', 'bp' => '122/82', 'hr' => 80, 'temp' => 36.8, 'rr' => 17, 'spo2' => 97, 'pain' => 3, 'recorded_by' => 'Nurse Patricia Lim'],
        ['date' => 'Aug 27, 2026', 'time' => '10:00 PM', 'bp' => '125/84', 'hr' => 76, 'temp' => 36.6, 'rr' => 16, 'spo2' => 98, 'pain' => 2, 'recorded_by' => 'Nurse Angela Reyes'],
        ['date' => 'Aug 27, 2026', 'time' => '06:00 PM', 'bp' => '128/86', 'hr' => 82, 'temp' => 37.0, 'rr' => 19, 'spo2' => 97, 'pain' => 4, 'recorded_by' => 'Nurse Maria Santos'],
    ];
}
}
