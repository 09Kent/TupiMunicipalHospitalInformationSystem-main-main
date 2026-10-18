<?php
// Doctor/views/referrals/index.php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Specialty.php';
require_once __DIR__ . '/../models/Referral.php';

$pageTitle = 'Patient Referrals (Internal & External) | Doctor Portal • Tupi Municipal Hospital';
$activeMenu = 'referrals';

$currentUser = Session::getCurrentUser();
$doctorId = $currentUser['doctor_id'] ?? (int)session('doctor_id', 1);
$specialtyId = $currentUser['specialty_id'] ?? 2;

$refModel = new Referral();
$patientModel = new Patient();
$specialtyModel = new Specialty();
$doctorModel = new Doctor();

$selectedPatientId = (int)($_GET['patient_id'] ?? 0);
$doctorPatients = $patientModel->getDoctorPatients($doctorId, 100, 0);
$allSpecialties = $specialtyModel->getAll();
$allDoctors = $doctorModel->getAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validate_csrf($csrf)) {
        Session::setFlash('error', 'Invalid security token.');
    } elseif ($action === 'create_referral') {
        $pId = (int)($_POST['patient_id'] ?? 0);
        $refType = $_POST['referral_type'] ?? 'Internal';
        $targetSpecId = !empty($_POST['target_specialty_id']) ? (int)$_POST['target_specialty_id'] : null;
        $targetDocId = !empty($_POST['target_doctor_id']) ? (int)$_POST['target_doctor_id'] : null;
        $receivingHospital = trim($_POST['receiving_hospital'] ?? '');
        $hospitalAddress = trim($_POST['hospital_address'] ?? '');
        $requestedService = trim($_POST['requested_service'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $summary = trim($_POST['clinical_summary'] ?? '');
        $priority = $_POST['priority'] ?? 'Routine';
        $urgency = $_POST['urgency'] ?? $priority;

        if ($pId && !empty($reason)) {
            if (!$patientModel->hasDoctorAccess($doctorId, $pId)) {
                Session::setFlash('error', 'Access Denied: You are not authorized to create referrals for this patient.');
                header("Location: " . doctor_url("views/referrals/index.php"));
                exit;
            }

            // Validation based on type
            if ($refType === 'Internal' && !$targetSpecId) {
                Session::setFlash('error', 'Please choose a target clinical specialty for internal referral.');
                header("Location: " . doctor_url("views/referrals/index.php"));
                exit;
            }
            if ($refType === 'External' && empty($receivingHospital)) {
                Session::setFlash('error', 'Please specify the receiving hospital for external referral.');
                header("Location: " . doctor_url("views/referrals/index.php"));
                exit;
            }

            $refId = $refModel->create([
                'patient_id'           => $pId,
                'referral_type'        => $refType,
                'referring_doctor_id'  => $doctorId,
                'referring_department' => $currentUser['specialty'] ?? 'General Medicine',
                'target_specialty_id'  => $targetSpecId,
                'target_doctor_id'     => $targetDocId,
                'receiving_hospital'   => $receivingHospital ?: null,
                'hospital_address'     => $hospitalAddress ?: null,
                'requested_service'    => $requestedService ?: null,
                'reason'               => $reason,
                'clinical_summary'     => $summary,
                'priority'             => $priority,
                'urgency'              => $urgency,
                'status'               => 'Pending',
                'tracking_status'      => $refType === 'External' ? 'Sent' : 'Created'
            ]);

            Session::setFlash('success', ($refType === 'External' ? 'External hospital referral dispatched' : 'Internal specialist referral routed') . ' successfully.');
            header("Location: " . doctor_url("views/referrals/index.php"));
            exit;
        } else {
            Session::setFlash('error', 'Please fill in all required referral details.');
        }
    } elseif ($action === 'update_status') {
        $refId = (int)($_POST['referral_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Accepted';
        $notes = trim($_POST['response_notes'] ?? '');

        if ($refId) {
            $ref = $refModel->findById($refId);
            if ($ref && ((int)$ref['TargetDoctorID'] === $doctorId || ((int)$ref['TargetSpecialtyID'] === $specialtyId && empty($ref['TargetDoctorID'])) || (int)$ref['ReferringDoctorID'] === $doctorId)) {
                $refModel->updateStatus($refId, $newStatus, $notes);
                Session::setFlash('success', "Referral status updated to $newStatus.");
            } else {
                Session::setFlash('error', 'Access Denied: You can only update referrals directed to you or your specialty.');
            }
            header("Location: " . doctor_url("views/referrals/index.php"));
            exit;
        }
    } elseif ($action === 'update_tracking') {
        $refId = (int)($_POST['referral_id'] ?? 0);
        $tracking = $_POST['tracking_status'] ?? 'Sent';

        if ($refId) {
            $refModel->updateTracking($refId, $tracking);
            Session::setFlash('success', "Referral tracking status updated to: $tracking");
            header("Location: " . doctor_url("views/referrals/index.php"));
            exit;
        }
    }
}

$outgoingReferrals = $refModel->getOutgoing($doctorId);
$incomingReferrals = $refModel->getIncoming($doctorId, $specialtyId);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen bg-slate-50">
  
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="p-6 sm:p-8 space-y-6 flex-1">

    <!-- Header & Action Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-black text-slate-900 tracking-tight font-display">Patient Referrals & Transfers</h1>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
            Internal & External
          </span>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-1">Cross-departmental specialist evaluations and external hospital transfers with live tracking.</p>
      </div>

      <div class="flex items-center gap-2.5">
        <button onclick="openNewRefModal('Internal')"
                class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md shadow-blue-600/25 transition flex items-center gap-2">
          <i data-lucide="share-2" class="w-4 h-4"></i>
          <span>Internal Specialty Referral</span>
        </button>

        <button onclick="openNewRefModal('External')"
                class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md shadow-amber-600/25 transition flex items-center gap-2">
          <i data-lucide="building-2" class="w-4 h-4"></i>
          <span>Refer to Another Hospital</span>
        </button>
      </div>
    </div>

    <!-- Section 1: Incoming Referrals (Sent to this doctor or specialty) -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
      <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h2 class="text-sm font-extrabold text-slate-900 font-display flex items-center gap-2">
            <i data-lucide="inbox" class="w-4 h-4 text-purple-600"></i>
            <span>Incoming Referrals to <?= e($currentUser['specialty']) ?> (<?= count($incomingReferrals) ?>)</span>
          </h2>
          <p class="text-xs text-slate-400 font-medium">Patients referred from other hospital medical departments for your evaluation</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
              <th class="py-3 px-5">Referral Code</th>
              <th class="py-3 px-5">Patient Name</th>
              <th class="py-3 px-5">Referring Doctor</th>
              <th class="py-3 px-5">Clinical Reason & Summary</th>
              <th class="py-3 px-5">Priority</th>
              <th class="py-3 px-5">Tracking Status</th>
              <th class="py-3 px-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($incomingReferrals)): ?>
              <tr>
                <td colspan="7" class="py-8 text-center text-slate-400 italic">No incoming referrals at this time.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($incomingReferrals as $inRef): ?>
                <tr class="hover:bg-blue-50/40 transition">
                  <td class="py-4 px-5 font-mono font-bold text-blue-700">
                    <?= e($inRef['ReferralCode']) ?>
                    <span class="block text-[10px] font-semibold text-slate-400 font-sans">
                      <?= e($inRef['ReferralType'] ?? 'Internal') ?>
                    </span>
                  </td>
                  <td class="py-4 px-5">
                    <a href="<?= doctor_url('views/patients/view.php?id=' . $inRef['PatientID']) ?>" 
                       class="font-extrabold text-slate-900 hover:text-blue-600 transition block">
                      <?= e($inRef['PatientFirstName'] . ' ' . $inRef['PatientLastName']) ?>
                    </a>
                    <span class="text-[11px] text-slate-400 font-mono"><?= e($inRef['PatientCode']) ?></span>
                  </td>
                  <td class="py-4 px-5">
                    <div class="font-bold text-slate-800">Dr. <?= e($inRef['RefDocFirstName'] . ' ' . $inRef['RefDocLastName']) ?></div>
                    <span class="text-[11px] text-slate-400"><?= e($inRef['RefDocSpecialty']) ?></span>
                  </td>
                  <td class="py-4 px-5 max-w-sm">
                    <p class="font-bold text-slate-800 line-clamp-1"><?= e($inRef['Reason']) ?></p>
                    <p class="text-[11px] text-slate-500 line-clamp-2"><?= e($inRef['ClinicalSummary'] ?: 'Standard specialist opinion request.') ?></p>
                  </td>
                  <td class="py-4 px-5">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?= get_priority_badge($inRef['Priority']) ?>">
                      <?= e($inRef['Priority']) ?>
                    </span>
                  </td>
                  <td class="py-4 px-5">
                    <span class="px-2.5 py-1 text-[10px] font-bold rounded-full border <?= get_status_badge($inRef['Status']) ?>">
                      <?= e($inRef['TrackingStatus'] ?? $inRef['Status']) ?>
                    </span>
                  </td>
                  <td class="py-4 px-5 text-right space-x-1.5 whitespace-nowrap">
                    <!-- Universal Printout -->
                    <a href="/print/referral/<?= (int)$inRef['ReferralID'] ?>" target="_blank"
                       title="Print Official Referral Document"
                       class="px-2.5 py-1 text-slate-700 bg-slate-100 hover:bg-slate-200 font-bold rounded-xl text-[11px] transition inline-flex items-center gap-1 border border-slate-200">
                      <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                      <span>Print</span>
                    </a>

                    <?php if ($inRef['Status'] === 'Pending'): ?>
                      <form method="POST" action="" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                        <input type="hidden" name="action" value="update_status" />
                        <input type="hidden" name="referral_id" value="<?= $inRef['ReferralID'] ?>" />
                        <input type="hidden" name="status" value="Accepted" />
                        <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-[11px] transition">
                          Accept
                        </button>
                      </form>
                    <?php elseif ($inRef['Status'] === 'Accepted'): ?>
                      <form method="POST" action="" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                        <input type="hidden" name="action" value="update_status" />
                        <input type="hidden" name="referral_id" value="<?= $inRef['ReferralID'] ?>" />
                        <input type="hidden" name="status" value="Completed" />
                        <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-[11px] transition">
                          Complete
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Section 2: Outgoing Referrals (Sent by this doctor) -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
      <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h2 class="text-sm font-extrabold text-slate-900 font-display flex items-center gap-2">
            <i data-lucide="send" class="w-4 h-4 text-blue-600"></i>
            <span>Outgoing Referrals (<?= count($outgoingReferrals) ?>)</span>
          </h2>
          <p class="text-xs text-slate-400 font-medium">Internal specialty consultations and external hospital transfers requested by you</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
              <th class="py-3 px-5">Referral Code & Type</th>
              <th class="py-3 px-5">Patient Name</th>
              <th class="py-3 px-5">Destination / Receiving Facility</th>
              <th class="py-3 px-5">Reason & Requested Service</th>
              <th class="py-3 px-5">Tracking Progress</th>
              <th class="py-3 px-5">Date</th>
              <th class="py-3 px-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($outgoingReferrals)): ?>
              <tr>
                <td colspan="7" class="py-8 text-center text-slate-400 italic">No outgoing referrals recorded yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($outgoingReferrals as $outRef): 
                $isExternal = ($outRef['ReferralType'] ?? 'Internal') === 'External';
                $tracking = $outRef['TrackingStatus'] ?? 'Created';
              ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-4 px-5">
                    <span class="font-mono font-bold text-blue-700 block"><?= e($outRef['ReferralCode']) ?></span>
                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold <?= $isExternal ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-100' ?>">
                      <?= $isExternal ? 'External Hospital' : 'Internal Specialty' ?>
                    </span>
                  </td>
                  <td class="py-4 px-5 font-extrabold text-slate-900">
                    <?= e($outRef['PatientFirstName'] . ' ' . $outRef['PatientLastName']) ?>
                    <span class="block text-[11px] text-slate-400 font-mono font-normal"><?= e($outRef['PatientCode']) ?></span>
                  </td>
                  <td class="py-4 px-5">
                    <?php if ($isExternal): ?>
                      <div class="font-bold text-amber-900 flex items-center gap-1">
                        <i data-lucide="building-2" class="w-3.5 h-3.5 text-amber-600"></i>
                        <span><?= e($outRef['ReceivingHospital']) ?></span>
                      </div>
                      <?php if (!empty($outRef['HospitalAddress'])): ?>
                        <div class="text-[11px] text-slate-400 truncate max-w-xs"><?= e($outRef['HospitalAddress']) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="px-2.5 py-1 bg-slate-100 rounded-xl font-bold text-slate-700 text-[11px] border border-slate-200 block w-fit">
                        <?= e($outRef['TargetSpecialtyName'] ?? 'Specialty') ?>
                      </span>
                      <span class="text-[11px] text-slate-500 mt-0.5 block">
                        <?= $outRef['TargetDocFirstName'] ? 'Dr. ' . e($outRef['TargetDocFirstName'] . ' ' . $outRef['TargetDocLastName']) : 'Any Attending Specialist' ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-5 max-w-xs">
                    <div class="font-bold text-slate-800 line-clamp-1"><?= e($outRef['Reason']) ?></div>
                    <?php if (!empty($outRef['RequestedService'])): ?>
                      <div class="text-[11px] text-blue-600 font-medium">Service: <?= e($outRef['RequestedService']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-5">
                    <!-- Workflow Progress Indicator -->
                    <div class="flex items-center gap-1 text-[10px] font-bold">
                      <span class="px-2 py-0.5 rounded-full border <?= get_status_badge($outRef['Status']) ?>">
                        <?= e($tracking) ?>
                      </span>
                    </div>
                  </td>
                  <td class="py-4 px-5 text-slate-500 whitespace-nowrap"><?= format_date($outRef['CreatedAt']) ?></td>
                  <td class="py-4 px-5 text-right whitespace-nowrap space-x-1">
                    <!-- Print Referral Document -->
                    <a href="/print/referral/<?= (int)$outRef['ReferralID'] ?>" target="_blank"
                       title="Print Official Referral Document"
                       class="px-2.5 py-1 text-slate-700 bg-slate-100 hover:bg-slate-200 font-bold rounded-xl text-[11px] transition inline-flex items-center gap-1 border border-slate-200">
                      <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                      <span>Print Form</span>
                    </a>

                    <!-- Fast Track Status update for External Referrals -->
                    <?php if ($isExternal && $tracking === 'Sent'): ?>
                      <form method="POST" action="" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                        <input type="hidden" name="action" value="update_tracking" />
                        <input type="hidden" name="referral_id" value="<?= $outRef['ReferralID'] ?>" />
                        <input type="hidden" name="tracking_status" value="Transferred" />
                        <button type="submit" title="Confirm patient arrival / transfer at hospital" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-[11px] transition">
                          Transferred
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

</div>

<!-- Modal: Create Referral (Supports Internal & External) -->
<div id="newRefModal" class="<?= isset($_GET['new']) ? '' : 'hidden' ?> fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 border border-slate-200 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
    
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center">
          <i data-lucide="share-2" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="text-base font-extrabold text-slate-900 font-display">Create Patient Referral</h3>
          <p class="text-xs text-slate-400">Internal specialty review or transfer to another medical center</p>
        </div>
      </div>
      <button type="button" onclick="document.getElementById('newRefModal').classList.add('hidden')"
              class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-4 h-4"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4 text-xs">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
      <input type="hidden" name="action" value="create_referral" />

      <!-- Referral Type Toggle -->
      <div>
        <label class="block font-bold text-slate-700 mb-1.5 uppercase text-[11px] tracking-wider">Referral Type *</label>
        <div class="grid grid-cols-2 gap-3 p-1.5 bg-slate-100 rounded-2xl border border-slate-200/80">
          <label class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl cursor-pointer font-bold transition text-xs select-none" id="type-internal-label">
            <input type="radio" name="referral_type" value="Internal" checked onchange="toggleRefType('Internal')" class="hidden">
            <i data-lucide="stethoscope" class="w-4 h-4"></i>
            <span>Internal Hospital Referral</span>
          </label>
          <label class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl cursor-pointer font-bold transition text-xs select-none" id="type-external-label">
            <input type="radio" name="referral_type" value="External" onchange="toggleRefType('External')" class="hidden">
            <i data-lucide="building-2" class="w-4 h-4"></i>
            <span>External Hospital Referral</span>
          </label>
        </div>
      </div>

      <!-- Searchable Patient Field -->
      <div>
        <label class="block font-bold text-slate-700 mb-1">Search Patient *</label>
        <div class="relative">
          <input type="text" id="ref-patient-search-input" 
                 placeholder="Type patient name, Patient ID, or Student ID..." 
                 oninput="filterRefPatients(this.value)"
                 autocomplete="off"
                 class="w-full px-3.5 py-2.5 pl-9 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none"></i>
          
          <div id="ref-patient-search-results" class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 max-h-48 overflow-y-auto divide-y divide-slate-100">
            <!-- Results dynamically generated -->
          </div>
        </div>
        <input type="hidden" name="patient_id" id="ref-selected-patient-id" value="<?= $selectedPatientId ?: ($doctorPatients[0]['PatientID'] ?? '') ?>" required />
        <div id="ref-selected-patient-badge" class="mt-1.5 p-2 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-between text-[11px] font-bold text-blue-900">
          <span id="ref-selected-patient-name">
            <?= !empty($doctorPatients[0]) ? e($doctorPatients[0]['FirstName'] . ' ' . $doctorPatients[0]['LastName']) . ' (' . e($doctorPatients[0]['PatientCode']) . ')' : 'No patient selected' ?>
          </span>
          <span class="text-blue-600 font-semibold text-[10px]">Verified Patient</span>
        </div>
      </div>

      <!-- INTERNAL FIELDS -->
      <div id="internal-ref-fields" class="space-y-4">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Target Medical Specialty *</label>
          <select name="target_specialty_id" id="target_specialty_id"
                  class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Choose Target Specialty --</option>
            <?php foreach ($allSpecialties as $spec): ?>
              <?php if ($spec['SpecialtyID'] != $specialtyId): ?>
                <option value="<?= $spec['SpecialtyID'] ?>"><?= e($spec['SpecialtyName']) ?> (<?= e($spec['Description']) ?>)</option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Target Doctor (Optional)</label>
          <select name="target_doctor_id" 
                  class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Any Available Specialist</option>
            <?php foreach ($allDoctors as $doc): ?>
              <?php if ($doc['DoctorID'] != $doctorId): ?>
                <option value="<?= $doc['DoctorID'] ?>">Dr. <?= e($doc['FirstName'] . ' ' . $doc['LastName']) ?> (<?= e($doc['Specialty'] ?? 'General') ?>)</option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- EXTERNAL HOSPITAL FIELDS -->
      <div id="external-ref-fields" class="hidden space-y-4 p-4 bg-amber-50/60 rounded-2xl border border-amber-200/80">
        <div>
          <label class="block font-bold text-amber-900 mb-1">Receiving Hospital / Medical Center *</label>
          <input type="text" name="receiving_hospital" id="receiving_hospital"
                 placeholder="e.g. South Cotabato Provincial Hospital / GenSan Doctors Hospital" 
                 list="hospital-presets"
                 class="w-full px-3.5 py-2.5 bg-white border border-amber-300 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500" />
          <datalist id="hospital-presets">
            <option value="South Cotabato Provincial Hospital (SCPH) - Koronadal City">
            <option value="General Santos City District Hospital">
            <option value="St. Elizabeth Hospital - General Santos City">
            <option value="Davao Regional Medical Center (DRMC) - Tagum City">
            <option value="Southern Philippines Medical Center (SPMC) - Davao City">
            <option value="Allentown General Hospital - South Cotabato">
          </datalist>
        </div>

        <div>
          <label class="block font-bold text-amber-900 mb-1">Hospital Address / Location</label>
          <input type="text" name="hospital_address" 
                 placeholder="e.g. Aguinaldo St., Koronadal City, South Cotabato" 
                 class="w-full px-3.5 py-2.5 bg-white border border-amber-300 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500" />
        </div>

        <div>
          <label class="block font-bold text-amber-900 mb-1">Requested Service / Level of Care *</label>
          <input type="text" name="requested_service" 
                 placeholder="e.g. Tertiary ICU Admission, Advanced CT/MRI, Emergency Neurosurgery" 
                 class="w-full px-3.5 py-2.5 bg-white border border-amber-300 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500" />
        </div>
      </div>

      <!-- Priority / Urgency -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Referral Priority *</label>
          <select name="priority" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800">
            <option value="Routine" selected>Routine Consultation</option>
            <option value="Urgent">Urgent Evaluation</option>
            <option value="Emergency">Emergency Transfer / Review</option>
          </select>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Clinical Urgency *</label>
          <select name="urgency" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800">
            <option value="Routine" selected>Routine (Within 48h)</option>
            <option value="Urgent">Urgent (Within 12h)</option>
            <option value="Emergency">Immediate (Stat)</option>
          </select>
        </div>
      </div>

      <!-- Reason for Referral -->
      <div>
        <label class="block font-bold text-slate-700 mb-1">Reason for Referral *</label>
        <input type="text" name="reason" required placeholder="e.g. Need higher level tertiary intensive care / specialized diagnostic evaluation..." 
               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <!-- Clinical Summary -->
      <div>
        <label class="block font-bold text-slate-700 mb-1">Diagnosis & Clinical Case Summary</label>
        <textarea name="clinical_summary" rows="3" placeholder="Summarize patient diagnosis, initial vital signs, treatment given, and reason TMHIMS cannot manage patient..." 
                  class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
      </div>

      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
        <button type="button" onclick="document.getElementById('newRefModal').classList.add('hidden')"
                class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
          Cancel
        </button>
        <button type="submit" id="ref-submit-btn"
                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-600/20 transition flex items-center gap-1.5">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span>Dispatch Referral</span>
        </button>
      </div>
    </form>

  </div>
</div>

<script>
  // Available patients in memory for instantaneous search
  const REFERRAL_PATIENTS = <?= json_encode(array_map(function($p) {
      return [
          'id' => (int)$p['PatientID'],
          'name' => $p['FirstName'] . ' ' . $p['LastName'],
          'code' => $p['PatientCode'] ?? ('TMH-' . $p['PatientID']),
          'student_id' => $p['StudentID'] ?? ''
      ];
  }, $doctorPatients)) ?>;

  let refSearchTimer = null;
  function filterRefPatients(query) {
    const box = document.getElementById('ref-patient-search-results');
    if (!box) return;
    const q = (query || '').toLowerCase().trim();
    if (!q) {
      box.classList.add('hidden');
      return;
    }

    const matches = REFERRAL_PATIENTS.filter(p => 
      p.name.toLowerCase().includes(q) || 
      p.code.toLowerCase().includes(q) || 
      (p.student_id && p.student_id.toLowerCase().includes(q))
    );

    if (matches.length > 0) {
      let html = '';
      matches.slice(0, 8).forEach(p => {
        html += `
          <div onclick="selectRefPatient(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.code}')" 
               class="p-2.5 hover:bg-blue-50 cursor-pointer flex items-center justify-between transition">
            <div>
              <div class="font-bold text-slate-800">${p.name}</div>
              <div class="text-[11px] text-slate-400 font-mono">${p.code} ${p.student_id ? '• ' + p.student_id : ''}</div>
            </div>
            <span class="text-blue-600 font-bold text-[10px]">Select &rarr;</span>
          </div>
        `;
      });
      box.innerHTML = html;
      box.classList.remove('hidden');
      return;
    }

    // Fallback to Universal Search API
    clearTimeout(refSearchTimer);
    refSearchTimer = setTimeout(async () => {
      try {
        const res = await fetch('/api/search/patients?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.data || data.data.length === 0) {
          box.innerHTML = '<div class="p-3 text-slate-400 text-center font-medium">No patients found.</div>';
          box.classList.remove('hidden');
          return;
        }
        let html = '';
        data.data.slice(0, 8).forEach(p => {
          html += `
            <div onclick="selectRefPatient(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.patient_code || p.code}')" 
                 class="p-2.5 hover:bg-blue-50 cursor-pointer flex items-center justify-between transition">
              <div>
                <div class="font-bold text-slate-800">${p.name}</div>
                <div class="text-[11px] text-slate-400 font-mono">${p.patient_code || p.code} • Hospital Registry</div>
              </div>
              <span class="text-blue-600 font-bold text-[10px]">Select &rarr;</span>
            </div>
          `;
        });
        box.innerHTML = html;
        box.classList.remove('hidden');
      } catch (e) {
        box.innerHTML = '<div class="p-3 text-slate-400 text-center font-medium">No patients found.</div>';
        box.classList.remove('hidden');
      }
    }, 200);
  }

  function selectRefPatient(id, name, code) {
    document.getElementById('ref-selected-patient-id').value = id;
    document.getElementById('ref-selected-patient-name').innerText = `${name} (${code})`;
    document.getElementById('ref-patient-search-input').value = `${name} (${code})`;
    document.getElementById('ref-patient-search-results').classList.add('hidden');
  }

  function toggleRefType(type) {
    const internalFields = document.getElementById('internal-ref-fields');
    const externalFields = document.getElementById('external-ref-fields');
    const intLabel = document.getElementById('type-internal-label');
    const extLabel = document.getElementById('type-external-label');
    const submitBtn = document.getElementById('ref-submit-btn');

    if (type === 'Internal') {
      internalFields.classList.remove('hidden');
      externalFields.classList.add('hidden');
      intLabel.className = 'flex items-center justify-center gap-2 py-2 px-3 rounded-xl cursor-pointer font-bold transition text-xs select-none bg-blue-600 text-white shadow-sm';
      extLabel.className = 'flex items-center justify-center gap-2 py-2 px-3 rounded-xl cursor-pointer font-bold transition text-xs select-none text-slate-600 hover:text-slate-900';
      submitBtn.className = 'px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-600/20 transition flex items-center gap-1.5';
    } else {
      internalFields.classList.add('hidden');
      externalFields.classList.remove('hidden');
      extLabel.className = 'flex items-center justify-center gap-2 py-2 px-3 rounded-xl cursor-pointer font-bold transition text-xs select-none bg-amber-600 text-white shadow-sm';
      intLabel.className = 'flex items-center justify-center gap-2 py-2 px-3 rounded-xl cursor-pointer font-bold transition text-xs select-none text-slate-600 hover:text-slate-900';
      submitBtn.className = 'px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl shadow-md shadow-amber-600/20 transition flex items-center gap-1.5';
    }
  }

  function openNewRefModal(type = 'Internal') {
    document.getElementById('newRefModal').classList.remove('hidden');
    const radios = document.getElementsByName('referral_type');
    for (let r of radios) {
      if (r.value === type) {
        r.checked = true;
        break;
      }
    }
    toggleRefType(type);
  }

  // Close search popup on click outside
  document.addEventListener('click', (e) => {
    const box = document.getElementById('ref-patient-search-results');
    const input = document.getElementById('ref-patient-search-input');
    if (box && input && !box.contains(e.target) && e.target !== input) {
      box.classList.add('hidden');
    }
  });

  // Initialize modal state on load
  document.addEventListener('DOMContentLoaded', () => {
    toggleRefType('Internal');
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
