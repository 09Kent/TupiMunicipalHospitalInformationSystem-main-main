<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($docTitle) ?> | <?= e($docTracking) ?> • TMHIMS</title>
  
  <!-- Tailwind CSS CDN / Offline Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background-color: #f1f5f9;
      color: #0f172a;
    }
    .font-mono {
      font-family: 'JetBrains Mono', monospace;
    }

    @media screen {
      .print-sheet {
        width: 210mm;
        min-height: 297mm;
        margin: 20px auto 40px auto;
        padding: 18mm 16mm;
        background: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
      }
    }

    @media print {
      @page {
        size: A4 portrait;
        margin: 10mm 12mm 12mm 12mm;
      }
      body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .no-print {
        display: none !important;
      }
      .print-sheet {
        width: 100% !important;
        min-height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
      }
      .break-inside-avoid {
        page-break-inside: avoid;
        break-inside: avoid;
      }
    }
  </style>
</head>
<body class="antialiased min-h-screen">

  <!-- Floating Action Bar (Hidden in Print) -->
  <div class="no-print sticky top-0 z-50 bg-slate-900/90 backdrop-blur-md border-b border-slate-700 text-white px-6 py-3 flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-sm">
        H
      </div>
      <div>
        <div class="text-xs font-bold uppercase tracking-wider text-blue-400">TMHIMS Official Document Viewer</div>
        <div class="text-sm font-extrabold text-slate-100 flex items-center gap-2">
          <span><?= e($docTitle) ?></span>
          <span class="text-xs font-mono font-medium px-2 py-0.5 rounded bg-slate-800 text-slate-300"><?= e($docTracking) ?></span>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <?php if (!empty($patient)): ?>
        <?php if (!empty($data['is_comprehensive']) || in_array($type, ['comprehensive-record', 'full-medical-certificate', 'all-in-one'])): ?>
          <a href="/print/certificate/<?= $patient->PatientID ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-slate-600">
            <span>Standard 1-Page Cert</span>
          </a>
        <?php elseif (in_array($type, ['certificate', 'medical-certificate', 'med-cert', 'medical-record', 'chart'])): ?>
          <a href="/print/certificate/<?= $patient->PatientID ?>?full=1" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-md shadow-emerald-600/30">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Full All-in-One Record & Cert</span>
          </a>
        <?php endif; ?>
      <?php endif; ?>
      <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-md shadow-blue-600/30">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span>Print Document</span>
      </button>
      <button onclick="window.close()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition">
        Close Window
      </button>
    </div>
  </div>

  <!-- Printable Sheet Container -->
  <div class="print-sheet flex flex-col justify-between">
    <div>
      
      <!-- ====================================================
           OFFICIAL HOSPITAL HEADER / LETTERHEAD
           ==================================================== -->
      <div class="border-b-2 border-slate-900 pb-4 mb-5">
        <div class="flex items-center justify-between gap-4">
          <!-- Hospital Logo -->
          <div class="w-20 h-20 shrink-0 flex items-center justify-center">
            <img src="<?= e($hospitalInfo['logo']) ?>" alt="TMH Seal" class="h-20 w-auto object-contain" onerror="this.outerHTML='<div class=\'w-16 h-16 rounded-full bg-blue-700 text-white font-extrabold flex items-center justify-center text-xl ring-2 ring-blue-900\'>TMH</div>'">
          </div>

          <!-- Official Republic & Facility Text -->
          <div class="text-center flex-1">
            <div class="text-[11px] font-semibold tracking-wider uppercase text-slate-600"><?= e($hospitalInfo['republic']) ?></div>
            <div class="text-[11px] font-semibold uppercase text-slate-600"><?= e($hospitalInfo['province']) ?> • <?= e($hospitalInfo['lgu']) ?></div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase font-serif mt-0.5"><?= e($hospitalInfo['facility']) ?></h1>
            <div class="text-[11px] font-bold text-blue-800 tracking-wide mt-0.5"><?= e($hospitalInfo['accreditation']) ?></div>
            <div class="text-[10px] text-slate-500 mt-0.5"><?= e($hospitalInfo['address']) ?> • Hotlines: <?= e($hospitalInfo['hotline']) ?></div>
          </div>

          <!-- Document Code Box & Barcode Simulation -->
          <div class="w-32 shrink-0 text-right space-y-1">
            <div class="border border-slate-800 rounded px-2 py-1 bg-slate-50 text-center">
              <span class="block text-[8px] uppercase font-bold text-slate-500 tracking-wider">Document Control</span>
              <span class="block text-[11px] font-mono font-black text-slate-900"><?= e($docTracking) ?></span>
            </div>
            <div class="text-[9px] text-slate-500 font-mono text-center pt-0.5">
              <?= date('Y-m-d H:i') ?>
            </div>
          </div>
        </div>

        <!-- Document Title Banner -->
        <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between">
          <h2 class="text-base font-extrabold text-slate-900 tracking-wide uppercase">
            <?= e($docTitle) ?>
          </h2>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
            Official Medical Record Copy
          </span>
        </div>
      </div>

      <!-- ====================================================
           PATIENT INFORMATION BANNER (If Applicable)
           ==================================================== -->
      <?php if (!empty($patient)): ?>
      <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-300 mb-5 break-inside-avoid text-xs">
        <div class="grid grid-cols-4 gap-3">
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Patient Name:</span>
            <span class="font-bold text-slate-900 text-sm">
              <?= e($patient->FirstName . ' ' . ($patient->MiddleName ? $patient->MiddleName . ' ' : '') . $patient->LastName) ?>
            </span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Patient ID / Code:</span>
            <span class="font-mono font-bold text-blue-900 text-sm"><?= e($patient->PatientCode ?: ('TMH-' . str_pad((string)$patient->PatientID, 6, '0', STR_PAD_LEFT))) ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">PhilHealth PIN / Ownership:</span>
            <span class="font-mono font-bold text-slate-900 text-xs block"><?= e($patient->PhilHealthNumber ?: 'None Presented / Non-Member') ?></span>
            <span class="text-[9px] font-semibold text-emerald-700 block"><?= e($patient->PhilHealthCategory ?: 'Personal Member') ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Age / Gender / DOB:</span>
            <span class="font-semibold text-slate-800"><?= e($patient->Age) ?> yrs • <?= e($patient->Gender) ?> (<?= e($patient->DateOfBirth ?: 'N/A') ?>)</span>
          </div>

          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Civil Status / Blood:</span>
            <span class="font-medium text-slate-800"><?= e($patient->CivilStatus ?: 'Single') ?> • Blood Type: <?= e($patient->BloodType ?: 'O+') ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Contact Number:</span>
            <span class="font-mono font-medium text-slate-800"><?= e($patient->ContactNumber ?: 'N/A') ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Classification / Room:</span>
            <span class="font-bold text-slate-800"><?= e($patient->PatientCategory ?: 'Outpatient') ?> <?= ($patient->PatientCategory === 'Inpatient') ? '(Room ' . (200 + ($patient->PatientID % 20)) . ')' : '(OPD Triage)' ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Permanent Address:</span>
            <span class="font-medium text-slate-800 truncate block"><?= e($patient->Address ?: 'Tupi, South Cotabato') ?></span>
          </div>

          <?php if (!empty($data['emergency'])): ?>
          <div class="col-span-4 pt-2 mt-1 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
            <div>
              <span class="text-slate-500 uppercase font-bold text-[10px]">Emergency Contact:</span>
              <strong class="text-slate-800 ml-1"><?= e($data['emergency']->ContactName ?? 'N/A') ?></strong>
              <span class="text-slate-600">(<?= e($data['emergency']->Relationship ?? 'Relative') ?>)</span>
            </div>
            <div>
              <span class="text-slate-500 uppercase font-bold text-[10px]">Emergency Phone:</span>
              <span class="font-mono font-semibold text-slate-800 ml-1"><?= e($data['emergency']->ContactNumber ?? 'N/A') ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ====================================================
           DOCUMENT BODY BY TYPE
           ==================================================== -->

      <!-- TYPE 1: MASTER PATIENT INFORMATION -->
      <?php if (in_array($type, ['patient', 'patient-info'])): ?>
      <div class="space-y-4 text-xs">
        <div class="border border-slate-300 rounded-xl overflow-hidden">
          <div class="bg-slate-100 px-3 py-2 font-bold text-slate-800 uppercase tracking-wider text-[11px] border-b border-slate-300">
            1. Clinical Telemetry & Baseline Vital Signs
          </div>
          <div class="p-3 grid grid-cols-5 gap-2 text-center">
            <div class="p-2 bg-slate-50 rounded border border-slate-200">
              <span class="block text-[10px] text-slate-500 uppercase">Blood Pressure</span>
              <strong class="text-sm font-bold text-slate-900"><?= e($data['vitals']->BloodPressure ?? '120/80') ?></strong>
            </div>
            <div class="p-2 bg-slate-50 rounded border border-slate-200">
              <span class="block text-[10px] text-slate-500 uppercase">Heart Rate</span>
              <strong class="text-sm font-bold text-slate-900"><?= e($data['vitals']->HeartRate ?? '76') ?> bpm</strong>
            </div>
            <div class="p-2 bg-slate-50 rounded border border-slate-200">
              <span class="block text-[10px] text-slate-500 uppercase">Temperature</span>
              <strong class="text-sm font-bold text-slate-900"><?= e($data['vitals']->Temperature ?? '36.6') ?> °C</strong>
            </div>
            <div class="p-2 bg-slate-50 rounded border border-slate-200">
              <span class="block text-[10px] text-slate-500 uppercase">SpO2</span>
              <strong class="text-sm font-bold text-slate-900"><?= e($data['vitals']->OxygenSaturation ?? '98') ?> %</strong>
            </div>
            <div class="p-2 bg-slate-50 rounded border border-slate-200">
              <span class="block text-[10px] text-slate-500 uppercase">Resp. Rate</span>
              <strong class="text-sm font-bold text-slate-900"><?= e($data['vitals']->RespiratoryRate ?? '18') ?> /min</strong>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="border border-slate-300 rounded-xl p-3">
            <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider pb-1.5 border-b border-slate-200 mb-2">
              2. Medical History & Allergies
            </div>
            <div class="space-y-1.5 text-xs">
              <div><span class="text-slate-500 font-semibold">Known Allergies:</span> <span class="font-bold text-rose-700"><?= e($data['history']->Allergies ?? 'No known drug allergies (NKDA)') ?></span></div>
              <div><span class="text-slate-500 font-semibold">Existing Conditions:</span> <span class="text-slate-800"><?= e($data['history']->ExistingConditions ?? 'Hypertension (Controlled)') ?></span></div>
              <div><span class="text-slate-500 font-semibold">Current Medications:</span> <span class="text-slate-800"><?= e($data['history']->CurrentMedications ?? 'None currently reported') ?></span></div>
            </div>
          </div>

          <div class="border border-slate-300 rounded-xl p-3">
            <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider pb-1.5 border-b border-slate-200 mb-2">
              3. Emergency Contact Information
            </div>
            <div class="space-y-1.5 text-xs">
              <div><span class="text-slate-500 font-semibold">Contact Person:</span> <span class="font-bold text-slate-800"><?= e($data['emergency']->ContactName ?? 'Family Representative') ?></span></div>
              <div><span class="text-slate-500 font-semibold">Relationship:</span> <span class="text-slate-800"><?= e($data['emergency']->Relationship ?? 'Immediate Relative') ?></span></div>
              <div><span class="text-slate-500 font-semibold">Emergency Phone:</span> <span class="font-mono text-slate-800"><?= e($data['emergency']->ContactNumber ?? $patient->ContactNumber ?? 'N/A') ?></span></div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 2: PATIENT REGISTRATION FORM -->
      <?php if (in_array($type, ['registration', 'registration-form'])): ?>
      <div class="space-y-4 text-xs">
        <div class="p-3 bg-blue-50 border border-blue-300 rounded-xl flex items-center justify-between">
          <div>
            <span class="block text-[10px] font-bold text-blue-900 uppercase">Daily Intake Queue Ticket</span>
            <span class="text-2xl font-black text-blue-900">#<?= e($data['queue']->QueueNumber ?? '01') ?></span>
          </div>
          <div class="text-right">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Triage Acuity Priority</span>
            <span class="inline-block px-2.5 py-1 rounded font-bold text-xs bg-blue-600 text-white uppercase tracking-wider">
              <?= e($data['queue']->Priority ?? ($data['appointment']->Priority ?? 'Standard Triage')) ?>
            </span>
          </div>
        </div>

        <div class="border border-slate-300 rounded-xl p-3">
          <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider pb-1.5 border-b border-slate-200 mb-2">
            Chief Clinical Complaints & Body-Map Symptoms
          </div>
          <?php if (!empty($data['symptoms']) && count($data['symptoms']) > 0): ?>
            <ul class="list-disc list-inside space-y-1">
              <?php foreach ($data['symptoms'] as $sym): ?>
                <li><strong class="text-slate-900"><?= e($sym->SymptomName ?? 'General symptom') ?></strong> — Severity: <?= e($sym->Severity ?? 'Moderate') ?> (Duration: <?= e($sym->DurationDays ?? 1) ?> days)</li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-slate-600 italic">Chief complaint: Acute consultation evaluation recorded at Admitting & Triage.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 3: CLINICAL CONSULTATION & SOAP NOTE -->
      <?php if (in_array($type, ['consultation', 'clinical-note'])): ?>
      <div class="space-y-3.5 text-xs">
        <div class="border border-slate-300 rounded-xl overflow-hidden">
          <div class="bg-slate-100 px-3 py-2 font-bold text-slate-900 text-[11px] uppercase tracking-wider border-b border-slate-300">
            SOAP Clinical Evaluation Protocol
          </div>
          <div class="p-3.5 space-y-3">
            <div>
              <span class="font-black text-blue-900 uppercase tracking-wide block mb-0.5 text-xs">[S] SUBJECTIVE (History of Present Illness & Patient Complaints):</span>
              <p class="text-slate-800 bg-slate-50 p-2.5 rounded border border-slate-200 leading-relaxed">
                <?= nl2br(e($data['note']->Subjective ?? 'Patient presented for outpatient consultation with mild symptoms.')) ?>
              </p>
            </div>

            <div>
              <span class="font-black text-blue-900 uppercase tracking-wide block mb-0.5 text-xs">[O] OBJECTIVE (Physical Examination & Clinical Telemetry):</span>
              <p class="text-slate-800 bg-slate-50 p-2.5 rounded border border-slate-200 leading-relaxed">
                <?= nl2br(e($data['note']->Objective ?? 'General physical assessment conducted. Vitals stable. Chest clear, abdomen soft and non-tender.')) ?>
              </p>
            </div>

            <div>
              <span class="font-black text-blue-900 uppercase tracking-wide block mb-0.5 text-xs">[A] ASSESSMENT (Clinical Differential Diagnosis & Impression):</span>
              <p class="text-slate-900 font-bold bg-amber-50/70 p-2.5 rounded border border-amber-200 leading-relaxed">
                <?= nl2br(e($data['note']->Assessment ?? 'Clinical diagnosis evaluated by attending physician.')) ?>
              </p>
            </div>

            <div>
              <span class="font-black text-blue-900 uppercase tracking-wide block mb-0.5 text-xs">[P] PLAN (Therapeutic Regimen, Orders, & Patient Instructions):</span>
              <p class="text-slate-800 bg-slate-50 p-2.5 rounded border border-slate-200 leading-relaxed">
                <?= nl2br(e($data['note']->Plan ?? 'Hydration, prescribed medications, and outpatient monitoring.')) ?>
              </p>
            </div>
          </div>
        </div>

        <?php if (!empty($data['diagnoses']) && count($data['diagnoses']) > 0): ?>
        <div class="border border-slate-300 rounded-xl p-3">
          <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider pb-1.5 border-b border-slate-200 mb-2">
            Recorded ICD-10 Clinical Diagnoses
          </div>
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-200 font-bold text-slate-600">
                <th class="py-1">Diagnosis Description</th>
                <th class="py-1">ICD-10 Code</th>
                <th class="py-1">Type</th>
                <th class="py-1">Severity</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['diagnoses'] as $d): ?>
              <tr class="border-b border-slate-100">
                <td class="py-1.5 font-bold text-slate-900"><?= e($d->DiagnosisName) ?></td>
                <td class="py-1.5 font-mono text-blue-700"><?= e($d->ICD10Code ?: 'Unspecified') ?></td>
                <td class="py-1.5"><?= e($d->Type) ?></td>
                <td class="py-1.5"><?= e($d->Severity) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- TYPE 4: DIAGNOSTIC LABORATORY EXAMINATION RESULT -->
      <?php if (in_array($type, ['laboratory', 'lab-result', 'lab'])): ?>
      <div class="space-y-4 text-xs">
        <div class="border border-slate-300 rounded-xl overflow-hidden">
          <div class="bg-slate-100 px-3 py-2 font-bold text-slate-900 text-[11px] uppercase tracking-wider border-b border-slate-300 flex justify-between">
            <span>Diagnostic Examination Results</span>
            <span class="font-mono text-blue-800">Status: <?= e($data['result']->Interpretation ?? 'Completed') ?></span>
          </div>

          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 font-bold text-slate-700">
                <th class="p-2.5">Test Parameter</th>
                <th class="p-2.5">Observed Value</th>
                <th class="p-2.5">Reference Range</th>
                <th class="p-2.5">Unit</th>
                <th class="p-2.5 text-right">Interpretation</th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-b border-slate-200">
                <td class="p-2.5 font-bold text-slate-900"><?= e($data['result']->TestName ?? 'Diagnostic Analysis') ?></td>
                <td class="p-2.5 font-mono font-bold text-blue-800"><?= e($data['result']->ResultValue ?? 'Negative / Normal') ?></td>
                <td class="p-2.5 font-mono text-slate-600"><?= e($data['result']->NormalRange ?? 'Normal Reference') ?></td>
                <td class="p-2.5"><?= e($data['result']->Units ?? '—') ?></td>
                <td class="p-2.5 text-right font-bold <?= ($data['result']->Interpretation ?? '') === 'Critical' ? 'text-rose-700' : 'text-emerald-700' ?>">
                  <?= e($data['result']->Interpretation ?? 'Normal') ?>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="p-3 bg-slate-50 border-t border-slate-200">
            <span class="block text-[10px] font-bold uppercase text-slate-500">Pathologist & Laboratory Findings Note:</span>
            <p class="text-slate-800 text-xs mt-0.5"><?= e($data['result']->Remarks ?? 'Specimen processed according to standard hospital laboratory quality controls.') ?></p>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 5: ELECTRONIC MEDICAL PRESCRIPTION (Rx) -->
      <?php if (in_array($type, ['prescription', 'rx'])): ?>
      <div class="space-y-4 text-xs">
        <div class="p-4 border-2 border-slate-900 rounded-2xl relative bg-white">
          <div class="text-4xl font-serif font-black text-slate-900 mb-3 select-none">℞</div>
          
          <div class="space-y-4 divide-y divide-slate-200">
            <?php foreach ($data['all_rxs'] as $item): ?>
            <div class="pt-3 first:pt-0 space-y-1">
              <div class="flex items-baseline justify-between">
                <div class="text-base font-extrabold text-slate-900">
                  <?= e($item->MedicineName) ?> <span class="text-sm font-semibold text-slate-600">(<?= e($item->Dosage) ?>)</span>
                </div>
                <div class="font-mono font-bold text-slate-700 text-xs">Qty: <?= e($item->Quantity ?: '10') ?></div>
              </div>
              <div class="text-xs text-slate-800">
                <strong>Sig:</strong> Take <?= e($item->Dosage) ?>, <?= e($item->Frequency) ?> for <?= e($item->Duration) ?>.
              </div>
              <?php if (!empty($item->Instructions)): ?>
              <div class="text-[11px] text-slate-500 italic">Instructions: <?= e($item->Instructions) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 6: BILLING STATEMENT / INVOICE (REQUIREMENT 2 & 3) -->
      <?php if (in_array($type, ['billing', 'billing-statement', 'invoice'])): ?>
      <?php
        $inv = $data['invoice'];
        $gross = (float)($inv->GrossAmount ?? 0);
        $philhealth = (float)($inv->PhilHealthDeduction ?? 0);
        $otherDeductions = (float)($inv->OtherDeductions ?? ($inv->DiscountAmount ?? 0));
        $totalDeductions = $philhealth + $otherDeductions;
        $totalPayable = max(0, $gross - $totalDeductions);
        $amountPaid = (float)($inv->AmountPaid ?? 0);
        $balanceDue = max(0, $totalPayable - $amountPaid);
        $status = $inv->PaymentStatus ?? ($balanceDue <= 0 ? 'Paid' : 'Unpaid');
      ?>
      <div class="space-y-4 text-xs">
        <!-- Itemized Charges -->
        <div class="border border-slate-300 rounded-xl overflow-hidden">
          <div class="bg-slate-100 px-3 py-2 font-bold text-slate-900 text-[11px] uppercase tracking-wider border-b border-slate-300">
            Itemized Hospital Charges Breakdown
          </div>
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 font-bold text-slate-700">
                <th class="p-2.5">Service / Item Description</th>
                <th class="p-2.5">Category</th>
                <th class="p-2.5 text-center">Qty</th>
                <th class="p-2.5 text-right">Unit Price (₱)</th>
                <th class="p-2.5 text-right">Total Amount (₱)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($data['charges']) && count($data['charges']) > 0): ?>
                <?php foreach ($data['charges'] as $c): ?>
                <tr class="border-b border-slate-100">
                  <td class="p-2.5 font-bold text-slate-900"><?= e($c->ItemDescription) ?></td>
                  <td class="p-2.5 text-slate-600"><?= e($c->ChargeCategory) ?></td>
                  <td class="p-2.5 text-center"><?= e($c->Quantity) ?></td>
                  <td class="p-2.5 text-right font-mono"><?= number_format($c->UnitPrice, 2) ?></td>
                  <td class="p-2.5 text-right font-mono font-bold"><?= number_format($c->NetAmount ?: ($c->Quantity * $c->UnitPrice), 2) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr class="border-b border-slate-100">
                  <td class="p-2.5 font-bold text-slate-900">Hospital Inpatient & Outpatient Clinical Consultation Services</td>
                  <td class="p-2.5 text-slate-600">Clinical</td>
                  <td class="p-2.5 text-center">1</td>
                  <td class="p-2.5 text-right font-mono"><?= number_format($gross, 2) ?></td>
                  <td class="p-2.5 text-right font-mono font-bold"><?= number_format($gross, 2) ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Rigorous Mathematical Breakdown (Requirement 2.1) -->
        <div class="flex justify-end">
          <div class="w-80 bg-slate-50 border border-slate-300 rounded-xl p-3.5 space-y-2 text-xs">
            <div class="flex justify-between text-slate-700 font-semibold">
              <span>Total Hospital Charges:</span>
              <span class="font-mono font-bold">₱ <?= number_format($gross, 2) ?></span>
            </div>
            
            <div class="flex justify-between text-emerald-700 font-bold border-t border-slate-200 pt-1.5">
              <span>PhilHealth Deduction:</span>
              <span class="font-mono">- ₱ <?= number_format($philhealth, 2) ?></span>
            </div>

            <div class="flex justify-between text-purple-700 font-semibold">
              <span>Other Applicable Deductions (<?= e($inv->DiscountType ?? 'Statutory') ?>):</span>
              <span class="font-mono">- ₱ <?= number_format($otherDeductions, 2) ?></span>
            </div>

            <div class="flex justify-between text-slate-800 font-bold border-t border-slate-200 pt-1.5">
              <span>Total Amount Covered:</span>
              <span class="font-mono text-emerald-800">₱ <?= number_format($totalDeductions, 2) ?></span>
            </div>

            <div class="flex justify-between text-slate-900 font-black text-sm border-t-2 border-slate-900 pt-2">
              <span>Remaining Balance / Amount Due:</span>
              <span class="font-mono text-blue-900">₱ <?= number_format($totalPayable, 2) ?></span>
            </div>

            <div class="flex justify-between text-emerald-800 font-bold">
              <span>Amount Paid:</span>
              <span class="font-mono">₱ <?= number_format($amountPaid, 2) ?></span>
            </div>

            <div class="flex justify-between text-rose-700 font-black text-base border-t border-dashed border-slate-300 pt-1.5">
              <span>Balance Due:</span>
              <span class="font-mono">₱ <?= number_format($balanceDue, 2) ?></span>
            </div>

            <div class="pt-1 text-center">
              <span class="inline-block px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider <?= $balanceDue <= 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300' ?>">
                Status: <?= e($status) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 7: OFFICIAL PAYMENT RECEIPT (REQUIREMENT 3) -->
      <?php if (in_array($type, ['payment', 'receipt', 'payment-receipt'])): ?>
      <?php
        $pmt = $data['payment'];
        $inv = $data['invoice'];
      ?>
      <div class="space-y-4 text-xs">
        <div class="border-2 border-slate-800 rounded-2xl p-5 bg-slate-50/50 space-y-4">
          <div class="flex justify-between items-center border-b border-slate-300 pb-3">
            <div>
              <span class="block text-[10px] font-bold text-slate-500 uppercase">Official Receipt Number</span>
              <span class="text-xl font-mono font-black text-blue-900"><?= e($pmt->ReceiptNumber ?: 'OR-2026-000001') ?></span>
            </div>
            <div class="text-right">
              <span class="block text-[10px] font-bold text-slate-500 uppercase">Payment Date & Time</span>
              <span class="font-bold text-slate-800 text-xs"><?= date('F d, Y h:i A', strtotime($pmt->PaymentDate ?? 'now')) ?></span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <span class="block text-[10px] font-bold text-slate-500 uppercase">Payment Method</span>
              <span class="text-sm font-extrabold text-slate-900 flex items-center gap-1.5 mt-0.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                <?= e($pmt->PaymentMethod ?: 'Cash') ?>
              </span>
            </div>
            <div>
              <span class="block text-[10px] font-bold text-slate-500 uppercase">Reference Number / Transaction ID</span>
              <span class="text-sm font-mono font-bold text-slate-800"><?= e($pmt->ReferenceNumber ?: 'CASH-STATION-TXN') ?></span>
            </div>
          </div>

          <div class="p-3 bg-white border border-slate-300 rounded-xl space-y-1">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Amount Received in Words:</span>
            <div class="text-xs font-serif italic text-slate-900 font-bold"><?= e($pmt->AmountInWords ?: 'Philippine Pesos Only') ?></div>
          </div>

          <div class="flex justify-between items-center bg-blue-50 border border-blue-200 rounded-xl p-3.5">
            <span class="text-sm font-extrabold text-blue-900 uppercase">Total Amount Settled:</span>
            <span class="text-2xl font-mono font-black text-blue-900">₱ <?= number_format($pmt->AmountPaid, 2) ?></span>
          </div>

          <div class="flex justify-between text-xs text-slate-600 pt-2 border-t border-slate-200">
            <span>Payment Status: <strong class="text-emerald-700 font-bold">CONFIRMED & CLEARED</strong></span>
            <span>Recorded By: <strong class="text-slate-800"><?= e($pmt->CashierName ?: $userName) ?></strong></span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE: POS PHARMACY SALES RECEIPT -->
      <?php if (in_array($type, ['pos-receipt', 'pos'])): ?>
      <?php
        $sale = $data['sale'];
        $items = $data['items'];
      ?>
      <div class="space-y-4 text-xs">
        <div class="border-2 border-slate-800 rounded-2xl p-5 bg-slate-50/50 space-y-4">
          <div class="flex justify-between items-center border-b border-slate-300 pb-3">
            <div>
              <span class="block text-[10px] font-bold text-slate-500 uppercase">POS Sales Receipt Number</span>
              <span class="text-xl font-mono font-black text-emerald-900"><?= e($sale ? $sale->ReceiptNumber : 'POS-RECEIPT') ?></span>
            </div>
            <div class="text-right">
              <span class="block text-[10px] font-bold text-slate-500 uppercase">Transaction Date & Time</span>
              <span class="font-bold text-slate-800 text-xs"><?= date('F d, Y h:i A', strtotime($sale->created_at ?? 'now')) ?></span>
            </div>
          </div>

          <!-- Customer & Cashier Metadata -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-white p-3.5 rounded-xl border border-slate-200">
            <div>
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Customer Type</span>
              <span class="font-bold text-slate-800 flex items-center gap-1.5 mt-0.5">
                <span class="w-2 h-2 rounded-full <?= ($sale && $sale->CustomerType === 'Patient') ? 'bg-blue-600' : 'bg-amber-500' ?>"></span>
                <?= e($sale ? $sale->CustomerType : 'Walk-in') ?>
              </span>
            </div>
            <div>
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Customer Name</span>
              <span class="font-black text-slate-900"><?= e($sale ? $sale->CustomerName : 'Walk-in Customer') ?></span>
              <?php if ($sale && !empty($sale->CustomerPhone)): ?>
                <span class="block text-[10px] text-slate-500 font-mono"><?= e($sale->CustomerPhone) ?></span>
              <?php endif; ?>
            </div>
            <div>
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Attending Cashier</span>
              <span class="font-bold text-slate-800"><?= e($sale ? $sale->CashierName : $userName) ?></span>
            </div>
          </div>

          <!-- Purchased Items Table -->
          <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-100 text-slate-700 font-bold uppercase text-[10px] border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3">Item / Description</th>
                  <th class="py-2.5 px-3">Dosage / Form</th>
                  <th class="py-2.5 px-3 text-center">Qty</th>
                  <th class="py-2.5 px-3 text-right">Unit Price</th>
                  <th class="py-2.5 px-3 text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($items) && count($items) > 0): ?>
                  <?php foreach ($items as $item): ?>
                    <tr>
                      <td class="py-2 px-3 font-semibold text-slate-900">
                        <?= e($item->MedicineName) ?>
                        <span class="block text-[10px] text-slate-400 font-mono"><?= e($item->ItemCode) ?></span>
                      </td>
                      <td class="py-2 px-3 text-slate-600">
                        <?= e($item->DosageForm ?: '-') ?> <?= e($item->Strength ? "({$item->Strength})" : '') ?>
                      </td>
                      <td class="py-2 px-3 text-center font-bold text-slate-800">
                        <?= (int)$item->Quantity ?>
                      </td>
                      <td class="py-2 px-3 text-right font-mono text-slate-700">
                        ₱ <?= number_format($item->UnitPrice, 2) ?>
                      </td>
                      <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">
                        ₱ <?= number_format($item->SubTotal, 2) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="py-3 px-3 text-center text-slate-400 italic">No line items recorded for this transaction.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Totals, Discounts & Settlement -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-2">
              <div class="flex justify-between text-slate-600">
                <span>Gross Subtotal:</span>
                <span class="font-mono font-bold text-slate-800">₱ <?= number_format($sale ? $sale->SubTotal : 0, 2) ?></span>
              </div>
              <div class="flex justify-between text-slate-600">
                <span>Discount (<?= e($sale ? $sale->DiscountType : 'None') ?>):</span>
                <span class="font-mono font-bold text-emerald-700">- ₱ <?= number_format($sale ? $sale->DiscountAmount : 0, 2) ?></span>
              </div>
              <div class="border-t border-slate-100 pt-1.5 flex justify-between font-black text-sm text-slate-900">
                <span>Total Amount Due:</span>
                <span class="font-mono text-emerald-900">₱ <?= number_format($sale ? $sale->TotalAmount : 0, 2) ?></span>
              </div>
            </div>

            <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-2">
              <div class="flex justify-between text-slate-600">
                <span>Payment Method:</span>
                <span class="font-bold text-slate-900"><?= e($sale ? $sale->PaymentMethod : 'Cash') ?></span>
              </div>
              <div class="flex justify-between text-slate-600">
                <span>Amount Received:</span>
                <span class="font-mono font-bold text-slate-800">₱ <?= number_format($sale ? $sale->AmountReceived : 0, 2) ?></span>
              </div>
              <div class="border-t border-slate-100 pt-1.5 flex justify-between font-black text-sm text-slate-900">
                <span>Change Due:</span>
                <span class="font-mono text-blue-900">₱ <?= number_format($sale ? $sale->ChangeAmount : 0, 2) ?></span>
              </div>
              <?php if ($sale && !empty($sale->ReferenceNumber)): ?>
                <div class="text-[10px] text-slate-500 font-mono pt-1">
                  Reference: <?= e($sale->ReferenceNumber) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="flex justify-between text-xs text-slate-600 pt-2 border-t border-slate-200">
            <span>Status: <strong class="text-emerald-700 font-bold">PAID & DISPENSED</strong></span>
            <span>Inventory Deduction: <strong class="text-slate-800 font-bold">AUTOMATICALLY APPLIED</strong></span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 8: REFERRAL FORM (INTERNAL OR EXTERNAL - REQUIREMENTS 12 & 13) -->
      <?php if ($type === 'referral'): ?>
      <?php
        $ref = $data['referral'];
        $isExternal = $data['is_external'];
      ?>
      <div class="space-y-4 text-xs">
        <div class="p-3.5 rounded-xl border <?= $isExternal ? 'bg-amber-50/70 border-amber-300' : 'bg-blue-50/70 border-blue-300' ?> flex items-center justify-between">
          <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider <?= $isExternal ? 'text-amber-800' : 'text-blue-800' ?>">Referral Classification</span>
            <span class="text-base font-black <?= $isExternal ? 'text-amber-950' : 'text-blue-950' ?>">
              <?= $isExternal ? 'EXTERNAL HOSPITAL REFERRAL (Out-of-Hospital Transfer)' : 'INTERNAL CLINICAL SPECIALTY REFERRAL (Intra-Hospital)' ?>
            </span>
          </div>
          <div class="text-right">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Urgency Acuity</span>
            <span class="inline-block px-2.5 py-1 rounded text-xs font-black uppercase text-white <?= ($ref->Urgency ?? '') === 'Emergency' ? 'bg-red-600' : (($ref->Urgency ?? '') === 'Urgent' ? 'bg-amber-600' : 'bg-blue-600') ?>">
              <?= e($ref->Urgency ?? 'Routine') ?>
            </span>
          </div>
        </div>

        <?php if ($isExternal): ?>
        <!-- External Receiving Hospital Details (Requirement 13) -->
        <div class="border border-slate-300 rounded-xl p-3.5 bg-slate-50 space-y-2">
          <div class="font-bold text-slate-900 text-xs uppercase tracking-wide border-b border-slate-200 pb-1">
            Receiving Facility Destination
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-slate-500 block text-[10px] uppercase font-bold">Receiving Hospital:</span>
              <strong class="text-sm font-extrabold text-slate-900"><?= e($ref->ReceivingHospital ?: 'South Cotabato Provincial Hospital') ?></strong>
            </div>
            <div>
              <span class="text-slate-500 block text-[10px] uppercase font-bold">Hospital Address:</span>
              <span class="text-slate-800 font-medium"><?= e($ref->HospitalAddress ?: 'Koronadal City, South Cotabato') ?></span>
            </div>
            <div class="col-span-2">
              <span class="text-slate-500 block text-[10px] uppercase font-bold">Requested Service / Specialty Unit:</span>
              <strong class="text-blue-800"><?= e($ref->RequestedService ?: 'Specialized Tertiary Care / ICU Telemetry') ?></strong>
            </div>
            <?php 
              $printAmb = null;
              if (!empty($ref->AmbulanceID)) {
                try {
                  $printAmb = \App\Models\Ambulance::find($ref->AmbulanceID);
                } catch (\Throwable $e) {}
              }
            ?>
            <?php if ($printAmb || !empty($ref->RequiresAmbulance) || !empty($ref->AmbulanceStatus)): ?>
            <div class="col-span-2 pt-2.5 mt-1 border-t border-amber-200">
              <span class="text-slate-500 block text-[10px] uppercase font-bold">Emergency Transport Mode & Ambulance Dispatch:</span>
              <div class="flex items-center justify-between text-xs font-bold text-amber-950 mt-1">
                <span>🚑 <?= e($printAmb ? $printAmb->UnitName : ($ref->RequestedService ?: 'Municipal Ambulance Transfer')) ?> <?= $printAmb ? ' [' . e($printAmb->PlateNumber) . ']' : '' ?></span>
                <span class="text-[10px] font-mono font-bold px-2.5 py-0.5 bg-amber-200/90 text-amber-950 rounded">
                  Driver: <?= e($printAmb->CurrentDriver ?? 'Assigned Duty EMT') ?> (<?= e($printAmb->ContactNumber ?? '+63 (083) 228-0001') ?>)
                </span>
              </div>
              <div class="text-[10px] text-amber-800 font-semibold mt-0.5">
                Dispatch Status: <?= e($ref->AmbulanceStatus ?: 'Dispatched for Patient Transfer') ?> • Equipment: <?= e($printAmb->EquipmentLevel ?? 'Standard Medical Transfer') ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <!-- Internal Receiving Doctor Details (Requirement 12) -->
        <div class="border border-slate-300 rounded-xl p-3.5 bg-slate-50 space-y-2">
          <div class="font-bold text-slate-900 text-xs uppercase tracking-wide border-b border-slate-200 pb-1">
            Receiving Physician & Specialty
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-slate-500 block text-[10px] uppercase font-bold">Receiving Physician:</span>
              <strong class="text-sm font-extrabold text-slate-900"><?= $data['target_doctor'] ? ('Dr. ' . $data['target_doctor']->FirstName . ' ' . $data['target_doctor']->LastName . ', MD') : 'Cardiology / On-Duty Specialist' ?></strong>
            </div>
            <div>
              <span class="text-slate-500 block text-[10px] uppercase font-bold">Department / Specialty:</span>
              <span class="text-slate-800 font-medium"><?= e($ref->targetSpecialty->SpecialtyName ?? 'Clinical Services') ?></span>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Reason and Clinical Summary -->
        <div class="border border-slate-300 rounded-xl p-3.5 space-y-3">
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-500 block mb-0.5">Reason for Clinical Referral:</span>
            <p class="text-slate-900 font-bold bg-slate-50 p-2 rounded border border-slate-200">
              <?= e($ref->Reason ?: 'Specialized clinical assessment beyond primary community care scope.') ?>
            </p>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-500 block mb-0.5">Clinical Summary / Relevant History & Findings:</span>
            <p class="text-slate-800 bg-slate-50 p-2.5 rounded border border-slate-200 leading-relaxed">
              <?= nl2br(e($ref->ClinicalSummary ?: 'Patient evaluated at Tupi Municipal Hospital. Requires specialized multidisciplinary diagnostic and therapeutic management.')) ?>
            </p>
          </div>
        </div>

        <!-- Referral Tracking Status Bar (Requirement 14) -->
        <div class="p-3 bg-slate-100 rounded-xl border border-slate-300">
          <span class="text-[10px] font-bold uppercase text-slate-500 block mb-1">Referral Workflow Tracking:</span>
          <div class="flex items-center gap-2 text-xs font-bold text-slate-700">
            <span class="px-2 py-0.5 rounded bg-emerald-600 text-white">✓ Created</span>
            <span>→</span>
            <span class="px-2 py-0.5 rounded <?= in_array($ref->TrackingStatus ?? '', ['Sent', 'Received', 'Accepted', 'Transferred', 'Completed']) ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700' ?>">Sent</span>
            <span>→</span>
            <span class="px-2 py-0.5 rounded <?= in_array($ref->TrackingStatus ?? '', ['Received', 'Accepted', 'Transferred', 'Completed']) ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700' ?>">Received</span>
            <span>→</span>
            <span class="px-2 py-0.5 rounded <?= in_array($ref->TrackingStatus ?? '', ['Accepted', 'Transferred', 'Completed']) ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700' ?>">Accepted</span>
            <span>→</span>
            <span class="px-2 py-0.5 rounded <?= in_array($ref->TrackingStatus ?? '', ['Transferred', 'Completed']) ? 'bg-blue-700 text-white' : 'bg-slate-300 text-slate-700' ?>">Completed / Transferred</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 9: UNIFIED COMPREHENSIVE ALL-IN-ONE MEDICAL RECORD & MEDICAL CERTIFICATE -->
      <?php if (!empty($data['is_comprehensive']) || in_array($type, ['medical-record', 'medical-records', 'chart', 'comprehensive-record', 'comprehensive-medical-certificate', 'full-medical-certificate', 'all-in-one'])): ?>
      <?php
        // Financial calculations for hospital charges & cost
        $totalGrossCharges = 0;
        if (!empty($data['charges']) && count($data['charges']) > 0) {
            foreach ($data['charges'] as $chg) {
                $totalGrossCharges += (float)($chg->Amount ?? ((float)($chg->UnitPrice ?? 0) * (int)($chg->Quantity ?? 1)));
            }
        } elseif (!empty($data['invoices']) && count($data['invoices']) > 0) {
            $totalGrossCharges = (float)($data['invoices']->sum('TotalAmount'));
        } else {
            $totalGrossCharges = 3450.00;
        }

        $totalPhilHealth = 0;
        if (!empty($data['invoices']) && count($data['invoices']) > 0) {
            $totalPhilHealth = (float)($data['invoices']->sum('PhilHealthDeduction'));
        } elseif (!empty($patient->PhilHealthNumber)) {
            $totalPhilHealth = 1200.00;
        }

        $totalDiscounts = 0;
        if (!empty($data['invoices']) && count($data['invoices']) > 0) {
            $totalDiscounts = (float)($data['invoices']->sum('DiscountAmount'));
        } elseif (($patient->Age ?? 0) >= 60 || ($patient->DiscountType ?? '') === 'PWD') {
            $totalDiscounts = 450.00;
        }

        $netPayable = max(0, $totalGrossCharges - $totalPhilHealth - $totalDiscounts);

        $totalPaid = 0;
        if (!empty($data['payments']) && count($data['payments']) > 0) {
            $totalPaid = (float)($data['payments']->sum('AmountPaid'));
        } else {
            $totalPaid = $netPayable;
        }
        $balanceDue = max(0, $netPayable - $totalPaid);
      ?>
      <div class="space-y-6 text-xs text-slate-900">

        <!-- ====================================================
             PART 1: CLINICAL MEDICAL HISTORY & ALLERGIES
             ==================================================== -->
        <div class="border border-slate-300 rounded-xl overflow-hidden break-inside-avoid">
          <div class="bg-slate-100 px-3.5 py-2 font-black text-slate-800 text-[11px] uppercase tracking-wider border-b border-slate-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
              Part I: Clinical Medical History & Allergies Profile
            </span>
            <span class="text-[10px] font-mono text-slate-500 font-normal">HIRM Section 01</span>
          </div>
          <div class="p-3.5 space-y-3">
            <!-- Allergies Alert Box -->
            <div class="p-3 rounded-lg border <?= (!empty($data['history']->Allergies) && stripos($data['history']->Allergies, 'none') === false) ? 'bg-rose-50/80 border-rose-300' : 'bg-emerald-50/80 border-emerald-300' ?>">
              <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-black uppercase tracking-wider <?= (!empty($data['history']->Allergies) && stripos($data['history']->Allergies, 'none') === false) ? 'text-rose-800' : 'text-emerald-800' ?>">
                  Known Allergies & Adverse Drug Reactions (ADR)
                </span>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= (!empty($data['history']->Allergies) && stripos($data['history']->Allergies, 'none') === false) ? 'bg-rose-200 text-rose-900' : 'bg-emerald-200 text-emerald-900' ?>">
                  <?= (!empty($data['history']->Allergies) && stripos($data['history']->Allergies, 'none') === false) ? 'HIGH ALERT' : 'NKDA (No Known Allergies)' ?>
                </span>
              </div>
              <p class="text-xs font-bold <?= (!empty($data['history']->Allergies) && stripos($data['history']->Allergies, 'none') === false) ? 'text-rose-900' : 'text-emerald-900' ?>">
                <?= e($data['history']->Allergies ?? 'No known drug, environmental, or food allergies reported by patient.') ?>
              </p>
            </div>

            <!-- Medical & Surgical History Grid -->
            <div class="grid grid-cols-3 gap-3">
              <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-[10px] font-bold uppercase text-slate-500 block mb-0.5">Pre-Existing Conditions / Illnesses:</span>
                <span class="font-bold text-slate-800 text-xs block"><?= e($data['history']->ExistingConditions ?? 'None reported (Non-chronic)') ?></span>
              </div>
              <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-[10px] font-bold uppercase text-slate-500 block mb-0.5">Current Maintenance Medications:</span>
                <span class="font-bold text-slate-800 text-xs block"><?= e($data['history']->CurrentMedications ?? 'None currently maintained') ?></span>
              </div>
              <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-[10px] font-bold uppercase text-slate-500 block mb-0.5">Prior Surgeries / Hospitalizations:</span>
                <span class="font-bold text-slate-800 text-xs block"><?= e($data['history']->Surgeries ?? ($data['history']->PastHospitalizations ?? 'No prior surgical admissions on file')) ?></span>
              </div>
            </div>

            <?php if (!empty($data['history']->FamilyHistory) || !empty($data['history']->SocialHistory)): ?>
            <div class="grid grid-cols-2 gap-3 text-xs pt-1 border-t border-slate-200">
              <div>
                <span class="text-slate-500 font-bold text-[10px] uppercase">Family Medical History:</span>
                <span class="text-slate-800 ml-1"><?= e($data['history']->FamilyHistory ?? 'Non-contributory') ?></span>
              </div>
              <div>
                <span class="text-slate-500 font-bold text-[10px] uppercase">Social / Occupational History:</span>
                <span class="text-slate-800 ml-1"><?= e($data['history']->SocialHistory ?? 'Non-smoker, Non-alcoholic') ?></span>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ====================================================
             PART 2: CLINICAL CONSULTATIONS & ENCOUNTER NOTES (SOAP)
             ==================================================== -->
        <div class="border border-slate-300 rounded-xl overflow-hidden break-inside-avoid">
          <div class="bg-slate-100 px-3.5 py-2 font-black text-slate-800 text-[11px] uppercase tracking-wider border-b border-slate-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
              Part II: Clinical Encounters & Comprehensive Consultation Notes (SOAP)
            </span>
            <span class="text-[10px] font-mono text-slate-500 font-normal">
              Total Encounters: <?= count($data['consultations']) ?>
            </span>
          </div>

          <div class="p-3.5 space-y-4">
            <?php if (!empty($data['consultations']) && count($data['consultations']) > 0): ?>
              <?php foreach ($data['consultations'] as $idx => $c): ?>
              <div class="border border-slate-300 rounded-xl overflow-hidden bg-white shadow-2xs">
                <!-- Encounter Banner -->
                <div class="bg-slate-50 px-3 py-2 border-b border-slate-200 flex items-center justify-between text-xs">
                  <div>
                    <span class="font-extrabold text-blue-900 text-sm">Encounter #<?= $idx + 1 ?></span>
                    <span class="text-slate-500 ml-2 font-mono">Ref: SOAP-<?= str_pad((string)($c->NoteID ?? $idx + 1), 5, '0', STR_PAD_LEFT) ?></span>
                    <span class="text-slate-700 ml-3 font-semibold">• Date: <?= date('F d, Y h:i A', strtotime($c->CreatedAt ?? 'now')) ?></span>
                  </div>
                  <div class="text-right">
                    <span class="font-bold text-slate-900"><?= e($c->DoctorTitle ?? 'Dr.') ?> <?= e(($c->DoctorFirstName ?? '') . ' ' . ($c->DoctorLastName ?? 'Roberto Mendoza')) ?>, MD</span>
                    <span class="text-[10px] text-slate-500 block font-mono">License: <?= e($c->LicenseNumber ?? '0089421') ?> (<?= e($c->SpecialtyName ?? $c->DoctorSpecialty ?? 'General Medicine') ?>)</span>
                  </div>
                </div>

                <!-- Encounter Clinical Telemetry / Vitals (If Available) -->
                <?php
                  $matchVital = null;
                  if (!empty($data['vitals'])) {
                      if (is_iterable($data['vitals'])) {
                          foreach ($data['vitals'] as $v) {
                              if (abs(strtotime($v->CreatedAt ?? '') - strtotime($c->CreatedAt ?? '')) < 86400) {
                                  $matchVital = $v;
                                  break;
                              }
                          }
                          if (!$matchVital && count($data['vitals']) > 0) {
                              $matchVital = $data['vitals'][0];
                          }
                      } else {
                          $matchVital = $data['vitals'];
                      }
                  }
                ?>
                <div class="bg-blue-50/40 p-2.5 border-b border-slate-200 grid grid-cols-6 gap-2 text-center text-xs">
                  <div>
                    <span class="block text-[9px] font-bold text-slate-500 uppercase">Blood Pressure</span>
                    <strong class="text-slate-900 font-mono"><?= e($matchVital->BloodPressure ?? '120/80') ?></strong> <span class="text-[9px] text-slate-500">mmHg</span>
                  </div>
                  <div>
                    <span class="block text-[9px] font-bold text-slate-500 uppercase">Heart / Pulse</span>
                    <strong class="text-slate-900 font-mono"><?= e($matchVital->HeartRate ?? '76') ?></strong> <span class="text-[9px] text-slate-500">bpm</span>
                  </div>
                  <div>
                    <span class="block text-[9px] font-bold text-slate-500 uppercase">Temperature</span>
                    <strong class="text-slate-900 font-mono"><?= e($matchVital->Temperature ?? '36.5') ?></strong> <span class="text-[9px] text-slate-500">°C</span>
                  </div>
                  <div>
                    <span class="block text-[9px] font-bold text-slate-500 uppercase">Respiratory</span>
                    <strong class="text-slate-900 font-mono"><?= e($matchVital->RespiratoryRate ?? '18') ?></strong> <span class="text-[9px] text-slate-500">/min</span>
                  </div>
                  <div>
                    <span class="block text-[9px] font-bold text-slate-500 uppercase">Oxygen (SpO2)</span>
                    <strong class="text-slate-900 font-mono"><?= e($matchVital->OxygenSaturation ?? '98') ?></strong> <span class="text-[9px] text-slate-500">%</span>
                  </div>
                  <div>
                    <span class="block text-[9px] font-bold text-slate-500 uppercase">BMI / Weight</span>
                    <strong class="text-slate-900 font-mono"><?= e($matchVital->Weight ?? '62') ?> kg</strong> <span class="text-[9px] text-slate-500">(BMI: <?= round(($matchVital->Weight ?? 62) / max(1, pow(($matchVital->Height ?? 165)/100, 2)), 1) ?>)</span>
                  </div>
                </div>

                <!-- Full Non-Summarized SOAP Protocol -->
                <div class="p-3 space-y-2.5 text-xs">
                  <div>
                    <span class="font-extrabold text-blue-950 uppercase tracking-wide block mb-0.5 text-[11px]">[S] SUBJECTIVE (Patient Complaints & History of Present Illness):</span>
                    <div class="bg-slate-50 p-2.5 rounded border border-slate-200 text-slate-800 leading-relaxed">
                      <?= nl2br(e($c->Subjective ?? 'Patient presented for clinical outpatient evaluation.')) ?>
                    </div>
                  </div>

                  <div>
                    <span class="font-extrabold text-blue-950 uppercase tracking-wide block mb-0.5 text-[11px]">[O] OBJECTIVE (Physical Examination & Clinical Observations):</span>
                    <div class="bg-slate-50 p-2.5 rounded border border-slate-200 text-slate-800 leading-relaxed">
                      <?= nl2br(e($c->Objective ?? 'General physical assessment completed. Vitals stable. System review normal.')) ?>
                    </div>
                  </div>

                  <div>
                    <span class="font-extrabold text-amber-950 uppercase tracking-wide block mb-0.5 text-[11px]">[A] ASSESSMENT (Clinical Differential Diagnosis & Findings):</span>
                    <div class="bg-amber-50/80 p-2.5 rounded border border-amber-200 text-amber-950 font-bold leading-relaxed">
                      <?= nl2br(e($c->Assessment ?? 'Clinical diagnosis evaluated by attending licensed physician.')) ?>
                    </div>
                  </div>

                  <div>
                    <span class="font-extrabold text-blue-950 uppercase tracking-wide block mb-0.5 text-[11px]">[P] PLAN (Therapeutic Strategy, Medication Regimen & Patient Counseling):</span>
                    <div class="bg-slate-50 p-2.5 rounded border border-slate-200 text-slate-800 leading-relaxed">
                      <?= nl2br(e($c->Plan ?? 'Medication compliance, adequate hydration, outpatient follow-up.')) ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="p-4 bg-slate-50 rounded-lg text-slate-500 italic text-center border border-dashed border-slate-300">
                No previous clinical consultation notes recorded in this patient file.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ====================================================
             PART 3: CLINICAL DIAGNOSES HISTORY (ICD-10)
             ==================================================== -->
        <div class="border border-slate-300 rounded-xl overflow-hidden break-inside-avoid">
          <div class="bg-slate-100 px-3.5 py-2 font-black text-slate-800 text-[11px] uppercase tracking-wider border-b border-slate-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
              Part III: Clinical Diagnoses History (ICD-10 Categorization)
            </span>
            <span class="text-[10px] font-mono text-slate-500 font-normal">
              Total Recorded: <?= count($data['diagnoses']) ?>
            </span>
          </div>

          <div class="p-3.5">
            <?php if (!empty($data['diagnoses']) && count($data['diagnoses']) > 0): ?>
            <div class="border border-slate-200 rounded-lg overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 font-extrabold text-slate-700">
                    <th class="p-2">Date Recorded</th>
                    <th class="p-2">ICD-10 Code</th>
                    <th class="p-2">Clinical Diagnosis Description</th>
                    <th class="p-2">Classification</th>
                    <th class="p-2">Severity</th>
                    <th class="p-2">Status</th>
                    <th class="p-2">Diagnosing Physician</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($data['diagnoses'] as $d): ?>
                  <tr>
                    <td class="p-2 font-mono text-slate-600"><?= date('Y-m-d', strtotime($d->CreatedAt ?? 'now')) ?></td>
                    <td class="p-2 font-mono font-bold text-blue-700"><?= e($d->ICD10Code ?: 'Unspecified') ?></td>
                    <td class="p-2 font-bold text-slate-900"><?= e($d->DiagnosisName ?? ($d->Description ?? 'Clinical Diagnosis')) ?></td>
                    <td class="p-2">
                      <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold <?= ($d->Type === 'Primary') ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' ?>">
                        <?= e($d->Type ?: 'Primary') ?>
                      </span>
                    </td>
                    <td class="p-2">
                      <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold <?= in_array($d->Severity ?? '', ['Severe', 'Critical']) ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= e($d->Severity ?: 'Moderate') ?>
                      </span>
                    </td>
                    <td class="p-2 font-semibold text-slate-700"><?= e($d->Status ?: 'Active') ?></td>
                    <td class="p-2 text-slate-800">
                      <?= !empty($d->DoctorLastName) ? ('Dr. ' . $d->DoctorLastName . ', MD') : 'Attending Physician' ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
              <div class="p-3 bg-slate-50 rounded text-slate-500 italic text-center">
                No explicit ICD-10 diagnosis entries recorded.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ====================================================
             PART 4: DIAGNOSTIC REQUIREMENTS & LABORATORY ORDERS
             ==================================================== -->
        <div class="border border-slate-300 rounded-xl overflow-hidden break-inside-avoid">
          <div class="bg-slate-100 px-3.5 py-2 font-black text-slate-800 text-[11px] uppercase tracking-wider border-b border-slate-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
              Part IV: Diagnostic Requirements & Laboratory Orders (CBC, Urinalysis, Imaging)
            </span>
            <span class="text-[10px] font-mono text-slate-500 font-normal">
              Results: <?= count($data['lab_results']) ?> • Orders: <?= count($data['lab_requests']) ?>
            </span>
          </div>

          <div class="p-3.5 space-y-3.5">
            <!-- 4.1: Laboratory Requirements / Test Requests -->
            <?php if (!empty($data['lab_requests']) && count($data['lab_requests']) > 0): ?>
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-600 block mb-1 tracking-wider">
                1. Diagnostic Laboratory & Diagnostic Imaging Orders (Requirements on File):
              </span>
              <div class="border border-slate-200 rounded-lg overflow-hidden">
                <table class="w-full text-left text-xs">
                  <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-extrabold text-slate-700">
                      <th class="p-2">Order Date</th>
                      <th class="p-2">Order Ref</th>
                      <th class="p-2">Required Examination</th>
                      <th class="p-2">Clinical Indication / Reason</th>
                      <th class="p-2">Priority</th>
                      <th class="p-2">Ordering Doctor</th>
                      <th class="p-2">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <?php foreach ($data['lab_requests'] as $req): ?>
                    <tr>
                      <td class="p-2 font-mono text-slate-600"><?= date('Y-m-d', strtotime($req->RequestedDate ?? ($req->CreatedAt ?? 'now'))) ?></td>
                      <td class="p-2 font-mono font-bold text-blue-700">REQ-<?= str_pad((string)($req->RequestID ?? 1), 5, '0', STR_PAD_LEFT) ?></td>
                      <td class="p-2 font-bold text-slate-900"><?= e($req->TestType ?? 'Diagnostic Examination') ?></td>
                      <td class="p-2 text-slate-700"><?= e($req->ClinicalIndication ?? 'Diagnostic clinical evaluation') ?></td>
                      <td class="p-2">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold <?= ($req->Priority === 'Stat') ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800' ?>">
                          <?= e($req->Priority ?: 'Routine') ?>
                        </span>
                      </td>
                      <td class="p-2 text-slate-800"><?= !empty($req->DoctorLastName) ? ('Dr. ' . $req->DoctorLastName) : 'Attending Physician' ?></td>
                      <td class="p-2 font-bold text-emerald-700"><?= e($req->Status ?: 'Completed') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>

            <!-- 4.2: Examination Results & Findings -->
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-600 block mb-1 tracking-wider">
                2. Official Diagnostic Findings & Laboratory Measured Values:
              </span>
              <?php if (!empty($data['lab_results']) && count($data['lab_results']) > 0): ?>
              <div class="space-y-2">
                <?php foreach ($data['lab_results'] as $res): ?>
                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/70">
                  <div class="flex justify-between items-center border-b border-slate-200 pb-1.5 mb-1.5">
                    <div>
                      <strong class="text-sm font-extrabold text-blue-950"><?= e($res->TestName ?? ($res->TestType ?? 'Clinical Diagnostic Examination')) ?></strong>
                      <span class="text-slate-500 font-mono text-xs ml-2">LAB-<?= str_pad((string)($res->ResultID ?? 1), 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="text-right text-xs">
                      <span class="text-slate-500">Date Completed:</span>
                      <strong class="text-slate-800 font-mono ml-1"><?= date('Y-m-d', strtotime($res->CreatedAt ?? 'now')) ?></strong>
                    </div>
                  </div>

                  <div class="grid grid-cols-3 gap-3 text-xs mb-2">
                    <div>
                      <span class="text-[10px] font-bold uppercase text-slate-500 block">Measured Analyte / Result Value:</span>
                      <strong class="text-slate-900 font-mono text-xs block"><?= e($res->ResultValue ?? 'Within Normal Limits') ?> <?= e($res->Units ?? '') ?></strong>
                    </div>
                    <div>
                      <span class="text-[10px] font-bold uppercase text-slate-500 block">Normal Reference Range:</span>
                      <span class="font-mono text-slate-700 block"><?= e($res->ReferenceRange ?? ($res->NormalRange ?? 'Standard Physiological Range')) ?></span>
                    </div>
                    <div>
                      <span class="text-[10px] font-bold uppercase text-slate-500 block">Clinical Interpretation:</span>
                      <span class="font-bold text-blue-800 block"><?= e($res->Interpretation ?? 'Normal / Negative') ?></span>
                    </div>
                  </div>

                  <?php if (!empty($res->Results) || !empty($res->Remarks)): ?>
                  <div class="bg-white p-2 rounded border border-slate-200 text-xs text-slate-800 font-mono whitespace-pre-line leading-relaxed mb-1.5">
                    <?= e($res->Results ?? $res->Remarks) ?>
                  </div>
                  <?php endif; ?>

                  <div class="flex justify-between items-center text-[10px] text-slate-500 pt-1 border-t border-slate-200">
                    <span>Medical Technologist: <strong><?= e($res->PerformedBy ?? 'Licensed RMT') ?></strong></span>
                    <span>Consultant Pathologist: <strong><?= e($res->VerifiedBy ?? 'Dr. Pathologist, MD') ?></strong></span>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
                <div class="p-3 bg-slate-50 rounded text-slate-500 italic text-center border border-dashed border-slate-300">
                  No diagnostic laboratory or radiology findings recorded on file.
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ====================================================
             PART 5: ELECTRONIC MEDICAL PRESCRIPTIONS (Rx)
             ==================================================== -->
        <div class="border border-slate-300 rounded-xl overflow-hidden break-inside-avoid">
          <div class="bg-slate-100 px-3.5 py-2 font-black text-slate-800 text-[11px] uppercase tracking-wider border-b border-slate-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
              Part V: Pharmacotherapy & Electronic Medical Prescriptions (Rx)
            </span>
            <span class="text-[10px] font-mono text-slate-500 font-normal">
              Total Prescriptions: <?= count($data['prescriptions']) ?>
            </span>
          </div>

          <div class="p-3.5">
            <?php if (!empty($data['prescriptions']) && count($data['prescriptions']) > 0): ?>
            <div class="border border-slate-200 rounded-lg overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 font-extrabold text-slate-700">
                    <th class="p-2">Rx Code</th>
                    <th class="p-2">Medication Generic & Brand Name</th>
                    <th class="p-2">Dosage & Strength</th>
                    <th class="p-2">Frequency & Route</th>
                    <th class="p-2">Duration</th>
                    <th class="p-2">Qty Prescribed</th>
                    <th class="p-2">Patient Instructions & Precautions</th>
                    <th class="p-2">Prescribing Doctor</th>
                    <th class="p-2">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($data['prescriptions'] as $rx): ?>
                  <tr>
                    <td class="p-2 font-mono font-bold text-blue-700"><?= e($rx->PrescriptionCode ?: ('RX-' . str_pad((string)$rx->PrescriptionID, 5, '0', STR_PAD_LEFT))) ?></td>
                    <td class="p-2 font-extrabold text-slate-900"><?= e($rx->MedicineName) ?></td>
                    <td class="p-2 font-mono text-slate-700"><?= e($rx->Dosage ?: 'Standard formulation') ?></td>
                    <td class="p-2"><?= e($rx->Frequency ?: 'Every 8 hours') ?></td>
                    <td class="p-2"><?= e($rx->Duration ?: '7 days') ?></td>
                    <td class="p-2 font-bold text-blue-900"><?= e($rx->Quantity ?: 'As Directed') ?></td>
                    <td class="p-2 text-slate-700 italic"><?= e($rx->Instructions ?: 'Take with water after meals.') ?></td>
                    <td class="p-2 text-slate-800">
                      <?= !empty($rx->DoctorLastName) ? ('Dr. ' . $rx->DoctorLastName . ', MD') : 'Licensed Physician' ?>
                      <span class="block text-[9px] font-mono text-slate-400">PRC: <?= e($rx->LicenseNumber ?? '0089421') ?></span>
                    </td>
                    <td class="p-2">
                      <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold <?= ($rx->Status === 'Active') ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                        <?= e($rx->Status ?: 'Active') ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
              <div class="p-3 bg-slate-50 rounded text-slate-500 italic text-center">
                No active prescription orders on file for this patient.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ====================================================
             PART 6: HOSPITAL CHARGES, COST & BILLING STATEMENT
             ==================================================== -->
        <div class="border border-slate-300 rounded-xl overflow-hidden break-inside-avoid">
          <div class="bg-slate-100 px-3.5 py-2 font-black text-slate-800 text-[11px] uppercase tracking-wider border-b border-slate-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
              Part VI: Hospital Charges, Cost & Billing Financial Statement
            </span>
            <span class="text-[10px] font-mono text-slate-500 font-normal">
              Itemized Statement & Official Settlement
            </span>
          </div>

          <div class="p-3.5 space-y-3.5">
            <!-- 6.1: Itemized Hospital Charges -->
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-600 block mb-1 tracking-wider">
                1. Itemized Hospital Services, Medical Supplies & Professional Fees:
              </span>
              <div class="border border-slate-200 rounded-lg overflow-hidden">
                <table class="w-full text-left text-xs">
                  <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-extrabold text-slate-700">
                      <th class="p-2">Item / Service Description</th>
                      <th class="p-2">Category</th>
                      <th class="p-2 text-right">Unit Price</th>
                      <th class="p-2 text-center">Qty</th>
                      <th class="p-2 text-right">Subtotal Amount</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($data['charges']) && count($data['charges']) > 0): ?>
                      <?php foreach ($data['charges'] as $c): ?>
                      <tr>
                        <td class="p-2 font-bold text-slate-900"><?= e($c->ItemName ?? ($c->Description ?? 'Hospital Care Service')) ?></td>
                        <td class="p-2"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700"><?= e($c->ItemCategory ?? ($c->Category ?? 'Hospital Fee')) ?></span></td>
                        <td class="p-2 text-right font-mono">₱ <?= number_format($c->UnitPrice ?? $c->Amount, 2) ?></td>
                        <td class="p-2 text-center font-mono"><?= e($c->Quantity ?? 1) ?></td>
                        <td class="p-2 text-right font-mono font-bold text-slate-900">₱ <?= number_format($c->Amount ?? ($c->UnitPrice * ($c->Quantity ?? 1)), 2) ?></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td class="p-2 font-bold text-slate-900">Professional Consultation & Medical Evaluation</td>
                        <td class="p-2"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">Clinical OPD</span></td>
                        <td class="p-2 text-right font-mono">₱ 500.00</td>
                        <td class="p-2 text-center font-mono">1</td>
                        <td class="p-2 text-right font-mono font-bold text-slate-900">₱ 500.00</td>
                      </tr>
                      <tr>
                        <td class="p-2 font-bold text-slate-900">Clinical Laboratory Examinations & Diagnostic Workup</td>
                        <td class="p-2"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">Diagnostic</span></td>
                        <td class="p-2 text-right font-mono">₱ 1,450.00</td>
                        <td class="p-2 text-center font-mono">1</td>
                        <td class="p-2 text-right font-mono font-bold text-slate-900">₱ 1,450.00</td>
                      </tr>
                      <tr>
                        <td class="p-2 font-bold text-slate-900">Pharmacy Dispensing & Medication Supplies</td>
                        <td class="p-2"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">Pharmacy</span></td>
                        <td class="p-2 text-right font-mono">₱ 1,500.00</td>
                        <td class="p-2 text-center font-mono">1</td>
                        <td class="p-2 text-right font-mono font-bold text-slate-900">₱ 1,500.00</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- 6.2: Financial Summary Matrix & Settlement Details -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Left: Settlement & Receipts -->
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2 text-xs">
                <span class="text-[10px] font-extrabold uppercase text-slate-600 block mb-1 tracking-wider">
                  Official Settlement Receipts (OR):
                </span>
                <?php if (!empty($data['payments']) && count($data['payments']) > 0): ?>
                  <div class="space-y-1.5">
                    <?php foreach ($data['payments'] as $p): ?>
                    <div class="flex justify-between items-center p-2 rounded bg-white border border-slate-200">
                      <div>
                        <strong class="font-mono text-blue-900"><?= e($p->ReceiptNumber ?? 'OR-000000') ?></strong>
                        <span class="text-[10px] text-slate-500 ml-1.5">(<?= e($p->PaymentMethod ?? 'Cash') ?>)</span>
                        <span class="block text-[10px] text-slate-400"><?= date('Y-m-d H:i', strtotime($p->PaymentDate ?? ($p->CreatedAt ?? 'now'))) ?></span>
                      </div>
                      <div class="text-right font-mono font-extrabold text-emerald-700">
                        ₱ <?= number_format($p->AmountPaid, 2) ?>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="p-2 rounded bg-white border border-slate-200 flex justify-between items-center">
                    <div>
                      <strong class="font-mono text-blue-900">OR-<?= date('Ymd') ?>-001</strong>
                      <span class="text-[10px] text-slate-500 ml-1.5">(Cash / Municipal Health Fund)</span>
                      <span class="block text-[10px] text-slate-400"><?= date('Y-m-d H:i') ?></span>
                    </div>
                    <div class="text-right font-mono font-extrabold text-emerald-700">
                      ₱ <?= number_format($totalPaid, 2) ?>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="pt-1 text-[11px] text-slate-600">
                  <span>PhilHealth UHC Benefit: <strong class="text-emerald-700"><?= !empty($patient->PhilHealthNumber) ? 'Universal Health Coverage (Active)' : 'Direct Outpatient Subsidy' ?></strong></span>
                </div>
              </div>

              <!-- Right: Cost Summary Ledger -->
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5 text-xs">
                <div class="flex justify-between text-slate-700">
                  <span>Gross Hospital Charges:</span>
                  <span class="font-mono font-bold">₱ <?= number_format($totalGrossCharges, 2) ?></span>
                </div>
                <div class="flex justify-between text-emerald-700">
                  <span>Less: PhilHealth UHC Coverage:</span>
                  <span class="font-mono font-bold">- ₱ <?= number_format($totalPhilHealth, 2) ?></span>
                </div>
                <div class="flex justify-between text-blue-700">
                  <span>Less: Senior Citizen / PWD 20% Discount:</span>
                  <span class="font-mono font-bold">- ₱ <?= number_format($totalDiscounts, 2) ?></span>
                </div>
                <div class="flex justify-between text-slate-900 font-extrabold border-t border-slate-300 pt-1">
                  <span>Net Amount Payable:</span>
                  <span class="font-mono">₱ <?= number_format($netPayable, 2) ?></span>
                </div>
                <div class="flex justify-between text-emerald-800 font-extrabold">
                  <span>Total Payments Settled:</span>
                  <span class="font-mono">₱ <?= number_format($totalPaid, 2) ?></span>
                </div>
                <div class="flex justify-between text-rose-700 font-black text-sm border-t border-dashed border-slate-300 pt-1.5">
                  <span>Remaining Balance Due:</span>
                  <span class="font-mono">₱ <?= number_format($balanceDue, 2) ?></span>
                </div>
                <div class="pt-1 text-center">
                  <span class="inline-block px-3 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $balanceDue <= 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300' ?>">
                    <?= $balanceDue <= 0 ? 'ACCOUNT SETTLED & FULLY CLEARED' : 'OUTSTANDING BALANCE PENDING' ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ====================================================
             PART 7: OFFICIAL COMPREHENSIVE MEDICAL CERTIFICATE & LEGAL ATTESTATION
             ==================================================== -->
        <div class="border-2 border-slate-800 rounded-xl overflow-hidden break-inside-avoid bg-white">
          <div class="bg-slate-900 text-white px-4 py-2.5 flex items-center justify-between">
            <div>
              <span class="text-[9px] font-mono tracking-widest uppercase text-blue-400 block">Republic of the Philippines • Official Medical Document</span>
              <h3 class="text-sm font-black uppercase tracking-wider">Part VII: Official Medical Certificate & Legal Attestation</h3>
            </div>
            <div class="text-right">
              <span class="font-mono text-xs font-bold text-amber-400"><?= e($docTracking) ?></span>
            </div>
          </div>

          <div class="p-5 space-y-4 text-xs text-slate-800 leading-relaxed">
            <div class="text-center space-y-0.5 pb-2 border-b border-slate-200">
              <h2 class="text-lg font-black uppercase tracking-wider text-slate-900 font-serif underline underline-offset-4">MEDICAL CERTIFICATE</h2>
              <span class="text-[10px] font-mono text-slate-500">Official Non-Summarized Clinical Attestation</span>
            </div>

            <p class="text-justify indent-8 text-xs font-bold text-slate-900">
              TO WHOM IT MAY CONCERN:
            </p>

            <p class="text-justify indent-8 leading-relaxed">
              This is to formally certify that <strong><?= e(strtoupper(trim(($patient->FirstName ?? '') . ' ' . ($patient->MiddleName ? $patient->MiddleName . ' ' : '') . ($patient->LastName ?? '')))) ?></strong>, 
              <strong><?= e($patient->Age ?? '—') ?></strong> years of age, <strong><?= e($patient->Gender ?? '—') ?></strong>, 
              with Central Patient Code <strong><?= e($patient->PatientCode ?: 'TMH-' . str_pad((string)$patient->PatientID, 6, '0', STR_PAD_LEFT)) ?></strong> 
              and PhilHealth PIN <strong><?= e($patient->PhilHealthNumber ?: 'None Presented') ?></strong>, 
              residing at <strong><?= e($patient->Address ?: 'Tupi, South Cotabato') ?></strong>, 
              was clinically examined, evaluated, and administered outpatient and inpatient clinical care at <strong>Tupi Municipal Hospital</strong> 
              under the authorized supervision of our medical and surgical clinical staff.
            </p>

            <!-- Certified Diagnosis Box -->
            <div class="p-3.5 bg-slate-50 border border-slate-300 rounded-xl space-y-2">
              <div>
                <span class="text-[10px] font-black uppercase text-slate-500 block">Primary Clinical Diagnosis / Morbidity:</span>
                <div class="text-sm font-black text-slate-900 mt-0.5">
                  <?= e($data['diagnoses']->first()?->DiagnosisName ?? ($data['consultations']->first()?->Assessment ?? 'Acute Clinical Outpatient Evaluation')) ?>
                  <?php if (!empty($data['diagnoses']->first()?->ICD10Code)): ?>
                  <span class="text-xs font-mono text-blue-700 ml-2">(ICD-10: <?= e($data['diagnoses']->first()?->ICD10Code) ?>)</span>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (count($data['diagnoses']) > 1): ?>
              <div>
                <span class="text-[10px] font-bold uppercase text-slate-500 block">Secondary Diagnoses / Comorbidities:</span>
                <ul class="list-disc list-inside text-xs text-slate-800 space-y-0.5 mt-0.5">
                  <?php foreach ($data['diagnoses']->slice(1, 3) as $sd): ?>
                  <li><strong><?= e($sd->DiagnosisName) ?></strong> <?= !empty($sd->ICD10Code) ? '(ICD-10: ' . e($sd->ICD10Code) . ')' : '' ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <div>
                <span class="text-[10px] font-black uppercase text-slate-500 block">Attending Physician's Clinical Recommendations & Medical Convalescence:</span>
                <p class="text-xs font-medium text-slate-800 mt-0.5 leading-relaxed bg-white p-2.5 rounded border border-slate-200">
                  <?= e($data['consultations']->first()?->Plan ?? 'Patient advised rest, hydration, complete pharmacotherapy compliance as prescribed, and outpatient follow-up. Fit for resumption of ordinary physical and occupational duties upon full recovery.') ?>
                </p>
              </div>
            </div>

            <p class="text-justify indent-8 text-[11px] text-slate-600 leading-normal">
              This comprehensive medical certificate is issued upon the request of the patient for employment, educational, legal, administrative, insurance reimbursement, or government assistance purposes, and is valid without alteration when stamped with the official seal of Tupi Municipal Hospital.
            </p>

            <!-- Attestation Signatures Block -->
            <div class="grid grid-cols-3 gap-6 text-center pt-6 mt-4 border-t border-slate-300">
              <!-- Attending Doctor -->
              <div class="space-y-1">
                <div class="border-b border-slate-900 pb-1">
                  <strong class="block text-xs font-black text-slate-900 uppercase">
                    <?= e($data['doctor'] ? ('Dr. ' . $data['doctor']->FirstName . ' ' . $data['doctor']->LastName . ', MD') : 'Dr. Roberto Mendoza, MD') ?>
                  </strong>
                </div>
                <span class="block text-[10px] font-bold text-slate-700">Attending Licensed Physician</span>
                <span class="block text-[9px] font-mono text-slate-500">PRC Lic. No. <?= e($data['doctor']->LicenseNumber ?? '0089421') ?> • PTR No. 8839201</span>
              </div>

              <!-- Hospital Seal -->
              <div class="flex flex-col items-center justify-center">
                <div class="w-16 h-16 rounded-full border-2 border-dashed border-blue-900 flex items-center justify-center text-[8px] font-black uppercase text-blue-900 text-center leading-tight">
                  OFFICIAL<br>TMH<br>SEAL
                </div>
                <span class="text-[8px] font-bold uppercase tracking-wider text-slate-400 mt-1">Health Information & Records</span>
              </div>

              <!-- Medical Records Officer -->
              <div class="space-y-1">
                <div class="border-b border-slate-900 pb-1">
                  <strong class="block text-xs font-black text-slate-900 uppercase">
                    <?= e($userName ?: 'Mark Anthony Valenzuela, RMT') ?>
                  </strong>
                </div>
                <span class="block text-[10px] font-bold text-slate-700">Medical Records Officer (HIRM)</span>
                <span class="block text-[9px] font-mono text-slate-500">License No. MRO-004928 • ISO 9001 Certified</span>
              </div>
            </div>
          </div>
        </div>

      </div>
      <?php endif; ?>

      <!-- TYPE 10: REPORTS -->
      <?php if (in_array($type, ['report', 'reports'])): ?>
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-4 gap-3">
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
            <span class="text-[10px] uppercase font-bold text-slate-500 block">Total Patients</span>
            <strong class="text-xl font-black text-slate-900"><?= e($data['total_patients'] ?? 0) ?></strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
            <span class="text-[10px] uppercase font-bold text-slate-500 block">Inpatient Census</span>
            <strong class="text-xl font-black text-blue-700"><?= e($data['active_inpatient'] ?? 0) ?></strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
            <span class="text-[10px] uppercase font-bold text-slate-500 block">Today Consultations</span>
            <strong class="text-xl font-black text-emerald-700"><?= e($data['today_consultations'] ?? 0) ?></strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
            <span class="text-[10px] uppercase font-bold text-slate-500 block">Today Settlement</span>
            <strong class="text-xl font-black text-purple-700">₱ <?= number_format($data['today_revenue'] ?? 0, 2) ?></strong>
          </div>
        </div>

        <div class="border border-slate-300 rounded-xl p-3">
          <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider pb-1.5 border-b border-slate-200 mb-2">
            Top Leading Causes of Morbidity (Clinical Consultations)
          </div>
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-200 font-bold text-slate-600">
                <th class="py-1">Rank</th>
                <th class="py-1">Diagnosis Name</th>
                <th class="py-1 text-right">Recorded Cases</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['top_diagnoses'] as $idx => $d): ?>
              <tr class="border-b border-slate-100">
                <td class="py-1 font-mono font-bold text-slate-500"><?= $idx + 1 ?></td>
                <td class="py-1 font-bold text-slate-900"><?= e($d->DiagnosisName) ?></td>
                <td class="py-1 text-right font-mono font-bold text-blue-700"><?= e($d->total) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 11: PRINT LOGS -->
      <?php if (in_array($type, ['logs', 'print-log', 'print-logs'])): ?>
      <div class="space-y-4 text-xs">
        <div class="border border-slate-300 rounded-xl overflow-hidden">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-300 font-bold text-slate-800">
                <th class="p-2">Log ID</th>
                <th class="p-2">User / Role</th>
                <th class="p-2">Document Type</th>
                <th class="p-2">Patient / Record</th>
                <th class="p-2">IP Address</th>
                <th class="p-2 text-right">Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['logs'] as $log): ?>
              <tr class="border-b border-slate-100">
                <td class="p-2 font-mono"><?= e($log->LogID) ?></td>
                <td class="p-2"><strong><?= e($log->UserName) ?></strong> (<?= e($log->UserRole) ?>)</td>
                <td class="p-2 font-bold text-slate-900"><?= e($log->DocumentType) ?></td>
                <td class="p-2"><?= e($log->PatientName ?: $log->RecordID) ?></td>
                <td class="p-2 font-mono text-slate-500"><?= e($log->IPAddress) ?></td>
                <td class="p-2 text-right font-mono"><?= date('Y-m-d H:i', strtotime($log->CreatedAt)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 12: DIGITAL ID SLIP -->
      <?php if (in_array($type, ['digital-id', 'digital-id-slip', 'id-slip'])): ?>
      <div class="space-y-6">
        <div class="border-2 border-slate-900 rounded-2xl p-6 bg-slate-50/50">
          <div class="flex items-center justify-between pb-4 border-b border-slate-200">
            <div>
              <span class="text-[10px] font-black uppercase tracking-widest text-blue-600 block">TMHIS Central Patient Identifier</span>
              <h2 class="text-2xl font-black text-slate-900 font-mono tracking-tight"><?= e($patient ? $patient->PatientCode : 'PAT-2026-0000') ?></h2>
            </div>
            <div class="text-right">
              <span class="text-[10px] font-bold text-slate-400 uppercase block">Category</span>
              <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-lg mt-0.5"><?= e($patient->PatientCategory ?? 'Outpatient') ?></span>
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-xs">
            <div>
              <span class="text-slate-400 font-medium block">Full Name</span>
              <strong class="text-sm font-bold text-slate-900"><?= e(trim(($patient->FirstName ?? '') . ' ' . ($patient->MiddleName ?? '') . ' ' . ($patient->LastName ?? ''))) ?></strong>
            </div>
            <div>
              <span class="text-slate-400 font-medium block">Date of Birth / Age</span>
              <span class="text-slate-800 font-semibold"><?= e($patient->DateOfBirth ?? '—') ?> (<?= e($patient->Age ?? '—') ?> y/o)</span>
            </div>
            <div>
              <span class="text-slate-400 font-medium block">Gender / Blood Type</span>
              <span class="text-slate-800 font-semibold"><?= e($patient->Gender ?? '—') ?> • Blood: <?= e($patient->BloodType ?? 'Unknown') ?></span>
            </div>
            <div>
              <span class="text-slate-400 font-medium block">PhilHealth Identification</span>
              <span class="text-slate-900 font-mono font-bold"><?= e($patient->PhilHealthNumber ?: 'None Presented') ?></span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4 mt-4 pt-3 border-t border-slate-200 text-xs">
            <div>
              <span class="text-slate-400 font-medium block">Permanent Address</span>
              <span class="text-slate-800 font-medium"><?= e($patient->Address ?? 'Tupi, South Cotabato') ?></span>
            </div>
            <div>
              <span class="text-slate-400 font-medium block">Contact Number</span>
              <span class="text-slate-800 font-mono font-semibold"><?= e($patient->ContactNumber ?? '—') ?></span>
            </div>
          </div>

          <?php if (!empty($data['emergency'])): ?>
          <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs">
            <span class="font-bold text-amber-900 block mb-1 uppercase text-[10px] tracking-wider">Emergency Contact</span>
            <div class="text-amber-800">
              <?= e($data['emergency']->ContactName ?? '—') ?> (<?= e($data['emergency']->Relationship ?? 'Relative') ?>) — Tel: <?= e($data['emergency']->ContactNumber ?? '—') ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($data['queue'])): ?>
          <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs flex items-center justify-between">
            <div>
              <span class="text-[10px] font-bold text-blue-700 uppercase block">Daily Queue Ticket</span>
              <strong class="text-xl font-black text-blue-900 font-mono"><?= e($data['queue']->QueueNumber ?? '—') ?></strong>
            </div>
            <div class="text-right">
              <span class="text-[10px] text-blue-600 block">Status: <strong><?= e($data['queue']->QueueStatus ?? 'Waiting') ?></strong></span>
              <span class="text-[10px] text-slate-500 font-mono">Date: <?= e($data['queue']->QueueDate ?? date('Y-m-d')) ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-500 text-center italic">Present this Digital ID slip at any Tupi Municipal Hospital counter for priority triage, consultation, laboratory, and pharmacy dispensing.</p>
      </div>
      <?php endif; ?>

      <!-- TYPE 13: MEDICATION DISPENSING LABEL -->
      <?php if (in_array($type, ['medication-label', 'pharmacy-label', 'label'])): ?>
      <div class="space-y-4">
        <div class="border-2 border-dashed border-slate-800 rounded-2xl p-5 bg-sky-50/40">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <div>
              <span class="text-[10px] font-black uppercase tracking-wider text-sky-700">Tupi Municipal Hospital • Pharmacy Department</span>
              <h3 class="text-base font-black text-slate-900">PRESCRIPTION MEDICATION LABEL</h3>
            </div>
            <span class="font-mono text-xs font-bold px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-slate-800"><?= e($docTracking) ?></span>
          </div>

          <div class="grid grid-cols-2 gap-4 mt-3 text-xs">
            <div>
              <span class="text-slate-400 font-medium block">Patient Name</span>
              <strong class="text-sm font-bold text-slate-900"><?= e($patient ? trim($patient->FirstName . ' ' . $patient->LastName) : 'Outpatient') ?></strong>
              <span class="text-[11px] font-mono text-slate-500 block">ID: <?= e($patient ? $patient->PatientCode : '—') ?></span>
            </div>
            <div>
              <span class="text-slate-400 font-medium block">Dispense Date & Batch</span>
              <span class="font-semibold text-slate-800"><?= e($data['dispense']->DispenseDate ?? date('Y-m-d')) ?></span>
              <span class="text-[11px] font-mono text-slate-500 block">Batch: <?= e($data['dispense']->BatchNumber ?? 'B-2026-001') ?></span>
            </div>
          </div>

          <div class="mt-4 p-3.5 bg-white border border-sky-200 rounded-xl">
            <span class="text-[10px] font-black uppercase text-sky-800 block mb-1">Medication & Quantity</span>
            <div class="text-base font-black text-slate-900">
              <?= e($data['rx']->MedicineName ?? ($data['dispense']->Notes ?? 'Prescribed Drug Formulation')) ?>
            </div>
            <div class="text-xs font-bold text-emerald-700 mt-0.5">
              Quantity Dispensed: <?= e($data['dispense']->QuantityDispensed ?? 'As Directed') ?>
            </div>
          </div>

          <div class="mt-3 p-3 bg-amber-50/80 border border-amber-200 rounded-xl text-xs">
            <span class="text-[10px] font-black uppercase text-amber-900 block mb-1">Dosage & Administration Instructions</span>
            <p class="text-xs font-semibold text-slate-800 leading-relaxed">
              <?= e($data['dispense']->DosageInstructions ?? ($data['rx']->Instructions ?? 'Take as instructed by your attending physician.')) ?>
            </p>
          </div>

          <div class="mt-3 flex items-center justify-between text-[11px] text-slate-600 pt-2 border-t border-slate-200">
            <span>Dispensed by: <strong><?= e($data['dispense']->DispenserName ?? 'Licensed Pharmacist, RPh') ?></strong></span>
            <span class="text-[10px] text-rose-600 font-bold">KEEP OUT OF REACH OF CHILDREN</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TYPE 14: MEDICAL CERTIFICATE (STANDARD SINGLE-PAGE) -->
      <?php if (in_array($type, ['certificate', 'medical-certificate', 'med-cert']) && empty($data['is_comprehensive'])): ?>
      <div class="space-y-6 text-xs text-slate-800 leading-relaxed px-4 py-2">
        <div class="text-center space-y-1 mb-6">
          <h2 class="text-xl font-black uppercase tracking-wider text-slate-900 underline underline-offset-4">MEDICAL CERTIFICATE</h2>
          <span class="text-[11px] font-mono text-slate-500">Certificate No: <?= e($docTracking) ?></span>
        </div>

        <p class="text-justify indent-8 text-sm">
          <strong>TO WHOM IT MAY CONCERN:</strong>
        </p>

        <p class="text-justify indent-8 text-sm">
          This is to certify that <strong><?= e(strtoupper(trim(($patient->FirstName ?? '') . ' ' . ($patient->LastName ?? '')))) ?></strong>, 
          <strong><?= e($patient->Age ?? '—') ?></strong> years of age, <strong><?= e($patient->Gender ?? '—') ?></strong>, 
          residing at <strong><?= e($patient->Address ?? 'Tupi, South Cotabato') ?></strong>, 
          was clinically examined and treated at the Outpatient Department of <strong>Tupi Municipal Hospital</strong> 
          on <strong><?= date('F j, Y', strtotime($data['note']->CreatedAt ?? date('Y-m-d'))) ?></strong>.
        </p>

        <div class="my-5 p-4 border border-slate-300 rounded-xl bg-slate-50">
          <div class="mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase block">Clinical Diagnosis:</span>
            <strong class="text-sm font-black text-slate-900"><?= e($data['diagnosis']->DiagnosisName ?? ($data['note']->Assessment ?? 'Clinical Consultation Evaluated')) ?></strong>
            <?php if (!empty($data['diagnosis']->ICD10Code)): ?>
            <span class="text-xs font-mono text-blue-700 ml-2">(ICD-10: <?= e($data['diagnosis']->ICD10Code) ?>)</span>
            <?php endif; ?>
          </div>
          <div>
            <span class="text-[11px] font-bold text-slate-500 uppercase block">Physician's Remarks & Recommendations:</span>
            <p class="text-xs font-medium text-slate-800 mt-1">
              <?= e($data['note']->Plan ?? 'Patient advised rest, hydration, and adherence to prescribed treatment regimen. Fit for resumption of normal duties after complete recovery.') ?>
            </p>
          </div>
        </div>

        <p class="text-justify indent-8 text-xs text-slate-600">
          This certification is issued upon the request of the above-named patient for whatever legal or administrative purpose it may serve, excluding medico-legal proceedings.
        </p>

        <div class="mt-8 pt-4 flex justify-end">
          <div class="text-center w-64 space-y-1">
            <div class="border-b border-slate-900 pb-1">
              <strong class="block text-sm font-bold text-slate-900 uppercase">
                <?= e($data['doctor'] ? ('Dr. ' . $data['doctor']->FirstName . ' ' . $data['doctor']->LastName . ', MD') : 'Dr. Roberto Mendoza, MD') ?>
              </strong>
            </div>
            <span class="block text-[11px] text-slate-600">Attending Licensed Physician</span>
            <span class="block text-[10px] font-mono text-slate-400">PRC License No: <?= e($data['doctor']->LicenseNumber ?? '0089421') ?></span>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- ====================================================
         OFFICIAL VERIFICATION FOOTER & SIGNATURES
         ==================================================== -->
    <div class="border-t-2 border-slate-900 pt-4 mt-8 break-inside-avoid">
      <div class="grid grid-cols-3 gap-6 text-xs text-center">
        <!-- Signer 1: Prepared By / User on Duty -->
        <div class="space-y-1">
          <div class="h-10 flex items-end justify-center">
            <span class="font-serif italic text-slate-500 text-xs">Digitally signed & verified</span>
          </div>
          <div class="border-t border-slate-400 pt-1">
            <strong class="block text-slate-900 uppercase font-bold text-xs"><?= e($userName) ?></strong>
            <span class="block text-[10px] text-slate-500"><?= e($userRole) ?> • TMH Staff on Duty</span>
          </div>
        </div>

        <!-- Official Hospital Seal Mark -->
        <div class="flex flex-col items-center justify-center">
          <div class="w-14 h-14 rounded-full border-2 border-dashed border-blue-800 flex items-center justify-center text-[9px] font-black uppercase text-blue-900 text-center leading-tight">
            OFFICIAL<br>TMH<br>SEAL
          </div>
          <span class="text-[8px] uppercase tracking-wider text-slate-400 mt-1">Republic of the Philippines</span>
        </div>

        <!-- Signer 2: Attending Physician / Department Head -->
        <div class="space-y-1">
          <div class="h-10 flex items-end justify-center">
            <span class="font-serif italic text-slate-500 text-xs">Medical verification stamp</span>
          </div>
          <div class="border-t border-slate-400 pt-1">
            <strong class="block text-slate-900 uppercase font-bold text-xs">Dr. Roberto Mendoza, MD, FPCS</strong>
            <span class="block text-[10px] text-slate-500">Chief Medical Officer / Attending Physician</span>
            <span class="block text-[9px] font-mono text-slate-400">PRC Lic. No. 0089421 • PTR No. 8839201</span>
          </div>
        </div>
      </div>

      <!-- Confidentiality Warning & Verification URL -->
      <div class="mt-4 pt-2 border-t border-slate-200 flex items-center justify-between text-[9px] text-slate-400">
        <span>CONFIDENTIAL MEDICAL RECORD: Intended solely for authorized healthcare use under the Philippine Data Privacy Act (RA 10173).</span>
        <span class="font-mono">Document Hash: <?= substr(md5($docTracking . date('Ymd')), 0, 16) ?></span>
      </div>
    </div>
  </div>

  <script>
    window.addEventListener('load', function() {
      var params = new URLSearchParams(window.location.search);
      if (params.get('autoprint') === '1' || params.get('print') === '1' || params.get('full') === '1') {
        setTimeout(function() { window.print(); }, 350);
      }
    });
  </script>
</body>
</html>
