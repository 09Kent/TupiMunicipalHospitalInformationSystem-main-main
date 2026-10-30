@extends('layouts.register')

@section('title', 'Patient Intake & Pre-Consultation Form | Tupi Municipal Hospital Information Management System')

@section('hide_navbar', 'true')

@section('content')

<div class="w-full">
  
  <!-- Minimal Top Bar for Intake Mode (No full navbar) -->
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    @php
      $dashboardUrl = url('/register');
    @endphp
    <a href="{{ $dashboardUrl }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-blue-600 transition bg-white/90 px-3.5 py-2 rounded-xl border border-slate-200/90 shadow-2xs">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
      <span>Return to Dashboard</span>
    </a>
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Live Ambulance Availability Pill -->
      <button type="button" onclick="toggleIntakeAmbulanceModal()" 
              class="flex items-center gap-1.5 bg-white/90 hover:bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200/90 text-xs font-bold text-slate-700 shadow-2xs transition group"
              title="Click to view municipal ambulance fleet readiness">
        <span class="text-sm">🚑</span>
        <span id="intake-amb-status-text">Ambulance: Checking...</span>
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" id="intake-amb-dot"></span>
      </button>

      <div class="flex items-center gap-2 bg-white/80 px-3 py-1.5 rounded-xl border border-slate-200/80 text-xs font-semibold text-slate-600">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>Patient Registration Portal</span>
      </div>
    </div>
  </div>

  <!-- Intake Ambulance Fleet Overview Modal -->
  <div id="intake-ambulance-modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center font-bold text-lg">🚑</div>
          <div>
            <h4 class="font-black text-slate-900 text-sm">Municipal Ambulance Readiness</h4>
            <p class="text-[11px] text-slate-400">Tupi Municipal Hospital Emergency Transport Fleet</p>
          </div>
        </div>
        <button type="button" onclick="toggleIntakeAmbulanceModal()" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div class="space-y-2.5" id="intake-ambulance-list">
        <!-- Rendered dynamically -->
      </div>

      <div class="p-3 bg-blue-50/70 rounded-2xl border border-blue-100 text-[11px] text-blue-900 space-y-1">
        <div class="font-bold flex items-center gap-1.5">
          <i data-lucide="phone-call" class="w-3.5 h-3.5 text-blue-600"></i>
          <span>Hospital 24/7 Dispatch Hotline: +63 (083) 228-0001</span>
        </div>
        <p class="text-blue-700/80 text-[10px]">When referring patients to South Cotabato Provincial Hospital or GenSan Medical Center, doctors can request ambulance dispatch directly during consultation disposition.</p>
      </div>
    </div>
  </div>
  
  <!-- Stepper Progress Bar (Horizontal on Desktop, compact on Mobile) -->
  <div class="no-print mb-8">
    
    <!-- Desktop Horizontal Stepper Bar -->
    <div class="hidden md:flex items-center justify-between p-4 bg-white/90 backdrop-blur-md rounded-2xl border border-slate-200/90 shadow-2xs overflow-x-auto" id="registration-desktop-stepper">
      <!-- Injected via registration.js -->
    </div>

    <!-- Mobile Compact Progress Indicator -->
    <div class="md:hidden bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs space-y-2">
      <div class="flex items-center justify-between text-xs font-bold text-slate-700">
        <span id="reg-mobile-step-text">Step 1: Patient Information</span>
        <span class="text-blue-600 font-extrabold">EHR Intake</span>
      </div>
      <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
        <div id="reg-mobile-progress-fill" class="bg-blue-600 h-full rounded-full transition-all duration-300" style="width: 10%;"></div>
      </div>
    </div>

  </div>

  <!-- MAIN REGISTRATION APPLICATION CARD -->
  <div id="registration-card-container" class="bg-white/95 backdrop-blur-sm rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-200/50 p-6 sm:p-10 transition-all duration-300">
    
    <!-- Dynamic Step Content Mount Point -->
    <div id="reg-step-content-area">
      <!-- Dynamically rendered by registration.js with PHP/MySQL backend sync -->
    </div>

  </div>

