<?php
// Doctor/views/diagnosis/index.php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/ConsultationNote.php';
require_once __DIR__ . '/../models/Diagnosis.php';
require_once __DIR__ . '/../models/TreatmentPlan.php';

$pageTitle = 'Diagnosis & Treatment | Doctor Portal • Tupi Municipal Hospital';
$activeMenu = 'diagnosis';

$currentUser = Session::getCurrentUser();
$doctorId = $currentUser['doctor_id'] ?? (int)session('doctor_id', 1);

$patientModel = new Patient();
$consultationModel = new Consultation();
$noteModel = new ConsultationNote();
$diagnosisModel = new Diagnosis();
$treatmentModel = new TreatmentPlan();

// Selected patient ID from query, post, or active patient
$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? (function_exists('request') ? request('patient_id', 0) : 0));
$appId = (int)($_POST['appointment_id'] ?? $_POST['app_id'] ?? $_GET['appointment_id'] ?? $_GET['app_id'] ?? (function_exists('request') ? (request('appointment_id') ?: request('app_id', 0)) : 0));

if ($patientId <= 0 && $appId > 0) {
    try {
        $stmtApp = Database::getConnection()->prepare("SELECT PatientID FROM appointments WHERE AppointmentID = :aid LIMIT 1");
        $stmtApp->execute([':aid' => $appId]);
        $patIdFromApp = $stmtApp->fetchColumn();
        if ($patIdFromApp) {
            $patientId = (int)$patIdFromApp;
        }
    } catch (\Throwable $e) {}
}

// Fetch all doctor patients for selector
$patientList = $patientModel->getDoctorPatients($doctorId, 50, 0);

// If patientId passed, verify doctor access
if ($patientId > 0 && !$patientModel->hasDoctorAccess($doctorId, $patientId)) {
    Session::setFlash('error', 'Access Denied: You are not authorized to access this patient\'s consultation chart.');
    header('Location: ' . doctor_url('views/diagnosis/index.php'));
    if (function_exists('app') && app()->environment('testing')) { return; }
    exit;
}

// Default to first accessible patient only if none selected AND none posted
if ($patientId === 0 && !empty($patientList)) {
    $patientId = (int)$patientList[0]['PatientID'];
    $appId = (int)($patientList[0]['AppointmentID'] ?? 0);
}

// If start parameter passed, verify patient access before starting consultation
if (!empty($_GET['start']) && $appId > 0 && $patientId > 0 && $patientModel->hasDoctorAccess($doctorId, $patientId)) {
    $consultationModel->startConsultation($appId, $doctorId);
}

// Fetch selected patient's full details
$patient = ($patientId && $patientModel->hasDoctorAccess($doctorId, $patientId)) ? $patientModel->getFullProfile($patientId) : null;
$existingNotes = ($patientId && $patientModel->hasDoctorAccess($doctorId, $patientId)) ? $noteModel->findByPatient($patientId) : [];
$existingDiagnoses = ($patientId && $patientModel->hasDoctorAccess($doctorId, $patientId)) ? $diagnosisModel->findByPatient($patientId) : [];
$existingTreatmentPlans = ($patientId && $patientModel->hasDoctorAccess($doctorId, $patientId)) ? $treatmentModel->findByPatient($patientId) : [];
$patientTimeline = ($patientId > 0) ? \App\Services\Clinical\PatientTimelineService::getTimeline($patientId) : [];
$latestNoteId = !empty($existingNotes) ? (int)$existingNotes[0]['NoteID'] : 0;

