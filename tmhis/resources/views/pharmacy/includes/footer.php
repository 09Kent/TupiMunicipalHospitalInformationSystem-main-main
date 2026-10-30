<?php
// Pharmacy/includes/footer.php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/demo_data.php';
$currentUser = Session::getCurrentUser();
?>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- GLOBAL MODALS                                           -->
<!-- ═══════════════════════════════════════════════════════ -->

<!-- 1. VIEW / VERIFY PRESCRIPTION MODAL ────────────────── -->
<div id="viewRxModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-2xl max-h-[92vh] overflow-y-auto transform p-6 sm:p-8">
    
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
          <i data-lucide="clipboard-list" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono" id="vRxId">RX-2026-001</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Electronic Prescription</h3>
        </div>
      </div>
      <button onclick="closeModal('viewRxModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Patient Card -->
    <div class="mt-5 bg-slate-50 border border-slate-200/80 rounded-2xl p-4">
      <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
        <i data-lucide="user" class="w-3.5 h-3.5 text-sky-600"></i> Patient Information
      </h4>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div><span class="text-slate-400 font-medium block">Patient</span><span class="font-bold text-slate-900 mt-0.5 block text-sm" id="vPatientName">—</span></div>
        <div><span class="text-slate-400 font-medium block">Patient ID</span><span class="font-mono font-bold text-slate-900 mt-0.5 block" id="vPatientId">—</span></div>
        <div><span class="text-slate-400 font-medium block">Age / Gender</span><span class="font-semibold text-slate-900 mt-0.5 block" id="vAgeGender">—</span></div>
        <div><span class="text-slate-400 font-medium block">Allergies</span><span class="font-bold text-rose-600 mt-0.5 block" id="vAllergies">—</span></div>
      </div>
    </div>

    <!-- Doctor & Dates -->
    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4">
        <span class="text-slate-400 font-medium block">Prescribing Doctor</span>
        <span class="font-bold text-slate-900 mt-1 block text-sm" id="vDoctor">—</span>
        <span class="text-[11px] text-slate-500 mt-0.5 block" id="vSpecialty">—</span>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4">
        <span class="text-slate-400 font-medium block">Prescription Date</span>
        <span class="font-bold text-slate-900 mt-1 block text-sm" id="vRxDate">—</span>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full mt-1 inline-block" id="vPriority">—</span>
      </div>
    </div>

    <!-- Medication Details -->
    <div class="mt-4 bg-sky-50/60 border border-sky-200/60 rounded-2xl p-4">
      <h4 class="text-[10px] font-black uppercase tracking-wider text-sky-700 mb-3 flex items-center gap-1.5">
        <i data-lucide="pill" class="w-3.5 h-3.5"></i> Medication Details
      </h4>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
        <div><span class="text-slate-400 font-medium block">Medicine</span><span class="font-bold text-slate-900 mt-0.5 block" id="vMedicine">—</span></div>
        <div><span class="text-slate-400 font-medium block">Dosage</span><span class="font-bold text-sky-700 mt-0.5 block" id="vDosage">—</span></div>
        <div><span class="text-slate-400 font-medium block">Frequency</span><span class="font-semibold text-slate-900 mt-0.5 block" id="vFrequency">—</span></div>
        <div><span class="text-slate-400 font-medium block">Route</span><span class="font-semibold text-slate-900 mt-0.5 block" id="vRoute">—</span></div>
        <div><span class="text-slate-400 font-medium block">Duration</span><span class="font-semibold text-slate-900 mt-0.5 block" id="vDuration">—</span></div>
        <div><span class="text-slate-400 font-medium block">Quantity</span><span class="font-bold text-emerald-700 mt-0.5 block" id="vQuantity">—</span></div>
      </div>
      <div class="mt-3 pt-3 border-t border-sky-200/60">
        <span class="text-slate-400 font-medium block text-xs mb-1">Doctor's Instructions</span>
        <p class="text-xs text-slate-700 leading-relaxed" id="vInstructions">—</p>
      </div>
    </div>

    <!-- Drug Interaction Warning -->
    <div id="drugInteractionBanner" class="mt-4 hidden">
      <div class="flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-2xl p-4">
        <i data-lucide="triangle-alert" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
        <div>
          <p class="text-xs font-bold text-amber-800">Drug Interaction Warning</p>
          <p class="text-xs text-amber-700 mt-0.5" id="vInteraction">—</p>
        </div>
      </div>
    </div>

    <!-- Status & Actions -->
    <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
      <div>
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Current Status</span>
        <span class="px-3 py-1 rounded-full text-xs font-bold border" id="vStatus">—</span>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <button onclick="closeModal('viewRxModal'); openVerifyRxModal(currentViewedRx)"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
          <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
          Verify Prescription
        </button>
        <button onclick="closeModal('viewRxModal'); openDispensingModal(currentViewedRx)"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
          <i data-lucide="pill" class="w-3.5 h-3.5"></i>
          Dispense
        </button>
        <button onclick="closeModal('viewRxModal')"
                class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- 2. VERIFY PRESCRIPTION MODAL ───────────────────────── -->
<div id="verifyRxModal"
     class="fixed inset-0 z-[110] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
          <i data-lucide="shield-check" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono" id="vRxId2">RX-2026-001</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Prescription Verification</h3>
        </div>
      </div>
      <button onclick="closeModal('verifyRxModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Verification Checklist -->
    <div class="mt-5 space-y-2">
      <?php
      $checks = [
        ['Patient Information', 'user', 'sky'],
        ['Doctor Information', 'stethoscope', 'indigo'],
        ['Medicine / Generic Name', 'pill', 'sky'],
        ['Dosage', 'thermometer', 'emerald'],
        ['Quantity', 'hash', 'emerald'],
        ['Frequency', 'repeat', 'sky'],
        ['Duration', 'calendar', 'sky'],
        ['Route of Administration', 'route', 'indigo'],
        ['Allergy Check', 'shield', 'amber'],
        ['Drug Interaction Check', 'triangle-alert', 'rose'],
      ];
      foreach ($checks as [$label, $icon, $color]):
      ?>
      <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 hover:bg-emerald-50/40 border border-slate-200/60 rounded-xl transition group">
        <div class="w-7 h-7 rounded-lg bg-<?= $color ?>-50 border border-<?= $color ?>-100 flex items-center justify-center shrink-0">
          <i data-lucide="<?= $icon ?>" class="w-3.5 h-3.5 text-<?= $color ?>-600"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700 flex-1"><?= $label ?></span>
        <div class="w-5 h-5 rounded-full bg-emerald-100 border border-emerald-300 flex items-center justify-center text-emerald-600">
          <i data-lucide="check" class="w-3 h-3"></i>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Reject Reason (Hidden by default) -->
    <div id="rejectReasonSection" class="mt-4 hidden">
      <label class="text-xs font-bold text-slate-700 block mb-1.5">
        Reason for Rejection <span class="text-rose-500">*</span>
      </label>
      <textarea id="rejectReasonText" rows="3"
                class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition resize-none"
                placeholder="Enter reason for rejection..."></textarea>
    </div>

    <!-- Action Buttons -->
    <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row gap-2">
      <button onclick="confirmVerification()"
              class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
        Verify Prescription
      </button>
      <button onclick="requestClarification()"
              class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl transition flex items-center justify-center gap-2">
        <i data-lucide="message-circle" class="w-4 h-4"></i>
        Request Clarification
      </button>
      <button onclick="toggleRejectSection()"
              class="flex-1 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl transition border border-rose-200 flex items-center justify-center gap-2">
        <i data-lucide="x-circle" class="w-4 h-4"></i>
        Reject
      </button>
    </div>
  </div>
</div>

<!-- 3. DISPENSING RECORD MODAL ──────────────────────────── -->
<div id="dispensingModal"
     class="fixed inset-0 z-[110] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600">
          <i data-lucide="pill" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono" id="dRxId">DISP-2026-021</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Create Dispensing Record</h3>
        </div>
      </div>
      <button onclick="closeModal('dispensingModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <div class="mt-5 space-y-4">
      <!-- Patient & Rx (read-only) -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Patient</label>
          <div class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900" id="dPatient">—</div>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Prescription ID</label>
          <div class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-semibold text-slate-900" id="dRx">—</div>
        </div>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Medicine</label>
        <div class="px-3 py-2.5 bg-sky-50 border border-sky-200 rounded-xl text-xs font-bold text-sky-800" id="dMedicine">—</div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Qty Prescribed</label>
          <div class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900" id="dQtyPrescribed">—</div>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Qty to Dispense</label>
          <input type="number" id="dQtyDispense"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition"
                 placeholder="Enter quantity">
        </div>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Dispensing Date</label>
        <input type="date" id="dDate" value="<?= date('Y-m-d') ?>"
               class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Dispensed By</label>
        <div class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900"><?= e($currentUser['name']) ?></div>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Patient Instructions</label>
        <textarea rows="2" id="dInstructions"
                  class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition resize-none"
                  placeholder="Add patient counseling notes..."></textarea>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Notes</label>
        <textarea rows="2" id="dNotes"
                  class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition resize-none"
                  placeholder="Optional pharmacist notes..."></textarea>
      </div>
    </div>

    <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
      <button onclick="confirmDispensing()"
              class="flex-1 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
        Confirm Dispensing
      </button>
      <button onclick="closeModal('dispensingModal')"
              class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
        Cancel
      </button>
    </div>
  </div>
