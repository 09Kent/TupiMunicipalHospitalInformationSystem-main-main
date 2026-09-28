/**
 * TMHIMS Reusable Patient / Student Search Component
 * Fulfills Requirement 5: Search Bar instead of dropdown for finding students/patients.
 */
window.TMHISPatientSearch = (function () {
  let debounceTimer = null;

  function attach(container, options = {}) {
    if (typeof container === 'string') {
      container = document.querySelector(container);
    }
    if (!container) return null;

    const inputName = options.hiddenInputName || 'patient_id';
    const placeholder = options.placeholder || '🔍 Search patient name, Patient ID, Student ID...';
    const onSelect = options.onSelect || function () {};
    const onClear = options.onClear || function () {};

    container.innerHTML = `
      <div class="tmhis-patient-search-wrapper relative w-full">
        <!-- Selected Patient State -->
        <div class="selected-patient-box hidden p-3 rounded-2xl bg-blue-50/80 border border-blue-200/90 flex items-center justify-between gap-3 shadow-xs">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
              <span class="patient-initial">P</span>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span class="patient-name font-bold text-slate-900 text-sm truncate">Patient Name</span>
                <span class="patient-category text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">OPD</span>
              </div>
              <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5 truncate">
                <span>ID: <strong class="patient-code font-mono text-slate-700">PAT-0001</strong></span>
                <span>•</span>
                <span>Student ID: <strong class="student-id font-mono text-slate-700">2026-00001</strong></span>
                <span>•</span>
                <span class="patient-meta">25 yrs • Male</span>
              </div>
            </div>
          </div>
          <button type="button" class="change-patient-btn px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-blue-700 hover:bg-blue-100/70 border border-blue-200 transition-colors shrink-0">
            Change
          </button>
        </div>

        <!-- Search Input State -->
        <div class="search-input-box relative">
          <div class="relative">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
            <input type="text" 
                   class="tmhis-search-input w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition shadow-2xs" 
                   placeholder="${placeholder}" 
                   autocomplete="off" />
            <div class="search-spinner hidden absolute right-3 top-1/2 -translate-y-1/2">
              <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
          </div>

          <!-- Dropdown Results -->
          <div class="search-results-dropdown hidden absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-xl max-h-72 overflow-y-auto z-50 p-1.5 space-y-1">
            <!-- Results injected here -->
          </div>
        </div>

        <!-- Hidden input for Form Submissions -->
        <input type="hidden" name="${inputName}" class="hidden-patient-id" value="" />
      </div>
    `;

    const wrapper = container.querySelector('.tmhis-patient-search-wrapper');
    const selectedBox = wrapper.querySelector('.selected-patient-box');
    const searchBox = wrapper.querySelector('.search-input-box');
    const input = wrapper.querySelector('.tmhis-search-input');
    const spinner = wrapper.querySelector('.search-spinner');
    const dropdown = wrapper.querySelector('.search-results-dropdown');
    const hiddenInput = wrapper.querySelector('.hidden-patient-id');
    const changeBtn = wrapper.querySelector('.change-patient-btn');

    let currentPatients = [];
    let activeIndex = -1;

    function selectPatient(patient, triggerCallback = true) {
      if (!patient) return;
      hiddenInput.value = patient.id || patient.patient_id;

      selectedBox.querySelector('.patient-initial').textContent = (patient.first_name || patient.name || 'P').charAt(0).toUpperCase();
      selectedBox.querySelector('.patient-name').textContent = patient.name;
      selectedBox.querySelector('.patient-code').textContent = patient.patient_code || ('PAT-' + patient.id);
      selectedBox.querySelector('.student-id').textContent = patient.student_id || ('STU-2026-' + patient.id);
      selectedBox.querySelector('.patient-category').textContent = patient.category || 'Outpatient';
      selectedBox.querySelector('.patient-meta').textContent = `${patient.age || '—'} yrs • ${patient.gender || '—'}`;

      selectedBox.classList.remove('hidden');
      searchBox.classList.add('hidden');
      dropdown.classList.add('hidden');

      if (triggerCallback) {
        onSelect(patient);
      }
    }

    function clearSelection(triggerCallback = true) {
      hiddenInput.value = '';
      input.value = '';
      selectedBox.classList.add('hidden');
      searchBox.classList.remove('hidden');
      dropdown.classList.add('hidden');
      input.focus();

      if (triggerCallback) {
        onClear();
      }
    }

    changeBtn.addEventListener('click', function () {
      clearSelection(true);
    });

    function performSearch(query) {
      spinner.classList.remove('hidden');
      const url = `/api/search/patients?q=${encodeURIComponent(query)}&limit=15`;

      fetch(url)
        .then((r) => r.json())
        .then((res) => {
          spinner.classList.add('hidden');
          if (res.success && Array.isArray(res.data)) {
            currentPatients = res.data;
            renderDropdown(res.data);
          } else {
            renderEmpty();
          }
        })
        .catch(() => {
          spinner.classList.add('hidden');
          renderEmpty();
        });
    }

    function renderDropdown(patients) {
      if (!patients || patients.length === 0) {
        renderEmpty();
        return;
      }

      dropdown.innerHTML = patients
        .map(
          (p, idx) => `
        <div class="patient-result-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50/80 cursor-pointer transition-colors group" data-idx="${idx}">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center font-bold text-xs shrink-0 transition-colors">
              ${(p.first_name || p.name || 'P').charAt(0).toUpperCase()}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-bold text-slate-800 group-hover:text-blue-700 truncate">
                ${p.name}
              </div>
              <div class="text-[10px] text-slate-500 flex items-center gap-2 mt-0.5 truncate">
                <span>Patient ID: <strong class="font-mono text-slate-700">${p.patient_code}</strong></span>
                <span>•</span>
                <span>Student ID: <strong class="font-mono text-slate-700">${p.student_id}</strong></span>
              </div>
            </div>
          </div>
          <div class="text-right shrink-0">
            <span class="text-[10px] font-semibold text-slate-500">${p.age} yrs • ${p.gender}</span>
            <div class="text-[9px] font-bold text-blue-600 uppercase tracking-wider">${p.category}</div>
          </div>
        </div>
      `
        )
        .join('');

      dropdown.classList.remove('hidden');

      dropdown.querySelectorAll('.patient-result-item').forEach((item) => {
        item.addEventListener('click', function () {
          const idx = parseInt(this.getAttribute('data-idx'));
          selectPatient(currentPatients[idx], true);
        });
      });
    }

    function renderEmpty() {
      dropdown.innerHTML = `
        <div class="p-4 text-center text-xs text-slate-400">
          <p class="font-semibold text-slate-500">No matching patients found</p>
          <p class="text-[11px] mt-0.5">Try searching by Name, Patient ID (TMH-000123), or Student ID</p>
        </div>
      `;
      dropdown.classList.remove('hidden');
    }

    input.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      const val = this.value.trim();
      debounceTimer = setTimeout(() => {
        performSearch(val);
      }, 250);
    });

    input.addEventListener('focus', function () {
      performSearch(this.value.trim());
    });

    // Close dropdown when clicked outside
    document.addEventListener('click', function (e) {
      if (!wrapper.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
    });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }

    // Set initial patient if provided
    if (options.initialPatient) {
      selectPatient(options.initialPatient, false);
    }

    return {
      selectPatient,
      clearSelection,
      getPatientId: () => hiddenInput.value,
    };
  }

  return { attach };
})();