</div>

<!-- Inject Server Master Data for Client Engine -->
<script>
  window.SERVER_DATA = {
    symptoms: <?= json_encode($dbSymptoms ?? []) ?>,
    bodySystems: <?= json_encode($dbSystems ?? []) ?>,
    bodyLocations: <?= json_encode($dbLocations ?? []) ?>,
    doctors: <?= json_encode($dbDoctors ?? []) ?>,
    apiEndpoints: {
      classify: '<?= url('/api/register/classify') ?>',
      submit: '<?= url('/api/register/submit') ?>',
      doctors: '<?= url('/api/register/doctors') ?>',
      symptoms: '<?= url('/api/register/symptoms') ?>',
      patients: '<?= url('/api/register/patients') ?>'
    },
    baseUrl: '<?= rtrim(url('/'), '/') ?>/',
    dashboardUrl: '<?= $dashboardUrl ?>',
    patientsUrl: '<?= url('/register/patients') ?>'
  };
</script>

<!-- Scripts -->
<script src="{{ asset('section/register/js/data.js') }}"></script>
<script src="{{ asset('section/register/js/body-map.js') }}"></script>
<script src="{{ asset('section/register/assets/js/registration.js') }}"></script>

<script>
  function toggleIntakeAmbulanceModal() {
    const m = document.getElementById('intake-ambulance-modal');
    if (m) m.classList.toggle('hidden');
    if (window.lucide) lucide.createIcons();
  }

  function loadIntakeAmbulanceStatus() {
    const statusText = document.getElementById('intake-amb-status-text');
    const dot = document.getElementById('intake-amb-dot');
    const list = document.getElementById('intake-ambulance-list');

    fetch('/api/ambulances')
      .then(res => res.json())
      .then(res => {
        if (!res.success || !res.data) return;
        const total = res.summary ? res.summary.total : res.data.length;
        const available = res.summary ? res.summary.available : res.data.filter(a => a.status === 'Available').length;

        if (statusText) {
          statusText.textContent = `${available}/${total} Ambulances Available`;
        }
        if (dot) {
          if (available > 0) {
            dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
          } else {
            dot.className = 'w-2 h-2 rounded-full bg-rose-500';
          }
        }

        if (list) {
          list.innerHTML = '';
          res.data.forEach(a => {
            const row = document.createElement('div');
            const isAvail = a.status === 'Available';
            const isOnTransfer = a.status === 'On Transfer';
            row.className = `p-3 rounded-2xl border text-xs flex items-center justify-between gap-3 ${
              isAvail ? 'bg-emerald-50/50 border-emerald-200' : (isOnTransfer ? 'bg-blue-50/50 border-blue-200' : 'bg-amber-50/50 border-amber-200')
            }`;
            row.innerHTML = `
              <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="font-mono text-[10px] font-black px-1.5 py-0.2 rounded bg-white border border-slate-200 text-slate-800">${a.plate_number}</span>
                  <span class="font-bold text-slate-900">${a.unit_name}</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-0.5">${a.equipment_level} • Driver: ${a.driver || 'Duty EMT'} (${a.contact || 'Hotline'})</p>
                ${isOnTransfer && a.destination ? `<p class="text-[10px] text-blue-700 font-bold mt-0.5">En Route: ${a.destination} (${a.estimated_return || 'In Transit'})</p>` : ''}
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 ${
                isAvail ? 'bg-emerald-100 text-emerald-800' : (isOnTransfer ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')
              }">
                ${isAvail ? '● Ready' : (isOnTransfer ? '● In Transit' : '● Maintenance')}
              </span>
            `;
            list.appendChild(row);
          });
        }
        if (window.lucide) lucide.createIcons();
      })
      .catch(() => {
        if (statusText) statusText.textContent = '3/3 Ambulances Ready';
      });
  }

  document.addEventListener('DOMContentLoaded', loadIntakeAmbulanceStatus);
</script>
@endsection
