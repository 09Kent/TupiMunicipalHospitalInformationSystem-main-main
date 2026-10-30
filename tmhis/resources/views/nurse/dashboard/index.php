<?php
// Nurse/views/dashboard/index.php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/demo_data.php';
require_once __DIR__ . '/../includes/patient_monitor.php';

$pageTitle = 'Dashboard | Nurse On Duty • Tupi Municipal Hospital';
$activeMenu = 'dashboard';

$currentUser = Session::getCurrentUser();
$patients = getDemoPatients();
$instructions = getDemoInstructions();
$notifications = getDemoNotifications();

// Real Database Patients & Assignments (Requirement 10: MY PATIENTS)
$realPatients = [];
try {
    $rows = \Illuminate\Support\Facades\DB::table('patients as p')
        ->leftJoin('appointments as a', 'p.PatientID', '=', 'a.PatientID')
        ->leftJoin('doctors as d', 'a.DoctorID', '=', 'd.DoctorID')
        ->leftJoin('nurse_assignments as na', function($join) {
            $join->on('p.PatientID', '=', 'na.patient_id')
                 ->where('na.status', '=', 'Active');
        })
        ->leftJoin('nurse_tasks as nt', function($join) {
            $join->on('p.PatientID', '=', 'nt.PatientID')
                 ->whereIn('nt.Status', ['Pending', 'Due', 'Scheduled']);
        })
        ->select(
            'p.PatientID', 'p.PatientCode', 'p.FirstName', 'p.LastName', 'p.Age', 'p.Gender', 'p.BloodType', 'p.Status as PatStatus', 'p.PatientCategory',
            'd.DoctorID', 'd.FirstName as DocFirst', 'd.LastName as DocLast', 'd.Specialty',
            'na.shift', 'na.task_summary as NurseTaskSummary',
            'nt.TaskID as NextTaskID', 'nt.TaskTitle as NextTaskTitle', 'nt.MedicationName as NextMedName', 'nt.DueTime as NextTaskDue', 'nt.Status as NextTaskStatus'
        )
        ->groupBy('p.PatientID')
        ->orderBy('p.PatientID', 'desc')
        ->limit(20)
        ->get();

    foreach ($rows as $r) {
        $realPatients[] = [
            'patient_id' => $r->PatientID,
            'code'       => $r->PatientCode ?: ('TMH-' . $r->PatientID),
            'name'       => trim($r->FirstName . ' ' . $r->LastName),
            'age'        => $r->Age,
            'gender'     => $r->Gender,
            'blood'      => $r->BloodType,
            'room'       => ($r->PatientCategory === 'Inpatient') ? ('Ward ' . (100 + ($r->PatientID % 20))) : 'OPD Triage',
            'doctor'     => !empty($r->DocLast) ? "Dr. {$r->DocFirst} {$r->DocLast}" : "Dr. Daniel Lewis, MD",
            'status'     => in_array($r->PatStatus, ['Stable', 'Critical', 'Needs Attention', 'Under Observation', 'Admitted']) ? $r->PatStatus : 'Stable',
            'next_task'  => $r->NextMedName ? ("Administer " . $r->NextMedName . " (" . ($r->NextTaskDue ?: 'Due') . ")") : ($r->NextTaskTitle ?: ($r->NurseTaskSummary ?: 'Vital Signs & Inpatient Care')),
            'task_status'=> $r->NextTaskStatus ?: 'Scheduled'
        ];
    }
} catch (\Throwable $e) {}

// Real Database Tasks & Schedule Categories (Requirement 11: SCHEDULE-BASED TASKS)
$upcomingSchedule = [];
$dueSchedule = [];
$completedSchedule = [];
$missedSchedule = [];

try {
    $tasksQuery = \Illuminate\Support\Facades\DB::table('nurse_tasks as nt')
        ->join('patients as p', 'nt.PatientID', '=', 'p.PatientID')
        ->leftJoin('doctors as d', 'nt.DoctorID', '=', 'd.DoctorID')
        ->select(
            'nt.*',
            'p.PatientCode', 'p.FirstName as PatFirst', 'p.LastName as PatLast', 'p.PatientCategory',
            'd.FirstName as DocFirst', 'd.LastName as DocLast'
        )
        ->orderBy('nt.TaskID', 'desc')
        ->limit(60)
        ->get();

    $now = time();
    foreach ($tasksQuery as $st) {
        $schedTs = !empty($st->ScheduledTime) ? strtotime($st->ScheduledTime) : strtotime($st->CreatedAt);
        $item = [
            'task_id'         => $st->TaskID,
            'patient_id'      => $st->PatientID,
            'patient_name'    => trim($st->PatFirst . ' ' . $st->PatLast),
            'patient_code'    => $st->PatientCode ?: ('TMH-' . $st->PatientID),
            'doctor_name'     => !empty($st->DocLast) ? "Dr. {$st->DocFirst} {$st->DocLast}" : "Dr. Daniel Lewis, MD",
            'task_title'      => $st->TaskTitle,
            'category'        => $st->Category ?: 'Nursing Care',
            'medication'      => $st->MedicationName ?: $st->TaskTitle,
            'dosage'          => $st->Dosage ?: 'Standard Dose',
            'route'           => $st->Route ?: 'Oral',
            'frequency'       => $st->Frequency ?: 'Daily',
            'schedule_time'   => $st->ScheduledTime ? date('g:i A', strtotime($st->ScheduledTime)) : ($st->DueTime ?: '10:00 AM'),
            'priority'        => $st->Priority ?: 'Normal',
            'status'          => $st->Status,
            'administered_at' => $st->AdministeredAt ? date('g:i A', strtotime($st->AdministeredAt)) : null,
            'administered_by' => $st->AdministeredByName,
            'remarks'         => $st->Remarks
        ];

        if (in_array($st->Status, ['Administered', 'Completed'])) {
            $completedSchedule[] = $item;
        } elseif (in_array($st->Status, ['Missed', 'Held', 'Cancelled'])) {
            $missedSchedule[] = $item;
        } elseif ($st->Status === 'Due') {
            $dueSchedule[] = $item;
        } else {
            // Check if overdue by more than 30 mins
            if ($schedTs < ($now - 1800)) {
                $item['status'] = 'Due';
                $dueSchedule[] = $item;
            } else {
                $upcomingSchedule[] = $item;
            }
        }
    }
} catch (\Throwable $e) {}

// Selected patient (default first)
$selectedPatientIndex = 0;
$selectedPatient = !empty($patients) ? ($patients[$selectedPatientIndex] ?? ($patients[0] ?? null)) : [
    'id'                  => 'PAT-0000',
    'patient_id'          => 0,
    'name'                => 'No active patients',
    'age'                 => 0,
    'gender'              => 'N/A',
    'contact'             => 'N/A',
    'room'                => 'OPD Triage',
    'attending_physician' => 'Attending Physician',
    'status'              => 'Stable',
    'bp'                  => '120/80',
    'heart_rate'          => 75,
    'temperature'         => 36.5,
    'respiratory_rate'    => 16,
    'spo2'                => 98,
    'pain_level'          => 0,
    'queue_status'        => 'None',
    'queue_number'        => 0,
    'assigned_task'       => 'None',
    'task_id'             => null,
    'task_status'         => 'None',
    'task_priority'       => 'Normal',
    'task_due'            => 'N/A',
    'last_update'         => 'Now',
    'medical_history'     => 'None',
    'allergies'           => 'None',
    'admission_date'      => date('Y-m-d'),
];

