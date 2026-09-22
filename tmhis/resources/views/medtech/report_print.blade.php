<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diagnostic Laboratory Report - TMHIS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @media print {
      body {
        margin: 0;
        padding: 0;
        background: #fff;
        color: #000;
      }
      .no-print {
        display: none !important;
      }
      .print-container {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
      }
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans p-4 sm:p-8">

  <!-- Floating Control Bar (Hidden when printing) -->
  <div class="no-print max-w-4xl mx-auto mb-6 flex items-center justify-between bg-white px-6 py-4 rounded-2xl shadow-md border border-slate-200">
    <div class="flex items-center gap-3">
      <a href="/medtech" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
        ← Return to Laboratory
      </a>
      <span class="text-xs text-slate-400 font-medium">Diagnostic Report Preview</span>
    </div>
    <div class="flex items-center gap-3">
      <button onclick="window.print()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        <span>Print Official Report</span>
      </button>
    </div>
  </div>

  <!-- Document Paper Container -->
  <div class="print-container max-w-4xl mx-auto bg-white rounded-3xl p-8 sm:p-12 shadow-xl border border-slate-200/80">
    
    <!-- Header -->
    <div class="border-b-2 border-slate-900 pb-6 mb-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-700 font-black text-2xl">
            TMH
          </div>
          <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase">Tupi Municipal Hospital</h1>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Department of Pathology & Clinical Laboratory</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Municipal Government of Tupi, South Cotabato, Philippines • Tel: (083) 228-1234</p>
          </div>
        </div>
        <div class="text-right">
          <div class="inline-block px-3 py-1 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-black uppercase tracking-wider">
            Official Diagnostic Report
          </div>
          <p class="text-[11px] font-mono text-slate-400 mt-1">Ref: RES-2026-<?= str_pad((string)($result->ResultID ?? 1), 4, '0', STR_PAD_LEFT) ?></p>
        </div>
      </div>
    </div>

    <!-- Patient & Specimen Metadata Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs mb-6">
      <div>
        <span class="text-slate-400 font-bold block uppercase text-[10px]">Patient Name</span>
        <span class="text-slate-900 font-bold text-sm block mt-0.5"><?= e(($patient->FirstName ?? 'Juan') . ' ' . ($patient->LastName ?? 'Dela Cruz')) ?></span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block uppercase text-[10px]">Patient Code / Age / Gender</span>
        <span class="text-slate-800 font-semibold block mt-0.5"><?= e($patient->PatientCode ?? 'P-2026-0001') ?> • <?= $patient->Age ?? 45 ?> yrs / <?= e($patient->Gender ?? 'Male') ?></span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block uppercase text-[10px]">Attending Physician</span>
        <span class="text-slate-800 font-semibold block mt-0.5">Dr. Michael Reyes, MD</span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block uppercase text-[10px]">Examination Date</span>
        <span class="text-slate-800 font-semibold block mt-0.5"><?= e($result->ResultDate ?? date('Y-m-d')) ?></span>
      </div>
    </div>

    <!-- Test Title Banner -->
    <div class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center justify-between mb-4">
      <span>Examination: <?= e($result->TestName ?? 'Diagnostic Laboratory Examination') ?></span>
      <span class="text-[11px] opacity-90">Laboratory Section: Clinical Diagnostic</span>
    </div>

    <!-- Results Table -->
    <div class="overflow-x-auto mb-6">
      <table class="w-full text-xs text-left border border-slate-200 rounded-xl overflow-hidden">
        <thead class="bg-slate-100 text-slate-700 uppercase text-[10px] tracking-wider border-b border-slate-200">
          <tr>
            <th class="py-3 px-4">Test Parameter</th>
            <th class="py-3 px-4">Observed Result</th>
            <th class="py-3 px-4">Unit</th>
            <th class="py-3 px-4">Reference Range</th>
            <th class="py-3 px-4 text-center">Status / Flag</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr class="hover:bg-slate-50">
            <td class="py-3 px-4 font-bold text-slate-900"><?= e($result->TestName ?? 'Parameter Value') ?></td>
            <td class="py-3 px-4 font-black text-slate-900 text-sm"><?= e($result->ResultValue ?? '14.2') ?></td>
            <td class="py-3 px-4 font-medium text-slate-600"><?= e($result->Units ?? 'mg/dL') ?></td>
            <td class="py-3 px-4 font-mono text-slate-500"><?= e($result->NormalRange ?? '13.0 - 17.5') ?></td>
            <td class="py-3 px-4 text-center">
              <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase <?= ($result->Interpretation ?? 'Normal') === 'Normal' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                <?= e($result->Interpretation ?? 'Normal') ?>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Clinical Interpretation / Remarks -->
    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs mb-8">
      <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Clinical Remarks & Technologist Notes</span>
      <p class="text-slate-700 leading-relaxed font-medium">
        <?= e($result->Notes ?? 'Specimen processed and verified in accordance with standard diagnostic protocols. All internal quality control metrics fall within acceptable tolerance limits.') ?>
      </p>
    </div>

    <!-- Signatures Section -->
    <div class="pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs">
      <div class="text-center">
        <div class="h-12 flex items-center justify-center">
          <span class="font-serif italic text-slate-400">Electronically Verified</span>
        </div>
        <p class="font-bold text-slate-900 uppercase">Robert Santos, RMT</p>
        <p class="text-[10px] text-slate-500">Medical Technologist • PRC License No. 0084920</p>
      </div>

      <div class="text-center">
        <div class="h-12 flex items-center justify-center">
          <span class="font-serif italic text-slate-400">Electronically Signed</span>
        </div>
        <p class="font-bold text-slate-900 uppercase">Dr. Vicente Gomez, MD, FPSP</p>
        <p class="text-[10px] text-slate-500">Anatomic & Clinical Pathologist • PRC License No. 0041285</p>
      </div>
    </div>

    <!-- Footer Disclaimer -->
    <div class="mt-8 pt-4 border-t border-slate-100 text-[10px] text-slate-400 text-center">
      This laboratory report is generated electronically by the Tupi Municipal Hospital Information Management System (TMHIS). Valid only when authorized by licensed pathology personnel.
    </div>

  </div>

</body>
</html>
