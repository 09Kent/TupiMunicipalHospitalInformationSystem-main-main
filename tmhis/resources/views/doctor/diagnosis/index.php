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
$doctorId = $currentUser['doctor_id'] ?? (int) session('doctor_id', 1);

$patientModel = new Patient();
$doctorModel = new Doctor();
$consultationModel = new Consultation();
$noteModel = new ConsultationNote();
$diagnosisModel = new Diagnosis();
$treatmentModel = new TreatmentPlan();
$allDoctorsList = $doctorModel->getAll();

// Selected patient ID from query, post, or active patient
$patientId = (int) ($_POST['patient_id'] ?? $_GET['patient_id'] ?? (function_exists('request') ? request('patient_id', 0) : 0));
$appId = (int) ($_POST['appointment_id'] ?? $_POST['app_id'] ?? $_GET['appointment_id'] ?? $_GET['app_id'] ?? (function_exists('request') ? (request('appointment_id') ?: request('app_id', 0)) : 0));

if ($patientId <= 0 && $appId > 0) {
  try {
    $stmtApp = Database::getConnection()->prepare("SELECT PatientID FROM appointments WHERE AppointmentID = :aid LIMIT 1");
    $stmtApp->execute([':aid' => $appId]);
    $patIdFromApp = $stmtApp->fetchColumn();
    if ($patIdFromApp) {
      $patientId = (int) $patIdFromApp;
    }
  } catch (\Throwable $e) {
  }
}

// Fetch all doctor patients for selector
$patientList = $patientModel->getDoctorPatients($doctorId, 50, 0);

// If patientId passed, verify doctor access
if ($patientId > 0 && !$patientModel->hasDoctorAccess($doctorId, $patientId)) {
  Session::setFlash('error', 'Access Denied: You are not authorized to access this patient\'s consultation chart.');
  header('Location: ' . doctor_url('views/diagnosis/index.php'));
  if (function_exists('app') && app()->environment('testing')) {
    return;
  }
  exit;
}