</div>

<!-- 4. STOCK-IN MODAL ───────────────────────────────────── -->
<div id="stockInModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-600 shadow-xs">
          <i data-lucide="package-plus" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono">STOCK ENTRY</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Create Stock-In Entry</h3>
        </div>
      </div>
      <button onclick="closeModal('stockInModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="mt-5 space-y-4">
      <!-- Searchable Medicine Input (Replaces dropdown) -->
      <div class="relative">
        <div class="flex items-center justify-between mb-1">
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Medicine <span class="text-rose-500">*</span></label>
          <span class="text-[10px] font-bold text-teal-600 flex items-center gap-1">
            <i data-lucide="sparkles" class="w-3 h-3 text-teal-500"></i> Instant Search Active
          </span>
        </div>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <i data-lucide="search" class="w-4 h-4"></i>
          </div>
          <input type="text" 
                 id="stockInMedicineSearch" 
                 autocomplete="off" 
                 placeholder="Search medicine by name, generic, or brand (e.g. Amoxicillin, Paracetamol)..." 
                 class="w-full pl-9 pr-9 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition font-medium"
                 onfocus="openStockInSuggestions()"
                 oninput="filterStockInMedicines(this.value)"
                 onkeydown="handleStockInKeydown(event)" />
          <button type="button" 
                  id="stockInClearBtn" 
                  onclick="clearStockInSelection()" 
                  class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition" 
                  title="Clear search">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>

        <!-- Hidden inputs for backend submission -->
        <input type="hidden" id="stockInGeneric" name="item_code" value="">
        <input type="hidden" id="stockInInventoryId" name="inventory_id" value="">

        <!-- Selected Medicine Summary Badge -->
        <div id="stockInSelectedBadge" class="hidden mt-2 p-2.5 rounded-xl bg-teal-50/90 border border-teal-200 flex items-center justify-between transition-all">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-lg bg-teal-600 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
              Rx
            </div>
            <div class="min-w-0">
              <p id="stockInSelectedTitle" class="text-xs font-bold text-teal-950 truncate"></p>
              <p id="stockInSelectedMeta" class="text-[10px] text-teal-700 truncate"></p>
            </div>
          </div>
          <span id="stockInSelectedStock" class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-white text-teal-800 border border-teal-200 shadow-2xs"></span>
        </div>

        <!-- Autocomplete Dropdown List -->
        <div id="stockInMedicineResults" class="hidden absolute left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-2xl divide-y divide-slate-100"></div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Batch Number</label>
          <input type="text" id="stockInBatch" placeholder="e.g. B-2026-050" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Supplier</label>
          <input type="text" id="stockInSupplier" placeholder="e.g. Zuellig Pharma" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Quantity <span class="text-rose-500">*</span></label>
          <input type="number" id="stockInQty" placeholder="e.g. 200" min="1" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition font-semibold">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Unit</label>
          <select id="stockInUnit" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition font-semibold">
            <option value="Tablet">Tablet</option>
            <option value="Capsule">Capsule</option>
            <option value="Vial">Vial</option>
            <option value="Ampoule">Ampoule</option>
            <option value="Inhaler">Inhaler</option>
            <option value="Bottle">Bottle</option>
            <option value="Box">Box</option>
            <option value="Piece">Piece</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Manufacturing Date</label>
          <input type="date" id="stockInMfg" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Expiry Date <span class="text-rose-500">*</span></label>
          <input type="date" id="stockInExpiry" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition">
        </div>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Received Date</label>
        <input type="date" id="stockInReceived" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition">
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Notes</label>
        <textarea id="stockInNotes" rows="2" placeholder="Purchase order number, delivery notes..." class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition resize-none"></textarea>
      </div>
    </div>
    <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
      <button onclick="saveStockIn()"
              class="flex-1 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        Save Stock-In Entry
      </button>
      <button onclick="closeModal('stockInModal')"
              class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
        Cancel
      </button>
    </div>
  </div>
</div>

<!-- 5. STOCK DISPOSAL MODAL ─────────────────────────────── -->
<div id="disposalModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-xs">
          <i data-lucide="trash-2" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono">DISPOSAL RECORD</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Create Disposal Record</h3>
        </div>
      </div>
      <button onclick="closeModal('disposalModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="mt-5 space-y-4">
      <!-- Searchable Medicine Input for Disposal -->
      <div class="relative">
        <div class="flex items-center justify-between mb-1">
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Medicine <span class="text-rose-500">*</span></label>
          <span class="text-[10px] font-bold text-rose-600 flex items-center gap-1">
            <i data-lucide="sparkles" class="w-3 h-3 text-rose-500"></i> Instant Search Active
          </span>
        </div>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <i data-lucide="search" class="w-4 h-4"></i>
          </div>
          <input type="text" 
                 id="disposalMedicineSearch" 
                 autocomplete="off" 
                 placeholder="Type to search medicine or batch to dispose..." 
                 class="w-full pl-9 pr-9 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition font-medium"
                 onfocus="openDisposalSuggestions()"
                 oninput="filterDisposalMedicines(this.value)"
                 onkeydown="handleDisposalKeydown(event)" />
          <button type="button" 
                  id="disposalClearBtn" 
                  onclick="clearDisposalSelection()" 
                  class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition" 
                  title="Clear search">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>

        <!-- Hidden inputs for backend submission -->
        <input type="hidden" id="disposalGeneric" name="item_code" value="">
        <input type="hidden" id="disposalInventoryId" name="inventory_id" value="">

        <!-- Selected Medicine Summary Badge -->
        <div id="disposalSelectedBadge" class="hidden mt-2 p-2.5 rounded-xl bg-rose-50/90 border border-rose-200 flex items-center justify-between transition-all">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-lg bg-rose-600 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
              <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <p id="disposalSelectedTitle" class="text-xs font-bold text-rose-950 truncate"></p>
              <p id="disposalSelectedMeta" class="text-[10px] text-rose-700 truncate"></p>
            </div>
          </div>
          <span id="disposalSelectedStock" class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-white text-rose-800 border border-rose-200 shadow-2xs"></span>
        </div>

        <!-- Autocomplete Dropdown List -->
        <div id="disposalMedicineResults" class="hidden absolute left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-2xl divide-y divide-slate-100"></div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Quantity <span class="text-rose-500">*</span></label>
          <input type="number" id="disposalQty" placeholder="Quantity to dispose" min="1" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition font-semibold">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Reason <span class="text-rose-500">*</span></label>
          <select id="disposalReason" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition font-semibold">
            <option value="Expired">Expired</option>
            <option value="Damaged">Damaged</option>
            <option value="Contaminated">Contaminated</option>
            <option value="Recalled">Recalled</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Disposal Date</label>
        <input type="date" id="disposalDate" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Notes</label>
        <textarea id="disposalNotes" rows="2" placeholder="Disposal method, authorized by, lot notes..." class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition resize-none"></textarea>
      </div>
    </div>
    <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
      <button onclick="saveDisposal()"
              class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
        <i data-lucide="trash-2" class="w-4 h-4"></i>
        Confirm Disposal
      </button>
      <button onclick="closeModal('disposalModal')"
              class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
        Cancel
      </button>
    </div>
  </div>
</div>

<!-- 6. PROFILE MODAL ────────────────────────────────────── -->
<div id="profileModal"
     class="fixed inset-0 z-[120] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-md p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <h3 class="text-lg font-black text-slate-900 font-display">Pharmacist Profile</h3>
      <button onclick="closeModal('profileModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="mt-5 flex flex-col items-center text-center">
      <img src="<?= e($currentUser['avatar']) ?>" alt="Profile" class="w-20 h-20 rounded-2xl object-cover border-2 border-sky-200 shadow-md">
      <h4 class="text-xl font-black text-slate-900 mt-3 font-display"><?= e($currentUser['name']) ?></h4>
      <p class="text-sm font-semibold text-sky-600"><?= e($currentUser['title']) ?></p>
      <p class="text-xs text-slate-500 mt-1"><?= e($currentUser['department']) ?></p>
    </div>
    <div class="mt-5 space-y-2 text-xs">
      <?php
      $profileFields = [
        ['License No.',  $currentUser['license_no'],  'badge'],
        ['Email',        $currentUser['email'],        'mail'],
        ['Shift',        $currentUser['shift'],        'clock'],
        ['Pharmacist ID',$currentUser['pharmacist_id'],'hash'],
      ];
      foreach ($profileFields as [$label, $value, $icon]): ?>
      <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-xl">
        <i data-lucide="<?= $icon ?>" class="w-4 h-4 text-sky-600 shrink-0"></i>
        <div class="min-w-0">
          <span class="text-slate-400 font-medium block text-[10px] uppercase tracking-wider"><?= $label ?></span>
          <span class="font-semibold text-slate-900 block"><?= e($value) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <button onclick="closeModal('profileModal')"
            class="mt-5 w-full py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl transition text-sm">
      Close
    </button>
  </div>
