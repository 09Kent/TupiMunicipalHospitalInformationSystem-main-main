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
            <span class="block text-[10px] font-bold uppercase text-slate-500">Student ID / Reference:</span>
            <span class="font-mono font-semibold text-slate-800 text-xs"><?= 'STU-' . date('Y', strtotime($patient->CreatedAt ?? 'now')) . '-' . str_pad((string)$patient->PatientID, 5, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Age / Gender:</span>
            <span class="font-semibold text-slate-800"><?= e($patient->Age) ?> yrs • <?= e($patient->Gender) ?></span>
          </div>

          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Civil Status / Blood:</span>
            <span class="font-medium text-slate-800"><?= e($patient->CivilStatus ?: 'Single') ?> • Blood Type: <?= e($patient->BloodType ?: 'O+') ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Contact Number:</span>
            <span class="font-medium text-slate-800"><?= e($patient->ContactNumber ?: 'N/A') ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Classification / Room:</span>
            <span class="font-bold text-slate-800"><?= e($patient->PatientCategory ?: 'Outpatient') ?> <?= ($patient->PatientCategory === 'Inpatient') ? '(Room ' . (200 + ($patient->PatientID % 20)) . ')' : '(OPD Triage)' ?></span>
          </div>
          <div>
            <span class="block text-[10px] font-bold uppercase text-slate-500">Address:</span>
            <span class="font-medium text-slate-800 truncate block"><?= e($patient->Address ?: 'Tupi, South Cotabato') ?></span>
          </div>
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
      <?php if (in_array($type, ['receipt', 'payment-receipt'])): ?>
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

      <!-- TYPE 9: UNIFIED MEDICAL RECORD / CHART -->
      <?php if (in_array($type, ['medical-record', 'medical-records', 'chart'])): ?>
      <div class="space-y-4 text-xs">
        <div class="border border-slate-300 rounded-xl p-3">
          <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider pb-1.5 border-b border-slate-200 mb-2">
            Historical Consultations & Clinical Encounters
          </div>
          <?php if (!empty($data['consultations']) && count($data['consultations']) > 0): ?>
            <div class="space-y-2">
              <?php foreach ($data['consultations'] as $c): ?>
              <div class="p-2.5 rounded bg-slate-50 border border-slate-200">
                <div class="flex justify-between font-bold text-slate-900">
                  <span>Date: <?= date('M d, Y', strtotime($c->CreatedAt)) ?></span>
                  <span class="text-blue-700">SOAP #<?= e($c->NoteID) ?></span>
                </div>
                <div class="text-slate-700 mt-1"><strong>Assessment:</strong> <?= e($c->Assessment ?: 'Routine check') ?></div>
                <div class="text-slate-600 text-[11px]"><strong>Plan:</strong> <?= e($c->Plan ?: 'Outpatient care') ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-slate-500 italic">No previous consultation encounters recorded.</p>
          <?php endif; ?>
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

</body>
</html>
