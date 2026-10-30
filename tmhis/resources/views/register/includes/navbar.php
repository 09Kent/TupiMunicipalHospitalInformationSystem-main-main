<?php
// includes/navbar.php

$activeNav = $activeNav ?? 'dashboard';
$user = Session::getCurrentUser() ?: [
    'name' => 'Sarah Jenkins',
    'role' => 'Registrator',
    'email' => 'sarah.jenkins@tupimunicipal.gov.ph'
];
?>

<!-- MAIN TOP NAVBAR (No Print) -->
<nav class="no-print sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/90 shadow-2xs">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-18 gap-4">
      
      <!-- Brand Logo -->
      <a href="<?= base_url('views/dashboard/index.php') ?>" class="flex items-center gap-3 shrink-0 group">
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-700 to-blue-500 text-white flex items-center justify-center shadow-md shadow-blue-500/25 group-hover:scale-105 transition-transform duration-200">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v20M2 12h20M12 8l4 4-4 4-4-4 4-4z"></path>
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="text-lg font-black text-slate-900 tracking-tight">Tupi Municipal Hospital</span>
            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">EHR</span>
          </div>
          <p class="text-[11px] text-slate-400 font-medium">Registrator & Pre-Consultation Portal</p>
        </div>
      </a>

      <!-- Primary Navigation Links -->
      <div class="hidden lg:flex items-center gap-1">
        
        <a href="<?= base_url('views/dashboard/index.php') ?>" 
           class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeNav === 'dashboard' ? 'bg-blue-50 text-blue-700 font-extrabold shadow-2xs' : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50' ?>">
          <i data-lucide="layout-dashboard" class="w-4 h-4 <?= $activeNav === 'dashboard' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
          <span>Dashboard</span>
        </a>

        <a href="<?= base_url('views/patients/index.php') ?>" 
           class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeNav === 'patients' ? 'bg-blue-50 text-blue-700 font-extrabold shadow-2xs' : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50' ?>">
          <i data-lucide="users" class="w-4 h-4 <?= $activeNav === 'patients' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
          <span>Patients</span>
        </a>

        <a href="<?= base_url('views/appointments/index.php') ?>" 
           class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeNav === 'appointments' ? 'bg-blue-50 text-blue-700 font-extrabold shadow-2xs' : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50' ?>">
          <i data-lucide="calendar" class="w-4 h-4 <?= $activeNav === 'appointments' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
          <span>Appointments</span>
        </a>

        <a href="<?= base_url('views/queue/index.php') ?>" 
           class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeNav === 'queue' ? 'bg-blue-50 text-blue-700 font-extrabold shadow-2xs' : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50' ?>">
          <i data-lucide="list-ordered" class="w-4 h-4 <?= $activeNav === 'queue' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
          <span>Queue Board</span>
        </a>

        <a href="<?= base_url('views/reports/index.php') ?>" 
           class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeNav === 'reports' ? 'bg-blue-50 text-blue-700 font-extrabold shadow-2xs' : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50' ?>">
          <i data-lucide="bar-chart-3" class="w-4 h-4 <?= $activeNav === 'reports' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
          <span>Reports</span>
        </a>

      </div>

      <!-- Right Controls & Actions -->
      <div class="flex items-center gap-3">

        <!-- Search Bar (Quick jump) -->
        <div class="hidden xl:flex items-center relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <i data-lucide="search" class="w-3.5 h-3.5"></i>
          </div>
          <input type="text" 
                 id="top-nav-quick-search"
                 autocomplete="off"
                 placeholder="Search patient, code, phone..." 
                 class="w-56 pl-8 pr-3 py-1.5 bg-slate-100/90 border border-slate-200/80 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:w-72 focus:bg-white focus:border-blue-400 transition-all"
                 onkeydown="if(event.key==='Enter'){ window.location.href='/register/patients?search=' + encodeURIComponent(this.value); }" />
          <div id="top-nav-search-results" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 max-h-64 overflow-y-auto divide-y divide-slate-100"></div>
        </div>

        <script>
        (() => {
          const input = document.getElementById('top-nav-quick-search');
          const results = document.getElementById('top-nav-search-results');
          if (!input || !results) return;
          let timer = null;

          input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) {
              results.classList.add('hidden');
              results.innerHTML = '';
              return;
            }
            timer = setTimeout(async () => {
              try {
                const res = await fetch('/api/search/patients?q=' + encodeURIComponent(q) + '&limit=6');
                const json = await res.json();
                if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                  results.innerHTML = json.data.map(p => {
                    const name = p.name || `${p.first_name || ''} ${p.last_name || ''}`.trim();
                    const code = p.patient_code || p.code || ('PAT-' + p.id);
                    return `
                      <a href="/register/patients/${p.id || p.patient_id}" class="block p-2.5 hover:bg-blue-50 transition text-left">
                        <div class="font-bold text-xs text-slate-900">${name}</div>
                        <div class="text-[10px] text-slate-500 font-mono">${code} • ${p.age ? p.age + 'y, ' : ''}${p.gender || ''}</div>
                      </a>
                    `;
                  }).join('');
                  results.classList.remove('hidden');
                } else {
                  results.innerHTML = '<div class="p-2.5 text-center text-xs text-slate-400 italic">No patients found</div>';
                  results.classList.remove('hidden');
                }
              } catch (e) {
                results.classList.add('hidden');
              }
            }, 180);
          });

          document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !results.contains(e.target)) {
              results.classList.add('hidden');
            }
          });
        })();
        </script>

        <!-- Notifications Bell -->
        <button type="button" 
                onclick="alert('System Notifications:\n\n• 3 New Patient Registrations today\n• Dr. Maria Santos is currently In Consultation\n• Queue Ticket #02 is next in line')"
                class="relative p-2 rounded-xl text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition" 
                title="Notifications">
          <i data-lucide="bell" class="w-4 h-4"></i>
          <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-blue-600 ring-2 ring-white"></span>
        </button>

        <!-- User Profile Dropdown -->
        <div class="flex items-center gap-2.5 pl-2 sm:pl-3 border-l border-slate-200">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
          </div>
          <div class="hidden sm:block text-left">
            <div class="text-xs font-bold text-slate-900 leading-tight"><?= e($user['name']) ?></div>
            <div class="text-[10px] font-semibold text-blue-600 leading-tight"><?= e($user['role']) ?></div>
          </div>

          <a href="<?= url('/logout') ?>" 
             class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition ml-1" 
             title="Log Out">
            <i data-lucide="log-out" class="w-4 h-4"></i>
          </a>
        </div>

      </div>

    </div>
  </div>
