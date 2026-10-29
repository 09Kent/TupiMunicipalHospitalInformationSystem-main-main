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
        Session::setFlash('success', 'Treatment plan created successfully.');
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

        $refCode = 'REF-' . date('Ymd') . '-' . rand(1000, 9999);

        $stmtRef = $db->prepare("
                    INSERT INTO referrals (
                        ReferralCode, ReferralType, PatientID, ReferringDoctorID, ReferringDepartment,
                        TargetSpecialtyID, TargetDoctorID, ReceivingHospital, HospitalAddress,
                        RequestedService, Reason, ClinicalSummary, Priority, Urgency, Status, TrackingStatus,
                        CreatedAt, UpdatedAt
                    ) VALUES (
                        :code, :rtype, :pid, :doc_id, :dept,
                        :tspec, :tdoc, :hosp, :haddr,
                        :serv, :reason, :summary, :priority, :urgency, 'Pending', :track,
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
          ':reason' => $refReason,
          ':summary' => $clinicalSummary,
          ':priority' => $priority,
          ':urgency' => $urgency,
          ':track' => ($refType === 'External' || !empty($receivingHospital)) ? 'Sent' : 'Created'
        ]);
        $referralId = (int) $db->lastInsertId();

        try {
          $auditStmt = $db->prepare("
                        INSERT INTO system_audit_logs (UserID, UserName, UserRole, Action, Module, RecordID, Details, IPAddress, CreatedAt)
                        VALUES (:uid, :uname, 'Doctor', 'REFERRAL_CREATED', 'Clinical Care', :rec, :det, '127.0.0.1', NOW())
                    ");
          $auditStmt->execute([
            ':uid' => $currentUser['user_id'] ?? $doctorId,
            ':uname' => $currentUser['full_name'] ?? 'Attending Physician',
            ':rec' => $referralId,
            ':det' => "Physician initiated {$refType} referral ({$refCode}) for Patient #{$patientId}."
          ]);
        } catch (\Throwable $ae) {
        }
      }

      $db->commit();

      if ($disposition === 'ADMIT') {
        Session::setFlash('success', "Admission Created Successfully! Patient assigned to {$admissionData['ward']}, {$admissionData['room_number']} ({$admissionData['bed_number']}) and routed to Inpatient Nurse Station.");
      } elseif ($disposition === 'REFER' && $referralId) {
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

      <!-- Quick Switch & Search Patient -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="relative w-full sm:w-64">
          <div class="relative">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
            <input type="text" id="doctor-patient-search" placeholder="Search patient name, ID, student ID..."
              autocomplete="off"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition" />
          </div>
          <div id="doctor-search-results"
            class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 max-h-72 overflow-y-auto divide-y divide-slate-100">
          </div>
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
            <a href="<?= doctor_url('views/patients/view.php?id=' . $patientId) ?>"
              class="p-2 text-slate-400 hover:text-blue-600 bg-slate-50 hover:bg-blue-50 rounded-xl border border-slate-200 transition shrink-0"
              title="Patient Full Chart">
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
        <p class="text-xs text-slate-400 mt-1">Select a patient from your assigned queue above to start recording notes.
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
        <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-9 gap-2.5">
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

            $cardClass = "w-full min-h-[76px] flex flex-col items-center justify-center p-2.5 rounded-2xl text-center border transition select-none " . (
              $st['done']
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800 hover:bg-emerald-100 hover:border-emerald-300'
              : (!empty($st['active'])
                ? 'bg-blue-50 border-2 border-blue-500 text-blue-900 shadow-sm ring-2 ring-blue-500/20'
                : 'bg-slate-50 border-slate-200/80 text-slate-500 hover:bg-slate-100/80')
            );
            ?>
            <?php if ($stageTarget): ?>
              <a href="<?= $stageTarget ?>" class="<?= $cardClass ?> cursor-pointer group">
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1.5 shrink-0 transition-transform group-hover:scale-110 <?= $st['done'] ? 'bg-emerald-600 text-white' : (!empty($st['active']) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') ?>">
                  <?= $st['done'] ? '✓' : ($idx + 1) ?>
                </div>
                <span
                  class="text-[10px] font-black uppercase tracking-tight leading-tight line-clamp-2"><?= e($st['label']) ?></span>
              </a>
            <?php else: ?>
              <div class="<?= $cardClass ?>">
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1.5 shrink-0 <?= $st['done'] ? 'bg-emerald-600 text-white' : (!empty($st['active']) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') ?>">
                  <?= $st['done'] ? '✓' : ($idx + 1) ?>
                </div>
                <span
                  class="text-[10px] font-black uppercase tracking-tight leading-tight line-clamp-2"><?= e($st['label']) ?></span>
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

                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Medication Regimen
                      Overview</label>
                    <span class="text-[9px] text-slate-400">Prescribed drugs & administration</span>
                  </div>
                  <input type="text" id="plan-medication-input" name="medication_plan"
                    placeholder="e.g. Daily oral antihypertensives with morning meals..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
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

                <button type="submit"
                  class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i>
                  <span>Establish Treatment Plan</span>
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
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Transfer Transport Mode
                      *</label>
                    <select name="requested_service"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800">
                      <option value="TMHIS Emergency Ambulance (O2 + EMT Accompanying)" selected>🚑 TMHIS Ambulance (O2 +
                        EMT)</option>
                      <option value="LGU Disaster Risk Reduction / Rescue Vehicle">🚑 LGU MDRRMO Rescue Vehicle</option>
                      <option value="Private Vehicle (Family Dispatched)">🚗 Private Family Vehicle</option>
                    </select>
                  </div>
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

  // Quick Select for Formal Diagnosis Form
  function selectQuickDiagnosis(name, icd, severity) {
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
      'Z20.3': {
        goal: 'Prevent rabies virus invasion following animal exposure and prevent secondary bacterial wound infection.',
        lifestyle: 'Wound cleaning with soap and running water for 15 minutes; Observe biting animal for 14 days if domestic.',
        medication_plan: 'Anti-Rabies Vaccine (PCECV) on Day 0, 3, 7, 28; Tetanus Toxoid 0.5ml IM; Co-amoxiclav 625mg PO BID x 5 days.',
        follow_up_schedule: 'Day 3 Rabies Dose',
        follow_up_days: 3
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

    // Dynamic Search for Patients & Diseases
    document.addEventListener('DOMContentLoaded', function () {
      // 1. Patient Search
      const docSearchInput = document.getElementById('doctor-patient-search');
      const docResultsBox = document.getElementById('doctor-search-results');
      let docSearchTimer = null;

      if (docSearchInput && docResultsBox) {
        docSearchInput.addEventListener('input', function () {
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

        document.addEventListener('click', function (e) {
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

            // Auto-populate related treatment plan
            const proto = item.treatment_plan || CLINICAL_PROTOCOLS[item.icd_code] || {
              goal: `Symptom stabilization, control of ${item.name}, and clinical recovery.`,
              lifestyle: 'Adequate rest, balanced nutrition, stay hydrated, avoid smoking and alcohol.',
              medication_plan: 'Prescribed pharmacological therapy and supportive clinical care as indicated.',
              follow_up_schedule: '2 Weeks',
              follow_up_days: 14
            };
            applyDiagnosisTreatmentPlan(proto, item.name, item.icd_code);

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
        diagInput.addEventListener('input', function () {
          searchDiseases(this.value);
        });
        diagInput.addEventListener('focus', function () {
          if (this.value.trim().length >= 1) {
            searchDiseases(this.value);
          }
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
      }

      document.addEventListener('click', function (e) {
        if (diagResultsBox && diagInput && icdInput) {
          if (!diagInput.contains(e.target) && !icdInput.contains(e.target) && !diagResultsBox.contains(e.target)) {
            diagResultsBox.classList.add('hidden');
          }
        }
      });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>