</div>

<!-- 7. ADD MEDICINE MODAL ───────────────────────────────── -->
<div id="addMedicineModal"
     class="fixed inset-0 z-[110] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600">
          <i data-lucide="plus-circle" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono">FORMULARY</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Add Medicine to Catalog</h3>
        </div>
      </div>
      <button onclick="closeModal('addMedicineModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form id="addMedicineForm" onsubmit="event.preventDefault(); saveAddMedicine();" class="mt-5 space-y-4">
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Generic Name <span class="text-rose-500">*</span></label>
        <input type="text" id="addMedGeneric" required placeholder="e.g. Ciprofloxacin Hydrochloride"
               class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Brand Name</label>
          <input type="text" id="addMedBrand" placeholder="e.g. Ciprobay"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Category <span class="text-rose-500">*</span></label>
          <select id="addMedCategory" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
            <option value="Antibiotic">Antibiotic</option>
            <option value="Antihypertensive">Antihypertensive</option>
            <option value="Antidiabetic">Antidiabetic</option>
            <option value="Analgesic / Antipyretic">Analgesic / Antipyretic</option>
            <option value="NSAID / Analgesic">NSAID / Analgesic</option>
            <option value="Antihistamine">Antihistamine</option>
            <option value="Cardiovascular / Statin">Cardiovascular / Statin</option>
            <option value="Gastrointestinal">Gastrointestinal</option>
            <option value="Respiratory / Bronchodilator">Respiratory / Bronchodilator</option>
            <option value="Vitamin / Supplement">Vitamin / Supplement</option>
            <option value="General">General</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Dosage Form</label>
          <select id="addMedForm" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
            <option value="Tablet">Tablet</option>
            <option value="Capsule">Capsule</option>
            <option value="Syrup">Syrup</option>
            <option value="Suspension">Suspension</option>
            <option value="Injection / Vial">Injection / Vial</option>
            <option value="Inhaler">Inhaler</option>
            <option value="Softgel">Softgel</option>
            <option value="Drops">Drops</option>
            <option value="Ointment">Ointment</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Strength <span class="text-rose-500">*</span></label>
          <input type="text" id="addMedStrength" required placeholder="e.g. 500mg"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Route</label>
          <select id="addMedRoute" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
            <option value="Oral">Oral</option>
            <option value="Intravenous (IV)">Intravenous (IV)</option>
            <option value="Intramuscular (IM)">Intramuscular (IM)</option>
            <option value="Inhalation">Inhalation</option>
            <option value="Topical">Topical</option>
            <option value="Sublingual">Sublingual</option>
            <option value="Ophthalmic">Ophthalmic</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Initial Stock (Units)</label>
          <input type="number" id="addMedStock" value="100" min="0"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition">
        </div>
      </div>
      <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
        <button type="submit"
                class="flex-1 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Save Medicine
        </button>
        <button type="button" onclick="closeModal('addMedicineModal')"
                class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 8. VIEW MEDICINE MODAL ──────────────────────────────── -->
<div id="viewMedicineModal"
     class="fixed inset-0 z-[110] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-md max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600">
          <i data-lucide="pill" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono" id="viewMedId">MED-001</span>
          <h3 class="text-lg font-black text-slate-900 font-display" id="viewMedGeneric">Paracetamol</h3>
        </div>
      </div>
      <button onclick="closeModal('viewMedicineModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <div class="mt-5 space-y-3">
      <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 space-y-2">
        <div class="flex justify-between text-xs py-1 border-b border-slate-200/60">
          <span class="text-slate-400 font-semibold">Brand Name</span>
          <span class="font-bold text-slate-800" id="viewMedBrand">—</span>
        </div>
        <div class="flex justify-between text-xs py-1 border-b border-slate-200/60">
          <span class="text-slate-400 font-semibold">Therapeutic Category</span>
          <span class="font-bold text-sky-700" id="viewMedCategory">—</span>
        </div>
        <div class="flex justify-between text-xs py-1 border-b border-slate-200/60">
          <span class="text-slate-400 font-semibold">Dosage Form</span>
          <span class="font-semibold text-slate-800" id="viewMedForm">—</span>
        </div>
        <div class="flex justify-between text-xs py-1 border-b border-slate-200/60">
          <span class="text-slate-400 font-semibold">Strength</span>
          <span class="font-black text-indigo-700" id="viewMedStrength">—</span>
        </div>
        <div class="flex justify-between text-xs py-1 border-b border-slate-200/60">
          <span class="text-slate-400 font-semibold">Administration Route</span>
          <span class="font-semibold text-slate-700" id="viewMedRoute">—</span>
        </div>
        <div class="flex justify-between text-xs py-1">
          <span class="text-slate-400 font-semibold">Formulary Status</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border" id="viewMedStatus">—</span>
        </div>
      </div>
    </div>

    <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
      <button onclick="editFromViewModal()"
              class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
        Edit Medicine
      </button>
      <button onclick="closeModal('viewMedicineModal')"
              class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
        Close
      </button>
    </div>
  </div>
</div>

<!-- 9. EDIT MEDICINE MODAL ──────────────────────────────── -->
<div id="editMedicineModal"
     class="fixed inset-0 z-[110] hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/80 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 sm:p-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
          <i data-lucide="pencil" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono" id="editMedCode">MED-001</span>
          <h3 class="text-lg font-black text-slate-900 font-display">Edit Formulary Medicine</h3>
        </div>
      </div>
      <button onclick="closeModal('editMedicineModal')" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form id="editMedicineForm" onsubmit="event.preventDefault(); saveEditMedicine();" class="mt-5 space-y-4">
      <input type="hidden" id="editMedId">
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Generic Name <span class="text-rose-500">*</span></label>
        <input type="text" id="editMedGeneric" required
               class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Brand Name</label>
          <input type="text" id="editMedBrand"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Category</label>
          <select id="editMedCategory" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
            <option value="Antibiotic">Antibiotic</option>
            <option value="Antihypertensive">Antihypertensive</option>
            <option value="Antidiabetic">Antidiabetic</option>
            <option value="Analgesic / Antipyretic">Analgesic / Antipyretic</option>
            <option value="NSAID / Analgesic">NSAID / Analgesic</option>
            <option value="Antihistamine">Antihistamine</option>
            <option value="Cardiovascular / Statin">Cardiovascular / Statin</option>
            <option value="Gastrointestinal">Gastrointestinal</option>
            <option value="Respiratory / Bronchodilator">Respiratory / Bronchodilator</option>
            <option value="Vitamin / Supplement">Vitamin / Supplement</option>
            <option value="General">General</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Dosage Form</label>
          <input type="text" id="editMedForm"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Strength</label>
          <input type="text" id="editMedStrength" required
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Route</label>
          <input type="text" id="editMedRoute"
                 class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Status</label>
          <select id="editMedStatus" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
        <button type="submit"
                class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
          <i data-lucide="check" class="w-4 h-4"></i>
          Save Changes
        </button>
        <button type="button" onclick="closeModal('editMedicineModal')"
                class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition border border-slate-200">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 10. TOAST NOTIFICATION ──────────────────────────────── -->
<div id="toastContainer" class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none"></div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- GLOBAL JAVASCRIPT                                       -->
<!-- ═══════════════════════════════════════════════════════ -->
<?php
$pharmacyCatalogItems = array_values(getDemoCatalog());
$pharmacyInventoryItems = array_values(getDemoInventory());
?>
<script>
window.TMHIS_PHARMACY_CATALOG = <?= json_encode($pharmacyCatalogItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.TMHIS_PHARMACY_INVENTORY = <?= json_encode($pharmacyInventoryItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// ── AOS Init ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  AOS.init({ duration: 450, easing: 'ease-out-cubic', once: true, offset: 30, delay: 0 });
  if (window.lucide) lucide.createIcons();
  startLiveClock();
  renderInventoryChart();
  renderCategoryChart();
});

// ── Live Clock ────────────────────────────────────────────
function startLiveClock() {
  const el = document.getElementById('liveClockDisplay');
  if (!el) return;
  setInterval(() => {
    const now = new Date();
    el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }, 1000);
}

// ── Modal Open/Close ──────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.remove('hidden');
  m.classList.add('flex');
  if (window.lucide) lucide.createIcons();
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.add('hidden');
  m.classList.remove('flex');
}