// Active Doctor & Nurse Assignment & Tasks
$assignedDoctorName = ($patient['Appointment']['DoctorFirstName'] ?? '') ? ('Dr. ' . $patient['Appointment']['DoctorFirstName'] . ' ' . $patient['Appointment']['DoctorLastName']) : 'Dr. Juan Dela Cruz';
$assignedNurseName = 'Nurse Maria Santos (General Care)';
$currentTaskSummary = 'Inpatient Monitoring & Vitals';
if ($patientId > 0) {
    try {
        $stmtNurse = Database::getConnection()->prepare("
            SELECT na.*, u.full_name as NurseName 
            FROM nurse_assignments na 
            LEFT JOIN users u ON na.nurse_id = u.id 
            WHERE na.patient_id = :pid AND na.status = 'Active' 
            ORDER BY na.id DESC LIMIT 1
        ");
        $stmtNurse->execute([':pid' => $patientId]);
        $activeNurseRow = $stmtNurse->fetch();
        if ($activeNurseRow && !empty($activeNurseRow['NurseName'])) {
            $assignedNurseName = 'Nurse ' . $activeNurseRow['NurseName'];
            if (!empty($activeNurseRow['task_summary'])) {
                $currentTaskSummary = $activeNurseRow['task_summary'];
            }
        }
        
        $stmtTask = Database::getConnection()->prepare("
            SELECT * FROM nurse_tasks 
            WHERE patient_id = :pid 
            ORDER BY CASE WHEN status = 'Due' THEN 1 WHEN status = 'Scheduled' THEN 2 ELSE 3 END, id DESC 
            LIMIT 1
        ");
        $stmtTask->execute([':pid' => $patientId]);
        $taskRow = $stmtTask->fetch();
        if ($taskRow) {
            $currentTaskSummary = $taskRow['task_type'] . ': ' . ($taskRow['medication_name'] ?: $taskRow['description']);
        }
    } catch (\Throwable $e) {}
}

// Determine Clinical Stage
$stages = [
    ['key' => 'registered', 'label' => 'Registered', 'done' => true],
    ['key' => 'verified', 'label' => 'Verified', 'done' => true],
    ['key' => 'waiting', 'label' => 'Queue / Vitals', 'done' => true],
    ['key' => 'assigned', 'label' => 'Assigned Doctor', 'done' => !empty($patient['Appointment']['DoctorID']) || $doctorId > 0],
    ['key' => 'consultation', 'label' => 'Consultation', 'done' => !empty($existingNotes), 'active' => empty($existingDiagnoses)],
    ['key' => 'diagnosis', 'label' => 'Diagnosis', 'done' => !empty($existingDiagnoses), 'active' => !empty($existingDiagnoses) && empty($existingTreatmentPlans)],
    ['key' => 'treatment', 'label' => 'Treatment', 'done' => !empty($existingTreatmentPlans), 'active' => !empty($existingTreatmentPlans) && empty($patient['Prescriptions'])],
    ['key' => 'prescription', 'label' => 'Prescription', 'done' => !empty($patient['Prescriptions']), 'active' => !empty($patient['Prescriptions']) && ($patient['Appointment']['Status'] ?? '') !== 'Completed'],
    ['key' => 'completed', 'label' => 'Completed', 'done' => ($patient['Appointment']['Status'] ?? '') === 'Completed', 'active' => ($patient['Appointment']['Status'] ?? '') === 'Completed']
];

// Handle Form Submissions (Create Note, Diagnosis, Treatment Plan)
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validate_csrf($csrf)) {
        $errorMessage = 'Security validation token expired. Please try again.';
    } elseif ($patientId <= 0 || !$patientModel->hasDoctorAccess($doctorId, $patientId)) {
        $errorMessage = 'Access Denied: You are not authorized to modify clinical records for this patient.';
    } elseif ($action === 'save_soap_note') {
        $subjective = trim($_POST['subjective'] ?? '');
        $objective = trim($_POST['objective'] ?? '');
        $assessment = trim($_POST['assessment'] ?? '');
        $plan = trim($_POST['plan'] ?? '');
        $clinicalNotes = trim($_POST['clinical_notes'] ?? "$subjective $assessment $plan");

        $vitals = [
            'bp'     => $_POST['vital_bp'] ?? '',
            'hr'     => $_POST['vital_hr'] ?? '',
            'temp'   => $_POST['vital_temp'] ?? '',
            'spo2'   => $_POST['vital_spo2'] ?? '',
            'weight' => $_POST['vital_weight'] ?? ''
        ];

        if ($appId <= 0 && !empty($patient['Appointment']['AppointmentID'])) {
            $appId = (int)$patient['Appointment']['AppointmentID'];
        }
        if ($appId <= 0) {
            $stmtFindApp = Database::getConnection()->prepare("
                SELECT AppointmentID FROM appointments 
                WHERE PatientID = :pid 
                ORDER BY AppointmentID DESC LIMIT 1
            ");
            $stmtFindApp->execute([':pid' => $patientId]);
            $appId = (int)$stmtFindApp->fetchColumn();
        }

        // Check if existing note already exists for this patient/appointment to update rather than duplicate
        $existingNoteForApp = ($appId > 0) ? $noteModel->findByAppointment($appId) : null;
        if (!$existingNoteForApp && !empty($existingNotes)) {
            $existingNoteForApp = $existingNotes[0];
        }

        if ($existingNoteForApp && !empty($existingNoteForApp['NoteID'])) {
            $noteId = (int)$existingNoteForApp['NoteID'];
            $noteModel->update($noteId, [
                'subjective'     => $subjective,
                'objective'      => $objective,
                'assessment'     => $assessment,
                'plan'           => $plan,
                'clinical_notes' => $clinicalNotes,
                'vitals'         => $vitals
            ]);
        } else {
            $noteId = $noteModel->create([
                'patient_id'     => $patientId,
                'doctor_id'      => $doctorId,
                'appointment_id' => $appId ?: null,
                'subjective'     => $subjective,
                'objective'      => $objective,
                'assessment'     => $assessment,
                'plan'           => $plan,
                'clinical_notes' => $clinicalNotes,
                'vitals'         => $vitals
            ]);
        }

        // Also persist vitals to patient_vitals table
        try {
            $bp = $vitals['bp'] ?? '120/80';
            $hr = (int)preg_replace('/[^0-9]/', '', $vitals['hr'] ?? '74');
            $temp = (float)preg_replace('/[^0-9.]/', '', $vitals['temp'] ?? '36.7');
            $spo2 = (int)preg_replace('/[^0-9]/', '', $vitals['spo2'] ?? '98');
            $wt = (float)preg_replace('/[^0-9.]/', '', $vitals['weight'] ?? '70');
            $stmtV = Database::getConnection()->prepare("
                INSERT INTO patient_vitals (
                    PatientID, AppointmentID, BloodPressure, HeartRate, RespiratoryRate,
                    Temperature, OxygenSaturation, PainScale, WeightKg, ClinicalNotes,
                    RecordedBy, RecordedByName, CreatedAt
                ) VALUES (
                    :pid, :aid, :bp, :hr, 18, :temp, :spo2, 0, :wt, 'Captured during clinical consultation',
                    :uid, :uname, NOW()
                )
            ");
            $stmtV->execute([
                ':pid'   => $patientId,
                ':aid'   => $appId ?: null,
                ':bp'    => $bp ?: '120/80',
                ':hr'    => $hr ?: 74,
                ':temp'  => $temp ?: 36.7,
                ':spo2'  => $spo2 ?: 98,
                ':wt'    => $wt ?: 70.0,
                ':uid'   => auth()->id() ?? 1,
                ':uname' => $currentUser['name'] ?? 'Attending Physician'
            ]);
        } catch (\Throwable $ve) {}

        // Persist working diagnosis if assessment is filled
        if (!empty($assessment)) {
            try {
                $diagCheck = Database::getConnection()->prepare("SELECT DiagnosisID FROM diagnoses WHERE PatientID = :pid AND DiagnosisName = :dname LIMIT 1");
                $diagCheck->execute([':pid' => $patientId, ':dname' => $assessment]);
                if (!$diagCheck->fetchColumn()) {
                    $diagnosisModel->create([
                        'patient_id'     => $patientId,
                        'doctor_id'      => $doctorId,
                        'appointment_id' => $appId ?: null,
                        'diagnosis_name' => $assessment,
                        'icd10_code'     => 'Z00.0',
                        'type'           => 'Working',
                        'severity'       => 'Moderate',
                        'status'         => 'Active',
                        'notes'          => 'Formulated during clinical consultation'
                    ]);
                }
            } catch (\Throwable $de) {}
        }

        if ($noteId) {
            Session::setFlash('success', 'Consultation SOAP note recorded and updated successfully.');
            header("Location: " . doctor_url("views/diagnosis/index.php?patient_id=$patientId&app_id=$appId"));
            if (function_exists('app') && app()->environment('testing')) { return; }
            exit;
        } else {
            $errorMessage = 'Failed to save consultation note.';
        }
    } elseif ($action === 'save_diagnosis') {
        $diagName = trim($_POST['diagnosis_name'] ?? '');
        $icdCode = trim($_POST['icd_code'] ?? '');
        $diagType = $_POST['diag_type'] ?? 'Primary';
        $severity = $_POST['severity'] ?? 'Moderate';
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($diagName)) {
            $diagId = $diagnosisModel->create([
                'patient_id'     => $patientId,
                'doctor_id'      => $doctorId,
                'appointment_id' => $appId ?: ($patient['Appointment']['AppointmentID'] ?? null),
                'diagnosis_name' => $diagName,
                'icd10_code'     => $icdCode,
                'type'           => $diagType,
                'severity'       => $severity,
                'status'         => 'Active',
                'notes'          => $notes
            ]);

            // Synchronize assessment into consultation note so doctor's diagnosis updates the note immediately
            try {
                $latestNotes = $noteModel->findByPatient($patientId);
                if (!empty($latestNotes)) {
                    $targetNoteId = (int)$latestNotes[0]['NoteID'];
                    $currAss = trim($latestNotes[0]['Assessment'] ?? '');
                    $updatedAss = (empty($currAss) || $currAss === 'Consultation completed' || $currAss === 'Clinical Differential & Impression evaluated')
                        ? $diagName
                        : ($currAss . '; ' . $diagName);
                    $noteModel->update($targetNoteId, [
                        'assessment'     => $updatedAss,
                        'clinical_notes' => trim(($latestNotes[0]['Subjective'] ?? '') . ' | Assessment: ' . $updatedAss . ' | Plan: ' . ($latestNotes[0]['Plan'] ?? ''))
                    ]);
                } else {
                    $noteModel->create([
                        'patient_id'     => $patientId,
                        'doctor_id'      => $doctorId,
                        'appointment_id' => $appId ?: null,
                        'subjective'     => $patient['Complaint']['ComplaintDescription'] ?? 'General Consultation',
                        'objective'      => 'Clinical physical examination conducted',
                        'assessment'     => $diagName,
                        'plan'           => 'Formulate treatment regimen and follow up',
                        'clinical_notes' => "Diagnosis: $diagName ($diagType)",
                        'vitals'         => ['bp' => '120/80', 'hr' => '74 bpm', 'temp' => '36.7 °C', 'spo2' => '98%', 'weight' => '72 kg']
                    ]);
                }
            } catch (\Throwable $ne) {}

            if ($diagId) {
                Session::setFlash('success', "Diagnosis '$diagName' recorded in patient chart and updated in consultation note.");
                header("Location: " . doctor_url("views/diagnosis/index.php?patient_id=$patientId&app_id=$appId"));
                if (function_exists('app') && app()->environment('testing')) { return; }
                exit;
            }
        } else {
            $errorMessage = 'Diagnosis name is required.';
        }
    } elseif ($action === 'save_treatment_plan') {
        $goal = trim($_POST['goal'] ?? '');
        $lifestyle = trim($_POST['lifestyle'] ?? '');
        $medicationPlan = trim($_POST['medication_plan'] ?? '');
        $followUp = trim($_POST['follow_up_sched'] ?? '');
        $followUpDate = $_POST['follow_up_date'] ?? null;
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($goal)) {
            $planId = $treatmentModel->create([
                'patient_id'                => $patientId,
                'doctor_id'                 => $doctorId,
                'appointment_id'            => $appId ?: ($patient['Appointment']['AppointmentID'] ?? null),
                'goal'                      => $goal,
                'lifestyle_recommendations' => $lifestyle,
                'medication_plan'           => $medicationPlan,
                'follow_up_schedule'        => $followUp,
                'follow_up_date'            => $followUpDate,
                'status'                    => 'Active',
                'notes'                     => $notes
            ]);

            if ($planId) {
                Session::setFlash('success', 'Treatment plan created successfully.');
                header("Location: " . doctor_url("views/diagnosis/index.php?patient_id=$patientId&app_id=$appId"));
                if (function_exists('app') && app()->environment('testing')) { return; }
                exit;
            }
        } else {
            $errorMessage = 'Primary treatment goal is required.';
        }
    } elseif ($action === 'complete_consultation') {
        $summary = trim($_POST['summary_notes'] ?? '');
        $subjective = trim($_POST['subjective'] ?? '');
        $objective = trim($_POST['objective'] ?? '');
        $assessment = trim($_POST['assessment'] ?? '');
        $plan = trim($_POST['plan'] ?? '');
        $clinicalNotes = trim($_POST['clinical_notes'] ?? "$subjective $assessment $plan");

        $vitals = [
            'bp'     => $_POST['vital_bp'] ?? '120/80',
            'hr'     => $_POST['vital_hr'] ?? '74 bpm',
            'temp'   => $_POST['vital_temp'] ?? '36.7 °C',
            'spo2'   => $_POST['vital_spo2'] ?? '98%',
            'weight' => $_POST['vital_weight'] ?? '72 kg'
        ];

        if ($appId <= 0 && !empty($patient['Appointment']['AppointmentID'])) {
            $appId = (int)$patient['Appointment']['AppointmentID'];
        }
        if ($appId <= 0) {
            $stmtFindApp = Database::getConnection()->prepare("
                SELECT AppointmentID FROM appointments 
                WHERE PatientID = :pid 
                ORDER BY AppointmentID DESC LIMIT 1
            ");
            $stmtFindApp->execute([':pid' => $patientId]);
            $appId = (int)$stmtFindApp->fetchColumn();
        }

        // 1. Persist or update SOAP Consultation Note
        $existingNoteForApp = ($appId > 0) ? $noteModel->findByAppointment($appId) : null;
        if (!$existingNoteForApp && !empty($existingNotes)) {
            $existingNoteForApp = $existingNotes[0];
        }

        $finalSub = $subjective ?: ($existingNoteForApp['Subjective'] ?? ($patient['Complaint']['ComplaintDescription'] ?? 'Routine consultation'));
        $finalObj = $objective ?: ($existingNoteForApp['Objective'] ?? 'Clinical evaluation performed');
        $finalAss = $assessment ?: ($existingNoteForApp['Assessment'] ?? (!empty($existingDiagnoses) ? $existingDiagnoses[0]['DiagnosisName'] : 'Consultation completed'));
        $finalPlan = $plan ?: ($existingNoteForApp['Plan'] ?? ($summary ?: 'Outpatient care and follow up'));
        $finalNotes = $clinicalNotes ?: ($summary ?: "$finalSub | $finalAss | $finalPlan");

        if ($existingNoteForApp && !empty($existingNoteForApp['NoteID'])) {
            $noteModel->update((int)$existingNoteForApp['NoteID'], [
                'subjective'     => $finalSub,
                'objective'      => $finalObj,
                'assessment'     => $finalAss,
                'plan'           => $finalPlan,
                'clinical_notes' => $finalNotes,
                'vitals'         => $vitals
            ]);
        } else {
            $noteModel->create([
                'patient_id'     => $patientId,
                'doctor_id'      => $doctorId,
                'appointment_id' => $appId ?: null,
                'subjective'     => $finalSub,
                'objective'      => $finalObj,
                'assessment'     => $finalAss,
                'plan'           => $finalPlan,
                'clinical_notes' => $finalNotes,
                'vitals'         => $vitals
            ]);
        }

        // 2. Persist Vitals to patient_vitals table
        try {
            $bp = $vitals['bp'] ?? '120/80';
            $hr = (int)preg_replace('/[^0-9]/', '', $vitals['hr'] ?? '74');
            $temp = (float)preg_replace('/[^0-9.]/', '', $vitals['temp'] ?? '36.7');
            $spo2 = (int)preg_replace('/[^0-9]/', '', $vitals['spo2'] ?? '98');
            $wt = (float)preg_replace('/[^0-9.]/', '', $vitals['weight'] ?? '70');
            $stmtV = Database::getConnection()->prepare("
                INSERT INTO patient_vitals (
                    PatientID, AppointmentID, BloodPressure, HeartRate, RespiratoryRate,
                    Temperature, OxygenSaturation, PainScale, WeightKg, ClinicalNotes,
                    RecordedBy, RecordedByName, CreatedAt
                ) VALUES (
                    :pid, :aid, :bp, :hr, 18, :temp, :spo2, 0, :wt, 'Captured during clinical consultation',
                    :uid, :uname, NOW()
                )
            ");
            $stmtV->execute([
                ':pid'   => $patientId,
                ':aid'   => $appId ?: null,
                ':bp'    => $bp ?: '120/80',
                ':hr'    => $hr ?: 74,
                ':temp'  => $temp ?: 36.7,
                ':spo2'  => $spo2 ?: 98,
                ':wt'    => $wt ?: 70.0,
                ':uid'   => auth()->id() ?? 1,
                ':uname' => $currentUser['name'] ?? 'Attending Physician'
            ]);
        } catch (\Throwable $ve) {}

        // 3. Persist Active Diagnosis if assessment provided
        if (!empty($finalAss) && $finalAss !== 'Consultation completed') {
            try {
                $diagCheck = Database::getConnection()->prepare("SELECT DiagnosisID FROM diagnoses WHERE PatientID = :pid AND DiagnosisName = :dname LIMIT 1");
                $diagCheck->execute([':pid' => $patientId, ':dname' => $finalAss]);
                if (!$diagCheck->fetchColumn()) {
                    $diagnosisModel->create([
                        'patient_id'     => $patientId,
                        'doctor_id'      => $doctorId,
                        'appointment_id' => $appId ?: null,
                        'diagnosis_name' => $finalAss,
                        'icd10_code'     => 'Z00.0',
                        'type'           => 'Primary',
                        'severity'       => 'Moderate',
                        'status'         => 'Active',
                        'notes'          => 'Formulated during clinical consultation'
                    ]);
                }
            } catch (\Throwable $de) {}
        }

        // 4. Persist Treatment Plan if plan provided
        if (!empty($finalPlan)) {
            try {
                $planCheck = Database::getConnection()->prepare("SELECT PlanID FROM treatment_plans WHERE PatientID = :pid AND Goal = :goal LIMIT 1");
                $planCheck->execute([':pid' => $patientId, ':goal' => $finalPlan]);
                if (!$planCheck->fetchColumn()) {
                    $treatmentModel->create([
                        'patient_id'                => $patientId,
                        'doctor_id'                 => $doctorId,
                        'appointment_id'            => $appId ?: null,
                        'goal'                      => $finalPlan,
                        'lifestyle_recommendations' => 'Adequate rest, balanced nutrition, and hydration.',
                        'medication_plan'           => $finalPlan,
                        'follow_up_schedule'        => 'Follow up in 2 weeks or PRN',
                        'status'                    => 'Active',
                        'notes'                     => 'Formulated during clinical consultation'
                    ]);
                }
            } catch (\Throwable $te) {}
        }

        // 5. Complete consultation in appointment and queue
        if ($appId > 0) {
            $consultationModel->completeConsultation($appId, $doctorId, $summary ?: $finalNotes);
        }

        Session::setFlash('success', 'Consultation marked as Completed. Clinical records archived and available in Medical Records.');
        header("Location: " . doctor_url("views/patients/view.php?id=$patientId"));
        if (function_exists('app') && app()->environment('testing')) { return; }
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen scroll-smooth">
  
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="p-6 sm:p-8 space-y-8 flex-1">

    <!-- Header & Patient Selector Bar -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="p-2 rounded-xl bg-blue-50 text-blue-600">
            <i data-lucide="stethoscope" class="w-5 h-5"></i>
          </span>
          <h1 class="text-xl font-black text-slate-900 tracking-tight font-display">Clinical Diagnosis & Treatment Planning</h1>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-1">
          Review medical histories, formulate differential diagnoses, and prescribe comprehensive treatment regimens.
        </p>
      </div>

      <!-- Quick Switch & Search Patient -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="relative w-full sm:w-64">
          <div class="relative">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
            <input type="text" id="doctor-patient-search" placeholder="Search patient name, ID, student ID..." 
                   autocomplete="off"
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition" />
          </div>
          <div id="doctor-search-results" class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 max-h-72 overflow-y-auto divide-y divide-slate-100"></div>
        </div>

        <div class="flex items-center gap-2">
          <select onchange="if(this.value) window.location.href='?patient_id=' + this.value;" 
                  class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Assigned Queue (<?= count($patientList) ?>) --</option>
            <?php foreach ($patientList as $p): ?>
              <option value="<?= $p['PatientID'] ?>" <?= $p['PatientID'] == $patientId ? 'selected' : '' ?>>
                <?= e($p['FirstName'] . ' ' . $p['LastName']) ?> (<?= e($p['PatientCode']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($patient): ?>
            <a href="<?= doctor_url('views/patients/view.php?id=' . $patientId) ?>" class="p-2 text-slate-400 hover:text-blue-600 bg-slate-50 hover:bg-blue-50 rounded-xl border border-slate-200 transition shrink-0" title="Patient Full Chart">
              <i data-lucide="external-link" class="w-4 h-4"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!$patient): ?>
      <div class="bg-white rounded-3xl p-12 text-center text-slate-400 border border-slate-200 shadow-card">
        <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 text-slate-300"></i>
        <h3 class="text-base font-bold text-slate-700">No Patient Selected</h3>
        <p class="text-xs text-slate-400 mt-1">Select a patient from your assigned queue above to start recording notes.</p>
      </div>
    <?php else: ?>

      <!-- Patient Overview Banner -->
      <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-3xl p-6 shadow-xl border border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-2xl bg-blue-600 font-black text-white text-base flex items-center justify-center shadow-lg shrink-0 font-display">
            <?= strtoupper(substr($patient['FirstName'], 0, 1) . substr($patient['LastName'], 0, 1)) ?>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="text-lg font-black tracking-tight font-display"><?= e($patient['FirstName'] . ' ' . $patient['LastName']) ?></h2>
              <span class="px-2 py-0.5 rounded-full text-[11px] font-mono bg-white/10 text-blue-300 font-bold"><?= e($patient['PatientCode']) ?></span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-400/30">
                <?= e($patient['Age']) ?>y, <?= e($patient['Gender']) ?> • Blood: <?= e($patient['BloodType']) ?>
              </span>
            </div>
            <p class="text-xs text-slate-300 mt-1">
              <strong>Complaint:</strong> <?= e($patient['Complaint']['ComplaintDescription'] ?? 'No complaint recorded') ?>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap shrink-0">
          <a href="/print/consultation/<?= $latestNoteId ?: $patientId ?>" target="_blank" class="px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 border border-white/20" title="Print SOAP Consultation Document">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Print Note</span>
          </a>
          <a href="/print/patient/<?= $patientId ?>" target="_blank" class="px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 border border-white/20" title="Print Comprehensive Patient Record">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            <span>Print Profile</span>
          </a>
          <form id="completeConsultationForm" method="POST" action="" onsubmit="return handleCompleteConsultationSubmit(this);" class="inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
            <input type="hidden" name="action" value="complete_consultation" />
            <input type="hidden" name="subjective" value="" />
            <input type="hidden" name="objective" value="" />
            <input type="hidden" name="assessment" value="" />
            <input type="hidden" name="plan" value="" />
            <input type="hidden" name="vital_bp" value="" />
            <input type="hidden" name="vital_hr" value="" />
            <input type="hidden" name="vital_temp" value="" />
            <input type="hidden" name="vital_spo2" value="" />
            <input type="hidden" name="vital_weight" value="" />
            <button type="submit" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-emerald-600/20 transition flex items-center gap-1.5">
              <i data-lucide="check-circle-2" class="w-4 h-4"></i>
              <span>Complete Consultation</span>
            </button>
          </form>
        </div>
      </div>

      <!-- Clinical Care Pathway Stage Tracker -->
      <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-card">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
          <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <i data-lucide="git-commit" class="w-4 h-4 text-blue-600"></i>
            Clinical Care Progression Pathway
          </span>
          <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
            Current Stage: <?= empty($existingDiagnoses) ? 'In Consultation' : (!empty($existingTreatmentPlans) ? 'Treatment Regimen' : 'Differential Diagnosis') ?>
          </span>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-9 gap-2">
          <?php foreach ($stages as $idx => $st): 
            $stageTarget = '';
            if ($st['key'] === 'consultation') {
                $stageTarget = '#soapNoteSection';
            } elseif ($st['key'] === 'diagnosis') {
                $stageTarget = '#diagnosisSection';
            } elseif ($st['key'] === 'treatment') {
                $stageTarget = '#treatmentPlanSection';
            } elseif ($st['key'] === 'prescription') {
                $stageTarget = doctor_url('views/prescriptions/index.php?patient_id=' . $patientId);
            }
          ?>
            <?php if ($stageTarget): ?>
              <a href="<?= $stageTarget ?>" class="flex flex-col items-center text-center p-2 rounded-2xl transition hover:scale-105 cursor-pointer <?= $st['done'] ? 'bg-emerald-50/80 border border-emerald-200 text-emerald-800 hover:bg-emerald-100/80' : (!empty($st['active']) ? 'bg-blue-50 border-2 border-blue-500 text-blue-900 shadow-sm hover:bg-blue-100' : 'bg-slate-50 border border-slate-200/60 text-slate-500 hover:bg-slate-100') ?>">
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1 <?= $st['done'] ? 'bg-emerald-600 text-white' : (!empty($st['active']) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') ?>">
                  <?= $st['done'] ? '✓' : ($idx + 1) ?>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-tight leading-tight"><?= e($st['label']) ?></span>
              </a>
            <?php else: ?>
              <div class="flex flex-col items-center text-center p-2 rounded-2xl transition <?= $st['done'] ? 'bg-emerald-50/80 border border-emerald-200 text-emerald-800' : (!empty($st['active']) ? 'bg-blue-50 border-2 border-blue-500 text-blue-900 shadow-sm' : 'bg-slate-50 border border-slate-200/60 text-slate-400') ?>">
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1 <?= $st['done'] ? 'bg-emerald-600 text-white' : (!empty($st['active']) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') ?>">
                  <?= $st['done'] ? '✓' : ($idx + 1) ?>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-tight leading-tight"><?= e($st['label']) ?></span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 5 Golden Questions Clinical Orientation Card -->
      <div class="bg-gradient-to-br from-blue-950 via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-blue-800/40">
        <div class="flex items-center gap-2 mb-4 border-b border-white/10 pb-3">
          <span class="p-1.5 rounded-lg bg-blue-500/20 text-blue-300">
            <i data-lucide="compass" class="w-4 h-4"></i>
          </span>
          <h3 class="text-xs font-black uppercase tracking-wider text-blue-200 font-display">Immediate Clinical Orientation (5 Golden Questions)</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-xs">
          <!-- 1. WHO is the Patient? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-blue-300 uppercase tracking-wider block">1. WHO is Patient?</span>
            <p class="font-extrabold text-sm text-white"><?= e($patient['FirstName'] . ' ' . $patient['LastName']) ?></p>
            <p class="text-[11px] text-slate-300"><?= e($patient['PatientCode']) ?> • <?= e($patient['Age']) ?>y <?= e($patient['Gender']) ?></p>
          </div>
          <!-- 2. WHAT is Happening? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-amber-300 uppercase tracking-wider block">2. WHAT is Happening?</span>
            <p class="font-bold text-white line-clamp-2"><?= e($patient['Complaint']['ComplaintDescription'] ?? 'Clinical Evaluation & Assessment') ?></p>
          </div>
          <!-- 3. WHO is Responsible? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-emerald-300 uppercase tracking-wider block">3. WHO is Responsible?</span>
            <p class="font-bold text-white"><?= e($assignedDoctorName) ?></p>
            <p class="text-[11px] text-emerald-300/90"><?= e($assignedNurseName) ?></p>
          </div>
          <!-- 4. CURRENT Status? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-cyan-300 uppercase tracking-wider block">4. CURRENT Status?</span>
            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-200 font-extrabold text-[11px] border border-cyan-400/30">
              <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-ping"></span>
              <?= empty($existingDiagnoses) ? 'In Consultation' : 'Diagnosed • In Plan' ?>
            </div>
            <p class="text-[10px] text-slate-300 mt-1 truncate" title="<?= e($currentTaskSummary) ?>"><?= e($currentTaskSummary) ?></p>
          </div>
          <!-- 5. NEXT Action? -->
          <div class="p-3.5 rounded-2xl bg-blue-600/30 border border-blue-400/40 space-y-1.5 flex flex-col justify-between">
            <div>
              <span class="text-[10px] font-black text-blue-200 uppercase tracking-wider block">5. NEXT Action?</span>
              <p class="font-bold text-white text-[11px]">
                <?= empty($existingNotes) ? 'Document SOAP Clinical Notes' : (empty($existingDiagnoses) ? 'Formulate Diagnostic Assessment' : 'Prescribe Regimen or Refer Patient') ?>
              </p>
            </div>
            <a href="#soapNoteSection" class="px-2.5 py-1 bg-blue-500 hover:bg-blue-400 text-white font-extrabold text-[10px] rounded-lg text-center transition flex items-center justify-center gap-1">
              <span>Proceed to Action</span>
              <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- 3 Columns Section: 1. Medical History Review | 2. Consultation Note (SOAP) | 3. Diagnosis & Treatment Plans -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Column 1: Medical History Review (3 Cols) -->
        <div class="lg:col-span-4 space-y-6">
          
          <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-blue-600"></i>
                <span>Medical History Review</span>
              </h3>
            </div>

            <div class="space-y-3 text-xs">
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Existing Conditions</span>
                <p class="font-bold text-slate-800"><?= e($patient['MedicalHistory']['ExistingConditions'] ?? 'None disclosed') ?></p>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Current Medications</span>
                <p class="font-bold text-slate-800"><?= e($patient['MedicalHistory']['CurrentMedications'] ?? 'None recorded') ?></p>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Past Surgeries</span>
                <p class="font-bold text-slate-800"><?= e($patient['MedicalHistory']['PreviousHospitalization'] ?? 'None recorded') ?></p>
              </div>

              <div class="p-3 bg-rose-50/60 rounded-xl border border-rose-100">
                <span class="text-[10px] font-extrabold uppercase text-rose-700 block mb-1">Known Allergies</span>
                <p class="font-bold text-rose-900"><?= e($patient['MedicalHistory']['Allergies'] ?? 'No known drug allergies') ?></p>
              </div>
            </div>
          </div>

          <!-- Existing Diagnoses History Card -->
          <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
              <i data-lucide="file-check" class="w-4 h-4 text-emerald-600"></i>
              <span>Active Diagnoses (<?= count($existingDiagnoses) ?>)</span>
            </h3>

            <div class="space-y-2.5">
              <?php if (empty($existingDiagnoses)): ?>
                <p class="text-xs text-slate-400 italic">No formal diagnoses recorded yet.</p>
              <?php else: ?>
                <?php foreach ($existingDiagnoses as $diag): ?>
                  <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                      <strong class="text-slate-900"><?= e($diag['DiagnosisName']) ?></strong>
                      <span class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-blue-100 text-blue-800 font-mono"><?= e($diag['ICD10Code'] ?: 'ICD-10') ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-[10px] text-slate-400">
                      <span>Type: <?= e($diag['Type']) ?></span>
                      <span>•</span>
                      <span>Severity: <?= e($diag['Severity']) ?></span>
                      <span>•</span>
                      <span><?= format_date($diag['DiagnosedDate']) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Patient Longitudinal Care Timeline Card -->
          <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i>
                <span>Patient Care Timeline</span>
              </h3>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?= count($patientTimeline) ?> Events</span>
            </div>
            <div class="relative pl-5 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
              <?php if (empty($patientTimeline)): ?>
                <p class="text-xs text-slate-400 italic">No care milestones recorded yet.</p>
              <?php else: ?>
                <?php foreach (array_slice($patientTimeline, 0, 7) as $tl): ?>
                  <div class="relative">
                    <div class="absolute -left-5 top-1 w-2.5 h-2.5 rounded-full bg-blue-600 border-2 border-white ring-2 ring-blue-100"></div>
                    <div>
                      <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-800"><?= e($tl['title']) ?></span>
                        <span class="text-[10px] font-mono text-slate-400"><?= date('H:i', strtotime($tl['timestamp'])) ?></span>
                      </div>
                      <p class="text-[11px] text-slate-500 mt-0.5"><?= e($tl['description']) ?></p>
                      <span class="text-[9px] font-semibold text-slate-400"><?= e($tl['actor']) ?> • <?= date('M d, Y', strtotime($tl['timestamp'])) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <!-- Column 2 & 3: Interactive Note Management & Diagnosis / Treatment Plan Builder (8 Cols) -->
        <div class="lg:col-span-8 space-y-6">
          
          <!-- SOAP Consultation Note Form -->
          <div id="soapNoteSection" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-card space-y-5 scroll-mt-24">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2 font-display">
                  <i data-lucide="edit-3" class="w-4 h-4 text-blue-600"></i>
                  <span>Consultation Note (SOAP Format)</span>
                </h3>
                <p class="text-xs text-slate-400">Structured electronic clinical observation and diagnostic evaluation</p>
              </div>
            </div>

            <?php
              $latestNote = !empty($existingNotes) ? $existingNotes[0] : null;
              $savedVitals = !empty($latestNote['VitalSigns']) ? (is_array($latestNote['VitalSigns']) ? $latestNote['VitalSigns'] : json_decode($latestNote['VitalSigns'], true)) : [];
              $bpVal = $savedVitals['bp'] ?? '120/80';
              $hrVal = $savedVitals['hr'] ?? '74 bpm';
              $tempVal = $savedVitals['temp'] ?? '36.7 °C';
              $spo2Val = $savedVitals['spo2'] ?? '98%';
              $weightVal = $savedVitals['weight'] ?? '72 kg';

              $subjVal = $latestNote['Subjective'] ?? ($patient['Complaint']['ComplaintDescription'] ?? '');
              $objVal = $latestNote['Objective'] ?? '';
              $assVal = $latestNote['Assessment'] ?? (!empty($existingDiagnoses) ? $existingDiagnoses[0]['DiagnosisName'] : '');
              $planVal = $latestNote['Plan'] ?? (!empty($existingTreatmentPlans) ? $existingTreatmentPlans[0]['Goal'] : '');
            ?>

            <form id="soapNoteForm" method="POST" action="" class="space-y-4">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
              <input type="hidden" name="action" value="save_soap_note" />

              <!-- Vital Signs Triage Ribbon -->
              <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/60 space-y-2">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Vital Signs Capture</span>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                  <div>
                    <label class="text-[10px] font-bold text-slate-600 block mb-1">Blood Pressure</label>
                    <input type="text" name="vital_bp" placeholder="120/80 mmHg" value="<?= e($bpVal) ?>" 
                           class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-slate-600 block mb-1">Heart Rate</label>
                    <input type="text" name="vital_hr" placeholder="72 bpm" value="<?= e($hrVal) ?>" 
                           class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-slate-600 block mb-1">Temperature</label>
                    <input type="text" name="vital_temp" placeholder="36.8 °C" value="<?= e($tempVal) ?>" 
                           class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-slate-600 block mb-1">SpO2 Oxygen</label>
                    <input type="text" name="vital_spo2" placeholder="99%" value="<?= e($spo2Val) ?>" 
                           class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-slate-600 block mb-1">Body Weight</label>
                    <input type="text" name="vital_weight" placeholder="70 kg" value="<?= e($weightVal) ?>" 
                           class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800" />
                  </div>
                </div>
              </div>

              <!-- S: Subjective -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                  <span>Subjective (Patient's History of Present Illness)</span>
                  <span class="text-[10px] text-slate-400">Chief complaint & symptom progression</span>
                </label>
                <textarea name="subjective" rows="2" required 
                          placeholder="e.g. Patient reports sharp retrosternal discomfort after heavy meals with nausea..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"><?= e($subjVal) ?></textarea>
              </div>

              <!-- O: Objective -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                  <span>Objective (Physical Examination & Clinical Findings)</span>
                  <span class="text-[10px] text-slate-400">Auscultation, palpation, signs</span>
                </label>
                <textarea name="objective" rows="2" 
                          placeholder="e.g. Normal S1/S2 heart sounds, abdomen soft without guarding, epigastric tenderness present..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"><?= e($objVal) ?></textarea>
              </div>

              <!-- A: Assessment -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="text-xs font-bold text-slate-700 flex items-center justify-between w-full">
                    <span>Assessment (Clinical Differential & Impression)</span>
                    <span class="text-[10px] text-slate-400">Working diagnosis</span>
                  </label>
                </div>
                <div class="flex items-center gap-1.5 flex-wrap mb-1.5">
                  <span class="text-[9px] uppercase font-extrabold text-slate-400">Insert:</span>
                  <button type="button" onclick="appendSoapDiagnosis('Dengue Fever without Warning Signs (ICD-10: A90)')" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold transition">+ Dengue</button>
                  <button type="button" onclick="appendSoapDiagnosis('Acute Gastroenteritis (ICD-10: A09)')" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold transition">+ AGE/Diarrhea</button>
                  <button type="button" onclick="appendSoapDiagnosis('Essential Hypertension (ICD-10: I10)')" class="text-[10px] px-1.5 py-0.5 rounded bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold transition">+ Hypertension</button>
                  <button type="button" onclick="appendSoapDiagnosis('Acute Upper Respiratory Tract Infection (ICD-10: J06.9)')" class="text-[10px] px-1.5 py-0.5 rounded bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-semibold transition">+ URTI</button>
                </div>
                <textarea id="soap-assessment-textarea" name="assessment" rows="2" 
                          placeholder="e.g. Gastritis secondary to mucosal hyperacidity vs Functional Dyspepsia..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"><?= e($assVal) ?></textarea>
              </div>

              <!-- P: Plan -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                  <span>Plan (Next Steps, Medications, Diagnostics)</span>
                  <span class="text-[10px] text-slate-400">Follow-up timeline</span>
                </label>
                <textarea name="plan" rows="2" 
                          placeholder="e.g. Prescribe Proton Pump Inhibitor 40mg daily, order H. Pylori antibody test, review in 2 weeks..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"><?= e($planVal) ?></textarea>
              </div>

              <div class="pt-2 flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md shadow-blue-600/20 transition flex items-center gap-1.5">
                  <i data-lucide="save" class="w-4 h-4"></i>
                  <span>Save SOAP Note to MySQL</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Add Diagnosis & Add Treatment Plan Forms Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            
            <!-- Card A: Add Diagnosis Form -->
            <div id="diagnosisSection" class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4 scroll-mt-24">
              <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 font-display">
                <i data-lucide="plus-circle" class="w-4 h-4 text-blue-600"></i>
                <span>Add Formal Diagnosis</span>
              </h3>

              <form method="POST" action="" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                <input type="hidden" name="action" value="save_diagnosis" />

                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Diagnosis Name *</label>
                    <span class="text-[9px] text-blue-600 font-bold flex items-center gap-1">
                      <i data-lucide="sparkles" class="w-3 h-3"></i>
                      <span>ICD-10 Dynamic Search Active</span>
                    </span>
                  </div>
                  <div class="relative">
                    <input type="text" id="diagnosis-name-input" name="diagnosis_name" required 
                           placeholder="Search disease or code (e.g. Dengue, Diarrhea, I10)..." 
                           autocomplete="off"
                           class="w-full pl-8 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition" />
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none"></i>
                    <span id="diagnosis-search-spinner" class="hidden absolute right-2.5 top-2.5 text-blue-600">
                      <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                    </span>
                    
                    <!-- Disease Search Results Dropdown -->
                    <div id="diagnosis-search-results" class="hidden absolute left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 max-h-64 overflow-y-auto divide-y divide-slate-100 p-1.5 space-y-1">
                    </div>
                  </div>

                  <!-- Quick Select Chips for Common Diagnoses -->
                  <div class="flex items-center gap-1.5 flex-wrap pt-1.5">
                    <span class="text-[9px] font-extrabold uppercase tracking-tight text-slate-400">Common:</span>
                    <button type="button" onclick="selectQuickDiagnosis('Dengue Fever without Warning Signs', 'A90', 'Moderate')" class="text-[10px] px-2 py-0.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold transition flex items-center gap-1">
                      <span>Dengue</span><span class="font-mono text-[9px] opacity-70">A90</span>
                    </button>
                    <button type="button" onclick="selectQuickDiagnosis('Diarrhea and Gastroenteritis (Presumed Infectious Origin)', 'A09', 'Moderate')" class="text-[10px] px-2 py-0.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold transition flex items-center gap-1">
                      <span>Diarrhea</span><span class="font-mono text-[9px] opacity-70">A09</span>
                    </button>
                    <button type="button" onclick="selectQuickDiagnosis('Essential (Primary) Hypertension', 'I10', 'Moderate')" class="text-[10px] px-2 py-0.5 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold transition flex items-center gap-1">
                      <span>Hypertension</span><span class="font-mono text-[9px] opacity-70">I10</span>
                    </button>
                    <button type="button" onclick="selectQuickDiagnosis('Type 2 Diabetes Mellitus without Complications', 'E11.9', 'Moderate')" class="text-[10px] px-2 py-0.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold transition flex items-center gap-1">
                      <span>Diabetes</span><span class="font-mono text-[9px] opacity-70">E11.9</span>
                    </button>
                    <button type="button" onclick="selectQuickDiagnosis('Acute Upper Respiratory Tract Infection (URTI)', 'J06.9', 'Mild')" class="text-[10px] px-2 py-0.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-bold transition flex items-center gap-1">
                      <span>URTI</span><span class="font-mono text-[9px] opacity-70">J06.9</span>
                    </button>
                  </div>
                </div>

                <!-- Verified Selection Pill -->
                <div id="diagnosis-selected-badge" class="hidden p-2 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold flex items-center justify-between transition">
                  <div class="flex items-center gap-1.5 truncate">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                    <span id="diagnosis-selected-text" class="truncate"></span>
                  </div>
                  <span class="text-[9px] font-mono px-2 py-0.5 bg-emerald-200/60 rounded-md text-emerald-900 shrink-0">ICD-10 Verified</span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">ICD-10 Code</label>
                    <input type="text" id="diagnosis-icd-input" name="icd_code" placeholder="e.g. I20.9" autocomplete="off"
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Type</label>
                    <select id="diagnosis-type-select" name="diag_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800">
                      <option value="Primary">Primary</option>
                      <option value="Secondary">Secondary</option>
                      <option value="Differential">Differential</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Severity</label>
                  <select id="diagnosis-severity-select" name="severity" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800">
                    <option value="Mild">Mild</option>
                    <option value="Moderate" selected>Moderate</option>
                    <option value="Severe">Severe</option>
                    <option value="Critical">Critical</option>
                  </select>
                </div>

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Clinical Notes</label>
                  <textarea name="notes" rows="2" placeholder="Diagnostic criteria or rationale..." 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i>
                  <span>Record Diagnosis</span>
                </button>
              </form>
            </div>

            <!-- Card B: Add Treatment Plan Form -->
            <div id="treatmentPlanSection" class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4 scroll-mt-24">
              <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 font-display">
                <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                <span>Add Treatment Plan</span>
              </h3>

              <form method="POST" action="" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                <input type="hidden" name="action" value="save_treatment_plan" />

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Treatment Goal</label>
                  <input type="text" name="goal" required placeholder="e.g. Alleviate symptoms, stabilize resting BP..." 
                         class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Lifestyle & Diet Recommendations</label>
                  <input type="text" name="lifestyle" placeholder="e.g. Low sodium diet, 30 min daily walking..." 
                         class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800" />
                </div>

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Medication Regimen Overview</label>
                  <input type="text" name="medication_plan" placeholder="e.g. Daily oral antihypertensives with morning meals..." 
                         class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800" />
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Follow-up Interval</label>
                    <input type="text" name="follow_up_sched" placeholder="e.g. 2 Weeks" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Target Date</label>
                    <input type="date" name="follow_up_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800" />
                  </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i>
                  <span>Establish Treatment Plan</span>
                </button>
              </form>
            </div>

          </div>

        </div>

      </div>

    <?php endif; ?>

  </main>

</div>

<script>
function handleCompleteConsultationSubmit(form) {
    if (!confirm('Complete and archive this consultation?')) {
        return false;
    }
    const soap = document.getElementById('soapNoteForm');
    if (soap) {
        form.subjective.value = soap.querySelector('textarea[name="subjective"]')?.value || '';
        form.objective.value = soap.querySelector('textarea[name="objective"]')?.value || '';
        form.assessment.value = soap.querySelector('textarea[name="assessment"]')?.value || '';
        form.plan.value = soap.querySelector('textarea[name="plan"]')?.value || '';
        form.vital_bp.value = soap.querySelector('input[name="vital_bp"]')?.value || '';
        form.vital_hr.value = soap.querySelector('input[name="vital_hr"]')?.value || '';
        form.vital_temp.value = soap.querySelector('input[name="vital_temp"]')?.value || '';
        form.vital_spo2.value = soap.querySelector('input[name="vital_spo2"]')?.value || '';
        form.vital_weight.value = soap.querySelector('input[name="vital_weight"]')?.value || '';
    }
    return true;
}

// Helper: Append disease to SOAP Assessment
function appendSoapDiagnosis(text) {
    const area = document.getElementById('soap-assessment-textarea');
    if (!area) return;
    if (area.value.trim().length > 0) {
        area.value = area.value.trim() + "; " + text;
    } else {
        area.value = text;
    }
    area.focus();
}

// Quick Select for Formal Diagnosis Form
function selectQuickDiagnosis(name, icd, severity) {
    const nameInput = document.getElementById('diagnosis-name-input');
    const icdInput = document.getElementById('diagnosis-icd-input');
    const sevSelect = document.getElementById('diagnosis-severity-select');
    const badge = document.getElementById('diagnosis-selected-badge');
    const badgeText = document.getElementById('diagnosis-selected-text');
    const resultsBox = document.getElementById('diagnosis-search-results');

    if (nameInput) nameInput.value = name;
    if (icdInput) icdInput.value = icd;
    if (sevSelect && severity) sevSelect.value = severity;
    if (badge && badgeText) {
        badgeText.textContent = `${name} [${icd}]`;
        badge.classList.remove('hidden');
    }
    if (resultsBox) resultsBox.classList.add('hidden');
    if (window.lucide) lucide.createIcons();
}

// Dynamic Search for Patients & Diseases
document.addEventListener('DOMContentLoaded', function() {
    // 1. Patient Search
    const docSearchInput = document.getElementById('doctor-patient-search');
    const docResultsBox = document.getElementById('doctor-search-results');
    let docSearchTimer = null;

    if (docSearchInput && docResultsBox) {
        docSearchInput.addEventListener('input', function() {
            clearTimeout(docSearchTimer);
            const q = this.value.trim();
            if (q.length < 1) {
                docResultsBox.classList.add('hidden');
                docResultsBox.innerHTML = '';
                return;
            }
            docSearchTimer = setTimeout(() => {
                fetch('/api/search/patients?q=' + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(data => {
                        docResultsBox.innerHTML = '';
                        if (!data || !data.data || data.data.length === 0) {
                            docResultsBox.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center font-medium">No patients found.</div>';
                            docResultsBox.classList.remove('hidden');
                            return;
                        }
                        data.data.forEach(p => {
                            const item = document.createElement('div');
                            item.className = 'p-3 hover:bg-blue-50 cursor-pointer transition flex items-center justify-between text-xs';
                            item.innerHTML = `
                                <div>
                                    <p class="font-bold text-slate-900">${p.name}</p>
                                    <p class="text-[10px] text-slate-500 font-mono">ID: ${p.code} ${p.student_id ? '• Student: ' + p.student_id : ''}</p>
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 font-semibold text-slate-600">${p.gender || ''}, ${p.age ? p.age + 'y' : ''}</span>
                            `;
                            item.addEventListener('click', () => {
                                window.location.href = '?patient_id=' + p.id;
                            });
                            docResultsBox.appendChild(item);
                        });
                        docResultsBox.classList.remove('hidden');
                    })
                    .catch(() => {
                        docResultsBox.classList.add('hidden');
                    });
            }, 250);
        });

        document.addEventListener('click', function(e) {
            if (!docSearchInput.contains(e.target) && !docResultsBox.contains(e.target)) {
                docResultsBox.classList.add('hidden');
            }
        });
    }

    // 2. Disease & ICD-10 AJAX Search Autocomplete
    const diagInput = document.getElementById('diagnosis-name-input');
    const icdInput = document.getElementById('diagnosis-icd-input');
    const diagResultsBox = document.getElementById('diagnosis-search-results');
    const diagSpinner = document.getElementById('diagnosis-search-spinner');
    const diagBadge = document.getElementById('diagnosis-selected-badge');
    const diagBadgeText = document.getElementById('diagnosis-selected-text');
    const diagSevSelect = document.getElementById('diagnosis-severity-select');
    let diagTimer = null;
    let diagAbortCtrl = null;

    function renderDiseaseResults(items) {
        if (!diagResultsBox) return;
        diagResultsBox.innerHTML = '';
        if (!items || items.length === 0) {
            diagResultsBox.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center font-medium">No matching diseases or ICD-10 codes found in hospital catalog.</div>';
            diagResultsBox.classList.remove('hidden');
            return;
        }

        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'p-2.5 hover:bg-blue-50/90 rounded-xl cursor-pointer transition flex items-start justify-between gap-3 text-xs border-b border-slate-50 last:border-b-0';
            
            const leftCol = document.createElement('div');
            leftCol.className = 'space-y-0.5 min-w-0';
            
            const titleRow = document.createElement('div');
            titleRow.className = 'flex items-center gap-1.5 flex-wrap';
            
            const nameEl = document.createElement('span');
            nameEl.className = 'font-bold text-slate-900 leading-tight';
            nameEl.textContent = item.name;
            titleRow.appendChild(nameEl);

            if (item.category) {
                const catEl = document.createElement('span');
                catEl.className = 'text-[9px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-500 font-semibold';
                catEl.textContent = item.category;
                titleRow.appendChild(catEl);
            }

            leftCol.appendChild(titleRow);

            if (item.description) {
                const descEl = document.createElement('p');
                descEl.className = 'text-[10px] text-slate-500 line-clamp-1';
                descEl.textContent = item.description;
                leftCol.appendChild(descEl);
            }

            const rightCol = document.createElement('div');
            rightCol.className = 'shrink-0 text-right';
            rightCol.innerHTML = `
                <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded-full bg-blue-100 text-blue-800">${item.icd_code || 'ICD-10'}</span>
            `;

            row.appendChild(leftCol);
            row.appendChild(rightCol);

            row.addEventListener('click', () => {
                if (diagInput) diagInput.value = item.name;
                if (icdInput) icdInput.value = item.icd_code || '';
                if (diagSevSelect && item.default_severity) diagSevSelect.value = item.default_severity;
                if (diagBadge && diagBadgeText) {
                    diagBadgeText.textContent = `${item.name} [${item.icd_code}]`;
                    diagBadge.classList.remove('hidden');
                }
                diagResultsBox.classList.add('hidden');
                if (window.lucide) lucide.createIcons();
            });

            diagResultsBox.appendChild(row);
        });

        diagResultsBox.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function searchDiseases(query) {
        clearTimeout(diagTimer);
        const q = (query || '').trim();
        if (q.length < 1) {
            if (diagResultsBox) {
                diagResultsBox.classList.add('hidden');
                diagResultsBox.innerHTML = '';
            }
            return;
        }

        if (diagSpinner) diagSpinner.classList.remove('hidden');
        diagTimer = setTimeout(async () => {
            if (diagAbortCtrl) diagAbortCtrl.abort();
            diagAbortCtrl = new AbortController();

            try {
                const res = await fetch('/api/search/diseases?q=' + encodeURIComponent(q), {
                    signal: diagAbortCtrl.signal,
                    headers: { 'Accept': 'application/json' }
                });
                const payload = await res.json();
                if (diagSpinner) diagSpinner.classList.add('hidden');
                renderDiseaseResults(payload.data || []);
            } catch (err) {
                if (err.name !== 'AbortError') {
                    if (diagSpinner) diagSpinner.classList.add('hidden');
                }
            }
        }, 150);
    }

    if (diagInput) {
        diagInput.addEventListener('input', function() {
            searchDiseases(this.value);
        });
        diagInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 1) {
                searchDiseases(this.value);
            }
        });
    }

    if (icdInput) {
        icdInput.addEventListener('input', function() {
            searchDiseases(this.value);
        });
    }

    document.addEventListener('click', function(e) {
        if (diagResultsBox && diagInput && icdInput) {
            if (!diagInput.contains(e.target) && !icdInput.contains(e.target) && !diagResultsBox.contains(e.target)) {
                diagResultsBox.classList.add('hidden');
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