// Default to first accessible patient only if none selected AND none posted
if ($patientId === 0 && !empty($patientList)) {
  $patientId = (int) $patientList[0]['PatientID'];
  $appId = (int) ($patientList[0]['AppointmentID'] ?? 0);
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
$latestNoteId = !empty($existingNotes) ? (int) $existingNotes[0]['NoteID'] : 0;

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
  } catch (\Throwable $e) {
  }
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
      'bp' => $_POST['vital_bp'] ?? '',
      'hr' => $_POST['vital_hr'] ?? '',
      'temp' => $_POST['vital_temp'] ?? '',
      'spo2' => $_POST['vital_spo2'] ?? '',
      'weight' => $_POST['vital_weight'] ?? ''
    ];

    if ($appId <= 0 && !empty($patient['Appointment']['AppointmentID'])) {
      $appId = (int) $patient['Appointment']['AppointmentID'];
    }
    if ($appId <= 0) {
      $stmtFindApp = Database::getConnection()->prepare("
                SELECT AppointmentID FROM appointments 
                WHERE PatientID = :pid 
                ORDER BY AppointmentID DESC LIMIT 1
            ");
      $stmtFindApp->execute([':pid' => $patientId]);
      $appId = (int) $stmtFindApp->fetchColumn();
    }

    // Check if existing note already exists for this patient/appointment to update rather than duplicate
    $existingNoteForApp = ($appId > 0) ? $noteModel->findByAppointment($appId) : null;
    if (!$existingNoteForApp && !empty($existingNotes)) {
      $existingNoteForApp = $existingNotes[0];
    }

    if ($existingNoteForApp && !empty($existingNoteForApp['NoteID'])) {
      $noteId = (int) $existingNoteForApp['NoteID'];
      $noteModel->update($noteId, [
        'subjective' => $subjective,
        'objective' => $objective,
        'assessment' => $assessment,
        'plan' => $plan,
        'clinical_notes' => $clinicalNotes,
        'vitals' => $vitals
      ]);
    } else {
      $noteId = $noteModel->create([
        'patient_id' => $patientId,
        'doctor_id' => $doctorId,
        'appointment_id' => $appId ?: null,
        'subjective' => $subjective,
        'objective' => $objective,
        'assessment' => $assessment,
        'plan' => $plan,
        'clinical_notes' => $clinicalNotes,
        'vitals' => $vitals
      ]);
    }

    // Also persist vitals to patient_vitals table
    try {
      $bp = $vitals['bp'] ?? '120/80';
      $hr = (int) preg_replace('/[^0-9]/', '', $vitals['hr'] ?? '74');
      $temp = (float) preg_replace('/[^0-9.]/', '', $vitals['temp'] ?? '36.7');
      $spo2 = (int) preg_replace('/[^0-9]/', '', $vitals['spo2'] ?? '98');
      $wt = (float) preg_replace('/[^0-9.]/', '', $vitals['weight'] ?? '70');
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
        ':pid' => $patientId,
        ':aid' => $appId ?: null,
        ':bp' => $bp ?: '120/80',
        ':hr' => $hr ?: 74,
        ':temp' => $temp ?: 36.7,
        ':spo2' => $spo2 ?: 98,
        ':wt' => $wt ?: 70.0,
        ':uid' => auth()->id() ?? 1,
        ':uname' => $currentUser['name'] ?? 'Attending Physician'
      ]);
    } catch (\Throwable $ve) {
    }

    // Persist working diagnosis if assessment is filled
    if (!empty($assessment)) {
      try {
        $diagCheck = Database::getConnection()->prepare("SELECT DiagnosisID FROM diagnoses WHERE PatientID = :pid AND DiagnosisName = :dname LIMIT 1");
        $diagCheck->execute([':pid' => $patientId, ':dname' => $assessment]);
        if (!$diagCheck->fetchColumn()) {
          $diagnosisModel->create([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'appointment_id' => $appId ?: null,
            'diagnosis_name' => $assessment,
            'icd10_code' => 'Z00.0',
            'type' => 'Working',
            'severity' => 'Moderate',
            'status' => 'Active',
            'notes' => 'Formulated during clinical consultation'
          ]);
        }
      } catch (\Throwable $de) {
      }
    }

    if ($noteId) {
      Session::setFlash('success', 'Consultation SOAP note recorded and updated successfully.');
      header("Location: " . doctor_url("views/diagnosis/index.php?patient_id=$patientId&app_id=$appId"));
      if (function_exists('app') && app()->environment('testing')) {
        return;
      }
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
        'patient_id' => $patientId,
        'doctor_id' => $doctorId,
        'appointment_id' => $appId ?: ($patient['Appointment']['AppointmentID'] ?? null),
        'diagnosis_name' => $diagName,
        'icd10_code' => $icdCode,
        'type' => $diagType,
        'severity' => $severity,
        'status' => 'Active',
        'notes' => $notes
      ]);

      // Synchronize assessment into consultation note so doctor's diagnosis updates the note immediately
      try {
        $latestNotes = $noteModel->findByPatient($patientId);
        if (!empty($latestNotes)) {
          $targetNoteId = (int) $latestNotes[0]['NoteID'];
          $currAss = trim($latestNotes[0]['Assessment'] ?? '');
          $updatedAss = (empty($currAss) || $currAss === 'Consultation completed' || $currAss === 'Clinical Differential & Impression evaluated')
            ? $diagName
            : ($currAss . '; ' . $diagName);
          $noteModel->update($targetNoteId, [
            'assessment' => $updatedAss,
            'clinical_notes' => trim(($latestNotes[0]['Subjective'] ?? '') . ' | Assessment: ' . $updatedAss . ' | Plan: ' . ($latestNotes[0]['Plan'] ?? ''))
          ]);
        } else {
          $noteModel->create([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'appointment_id' => $appId ?: null,
            'subjective' => $patient['Complaint']['ComplaintDescription'] ?? 'General Consultation',
            'objective' => 'Clinical physical examination conducted',
            'assessment' => $diagName,
            'plan' => 'Formulate treatment regimen and follow up',
            'clinical_notes' => "Diagnosis: $diagName ($diagType)",
            'vitals' => ['bp' => '120/80', 'hr' => '74 bpm', 'temp' => '36.7 °C', 'spo2' => '98%', 'weight' => '72 kg']
          ]);
        }
      } catch (\Throwable $ne) {
      }

      if ($diagId) {
        Session::setFlash('success', "Diagnosis '$diagName' recorded in patient chart and updated in consultation note.");
        header("Location: " . doctor_url("views/diagnosis/index.php?patient_id=$patientId&app_id=$appId"));
        if (function_exists('app') && app()->environment('testing')) {
          return;
        }
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
        'patient_id' => $patientId,
        'doctor_id' => $doctorId,
        'appointment_id' => $appId ?: ($patient['Appointment']['AppointmentID'] ?? null),
        'goal' => $goal,
        'lifestyle_recommendations' => $lifestyle,
        'medication_plan' => $medicationPlan,
        'follow_up_schedule' => $followUp,
        'follow_up_date' => $followUpDate,
        'status' => 'Active',
        'notes' => $notes
      ]);

      if ($planId) {
        $admitPatient = !empty($_POST['admit_patient']);
        if ($admitPatient) {
          $ward = trim($_POST['admission_ward'] ?? 'Cardiology Inpatient Ward');
          $room = trim($_POST['admission_room'] ?? 'Room 302');
          $bed = trim($_POST['admission_bed'] ?? 'Bed A');
          $admissionReason = trim($_POST['admission_reason'] ?? $goal);
          $nurseOrders = trim($_POST['nurse_orders'] ?? 'Intake vitals monitoring and administer treatment plan.');
          $admNum = 'ADM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 4));
          $admissionId = 0;

          // 1. Create admissions record
          try {
            $stmtAdm = Database::getConnection()->prepare("
              INSERT INTO admissions (
                AdmissionNumber, PatientID, DoctorID, AppointmentID, Ward, RoomNumber, BedNumber,
                AdmissionReason, AdmissionDate, Status, AdmittedBy, AdmittedByName, created_at, updated_at
              ) VALUES (
                :adm_num, :pid, :doc_id, :aid, :ward, :room, :bed,
                :reason, NOW(), 'Admitted', :admitted_by, :admitted_by_name, NOW(), NOW()
              )
            ");
            $stmtAdm->execute([
              ':adm_num' => $admNum,
              ':pid' => $patientId,
              ':doc_id' => $doctorId,
              ':aid' => $appId ?: ($patient['Appointment']['AppointmentID'] ?? null),
              ':ward' => $ward,
              ':room' => $room,
              ':bed' => $bed,
              ':reason' => $admissionReason,
              ':admitted_by' => auth()->id() ?? $doctorId,
              ':admitted_by_name' => 'Dr. Alexander Chen, MD'
            ]);
            $admissionId = (int)Database::getConnection()->lastInsertId();
          } catch (\Throwable $admErr) {}

          // 2. Update patient category to Inpatient
          try {
            $stmtCat = Database::getConnection()->prepare("UPDATE patients SET PatientCategory = 'Inpatient', Status = 'Active', UpdatedAt = NOW() WHERE PatientID = :pid");
            $stmtCat->execute([':pid' => $patientId]);
          } catch (\Throwable $catErr) {}

          // 3. Update queue status
          try {
            $stmtQueue = Database::getConnection()->prepare("UPDATE patient_queue SET QueueStatus = 'Admitted - Inpatient' WHERE PatientID = :pid AND QueueStatus NOT IN ('Completed', 'Discharged')");
            $stmtQueue->execute([':pid' => $patientId]);
          } catch (\Throwable $qe) {}

          // 4. Resolve on-duty nurse ID
          $nurseId = 4; // Default to Elena Gomez
          try {
            $nStmt = Database::getConnection()->query("SELECT UserID FROM users WHERE RoleID = 3 OR LOWER(Role) LIKE '%nurse%' ORDER BY UserID ASC LIMIT 1");
            $fetchedNurse = $nStmt->fetchColumn();
            if ($fetchedNurse) $nurseId = (int)$fetchedNurse;
          } catch (\Throwable $ne) {}

          // 5. Create active nurse assignment
          try {
            $stmtNurse = Database::getConnection()->prepare("
              INSERT INTO nurse_assignments (
                PatientID, DoctorID, NurseID, TaskDescription, Status, AssignedBy, AssignedByName, Notes, AssignedAt
              ) VALUES (
                :pid, :doc_id, :nid, :task_desc, 'Active', :assigned_by, :assigned_by_name, :notes, NOW()
              )
            ");
            $stmtNurse->execute([
              ':pid' => $patientId,
              ':doc_id' => $doctorId,
              ':nid' => $nurseId,
              ':task_desc' => 'Inpatient Bedside Intake & Monitoring: ' . substr($admissionReason, 0, 50),
              ':assigned_by' => auth()->id() ?? $doctorId,
              ':assigned_by_name' => 'Dr. Alexander Chen, MD',
              ':notes' => "Inpatient Admission to $ward, $room ($bed). Directives: $nurseOrders"
            ]);
          } catch (\Throwable $naErr) {}

          // 6. Create actionable nurse tasks
          try {
            $taskIns = Database::getConnection()->prepare("
              INSERT INTO nurse_tasks (
                PatientID, NurseID, DoctorID, TaskTitle, Category, Priority, Status,
                ScheduledTime, DueTime, Remarks, MedicationName, CreatedAt
              ) VALUES (
                :pid, :nid, :doc_id, :title, :cat, :prio, :stat,
                :sched, :due, :rem, :med, NOW()
              )
            ");

            // Task 1: Bedside intake vitals
            $taskIns->execute([
              ':pid' => $patientId,
              ':nid' => $nurseId,
              ':doc_id' => $doctorId,
              ':title' => 'Inpatient Bedside Intake & Vital Signs Monitoring',
              ':cat' => 'Vital Signs',
              ':prio' => 'Urgent',
              ':stat' => 'Due',
              ':sched' => date('Y-m-d H:i:s'),
              ':due' => 'Due Now',
              ':rem' => "Admitted to $ward, $room ($bed). Perform baseline vitals, SpO2, and continuous monitoring.",
              ':med' => null
            ]);

            // Task 2: Administer treatment plan
            $taskIns->execute([
              ':pid' => $patientId,
              ':nid' => $nurseId,
              ':doc_id' => $doctorId,
              ':title' => 'Administer Inpatient Treatment Plan: ' . substr($goal, 0, 45),
              ':cat' => 'Medication',
              ':prio' => 'High',
              ':stat' => 'Scheduled',
              ':sched' => date('Y-m-d H:i:s', strtotime('+1 hour')),
              ':due' => '+1 Hour',
              ':rem' => "Doctor Chen orders: $medicationPlan. Special instructions: $nurseOrders",
              ':med' => $medicationPlan ?: 'Prescribed clinical regimen'
            ]);

            // Task 3: Continuous observation
            $taskIns->execute([
              ':pid' => $patientId,
              ':nid' => $nurseId,
              ':doc_id' => $doctorId,
              ':title' => 'Ward Observation & Continuous Bedside Care',
              ':cat' => 'Observation',
              ':prio' => 'Normal',
              ':stat' => 'Scheduled',
              ':sched' => date('Y-m-d H:i:s', strtotime('+4 hours')),
              ':due' => '+4 Hours',
              ':rem' => "Ongoing care for patient in $ward, $room ($bed).",
              ':med' => null
            ]);
          } catch (\Throwable $ntErr) {}

          // 7. System Audit Log
          try {
            $stmtAudit = Database::getConnection()->prepare("
              INSERT INTO system_audit_logs (UserID, UserName, UserRole, Action, Module, RecordID, Details, IPAddress, CreatedAt)
              VALUES (:uid, 'dr.chen', 'Doctor', 'TREATMENT_PLAN_ADMISSION', 'Doctor Clinical Portal', :rec, :det, '127.0.0.1', NOW())
            ");
            $stmtAudit->execute([
              ':uid' => auth()->id() ?? $doctorId,
              ':rec' => $admissionId ?: $patientId,
              ':det' => "Dr. Alexander Chen established treatment plan and ordered inpatient admission for patient #$patientId to $ward ($room, $bed). Automatically routed to Inpatient Nurse Station."
            ]);
          } catch (\Throwable $ae) {}

          Session::setFlash('success', "Treatment plan established! Patient admitted as Inpatient to $ward ($room, $bed) and automatically routed to the Nurse Station with active care tasks.");
        } else {
          Session::setFlash('success', 'Treatment plan created successfully.');
        }

        header("Location: " . doctor_url("views/diagnosis/index.php?patient_id=$patientId&app_id=$appId"));
        if (function_exists('app') && app()->environment('testing')) {
          return;
        }
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
      'bp' => $_POST['vital_bp'] ?? '120/80',
      'hr' => $_POST['vital_hr'] ?? '74 bpm',
      'temp' => $_POST['vital_temp'] ?? '36.7 °C',
      'spo2' => $_POST['vital_spo2'] ?? '98%',
      'weight' => $_POST['vital_weight'] ?? '72 kg'
    ];

    if ($appId <= 0 && !empty($patient['Appointment']['AppointmentID'])) {
      $appId = (int) $patient['Appointment']['AppointmentID'];
    }
    if ($appId <= 0) {
      $stmtFindApp = Database::getConnection()->prepare("
                SELECT AppointmentID FROM appointments 
                WHERE PatientID = :pid 
                ORDER BY AppointmentID DESC LIMIT 1
            ");
      $stmtFindApp->execute([':pid' => $patientId]);
      $appId = (int) $stmtFindApp->fetchColumn();
    }

    $db = Database::getConnection();
    $db->beginTransaction();
    try {
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
        $noteModel->update((int) $existingNoteForApp['NoteID'], [
          'subjective' => $finalSub,
          'objective' => $finalObj,
          'assessment' => $finalAss,
          'plan' => $finalPlan,
          'clinical_notes' => $finalNotes,
          'vitals' => $vitals
        ]);
      } else {
        $noteModel->create([
          'patient_id' => $patientId,
          'doctor_id' => $doctorId,
          'appointment_id' => $appId ?: null,
          'subjective' => $finalSub,
          'objective' => $finalObj,
          'assessment' => $finalAss,
          'plan' => $finalPlan,
          'clinical_notes' => $finalNotes,
          'vitals' => $vitals
        ]);
      }

      // 2. Persist Vitals to patient_vitals table (with BMI and MAP)
      $bp = $vitals['bp'] ?? '120/80';
      $hr = (int) preg_replace('/[^0-9]/', '', $vitals['hr'] ?? '74');
      $temp = (float) preg_replace('/[^0-9.]/', '', $vitals['temp'] ?? '36.7');
      $spo2 = (int) preg_replace('/[^0-9]/', '', $vitals['spo2'] ?? '98');
      $wt = (float) preg_replace('/[^0-9.]/', '', $vitals['weight'] ?? '70');
      $ht = (float) preg_replace('/[^0-9.]/', '', $vitals['height'] ?? '165');

      $bmi = ($wt > 0 && $ht > 0) ? round($wt / (($ht / 100) ** 2), 2) : null;
      $map = null;
      if (preg_match('/(\d+)\s*\/\s*(\d+)/', $bp, $bpM)) {
        $sbp = (int) $bpM[1];
        $dbp = (int) $bpM[2];
        if ($sbp > 0 && $dbp > 0) {
          $map = round(($sbp + 2 * $dbp) / 3, 2);
        }
      }

      $stmtV = $db->prepare("
                INSERT INTO patient_vitals (
                    PatientID, AppointmentID, BloodPressure, HeartRate, RespiratoryRate,
                    Temperature, OxygenSaturation, PainScale, WeightKg, HeightCm, BMI, MeanArterialPressure,
                    ClinicalNotes, RecordedBy, RecordedByName, CreatedAt
                ) VALUES (
                    :pid, :aid, :bp, :hr, 18, :temp, :spo2, 0, :wt, :ht, :bmi, :map,
                    'Captured during clinical consultation', :uid, :uname, NOW()
                )
            ");
      $stmtV->execute([
        ':pid' => $patientId,
        ':aid' => $appId ?: null,
        ':bp' => $bp ?: '120/80',
        ':hr' => $hr ?: 74,
        ':temp' => $temp ?: 36.7,
        ':spo2' => $spo2 ?: 98,
        ':wt' => $wt ?: 70.0,
        ':ht' => $ht ?: 165.0,
        ':bmi' => $bmi,
        ':map' => $map,
        ':uid' => auth()->id() ?? 1,
        ':uname' => $currentUser['name'] ?? 'Attending Physician'
      ]);

      // 3. Persist Active Diagnosis if assessment provided
      if (!empty($finalAss) && $finalAss !== 'Consultation completed') {
        $diagCheck = $db->prepare("SELECT DiagnosisID FROM diagnoses WHERE PatientID = :pid AND DiagnosisName = :dname LIMIT 1");
        $diagCheck->execute([':pid' => $patientId, ':dname' => $finalAss]);
        if (!$diagCheck->fetchColumn()) {
          $diagnosisModel->create([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'appointment_id' => $appId ?: null,
            'diagnosis_name' => $finalAss,
            'icd10_code' => 'Z00.0',
            'type' => 'Primary',
            'severity' => 'Moderate',
            'status' => 'Active',
            'notes' => 'Formulated during clinical consultation'
          ]);
        }
      }

      // 4. Persist Treatment Plan if plan provided
      if (!empty($finalPlan)) {
        $planCheck = $db->prepare("SELECT PlanID FROM treatment_plans WHERE PatientID = :pid AND Goal = :goal LIMIT 1");
        $planCheck->execute([':pid' => $patientId, ':goal' => $finalPlan]);
        if (!$planCheck->fetchColumn()) {
          $treatmentModel->create([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'appointment_id' => $appId ?: null,
            'goal' => $finalPlan,
            'lifestyle_recommendations' => 'Adequate rest, balanced nutrition, and hydration.',
            'medication_plan' => $finalPlan,
            'follow_up_schedule' => 'Follow up in 2 weeks or PRN',
            'status' => 'Active',
            'notes' => 'Formulated during clinical consultation'
          ]);
        }
      }

      // 5. Complete consultation with doctor's explicit disposition decision
      $disposition = strtoupper(trim($_POST['disposition'] ?? 'OUTPATIENT'));
      $admissionData = [
        'ward' => trim($_POST['admission_ward'] ?? 'Medical Ward'),
        'room_number' => trim($_POST['admission_room'] ?? 'Room 201'),
        'bed_number' => trim($_POST['admission_bed'] ?? 'Bed-01'),
        'admission_reason' => trim($_POST['admission_reason'] ?? ($finalAss ?: 'Admitted for inpatient medical care and management'))
      ];

      if ($appId > 0) {
        $consultationModel->completeConsultation($appId, $doctorId, $summary ?: $finalNotes, $disposition, $admissionData);
      }

      $referralId = null;
      $refCode = null;
      if ($disposition === 'REFER') {
        $refType = $_POST['referral_type'] ?? 'Internal';
        $receivingHospital = trim($_POST['receiving_hospital'] ?? $_POST['referral_facility'] ?? '');
        $refReason = trim($_POST['referral_reason'] ?? ($finalAss ?: 'Referred for specialized clinical evaluation and care'));
        $clinicalSummary = trim($_POST['referral_summary'] ?? ($finalNotes ?: ($finalPlan ?: 'Clinical referral initiated following comprehensive physician consultation.')));
        $targetDoctorId = !empty($_POST['target_doctor_id']) ? (int) $_POST['target_doctor_id'] : null;
        $targetSpecId = !empty($_POST['target_specialty_id']) ? (int) $_POST['target_specialty_id'] : 1;
        $priority = $_POST['referral_priority'] ?? 'Urgent';
        $urgency = $priority;

        $rawAmbId = $_POST['ambulance_id'] ?? null;
        $ambulanceId = ($rawAmbId !== null && $rawAmbId !== '' && (int)$rawAmbId > 0) ? (int)$rawAmbId : null;
        $requiresAmbulance = $ambulanceId ? 1 : 0;
        $ambulanceStatus = $requiresAmbulance ? 'Dispatched' : ($rawAmbId === '0' ? 'Private Transport' : ($rawAmbId === '-1' ? 'MDRRMO Rescue' : null));

        $refCode = 'REF-' . date('Ymd') . '-' . rand(1000, 9999);

        $stmtRef = $db->prepare("
                    INSERT INTO referrals (
                        ReferralCode, ReferralType, PatientID, ReferringDoctorID, ReferringDepartment,
                        TargetSpecialtyID, TargetDoctorID, ReceivingHospital, HospitalAddress,
                        RequestedService, AmbulanceID, RequiresAmbulance, AmbulanceStatus, Reason, ClinicalSummary, Priority, Urgency, Status, TrackingStatus,
                        CreatedAt, UpdatedAt
                    ) VALUES (
                        :code, :rtype, :pid, :doc_id, :dept,
                        :tspec, :tdoc, :hosp, :haddr,
                        :serv, :amb_id, :req_amb, :amb_status, :reason, :summary, :priority, :urgency, 'Pending', :track,
                        NOW(), NOW()
                    )
                ");
        $stmtRef->execute([
          ':code' => $refCode,
          ':rtype' => ($refType === 'External' || !empty($receivingHospital)) ? 'External' : 'Internal',
          ':pid' => $patientId,
          ':doc_id' => $doctorId,
          ':dept' => $currentUser['specialty'] ?? 'Outpatient & Emergency Department',
          ':tspec' => $targetSpecId,
          ':tdoc' => $targetDoctorId,
          ':hosp' => $receivingHospital ?: null,
          ':haddr' => trim($_POST['hospital_address'] ?? 'South Cotabato'),
          ':serv' => trim($_POST['requested_service'] ?? 'Specialized Medical Evaluation / Transfer'),
          ':amb_id' => $ambulanceId,
          ':req_amb' => $requiresAmbulance,
          ':amb_status' => $ambulanceStatus,
          ':reason' => $refReason,
          ':summary' => $clinicalSummary,
          ':priority' => $priority,
          ':urgency' => $urgency,
          ':track' => ($refType === 'External' || !empty($receivingHospital)) ? 'Sent' : 'Created'
        ]);
        $referralId = (int) $db->lastInsertId();

        // Dispatch ambulance in fleet record if selected
        if ($ambulanceId > 0) {
          try {
            $ambStmt = $db->prepare("UPDATE ambulances SET Status = 'On Transfer', CurrentDestination = :dest, CurrentPatientID = :pid, EstimatedReturnTime = 'Est. 2 Hours', updated_at = NOW() WHERE AmbulanceID = :aid");
            $ambStmt->execute([
              ':dest' => $receivingHospital ?: 'Tertiary Hospital Transfer',
              ':pid' => $patientId,
              ':aid' => $ambulanceId
            ]);
          } catch (\Throwable $ue) {}
        }

        try {
          $auditStmt = $db->prepare("
                        INSERT INTO system_audit_logs (UserID, UserName, UserRole, Action, Module, RecordID, Details, IPAddress, CreatedAt)
                        VALUES (:uid, :uname, 'Doctor', 'REFERRAL_CREATED', 'Clinical Care', :rec, :det, '127.0.0.1', NOW())
                    ");
          $auditStmt->execute([
            ':uid' => $currentUser['user_id'] ?? $doctorId,
            ':uname' => $currentUser['full_name'] ?? 'Attending Physician',
            ':rec' => $referralId,
            ':det' => "Physician initiated {$refType} referral ({$refCode}) for Patient #{$patientId}." . ($requiresAmbulance ? " Dispatched Ambulance Unit #{$ambulanceId}." : "")
          ]);
        } catch (\Throwable $ae) {
        }
      }

      $db->commit();

      if ($disposition === 'ADMIT') {
        Session::setFlash('success', "Admission Created Successfully! Patient assigned to {$admissionData['ward']}, {$admissionData['room_number']} ({$admissionData['bed_number']}) and routed to Inpatient Nurse Station.");
      } elseif ($disposition === 'REFER' && $referralId) {
        $ambNotice = !empty($requiresAmbulance) ? " 🚑 Hospital Ambulance Dispatched." : "";
        Session::setFlash('success', "Patient Referral Registered (Code: {$refCode})!{$ambNotice} <a href=\"/print/referral/{$referralId}\" target=\"_blank\" class=\"underline font-bold text-blue-600 hover:text-blue-800 ml-1\">🖨️ Print Referral / Transfer Slip</a>");
        Session::setFlash('success', "Patient Referral Registered (Code: {$refCode})! <a href=\"/print/referral/{$referralId}\" target=\"_blank\" class=\"underline font-bold text-blue-600 hover:text-blue-800 ml-1\">🖨️ Print Referral / Transfer Slip</a>");
      } else {
        Session::setFlash('success', "Consultation completed as $disposition. Outpatient records updated and archived.");
      }
      header("Location: " . doctor_url("views/patients/view.php?id=$patientId"));
      if (function_exists('app') && app()->environment('testing')) {
        return;
      }
      exit;
    } catch (\Throwable $e) {
      $db->rollBack();
      $errorMessage = safe_error_message($e, 'Failed to finalize clinical encounter.');
    }
  }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen scroll-smooth">

  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="p-6 sm:p-8 space-y-8 flex-1">

    <!-- Header & Patient Selector Bar -->
    <div
      class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="p-2 rounded-xl bg-blue-50 text-blue-600">
            <i data-lucide="stethoscope" class="w-5 h-5"></i>
          </span>
          <h1 class="text-xl font-black text-slate-900 tracking-tight font-display">Clinical Diagnosis & Treatment
            Planning</h1>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-1">
          Review medical histories, formulate differential diagnoses, and prescribe comprehensive treatment regimens.
        </p>
      </div>

      <!-- Patient Search Bar -->
      <div class="relative w-full sm:w-80 md:w-96">
        <div class="relative">
          <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          <input type="text" id="doctor-patient-search" placeholder="Search patient name, ID, or patient code..."
            autocomplete="off"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition" />
        </div>
        <div id="doctor-search-results"
          class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 max-h-72 overflow-y-auto divide-y divide-slate-100">
        </div>
      </div>
    </div>

    <?php if (!$patient): ?>
      <div class="bg-white rounded-3xl p-12 text-center text-slate-400 border border-slate-200 shadow-card">
        <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 text-slate-300"></i>
        <h3 class="text-base font-bold text-slate-700">No Patient Selected</h3>
        <p class="text-xs text-slate-400 mt-1">Search and select a patient from the search bar above to start consultation and diagnosis.
        </p>
      </div>
    <?php else: ?>

      <!-- Patient Overview Banner -->
      <div
        class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-3xl p-6 shadow-xl border border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div
            class="w-12 h-12 rounded-2xl bg-blue-600 font-black text-white text-base flex items-center justify-center shadow-lg shrink-0 font-display">
            <?= strtoupper(substr($patient['FirstName'], 0, 1) . substr($patient['LastName'], 0, 1)) ?>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="text-lg font-black tracking-tight font-display">
                <?= e($patient['FirstName'] . ' ' . $patient['LastName']) ?></h2>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] font-mono bg-white/10 text-blue-300 font-bold"><?= e($patient['PatientCode']) ?></span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-400/30">
                <?= e($patient['Age']) ?>y, <?= e($patient['Gender']) ?> • Blood: <?= e($patient['BloodType']) ?>
              </span>
            </div>
            <p class="text-xs text-slate-300 mt-1">
              <strong>Complaint:</strong>
              <?= e($patient['Complaint']['ComplaintDescription'] ?? 'No complaint recorded') ?>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap shrink-0">
          <a href="/print/consultation/<?= $latestNoteId ?: $patientId ?>" target="_blank"
            class="px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 border border-white/20"
            title="Print SOAP Consultation Document">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Print Note</span>
          </a>
          <a href="/print/patient/<?= $patientId ?>" target="_blank"
            class="px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 border border-white/20"
            title="Print Comprehensive Patient Record">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            <span>Print Profile</span>
          </a>
          <button type="button" onclick="openDispositionModal()"
            class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black rounded-xl text-xs shadow-lg shadow-emerald-600/20 transition flex items-center gap-1.5">
            <i data-lucide="clipboard-check" class="w-4 h-4"></i>
            <span>Decide Disposition & Finalize</span>
          </button>
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
            Current Stage:
            <?= empty($existingDiagnoses) ? 'In Consultation' : (!empty($existingTreatmentPlans) ? 'Treatment Regimen' : 'Differential Diagnosis') ?>
          </span>
        </div>
        <div class="flex items-center gap-2 overflow-x-auto pb-1.5 scrollbar-thin scrollbar-thumb-slate-200">
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

            $cardClass = "flex-1 min-w-[105px] max-w-[135px] min-h-[74px] flex flex-col items-center justify-center p-2 rounded-2xl text-center border transition select-none " . (
              $st['done']
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800 hover:bg-emerald-100 hover:border-emerald-300'
              : (!empty($st['active'])
                ? 'bg-blue-50 border-2 border-blue-500 text-blue-900 shadow-sm ring-2 ring-blue-500/20'
                : 'bg-slate-50 border-slate-200/80 text-slate-500 hover:bg-slate-100/80')
            );
            ?>
            <?php if ($stageTarget): ?>
              <a href="<?= $stageTarget ?>" class="<?= $cardClass ?> cursor-pointer group" title="<?= e($st['label']) ?>">
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1.5 shrink-0 transition-transform group-hover:scale-110 <?= $st['done'] ? 'bg-emerald-600 text-white' : (!empty($st['active']) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') ?>">
                  <?= $st['done'] ? '✓' : ($idx + 1) ?>
                </div>
                <span
                  class="text-[10px] font-black uppercase tracking-tight leading-tight line-clamp-1"><?= e($st['label']) ?></span>
              </a>
            <?php else: ?>
              <div class="<?= $cardClass ?>" title="<?= e($st['label']) ?>">
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1.5 shrink-0 <?= $st['done'] ? 'bg-emerald-600 text-white' : (!empty($st['active']) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') ?>">
                  <?= $st['done'] ? '✓' : ($idx + 1) ?>
                </div>
                <span
                  class="text-[10px] font-black uppercase tracking-tight leading-tight line-clamp-1"><?= e($st['label']) ?></span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 5 Golden Questions Clinical Orientation Card -->
      <div
        class="bg-gradient-to-br from-blue-950 via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-blue-800/40">
        <div class="flex items-center gap-2 mb-4 border-b border-white/10 pb-3">
          <span class="p-1.5 rounded-lg bg-blue-500/20 text-blue-300">
            <i data-lucide="compass" class="w-4 h-4"></i>
          </span>
          <h3 class="text-xs font-black uppercase tracking-wider text-blue-200 font-display">Immediate Clinical
            Orientation (5 Golden Questions)</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-xs">
          <!-- 1. WHO is the Patient? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-blue-300 uppercase tracking-wider block">1. WHO is Patient?</span>
            <p class="font-extrabold text-sm text-white"><?= e($patient['FirstName'] . ' ' . $patient['LastName']) ?></p>
            <p class="text-[11px] text-slate-300"><?= e($patient['PatientCode']) ?> • <?= e($patient['Age']) ?>y
              <?= e($patient['Gender']) ?></p>
          </div>
          <!-- 2. WHAT is Happening? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-amber-300 uppercase tracking-wider block">2. WHAT is
              Happening?</span>
            <p class="font-bold text-white line-clamp-2">
              <?= e($patient['Complaint']['ComplaintDescription'] ?? 'Clinical Evaluation & Assessment') ?></p>
          </div>
          <!-- 3. WHO is Responsible? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-emerald-300 uppercase tracking-wider block">3. WHO is
              Responsible?</span>
            <p class="font-bold text-white"><?= e($assignedDoctorName) ?></p>
            <p class="text-[11px] text-emerald-300/90"><?= e($assignedNurseName) ?></p>
          </div>
          <!-- 4. CURRENT Status? -->
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <span class="text-[10px] font-black text-cyan-300 uppercase tracking-wider block">4. CURRENT Status?</span>
            <div
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-200 font-extrabold text-[11px] border border-cyan-400/30">
              <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-ping"></span>
              <?= empty($existingDiagnoses) ? 'In Consultation' : 'Diagnosed • In Plan' ?>
            </div>
            <p class="text-[10px] text-slate-300 mt-1 truncate" title="<?= e($currentTaskSummary) ?>">
              <?= e($currentTaskSummary) ?></p>
          </div>
          <!-- 5. NEXT Action? -->
          <div
            class="p-3.5 rounded-2xl bg-blue-600/30 border border-blue-400/40 space-y-1.5 flex flex-col justify-between">
            <div>
              <span class="text-[10px] font-black text-blue-200 uppercase tracking-wider block">5. NEXT Action?</span>
              <p class="font-bold text-white text-[11px]">
                <?= empty($existingNotes) ? 'Document SOAP Clinical Notes' : (empty($existingDiagnoses) ? 'Formulate Diagnostic Assessment' : 'Prescribe Regimen or Refer Patient') ?>
              </p>
            </div>
            <a href="#soapNoteSection"
              class="px-2.5 py-1 bg-blue-500 hover:bg-blue-400 text-white font-extrabold text-[10px] rounded-lg text-center transition flex items-center justify-center gap-1">
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
                <p class="font-bold text-slate-800">
                  <?= e($patient['MedicalHistory']['ExistingConditions'] ?? 'None disclosed') ?></p>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Current Medications</span>
                <p class="font-bold text-slate-800">
                  <?= e($patient['MedicalHistory']['CurrentMedications'] ?? 'None recorded') ?></p>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Past Surgeries</span>
                <p class="font-bold text-slate-800">
                  <?= e($patient['MedicalHistory']['PreviousHospitalization'] ?? 'None recorded') ?></p>
              </div>

              <div class="p-3 bg-rose-50/60 rounded-xl border border-rose-100">
                <span class="text-[10px] font-extrabold uppercase text-rose-700 block mb-1">Known Allergies</span>
                <p class="font-bold text-rose-900">
                  <?= e($patient['MedicalHistory']['Allergies'] ?? 'No known drug allergies') ?></p>
              </div>
            </div>
          </div>

          <!-- Existing Diagnoses History Card -->
          <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4">
            <h3
              class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
              <i data-lucide="file-check" class="w-4 h-4 text-emerald-600"></i>
              <span>Active Diagnoses (<?= count($existingDiagnoses) ?>)</span>
            </h3>

            <div class="space-y-2.5">
              <?php if (empty($existingDiagnoses)): ?>
                <p class="text-xs text-slate-400 italic">No formal diagnoses recorded yet.</p>
              <?php else: ?>
                <?php foreach ($existingDiagnoses as $diag): ?>
                  <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                      <strong class="text-slate-900"><?= e($diag['DiagnosisName']) ?></strong>
                      <span
                        class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-blue-100 text-blue-800 font-mono"><?= e($diag['ICD10Code'] ?: 'ICD-10') ?></span>
                    </div>
                    <div class="flex items-center justify-between flex-wrap gap-1">
                      <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                        <span><?= e($diag['Type']) ?></span>
                        <span>•</span>
                        <span><?= e($diag['Severity']) ?></span>
                        <span>•</span>
                        <span><?= format_date($diag['DiagnosedDate']) ?></span>
                      </div>
                      <button type="button"
                        onclick="loadDiagnosisProtocol('<?= addslashes($diag['DiagnosisName']) ?>', '<?= addslashes($diag['ICD10Code'] ?? '') ?>')"
                        class="text-[10px] px-2 py-0.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg border border-emerald-200 transition flex items-center gap-1">
                        <i data-lucide="zap" class="w-3 h-3 text-emerald-600"></i>
                        <span>Generate Plan</span>
                      </button>
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
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?= count($patientTimeline) ?>
                Events</span>
            </div>
            <div
              class="relative pl-5 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
              <?php if (empty($patientTimeline)): ?>
                <p class="text-xs text-slate-400 italic">No care milestones recorded yet.</p>
              <?php else: ?>
                <?php foreach (array_slice($patientTimeline, 0, 7) as $tl): ?>
                  <div class="relative">
                    <div
                      class="absolute -left-5 top-1 w-2.5 h-2.5 rounded-full bg-blue-600 border-2 border-white ring-2 ring-blue-100">
                    </div>
                    <div>
                      <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-800"><?= e($tl['title']) ?></span>
                        <span
                          class="text-[10px] font-mono text-slate-400"><?= date('H:i', strtotime($tl['timestamp'])) ?></span>
                      </div>
                      <p class="text-[11px] text-slate-500 mt-0.5"><?= e($tl['description']) ?></p>
                      <span class="text-[9px] font-semibold text-slate-400"><?= e($tl['actor']) ?> •
                        <?= date('M d, Y', strtotime($tl['timestamp'])) ?></span>
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
          <div id="soapNoteSection"
            class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-card space-y-5 scroll-mt-24">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3
                  class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2 font-display">
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
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Vital Signs
                  Capture</span>
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
                  <button type="button" onclick="appendSoapDiagnosis('Dengue Fever without Warning Signs (ICD-10: A90)')"
                    class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold transition">+
                    Dengue</button>
                  <button type="button" onclick="appendSoapDiagnosis('Acute Gastroenteritis (ICD-10: A09)')"
                    class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold transition">+
                    AGE/Diarrhea</button>
                  <button type="button" onclick="appendSoapDiagnosis('Essential Hypertension (ICD-10: I10)')"
                    class="text-[10px] px-1.5 py-0.5 rounded bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold transition">+
                    Hypertension</button>
                  <button type="button"
                    onclick="appendSoapDiagnosis('Acute Upper Respiratory Tract Infection (ICD-10: J06.9)')"
                    class="text-[10px] px-1.5 py-0.5 rounded bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-semibold transition">+
                    URTI</button>
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
                <button type="submit"
                  class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md shadow-blue-600/20 transition flex items-center gap-1.5">
                  <i data-lucide="save" class="w-4 h-4"></i>
                  <span>Save SOAP Note to MySQL</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Add Diagnosis & Add Treatment Plan Forms Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

            <!-- Card A: Add Diagnosis Form -->
            <div id="diagnosisSection"
              class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4 scroll-mt-24">
              <h3
                class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 font-display">
                <i data-lucide="plus-circle" class="w-4 h-4 text-blue-600"></i>
                <span>Add Formal Diagnosis</span>
              </h3>

              <form method="POST" action="" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                <input type="hidden" name="action" value="save_diagnosis" />

                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Diagnosis Name
                      *</label>
                    <span class="text-[9px] text-blue-600 font-bold flex items-center gap-1">
                      <i data-lucide="sparkles" class="w-3 h-3"></i>
                      <span>ICD-10 Dynamic Search Active</span>
                    </span>
                  </div>
                  <div class="relative">
                    <input type="text" id="diagnosis-name-input" name="diagnosis_name" required
                      placeholder="Search disease or code (e.g. Dengue, Diarrhea, I10)..." autocomplete="off"
                      class="w-full pl-8 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition" />
                    <i data-lucide="search"
                      class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none"></i>
                    <span id="diagnosis-search-spinner" class="hidden absolute right-2.5 top-2.5 text-blue-600">
                      <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                    </span>

                    <!-- Disease Search Results Dropdown -->
                    <div id="diagnosis-search-results"
                      class="hidden absolute left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 max-h-64 overflow-y-auto divide-y divide-slate-100 p-1.5 space-y-1">
                    </div>
                  </div>

                  <!-- Quick Select Chips for Common Diagnoses -->
                  <div class="flex items-center gap-1.5 flex-wrap pt-1.5">
                    <span class="text-[9px] font-extrabold uppercase tracking-tight text-slate-400">Common:</span>
                    <button type="button"
                      onclick="selectQuickDiagnosis('Rabies Virus Infection (Lyssavirus Encephalitis)', 'A82.9', 'Critical')"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold transition flex items-center gap-1">
                      <span>Rabies</span><span class="font-mono text-[9px] opacity-70">A82.9</span>
                    </button>
                    <button type="button"
                      onclick="selectQuickDiagnosis('Dengue Fever without Warning Signs', 'A90', 'Moderate')"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold transition flex items-center gap-1">
                      <span>Dengue</span><span class="font-mono text-[9px] opacity-70">A90</span>
                    </button>
                    <button type="button"
                      onclick="selectQuickDiagnosis('Diarrhea and Gastroenteritis (Presumed Infectious Origin)', 'A09', 'Moderate')"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold transition flex items-center gap-1">
                      <span>Diarrhea</span><span class="font-mono text-[9px] opacity-70">A09</span>
                    </button>
                    <button type="button"
                      onclick="selectQuickDiagnosis('Essential (Primary) Hypertension', 'I10', 'Moderate')"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold transition flex items-center gap-1">
                      <span>Hypertension</span><span class="font-mono text-[9px] opacity-70">I10</span>
                    </button>
                    <button type="button"
                      onclick="selectQuickDiagnosis('Type 2 Diabetes Mellitus without Complications', 'E11.9', 'Moderate')"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold transition flex items-center gap-1">
                      <span>Diabetes</span><span class="font-mono text-[9px] opacity-70">E11.9</span>
                    </button>
                    <button type="button"
                      onclick="selectQuickDiagnosis('Acute Upper Respiratory Tract Infection (URTI)', 'J06.9', 'Mild')"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-bold transition flex items-center gap-1">
                      <span>URTI</span><span class="font-mono text-[9px] opacity-70">J06.9</span>
                    </button>
                  </div>
                </div>

                <!-- Verified Selection Pill -->
                <div id="diagnosis-selected-badge"
                  class="hidden p-2 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold flex items-center justify-between transition">
                  <div class="flex items-center gap-1.5 truncate">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                    <span id="diagnosis-selected-text" class="truncate"></span>
                  </div>
                  <span
                    class="text-[9px] font-mono px-2 py-0.5 bg-emerald-200/60 rounded-md text-emerald-900 shrink-0">ICD-10
                    Verified</span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">ICD-10
                      Code</label>
                    <input type="text" id="diagnosis-icd-input" name="icd_code" placeholder="e.g. I20.9"
                      autocomplete="off"
                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Type</label>
                    <select id="diagnosis-type-select" name="diag_type"
                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800">
                      <option value="Primary">Primary</option>
                      <option value="Secondary">Secondary</option>
                      <option value="Differential">Differential</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Severity</label>
                  <select id="diagnosis-severity-select" name="severity"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800">
                    <option value="Mild">Mild</option>
                    <option value="Moderate" selected>Moderate</option>
                    <option value="Severe">Severe</option>
                    <option value="Critical">Critical</option>
                  </select>
                </div>

                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Clinical
                    Notes</label>
                  <textarea name="notes" rows="2" placeholder="Diagnostic criteria or rationale..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800"></textarea>
                </div>

                <button type="submit"
                  class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i>
                  <span>Record Diagnosis</span>
                </button>
              </form>
            </div>

            <!-- Card B: Add Treatment Plan Form -->
            <div id="treatmentPlanSection"
              class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card space-y-4 scroll-mt-24">
              <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3
                  class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-2 font-display">
                  <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                  <span>Add Treatment Plan</span>
                </h3>
                <span class="text-[9px] text-emerald-600 font-bold flex items-center gap-1">
                  <i data-lucide="sparkles" class="w-3 h-3"></i>
                  <span>Auto-Protocol Synced</span>
                </span>
              </div>

              <!-- Auto-Protocol Notification Banner -->
              <div id="treatment-protocol-banner"
                class="hidden p-3 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 text-emerald-950 transition space-y-1.5 animate-in fade-in duration-200">
                <div class="flex items-center justify-between flex-wrap gap-1.5">
                  <div class="flex items-center gap-2 min-w-0">
                    <span
                      class="w-5 h-5 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold shadow-xs shrink-0">⚡</span>
                    <div class="min-w-0 truncate">
                      <span
                        class="text-[11px] font-black uppercase tracking-tight text-emerald-900 mr-1">Auto-Protocol:</span>
                      <span id="treatment-protocol-title" class="text-xs font-extrabold text-emerald-700 truncate"></span>
                    </div>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <button type="button" onclick="resetToAutoProtocol()" title="Reapply clinical guideline"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-white border border-emerald-200 hover:bg-emerald-100 text-emerald-800 font-bold transition">Reapply</button>
                    <button type="button" onclick="clearTreatmentPlanInputs()" title="Clear to write custom method"
                      class="text-[10px] px-2 py-0.5 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 text-slate-600 font-bold transition">Clear</button>
                  </div>
                </div>
                <p class="text-[10px] text-emerald-700 font-medium">Pre-filled with clinical guidelines • You can freely
                  adjust dosages, lifestyle goals, or change methods below.</p>
              </div>

              <form method="POST" action="" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                <input type="hidden" name="action" value="save_treatment_plan" />

                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Treatment Goal
                      *</label>
                    <span class="text-[9px] text-slate-400">Target outcome</span>
                  </div>
                  <input type="text" id="plan-goal-input" name="goal" required
                    placeholder="e.g. Alleviate symptoms, stabilize resting BP..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
                </div>

                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Lifestyle & Diet
                      Recommendations</label>
                    <span class="text-[9px] text-slate-400">Diet, activity & non-pharmacologic</span>
                  </div>
                  <input type="text" id="plan-lifestyle-input" name="lifestyle"
                    placeholder="e.g. Low sodium diet, 30 min daily walking..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
                </div>

                <div class="relative">
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Medication Regimen
                      Overview</label>
                    <span class="text-[9px] font-semibold text-emerald-600 flex items-center gap-1">
                      <i data-lucide="sparkles" class="w-3 h-3 text-emerald-500"></i> Searchable Medications
                    </span>
                  </div>
                  <div class="relative">
                    <input type="text" id="plan-medication-input" name="medication_plan"
                      placeholder="Type medicine name (e.g. Amoxicillin, Paracetamol, Co-amoxiclav) or full regimen..."
                      autocomplete="off"
                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
                    <div id="plan-medication-results" class="hidden absolute z-50 left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-xl divide-y divide-slate-100 p-1"></div>
                  </div>
                  <div class="mt-1.5 flex items-center gap-1 flex-wrap text-[10px]">
                    <span class="text-[9px] font-bold text-slate-400 mr-0.5">QUICK RX:</span>
                    <button type="button" onclick="appendMedicationPlan('Amoxicillin Trihydrate 500mg TID x 7 days')" class="px-2 py-0.5 rounded-full font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition border border-emerald-200">+ Amoxicillin 500mg</button>
                    <button type="button" onclick="appendMedicationPlan('Paracetamol 500mg PO q6h PRN')" class="px-2 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition border border-slate-200">+ Paracetamol 500mg</button>
                    <button type="button" onclick="appendMedicationPlan('Co-amoxiclav 625mg PO BID x 7 days')" class="px-2 py-0.5 rounded-full font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition border border-blue-200">+ Co-amoxiclav</button>
                    <button type="button" onclick="appendMedicationPlan('Salbutamol 2mg/5ml syr TID')" class="px-2 py-0.5 rounded-full font-semibold bg-purple-50 text-purple-700 hover:bg-purple-100 transition border border-purple-200">+ Salbutamol</button>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">Follow-up
                      Interval</label>
                    <input type="text" id="plan-followup-sched-input" name="follow_up_sched" placeholder="e.g. 2 Weeks"
                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
                  </div>
                  <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">Target
                      Date</label>
                    <input type="date" id="plan-followup-date-input" name="follow_up_date"
                      value="<?= date('Y-m-d', strtotime('+14 days')) ?>"
                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
                  </div>
                </div>

                <!-- Patient Disposition & Clinical Care Trajectory -->
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3 mt-2">
                  <div class="flex items-center justify-between">
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                      <i data-lucide="bed-double" class="w-3.5 h-3.5 text-blue-600"></i>
                      <span>Patient Disposition & Care Trajectory</span>
                    </label>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Physician Order</span>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <label class="relative flex items-center p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-blue-400 transition select-none">
                      <input type="radio" name="admit_patient" value="0" checked onchange="togglePlanAdmission(false)" class="text-blue-600 focus:ring-blue-500" />
                      <div class="ml-2">
                        <span class="block font-bold text-slate-800 text-[11px]">Outpatient Regimen</span>
                        <span class="block text-[9px] text-slate-400">Prescription & scheduled review</span>
                      </div>
                    </label>

                    <label class="relative flex items-center p-2.5 rounded-xl border border-blue-200 bg-blue-50/50 cursor-pointer hover:border-blue-500 transition select-none">
                      <input type="radio" name="admit_patient" value="1" onchange="togglePlanAdmission(true)" class="text-blue-600 focus:ring-blue-500" />
                      <div class="ml-2">
                        <span class="block font-bold text-blue-950 text-[11px]">Direct Inpatient Admission</span>
                        <span class="block text-[9px] text-blue-600 font-semibold">Route directly to Nurse Station</span>
                      </div>
                    </label>
                  </div>

                  <!-- Inpatient Admission Controls (Collapsible) -->
                  <div id="plan-admission-details" class="hidden pt-3 border-t border-slate-200/80 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                      <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Inpatient Ward</label>
                        <select name="admission_ward" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800">
                          <option value="Cardiology Inpatient Ward" selected>Cardiology Inpatient Ward (5th Flr)</option>
                          <option value="Medical Ward (General)">Medical Ward (General)</option>
                          <option value="Intensive Care Unit (ICU)">Intensive Care Unit (ICU)</option>
                          <option value="Surgical / Step-down Ward">Surgical / Step-down Ward</option>
                          <option value="Pediatric Ward">Pediatric Ward</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Room Number</label>
                        <input type="text" name="admission_room" value="Room 302" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800" />
                      </div>
                      <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Bed Number</label>
                        <input type="text" name="admission_bed" value="Bed A" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800" />
                      </div>
                    </div>

                    <div>
                      <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Clinical Admission Indication</label>
                      <input type="text" name="admission_reason" placeholder="e.g. Inpatient cardiac monitoring, IV stabilization, continuous vitals tracking" value="<?= e($patient['Complaint']['ComplaintDescription'] ?? 'Inpatient clinical admission and continuous bedside monitoring.') ?>" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800" />
                    </div>

                    <div>
                      <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Bedside Nurse Directives / Special Orders</label>
                      <input type="text" name="nurse_orders" placeholder="e.g. Continuous telemetry, vitals q2h, strict I&O, bedside head elevated 30°" value="Intake vitals monitoring, initiate prescribed treatment plan, monitor oxygen saturation." class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800" />
                    </div>
                  </div>
                </div>

                <button type="submit" id="btn-submit-treatment-plan"
                  class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i>
                  <span id="treatment-plan-btn-text">Establish Treatment Plan</span>
                </button>
              </form>
            </div>

          </div>

        </div>

      </div>

      <!-- ========================================================
         MODAL: DOCTOR DISPOSITION DECISION (Prompt Section 20)
         ======================================================== -->
      <div id="modal-disposition"
        class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div
          class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 relative space-y-5 animate-in fade-in zoom-in-95 duration-200">

          <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
              <div
                class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-md">
                <i data-lucide="stethoscope" class="w-5 h-5"></i>
              </div>
              <div>
                <h3 class="text-base font-extrabold text-slate-900 font-display">DECIDE PATIENT DISPOSITION</h3>
                <p class="text-xs text-slate-400">Select clinical care trajectory after consultation</p>
              </div>
            </div>
            <button type="button" onclick="closeDispositionModal()"
              class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100">
              <i data-lucide="x" class="w-4 h-4"></i>
            </button>
          </div>

          <!-- Patient Quick Summary -->
          <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-center justify-between text-xs">
            <div>
              <span class="text-slate-400 text-[10px] uppercase font-bold block">Patient</span>
              <strong class="text-slate-800 text-sm"><?= e($patient['FirstName'] . ' ' . $patient['LastName']) ?></strong>
              <span class="text-[11px] text-slate-500 font-mono ml-1">(<?= e($patient['PatientCode']) ?>)</span>
            </div>
            <div class="text-right">
              <span class="text-slate-400 text-[10px] uppercase font-bold block">Admitting Physician</span>
              <strong class="text-slate-800"><?= e($assignedDoctorName) ?></strong>
            </div>
          </div>

          <!-- Master Form -->
          <form id="dispositionMasterForm" method="POST" action="" onsubmit="return handleDispositionFormSubmit(event)">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
            <input type="hidden" name="action" value="complete_consultation" />
            <input type="hidden" name="disposition" id="form-disposition-value" value="OUTPATIENT" />

            <!-- Hidden SOAP fields passed through -->
            <input type="hidden" name="subjective" id="disp-subjective" value="" />
            <input type="hidden" name="objective" id="disp-objective" value="" />
            <input type="hidden" name="assessment" id="disp-assessment" value="" />
            <input type="hidden" name="plan" id="disp-plan" value="" />
            <input type="hidden" name="vital_bp" id="disp-vital-bp" value="" />
            <input type="hidden" name="vital_hr" id="disp-vital-hr" value="" />
            <input type="hidden" name="vital_temp" id="disp-vital-temp" value="" />
            <input type="hidden" name="vital_spo2" id="disp-vital-spo2" value="" />
            <input type="hidden" name="vital_weight" id="disp-vital-weight" value="" />

            <!-- Disposition Options Selection -->
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Doctor's Disposition
                Decision *</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button type="button" id="btn-disp-outpatient" onclick="setDispositionMode('OUTPATIENT')"
                  class="p-3 rounded-2xl border-2 text-center transition flex flex-col items-center gap-1.5 border-blue-600 bg-blue-50/70 text-blue-900 font-bold shadow-xs">
                  <span class="text-lg">🏥</span>
                  <span class="text-xs">Outpatient</span>
                </button>
                <button type="button" id="btn-disp-admit" onclick="setDispositionMode('ADMIT')"
                  class="p-3 rounded-2xl border-2 text-center transition flex flex-col items-center gap-1.5 border-slate-200 bg-white text-slate-600 hover:border-slate-300 font-bold">
                  <span class="text-lg">🛏️</span>
                  <span class="text-xs">Admit Patient</span>
                </button>
                <button type="button" id="btn-disp-refer" onclick="setDispositionMode('REFER')"
                  class="p-3 rounded-2xl border-2 text-center transition flex flex-col items-center gap-1.5 border-slate-200 bg-white text-slate-600 hover:border-slate-300 font-bold">
                  <span class="text-lg">🔄</span>
                  <span class="text-xs">Refer</span>
                </button>
                <button type="button" id="btn-disp-followup" onclick="setDispositionMode('FOLLOW-UP')"
                  class="p-3 rounded-2xl border-2 text-center transition flex flex-col items-center gap-1.5 border-slate-200 bg-white text-slate-600 hover:border-slate-300 font-bold">
                  <span class="text-lg">📅</span>
                  <span class="text-xs">Follow-up</span>
                </button>
              </div>
            </div>

            <!-- Section A: Outpatient / Discharge Details -->
            <div id="section-disp-outpatient"
              class="p-4 bg-blue-50/60 rounded-2xl border border-blue-100 text-xs text-blue-900 space-y-2 mt-3">
              <div class="flex items-center gap-2 font-bold text-blue-950">
                <i data-lucide="info" class="w-4 h-4 text-blue-600"></i>
                <span>Outpatient Care Pathway</span>
              </div>
              <p class="text-slate-600">Patient will be discharged for outpatient continuation. Active prescriptions and
                lab orders will route directly to Pharmacy & Laboratory. Patient is <strong>not</strong> admitted to
                inpatient ward.</p>
            </div>

            <!-- Section B: Inpatient Admission Details (Section 20 of prompt) -->
            <div id="section-disp-admit"
              class="hidden p-4 bg-amber-50/50 rounded-2xl border border-amber-200/80 space-y-3.5 mt-3">
              <div class="flex items-center gap-2 text-amber-900 font-bold text-xs">
                <i data-lucide="bed-double" class="w-4 h-4 text-amber-700"></i>
                <span>Inpatient Admission Placement</span>
              </div>

              <div>
                <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Admission Reason / Clinical
                  Indication *</label>
                <input type="text" id="disp-admission-reason" name="admission_reason"
                  value="<?= !empty($existingDiagnoses) ? e($existingDiagnoses[0]['DiagnosisName']) : 'Inpatient medical management and clinical observation' ?>"
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800" />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Ward *</label>
                  <select id="disp-admission-ward" name="admission_ward"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    <option value="Medical Ward" selected>Medical Ward</option>
                    <option value="Surgical Ward">Surgical Ward</option>
                    <option value="Pediatric Ward">Pediatric Ward</option>
                    <option value="OB-GYN Ward">OB-GYN Ward</option>
                    <option value="Intensive Care Unit (ICU)">ICU</option>
                    <option value="Isolation Ward">Isolation Ward</option>
                  </select>
                </div>

                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Room Number *</label>
                  <input type="text" id="disp-admission-room" name="admission_room" value="Room 201"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800" />
                </div>

                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Bed Number *</label>
                  <input type="text" id="disp-admission-bed" name="admission_bed" value="Bed B-12"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800" />
                </div>
              </div>

              <p class="text-[11px] text-amber-800 font-medium">⚠️ Patient will be placed in the Nurse Inpatient Station
                and an active admission episode will be opened.</p>
            </div>

            <!-- Section C: Referral Details (Internal Doctor Handover & External Hospital Transfer) -->
            <div id="section-disp-refer"
              class="hidden p-4 bg-purple-50/70 rounded-2xl border border-purple-200/80 space-y-3.5 mt-3">
              <div class="flex items-center justify-between pb-2 border-b border-purple-200/60">
                <div class="flex items-center gap-2 text-purple-900 font-bold text-xs">
                  <i data-lucide="share-2" class="w-4 h-4 text-purple-700"></i>
                  <span class="font-extrabold uppercase tracking-wide">Patient Referral & Transfer Protocol</span>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-200 text-purple-800">
                  Official Clinical Endorsement
                </span>
              </div>

              <!-- Referral Pathway Switcher (Internal Doctor vs External Hospital) -->
              <input type="hidden" name="referral_type" id="disp-referral-type" value="External" />
              <div class="grid grid-cols-2 gap-2">
                <button type="button" id="btn-ref-tab-external" onclick="switchReferralPathway('External')"
                  class="px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex items-center justify-center gap-2 border-purple-600 bg-purple-600 text-white shadow-sm">
                  <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                  <span>Refer to Other Hospital</span>
                </button>
                <button type="button" id="btn-ref-tab-internal" onclick="switchReferralPathway('Internal')"
                  class="px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex items-center justify-center gap-2 border-slate-200 bg-white text-slate-700 hover:border-slate-300">
                  <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                  <span>Pass to Another Doctor</span>
                </button>
              </div>

              <!-- SUB-PANEL 1: EXTERNAL HOSPITAL TRANSFER (Hospital Can't Take Patient In) -->
              <div id="panel-ref-external" class="space-y-3 pt-1">
                <div
                  class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                  <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                  <div class="leading-relaxed">
                    <strong class="font-bold">Hospital Capacity & Capability Transfer:</strong> Use when TMHIS cannot
                    accommodate the patient due to lack of ICU, specialized surgical facilities, advanced imaging, or
                    emergency hemodialysis.
                  </div>
                </div>

                <!-- Receiving Hospital Quick Select -->
                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Receiving Tertiary Hospital
                    *</label>
                  <div class="flex flex-wrap gap-1.5 mb-1.5">
                    <button type="button"
                      onclick="selectReceivingHospital('South Cotabato Provincial Hospital (SCPH)', 'Koronadal City, South Cotabato')"
                      class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white hover:bg-purple-100 text-purple-900 border border-purple-200 transition">
                      🏥 SCPH (Koronadal)
                    </button>
                    <button type="button"
                      onclick="selectReceivingHospital('General Santos Doctors Hospital (GSDH)', 'National Highway, General Santos City')"
                      class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white hover:bg-purple-100 text-purple-900 border border-purple-200 transition">
                      🏥 GenSan Doctors
                    </button>
                    <button type="button"
                      onclick="selectReceivingHospital('St. Elizabeth Hospital', 'National Highway, General Santos City')"
                      class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white hover:bg-purple-100 text-purple-900 border border-purple-200 transition">
                      🏥 St. Elizabeth
                    </button>
                    <button type="button"
                      onclick="selectReceivingHospital('Allison Medical Center', 'Surallah, South Cotabato')"
                      class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white hover:bg-purple-100 text-purple-900 border border-purple-200 transition">
                      🏥 Allison (Surallah)
                    </button>
                    <button type="button"
                      onclick="selectReceivingHospital('Howard Hubbard Memorial Hospital', 'Polomolok, South Cotabato')"
                      class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white hover:bg-purple-100 text-purple-900 border border-purple-200 transition">
                      🏥 Howard Hubbard
                    </button>
                  </div>
                  <input type="text" id="disp-receiving-hospital" name="receiving_hospital"
                    value="South Cotabato Provincial Hospital (SCPH)" placeholder="Name of receiving hospital"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800" />
                  <input type="hidden" id="disp-hospital-address" name="hospital_address"
                    value="Koronadal City, South Cotabato" />
                </div>

                <!-- Reason Hospital Cannot Accommodate Patient -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Reason TMHIS Cannot
                      Accommodate *</label>
                    <select id="disp-inability-reason" onchange="updateReferralReasonFromSelect(this)"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800">
                      <option value="Lack of Intensive Care Unit (ICU) / Ventilator Bed">Lack of ICU / Ventilator Bed
                      </option>
                      <option value="Specialized Surgical / Orthopedic Trauma Intervention Required">Specialized Surgery /
                        Trauma Required</option>
                      <option value="Advanced Diagnostic Imaging (CT Scan / MRI) Required">Advanced Imaging (CT / MRI)
                        Required</option>
                      <option value="Emergency Hemodialysis / Nephrology Center Required">Emergency Hemodialysis Required
                      </option>
                      <option value="Neonatal ICU (NICU) Bed Unavailable">Neonatal ICU (NICU) Bed Unavailable</option>
                      <option value="Cardiac Catheterization / Interventional Cardiology Required">Cardiac Cath Lab /
                        Interventional Cardiology</option>
                      <option value="Patient / Family Explicit Request for Tertiary Transfer">Family Request for Tertiary
                        Transfer</option>
                    </select>
                  </div>
                  <div>
                    <div class="flex items-center justify-between mb-1">
                      <label class="block text-[10px] font-bold uppercase text-slate-500">Transport & Ambulance *</label>
                      <span id="disp-amb-badge" class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800">
                        Checking Fleet...
                      </span>
                    </div>
                    <select name="ambulance_id" id="disp-ambulance-select" onchange="updateDispAmbulanceInfo(this)"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500">
                      <option value="1">Ambulance Unit 01 (ALS - Advanced Life Support) [SGA-1041]</option>
                      <option value="2">Ambulance Unit 02 (BLS - Basic Life Support) [SKD-5520]</option>
                      <option value="3">Ambulance Unit 03 (Rapid Transport) [SMM-9832]</option>
                      <option value="0">Private Vehicle (Family Dispatched)</option>
                      <option value="-1">LGU / MDRRMO Disaster Emergency Rescue Unit</option>
                    </select>
                    <input type="hidden" name="requested_service" id="disp-requested-service" value="TMHIS Emergency Ambulance Transfer" />
                  </div>
                </div>

                <!-- Live Ambulance Fleet Status Box for Physician -->
                <div id="disp-amb-info-strip" class="p-2.5 bg-purple-50/80 border border-purple-200 rounded-xl text-xs space-y-1">
                  <div class="flex items-center justify-between font-bold text-purple-950">
                    <span id="disp-amb-name-label">Ambulance Unit 01 (ALS - Advanced Life Support)</span>
                    <span class="font-mono text-[11px] text-purple-700" id="disp-amb-contact-label">Hotline: 0917-882-9011</span>
                  </div>
                  <div class="flex items-center justify-between text-[11px] text-slate-600">
                    <span>Driver on Duty: <strong id="disp-amb-driver-label" class="text-slate-800">Reynaldo Gomez (EMT-B)</strong></span>
                    <span class="text-emerald-700 font-bold" id="disp-amb-ready-label">✅ Available for Immediate Transfer</span>
                  </div>
                </div>

                <div id="disp-amb-alert-strip" class="hidden p-2.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 font-bold flex items-center gap-2">
                  <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                  <span>Warning: All hospital ambulances are currently on transit. Consider patient private transport or coordinating with MDRRMO Rescue.</span>
                </div>

                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Clinical Indication & Specific
                    Referral Reason *</label>
                  <input type="text" id="disp-referral-reason" name="referral_reason"
                    value="Lack of Intensive Care Unit (ICU) / Ventilator Bed"
                    placeholder="e.g. Severe respiratory failure requiring mechanical ventilation unavailable at TMH"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800" />
                </div>

                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">En-route Interventions &
                    Transfer Clinical Summary</label>
                  <textarea name="referral_summary" rows="2"
                    placeholder="Document patient vitals, IV lines, oxygen flow rate, and emergency medications administered prior to ambulance transfer..."
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800"></textarea>
                </div>
              </div>

              <!-- SUB-PANEL 2: INTERNAL DOCTOR ENDORSEMENT (Pass to Another Doctor / Specialty) -->
              <div id="panel-ref-internal" class="hidden space-y-3 pt-1">
                <div
                  class="p-3 bg-blue-50 rounded-xl border border-blue-200 text-blue-900 text-xs flex items-start gap-2.5">
                  <i data-lucide="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
                  <div class="leading-relaxed">
                    <strong class="font-bold">Internal Medical Handover:</strong> Transfer patient case or request
                    co-management evaluation with another attending physician or specialty within TMHIS.
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Target Attending
                      Doctor</label>
                    <select name="target_doctor_id"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                      <option value="">-- Any Available Specialty Doctor --</option>
                      <?php if (!empty($allDoctorsList)): ?>
                        <?php foreach ($allDoctorsList as $dItem): ?>
                          <?php if ((int) $dItem['DoctorID'] !== $doctorId): ?>
                            <option value="<?= (int) $dItem['DoctorID'] ?>">
                              Dr. <?= e($dItem['FirstName'] . ' ' . $dItem['LastName']) ?>
                              (<?= e($dItem['Specialty'] ?? 'General Medicine') ?>)
                            </option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Target Department /
                      Specialty</label>
                    <select name="target_specialty_id"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                      <option value="1">General Medicine / Internal Medicine</option>
                      <option value="2">Pediatrics & Neonatal Care</option>
                      <option value="3">General Surgery & Trauma</option>
                      <option value="4">Obstetrics & Gynecology (OB-GYN)</option>
                      <option value="5">Emergency & Critical Care</option>
                    </select>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Referral Priority</label>
                    <select name="referral_priority"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                      <option value="Routine">Routine - Next Available Consultation</option>
                      <option value="Urgent" selected>Urgent - Same Day Clinical Evaluation</option>
                      <option value="Emergency">STAT / Emergency Handover</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Handover Endorsement
                      Reason</label>
                    <input type="text" name="internal_reason"
                      placeholder="e.g. Pediatric evaluation required for severe persistent fever"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800" />
                  </div>
                </div>
              </div>

            </div>

            <!-- Section D: Follow-up Details -->
            <div id="section-disp-followup"
              class="hidden p-4 bg-teal-50/60 rounded-2xl border border-teal-100 space-y-3 mt-3">
              <div class="flex items-center gap-2 text-teal-900 font-bold text-xs">
                <i data-lucide="calendar" class="w-4 h-4 text-teal-700"></i>
                <span>Scheduled Follow-Up Review</span>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Follow-up Date</label>
                  <input type="date" name="follow_up_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800" />
                </div>
                <div>
                  <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Instructions</label>
                  <input type="text" name="follow_up_instructions" placeholder="e.g. Fasting 8 hrs prior to lab"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800" />
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5 mt-4">
              <button type="button" onclick="closeDispositionModal()"
                class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                Cancel
              </button>
              <button type="submit" id="btn-submit-disposition"
                class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span id="btn-submit-disposition-label">Finalize Outpatient Consultation</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- ========================================================
         MODAL: ADMISSION CONFIRMATION (Prompt Section 21)
         ======================================================== -->
      <div id="modal-confirm-admission"
        class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
        <div
          class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 relative space-y-4 animate-in fade-in zoom-in-95 duration-200">

          <div class="text-center pb-2 border-b border-slate-100">
            <div
              class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 mx-auto flex items-center justify-center mb-2 shadow-sm">
              <i data-lucide="bed" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-black text-slate-900 font-heading">CONFIRM ADMISSION</h3>
            <p class="text-xs text-slate-400">Please review inpatient ward placement</p>
          </div>

          <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2.5 text-xs">
            <div class="flex justify-between">
              <span class="text-slate-400">Patient:</span>
              <strong class="text-slate-900"><?= e($patient['FirstName'] . ' ' . $patient['LastName']) ?></strong>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Reason:</span>
              <span id="confirm-adm-reason" class="font-bold text-slate-800 text-right max-w-[200px] truncate"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Admitting Doctor:</span>
              <span class="font-bold text-slate-800"><?= e($assignedDoctorName) ?></span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-blue-900 font-bold">
              <span>Ward:</span>
              <span id="confirm-adm-ward">Medical Ward</span>
            </div>
            <div class="flex justify-between text-blue-900 font-bold">
              <span>Bed / Room:</span>
              <span id="confirm-adm-bed">Bed B-12 (Room 201)</span>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="closeAdmissionConfirmation()"
              class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
              Cancel
            </button>
            <button type="button" onclick="submitAdmissionConfirmed()"
              class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-1.5">
              <i data-lucide="check-circle" class="w-4 h-4"></i>
              <span>Confirm Admission</span>
          </div>
        </div>
      </div>

    <?php endif; ?>

  </main>

</div>

<script>
  let currentDisposition = 'OUTPATIENT';

  function openDispositionModal() {
    const modal = document.getElementById('modal-disposition');
    if (!modal) return;

    // Copy current SOAP and vitals values into hidden form fields
    const soap = document.getElementById('soapNoteForm');
    if (soap) {
      document.getElementById('disp-subjective').value = soap.querySelector('textarea[name="subjective"]')?.value || '';
      document.getElementById('disp-objective').value = soap.querySelector('textarea[name="objective"]')?.value || '';
      document.getElementById('disp-assessment').value = soap.querySelector('textarea[name="assessment"]')?.value || '';
      document.getElementById('disp-plan').value = soap.querySelector('textarea[name="plan"]')?.value || '';
      document.getElementById('disp-vital-bp').value = soap.querySelector('input[name="vital_bp"]')?.value || '';
      document.getElementById('disp-vital-hr').value = soap.querySelector('input[name="vital_hr"]')?.value || '';
      document.getElementById('disp-vital-temp').value = soap.querySelector('input[name="vital_temp"]')?.value || '';
      document.getElementById('disp-vital-spo2').value = soap.querySelector('input[name="vital_spo2"]')?.value || '';
      document.getElementById('disp-vital-weight').value = soap.querySelector('input[name="vital_weight"]')?.value || '';

      const currentAss = soap.querySelector('textarea[name="assessment"]')?.value?.trim();
      const admReasonInput = document.getElementById('disp-admission-reason');
      if (currentAss && admReasonInput && (!admReasonInput.value || admReasonInput.value === 'Inpatient medical management and clinical observation')) {
        admReasonInput.value = currentAss;
      }
    }

    setDispositionMode(currentDisposition);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (window.lucide) lucide.createIcons();
  }

  function closeDispositionModal() {
    const modal = document.getElementById('modal-disposition');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
  }

  function setDispositionMode(mode) {
    currentDisposition = mode;
    document.getElementById('form-disposition-value').value = mode;

    const sections = {
      'OUTPATIENT': document.getElementById('section-disp-outpatient'),
      'ADMIT': document.getElementById('section-disp-admit'),
      'REFER': document.getElementById('section-disp-refer'),
      'FOLLOW-UP': document.getElementById('section-disp-followup')
    };

    const buttons = {
      'OUTPATIENT': document.getElementById('btn-disp-outpatient'),
      'ADMIT': document.getElementById('btn-disp-admit'),
      'REFER': document.getElementById('btn-disp-refer'),
      'FOLLOW-UP': document.getElementById('btn-disp-followup')
    };

    const submitBtn = document.getElementById('btn-submit-disposition');
    const submitLabel = document.getElementById('btn-submit-disposition-label');

    // Toggle active classes on buttons and sections
    for (const [key, sec] of Object.entries(sections)) {
      if (!sec) continue;
      if (key === mode) sec.classList.remove('hidden');
      else sec.classList.add('hidden');
    }

    for (const [key, btn] of Object.entries(buttons)) {
      if (!btn) continue;
      if (key === mode) {
        btn.className = 'p-3 rounded-2xl border-2 text-center transition flex flex-col items-center gap-1.5 border-blue-600 bg-blue-50/70 text-blue-900 font-bold shadow-xs';
      } else {
        btn.className = 'p-3 rounded-2xl border-2 text-center transition flex flex-col items-center gap-1.5 border-slate-200 bg-white text-slate-600 hover:border-slate-300 font-bold';
      }
    }

    if (mode === 'ADMIT') {
      submitBtn.className = 'px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-2';
      submitLabel.innerText = 'Review & Confirm Admission';
    } else if (mode === 'REFER') {
      submitBtn.className = 'px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-2';
      submitLabel.innerText = 'Finalize & Route Referral';
      fetchDispositionAmbulances();
    } else if (mode === 'FOLLOW-UP') {
      submitBtn.className = 'px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-2';
      submitLabel.innerText = 'Finalize & Schedule Follow-Up';
    } else {
      submitBtn.className = 'px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-2';
      submitLabel.innerText = 'Finalize Outpatient Consultation';
    }

    if (window.lucide) lucide.createIcons();
  }

  function switchReferralPathway(pathway) {
    const typeInput = document.getElementById('disp-referral-type');
    if (typeInput) typeInput.value = pathway;

    const btnExt = document.getElementById('btn-ref-tab-external');
    const btnInt = document.getElementById('btn-ref-tab-internal');
    const panelExt = document.getElementById('panel-ref-external');
    const panelInt = document.getElementById('panel-ref-internal');

    if (pathway === 'External') {
      if (panelExt) panelExt.classList.remove('hidden');
      if (panelInt) panelInt.classList.add('hidden');
      if (btnExt) btnExt.className = 'px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex items-center justify-center gap-2 border-purple-600 bg-purple-600 text-white shadow-sm';
      if (btnInt) btnInt.className = 'px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex items-center justify-center gap-2 border-slate-200 bg-white text-slate-700 hover:border-slate-300';
      fetchDispositionAmbulances();
    } else {
      if (panelInt) panelInt.classList.remove('hidden');
      if (panelExt) panelExt.classList.add('hidden');
      if (btnInt) btnInt.className = 'px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex items-center justify-center gap-2 border-purple-600 bg-purple-600 text-white shadow-sm';
      if (btnExt) btnExt.className = 'px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex items-center justify-center gap-2 border-slate-200 bg-white text-slate-700 hover:border-slate-300';
    }
    if (window.lucide) lucide.createIcons();
  }

  function selectReceivingHospital(name, address) {
    const input = document.getElementById('disp-receiving-hospital');
    const addrInput = document.getElementById('disp-hospital-address');
    if (input) input.value = name;
    if (addrInput) addrInput.value = address;
  }

  function updateReferralReasonFromSelect(selectEl) {
    const reasonInput = document.getElementById('disp-referral-reason');
    if (reasonInput && selectEl) {
      reasonInput.value = selectEl.value;
    }
  }

  let DISP_AMBULANCES = [];

  function fetchDispositionAmbulances() {
    fetch('/api/ambulances')
      .then(res => res.json())
      .then(res => {
        if (!res.success || !res.data) return;
        DISP_AMBULANCES = res.data;
        const total = res.summary ? res.summary.total : res.data.length;
        const avail = res.summary ? res.summary.available : res.data.filter(a => a.status === 'Available').length;

        const badge = document.getElementById('disp-amb-badge');
        const alertStrip = document.getElementById('disp-amb-alert-strip');
        const select = document.getElementById('disp-ambulance-select');

        if (badge) {
          if (avail > 0) {
            badge.textContent = `${avail} of ${total} Ready`;
            badge.className = 'text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800';
            if (alertStrip) alertStrip.classList.add('hidden');
          } else {
            badge.textContent = `0 of ${total} Available`;
            badge.className = 'text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-rose-100 text-rose-800';
            if (alertStrip) alertStrip.classList.remove('hidden');
          }
        }

        if (select) {
          select.innerHTML = '';
          res.data.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            const isAvail = a.status === 'Available';
            opt.textContent = `${a.unit_name} [${a.plate_number}] - ${isAvail ? 'AVAILABLE' : (a.status === 'On Transfer' ? 'IN TRANSIT' : 'MAINTENANCE')}`;
            if (!isAvail) opt.disabled = true;
            select.appendChild(opt);
          });
          const optPriv = document.createElement('option');
          optPriv.value = "0";
          optPriv.textContent = "Private Vehicle (Family Dispatched)";
          select.appendChild(optPriv);

          const optLgu = document.createElement('option');
          optLgu.value = "-1";
          optLgu.textContent = "LGU / MDRRMO Disaster Emergency Rescue Unit";
          select.appendChild(optLgu);

          const firstAvail = res.data.find(a => a.status === 'Available');
          if (firstAvail) {
            select.value = firstAvail.id;
          } else {
            select.value = "0";
          }
          updateDispAmbulanceInfo(select);
        }
      })
      .catch(() => {});
  }

  function updateDispAmbulanceInfo(selectEl) {
    const val = parseInt(selectEl.value, 10);
    const infoStrip = document.getElementById('disp-amb-info-strip');
    const nameLabel = document.getElementById('disp-amb-name-label');
    const contactLabel = document.getElementById('disp-amb-contact-label');
    const driverLabel = document.getElementById('disp-amb-driver-label');
    const readyLabel = document.getElementById('disp-amb-ready-label');
    const reqServiceInput = document.getElementById('disp-requested-service');

    if (!infoStrip) return;

    if (val > 0) {
      const amb = DISP_AMBULANCES.find(a => a.id === val);
      if (amb) {
        if (nameLabel) nameLabel.textContent = amb.unit_name;
        if (contactLabel) contactLabel.textContent = `Hotline: ${amb.contact || '+63 (083) 228-0001'}`;
        if (driverLabel) driverLabel.textContent = amb.driver || 'Duty EMT';
        if (readyLabel) {
          readyLabel.textContent = '✅ Available for Immediate Transfer';
          readyLabel.className = 'text-emerald-700 font-bold';
        }
        if (reqServiceInput) reqServiceInput.value = `TMHIS Ambulance (${amb.unit_name} - ${amb.plate_number})`;
        infoStrip.classList.remove('hidden');
      }
    } else if (val === 0) {
      if (nameLabel) nameLabel.textContent = 'Private / Family Vehicle Transport';
      if (contactLabel) contactLabel.textContent = 'Family Arranged';
      if (driverLabel) driverLabel.textContent = 'Family Driver';
      if (readyLabel) {
        readyLabel.textContent = '🚗 Non-Ambulance Transport';
        readyLabel.className = 'text-slate-600 font-bold';
      }
      if (reqServiceInput) reqServiceInput.value = 'Private Vehicle Transport';
      infoStrip.classList.remove('hidden');
    } else if (val === -1) {
      if (nameLabel) nameLabel.textContent = 'LGU / MDRRMO Rescue Vehicle';
      if (contactLabel) contactLabel.textContent = 'Hotline: (083) 228-2999';
      if (driverLabel) driverLabel.textContent = 'MDRRMO Rescue EMT';
      if (readyLabel) {
        readyLabel.textContent = '🚒 Municipal Disaster Rescue';
        readyLabel.className = 'text-amber-700 font-bold';
      }
      if (reqServiceInput) reqServiceInput.value = 'MDRRMO Rescue Vehicle';
      infoStrip.classList.remove('hidden');
    }
  }

  function handleDispositionFormSubmit(event) {
    if (currentDisposition === 'ADMIT') {
      event.preventDefault();
      // Prompt Section 21: Show Admission Confirmation Modal
      openAdmissionConfirmation();
      return false;
    }
    return true;
  }

  function openAdmissionConfirmation() {
    const confirmModal = document.getElementById('modal-confirm-admission');
    if (!confirmModal) return;

    const reason = document.getElementById('disp-admission-reason')?.value || 'Acute clinical indication';
    const ward = document.getElementById('disp-admission-ward')?.value || 'Medical Ward';
    const room = document.getElementById('disp-admission-room')?.value || 'Room 201';
    const bed = document.getElementById('disp-admission-bed')?.value || 'Bed B-12';

    document.getElementById('confirm-adm-reason').innerText = reason;
    document.getElementById('confirm-adm-ward').innerText = ward;
    document.getElementById('confirm-adm-bed').innerText = `${bed} (${room})`;

    confirmModal.classList.remove('hidden');
    confirmModal.classList.add('flex');
    if (window.lucide) lucide.createIcons();
  }

  function closeAdmissionConfirmation() {
    const confirmModal = document.getElementById('modal-confirm-admission');
    if (confirmModal) {
      confirmModal.classList.add('hidden');
      confirmModal.classList.remove('flex');
    }
  }

  function submitAdmissionConfirmed() {
    closeAdmissionConfirmation();
    const form = document.getElementById('dispositionMasterForm');
    if (form) {
      form.submit();
    }
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

  // Clinical Protocol Dictionary for Instant Client-Side Population
  const CLINICAL_PROTOCOLS = {
      'J02.9': {
        goal: 'Symptom resolution, eradicate pharyngeal pathogen, and prevent suppurative complications.',
        lifestyle: 'Warm saline gargle 3-4x daily; Oral fluid intake >2.5L/day; Soft non-acidic diet; Rest.',
        medication_plan: 'Paracetamol 500mg PO q6h PRN for throat pain/fever; Amoxicillin 500mg PO TID x 7 days (if bacterial); Throat lozenges.',
        follow_up_schedule: '5-7 Days',
        follow_up_days: 7
      },
      'J03.9': {
        goal: 'Reduce tonsillar edema, eliminate exudate, and restore comfortable oral swallowing.',
        lifestyle: 'Warm salt water gargles; Soft, cool foods; Avoid spicy and acidic foods; Adequate rest.',
        medication_plan: 'Co-amoxiclav 625mg PO BID x 7 days OR Amoxicillin 500mg PO TID x 7 days; Paracetamol 500mg PO q6h PRN.',
        follow_up_schedule: '5-7 Days',
        follow_up_days: 7
      },
      'J00': {
        goal: 'Relieve nasal congestion and rhinorrhea, prevent secondary sinus infection.',
        lifestyle: 'Steam inhalation twice daily; Increase oral fluids; Vitamin C rich fruits; Proper rest.',
        medication_plan: 'Paracetamol 500mg PO q6h PRN; Phenylephrine + Chlorphenamine PO TID; Normal saline nasal spray PRN.',
        follow_up_schedule: '5-7 Days',
        follow_up_days: 7
      },
      'J06.9': {
        goal: 'Alleviate upper airway mucosal inflammation, clear congestion, and ensure swift clinical recovery.',
        lifestyle: 'Adequate oral hydration (>2L/day); Rest; Avoid cold drinks and dusty environments.',
        medication_plan: 'Paracetamol 500mg PO q6h PRN for fever/body aches; Cetirizine 10mg PO OD at bedtime; Saline nasal drops.',
        follow_up_schedule: '5-7 Days',
        follow_up_days: 7
      },
      'J20.9': {
        goal: 'Reduce bronchial tree inflammation, facilitate sputum expectoration, and relieve paroxysmal cough.',
        lifestyle: 'Adequate oral hydration to thin secretions; Humidified room air; Avoid tobacco smoke exposure.',
        medication_plan: 'Ambroxol 30mg PO TID x 7 days; Salbutamol 2mg PO TID if bronchospasm present; Paracetamol 500mg PRN.',
        follow_up_schedule: '1-2 Weeks',
        follow_up_days: 10
      },
      'J18.9': {
        goal: 'Eradicate pulmonary infection, clear alveolar consolidation, and maintain SpO2 > 95%.',
        lifestyle: 'Chest physiotherapy / deep breathing exercises; High-calorie, high-protein diet; Bed rest.',
        medication_plan: 'Amoxicillin-Clavulanic Acid 625mg PO TID x 7 days + Azithromycin 500mg PO OD x 3 days; Salbutamol nebulization PRN.',
        follow_up_schedule: '3-5 Days',
        follow_up_days: 5
      },
      'J45.9': {
        goal: 'Reverse bronchospasm, prevent nocturnal wheezing, and maintain normal daily physical activity.',
        lifestyle: 'Identify and eliminate environmental allergen triggers (dust, fur, smoke); Follow personal Asthma Action Plan.',
        medication_plan: 'Salbutamol MDI 2 puffs q4-6h PRN wheezing; Budesonide/Formoterol maintenance inhaler 1 puff BID.',
        follow_up_schedule: '1-2 Weeks',
        follow_up_days: 14
      },
      'A90': {
        goal: 'Maintain normovolemic fluid status, monitor hematocrit/platelet progression, and prevent complications.',
        lifestyle: 'Strict oral fluid replacement with ORS, fruit juice, coconut water; Mosquito netting; Absolute bed rest.',
        medication_plan: 'Paracetamol 500mg PO q6h PRN (Strictly AVOID NSAIDs/Aspirin/Mefenamic); Oral Rehydration Salts (ORS) ad libitum.',
        follow_up_schedule: 'Daily CBC & Platelet Audit',
        follow_up_days: 1
      },
      'A91': {
        goal: 'Immediate hospital stabilization, monitor plasma leakage, and maintain stable pulse pressure.',
        lifestyle: 'Inpatient observation; Strict fluid balance input/output recording; Bed rest.',
        medication_plan: 'Intravenous fluid therapy (Plain LRS / D5LRS); Paracetamol IV/PO PRN; Daily serial CBC/platelet monitoring.',
        follow_up_schedule: 'Daily Inpatient Rounds',
        follow_up_days: 1
      },
      'A09': {
        goal: 'Restore intestinal electrolyte balance, halt dehydration, and normalize stool consistency.',
        lifestyle: 'BRAT diet (Banana, Rice, Applesauce, Toast); Avoid fatty, dairy, and caffeine products; Frequent sips of ORS.',
        medication_plan: 'Oral Rehydration Salts (ORS) 1 sachet per 1L boiled water; Zinc Sulfate 20mg PO OD x 14 days; Paracetamol 500mg PRN.',
        follow_up_schedule: '3-5 Days',
        follow_up_days: 3
      },
      'A09.0': {
        goal: 'Manage mild fluid loss, control nausea, and maintain adequate oral hydration.',
        lifestyle: 'Oral fluid intake with ORS after every loose bowel movement; Normal bland feeding.',
        medication_plan: 'Oral Rehydration Salts (ORS); Zinc Sulfate 20mg OD x 14 days; Probiotics 1 cap PO BID.',
        follow_up_schedule: '3 Days',
        follow_up_days: 3
      },
      'I10': {
        goal: 'Achieve target blood pressure < 130/80 mmHg and reduce long-term cardiovascular risk.',
        lifestyle: 'DASH diet: restrict sodium < 2g/day; 30 minutes daily aerobic physical activity; Weight control; Avoid smoking.',
        medication_plan: 'Amlodipine 5mg PO OD morning OR Losartan 50mg PO OD morning; Record daily morning blood pressure log.',
        follow_up_schedule: '2-4 Weeks',
        follow_up_days: 14
      },
      'E11.9': {
        goal: 'Maintain glycemic control: Fasting Blood Sugar 80-130 mg/dL and HbA1c < 7.0%.',
        lifestyle: 'Low glycemic index meals; Restrict refined sugars; 150 min/week structured physical exercise; Daily foot inspection.',
        medication_plan: 'Metformin 500mg PO BID with meals; Monitor self-monitoring blood glucose (SMBG) log twice weekly.',
        follow_up_schedule: '1 Month',
        follow_up_days: 30
      },
      'K29.7': {
        goal: 'Suppress gastric acid secretion, heal eroded mucosal lining, and relieve epigastric discomfort.',
        lifestyle: 'Avoid skipping meals; Small, frequent meals; Avoid spicy, acidic, oily foods, alcohol, and caffeine.',
        medication_plan: 'Omeprazole 20-40mg PO OD 30 minutes before breakfast x 14 days; Antacid suspension 10ml PO PRN after meals.',
        follow_up_schedule: '2 Weeks',
        follow_up_days: 14
      },
      'K21.9': {
        goal: 'Eliminate acid regurgitation and substernal pyrosis, prevent esophageal mucosal erosion.',
        lifestyle: 'Do not lie down within 3 hours after meals; Elevate head of bed 15cm; Avoid tight clothing; Weight reduction.',
        medication_plan: 'Esomeprazole 40mg PO OD 30 mins before breakfast x 14-28 days; Aluminum/Magnesium Antacid 10ml PRN.',
        follow_up_schedule: '2-4 Weeks',
        follow_up_days: 21
      },
      'N39.0': {
        goal: 'Eradicate urinary bacterial proliferation, relieve dysuria and frequency, normalize urinalysis.',
        lifestyle: 'Increase oral fluid intake > 3 liters/day; Do not delay urination; Good perineal hygiene; Urinate after intercourse.',
        medication_plan: 'Ciprofloxacin 500mg PO BID x 7 days OR Co-amoxiclav 625mg PO BID x 7 days; Phenazopyridine 100mg TID x 2 days PRN.',
        follow_up_schedule: '1 Week',
        follow_up_days: 7
      },
      'G44.2': {
        goal: 'Relieve pericranial myofascial tension and eliminate band-like headache pressure.',
        lifestyle: 'Stress management; Ergonomic desk posture; Regular sleep schedule 7-8 hours; Neck/shoulder stretching.',
        medication_plan: 'Paracetamol 500mg + Ibuprofen 200mg PO with meals PRN; Eperisone 50mg PO TID if neck spasm present.',
        follow_up_schedule: '2 Weeks',
        follow_up_days: 14
      },
      'M54.5': {
        goal: 'Relieve lumbar muscle spasm, reduce lower back pain score, and restore spinal mobility.',
        lifestyle: 'Apply warm compress to lumbar area 20 min TID; Avoid heavy lifting; Sleep on firm mattress with pillow under knees.',
        medication_plan: 'Mefenamic Acid 500mg PO TID with meals x 5 days; Eperisone 50mg PO TID x 5 days; Topical Diclofenac gel BID.',
        follow_up_schedule: '1-2 Weeks',
        follow_up_days: 10
      },
      'A82.9': {
        goal: 'Immediate post-exposure prophylaxis to neutralize rabies virus prior to CNS infiltration; intensive supportive critical care.',
        lifestyle: 'Immediate aggressive wound flushing under running water with detergent soap for at least 15 minutes; isolate biting domestic animal for 14-day observation.',
        medication_plan: 'Rabies Vaccine (PCECV/PVRV) Day 0, 3, 7, 28; Equine/Human Rabies Immune Globulin (ERIG/HRIG) infiltration at wound site; Tetanus Toxoid 0.5 mL IM; Co-amoxiclav 625mg PO BID x 7 days.',
        follow_up_schedule: 'Day 3 Rabies Dose',
        follow_up_days: 3
      },
      'Z20.3': {
        goal: 'Prevent rabies virus invasion following animal exposure and prevent secondary bacterial wound infection.',
        lifestyle: 'Wound cleaning with soap and running water for 15 minutes; Observe biting animal for 14 days if domestic.',
        medication_plan: 'Anti-Rabies Vaccine (PCECV) on Day 0, 3, 7, 28; Tetanus Toxoid 0.5ml IM; Co-amoxiclav 625mg PO BID x 5 days.',
        follow_up_schedule: 'Day 3 Rabies Dose',
        follow_up_days: 3
      },
      'W54.0': {
        goal: 'Wound debridement, rabies post-exposure prophylaxis, tetanus prophylaxis, and secondary infection prevention.',
        lifestyle: 'Wash wound thoroughly with clean water and povidone-iodine; do not tightly suture high-risk puncture wounds; keep dressing dry.',
        medication_plan: 'Co-amoxiclav 625mg PO BID x 7 days; Anti-Rabies Vaccine (PCECV) Day 0, 3, 7, 28; Tetanus Toxoid 0.5 mL IM; Paracetamol 500mg PO Q6H PRN for pain.',
        follow_up_schedule: 'Day 3 Rabies Dose & Wound Check',
        follow_up_days: 3
      },
      'A00.9': {
        goal: 'Immediate aggressive rehydration, correction of metabolic acidosis and hypokalemia, antimicrobial eradication.',
        lifestyle: 'Strict enteric isolation, safe water sanitation, continuous ORS administration.',
        medication_plan: 'Ringer\'s Lactate IV rapid infusion; Doxycycline 300mg PO single dose OR Azithromycin 1g PO single dose; Zinc supplementation.',
        follow_up_schedule: 'Daily Inpatient Rounds',
        follow_up_days: 1
      },
      'A01.0': {
        goal: 'Eradication of Salmonella typhi, prevention of bowel perforation, and fever defervescence.',
        lifestyle: 'Strict personal hygiene, drink only boiled/potable water, soft low-fiber diet during active intestinal inflammation.',
        medication_plan: 'Ciprofloxacin 500mg PO BID x 10 days OR Ceftriaxone 2g IV OD x 7 days; Paracetamol 500mg PO Q6H PRN.',
        follow_up_schedule: '5 Days',
        follow_up_days: 5
      },
      'A15.0': {
        goal: 'Microbiologic cure via DOTS protocol, prevent transmission, and prevent drug resistance.',
        lifestyle: 'Cough etiquette, well-ventilated living quarters, high-protein diet, absolute smoking cessation.',
        medication_plan: 'Fixed-dose combination anti-TB therapy (Rifampicin, Isoniazid, Pyrazinamide, Ethambutol) daily for 2 months intensive phase, then Rifampicin/Isoniazid for 4 months.',
        follow_up_schedule: 'Monthly DOTS Review',
        follow_up_days: 30
      },
      'A27.9': {
        goal: 'Elimination of Leptospira, prevention of renal failure (Weil\'s syndrome) and pulmonary hemorrhage.',
        lifestyle: 'Avoid wading in flood waters; wear rubber boots; maintain hydration and report dark urine or jaundice.',
        medication_plan: 'Doxycycline 100mg PO BID x 7 days (mild) OR Penicillin G 1.5 million units IV Q6H x 7 days (moderate-severe).',
        follow_up_schedule: '3 Days (Renal Panel Check)',
        follow_up_days: 3
      },
      'B50.9': {
        goal: 'Clearance of blood parasites, prevention of cerebral malaria, and prevention of relapse.',
        lifestyle: 'Insecticide-treated bed nets, prompt fever reporting, adequate oral fluids.',
        medication_plan: 'Artemether-Lumefantrine (Coartem) standard weight-based 3-day course; Primaquine single dose on Day 1 for gametocyte clearance.',
        follow_up_schedule: 'Day 3 & Day 7 Blood Smear',
        follow_up_days: 3
      },
      'U07.1': {
        goal: 'Prevent hypoxic respiratory failure, manage inflammatory cascade, and isolate during infectious window.',
        lifestyle: '7-day home isolation, pulse oximetry monitoring twice daily (report SpO2 <94%), mask wearing, fluid hydration.',
        medication_plan: 'Paracetamol 500mg PO Q6H PRN; Vitamin C 500mg + Zinc 20mg PO OD; Nirmatrelvir/Ritonavir (Paxlovid) for high-risk patients within 5 days.',
        follow_up_schedule: '5 Days',
        follow_up_days: 5
      }
    };

    let currentActiveProtocol = null;

    // Apply treatment plan protocol to the form inputs
    function applyDiagnosisTreatmentPlan(proto, diagName, icdCode) {
      if (!proto) return;
      currentActiveProtocol = { ...proto, diseaseName: diagName, icdCode: icdCode };

      const goalInput = document.getElementById('plan-goal-input');
      const lifestyleInput = document.getElementById('plan-lifestyle-input');
      const medInput = document.getElementById('plan-medication-input');
      const schedInput = document.getElementById('plan-followup-sched-input');
      const dateInput = document.getElementById('plan-followup-date-input');
      const banner = document.getElementById('treatment-protocol-banner');
      const bannerTitle = document.getElementById('treatment-protocol-title');

      if (goalInput && proto.goal) goalInput.value = proto.goal;
      if (lifestyleInput && proto.lifestyle) lifestyleInput.value = proto.lifestyle;
      if (medInput && (proto.medication_plan || proto.meds)) medInput.value = proto.medication_plan || proto.meds;
      if (schedInput && (proto.follow_up_schedule || proto.follow_up)) schedInput.value = proto.follow_up_schedule || proto.follow_up;

      if (dateInput) {
        if (proto.follow_up_date) {
          dateInput.value = proto.follow_up_date;
        } else {
          const days = proto.follow_up_days || proto.days || 14;
          const d = new Date();
          d.setDate(d.getDate() + days);
          dateInput.value = d.toISOString().split('T')[0];
        }
      }

      if (banner && bannerTitle) {
        bannerTitle.textContent = `${diagName} [${icdCode}]`;
        banner.classList.remove('hidden');
      }
      if (window.lucide) lucide.createIcons();
    }

    function togglePlanAdmission(admit) {
      const box = document.getElementById('plan-admission-details');
      const btnText = document.getElementById('treatment-plan-btn-text');
      const btn = document.getElementById('btn-submit-treatment-plan');
      if (admit) {
        if (box) box.classList.remove('hidden');
        if (btnText) btnText.textContent = 'Establish Treatment Plan & Admit to Nurse Station';
        if (btn) {
          btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
          btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        }
      } else {
        if (box) box.classList.add('hidden');
        if (btnText) btnText.textContent = 'Establish Treatment Plan';
        if (btn) {
          btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
          btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
        }
      }
      if (window.lucide) lucide.createIcons();
    }

    function resetToAutoProtocol() {
      if (currentActiveProtocol) {
        applyDiagnosisTreatmentPlan(currentActiveProtocol, currentActiveProtocol.diseaseName, currentActiveProtocol.icdCode);
      }
    }

    function clearTreatmentPlanInputs() {
      const goalInput = document.getElementById('plan-goal-input');
      const lifestyleInput = document.getElementById('plan-lifestyle-input');
      const medInput = document.getElementById('plan-medication-input');
      const schedInput = document.getElementById('plan-followup-sched-input');
      const banner = document.getElementById('treatment-protocol-banner');

      if (goalInput) goalInput.value = '';
      if (lifestyleInput) lifestyleInput.value = '';
      if (medInput) medInput.value = '';
      if (schedInput) schedInput.value = '';
      if (banner) banner.classList.add('hidden');
      if (goalInput) goalInput.focus();
    }

    function loadDiagnosisProtocol(name, icd) {
      const proto = CLINICAL_PROTOCOLS[icd] || {
        goal: `Symptom stabilization, control of ${name}, and clinical recovery.`,
        lifestyle: 'Adequate rest, balanced nutrition, stay hydrated, avoid smoking and alcohol.',
        medication_plan: 'Prescribed pharmacological therapy and supportive clinical care as indicated.',
        follow_up_schedule: '2 Weeks',
        follow_up_days: 14
      };
      applyDiagnosisTreatmentPlan(proto, name, icd);
      const planSec = document.getElementById('treatmentPlanSection');
      if (planSec) planSec.scrollIntoView({ behavior: 'smooth' });
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

      // Auto-populate related treatment plan
      const proto = CLINICAL_PROTOCOLS[icd] || {
        goal: `Symptom stabilization, control of ${name}, and clinical recovery.`,
        lifestyle: 'Adequate rest, balanced nutrition, stay hydrated, avoid smoking and alcohol.',
        medication_plan: 'Prescribed pharmacological therapy and supportive clinical care as indicated.',
        follow_up_schedule: '2 Weeks',
        follow_up_days: 14
      };
      applyDiagnosisTreatmentPlan(proto, name, icd);

      if (window.lucide) lucide.createIcons();
    }

    // Comprehensive Master Diseases & ICD-10 Hospital Catalog
    const MASTER_DISEASES_CATALOG = [
      {
        id: 9001,
        name: 'Rabies Virus Infection (Lyssavirus Encephalitis)',
        disease_name: 'Rabies Virus Infection (Lyssavirus Encephalitis)',
        icd_code: 'A82.9',
        icd10_code: 'A82.9',
        category: 'Zoonotic Infection',
        description: 'Acute fatal viral encephalomyelitis caused by rabies virus transmitted via animal bite/saliva; requires urgent post-exposure prophylaxis.',
        default_severity: 'Critical',
        treatment_plan: CLINICAL_PROTOCOLS['A82.9']
      },
      {
        id: 9002,
        name: 'Contact / Exposure to Rabies (Animal Bite Exposure)',
        disease_name: 'Contact / Exposure to Rabies (Animal Bite Exposure)',
        icd_code: 'Z20.3',
        icd10_code: 'Z20.3',
        category: 'Preventive / Emergency',
        description: 'Potential lyssavirus exposure from mammalian bite or scratch requiring anti-rabies vaccination protocol.',
        default_severity: 'Severe',
        treatment_plan: CLINICAL_PROTOCOLS['Z20.3']
      },
      {
        id: 9003,
        name: 'Bitten by Dog (Animal Bite - Dog)',
        disease_name: 'Bitten by Dog (Animal Bite - Dog)',
        icd_code: 'W54.0',
        icd10_code: 'W54.0',
        category: 'Injury / Trauma',
        description: 'Canine bite injury with risk of secondary soft tissue infection (Pasteurella, Capnocytophaga) and rabies transmission.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['W54.0']
      },
      {
        id: 9004,
        name: 'Contact with Cat (Cat Scratch / Bite)',
        disease_name: 'Contact with Cat (Cat Scratch / Bite)',
        icd_code: 'W55.0',
        icd10_code: 'W55.0',
        category: 'Injury / Trauma',
        description: 'Feline scratch or bite with high risk of deep inoculation and Bartonella / Pasteurella infection.',
        default_severity: 'Moderate',
        treatment_plan: {
          goal: 'Prevent cat scratch disease (Bartonella henselae) and rabies transmission; monitor localized lymphadenopathy.',
          lifestyle: 'Wash scratch or bite site immediately with antiseptic soap; observe cat for 14 days.',
          medication_plan: 'Co-amoxiclav 625mg PO BID x 7 days or Azithromycin 500mg PO OD x 3 days; Anti-Rabies Vaccine Day 0, 3, 7; Tetanus Toxoid 0.5 mL IM.',
          follow_up_schedule: 'Day 3 Post-Exposure Dose',
          follow_up_days: 3
        }
      },
      {
        id: 9005,
        name: 'Dengue Fever without Warning Signs',
        disease_name: 'Dengue Fever without Warning Signs',
        icd_code: 'A90',
        icd10_code: 'A90',
        category: 'Vector-Borne Infection',
        description: 'Acute viral flavivirus infection transmitted by Aedes aegypti; high fever, myalgia, arthralgia, retro-orbital pain.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['A90']
      },
      {
        id: 9006,
        name: 'Dengue Hemorrhagic Fever with Warning Signs',
        disease_name: 'Dengue Hemorrhagic Fever with Warning Signs',
        icd_code: 'A91',
        icd10_code: 'A91',
        category: 'Vector-Borne Infection',
        description: 'Dengue infection with persistent vomiting, mucosal bleeding, abdominal tenderness, or thrombocytopenia.',
        default_severity: 'Severe',
        treatment_plan: {
          goal: 'Immediate hospital admission, close hemodynamic monitoring, and controlled intravenous crystalloid resuscitation.',
          lifestyle: 'Inpatient bed rest; strict hourly intake and output chart; serial hematocrit and platelet count monitoring.',
          medication_plan: 'Intravenous fluid hydration (D5 0.9 NaCl or Plain Lactated Ringer\'s); IV Paracetamol 1g Q6H for hyperpyrexia.',
          follow_up_schedule: 'Hourly Inpatient Vitals Check',
          follow_up_days: 1
        }
      },
      {
        id: 9007,
        name: 'Diarrhea and Gastroenteritis (Presumed Infectious Origin)',
        disease_name: 'Diarrhea and Gastroenteritis (Presumed Infectious Origin)',
        icd_code: 'A09',
        icd10_code: 'A09',
        category: 'Infectious & Parasitic',
        description: 'Acute loose/watery stools with nausea, vomiting, or abdominal cramps; common in food/waterborne exposure.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['A09']
      },
      {
        id: 9008,
        name: 'Typhoid Fever (Salmonella Typhi)',
        disease_name: 'Typhoid Fever (Salmonella Typhi)',
        icd_code: 'A01.0',
        icd10_code: 'A01.0',
        category: 'Infectious & Parasitic',
        description: 'Systemic enteric fever characterized by step-ladder pyrexia, hepatosplenomegaly, and headache.',
        default_severity: 'Severe',
        treatment_plan: CLINICAL_PROTOCOLS['A01.0']
      },
      {
        id: 9009,
        name: 'Cholera (Vibrio Cholerae)',
        disease_name: 'Cholera (Vibrio Cholerae)',
        icd_code: 'A00.9',
        icd10_code: 'A00.9',
        category: 'Infectious & Parasitic',
        description: 'Severe acute watery diarrhea ("rice-water stools") causing rapid dehydration and hypovolemic shock.',
        default_severity: 'Critical',
        treatment_plan: CLINICAL_PROTOCOLS['A00.9']
      },
      {
        id: 9010,
        name: 'Leptospirosis, Unspecified',
        disease_name: 'Leptospirosis, Unspecified',
        icd_code: 'A27.9',
        icd10_code: 'A27.9',
        category: 'Zoonotic Infection',
        description: 'Spirochetal infection following exposure to contaminated flood water; calf tenderness, conjunctival suffusion.',
        default_severity: 'Severe',
        treatment_plan: CLINICAL_PROTOCOLS['A27.9']
      },
      {
        id: 9011,
        name: 'Tuberculosis of Lung (Pulmonary TB)',
        disease_name: 'Tuberculosis of Lung (Pulmonary TB)',
        icd_code: 'A15.0',
        icd10_code: 'A15.0',
        category: 'Mycobacterial Infection',
        description: 'Chronic Mycobacterium tuberculosis lung infection with productive cough >2 weeks, night sweats, and weight loss.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['A15.0']
      },
      {
        id: 9012,
        name: 'Malaria (Plasmodium Falciparum / Vivax)',
        disease_name: 'Malaria (Plasmodium Falciparum / Vivax)',
        icd_code: 'B50.9',
        icd10_code: 'B50.9',
        category: 'Vector-Borne Infection',
        description: 'Protozoan mosquito-borne illness presenting with paroxysms of shaking chills, high fever, and sweating.',
        default_severity: 'Severe',
        treatment_plan: CLINICAL_PROTOCOLS['B50.9']
      },
      {
        id: 9013,
        name: 'COVID-19 (SARS-CoV-2 Acute Respiratory Disease)',
        disease_name: 'COVID-19 (SARS-CoV-2 Acute Respiratory Disease)',
        icd_code: 'U07.1',
        icd10_code: 'U07.1',
        category: 'Viral Infection',
        description: 'Acute respiratory illness caused by SARS-CoV-2 presenting with fever, cough, loss of taste/smell, and dyspnea.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['U07.1']
      },
      {
        id: 9014,
        name: 'Varicella (Chickenpox) without Complication',
        disease_name: 'Varicella (Chickenpox) without Complication',
        icd_code: 'B01.9',
        icd10_code: 'B01.9',
        category: 'Viral Exanthem',
        description: 'Varicella-zoster virus presenting with polymorphic pruritic maculopapular and vesicular skin eruptions.',
        default_severity: 'Mild',
        treatment_plan: {
          goal: 'Symptom relief, prevention of secondary bacterial superinfection from excoriation, and isolation until all lesions crust.',
          lifestyle: 'Keep fingernails short and clean; cool sponge baths; avoid scratching lesions; isolate from non-immune individuals.',
          medication_plan: 'Calamine lotion topically TID; Cetirizine 10mg PO OD for itching; Paracetamol 500mg PO Q6H PRN.',
          follow_up_schedule: '7 Days',
          follow_up_days: 7
        }
      },
      {
        id: 9015,
        name: 'Acute Upper Respiratory Tract Infection (URTI)',
        disease_name: 'Acute Upper Respiratory Tract Infection (URTI)',
        icd_code: 'J06.9',
        icd10_code: 'J06.9',
        category: 'Respiratory Infection',
        description: 'Acute viral inflammation of upper respiratory tract presenting with rhinorrhea, nasal congestion, cough, and low-grade fever.',
        default_severity: 'Mild',
        treatment_plan: CLINICAL_PROTOCOLS['J06.9']
      },
      {
        id: 9016,
        name: 'Acute Bronchitis, Unspecified',
        disease_name: 'Acute Bronchitis, Unspecified',
        icd_code: 'J20.9',
        icd10_code: 'J20.9',
        category: 'Lower Respiratory',
        description: 'Acute inflammation of tracheobronchial tree with productive cough, mild chest discomfort, and absence of consolidation on auscultation.',
        default_severity: 'Mild',
        treatment_plan: CLINICAL_PROTOCOLS['J20.9']
      },
      {
        id: 9017,
        name: 'Community-Acquired Pneumonia (CAP), Unspecified',
        disease_name: 'Community-Acquired Pneumonia (CAP), Unspecified',
        icd_code: 'J18.9',
        icd10_code: 'J18.9',
        category: 'Lower Respiratory',
        description: 'Acute parenchymal lung infection acquired outside hospital setting; productive cough, fever, crackles/rales on auscultation.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['J18.9']
      },
      {
        id: 9018,
        name: 'Bronchial Asthma with (Acute) Exacerbation',
        disease_name: 'Bronchial Asthma with (Acute) Exacerbation',
        icd_code: 'J45.901',
        icd10_code: 'J45.901',
        category: 'Chronic Respiratory',
        description: 'Acute flare of reversible airway obstruction characterized by wheezing, chest tightness, dyspnea, and prolonged expiratory phase.',
        default_severity: 'Severe',
        treatment_plan: CLINICAL_PROTOCOLS['J45.901']
      },
      {
        id: 9019,
        name: 'Chronic Obstructive Pulmonary Disease (COPD)',
        disease_name: 'Chronic Obstructive Pulmonary Disease (COPD)',
        icd_code: 'J44.9',
        icd10_code: 'J44.9',
        category: 'Chronic Respiratory',
        description: 'Persistent respiratory symptoms and airflow limitation caused by significant exposure to noxious particles or gases.',
        default_severity: 'Moderate',
        treatment_plan: {
          goal: 'Improve exercise tolerance, reduce exacerbation frequency, and preserve lung function.',
          lifestyle: 'Smoking cessation program, pulmonary rehabilitation, annual influenza and pneumococcal vaccination.',
          medication_plan: 'Tiotropium Respimat 2.5mcg 2 puffs OD; Salbutamol MDI 2 puffs Q4-6H PRN for acute shortness of breath.',
          follow_up_schedule: '1 Month',
          follow_up_days: 30
        }
      },
      {
        id: 9020,
        name: 'Essential (Primary) Hypertension',
        disease_name: 'Essential (Primary) Hypertension',
        icd_code: 'I10',
        icd10_code: 'I10',
        category: 'Cardiovascular',
        description: 'Persistently elevated systemic arterial blood pressure (>=140/90 mmHg) without identified secondary cause.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['I10']
      },
      {
        id: 9021,
        name: 'Hypertensive Heart Disease without Heart Failure',
        disease_name: 'Hypertensive Heart Disease without Heart Failure',
        icd_code: 'I11.9',
        icd10_code: 'I11.9',
        category: 'Cardiovascular',
        description: 'Left ventricular hypertrophy and cardiac structural remodeling resulting from longstanding systemic arterial hypertension.',
        default_severity: 'Moderate',
        treatment_plan: {
          goal: 'Regression of left ventricular hypertrophy, strict BP control <130/80 mmHg, and prevention of diastolic dysfunction.',
          lifestyle: 'Strict low-sodium diet (<1.5g sodium/day), daily home blood pressure diary, moderate exercise, weight management.',
          medication_plan: 'Losartan 50mg PO OD + Amlodipine 5mg PO OD; Carvedilol 6.25mg to 12.5mg PO BID if indicated.',
          follow_up_schedule: '2 Weeks',
          follow_up_days: 14
        }
      },
      {
        id: 9022,
        name: 'Heart Failure, Unspecified (Congestive Heart Failure)',
        disease_name: 'Heart Failure, Unspecified (Congestive Heart Failure)',
        icd_code: 'I50.9',
        icd10_code: 'I50.9',
        category: 'Cardiovascular',
        description: 'Clinical syndrome characterized by bipedal edema, paroxysmal nocturnal dyspnea, orthopnea, and elevated JVP.',
        default_severity: 'Severe',
        treatment_plan: {
          goal: 'Decongestion, symptom improvement, neurohormonal blockade, and reduction of hospitalization.',
          lifestyle: 'Fluid restriction to 1.5-2.0 liters/day, strict low salt intake (<2g/day), daily morning weight check.',
          medication_plan: 'Furosemide 20mg to 40mg PO OD (morning); Sacubitril/Valsartan or Enalapril; Bisoprolol 2.5mg PO OD.',
          follow_up_schedule: '1 Week',
          follow_up_days: 7
        }
      },
      {
        id: 9023,
        name: 'Atherosclerotic Heart Disease (Coronary Artery Disease)',
        disease_name: 'Atherosclerotic Heart Disease (Coronary Artery Disease)',
        icd_code: 'I25.10',
        icd10_code: 'I25.10',
        category: 'Cardiovascular',
        description: 'Lipid plaque buildup in coronary arteries reducing myocardial perfusion; presents with exertional angina or dyspnea.',
        default_severity: 'Severe',
        treatment_plan: {
          goal: 'Secondary prevention of acute coronary events, relief of angina, and stabilization of coronary plaques.',
          lifestyle: 'Heart-healthy Mediterranean diet, smoking cessation, stress reduction, cardiac rehabilitation exercise.',
          medication_plan: 'Aspirin 80mg PO OD + Atorvastatin 40mg PO OD at bedtime; Metoprolol tartrate 50mg PO BID.',
          follow_up_schedule: '2 Weeks',
          follow_up_days: 14
        }
      },
      {
        id: 9024,
        name: 'Type 2 Diabetes Mellitus without Complications',
        disease_name: 'Type 2 Diabetes Mellitus without Complications',
        icd_code: 'E11.9',
        icd10_code: 'E11.9',
        category: 'Endocrine & Metabolic',
        description: 'Insulin resistance and relative insulin deficiency resulting in chronic hyperglycemia (FBS >=126 mg/dL or HbA1c >=6.5%).',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['E11.9']
      },
      {
        id: 9025,
        name: 'Pure Hypercholesterolemia / Dyslipidemia',
        disease_name: 'Pure Hypercholesterolemia / Dyslipidemia',
        icd_code: 'E78.0',
        icd10_code: 'E78.0',
        category: 'Endocrine & Metabolic',
        description: 'Elevated low-density lipoprotein cholesterol (LDL-C) or total cholesterol increasing cardiovascular atherosclerotic risk.',
        default_severity: 'Mild',
        treatment_plan: {
          goal: 'Lower LDL-C by >=50% or target <100 mg/dL (<70 mg/dL if high risk) and stabilize vascular plaques.',
          lifestyle: 'Low saturated fat diet, replace trans fats with unsaturated oils, increase soluble fiber, regular aerobic exercise.',
          medication_plan: 'Atorvastatin 20mg to 40mg PO OD at bedtime or Rosuvastatin 10mg PO OD.',
          follow_up_schedule: '2 Months',
          follow_up_days: 60
        }
      },
      {
        id: 9026,
        name: 'Gouty Arthritis / Hyperuricemia, Unspecified',
        disease_name: 'Gouty Arthritis / Hyperuricemia, Unspecified',
        icd_code: 'M10.9',
        icd10_code: 'M10.9',
        category: 'Musculoskeletal / Metabolic',
        description: 'Monosodium urate crystal deposition in peripheral joints causing acute severe inflammatory monoarthritis.',
        default_severity: 'Moderate',
        treatment_plan: {
          goal: 'Rapid termination of acute joint inflammation/pain, followed by long-term serum uric acid lowering to <6.0 mg/dL.',
          lifestyle: 'Avoid purine-rich foods (organ meats, anchovies, beer); high fluid intake (>2.5L/day); cold pack to joint.',
          medication_plan: 'Colchicine 0.5mg PO BID during acute flare + Naproxen 500mg PO BID; Allopurinol 100mg PO OD started 2 weeks later.',
          follow_up_schedule: '2 Weeks',
          follow_up_days: 14
        }
      },
      {
        id: 9027,
        name: 'Gastritis, Unspecified',
        disease_name: 'Gastritis, Unspecified',
        icd_code: 'K29.7',
        icd10_code: 'K29.7',
        category: 'Gastrointestinal',
        description: 'Acute or chronic mucosal inflammation of gastric lining presenting with epigastric burning, nausea, and early satiety.',
        default_severity: 'Mild',
        treatment_plan: CLINICAL_PROTOCOLS['K29.7']
      },
      {
        id: 9028,
        name: 'Gastro-Esophageal Reflux Disease (GERD)',
        disease_name: 'Gastro-Esophageal Reflux Disease (GERD)',
        icd_code: 'K21.9',
        icd10_code: 'K21.9',
        category: 'Gastrointestinal',
        description: 'Retrograde flow of gastric contents into esophagus producing retrosternal heartburn, acid regurgitation, or chronic cough.',
        default_severity: 'Mild',
        treatment_plan: CLINICAL_PROTOCOLS['K21.9']
      },
      {
        id: 9029,
        name: 'Acute Appendicitis, Other / Unspecified',
        disease_name: 'Acute Appendicitis, Other / Unspecified',
        icd_code: 'K35.80',
        icd10_code: 'K35.80',
        category: 'Surgical / Abdominal',
        description: 'Acute inflammation of vermiform appendix; periumbilical pain migrating to right lower quadrant (McBurney\'s sign), fever, anorexia.',
        default_severity: 'Severe',
        treatment_plan: {
          goal: 'Immediate surgical referral, prevention of appendiceal perforation, peritonitis, and intra-abdominal sepsis.',
          lifestyle: 'Strict NPO (nothing by mouth); avoid hot compress or laxatives which increase perforation risk; urgent surgical admission.',
          medication_plan: 'IV fluids (Plain Ringer\'s Lactate); Ceftriaxone 1g IV + Metronidazole 500mg IV.',
          follow_up_schedule: 'Immediate Surgical Evaluation',
          follow_up_days: 1
        }
      },
      {
        id: 9030,
        name: 'Urinary Tract Infection (UTI), Site Not Specified',
        disease_name: 'Urinary Tract Infection (UTI), Site Not Specified',
        icd_code: 'N39.0',
        icd10_code: 'N39.0',
        category: 'Genitourinary',
        description: 'Bacterial infection of urinary tract producing dysuria, urinary frequency, urgency, and suprapubic discomfort.',
        default_severity: 'Moderate',
        treatment_plan: CLINICAL_PROTOCOLS['N39.0']
      },
      {
        id: 9031,
        name: 'Low Back Pain / Lumbago (Mechanical Lumbar Strain)',
        disease_name: 'Low Back Pain / Lumbago (Mechanical Lumbar Strain)',
        icd_code: 'M54.5',
        icd10_code: 'M54.5',
        category: 'Musculoskeletal',
        description: 'Acute or subacute paraspinal muscle spasm or ligamentous sprain of lumbar spine without radiculopathy.',
        default_severity: 'Mild',
        treatment_plan: CLINICAL_PROTOCOLS['M54.5']
      },
      {
        id: 9032,
        name: 'Osteoarthritis, Unspecified Site (Joint Wear)',
        disease_name: 'Osteoarthritis, Unspecified Site (Joint Wear)',
        icd_code: 'M19.90',
        icd10_code: 'M19.90',
        category: 'Musculoskeletal',
        description: 'Degenerative articular cartilage breakdown presenting with joint pain worsened by weight-bearing, crepitus, and stiffness.',
        default_severity: 'Mild',
        treatment_plan: {
          goal: 'Alleviate joint pain, maintain range of motion, strengthen periarticular muscles, and minimize functional limitation.',
          lifestyle: 'Low-impact aerobic exercise (swimming, cycling, walking), weight loss if overweight, knee support.',
          medication_plan: 'Paracetamol 500mg PO Q6-8H first-line; Topical Diclofenac gel 1% TID-QID; Celecoxib 200mg PO OD for persistent flares.',
          follow_up_schedule: '1 Month',
          follow_up_days: 30
        }
      },
      {
        id: 9033,
        name: 'Open Wound / Laceration, Unspecified',
        disease_name: 'Open Wound / Laceration, Unspecified',
        icd_code: 'T14.1',
        icd10_code: 'T14.1',
        category: 'Trauma & Injury',
        description: 'Mechanical breach of cutaneous integrity resulting from sharp or blunt trauma, requiring wound debridement or closure.',
        default_severity: 'Moderate',
        treatment_plan: {
          goal: 'Hemostasis, surgical wound toilet/closure, prevention of tetanus and bacterial infection, primary intention healing.',
          lifestyle: 'Keep wound clean and dry for 48 hours; do not soak in water; watch for erythema, warmth, purulent drainage.',
          medication_plan: 'Tetanus Toxoid 0.5 mL IM; Cephalexin 500mg PO QID x 5 days; Mefenamic Acid 500mg PO TID PRN for pain.',
          follow_up_schedule: 'Suture Removal (7-10 Days)',
          follow_up_days: 7
        }
      },
      {
        id: 9034,
        name: 'Tension-Type Headache, Unspecified',
        disease_name: 'Tension-Type Headache, Unspecified',
        icd_code: 'G44.2',
        icd10_code: 'G44.2',
        category: 'Neurological',
        description: 'Bilateral dull band-like pressing head pain without nausea or photophobia, exacerbated by stress, fatigue, or muscle tension.',
        default_severity: 'Mild',
        treatment_plan: CLINICAL_PROTOCOLS['G44.2']
      },
      {
        id: 9035,
        name: 'Allergic Urticaria (Hives)',
        disease_name: 'Allergic Urticaria (Hives)',
        icd_code: 'L50.0',
        icd10_code: 'L50.0',
        category: 'Dermatology & Allergy',
        description: 'Mast cell histamine release causing transient intensely pruritic erythematous edematous wheals.',
        default_severity: 'Mild',
        treatment_plan: {
          goal: 'Suppression of cutaneous histamine release, immediate relief of pruritus, and identification of allergen.',
          lifestyle: 'Avoid identified food/drug triggers, cool compresses on itchy skin, wear loose cotton clothing.',
          medication_plan: 'Cetirizine 10mg or Bilastine 20mg PO OD; Hydrocortisone 1% cream topically.',
          follow_up_schedule: '1 Week',
          follow_up_days: 7
        }
      },
      {
        id: 9036,
        name: 'Cellulitis, Unspecified',
        disease_name: 'Cellulitis, Unspecified',
        icd_code: 'L03.90',
        icd10_code: 'L03.90',
        category: 'Dermatology / Infectious',
        description: 'Acute non-necrotizing bacterial dermis and subcutaneous infection; spreading erythema, warmth, edema, and tenderness.',
        default_severity: 'Moderate',
        treatment_plan: {
          goal: 'Arrest bacterial dissemination, resolve soft tissue erythema and swelling, and prevent abscess or necrotizing fasciitis.',
          lifestyle: 'Elevate affected limb above heart level; mark erythematous borders with surgical pen to track progression.',
          medication_plan: 'Cloxacillin 500mg PO Q6H on empty stomach x 10 days OR Cephalexin 500mg PO QID x 10 days; Paracetamol 500mg PO Q6H PRN.',
          follow_up_schedule: '3 Days for Border Regression',
          follow_up_days: 3
        }
      },
      {
        id: 9037,
        name: 'Iron Deficiency Anemia, Unspecified',
        disease_name: 'Iron Deficiency Anemia, Unspecified',
        icd_code: 'D50.9',
        icd10_code: 'D50.9',
        category: 'Hematology',
        description: 'Microcytic hypochromic anemia from depleted iron stores; pallor, fatigue, exercise intolerance, brittle nails, or pica.',
        default_severity: 'Mild',
        treatment_plan: {
          goal: 'Replenish iron stores (ferritin), normalize hemoglobin/hematocrit, and identify cause of occult blood loss.',
          lifestyle: 'High iron diet (dark leafy greens, lean red meat, legumes); take iron with vitamin C; avoid tea/coffee near dose.',
          medication_plan: 'Ferrous Sulfate 325mg PO OD to BID on empty stomach x 3-6 months; Ascorbic Acid 500mg PO with iron.',
          follow_up_schedule: '1 Month (CBC Check)',
          follow_up_days: 30
        }
      }
    ];

    // Assigned queue patients cached for instant focus lookup
    const ASSIGNED_QUEUE_PATIENTS = <?= json_encode(array_values(array_map(function($p) {
        return [
            'id' => (int)$p['PatientID'],
            'name' => trim(($p['FirstName'] ?? '') . ' ' . ($p['LastName'] ?? '')),
            'code' => $p['PatientCode'] ?? '',
            'student_id' => $p['StudentID'] ?? '',
            'gender' => $p['Gender'] ?? '',
            'age' => $p['Age'] ?? '',
            'queue_num' => $p['QueueNumber'] ?? ''
        ];
    }, $patientList))) ?>;

    // Dynamic Search for Patients & Diseases
    document.addEventListener('DOMContentLoaded', function () {
      // 1. Patient Search Handler
      const docSearchInput = document.getElementById('doctor-patient-search');
      const docResultsBox = document.getElementById('doctor-search-results');
      let docSearchTimer = null;

      function renderPatientList(list, isQueueFallback = false) {
        if (!docResultsBox) return;
        docResultsBox.innerHTML = '';

        if (!list || list.length === 0) {
          docResultsBox.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center font-medium">No matching patients found.</div>';
          docResultsBox.classList.remove('hidden');
          return;
        }

        if (isQueueFallback) {
          const header = document.createElement('div');
          header.className = 'px-3 py-1.5 bg-slate-50 text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between border-b border-slate-100';
          header.innerHTML = `<span>Your Active Assigned Patients (${list.length})</span><span class="text-[9px] text-blue-600 font-semibold">Click to open chart</span>`;
          docResultsBox.appendChild(header);
        }

        list.forEach(p => {
          const item = document.createElement('div');
          item.className = 'p-2.5 hover:bg-blue-50 cursor-pointer transition flex items-center justify-between text-xs border-b border-slate-50 last:border-b-0';
          const pName = p.name || `${p.first_name || ''} ${p.last_name || ''}`.trim();
          const pCode = p.code || p.patient_code || '';
          const pAge = p.age ? `${p.age}y` : '';
          const pGender = p.gender || '';

          item.innerHTML = `
            <div class="min-w-0 pr-2">
              <p class="font-bold text-slate-900 truncate leading-tight">${pName}</p>
              <p class="text-[10px] text-slate-500 font-mono mt-0.5">${pCode} ${p.student_id ? '• ID: ' + p.student_id : ''} ${p.queue_num ? '• Queue #' + p.queue_num : ''}</p>
            </div>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 font-semibold text-slate-600 shrink-0">${pGender} ${pAge}</span>
          `;

          item.addEventListener('click', () => {
            window.location.href = '?patient_id=' + (p.id || p.patient_id);
          });
          docResultsBox.appendChild(item);
        });

        docResultsBox.classList.remove('hidden');
      }

      if (docSearchInput && docResultsBox) {
        docSearchInput.addEventListener('focus', function () {
          const q = this.value.trim();
          if (q.length < 1 && ASSIGNED_QUEUE_PATIENTS.length > 0) {
            renderPatientList(ASSIGNED_QUEUE_PATIENTS, true);
          }
        });

        docSearchInput.addEventListener('input', function () {
          clearTimeout(docSearchTimer);
          const q = this.value.trim();
          if (q.length < 1) {
            if (ASSIGNED_QUEUE_PATIENTS.length > 0) {
              renderPatientList(ASSIGNED_QUEUE_PATIENTS, true);
            } else {
              docResultsBox.classList.add('hidden');
              docResultsBox.innerHTML = '';
            }
            return;
          }

          // Instant local search in assigned queue first
          const qLower = q.toLowerCase();
          const localPatients = ASSIGNED_QUEUE_PATIENTS.filter(p => 
            p.name.toLowerCase().includes(qLower) || 
            (p.code && p.code.toLowerCase().includes(qLower)) ||
            (p.student_id && p.student_id.toLowerCase().includes(qLower))
          );
          if (localPatients.length > 0) {
            renderPatientList(localPatients);
          }

          docSearchTimer = setTimeout(() => {
            const searchUrls = ['/api/search/patients?q=' + encodeURIComponent(q), '/search/patients?q=' + encodeURIComponent(q)];
            
            const tryFetch = (index) => {
              if (index >= searchUrls.length) return;
              fetch(searchUrls[index])
                .then(res => {
                  if (!res.ok) throw new Error('HTTP ' + res.status);
                  return res.json();
                })
                .then(data => {
                  if (data && data.data && data.data.length > 0) {
                    renderPatientList(data.data);
                  } else if (localPatients.length === 0) {
                    renderPatientList([]);
                  }
                })
                .catch(() => {
                  tryFetch(index + 1);
                });
            };

            tryFetch(0);
          }, 200);
        });

        document.addEventListener('click', function (e) {
          if (!docSearchInput.contains(e.target) && !docResultsBox.contains(e.target)) {
            docResultsBox.classList.add('hidden');
          }
        });
      }

      // 2. Comprehensive Disease & ICD-10 AJAX Search Autocomplete
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
          diagResultsBox.innerHTML = `
            <div class="p-3 text-xs text-slate-500 text-center font-medium">
              No matching clinical conditions found. You may still type any custom diagnosis name or code.
            </div>
          `;
          diagResultsBox.classList.remove('hidden');
          return;
        }

        // Header for results
        const countHeader = document.createElement('div');
        countHeader.className = 'px-2.5 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between border-b border-slate-100';
        countHeader.innerHTML = `
          <span>Hospital Diagnoses (${items.length})</span>
          <span class="text-[9px] text-blue-600 font-semibold">Select to Auto-Fill Protocol</span>
        `;
        diagResultsBox.appendChild(countHeader);

        items.forEach(item => {
          const row = document.createElement('div');
          row.className = 'p-2.5 hover:bg-blue-50/90 rounded-xl cursor-pointer transition flex items-start justify-between gap-3 text-xs border-b border-slate-50 last:border-b-0 group';

          const leftCol = document.createElement('div');
          leftCol.className = 'space-y-0.5 min-w-0';

          const titleRow = document.createElement('div');
          titleRow.className = 'flex items-center gap-1.5 flex-wrap';

          const nameEl = document.createElement('span');
          nameEl.className = 'font-bold text-slate-900 group-hover:text-blue-700 leading-tight';
          nameEl.textContent = item.name || item.disease_name;
          titleRow.appendChild(nameEl);

          if (item.category) {
            const catEl = document.createElement('span');
            catEl.className = 'text-[9px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 font-semibold';
            catEl.textContent = item.category;
            titleRow.appendChild(catEl);
          }

          leftCol.appendChild(titleRow);

          if (item.description) {
            const descEl = document.createElement('p');
            descEl.className = 'text-[10px] text-slate-500 line-clamp-1 leading-normal';
            descEl.textContent = item.description;
            leftCol.appendChild(descEl);
          }

          const rightCol = document.createElement('div');
          rightCol.className = 'shrink-0 text-right';
          const code = item.icd_code || item.icd10_code || 'ICD-10';
          rightCol.innerHTML = `
            <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded-full bg-blue-100 text-blue-800 group-hover:bg-blue-600 group-hover:text-white transition">${code}</span>
          `;

          row.appendChild(leftCol);
          row.appendChild(rightCol);

          row.addEventListener('click', () => {
            const diseaseName = item.name || item.disease_name;
            const icdCode = item.icd_code || item.icd10_code || '';
            const sev = item.default_severity || 'Moderate';

            if (diagInput) diagInput.value = diseaseName;
            if (icdInput) icdInput.value = icdCode;
            if (diagSevSelect) diagSevSelect.value = sev;
            if (diagBadge && diagBadgeText) {
              diagBadgeText.textContent = `${diseaseName} [${icdCode}]`;
              diagBadge.classList.remove('hidden');
            }
            diagResultsBox.classList.add('hidden');

            // Auto-populate related treatment plan
            const proto = item.treatment_plan || CLINICAL_PROTOCOLS[icdCode] || {
              goal: `Symptom stabilization, control of ${diseaseName}, and clinical recovery.`,
              lifestyle: 'Adequate rest, balanced nutrition, stay hydrated, avoid smoking and alcohol.',
              medication_plan: 'Prescribed pharmacological therapy and supportive clinical care as indicated.',
              follow_up_schedule: '2 Weeks',
              follow_up_days: 14
            };
            applyDiagnosisTreatmentPlan(proto, diseaseName, icdCode);

            if (window.lucide) lucide.createIcons();
          });

          diagResultsBox.appendChild(row);
        });

        diagResultsBox.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
      }

      function searchDiseases(query) {
        clearTimeout(diagTimer);
        const q = (query || '').trim().toLowerCase();

        if (q.length < 1) {
          // If empty, show top 10 common conditions
          renderDiseaseResults(MASTER_DISEASES_CATALOG.slice(0, 10));
          return;
        }

        // 1. Instant 0ms Local Master Catalog Filtering
        const localMatches = MASTER_DISEASES_CATALOG.filter(d => {
          const n = (d.name || d.disease_name || '').toLowerCase();
          const c = (d.icd_code || d.icd10_code || '').toLowerCase();
          const cat = (d.category || '').toLowerCase();
          const desc = (d.description || '').toLowerCase();
          return n.includes(q) || c.includes(q) || cat.includes(q) || desc.includes(q);
        });

        // Immediately render local matches without delay!
        renderDiseaseResults(localMatches);

        // 2. Concurrently fetch and merge with server DB catalog
        if (diagSpinner) diagSpinner.classList.remove('hidden');
        diagTimer = setTimeout(async () => {
          if (diagAbortCtrl) diagAbortCtrl.abort();
          diagAbortCtrl = new AbortController();

          const apiUrls = [
            '/api/search/diseases?q=' + encodeURIComponent(q),
            '/search/diseases?q=' + encodeURIComponent(q)
          ];

          let serverResults = [];
          for (const url of apiUrls) {
            try {
              const res = await fetch(url, {
                signal: diagAbortCtrl.signal,
                headers: { 'Accept': 'application/json' }
              });
              if (res.ok) {
                const payload = await res.json();
                if (payload && payload.data && Array.isArray(payload.data)) {
                  serverResults = payload.data;
                  break;
                }
              }
            } catch (err) {
              if (err.name === 'AbortError') return;
            }
          }

          if (diagSpinner) diagSpinner.classList.add('hidden');

          if (serverResults.length > 0) {
            // Merge server results with local results avoiding duplicates
            const seenCodes = new Set();
            const merged = [];

            // Add server items first
            serverResults.forEach(item => {
              const code = (item.icd_code || item.icd10_code || '').toUpperCase();
              if (code && !seenCodes.has(code)) {
                seenCodes.add(code);
                merged.push(item);
              }
            });

            // Add any remaining local items
            localMatches.forEach(item => {
              const code = (item.icd_code || item.icd10_code || '').toUpperCase();
              if (!seenCodes.has(code)) {
                seenCodes.add(code);
                merged.push(item);
              }
            });

            renderDiseaseResults(merged);
          }
        }, 150);
      }

      if (diagInput) {
        diagInput.addEventListener('input', function () {
          searchDiseases(this.value);
        });
        diagInput.addEventListener('focus', function () {
          searchDiseases(this.value);
        });
      }

      if (icdInput) {
        icdInput.addEventListener('input', function () {
          searchDiseases(this.value);
          const val = this.value.trim().toUpperCase();
          if (CLINICAL_PROTOCOLS[val]) {
            const name = diagInput ? diagInput.value : val;
            applyDiagnosisTreatmentPlan(CLINICAL_PROTOCOLS[val], name, val);
          }
        });
        icdInput.addEventListener('focus', function () {
          if (this.value.trim().length >= 1) {
            searchDiseases(this.value);
          }
        });
      }

      document.addEventListener('click', function (e) {
        if (diagResultsBox && diagInput && icdInput) {
          if (!diagInput.contains(e.target) && !icdInput.contains(e.target) && !diagResultsBox.contains(e.target)) {
            diagResultsBox.classList.add('hidden');
          }
        }
      });

      // Medication Plan Search Helper
      const planMedInput = document.getElementById('plan-medication-input');
      const planMedResults = document.getElementById('plan-medication-results');
      let planMedTimer = null;

      window.appendMedicationPlan = function(text) {
        if (!planMedInput) return;
        const current = planMedInput.value.trim();
        if (!current) {
          planMedInput.value = text;
        } else if (!current.includes(text)) {
          planMedInput.value = current + '; ' + text;
        }
        planMedInput.focus();
        if (planMedResults) planMedResults.classList.add('hidden');
      };

      if (planMedInput && planMedResults) {
        planMedInput.addEventListener('input', function() {
          clearTimeout(planMedTimer);
          const q = this.value.trim();
          if (q.length < 2) {
            planMedResults.classList.add('hidden');
            return;
          }
          planMedTimer = setTimeout(async () => {
            try {
              const res = await fetch('/api/search/medications?q=' + encodeURIComponent(q));
              const data = await res.json();
              if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                let html = '';
                data.data.slice(0, 8).forEach(item => {
                  const title = item.full_name || (item.generic_name + ' ' + (item.strength || ''));
                  const dosageSuggestion = title + ' PO TID';
                  const safeTitle = title.replace(/'/g, "\\'");
                  const safeSuggest = dosageSuggestion.replace(/'/g, "\\'");
                  html += `
                    <div class="p-2 hover:bg-emerald-50 cursor-pointer rounded-lg transition flex items-center justify-between text-xs"
                         onclick="appendMedicationPlan('${safeSuggest}')">
                      <div>
                        <div class="font-bold text-slate-800">${escapeHtml(title)}</div>
                        <div class="text-[10px] text-slate-400">${escapeHtml(item.dosage_form || '')} • Stock: ${item.current_stock ?? 0}</div>
                      </div>
                      <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">+ Add</span>
                    </div>
                  `;
                });
                planMedResults.innerHTML = html;
                planMedResults.classList.remove('hidden');
              } else {
                planMedResults.classList.add('hidden');
              }
            } catch (e) {
              planMedResults.classList.add('hidden');
            }
          }, 180);
        });

        document.addEventListener('click', function(e) {
          if (!planMedInput.contains(e.target) && !planMedResults.contains(e.target)) {
            planMedResults.classList.add('hidden');
          }
        });
      }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>