// Compute KPI metrics
$totalPatients = !empty($realPatients) ? count($realPatients) : count($patients);
$criticalCount = count(array_filter($patients, fn($p) => $p['status'] === 'Critical' || $p['status'] === 'Needs Attention'));
$pendingTasks = count($dueSchedule) + count($upcomingSchedule);
$queueWaiting = count(array_filter($patients, fn($p) => in_array($p['queue_status'], ['Waiting', 'Called'])));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen">
  
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="p-6 sm:p-8 space-y-8 flex-1">

    <!-- Page Title & Search -->
    <section data-aos="fade-down" class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-teal-100 text-teal-800 border border-teal-200">Role 6</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight font-display">Nurse On Duty</h1>
        <p class="text-sm text-slate-500 font-medium mt-1">Patient Vital Signs & Status Monitoring</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
          <input type="text" id="patientSearchInput" placeholder="Search patient..." 
                 class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 shadow-xs w-64"
                 onkeyup="filterPatients(this.value)">
        </div>
        <button onclick="document.getElementById('filterDropdown').classList.toggle('hidden')" 
                class="p-2.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition shadow-xs relative">
          <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
        </button>
        <!-- Filter Dropdown -->
        <div id="filterDropdown" class="hidden absolute right-8 top-52 bg-white border border-slate-200 rounded-2xl shadow-xl p-4 z-50 w-64">
          <p class="text-xs font-bold text-slate-900 mb-3">Filter Patients</p>
          <div class="space-y-2">
            <button onclick="filterByStatus('all')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold hover:bg-slate-50 transition">All Patients</button>
            <button onclick="filterByStatus('Stable')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold hover:bg-emerald-50 text-emerald-700 transition">● Stable</button>
            <button onclick="filterByStatus('Under Observation')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold hover:bg-blue-50 text-blue-700 transition">● Under Observation</button>
            <button onclick="filterByStatus('Needs Attention')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold hover:bg-amber-50 text-amber-700 transition">● Needs Attention</button>
            <button onclick="filterByStatus('Critical')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold hover:bg-red-50 text-red-700 transition">● Critical</button>
            <button onclick="filterByStatus('For Discharge')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold hover:bg-purple-50 text-purple-700 transition">● For Discharge</button>
          </div>
        </div>
      </div>
    </section>

    <!-- 4 KPI Metrics Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5" data-aos="fade-up">
      
      <!-- Card 1: Total Patients -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card hover:shadow-card-hover transition-all duration-200 group">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Assigned Patients</span>
          <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:scale-110 transition-transform">
            <i data-lucide="users" class="w-6 h-6"></i>
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 tracking-tight font-display"><?= $totalPatients ?></span>
          <span class="text-xs font-semibold text-emerald-600 flex items-center gap-0.5">
            <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
            <span>Active Ward</span>
          </span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1 font-medium">Patients under your care today</p>
      </div>

      <!-- Card 2: Critical/Attention -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card hover:shadow-card-hover transition-all duration-200 group">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Needs Attention</span>
          <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-110 transition-transform">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 tracking-tight font-display"><?= $criticalCount ?></span>
          <span class="text-xs font-semibold text-amber-600 flex items-center gap-0.5">
            <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
            <span>Priority</span>
          </span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1 font-medium">Critical & needs-attention patients</p>
      </div>

      <!-- Card 3: Pending Tasks -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card hover:shadow-card-hover transition-all duration-200 group">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Tasks</span>
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:scale-110 transition-transform">
            <i data-lucide="clipboard-list" class="w-6 h-6"></i>
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 tracking-tight font-display"><?= $pendingTasks ?></span>
          <span class="text-xs font-semibold text-rose-600 flex items-center gap-0.5">
            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
            <span>Due Today</span>
          </span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1 font-medium">Tasks awaiting completion</p>
      </div>

      <!-- Card 4: Queue Waiting -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card hover:shadow-card-hover transition-all duration-200 group">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Queue Waiting</span>
          <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
            <i data-lucide="list-ordered" class="w-6 h-6"></i>
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 tracking-tight font-display"><?= $queueWaiting ?></span>
          <span class="text-xs font-semibold text-blue-600 flex items-center gap-0.5">
            <i data-lucide="users" class="w-3.5 h-3.5"></i>
            <span>In Queue</span>
          </span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1 font-medium">Patients waiting for assistance</p>
      </div>

    </section>

    <!-- Main Content: Monitor + Vital Signs -->
    <section class="grid grid-cols-1 xl:grid-cols-12 gap-6">

      <!-- LEFT: Patient Monitor Visualization -->
      <div class="xl:col-span-5" data-aos="fade-right" data-aos-delay="100">
        <?php render_patient_monitor($selectedPatient); ?>
      </div>

      <!-- RIGHT: Vital Signs Panel + Management -->
      <div class="xl:col-span-7 space-y-6" data-aos="fade-left" data-aos-delay="200">
        
        <!-- Patient Vital Signs Card -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-base font-black text-slate-900 tracking-tight font-display">Patient Vital Signs</h3>
              <p class="text-xs text-slate-400 mt-0.5">Latest readings for <span class="font-semibold text-teal-600" id="vitalPatientName"><?= e($selectedPatient['name']) ?></span></p>
            </div>
            <span class="px-3 py-1 rounded-full text-[10px] font-bold <?= get_patient_status_badge($selectedPatient['status']) ?>"><?= e($selectedPatient['status']) ?></span>
          </div>

          <!-- 6 Vital Sign Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3" id="vitalSignsGrid">
            
            <?php
            $vitals = [
                ['label' => 'Heart Rate', 'value' => $selectedPatient['heart_rate'], 'unit' => 'BPM', 'icon' => 'heart', 'type' => 'heart_rate', 'color' => 'rose'],
                ['label' => 'Blood Pressure', 'value' => $selectedPatient['bp'], 'unit' => 'mmHg', 'icon' => 'activity', 'type' => 'bp_systolic', 'color' => 'blue', 'raw' => (int)explode('/', $selectedPatient['bp'])[0]],
                ['label' => 'Temperature', 'value' => $selectedPatient['temperature'], 'unit' => '°C', 'icon' => 'thermometer', 'type' => 'temperature', 'color' => 'orange'],
                ['label' => 'Respiratory Rate', 'value' => $selectedPatient['respiratory_rate'], 'unit' => 'BPM', 'icon' => 'wind', 'type' => 'respiratory_rate', 'color' => 'cyan'],
                ['label' => 'Oxygen Saturation', 'value' => $selectedPatient['spo2'], 'unit' => '%', 'icon' => 'droplets', 'type' => 'spo2', 'color' => 'emerald'],
                ['label' => 'Pain Level', 'value' => $selectedPatient['pain_level'] . '/10', 'unit' => '', 'icon' => 'gauge', 'type' => 'pain_level', 'color' => 'purple', 'raw' => $selectedPatient['pain_level']],
            ];
            
            foreach ($vitals as $vital):
                $rawValue = $vital['raw'] ?? $vital['value'];
                $status = getVitalStatus($vital['type'], is_numeric($rawValue) ? $rawValue : 0);
                $statusDot = $status === 'Normal' ? 'bg-emerald-400' : ($status === 'Warning' ? 'bg-amber-400' : 'bg-red-400');
                $statusText = $status === 'Normal' ? 'text-emerald-600' : ($status === 'Warning' ? 'text-amber-600' : 'text-red-600');
            ?>
            <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-100 hover:bg-slate-50 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 group">
              <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400"><?= $vital['label'] ?></span>
                <div class="w-8 h-8 rounded-xl bg-<?= $vital['color'] ?>-50 text-<?= $vital['color'] ?>-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                  <i data-lucide="<?= $vital['icon'] ?>" class="w-4 h-4"></i>
                </div>
              </div>
              <div class="flex items-baseline gap-1.5">
                <span class="text-xl font-black text-slate-900 counter-value"><?= $vital['value'] ?></span>
                <?php if ($vital['unit']): ?>
                  <span class="text-xs text-slate-400 font-medium"><?= $vital['unit'] ?></span>
                <?php endif; ?>
              </div>
              <div class="flex items-center gap-1.5 mt-1.5">
                <span class="w-1.5 h-1.5 rounded-full <?= $statusDot ?>"></span>
                <span class="text-[10px] font-semibold <?= $statusText ?>"><?= $status ?></span>
              </div>
            </div>
            <?php endforeach; ?>

          </div>
        </div>

        <!-- Vital Signs Management -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card">
          <h3 class="text-base font-black text-slate-900 tracking-tight font-display mb-4">Vital Signs Management</h3>
          <div class="grid grid-cols-2 gap-3">
            <button onclick="openModal('createVitalSignsModal')" class="flex items-center gap-2 px-4 py-3 bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 rounded-xl text-xs font-bold transition shadow-xs">
              <i data-lucide="plus-circle" class="w-4 h-4"></i>
              <span>Create Vital Signs Entry</span>
            </button>
            <button onclick="openModal('viewVitalSignsModal')" class="flex items-center gap-2 px-4 py-3 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-bold transition shadow-xs">
              <i data-lucide="eye" class="w-4 h-4"></i>
              <span>View Vital Signs Entry</span>
            </button>
            <button onclick="openModal('updateVitalSignsModal')" class="flex items-center gap-2 px-4 py-3 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 rounded-xl text-xs font-bold transition shadow-xs">
              <i data-lucide="edit" class="w-4 h-4"></i>
              <span>Update Vital Signs Entry</span>
            </button>
            <button onclick="openModal('vitalHistoryModal')" class="flex items-center gap-2 px-4 py-3 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded-xl text-xs font-bold transition shadow-xs">
              <i data-lucide="history" class="w-4 h-4"></i>
              <span>View Vital Signs History</span>
            </button>
          </div>
        </div>

      </div>

    </section>

    <!-- Second Row: Patient Status + Doctor Instructions -->
    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      
      <!-- Patient Status Monitoring -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card" data-aos="fade-up" data-aos-delay="100">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-base font-black text-slate-900 tracking-tight font-display">Patient Status Monitoring</h3>
            <p class="text-xs text-slate-400 mt-0.5">Status tracking & updates</p>
          </div>
        </div>

        <!-- Current Patient Status -->
        <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-100 mb-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                <i data-lucide="user-check" class="w-5 h-5"></i>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-900" id="statusPatientName"><?= e($selectedPatient['name']) ?></p>
                <p class="text-xs text-slate-500"><?= e($selectedPatient['room']) ?> • <?= e($selectedPatient['attending_physician']) ?></p>
              </div>
            </div>
            <span class="px-3 py-1.5 rounded-full text-[11px] font-bold <?= get_patient_status_badge($selectedPatient['status']) ?>" id="currentStatusBadge">
              ● <?= e($selectedPatient['status']) ?>
            </span>
          </div>
          <div class="flex items-center gap-1 mt-3 text-[10px] text-slate-400">
            <i data-lucide="clock" class="w-3 h-3"></i>
            <span>Last Updated: <?= e($selectedPatient['last_update']) ?></span>
          </div>
        </div>

        <!-- Status Actions -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <button onclick="openModal('patientRecordModal')" class="flex items-center gap-2 px-4 py-3 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold transition shadow-xs">
            <i data-lucide="file-text" class="w-4 h-4 text-slate-500"></i>
            <span>View Patient Record</span>
          </button>
          <button onclick="openModal('updateStatusModal')" class="flex items-center gap-2 px-4 py-3 bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 rounded-xl text-xs font-bold transition shadow-xs">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            <span>Update Patient Status</span>
          </button>
          <button onclick="openModal('doctorInstructionsModal')" class="flex items-center gap-2 px-4 py-3 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-bold transition shadow-xs">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            <span>Doctor Instructions</span>
          </button>
        </div>
      </div>

      <!-- Doctor Instructions -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card" data-aos="fade-up" data-aos-delay="200">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-base font-black text-slate-900 tracking-tight font-display">Doctor Instructions</h3>
            <p class="text-xs text-slate-400 mt-0.5">Active physician orders</p>
          </div>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200"><?= count($instructions) ?> Active</span>
        </div>

        <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
          <?php foreach (array_slice($instructions, 0, 4) as $inst): ?>
          <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-100 hover:bg-slate-50 transition group">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mt-0.5 shrink-0">
                  <i data-lucide="stethoscope" class="w-4 h-4"></i>
                </div>
                <div>
                  <p class="text-xs font-bold text-slate-900"><?= e($inst['doctor']) ?></p>
                  <p class="text-[11px] text-slate-500 mt-0.5"><?= e($inst['patient']) ?> • <?= e($inst['room']) ?></p>
                  <p class="text-xs text-slate-600 mt-2 leading-relaxed">"<?= e($inst['instruction'] ?? $inst['title'] ?? '') ?>"</p>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold shrink-0 <?= get_priority_badge($inst['priority'] ?? 'Normal') ?>"><?= e($inst['priority'] ?? 'Normal') ?></span>
            </div>
            <div class="flex items-center gap-1 mt-2 text-[10px] text-slate-400 ml-11">
              <i data-lucide="calendar" class="w-3 h-3"></i>
              <span><?= format_date($inst['date'] ?? 'now', 'd M Y h:i A') ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </section>

    <!-- Requirement 10: MY PATIENTS SECTION -->
    <section class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-card space-y-6" data-aos="fade-up">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="p-2 rounded-xl bg-teal-50 text-teal-600">
              <i data-lucide="users" class="w-5 h-5"></i>
            </span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight font-display">MY PATIENTS</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800 border border-teal-200"><?= count($realPatients) ?> Active</span>
          </div>
          <p class="text-xs text-slate-400 font-medium mt-1">Patients assigned to your nursing care and inpatient ward duty</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-wider text-slate-400 bg-slate-50/50">
              <th class="p-3.5 pl-4 rounded-l-2xl">Patient</th>
              <th class="p-3.5">Assigned Doctor</th>
              <th class="p-3.5">Room / Ward</th>
              <th class="p-3.5">Clinical Status</th>
              <th class="p-3.5">Next Nursing Task</th>
              <th class="p-3.5 pr-4 rounded-r-2xl text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <?php if (empty($realPatients)): ?>
              <tr>
                <td colspan="6" class="p-8 text-center text-slate-400 italic">No assigned patients found in database.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($realPatients as $rp): ?>
                <tr class="nurse-patient-row hover:bg-slate-50/80 transition group" 
                    data-name="<?= e(strtolower($rp['name'])) ?>" 
                    data-code="<?= e(strtolower($rp['code'])) ?>" 
                    data-doctor="<?= e(strtolower($rp['doctor'])) ?>" 
                    data-room="<?= e(strtolower($rp['room'])) ?>" 
                    data-status="<?= e(strtolower($rp['status'])) ?>">
                  <td class="p-3.5 pl-4">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-teal-600 text-white font-black text-xs flex items-center justify-center font-display shrink-0">
                        <?= strtoupper(substr($rp['name'], 0, 1)) ?>
                      </div>
                      <div>
                        <strong class="text-slate-900 block"><?= e($rp['name']) ?></strong>
                        <span class="text-[10px] text-slate-400 font-mono"><?= e($rp['code']) ?> • <?= e($rp['age']) ?>y, <?= e($rp['gender']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="p-3.5">
                    <span class="font-bold text-slate-700"><?= e($rp['doctor']) ?></span>
                  </td>
                  <td class="p-3.5">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold text-[11px]"><?= e($rp['room']) ?></span>
                  </td>
                  <td class="p-3.5">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= get_patient_status_badge($rp['status']) ?>">
                      ● <?= e($rp['status']) ?>
                    </span>
                  </td>
                  <td class="p-3.5">
                    <div class="flex items-center gap-1.5 text-slate-700 font-medium">
                      <i data-lucide="clipboard" class="w-3.5 h-3.5 text-teal-600 shrink-0"></i>
                      <span class="truncate max-w-xs text-xs"><?= e($rp['next_task']) ?></span>
                    </div>
                  </td>
                  <td class="p-3.5 pr-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <a href="/print/patient/<?= $rp['patient_id'] ?>" target="_blank" 
                         class="p-2 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition border border-slate-200 text-xs font-bold flex items-center gap-1" title="Print Comprehensive Patient Record">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        <span>Print</span>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Requirement 10 & 11: MY TASKS (SCHEDULE-BASED NURSING TASKS & MEDICATION ADMINISTRATION) -->
    <section class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-card space-y-6" data-aos="fade-up">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="p-2 rounded-xl bg-blue-50 text-blue-600">
              <i data-lucide="clock" class="w-5 h-5"></i>
            </span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight font-display">MY TASKS & MEDICATION ADMINISTRATION</h2>
          </div>
          <p class="text-xs text-slate-400 font-medium mt-1">Organized schedule of physician orders, medication administration, and patient care</p>
        </div>

        <!-- Schedule Filter Tabs -->
        <div class="flex items-center gap-1.5 bg-slate-100 p-1.5 rounded-2xl flex-wrap">
          <button onclick="switchTaskScheduleTab('due')" id="tabBtn-due" 
                  class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-white text-rose-700 shadow-xs">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
            <span>Due</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-100 text-rose-800"><?= count($dueSchedule) ?></span>
          </button>
          <button onclick="switchTaskScheduleTab('upcoming')" id="tabBtn-upcoming" 
                  class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900">
            <span>Upcoming</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-blue-100 text-blue-800"><?= count($upcomingSchedule) ?></span>
          </button>
          <button onclick="switchTaskScheduleTab('completed')" id="tabBtn-completed" 
                  class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900">
            <span>Completed</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-100 text-emerald-800"><?= count($completedSchedule) ?></span>
          </button>
          <button onclick="switchTaskScheduleTab('missed')" id="tabBtn-missed" 
                  class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900">
            <span>Missed / Held</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-200 text-slate-700"><?= count($missedSchedule) ?></span>
          </button>
        </div>
      </div>

      <!-- Schedule Panels -->
      <div id="scheduleTabContent">

        <!-- Panel 1: Due Tasks -->
        <div id="panel-due" class="schedule-panel space-y-3">
          <?php if (empty($dueSchedule)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-100">
              <i data-lucide="check-circle" class="w-8 h-8 text-emerald-500 mx-auto mb-2"></i>
              <p class="text-xs font-bold text-slate-700">No overdue tasks</p>
              <p class="text-[11px] text-slate-400">All scheduled medications and nursing duties are up to date.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($dueSchedule as $t): ?>
                <div class="p-4 bg-rose-50/40 rounded-2xl border border-rose-200/80 shadow-xs flex flex-col justify-between space-y-3">
                  <div>
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 uppercase tracking-wider">DUE NOW</span>
                      <span class="text-xs font-mono font-bold text-rose-700"><?= e($t['schedule_time']) ?></span>
                    </div>
                    <h4 class="font-extrabold text-sm text-slate-900 mt-2 leading-snug"><?= e($t['task_title']) ?></h4>
                    <p class="text-xs text-slate-600 mt-1">
                      <strong>Patient:</strong> <?= e($t['patient_name']) ?> <span class="text-slate-400 font-mono">(<?= e($t['patient_code']) ?>)</span>
                    </p>
                    <div class="mt-2 p-2 bg-white/80 rounded-xl border border-rose-100 text-[11px] space-y-0.5 text-slate-700">
                      <p><strong>Medication:</strong> <?= e($t['medication']) ?></p>
                      <p><strong>Dosage / Route:</strong> <?= e($t['dosage']) ?> • <?= e($t['route']) ?></p>
                      <p><strong>Frequency:</strong> <?= e($t['frequency']) ?></p>
                      <p class="text-slate-500 text-[10px]">Ordered by: <?= e($t['doctor_name']) ?></p>
                    </div>
                  </div>
                  <div class="pt-2 border-t border-rose-100 flex items-center justify-between gap-2">
                    <a href="/print/patient/<?= $t['patient_id'] ?>" target="_blank" class="text-[11px] font-bold text-slate-500 hover:text-slate-800">
                      View Chart
                    </a>
                    <button onclick="openMedicationAdminModal(<?= (int)$t['task_id'] ?>, <?= (int)$t['patient_id'] ?>, '<?= e(addslashes($t['patient_name'])) ?>', '<?= e(addslashes($t['medication'])) ?>', '<?= e(addslashes($t['dosage'])) ?>', '<?= e(addslashes($t['route'])) ?>', '<?= e(addslashes($t['schedule_time'])) ?>')"
                            class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center gap-1.5">
                      <i data-lucide="check" class="w-3.5 h-3.5"></i>
                      <span>Administer</span>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Panel 2: Upcoming Tasks -->
        <div id="panel-upcoming" class="schedule-panel hidden space-y-3">
          <?php if (empty($upcomingSchedule)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-100">
              <p class="text-xs text-slate-400 italic">No upcoming tasks scheduled for current shift.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($upcomingSchedule as $t): ?>
                <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-3">
                  <div>
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider"><?= e($t['status']) ?></span>
                      <span class="text-xs font-mono font-bold text-slate-600"><?= e($t['schedule_time']) ?></span>
                    </div>
                    <h4 class="font-extrabold text-sm text-slate-900 mt-2 leading-snug"><?= e($t['task_title']) ?></h4>
                    <p class="text-xs text-slate-600 mt-1">
                      <strong>Patient:</strong> <?= e($t['patient_name']) ?> <span class="text-slate-400 font-mono">(<?= e($t['patient_code']) ?>)</span>
                    </p>
                    <div class="mt-2 p-2 bg-white rounded-xl border border-slate-200/60 text-[11px] space-y-0.5 text-slate-700">
                      <p><strong>Medication:</strong> <?= e($t['medication']) ?></p>
                      <p><strong>Dosage / Route:</strong> <?= e($t['dosage']) ?> • <?= e($t['route']) ?></p>
                      <p class="text-slate-500 text-[10px]">Ordered by: <?= e($t['doctor_name']) ?></p>
                    </div>
                  </div>
                  <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between gap-2">
                    <a href="/print/patient/<?= $t['patient_id'] ?>" target="_blank" class="text-[11px] font-bold text-slate-500 hover:text-slate-800">
                      View Chart
                    </a>
                    <button onclick="openMedicationAdminModal(<?= (int)$t['task_id'] ?>, <?= (int)$t['patient_id'] ?>, '<?= e(addslashes($t['patient_name'])) ?>', '<?= e(addslashes($t['medication'])) ?>', '<?= e(addslashes($t['dosage'])) ?>', '<?= e(addslashes($t['route'])) ?>', '<?= e(addslashes($t['schedule_time'])) ?>')"
                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center gap-1">
                      <span>Administer</span>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Panel 3: Completed Tasks -->
        <div id="panel-completed" class="schedule-panel hidden space-y-3">
          <?php if (empty($completedSchedule)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-100">
              <p class="text-xs text-slate-400 italic">No completed tasks yet for this shift.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($completedSchedule as $t): ?>
                <div class="p-4 bg-emerald-50/40 rounded-2xl border border-emerald-200/80 shadow-xs flex flex-col justify-between space-y-3">
                  <div>
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 uppercase tracking-wider">ADMINISTERED</span>
                      <span class="text-xs font-mono font-bold text-emerald-700"><?= e($t['administered_at'] ?: $t['schedule_time']) ?></span>
                    </div>
                    <h4 class="font-extrabold text-sm text-slate-900 mt-2 leading-snug"><?= e($t['task_title']) ?></h4>
                    <p class="text-xs text-slate-600 mt-1">
                      <strong>Patient:</strong> <?= e($t['patient_name']) ?> <span class="text-slate-400 font-mono">(<?= e($t['patient_code']) ?>)</span>
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                      <?= e($t['remarks'] ?: 'Administered according to standard protocol.') ?>
                    </p>
                  </div>
                  <div class="pt-2 border-t border-emerald-100 text-[10px] text-emerald-800 font-semibold">
                    Administered by: <?= e($t['administered_by'] ?: 'Nurse On Duty') ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Panel 4: Missed / Held Tasks -->
        <div id="panel-missed" class="schedule-panel hidden space-y-3">
          <?php if (empty($missedSchedule)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-100">
              <p class="text-xs text-slate-400 italic">No missed or held tasks recorded.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($missedSchedule as $t): ?>
                <div class="p-4 bg-amber-50/40 rounded-2xl border border-amber-200/80 shadow-xs flex flex-col justify-between space-y-3">
                  <div>
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 uppercase tracking-wider"><?= strtoupper(e($t['status'])) ?></span>
                      <span class="text-xs font-mono font-bold text-amber-700"><?= e($t['schedule_time']) ?></span>
                    </div>
                    <h4 class="font-extrabold text-sm text-slate-900 mt-2 leading-snug"><?= e($t['task_title']) ?></h4>
                    <p class="text-xs text-slate-600 mt-1">
                      <strong>Patient:</strong> <?= e($t['patient_name']) ?> <span class="text-slate-400 font-mono">(<?= e($t['patient_code']) ?>)</span>
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                      Reason / Remarks: <?= e($t['remarks'] ?: 'Medication held or missed as noted.') ?>
                    </p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </section>

    <!-- Fourth Row: Notifications -->
    <section data-aos="fade-up" data-aos-delay="100">
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-card">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-base font-black text-slate-900 tracking-tight font-display">Department Notifications</h3>
            <p class="text-xs text-slate-400 mt-0.5">Recent updates and alerts</p>
          </div>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800 border border-teal-200"><?= count(array_filter($notifications, fn($n) => !$n['read'])) ?> Unread</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
          <?php foreach (array_slice($notifications, 0, 4) as $notif): 
            $notifColor = $notif['color'] ?? ($notif['type'] === 'critical' ? 'rose' : ($notif['type'] === 'warning' ? 'amber' : 'teal'));
            $notifIcon = $notif['icon'] ?? ($notif['type'] === 'critical' ? 'alert-triangle' : ($notif['type'] === 'warning' ? 'alert-circle' : 'bell'));
            $isRead = !empty($notif['read']) || (isset($notif['unread']) && !$notif['unread']);
          ?>
          <div class="p-4 bg-<?= $notifColor ?>-50/60 rounded-2xl border border-<?= $notifColor ?>-100 hover:bg-<?= $notifColor ?>-50 transition cursor-pointer group
               <?= !$isRead ? 'ring-2 ring-' . $notifColor . '-200' : '' ?>">
            <div class="flex items-start gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-<?= $notifColor ?>-100 text-<?= $notifColor ?>-600 flex items-center justify-center mt-0.5 shrink-0">
                <i data-lucide="<?= $notifIcon ?>" class="w-4 h-4"></i>
              </div>
              <div>
                <p class="text-xs font-bold text-<?= $notifColor ?>-900"><?= e($notif['title'] ?? 'Notification') ?></p>
                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed"><?= e($notif['message'] ?? '') ?></p>
                <p class="text-[10px] text-slate-400 mt-1.5 flex items-center gap-1">
                  <i data-lucide="clock" class="w-3 h-3"></i>
                  <?= e($notif['time'] ?? 'Recent') ?>
                </p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

  </main>

</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->

<!-- Create Vital Signs Modal -->
<div id="createVitalSignsModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('createVitalSignsModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Create Vital Signs Entry</h3>
        <p class="text-xs text-slate-400 mt-0.5">Record new vital signs for a patient</p>
      </div>
      <button onclick="closeModal('createVitalSignsModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form id="createVitalsForm" class="p-6 space-y-4" onsubmit="saveVitalSigns(event)">
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Patient</label>
        <select id="vsPatient" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500">
          <?php foreach ($patients as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= $p['id'] === $selectedPatient['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> — <?= e($p['room']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Date & Time</label>
        <input type="datetime-local" id="vsDateTime" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" 
               value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Blood Pressure</label>
          <input type="text" id="vsBP" placeholder="120/80" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="120/80">
        </div>
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Heart Rate (BPM)</label>
          <input type="number" id="vsHR" placeholder="78" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="78">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Temperature (°C)</label>
          <input type="number" id="vsTemp" step="0.1" placeholder="36.7" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="36.7">
        </div>
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Respiratory Rate (BPM)</label>
          <input type="number" id="vsRR" placeholder="18" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="18">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Oxygen Saturation (%)</label>
          <input type="number" id="vsSpO2" placeholder="98" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="98">
        </div>
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Pain Level (0-10)</label>
          <input type="number" id="vsPain" min="0" max="10" placeholder="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="2">
        </div>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Notes</label>
        <textarea id="vsNotes" rows="3" placeholder="Additional observations..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"></textarea>
      </div>
      <div class="flex items-center gap-3 pt-2">
        <button type="button" onclick="closeModal('createVitalSignsModal')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-3 bg-teal-600 text-white hover:bg-teal-700 rounded-xl text-sm font-bold transition shadow-lg shadow-teal-600/25">Save Vital Signs</button>
      </div>
    </form>
  </div>
</div>

<!-- View Vital Signs Modal -->
<div id="viewVitalSignsModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('viewVitalSignsModal')"></div>
  <div class="modal-content absolute inset-4 sm:inset-8 lg:inset-16 bg-white rounded-3xl shadow-2xl opacity-0 scale-95 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10 rounded-t-3xl">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">View Vital Signs Entry</h3>
        <p class="text-xs text-slate-400 mt-0.5">Latest vital signs for selected patient</p>
      </div>
      <button onclick="closeModal('viewVitalSignsModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6" id="viewVitalSignsContent">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<!-- Update Vital Signs Modal -->
<div id="updateVitalSignsModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('updateVitalSignsModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Update Vital Signs</h3>
        <p class="text-xs text-slate-400 mt-0.5">Update existing vital signs entry</p>
      </div>
      <button onclick="closeModal('updateVitalSignsModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6" id="updateVitalSignsContent">
      <!-- Same form as create, pre-populated -->
    </div>
  </div>
</div>

<!-- Vital Signs History Modal -->
<div id="vitalHistoryModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('vitalHistoryModal')"></div>
  <div class="modal-content absolute inset-4 sm:inset-6 lg:inset-10 bg-white rounded-3xl shadow-2xl opacity-0 scale-95 overflow-hidden flex flex-col">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10 rounded-t-3xl">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Patient Vital Signs History</h3>
        <p class="text-xs text-slate-400 mt-0.5">Complete vital signs timeline</p>
      </div>
      <button onclick="closeModal('vitalHistoryModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="flex-1 overflow-auto p-6">
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-slate-200">
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">Date</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">Time</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">BP</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">HR</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">Temp</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">RR</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">SpO₂</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">Pain</th>
              <th class="text-left py-3 px-3 font-bold text-slate-500 uppercase tracking-wider text-[10px]">Recorded By</th>
            </tr>
          </thead>
          <tbody id="vitalHistoryTableBody">
            <?php foreach (getDemoVitalHistory() as $vh): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
              <td class="py-3 px-3 font-semibold text-slate-900"><?= e($vh['date']) ?></td>
              <td class="py-3 px-3 text-slate-600"><?= e($vh['time']) ?></td>
              <td class="py-3 px-3 font-semibold text-slate-900"><?= e($vh['bp']) ?></td>
              <td class="py-3 px-3"><?= $vh['hr'] ?> BPM</td>
              <td class="py-3 px-3"><?= $vh['temp'] ?>°C</td>
              <td class="py-3 px-3"><?= $vh['rr'] ?> BPM</td>
              <td class="py-3 px-3"><?= $vh['spo2'] ?>%</td>
              <td class="py-3 px-3"><?= $vh['pain'] ?>/10</td>
              <td class="py-3 px-3 text-slate-500"><?= e($vh['recorded_by']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Patient Record Modal -->
<div id="patientRecordModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('patientRecordModal')"></div>
  <div class="modal-content absolute inset-4 sm:inset-6 lg:inset-12 bg-white rounded-3xl shadow-2xl opacity-0 scale-95 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10 rounded-t-3xl">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Patient Record</h3>
        <p class="text-xs text-slate-400 mt-0.5">Complete patient profile (View Only)</p>
      </div>
      <button onclick="closeModal('patientRecordModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6" id="patientRecordContent">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<!-- Update Patient Status Modal -->
<div id="updateStatusModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('updateStatusModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Update Patient Status</h3>
        <p class="text-xs text-slate-400 mt-0.5">Change current patient status</p>
      </div>
      <button onclick="closeModal('updateStatusModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form class="p-6 space-y-4" onsubmit="updatePatientStatus(event)">
      <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
        <p class="text-xs font-bold text-slate-900" id="updateStatusPatientName"><?= e($selectedPatient['name']) ?></p>
        <p class="text-[11px] text-slate-500"><?= e($selectedPatient['room']) ?></p>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">New Status</label>
        <select id="newPatientStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500">
          <option value="Stable">Stable</option>
          <option value="Under Observation">Under Observation</option>
          <option value="Needs Attention">Needs Attention</option>
          <option value="Critical">Critical</option>
          <option value="For Discharge">For Discharge</option>
        </select>
      </div>
      <div class="flex items-center gap-3 pt-2">
        <button type="button" onclick="closeModal('updateStatusModal')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-3 bg-teal-600 text-white hover:bg-teal-700 rounded-xl text-sm font-bold transition shadow-lg shadow-teal-600/25">Update Status</button>
      </div>
    </form>
  </div>
</div>

<!-- Doctor Instructions Modal (Read Only) -->
<div id="doctorInstructionsModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('doctorInstructionsModal')"></div>
  <div class="modal-content absolute inset-4 sm:inset-8 lg:inset-16 bg-white rounded-3xl shadow-2xl opacity-0 scale-95 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10 rounded-t-3xl">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Doctor Instructions</h3>
        <p class="text-xs text-slate-400 mt-0.5">All active physician orders (Read Only)</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
          <i data-lucide="lock" class="w-3 h-3 inline-block mr-0.5"></i>
          View Only
        </span>
        <button onclick="closeModal('doctorInstructionsModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
    </div>
    <div class="p-6 space-y-3">
      <?php foreach ($instructions as $inst): ?>
      <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-100">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <i data-lucide="stethoscope" class="w-5 h-5"></i>
            </div>
            <div>
              <p class="text-sm font-bold text-slate-900"><?= e($inst['doctor']) ?></p>
              <p class="text-xs text-slate-500"><?= e($inst['patient']) ?> • <?= e($inst['room']) ?></p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold <?= get_priority_badge($inst['priority'] ?? 'Normal') ?>"><?= e($inst['priority'] ?? 'Normal') ?></span>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800"><?= e($inst['status'] ?? 'Active') ?></span>
          </div>
        </div>
        <div class="bg-white rounded-xl p-3 border border-slate-100">
          <p class="text-sm text-slate-700 leading-relaxed">"<?= e($inst['instruction'] ?? $inst['title'] ?? '') ?>"</p>
        </div>
        <div class="flex items-center gap-1 mt-3 text-[10px] text-slate-400">
          <i data-lucide="calendar" class="w-3 h-3"></i>
          <span>Instruction Date: <?= format_date($inst['date'] ?? 'now', 'd M Y h:i A') ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Queue Status Update Modal -->
<div id="queueUpdateModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('queueUpdateModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Update Queue Status</h3>
        <p class="text-xs text-slate-400 mt-0.5">Change patient queue position</p>
      </div>
      <button onclick="closeModal('queueUpdateModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form class="p-6 space-y-4" onsubmit="updateQueueStatus(event)">
      <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
        <p class="text-xs font-bold text-slate-900" id="queuePatientName"></p>
        <p class="text-[11px] text-slate-500" id="queuePatientRoom"></p>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-2">Select Status</label>
        <div class="grid grid-cols-1 gap-2" id="queueStatusOptions">
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-amber-50 hover:border-amber-200 transition has-[:checked]:bg-amber-50 has-[:checked]:border-amber-400">
            <input type="radio" name="queueStatus" value="Waiting" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Waiting</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition has-[:checked]:bg-blue-50 has-[:checked]:border-blue-400">
            <input type="radio" name="queueStatus" value="Called" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Called</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-400">
            <input type="radio" name="queueStatus" value="In Progress" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">In Progress</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-emerald-50 hover:border-emerald-200 transition has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-400">
            <input type="radio" name="queueStatus" value="Completed" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Completed</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-rose-50 hover:border-rose-200 transition has-[:checked]:bg-rose-50 has-[:checked]:border-rose-400">
            <input type="radio" name="queueStatus" value="Cancelled" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Cancelled</span>
          </label>
        </div>
      </div>
      <input type="hidden" id="queuePatientIndex" value="">
      <div class="flex items-center gap-3 pt-2">
        <button type="button" onclick="closeModal('queueUpdateModal')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-3 bg-teal-600 text-white hover:bg-teal-700 rounded-xl text-sm font-bold transition shadow-lg shadow-teal-600/25">Update Queue</button>
      </div>
    </form>
  </div>
</div>

<!-- Task Update Modal -->
<div id="taskUpdateModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('taskUpdateModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Update Task Status</h3>
        <p class="text-xs text-slate-400 mt-0.5">Update assigned task status</p>
      </div>
      <button onclick="closeModal('taskUpdateModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form class="p-6 space-y-4" onsubmit="updateTaskStatus(event)">
      <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
        <p class="text-xs font-bold text-slate-900" id="taskName"></p>
        <p class="text-[11px] text-slate-500" id="taskPatientInfo"></p>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-2">Select Status</label>
        <div class="grid grid-cols-1 gap-2">
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-amber-50 transition has-[:checked]:bg-amber-50 has-[:checked]:border-amber-400">
            <input type="radio" name="taskStatus" value="Pending" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Pending</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-blue-50 transition has-[:checked]:bg-blue-50 has-[:checked]:border-blue-400">
            <input type="radio" name="taskStatus" value="In Progress" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">In Progress</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-emerald-50 transition has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-400">
            <input type="radio" name="taskStatus" value="Completed" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Completed</span>
          </label>
          <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer hover:bg-rose-50 transition has-[:checked]:bg-rose-50 has-[:checked]:border-rose-400">
            <input type="radio" name="taskStatus" value="Cancelled" class="accent-teal-600">
            <span class="text-sm font-semibold text-slate-700">Cancelled</span>
          </label>
        </div>
      </div>
      <input type="hidden" id="taskPatientIndex" value="">
      <div class="flex items-center gap-3 pt-2">
        <button type="button" onclick="closeModal('taskUpdateModal')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-3 bg-teal-600 text-white hover:bg-teal-700 rounded-xl text-sm font-bold transition shadow-lg shadow-teal-600/25">Update Task</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Patient Status Update Modal -->
<div id="createStatusUpdateModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/40 opacity-0" onclick="closeModal('createStatusUpdateModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <h3 class="text-lg font-black text-slate-900 font-display">Create Patient Status Update</h3>
        <p class="text-xs text-slate-400 mt-0.5">Record a patient status observation</p>
      </div>
      <button onclick="closeModal('createStatusUpdateModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form class="p-6 space-y-4" onsubmit="saveStatusUpdate(event)">
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Patient</label>
        <select id="suPatient" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500">
          <?php foreach ($patients as $p): ?>
            <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> — <?= e($p['room']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Current Status</label>
        <select id="suStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500">
          <option>Stable</option>
          <option>Under Observation</option>
          <option>Needs Attention</option>
          <option>Critical</option>
          <option>For Discharge</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Vital Sign Concern</label>
        <input type="text" id="suVitalConcern" placeholder="e.g., Blood pressure increased slightly" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500">
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Observation</label>
        <textarea id="suObservation" rows="2" placeholder="Describe your observation..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"></textarea>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Action Taken</label>
        <textarea id="suAction" rows="2" placeholder="What action was taken..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"></textarea>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Additional Notes</label>
        <textarea id="suNotes" rows="2" placeholder="Any additional notes..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"></textarea>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Date & Time</label>
        <input type="datetime-local" id="suDateTime" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500" value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <div class="flex items-center gap-3 pt-2">
        <button type="button" onclick="closeModal('createStatusUpdateModal')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-3 bg-teal-600 text-white hover:bg-teal-700 rounded-xl text-sm font-bold transition shadow-lg shadow-teal-600/25">Save Status Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-6 right-6 z-[70] hidden">
  <div class="bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-sm font-semibold" id="toastContent">
    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400"></i>
    <span id="toastMessage">Saved successfully!</span>
  </div>
</div>


<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
// Patient data in JS
const patients = <?= json_encode($patients, JSON_UNESCAPED_UNICODE) ?>;
let selectedPatientIndex = 0;

// ============================
// Modal Functions
// ============================
function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('hidden');
  
  requestAnimationFrame(() => {
    const backdrop = modal.querySelector('.modal-backdrop');
    const content = modal.querySelector('.modal-content');
    if (backdrop) { backdrop.style.opacity = '1'; backdrop.style.backdropFilter = 'blur(8px)'; }
    if (content) { content.style.opacity = '1'; content.style.transform = 'scale(1) translateX(0) translateY(0)'; }
  });

  // Populate content based on modal
  if (id === 'viewVitalSignsModal') populateViewVitals();
  if (id === 'updateVitalSignsModal') populateUpdateVitals();
  if (id === 'patientRecordModal') populatePatientRecord();
  
  lucide.createIcons();
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  const backdrop = modal.querySelector('.modal-backdrop');
  const content = modal.querySelector('.modal-content');
  if (backdrop) { backdrop.style.opacity = '0'; backdrop.style.backdropFilter = 'none'; }
  if (content) { content.style.opacity = '0'; content.style.transform = 'scale(0.95) translateX(16px)'; }
  setTimeout(() => modal.classList.add('hidden'), 300);
}

// ============================
// Patient Selection
// ============================
function selectPatient(index) {
  selectedPatientIndex = index;
  const p = patients[index];
  
  // Update monitor panel header
  const nameEl = document.getElementById('monitorPatientName');
  if (nameEl) nameEl.textContent = p.name;
  
  // Update vital patient name
  const vitalNameEl = document.getElementById('vitalPatientName');
  if (vitalNameEl) vitalNameEl.textContent = p.name;
  
  // Update status patient name
  const statusNameEl = document.getElementById('statusPatientName');
  if (statusNameEl) statusNameEl.textContent = p.name;
  
  // Update monitor vitals
  const hrEl = document.getElementById('monitorHR');
  const bpEl = document.getElementById('monitorBP');
  const tempEl = document.getElementById('monitorTemp');
  const spo2El = document.getElementById('monitorSpO2');
  if (hrEl) animateCounter(hrEl, parseInt(hrEl.textContent), p.heart_rate);
  if (bpEl) bpEl.textContent = p.bp;
  if (tempEl) animateCounter(tempEl, parseFloat(tempEl.textContent), p.temperature, true);
  if (spo2El) animateCounter(spo2El, parseInt(spo2El.textContent), p.spo2);
  
  // Update status badge
  const statusBadge = document.getElementById('currentStatusBadge');
  if (statusBadge) {
    statusBadge.textContent = '● ' + p.status;
    statusBadge.className = 'px-3 py-1.5 rounded-full text-[11px] font-bold ' + getStatusBadgeClass(p.status);
  }
  
  showToast('Patient selected: ' + p.name);
}

// ============================
// Counter Animation
// ============================
function animateCounter(el, from, to, isFloat = false) {
  const duration = 600;
  const start = performance.now();
  
  function update(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const value = from + (to - from) * eased;
    el.textContent = isFloat ? value.toFixed(1) : Math.round(value);
    if (progress < 1) requestAnimationFrame(update);
  }
  requestAnimationFrame(update);
}

// ============================
// Search & Filter
// ============================
let currentNurseStatusFilter = 'all';

function filterPatients(query) {
  const q = (query || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.nurse-patient-row, .queue-item');
  let matchCount = 0;

  rows.forEach(item => {
    const name = item.getAttribute('data-name') || item.querySelector('strong')?.textContent?.toLowerCase() || '';
    const code = item.getAttribute('data-code') || item.querySelector('.font-mono')?.textContent?.toLowerCase() || '';
    const doctor = item.getAttribute('data-doctor') || '';
    const room = item.getAttribute('data-room') || '';
    const status = item.getAttribute('data-status') || '';
    const fullText = item.textContent?.toLowerCase() || '';

    const textMatch = !q || name.includes(q) || code.includes(q) || doctor.includes(q) || room.includes(q) || fullText.includes(q);
    const statusMatch = (currentNurseStatusFilter === 'all') || (status && status.includes(currentNurseStatusFilter.toLowerCase()));

    const show = textMatch && statusMatch;
    item.style.display = show ? '' : 'none';
    if (show) matchCount++;
  });
}

function filterByStatus(status) {
  currentNurseStatusFilter = status || 'all';
  document.getElementById('filterDropdown')?.classList.add('hidden');
  const searchInput = document.getElementById('patientSearchInput');
  filterPatients(searchInput ? searchInput.value : '');
  showToast(status === 'all' ? 'Showing all patients' : 'Filtered: ' + status, 'info');
}

// ============================
// Save Vital Signs
// ============================
function saveVitalSigns(e) {
  e.preventDefault();
  showToast('Vital signs saved successfully!');
  closeModal('createVitalSignsModal');
}

// ============================
// Update Patient Status
// ============================
function updatePatientStatus(e) {
  e.preventDefault();
  const newStatus = document.getElementById('newPatientStatus').value;
  patients[selectedPatientIndex].status = newStatus;
  
  const statusBadge = document.getElementById('currentStatusBadge');
  if (statusBadge) {
    statusBadge.textContent = '● ' + newStatus;
    statusBadge.className = 'px-3 py-1.5 rounded-full text-[11px] font-bold ' + getStatusBadgeClass(newStatus);
  }
  
  showToast('Patient status updated to: ' + newStatus);
  closeModal('updateStatusModal');
}

// ============================
// Queue Update
// ============================
function openQueueUpdateModal(index) {
  document.getElementById('queuePatientName').textContent = patients[index].name;
  document.getElementById('queuePatientRoom').textContent = patients[index].room;
  document.getElementById('queuePatientIndex').value = index;
  openModal('queueUpdateModal');
}

function updateQueueStatus(e) {
  e.preventDefault();
  const index = document.getElementById('queuePatientIndex').value;
  const status = document.querySelector('input[name="queueStatus"]:checked')?.value;
  if (!status) { showToast('Please select a status'); return; }
  
  patients[index].queue_status = status;
  showToast('Queue status updated: ' + status);
  closeModal('queueUpdateModal');
}

// ============================
// Task Update
// ============================
function openTaskUpdateModal(index) {
  document.getElementById('taskName').textContent = patients[index].assigned_task;
  document.getElementById('taskPatientInfo').textContent = 'Patient: ' + patients[index].name + ' • ' + patients[index].room;
  document.getElementById('taskPatientIndex').value = index;
  openModal('taskUpdateModal');
}

function updateTaskStatus(e) {
  e.preventDefault();
  const index = document.getElementById('taskPatientIndex').value;
  const status = document.querySelector('input[name="taskStatus"]:checked')?.value;
  if (!status) { showToast('Please select a status'); return; }
  
  patients[index].task_status = status;
  showToast('Task status updated: ' + status);
  closeModal('taskUpdateModal');
}

// ============================
// Status Update
// ============================
function saveStatusUpdate(e) {
  e.preventDefault();
  showToast('Patient status update saved successfully!');
  closeModal('createStatusUpdateModal');
}

// ============================
// Populate View Vitals
// ============================
function populateViewVitals() {
  const p = patients[selectedPatientIndex];
  const container = document.getElementById('viewVitalSignsContent');
  container.innerHTML = `
    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
          <i data-lucide="user" class="w-6 h-6"></i>
        </div>
        <div>
          <p class="text-base font-bold text-slate-900">${p.name}</p>
          <p class="text-xs text-slate-500">${p.room} • ${p.attending_physician} • Age: ${p.age}</p>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
      ${vitalCard('Heart Rate', p.heart_rate, 'BPM', 'heart', 'rose')}
      ${vitalCard('Blood Pressure', p.bp, 'mmHg', 'activity', 'blue')}
      ${vitalCard('Temperature', p.temperature, '°C', 'thermometer', 'orange')}
      ${vitalCard('Respiratory Rate', p.respiratory_rate, 'BPM', 'wind', 'cyan')}
      ${vitalCard('SpO₂', p.spo2, '%', 'droplets', 'emerald')}
      ${vitalCard('Pain Level', p.pain_level + '/10', '', 'gauge', 'purple')}
    </div>
    <div class="mt-4 text-[11px] text-slate-400 flex items-center gap-1">
      <i data-lucide="clock" class="w-3 h-3"></i>
      Last updated: ${p.last_update}
    </div>
  `;
  lucide.createIcons();
}

function vitalCard(label, value, unit, icon, color) {
  return `
    <div class="bg-${color}-50/50 rounded-2xl p-4 border border-${color}-100">
      <div class="flex items-center justify-between mb-2">
        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">${label}</span>
        <i data-lucide="${icon}" class="w-4 h-4 text-${color}-500"></i>
      </div>
      <div class="flex items-baseline gap-1.5">
        <span class="text-2xl font-black text-slate-900">${value}</span>
        <span class="text-xs text-slate-400">${unit}</span>
      </div>
    </div>
  `;
}

// ============================
// Populate Update Vitals
// ============================
function populateUpdateVitals() {
  const p = patients[selectedPatientIndex];
  const container = document.getElementById('updateVitalSignsContent');
  container.innerHTML = `
    <form class="space-y-4" onsubmit="event.preventDefault(); showToast('Vital signs updated!'); closeModal('updateVitalSignsModal');">
      <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
        <p class="text-xs font-bold text-slate-900">${p.name}</p>
        <p class="text-[11px] text-slate-500">${p.room}</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Blood Pressure</label>
          <input type="text" value="${p.bp}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Heart Rate</label>
          <input type="number" value="${p.heart_rate}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Temperature</label>
          <input type="number" step="0.1" value="${p.temperature}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Respiratory Rate</label>
          <input type="number" value="${p.respiratory_rate}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">SpO₂</label>
          <input type="number" value="${p.spo2}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
        <div>
          <label class="text-xs font-bold text-slate-700 block mb-1.5">Pain Level</label>
          <input type="number" min="0" max="10" value="${p.pain_level}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
      </div>
      <div class="flex items-center gap-3 pt-2">
        <button type="button" onclick="closeModal('updateVitalSignsModal')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-3 bg-teal-600 text-white hover:bg-teal-700 rounded-xl text-sm font-bold transition shadow-lg shadow-teal-600/25">Update Vitals</button>
      </div>
    </form>
  `;
}

// ============================
// Populate Patient Record
// ============================
function populatePatientRecord() {
  const p = patients[selectedPatientIndex];
  const container = document.getElementById('patientRecordContent');
  container.innerHTML = `
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Patient Profile -->
      <div class="space-y-4">
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-14 h-14 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center">
              <i data-lucide="user" class="w-7 h-7"></i>
            </div>
            <div>
              <h4 class="text-lg font-bold text-slate-900">${p.name}</h4>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${getStatusBadgeClass(p.status)}">● ${p.status}</span>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div><span class="text-slate-400 font-medium">Patient ID</span><p class="font-bold text-slate-900 mt-0.5">${p.id}</p></div>
            <div><span class="text-slate-400 font-medium">Age</span><p class="font-bold text-slate-900 mt-0.5">${p.age} years old</p></div>
            <div><span class="text-slate-400 font-medium">Gender</span><p class="font-bold text-slate-900 mt-0.5">${p.gender}</p></div>
            <div><span class="text-slate-400 font-medium">Contact</span><p class="font-bold text-slate-900 mt-0.5">${p.contact}</p></div>
            <div><span class="text-slate-400 font-medium">Room</span><p class="font-bold text-slate-900 mt-0.5">${p.room}</p></div>
            <div><span class="text-slate-400 font-medium">Physician</span><p class="font-bold text-slate-900 mt-0.5">${p.attending_physician}</p></div>
            <div class="col-span-2"><span class="text-slate-400 font-medium">Admission Date</span><p class="font-bold text-slate-900 mt-0.5">${p.admission_date}</p></div>
          </div>
        </div>
        
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
          <h5 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Medical History</h5>
          <p class="text-sm text-slate-700 leading-relaxed">${p.medical_history}</p>
          <h5 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2 mt-4">Allergies</h5>
          <p class="text-sm font-semibold ${p.allergies === 'None' ? 'text-emerald-600' : 'text-rose-600'}">${p.allergies}</p>
        </div>
      </div>

      <!-- Latest Vitals & More -->
      <div class="space-y-4">
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
          <h5 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Latest Vital Signs</h5>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="bg-white rounded-xl p-3 border border-slate-100">
              <span class="text-slate-400">Heart Rate</span>
              <p class="text-base font-black text-slate-900 mt-1">${p.heart_rate} <span class="text-xs font-normal text-slate-400">BPM</span></p>
            </div>
            <div class="bg-white rounded-xl p-3 border border-slate-100">
              <span class="text-slate-400">Blood Pressure</span>
              <p class="text-base font-black text-slate-900 mt-1">${p.bp} <span class="text-xs font-normal text-slate-400">mmHg</span></p>
            </div>
            <div class="bg-white rounded-xl p-3 border border-slate-100">
              <span class="text-slate-400">Temperature</span>
              <p class="text-base font-black text-slate-900 mt-1">${p.temperature} <span class="text-xs font-normal text-slate-400">°C</span></p>
            </div>
            <div class="bg-white rounded-xl p-3 border border-slate-100">
              <span class="text-slate-400">SpO₂</span>
              <p class="text-base font-black text-slate-900 mt-1">${p.spo2} <span class="text-xs font-normal text-slate-400">%</span></p>
            </div>
            <div class="bg-white rounded-xl p-3 border border-slate-100">
              <span class="text-slate-400">Resp. Rate</span>
              <p class="text-base font-black text-slate-900 mt-1">${p.respiratory_rate} <span class="text-xs font-normal text-slate-400">BPM</span></p>
            </div>
            <div class="bg-white rounded-xl p-3 border border-slate-100">
              <span class="text-slate-400">Pain Level</span>
              <p class="text-base font-black text-slate-900 mt-1">${p.pain_level}/10</p>
            </div>
          </div>
        </div>

        <div class="bg-amber-50/60 rounded-2xl p-4 border border-amber-100">
          <div class="flex items-center gap-2 mb-2">
            <i data-lucide="lock" class="w-4 h-4 text-amber-600"></i>
            <span class="text-xs font-bold text-amber-800">View-Only Access</span>
          </div>
          <p class="text-[11px] text-amber-700 leading-relaxed">As a nurse, you have view-only access to this patient record. Contact the attending physician for modifications to medical records, prescriptions, or treatment plans.</p>
        </div>
      </div>
    </div>
  `;
  lucide.createIcons();
}

// ============================
// Helpers
// ============================
function getStatusBadgeClass(status) {
  const map = {
    'Stable': 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    'Under Observation': 'bg-blue-50 text-blue-700 border border-blue-200',
    'Needs Attention': 'bg-amber-50 text-amber-700 border border-amber-200',
    'Critical': 'bg-red-50 text-red-700 border border-red-200',
    'For Discharge': 'bg-purple-50 text-purple-700 border border-purple-200'
  };
  return map[status] || map['Stable'];
}

function showToast(message) {
  const toast = document.getElementById('toast');
  const msg = document.getElementById('toastMessage');
  msg.textContent = message;
  toast.classList.remove('hidden');
  toast.style.animation = 'none';
  toast.offsetHeight; // trigger reflow
  toast.style.animation = '';
  
  setTimeout(() => {
    toast.classList.add('hidden');
  }, 3000);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  const filterDropdown = document.getElementById('filterDropdown');
  if (filterDropdown && !filterDropdown.contains(e.target) && !e.target.closest('[onclick*="filterDropdown"]')) {
    filterDropdown.classList.add('hidden');
  }
});

// Escape key closes modals
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.fixed.z-\\[60\\]:not(.hidden)').forEach(modal => {
      closeModal(modal.id);
    });
  }
});

// Schedule Tabs Switcher (Requirement 11)
function switchTaskScheduleTab(tab) {
  document.querySelectorAll('.schedule-panel').forEach(p => p.classList.add('hidden'));
  const panel = document.getElementById('panel-' + tab);
  if (panel) panel.classList.remove('hidden');

  ['due', 'upcoming', 'completed', 'missed'].forEach(t => {
    const btn = document.getElementById('tabBtn-' + t);
    if (btn) {
      if (t === tab) {
        btn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-white text-slate-900 shadow-xs';
      } else {
        btn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900';
      }
    }
  });
}

// Medication Administration Modal Opener (Requirement 9 & 10)
function openMedicationAdminModal(taskId, patientId, patientName, medication, dosage, route, schedTime) {
  document.getElementById('admin_task_id').value = taskId;
  document.getElementById('admin_patient_id').value = patientId;
  document.getElementById('admin_patient_name').textContent = patientName;
  document.getElementById('admin_med_name').textContent = medication;
  document.getElementById('admin_dosage_route').textContent = (dosage || 'Standard') + ' (' + (route || 'Oral') + ')';
  document.getElementById('admin_sched_time').textContent = schedTime || 'As Scheduled';
  document.getElementById('admin_status').value = 'Administered';
  document.getElementById('admin_notes').value = '';
  openModal('medicationAdminModal');
}

// Submit Administration to Backend (Requirement 9 & 30: Zero fake success)
async function submitMedicationAdministration(e) {
  e.preventDefault();
  const btn = document.getElementById('adminSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="animate-spin">⏳</span> Saving...';

  const taskId = document.getElementById('admin_task_id').value;
  const patientId = document.getElementById('admin_patient_id').value;
  const status = document.getElementById('admin_status').value;
  const notes = document.getElementById('admin_notes').value;

  try {
    const res = await fetch('/api/nurse/medications/administer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '<?= csrf_token() ?>'
      },
      body: JSON.stringify({
        task_id: taskId,
        patient_id: patientId,
        status: status,
        notes: notes
      })
    });
    const data = await res.json();
    if (data.success) {
      alert(data.message || 'Administration record successfully saved.');
      closeModal('medicationAdminModal');
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Unable to record medication administration.'));
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i><span>Confirm Administration</span>';
      lucide.createIcons();
    }
  } catch (err) {
    console.error(err);
    alert('Network error connecting to medication administration service.');
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i><span>Confirm Administration</span>';
    lucide.createIcons();
  }
}
</script>

<!-- Modal: Medication Administration (Requirement 9 & 10) -->
<div id="medicationAdminModal" class="fixed inset-0 z-[60] hidden">
  <div class="modal-backdrop absolute inset-0 bg-black/50 backdrop-blur-xs opacity-0" onclick="closeModal('medicationAdminModal')"></div>
  <div class="modal-content absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl opacity-0 translate-x-4 overflow-y-auto">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <div>
        <div class="flex items-center gap-2">
          <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
            <i data-lucide="pill" class="w-4 h-4"></i>
          </span>
          <h3 class="text-base font-black text-slate-900 font-display">Record Medication Administration</h3>
        </div>
        <p class="text-xs text-slate-400 mt-0.5">Clinical verification & administration log</p>
      </div>
      <button onclick="closeModal('medicationAdminModal')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form class="p-6 space-y-4" onsubmit="submitMedicationAdministration(event)">
      <input type="hidden" name="task_id" id="admin_task_id" value="" />
      <input type="hidden" name="patient_id" id="admin_patient_id" value="" />

      <!-- Patient & Medication Verification Banner -->
      <div class="p-4 bg-blue-50/60 rounded-2xl border border-blue-100 space-y-1.5">
        <span class="text-[10px] font-black uppercase tracking-wider text-blue-600">Patient Verification</span>
        <p class="text-sm font-extrabold text-slate-900" id="admin_patient_name">Loading patient...</p>
        <div class="text-xs text-slate-700 pt-1 border-t border-blue-100/60 space-y-0.5">
          <p><strong>Medication:</strong> <span id="admin_med_name">Loading...</span></p>
          <p><strong>Dosage / Route:</strong> <span id="admin_dosage_route">Loading...</span></p>
          <p><strong>Scheduled Time:</strong> <span id="admin_sched_time">Loading...</span></p>
        </div>
      </div>

      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Administration Outcome / Status</label>
        <select name="status" id="admin_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="Administered">● Administered (Given as ordered)</option>
          <option value="Held">● Held (Clinically contraindicated / patient refused)</option>
          <option value="Missed">● Missed (Patient absent / delayed)</option>
          <option value="Cancelled">● Cancelled (Discontinued by doctor)</option>
        </select>
      </div>

      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Administration Timestamp</label>
        <input type="datetime-local" name="timestamp" id="admin_timestamp" value="<?= date('Y-m-d\TH:i') ?>" 
               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="text-xs font-bold text-slate-700 block mb-1.5">Nurse Observation & Tolerance Notes</label>
        <textarea name="notes" id="admin_notes" rows="3" placeholder="e.g. Tolerated oral medication well with water. Vitals verified prior to dose..." 
                  class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
      </div>

      <div class="flex items-center gap-3 pt-3">
        <button type="button" onclick="closeModal('medicationAdminModal')" class="flex-1 px-4 py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-xs font-bold transition">Cancel</button>
        <button type="submit" id="adminSubmitBtn" class="flex-1 px-4 py-2.5 bg-emerald-600 text-white hover:bg-emerald-700 rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-1.5">
          <i data-lucide="check-circle" class="w-4 h-4"></i>
          <span>Confirm Administration</span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