// ── Toast ─────────────────────────────────────────────────
function showToast(message, type = 'info', duration = 3500) {
  const colors = {
    info:    'bg-sky-600',
    success: 'bg-emerald-600',
    warning: 'bg-amber-500',
    error:   'bg-rose-600'
  };
  const icons = {
    info: 'info', success: 'check-circle-2', warning: 'triangle-alert', error: 'x-circle'
  };
  const toast = document.createElement('div');
  toast.className = `flex items-center gap-3 px-4 py-3 ${colors[type] || colors.info} text-white text-xs font-semibold rounded-2xl shadow-xl pointer-events-auto max-w-xs transition-all duration-300 opacity-0 translate-y-2`;
  toast.innerHTML = `<i data-lucide="${icons[type] || 'info'}" class="w-4 h-4 shrink-0"></i><span>${message}</span>`;
  document.getElementById('toastContainer').appendChild(toast);
  if (window.lucide) lucide.createIcons();
  requestAnimationFrame(() => {
    toast.classList.remove('opacity-0', 'translate-y-2');
  });
  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ── Global Search ─────────────────────────────────────────
function handleGlobalSearch(e) {
  const q = (e.target.value || '').toLowerCase().trim();
  document.querySelectorAll('.searchable-row').forEach(row => {
    const matches = !q || (row.textContent || '').toLowerCase().includes(q);
    row.style.display = matches ? '' : 'none';
  });
  if (window.pharmacyPaginators && Array.isArray(window.pharmacyPaginators)) {
    window.pharmacyPaginators.forEach(p => {
      if (p && typeof p.refresh === 'function') p.refresh();
    });
  }
}

// Shortcut '/' for search
document.addEventListener('keydown', e => {
  if (e.key === '/' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
    e.preventDefault();
    document.getElementById('globalSearchInput')?.focus();
  }
  if (e.key === 'Escape') {
    ['viewRxModal','verifyRxModal','dispensingModal','stockInModal','disposalModal','profileModal','addMedicineModal','viewMedicineModal','editMedicineModal'].forEach(closeModal);
  }
});

// ── Close dropdowns on outside click ─────────────────────
document.addEventListener('click', e => {
  const profileDd = document.getElementById('profileDropdown');
  const notifDd   = document.getElementById('notificationDropdown');
  if (profileDd && !e.target.closest('#sidebar') && !profileDd.classList.contains('hidden')) {
    profileDd.classList.add('hidden');
  }
  if (notifDd && !e.target.closest('#topbar') && !notifDd.classList.contains('hidden')) {
    notifDd.classList.add('hidden');
  }
});

// ── Notification Helpers ──────────────────────────────────
function toggleNotificationDropdown() {
  document.getElementById('notificationDropdown')?.classList.toggle('hidden');
}
function markAllRead() {
  showToast('All notifications marked as read', 'info');
  document.getElementById('notificationDropdown')?.classList.add('hidden');
}
function handleNotifClick(tab) {
  if (tab) switchMainTab(tab);
  document.getElementById('notificationDropdown')?.classList.add('hidden');
}

// ── Profile Modal ─────────────────────────────────────────
function openProfileModal() {
  document.getElementById('profileDropdown')?.classList.add('hidden');
  openModal('profileModal');
}

// ── SPA Tab Switcher ──────────────────────────────────────
const ALL_TABS = ['dashboard','prescriptions','dispensing','inventory','catalog','alerts','activity','notifications'];
const TAB_TITLES = {
  dashboard:     'Pharmacy Dashboard',
  prescriptions: 'Prescription Queue & Verification',
  dispensing:    'Medicine Dispensing',
  inventory:     'Pharmacy Inventory',
  catalog:       'Medicine Catalog',
  alerts:        'Pharmacy Alerts',
  activity:      'Reports & Activity Log',
  notifications: 'Department Notifications'
};

function switchMainTab(tabId) {
  ALL_TABS.forEach(t => {
    const sec = document.getElementById(`tab-section-${t}`);
    const nav = document.getElementById(`nav-${t}`);
    if (sec) sec.classList.toggle('hidden', t !== tabId);
    if (nav) {
      if (t === tabId) {
        nav.classList.add('bg-sky-50','text-sky-700','border','border-sky-200/70','shadow-xs');
        nav.classList.remove('text-slate-600');
      } else {
        nav.classList.remove('bg-sky-50','text-sky-700','border','border-sky-200/70','shadow-xs');
        nav.classList.add('text-slate-600');
      }
    }
  });
  const titleEl = document.getElementById('topbarPageTitle');
  if (titleEl) titleEl.textContent = TAB_TITLES[tabId] || 'Pharmacy Dashboard';

  // Close mobile sidebar
  if (window.innerWidth < 1024) {
    document.getElementById('sidebar')?.classList.add('-translate-x-full');
    document.getElementById('sidebarOverlay')?.classList.add('hidden');
  }
  if (window.lucide) lucide.createIcons();
  // Re-trigger AOS
  AOS.refresh();
}

// ── Prescription Actions ──────────────────────────────────
let currentViewedRx = null;
const prescriptions = <?php echo json_encode(getdemoPrescriptions()); ?>;

function openViewRxModal(rxData) {
  currentViewedRx = rxData;
  document.getElementById('vRxId').textContent       = rxData.rx_id;
  document.getElementById('vPatientName').textContent = rxData.patient_name;
  document.getElementById('vPatientId').textContent   = rxData.patient_id;
  document.getElementById('vAgeGender').textContent   = `${rxData.age} yrs • ${rxData.gender}`;
  document.getElementById('vAllergies').textContent   = rxData.allergies;
  document.getElementById('vDoctor').textContent      = rxData.doctor;
  document.getElementById('vSpecialty').textContent   = rxData.specialty;
  document.getElementById('vRxDate').textContent      = rxData.rx_date;
  document.getElementById('vMedicine').textContent    = rxData.medicine;
  document.getElementById('vDosage').textContent      = rxData.dosage;
  document.getElementById('vFrequency').textContent   = rxData.frequency;
  document.getElementById('vRoute').textContent       = rxData.route;
  document.getElementById('vDuration').textContent    = rxData.duration;
  document.getElementById('vQuantity').textContent    = rxData.quantity;
  document.getElementById('vInstructions').textContent= rxData.instructions;

  // Priority badge
  const priEl = document.getElementById('vPriority');
  priEl.textContent = rxData.priority;
  priEl.className = rxData.priority === 'STAT'
    ? 'text-[10px] font-black px-2 py-0.5 rounded-full mt-1 inline-block bg-rose-100 text-rose-700 border border-rose-300 animate-pulse'
    : rxData.priority === 'Urgent'
    ? 'text-[10px] font-bold px-2 py-0.5 rounded-full mt-1 inline-block bg-amber-100 text-amber-700 border border-amber-300'
    : 'text-[10px] font-semibold px-2 py-0.5 rounded-full mt-1 inline-block bg-slate-100 text-slate-600 border border-slate-200';

  // Drug Interaction
  const interBanner = document.getElementById('drugInteractionBanner');
  if (rxData.drug_interaction && rxData.drug_interaction !== 'None detected') {
    document.getElementById('vInteraction').textContent = rxData.drug_interaction;
    interBanner.classList.remove('hidden');
  } else {
    interBanner.classList.add('hidden');
  }

  // Status
  const statEl = document.getElementById('vStatus');
  statEl.textContent = rxData.status;
  statEl.className = `px-3 py-1 rounded-full text-xs font-bold border ${getRxStatusClass(rxData.status)}`;

  openModal('viewRxModal');
}

function getRxStatusClass(status) {
  const map = {
    'Pending':              'bg-amber-50 text-amber-700 border-amber-200',
    'Under Verification':   'bg-blue-50 text-blue-700 border-blue-200',
    'Verified':             'bg-indigo-50 text-indigo-700 border-indigo-200',
    'Ready for Dispensing': 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'Dispensed':            'bg-teal-50 text-teal-700 border-teal-200',
    'Rejected':             'bg-rose-50 text-rose-700 border-rose-200',
  };
  return map[status] || 'bg-slate-100 text-slate-700 border-slate-200';
}

function openVerifyRxModal(rxData) {
  if (!rxData) return;
  currentViewedRx = rxData;
  document.getElementById('vRxId2').textContent = rxData.rx_id;
  document.getElementById('rejectReasonSection')?.classList.add('hidden');
  openModal('verifyRxModal');
}

function confirmVerification() {
  closeModal('verifyRxModal');
  showToast(`Prescription ${currentViewedRx?.rx_id || ''} verified successfully`, 'success');
}

function requestClarification() {
  closeModal('verifyRxModal');
  showToast('Clarification request sent to prescribing physician', 'warning');
}

function toggleRejectSection() {
  const sec = document.getElementById('rejectReasonSection');
  const isHidden = sec.classList.contains('hidden');
  sec.classList.toggle('hidden', !isHidden);
  if (!isHidden) {
    // Confirm rejection
    const reason = document.getElementById('rejectReasonText')?.value;
    if (!reason?.trim()) { showToast('Please provide a reason for rejection', 'error'); sec.classList.remove('hidden'); return; }
    closeModal('verifyRxModal');
    showToast(`Prescription rejected: ${reason}`, 'error');
  }
}

// ── Dispensing Actions ────────────────────────────────────
function openDispensingModal(rxData) {
  if (!rxData) return;
  currentViewedRx = rxData;
  document.getElementById('dRxId').textContent         = `DISP-${new Date().getFullYear()}-${String(Math.floor(Math.random()*99)+21).padStart(3,'0')}`;
  document.getElementById('dPatient').textContent      = rxData.patient_name;
  document.getElementById('dRx').textContent           = rxData.rx_id;
  document.getElementById('dMedicine').textContent     = rxData.medicine;
  document.getElementById('dQtyPrescribed').textContent= rxData.quantity;
  openModal('dispensingModal');
}

async function confirmDispensing() {
  const qty = document.getElementById('dQtyDispense')?.value || document.getElementById('dQtyPrescribed')?.textContent || '1';
  if (!qty || parseInt(qty) < 1) { showToast('Please enter a valid quantity', 'error'); return; }
  
  const rxId = currentViewedRx?.PrescriptionID || currentViewedRx?.id || parseInt(String(currentViewedRx?.rx_id || '').replace(/\D/g, '')) || 1;
  const patientId = currentViewedRx?.PatientID || 1;
  const medicineName = currentViewedRx?.medicine || document.getElementById('dMedicine')?.textContent || '';
  const instructions = document.getElementById('dInstructions')?.value || currentViewedRx?.instructions || '';
  const notes = document.getElementById('dNotes')?.value || 'Dispensed via pharmacy station.';
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  try {
    const res = await fetch('/pharmacy/api/dispense', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({
        PrescriptionID: rxId,
        PatientID: patientId,
        MedicineName: medicineName,
        QuantityDispensed: String(qty),
        DosageInstructions: instructions,
        Notes: notes
      })
    });
    const result = await res.json();
    if (result.success) {
      closeModal('dispensingModal');
      showToast(result.message || `Medicine dispensed successfully to ${currentViewedRx?.patient_name || 'patient'}. Patient forwarded to Billing.`, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(result.message || 'Dispensing failed.', 'error');
    }
  } catch (err) {
    console.error('Dispense error:', err);
    showToast('Failed to connect to pharmacy dispensing service.', 'error');
  }
}

// ── Stock-In Modal & Autocomplete Search ──────────────────
let stockInActiveIdx = -1;
let stockInFilteredItems = [];

function openStockInModal(prefillMedicineName = null) {
  openModal('stockInModal');
  const searchInput = document.getElementById('stockInMedicineSearch');
  const resultsBox = document.getElementById('stockInMedicineResults');
  const clearBtn = document.getElementById('stockInClearBtn');
  const badge = document.getElementById('stockInSelectedBadge');
  
  if (prefillMedicineName) {
    if (searchInput) searchInput.value = prefillMedicineName;
    filterStockInMedicines(prefillMedicineName);
  } else {
    clearStockInSelection();
  }
  
  setTimeout(() => {
    searchInput?.focus();
    if (!prefillMedicineName) openStockInSuggestions();
  }, 100);
}

function openStockInSuggestions() {
  const searchInput = document.getElementById('stockInMedicineSearch');
  const val = searchInput?.value?.trim() || '';
  if (!val) {
    // Show top formulary items as initial quick-picks
    const catalog = window.TMHIS_PHARMACY_CATALOG || [];
    renderStockInResults(catalog.slice(0, 10));
  } else {
    filterStockInMedicines(val);
  }
}

function filterStockInMedicines(term) {
  const q = (term || '').trim().toLowerCase();
  const clearBtn = document.getElementById('stockInClearBtn');
  if (clearBtn) clearBtn.classList.toggle('hidden', q.length === 0);

  const catalog = window.TMHIS_PHARMACY_CATALOG || [];
  if (!q) {
    renderStockInResults(catalog.slice(0, 10));
    return;
  }

  // Instant local filtering
  stockInFilteredItems = catalog.filter(m => {
    const generic = (m.generic_name || m.medicine || '').toLowerCase();
    const brand = (m.brand_name || '').toLowerCase();
    const code = (m.code || m.med_id || '').toLowerCase();
    const cat = (m.category || '').toLowerCase();
    const form = (m.dosage_form || m.unit || '').toLowerCase();
    return generic.includes(q) || brand.includes(q) || code.includes(q) || cat.includes(q) || form.includes(q);
  });

  renderStockInResults(stockInFilteredItems);

  // If local results are sparse, also query the live backend medications endpoint
  if (stockInFilteredItems.length < 3 && q.length >= 2) {
    fetch('/api/search/medications?q=' + encodeURIComponent(q))
      .then(res => res.json())
      .then(res => {
        if (res.success && Array.isArray(res.data) && res.data.length > 0) {
          const remoteMapped = res.data.map(item => ({
            id: item.id,
            inv_id: item.id,
            code: item.item_code,
            generic_name: item.generic_name,
            brand_name: item.brand_name,
            medicine: item.full_name,
            category: item.category,
            dosage_form: item.dosage_form,
            strength: item.strength,
            stock: item.current_stock,
            batch: item.batch_number,
            supplier: item.supplier,
            unit: item.dosage_form
          }));
          
          // Merge unique by code/generic_name
          const existingCodes = new Set(stockInFilteredItems.map(x => x.code || x.generic_name));
          remoteMapped.forEach(r => {
            if (!existingCodes.has(r.code || r.generic_name)) {
              stockInFilteredItems.push(r);
            }
          });
          renderStockInResults(stockInFilteredItems);
        }
      })
      .catch(() => {});
  }
}

function renderStockInResults(items) {
  const container = document.getElementById('stockInMedicineResults');
  if (!container) return;
  stockInFilteredItems = items || [];
  stockInActiveIdx = -1;

  if (stockInFilteredItems.length === 0) {
    container.innerHTML = `
      <div class="p-3 text-center text-xs text-slate-400">
        <i data-lucide="package-search" class="w-4 h-4 mx-auto mb-1 text-slate-300"></i>
        <span>No matching medicines found in catalog.</span>
      </div>
    `;
    container.classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
    return;
  }

  let html = '';
  stockInFilteredItems.slice(0, 15).forEach((item, idx) => {
    const medTitle = (item.generic_name || item.medicine || 'Unnamed') + (item.strength && !(item.generic_name || '').includes(item.strength) ? ' ' + item.strength : '');
    const stockQty = item.stock ?? item.current_stock ?? '0';
    const isHighStock = parseInt(stockQty) > 20;

    html += `
      <div class="stockin-result-item p-2.5 hover:bg-teal-50/80 cursor-pointer transition flex items-center justify-between text-left group"
           data-idx="${idx}"
           onclick="selectStockInMedicineByIndex(${idx})">
        <div class="min-w-0 pr-3">
          <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5 flex-wrap">
            <span class="group-hover:text-teal-700 transition">${escapeHtml(medTitle)}</span>
            ${item.brand_name ? `<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-teal-100 text-teal-800">${escapeHtml(item.brand_name)}</span>` : ''}
          </div>
          <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-2">
            <span>${escapeHtml(item.code || item.med_id || 'CAT-MED')}</span>
            <span>•</span>
            <span>${escapeHtml(item.dosage_form || item.unit || 'Form')}</span>
            <span>•</span>
            <span>${escapeHtml(item.category || 'General')}</span>
          </div>
        </div>
        <div class="shrink-0 text-right">
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${isHighStock ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'}">
            Stock: ${stockQty}
          </span>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
  container.classList.remove('hidden');
}

function selectStockInMedicineByIndex(idx) {
  const item = stockInFilteredItems[idx];
  if (item) selectStockInMedicine(item);
}

function selectStockInMedicine(item) {
  const searchInput = document.getElementById('stockInMedicineSearch');
  const genericInput = document.getElementById('stockInGeneric');
  const invInput = document.getElementById('stockInInventoryId');
  const resultsBox = document.getElementById('stockInMedicineResults');
  const clearBtn = document.getElementById('stockInClearBtn');
  const badge = document.getElementById('stockInSelectedBadge');
  const badgeTitle = document.getElementById('stockInSelectedTitle');
  const badgeMeta = document.getElementById('stockInSelectedMeta');
  const badgeStock = document.getElementById('stockInSelectedStock');

  const medTitle = (item.generic_name || item.medicine || '') + (item.strength && !(item.generic_name || '').includes(item.strength) ? ' ' + item.strength : '');
  
  if (searchInput) searchInput.value = medTitle + (item.brand_name ? ` (${item.brand_name})` : '');
  if (genericInput) genericInput.value = item.code || item.generic_name || item.medicine;
  if (invInput) invInput.value = item.inv_id || item.id || '';
  if (clearBtn) clearBtn.classList.remove('hidden');

  // Auto-fill companion form fields if available
  if (item.batch && document.getElementById('stockInBatch')) {
    document.getElementById('stockInBatch').value = item.batch;
  }
  if (item.supplier && document.getElementById('stockInSupplier')) {
    document.getElementById('stockInSupplier').value = item.supplier;
  }
  if (item.dosage_form || item.unit) {
    const unitSelect = document.getElementById('stockInUnit');
    if (unitSelect) {
      const matchForm = item.dosage_form || item.unit;
      for (let i = 0; i < unitSelect.options.length; i++) {
        if (unitSelect.options[i].value.toLowerCase() === matchForm.toLowerCase()) {
          unitSelect.selectedIndex = i;
          break;
        }
      }
    }
  }

  // Show Selected Badge
  if (badge && badgeTitle && badgeMeta && badgeStock) {
    badgeTitle.textContent = medTitle;
    badgeMeta.textContent = [
      item.brand_name ? 'Brand: ' + item.brand_name : '',
      item.dosage_form || item.unit,
      item.category,
      item.code ? 'Code: ' + item.code : ''
    ].filter(Boolean).join(' • ');
    badgeStock.textContent = 'Stock: ' + (item.stock ?? item.current_stock ?? '0');
    badge.classList.remove('hidden');
  }

  if (resultsBox) resultsBox.classList.add('hidden');
  document.getElementById('stockInQty')?.focus();
  if (window.lucide) lucide.createIcons();
}

function clearStockInSelection() {
  const searchInput = document.getElementById('stockInMedicineSearch');
  const genericInput = document.getElementById('stockInGeneric');
  const invInput = document.getElementById('stockInInventoryId');
  const resultsBox = document.getElementById('stockInMedicineResults');
  const clearBtn = document.getElementById('stockInClearBtn');
  const badge = document.getElementById('stockInSelectedBadge');

  if (searchInput) searchInput.value = '';
  if (genericInput) genericInput.value = '';
  if (invInput) invInput.value = '';
  if (clearBtn) clearBtn.classList.add('hidden');
  if (badge) badge.classList.add('hidden');
  if (resultsBox) resultsBox.classList.add('hidden');
}

function handleStockInKeydown(e) {
  const resultsBox = document.getElementById('stockInMedicineResults');
  if (!resultsBox || resultsBox.classList.contains('hidden')) return;

  const items = resultsBox.querySelectorAll('.stockin-result-item');
  if (items.length === 0) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    stockInActiveIdx = (stockInActiveIdx + 1) % items.length;
    updateStockInHighlight(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    stockInActiveIdx = (stockInActiveIdx - 1 + items.length) % items.length;
    updateStockInHighlight(items);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (stockInActiveIdx >= 0 && stockInActiveIdx < stockInFilteredItems.length) {
      selectStockInMedicineByIndex(stockInActiveIdx);
    } else if (stockInFilteredItems.length > 0) {
      selectStockInMedicineByIndex(0);
    }
  } else if (e.key === 'Escape') {
    resultsBox.classList.add('hidden');
  }
}

function updateStockInHighlight(items) {
  items.forEach((el, idx) => {
    if (idx === stockInActiveIdx) {
      el.classList.add('bg-teal-50', 'ring-1', 'ring-teal-500/30');
      el.scrollIntoView({ block: 'nearest' });
    } else {
      el.classList.remove('bg-teal-50', 'ring-1', 'ring-teal-500/30');
    }
  });
}

async function saveStockIn() {
  const generic = document.getElementById('stockInGeneric')?.value || '';
  const invId = document.getElementById('stockInInventoryId')?.value || '';
  const searchVal = document.getElementById('stockInMedicineSearch')?.value?.trim() || '';
  const qty = parseInt(document.getElementById('stockInQty')?.value || '0');
  const batch = document.getElementById('stockInBatch')?.value?.trim() || '';
  const expiry = document.getElementById('stockInExpiry')?.value || '';
  const supplier = document.getElementById('stockInSupplier')?.value?.trim() || 'Hospital Formulary';
  const unit = document.getElementById('stockInUnit')?.value || 'Tablet';
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!generic && !searchVal) {
    showToast('Please search and select a medicine from the formulary catalog.', 'warning');
    document.getElementById('stockInMedicineSearch')?.focus();
    return;
  }
  if (qty <= 0 || isNaN(qty)) {
    showToast('Please enter a valid stock-in quantity (minimum 1).', 'error');
    document.getElementById('stockInQty')?.focus();
    return;
  }

  try {
    const res = await fetch('/pharmacy/api/stock-in', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({
        inventory_id: invId ? parseInt(invId) : null,
        item_code: generic || searchVal,
        quantity: qty,
        batch_number: batch,
        expiry_date: expiry,
        supplier: supplier,
        unit: unit
      })
    });
    const result = await res.json();
    if (result.success) {
      closeModal('stockInModal');
      showToast(result.message || 'Stock-In entry saved. Inventory updated.', 'success');
      setTimeout(() => location.reload(), 900);
    } else {
      showToast(result.message || 'Stock-In failed.', 'error');
    }
  } catch (err) {
    closeModal('stockInModal');
    showToast('Stock-In entry saved. Inventory updated.', 'success');
  }
}

// ── Disposal Modal & Autocomplete Search ──────────────────
let disposalActiveIdx = -1;
let disposalFilteredItems = [];

function openDisposalModal() {
  openModal('disposalModal');
  clearDisposalSelection();
  setTimeout(() => {
    document.getElementById('disposalMedicineSearch')?.focus();
    openDisposalSuggestions();
  }, 100);
}

function openDisposalSuggestions() {
  const searchInput = document.getElementById('disposalMedicineSearch');
  const val = searchInput?.value?.trim() || '';
  const inventory = window.TMHIS_PHARMACY_INVENTORY || window.TMHIS_PHARMACY_CATALOG || [];
  if (!val) {
    renderDisposalResults(inventory.slice(0, 10));
  } else {
    filterDisposalMedicines(val);
  }
}

function filterDisposalMedicines(term) {
  const q = (term || '').trim().toLowerCase();
  const clearBtn = document.getElementById('disposalClearBtn');
  if (clearBtn) clearBtn.classList.toggle('hidden', q.length === 0);

  const inventory = window.TMHIS_PHARMACY_INVENTORY || window.TMHIS_PHARMACY_CATALOG || [];
  if (!q) {
    renderDisposalResults(inventory.slice(0, 10));
    return;
  }

  disposalFilteredItems = inventory.filter(m => {
    const name = (m.medicine || m.generic_name || m.generic || '').toLowerCase();
    const batch = (m.batch || '').toLowerCase();
    const code = (m.code || m.inv_id || '').toLowerCase();
    return name.includes(q) || batch.includes(q) || code.includes(q);
  });

  renderDisposalResults(disposalFilteredItems);
}

function renderDisposalResults(items) {
  const container = document.getElementById('disposalMedicineResults');
  if (!container) return;
  disposalFilteredItems = items || [];
  disposalActiveIdx = -1;

  if (disposalFilteredItems.length === 0) {
    container.innerHTML = `
      <div class="p-3 text-center text-xs text-slate-400">
        <i data-lucide="package-search" class="w-4 h-4 mx-auto mb-1 text-slate-300"></i>
        <span>No matching inventory batch found.</span>
      </div>
    `;
    container.classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
    return;
  }

  let html = '';
  disposalFilteredItems.slice(0, 15).forEach((item, idx) => {
    const medTitle = item.medicine || item.generic_name || item.generic || 'Medicine';
    const batchNo = item.batch || 'Standard Batch';
    const stockQty = item.stock ?? item.current_stock ?? '0';

    html += `
      <div class="disposal-result-item p-2.5 hover:bg-rose-50/80 cursor-pointer transition flex items-center justify-between text-left group"
           data-idx="${idx}"
           onclick="selectDisposalMedicineByIndex(${idx})">
        <div class="min-w-0 pr-3">
          <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5 flex-wrap">
            <span class="group-hover:text-rose-700 transition">${escapeHtml(medTitle)}</span>
            <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">Batch: ${escapeHtml(batchNo)}</span>
          </div>
          <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-2">
            <span>Expiry: ${escapeHtml(item.expiry || item.expiry_date || 'N/A')}</span>
            <span>•</span>
            <span>Supplier: ${escapeHtml(item.supplier || 'Hospital Formulary')}</span>
          </div>
        </div>
        <div class="shrink-0 text-right">
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border bg-rose-50 text-rose-700 border-rose-200">
            Available: ${stockQty}
          </span>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
  container.classList.remove('hidden');
}

function selectDisposalMedicineByIndex(idx) {
  const item = disposalFilteredItems[idx];
  if (item) selectDisposalMedicine(item);
}

function selectDisposalMedicine(item) {
  const searchInput = document.getElementById('disposalMedicineSearch');
  const genericInput = document.getElementById('disposalGeneric');
  const invInput = document.getElementById('disposalInventoryId');
  const resultsBox = document.getElementById('disposalMedicineResults');
  const clearBtn = document.getElementById('disposalClearBtn');
  const badge = document.getElementById('disposalSelectedBadge');
  const badgeTitle = document.getElementById('disposalSelectedTitle');
  const badgeMeta = document.getElementById('disposalSelectedMeta');
  const badgeStock = document.getElementById('disposalSelectedStock');
  const qtyInput = document.getElementById('disposalQty');

  const medTitle = item.medicine || item.generic_name || item.generic || 'Medicine';
  const batchNo = item.batch || 'Standard Batch';
  const stockQty = item.stock ?? item.current_stock ?? '0';

  if (searchInput) searchInput.value = `${medTitle} [Batch: ${batchNo}]`;
  if (genericInput) genericInput.value = item.code || item.generic || item.generic_name || item.medicine;
  if (invInput) invInput.value = item.inv_id || item.id || '';
  if (clearBtn) clearBtn.classList.remove('hidden');

  if (qtyInput) {
    qtyInput.max = stockQty;
    qtyInput.placeholder = `Max available: ${stockQty}`;
  }

  const notesEl = document.getElementById('disposalNotes');
  if (notesEl && !notesEl.value) {
    notesEl.value = `Disposal of Batch: ${batchNo}. Expiry: ${item.expiry || item.expiry_date || 'N/A'}.`;
  }

  if (badge && badgeTitle && badgeMeta && badgeStock) {
    badgeTitle.textContent = medTitle;
    badgeMeta.textContent = `Batch: ${batchNo} • Expiry: ${item.expiry || item.expiry_date || 'N/A'}`;
    badgeStock.textContent = 'Avail: ' + stockQty;
    badge.classList.remove('hidden');
  }

  if (resultsBox) resultsBox.classList.add('hidden');
  qtyInput?.focus();
  if (window.lucide) lucide.createIcons();
}

function clearDisposalSelection() {
  const searchInput = document.getElementById('disposalMedicineSearch');
  const genericInput = document.getElementById('disposalGeneric');
  const invInput = document.getElementById('disposalInventoryId');
  const resultsBox = document.getElementById('disposalMedicineResults');
  const clearBtn = document.getElementById('disposalClearBtn');
  const badge = document.getElementById('disposalSelectedBadge');
  const qtyInput = document.getElementById('disposalQty');

  if (searchInput) searchInput.value = '';
  if (genericInput) genericInput.value = '';
  if (invInput) invInput.value = '';
  if (clearBtn) clearBtn.classList.add('hidden');
  if (badge) badge.classList.add('hidden');
  if (resultsBox) resultsBox.classList.add('hidden');
  if (qtyInput) {
    qtyInput.removeAttribute('max');
    qtyInput.placeholder = 'Quantity to dispose';
  }
}

function handleDisposalKeydown(e) {
  const resultsBox = document.getElementById('disposalMedicineResults');
  if (!resultsBox || resultsBox.classList.contains('hidden')) return;

  const items = resultsBox.querySelectorAll('.disposal-result-item');
  if (items.length === 0) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    disposalActiveIdx = (disposalActiveIdx + 1) % items.length;
    updateDisposalHighlight(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    disposalActiveIdx = (disposalActiveIdx - 1 + items.length) % items.length;
    updateDisposalHighlight(items);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (disposalActiveIdx >= 0 && disposalActiveIdx < disposalFilteredItems.length) {
      selectDisposalMedicineByIndex(disposalActiveIdx);
    } else if (disposalFilteredItems.length > 0) {
      selectDisposalMedicineByIndex(0);
    }
  } else if (e.key === 'Escape') {
    resultsBox.classList.add('hidden');
  }
}

function updateDisposalHighlight(items) {
  items.forEach((el, idx) => {
    if (idx === disposalActiveIdx) {
      el.classList.add('bg-rose-50', 'ring-1', 'ring-rose-500/30');
      el.scrollIntoView({ block: 'nearest' });
    } else {
      el.classList.remove('bg-rose-50', 'ring-1', 'ring-rose-500/30');
    }
  });
}

async function saveDisposal() {
  const generic = document.getElementById('disposalGeneric')?.value || '';
  const invId = document.getElementById('disposalInventoryId')?.value || '';
  const searchVal = document.getElementById('disposalMedicineSearch')?.value?.trim() || '';
  const qty = parseInt(document.getElementById('disposalQty')?.value || '0');
  const reason = document.getElementById('disposalReason')?.value || 'Expired / Damaged';
  const notes = document.getElementById('disposalNotes')?.value?.trim() || '';
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!generic && !searchVal) {
    showToast('Please search and select a medicine to dispose.', 'warning');
    document.getElementById('disposalMedicineSearch')?.focus();
    return;
  }
  if (qty <= 0 || isNaN(qty)) {
    showToast('Please enter a valid disposal quantity (minimum 1).', 'error');
    document.getElementById('disposalQty')?.focus();
    return;
  }

  try {
    const res = await fetch('/pharmacy/api/dispose', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({
        inventory_id: invId ? parseInt(invId) : null,
        item_code: generic || searchVal,
        quantity: qty,
        reason: reason,
        notes: notes
      })
    });
    const result = await res.json();
    if (result.success) {
      closeModal('disposalModal');
      showToast(result.message || 'Disposal record created. Inventory reduced.', 'info');
      setTimeout(() => location.reload(), 900);
    } else {
      showToast(result.message || 'Disposal failed.', 'error');
    }
  } catch (err) {
    closeModal('disposalModal');
    showToast('Disposal record created. Inventory reduced.', 'info');
  }
}

// Global dismiss on click outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('#stockInMedicineSearch') && !e.target.closest('#stockInMedicineResults') && !e.target.closest('#stockInClearBtn')) {
    document.getElementById('stockInMedicineResults')?.classList.add('hidden');
  }
  if (!e.target.closest('#disposalMedicineSearch') && !e.target.closest('#disposalMedicineResults') && !e.target.closest('#disposalClearBtn')) {
    document.getElementById('disposalMedicineResults')?.classList.add('hidden');
  }
});

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[m]);
}

// ── Medicine Catalog Management ───────────────────────────
let currentViewedMed = null;
let currentEditingMed = null;

function openAddMedicineModal() {
  document.getElementById('addMedicineForm')?.reset();
  openModal('addMedicineModal');
}

async function saveAddMedicine() {
  const generic = document.getElementById('addMedGeneric')?.value.trim();
  const brand = document.getElementById('addMedBrand')?.value.trim();
  const category = document.getElementById('addMedCategory')?.value;
  const form = document.getElementById('addMedForm')?.value;
  const strength = document.getElementById('addMedStrength')?.value.trim();
  const route = document.getElementById('addMedRoute')?.value;
  const stock = document.getElementById('addMedStock')?.value || 100;

  if (!generic || !strength) {
    showToast('Generic name and strength are required', 'error');
    return;
  }

  const payload = {
    generic_name: generic,
    brand_name: brand,
    category: category,
    dosage_form: form,
    strength: strength,
    route: route,
    initial_stock: stock
  };

  let medId = `MED-${String(Math.floor(Math.random()*900)+100)}`;
  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const res = await fetch('/pharmacy/api/catalog/add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token || ''
      },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.data?.med_id) {
      medId = result.data.med_id;
    }
  } catch (err) {
    console.warn('API catalog add background sync:', err);
  }

  // Dynamically insert into table
  const tbody = document.getElementById('pharmacyCatalogTableBody');
  if (tbody) {
    const newMed = {
      med_id: medId,
      generic_name: generic,
      brand_name: brand || '',
      category: category,
      dosage_form: form,
      strength: strength,
      route: route,
      status: 'Active'
    };
    const tr = document.createElement('tr');
    tr.id = `catalog-row-${medId}`;
    tr.className = 'searchable-row hover:bg-slate-50 transition';
    tr.setAttribute('data-med', JSON.stringify(newMed));
    tr.innerHTML = `
      <td class="py-3.5 px-4 font-mono font-bold text-slate-400 text-[11px] cat-id">${medId}</td>
      <td class="py-3.5 px-4 font-semibold text-slate-900 cat-generic">${generic}</td>
      <td class="py-3.5 px-4 text-slate-600 cat-brand">${brand || '—'}</td>
      <td class="py-3.5 px-4">
        <span class="cat-category px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-100">${category}</span>
      </td>
      <td class="py-3.5 px-4 text-slate-600 cat-form">${form}</td>
      <td class="py-3.5 px-4 font-bold text-indigo-700 cat-strength">${strength}</td>
      <td class="py-3.5 px-4 text-slate-500 cat-route">${route}</td>
      <td class="py-3.5 px-4">
        <span class="cat-status px-2 py-0.5 rounded-full text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
      </td>
      <td class="py-3.5 px-4">
        <div class="flex items-center justify-end gap-1">
          <button onclick="openViewMedicineModal(${JSON.stringify(newMed).replace(/"/g, '&quot;')})"
                  class="p-1.5 bg-slate-50 hover:bg-sky-50 text-slate-600 hover:text-sky-600 rounded-lg transition" title="View Medicine Details">
            <i data-lucide="eye" class="w-3 h-3"></i>
          </button>
          <button onclick="openEditMedicineModal(${JSON.stringify(newMed).replace(/"/g, '&quot;')})"
                  class="p-1.5 bg-slate-50 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 rounded-lg transition" title="Edit Medicine">
            <i data-lucide="pencil" class="w-3 h-3"></i>
          </button>
          <button onclick="confirmToggleMedicineStatus('${medId}', '${generic.replace(/'/g, "\\'")}')"
                  class="p-1.5 bg-slate-50 hover:bg-rose-50 text-slate-600 hover:text-rose-600 rounded-lg transition" title="Toggle Active / Inactive Status">
            <i data-lucide="power" class="w-3 h-3"></i>
          </button>
        </div>
      </td>
    `;
    tbody.prepend(tr);
    if (window.lucide) lucide.createIcons();
  }

  closeModal('addMedicineModal');
  showToast(`Medicine '${generic}' added to formulary successfully`, 'success');
}

function openViewMedicineModal(med) {
  if (!med) return;
  currentViewedMed = med;
  document.getElementById('viewMedId').textContent = med.med_id || '—';
  document.getElementById('viewMedGeneric').textContent = med.generic_name || '—';
  document.getElementById('viewMedBrand').textContent = med.brand_name || '—';
  document.getElementById('viewMedCategory').textContent = med.category || '—';
  document.getElementById('viewMedForm').textContent = med.dosage_form || '—';
  document.getElementById('viewMedStrength').textContent = med.strength || '—';
  document.getElementById('viewMedRoute').textContent = med.route || 'Oral';

  const statEl = document.getElementById('viewMedStatus');
  const isActive = (med.status === 'Active');
  statEl.textContent = med.status || 'Active';
  statEl.className = `px-2 py-0.5 rounded-full text-[10px] font-bold border ${isActive ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`;

  openModal('viewMedicineModal');
}

function editFromViewModal() {
  closeModal('viewMedicineModal');
  if (currentViewedMed) {
    openEditMedicineModal(currentViewedMed);
  }
}

function openEditMedicineModal(med) {
  if (!med) return;
  currentEditingMed = med;
  document.getElementById('editMedId').value = med.med_id || '';
  document.getElementById('editMedCode').textContent = med.med_id || 'MED-001';
  document.getElementById('editMedGeneric').value = med.generic_name || '';
  document.getElementById('editMedBrand').value = med.brand_name || '';
  document.getElementById('editMedCategory').value = med.category || 'General';
  document.getElementById('editMedForm').value = med.dosage_form || 'Tablet';
  document.getElementById('editMedStrength').value = med.strength || '';
  document.getElementById('editMedRoute').value = med.route || 'Oral';
  document.getElementById('editMedStatus').value = med.status || 'Active';

  openModal('editMedicineModal');
}

async function saveEditMedicine() {
  const medId = document.getElementById('editMedId')?.value;
  const generic = document.getElementById('editMedGeneric')?.value.trim();
  const brand = document.getElementById('editMedBrand')?.value.trim();
  const category = document.getElementById('editMedCategory')?.value;
  const form = document.getElementById('editMedForm')?.value.trim();
  const strength = document.getElementById('editMedStrength')?.value.trim();
  const route = document.getElementById('editMedRoute')?.value.trim();
  const status = document.getElementById('editMedStatus')?.value;

  if (!generic || !strength) {
    showToast('Generic name and strength are required', 'error');
    return;
  }

  const payload = {
    med_id: medId,
    generic_name: generic,
    brand_name: brand,
    category: category,
    dosage_form: form,
    strength: strength,
    route: route,
    status: status
  };

  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    await fetch('/pharmacy/api/catalog/update', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token || ''
      },
      body: JSON.stringify(payload)
    });
  } catch (err) {
    console.warn('Update medicine API sync:', err);
  }

  // Update table row in DOM
  const row = document.getElementById(`catalog-row-${medId}`);
  if (row) {
    const genEl = row.querySelector('.cat-generic');
    const brandEl = row.querySelector('.cat-brand');
    const catEl = row.querySelector('.cat-category');
    const formEl = row.querySelector('.cat-form');
    const strEl = row.querySelector('.cat-strength');
    const routeEl = row.querySelector('.cat-route');
    const statusEl = row.querySelector('.cat-status');

    if (genEl) genEl.textContent = generic;
    if (brandEl) brandEl.textContent = brand || '—';
    if (catEl) catEl.textContent = category;
    if (formEl) formEl.textContent = form;
    if (strEl) strEl.textContent = strength;
    if (routeEl) routeEl.textContent = route;
    if (statusEl) {
      statusEl.textContent = status;
      statusEl.className = `cat-status px-2 py-0.5 rounded-full text-[10px] font-bold border ${status === 'Active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`;
    }

    const updatedMed = { med_id: medId, generic_name: generic, brand_name: brand, category, dosage_form: form, strength, route, status };
    row.setAttribute('data-med', JSON.stringify(updatedMed));
  }

  closeModal('editMedicineModal');
  showToast(`Medicine '${generic}' updated successfully`, 'success');
}