</nav>

<!-- Mobile Bottom Navigation (Responsive Bar for Touch Devices) -->
<div class="no-print lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 py-2 px-3 flex items-center justify-around shadow-lg">
  <a href="<?= base_url('views/dashboard/index.php') ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold <?= $activeNav === 'dashboard' ? 'text-blue-600' : 'text-slate-500' ?>">
    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
    <span>Home</span>
  </a>
  <a href="<?= base_url('views/patients/index.php') ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold <?= $activeNav === 'patients' ? 'text-blue-600' : 'text-slate-500' ?>">
    <i data-lucide="users" class="w-4 h-4"></i>
    <span>Patients</span>
  </a>
  <a href="<?= base_url('views/registration/index.php') ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold text-blue-600 -mt-3">
    <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/30">
      <i data-lucide="plus" class="w-5 h-5"></i>
    </div>
    <span>Intake</span>
  </a>
  <a href="<?= base_url('views/appointments/index.php') ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold <?= $activeNav === 'appointments' ? 'text-blue-600' : 'text-slate-500' ?>">
    <i data-lucide="calendar" class="w-4 h-4"></i>
    <span>Appts</span>
  </a>
  <a href="<?= base_url('views/queue/index.php') ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold <?= $activeNav === 'queue' ? 'text-blue-600' : 'text-slate-500' ?>">
    <i data-lucide="list-ordered" class="w-4 h-4"></i>
    <span>Queue</span>
  </a>
</div>