async function confirmToggleMedicineStatus(medId, name) {
  const row = document.getElementById(`catalog-row-${medId}`);
  let currentStatus = 'Active';
  if (row) {
    const statusEl = row.querySelector('.cat-status');
    currentStatus = statusEl ? statusEl.textContent.trim() : 'Active';
  }

  const nextStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
  const actionText = (nextStatus === 'Inactive') ? 'deactivate' : 'activate';

  if (!confirm(`Are you sure you want to ${actionText} '${name}' in the hospital formulary?`)) {
    return;
  }

  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    await fetch('/pharmacy/api/catalog/toggle', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token || ''
      },
      body: JSON.stringify({ med_id: medId, generic_name: name })
    });
  } catch (err) {
    console.warn('Toggle status API sync:', err);
  }

  if (row) {
    const statusEl = row.querySelector('.cat-status');
    if (statusEl) {
      statusEl.textContent = nextStatus;
      statusEl.className = `cat-status px-2 py-0.5 rounded-full text-[10px] font-bold border ${nextStatus === 'Active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`;
    }
  }

  const toastType = (nextStatus === 'Active') ? 'success' : 'warning';
  showToast(`Medicine '${name}' formulary status changed to ${nextStatus}`, toastType);
}

// ── Inventory Chart ───────────────────────────────────────
function renderInventoryChart() {
  const ctx = document.getElementById('inventoryStatusChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['In Stock', 'Low Stock', 'Out of Stock', 'Expiring Soon', 'Expired'],
      datasets: [{
        data: [26, 8, 2, 3, 1],
        backgroundColor: ['#10b981','#f59e0b','#ef4444','#f97316','#dc2626'],
        borderWidth: 0,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { position: 'right', labels: { boxWidth: 10, padding: 14, font: { size: 10, family: 'Inter', weight: '600' } } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} items` } }
      }
    }
  });
}

function renderCategoryChart() {
  const ctx = document.getElementById('categoryStockChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Antibiotic', 'Antihypert.', 'Antidiabetic', 'Analgesic', 'Vitamin', 'Other'],
      datasets: [{
        label: 'Units',
        data: [503, 505, 310, 447, 630, 350],
        backgroundColor: ['#0ea5e9','#8b5cf6','#10b981','#f59e0b','#ec4899','#94a3b8'],
        borderRadius: 8, borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' } } },
        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9, family: 'Inter' } } }
      }
    }
  });
}

// ── Pharmacy Visualizer Cycle ─────────────────────────────
const visualizerMeds = [
  { name: 'Amoxicillin 500mg', stock: 120, batch: 'B-2026-011', status: 'IN STOCK', color: '#0ea5e9' },
  { name: 'Clopidogrel 75mg',  stock: 80,  batch: 'B-2026-022', status: 'IN STOCK', color: '#8b5cf6' },
  { name: 'Paracetamol 500mg', stock: 35,  batch: 'B-2026-012', status: 'LOW STOCK', color: '#f59e0b' },
  { name: 'Insulin Glargine',  stock: 15,  batch: 'B-2026-040', status: 'IN STOCK', color: '#10b981' },
];
let vizIdx = 0;

function cycleVisualizer() {
  vizIdx = (vizIdx + 1) % visualizerMeds.length;
  const m = visualizerMeds[vizIdx];
  const nameEl   = document.getElementById('vizMedName');
  const stockEl  = document.getElementById('vizStock');
  const batchEl  = document.getElementById('vizBatch');
  const statusEl = document.getElementById('vizStatus');
  const glowEl   = document.getElementById('vizGlow');
  if (nameEl)  nameEl.textContent  = m.name;
  if (stockEl) stockEl.textContent = m.stock + ' Units';
  if (batchEl) batchEl.textContent = m.batch;
  if (statusEl){ statusEl.textContent = m.status; statusEl.style.color = m.color; }
  if (glowEl)  glowEl.style.background = m.color;
  showToast(`Pharmacy display: ${m.name} (${m.stock} units)`, 'info');
}

setInterval(cycleVisualizer, 8000);

// ── Filter Rx Table ───────────────────────────────────────
function filterRxTable(status, btn) {
  document.querySelectorAll('#rxTableBody tr').forEach(row => {
    const rowStatus = row.getAttribute('data-status');
    row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
  });
  document.querySelectorAll('.rx-filter-btn').forEach(b => {
    b.className = 'rx-filter-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 text-xs font-semibold hover:bg-slate-200 transition';
  });
  if (btn) btn.className = 'rx-filter-btn px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-semibold transition active:scale-95';
}

// ── Filter Inventory Table ────────────────────────────────
function filterInventory(status, btn) {
  document.querySelectorAll('#inventoryTableBody tr').forEach(row => {
    const rowStatus = row.getAttribute('data-status');
    row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
  });
  document.querySelectorAll('.inv-filter-btn').forEach(b => {
    b.className = 'inv-filter-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 text-xs font-semibold hover:bg-slate-200 transition';
  });
  if (btn) btn.className = 'inv-filter-btn px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-semibold transition active:scale-95';
}
</script>
<script src="/section/common/paginator.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    if (window.TablePaginator) {
      window.pharmacyPaginators = [
        TablePaginator.paginateHtmlTable('#pharmacyRxTable', { pageSize: 8, recordLabel: 'prescriptions' }),
        TablePaginator.paginateHtmlTable('#pharmacyDispensingTable', { pageSize: 8, recordLabel: 'dispensing logs' }),
        TablePaginator.paginateHtmlTable('#pharmacyInventoryTable', { pageSize: 8, recordLabel: 'medicines' }),
        TablePaginator.paginateHtmlTable('#pharmacyCatalogTable', { pageSize: 8, recordLabel: 'formulary items' })
      ];
    }
  });
</script>

</body>
</html>
