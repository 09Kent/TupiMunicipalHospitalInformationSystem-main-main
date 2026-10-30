/**
 * TMHIS Healthcare Billing & Cashier Dashboard Application Controller
 * Handles reactivity, state management, validation, modal workflows, printing, and UI transitions
 */

// Application State cloned from BILLING_DATA
const AppState = {
  activeView: 'dashboard',
  sidebarCollapsed: false,
  searchTerm: '',
  chargeFilter: 'all',
  paymentFilter: 'all',
  accountFilter: 'all',
  invoiceFilter: 'all',
  receiptFilter: 'all',
  
  // Data references (use live server database records when available)
  currentUser: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.currentUser) ? { ...window.SERVER_BILLING_DATA.currentUser } : { ...BILLING_DATA.currentUser },
  metrics: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.metrics) ? { ...window.SERVER_BILLING_DATA.metrics } : { ...BILLING_DATA.metrics },
  patients: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.patients && window.SERVER_BILLING_DATA.patients.length) ? [ ...window.SERVER_BILLING_DATA.patients ] : [ ...BILLING_DATA.patients ],
  charges: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.charges && window.SERVER_BILLING_DATA.charges.length) ? [ ...window.SERVER_BILLING_DATA.charges ] : [ ...BILLING_DATA.charges ],
  bills: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.bills && window.SERVER_BILLING_DATA.bills.length) ? [ ...window.SERVER_BILLING_DATA.bills ] : [ ...BILLING_DATA.bills ],
  invoices: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.invoices && window.SERVER_BILLING_DATA.invoices.length) ? [ ...window.SERVER_BILLING_DATA.invoices ] : [ ...BILLING_DATA.invoices ],
  payments: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.payments && window.SERVER_BILLING_DATA.payments.length) ? [ ...window.SERVER_BILLING_DATA.payments ] : [ ...BILLING_DATA.payments ],
  officialReceipts: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.officialReceipts && window.SERVER_BILLING_DATA.officialReceipts.length) ? [ ...window.SERVER_BILLING_DATA.officialReceipts ] : [ ...BILLING_DATA.officialReceipts ],
  recentActivities: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.recentActivities && window.SERVER_BILLING_DATA.recentActivities.length) ? [ ...window.SERVER_BILLING_DATA.recentActivities ] : [ ...BILLING_DATA.recentActivities ],
  notifications: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.notifications && window.SERVER_BILLING_DATA.notifications.length) ? [ ...window.SERVER_BILLING_DATA.notifications ] : [ ...BILLING_DATA.notifications ],
  settings: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.settings) ? { ...window.SERVER_BILLING_DATA.settings } : { ...BILLING_DATA.settings },

  // Currently selected items for active flows
  selectedPatientForBill: (window.SERVER_BILLING_DATA && window.SERVER_BILLING_DATA.patients && window.SERVER_BILLING_DATA.patients.length > 0) ? window.SERVER_BILLING_DATA.patients[0].id : 'P-2026-001',
  activeInvoice: null,
  activeReceipt: null,
  chargeToEdit: null,
  chargeToDelete: null,
  paymentToEdit: null,

  // Table pagination state (strictly 8 records per page)
  pageSize: 8,
  pages: {
    charges: 1,
    payments: 1,
    invoices: 1,
    receipts: 1,
    accounts: 1
  }
};

if (AppState.patients && AppState.patients.length > 0) {
  AppState.selectedPatientForBill = AppState.patients[0].id;
}

// Currency Formatter for Philippine Peso (₱)
function formatPeso(amount) {
  const num = parseFloat(amount) || 0;
  return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// Convert numbers to Philippine English Words for Official Receipts
function numberToWords(amount) {
  const num = Math.floor(amount);
  if (num === 0) return "Zero Pesos Only";

  const ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", 
                "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
  const tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
  const thousands = ["", "Thousand", "Million", "Billion"];

  function convertGroup(n) {
    let result = "";
    if (n >= 100) {
      result += ones[Math.floor(n / 100)] + " Hundred ";
      n %= 100;
    }
    if (n >= 20) {
      result += tens[Math.floor(n / 10)] + " ";
      n %= 10;
    }
    if (n > 0) {
      result += ones[n] + " ";
    }
    return result.trim();
  }

  let wordResult = "";
  let thousandIdx = 0;
  let remaining = num;

  while (remaining > 0) {
    const chunk = remaining % 1000;
    if (chunk !== 0) {
      const groupStr = convertGroup(chunk);
      wordResult = groupStr + (thousands[thousandIdx] ? " " + thousands[thousandIdx] + " " : " ") + wordResult;
    }
    remaining = Math.floor(remaining / 1000);
    thousandIdx++;
  }

  return wordResult.trim() + " Pesos Only";
}

// Recompute Metrics
function recalculateMetrics() {
  let chargesTotal = 0;
  AppState.charges.forEach(c => {
    if (c.status !== 'Cancelled') chargesTotal += c.total;
  });

  let paymentsTotal = 0;
  AppState.payments.forEach(p => {
    if (p.status === 'Completed') paymentsTotal += p.amount;
  });

  let outstandingTotal = 0;
  let pendingCount = 0;
  AppState.bills.forEach(b => {
    if (b.status !== 'PAID') {
      outstandingTotal += b.outstandingBalance;
      pendingCount++;
    }
  });

  AppState.metrics.chargesToday = chargesTotal;
  AppState.metrics.paymentsToday = paymentsTotal;
  AppState.metrics.outstandingBalance = outstandingTotal;
  AppState.metrics.pendingBills = pendingCount;
  AppState.metrics.targetPercentage = Math.min(100, Math.round((paymentsTotal / AppState.metrics.dailyCollectionTarget) * 100));

  renderKPIs();
}

// Show Toast Alert
function showToast(message, type = 'success') {
  const toastContainer = document.getElementById('toast-container');
  if (!toastContainer) return;

  const toastId = 'toast-' + Date.now();
  const iconName = type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info';
  const bgColor = type === 'success' ? 'bg-emerald-600 text-white' : type === 'error' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-white';

  const toastEl = document.createElement('div');
  toastEl.id = toastId;
  toastEl.className = `flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg transform transition-all duration-300 translate-y-2 opacity-0 ${bgColor} text-sm font-medium`;
  toastEl.innerHTML = `
    <i data-lucide="${iconName}" class="w-5 h-5 flex-shrink-0"></i>
    <span>${message}</span>
    <button onclick="document.getElementById('${toastId}').remove()" class="ml-auto opacity-75 hover:opacity-100">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  `;

  toastContainer.appendChild(toastEl);
  lucide.createIcons();

  setTimeout(() => {
    toastEl.classList.remove('translate-y-2', 'opacity-0');
    toastEl.classList.add('translate-y-0', 'opacity-100');
  }, 10);

  setTimeout(() => {
    toastEl.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => toastEl.remove(), 300);
  }, 4000);
}

// Add an Activity Log
function addActivityLog(type, title, description, icon, badgeColor) {
  const newActivity = {
    id: Date.now(),
    type: type,
    title: title,
    description: description,
    time: "Just now",
    icon: icon,
    badgeColor: badgeColor
  };
  AppState.recentActivities.unshift(newActivity);
  renderActivities();
}

// Reusable 8-Record Pagination Controller & UI Builder
function renderBillingPaginationHelper(tableKey, totalItems, onPageChange) {
  let container = document.getElementById(`${tableKey}-pagination-container`);
  if (!container) {
    const tbody = document.getElementById(`${tableKey}-tbody`);
    if (tbody && tbody.closest('table')) {
      const table = tbody.closest('table');
      container = document.createElement('div');
      container.id = `${tableKey}-pagination-container`;
      container.className = 'tmhis-billing-pagination-wrap';
      table.parentNode.insertBefore(container, table.nextSibling);
    }
  }
  if (!container) return;

  const pageSize = AppState.pageSize || 8;
  const currentPage = AppState.pages[tableKey] || 1;
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
  if (currentPage > totalPages) AppState.pages[tableKey] = totalPages;

  const startRecord = totalItems === 0 ? 0 : (currentPage - 1) * pageSize + 1;
  const endRecord = Math.min(startRecord + pageSize - 1, totalItems);

  let html = `
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 py-3 px-4 bg-white border-t border-slate-200 text-xs font-medium text-slate-600 rounded-b-2xl select-none">
      <div class="text-slate-500 font-medium">
        ${totalItems === 0 
          ? 'Showing <span class="font-bold text-slate-800">0</span> records' 
          : `Showing <span class="font-bold text-slate-900">${startRecord}–${endRecord}</span> of <span class="font-bold text-slate-900">${totalItems}</span> records`}
      </div>
      <div class="flex items-center gap-1.5">
  `;

  // Previous button
  if (currentPage <= 1) {
    html += `<span class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-300 font-bold cursor-not-allowed bg-slate-50 flex items-center gap-1"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Previous</span>`;
  } else {
    html += `<button type="button" onclick="setBillingPage('${tableKey}', ${currentPage - 1})" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold transition flex items-center gap-1"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Previous</button>`;
  }

  // Page numeric buttons
  const startP = Math.max(1, currentPage - 2);
  const endP = Math.min(totalPages, currentPage + 2);

  if (startP > 1) {
    html += `<button type="button" onclick="setBillingPage('${tableKey}', 1)" class="w-8 h-8 rounded-lg flex items-center justify-center font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition">1</button>`;
    if (startP > 2) html += `<span class="px-1 text-slate-400">...</span>`;
  }

  for (let p = startP; p <= endP; p++) {
    if (p === currentPage) {
      html += `<span class="w-8 h-8 rounded-lg flex items-center justify-center font-extrabold bg-brand-600 text-white shadow-xs">${p}</span>`;
    } else {
      html += `<button type="button" onclick="setBillingPage('${tableKey}', ${p})" class="w-8 h-8 rounded-lg flex items-center justify-center font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition">${p}</button>`;
    }
  }

  if (endP < totalPages) {
    if (endP < totalPages - 1) html += `<span class="px-1 text-slate-400">...</span>`;
    html += `<button type="button" onclick="setBillingPage('${tableKey}', ${totalPages})" class="w-8 h-8 rounded-lg flex items-center justify-center font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition">${totalPages}</button>`;
  }

  // Next button
  if (currentPage >= totalPages) {
    html += `<span class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-300 font-bold cursor-not-allowed bg-slate-50 flex items-center gap-1">Next <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>`;
  } else {
    html += `<button type="button" onclick="setBillingPage('${tableKey}', ${currentPage + 1})" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold transition flex items-center gap-1">Next <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>`;
  }

  html += `</div></div>`;
  container.innerHTML = html;
  if (window.lucide) lucide.createIcons();
}

function setBillingPage(tableKey, page) {
  AppState.pages[tableKey] = page;
  if (tableKey === 'charges') renderChargesTable();
  else if (tableKey === 'payments') renderPaymentsTable();
  else if (tableKey === 'invoices') renderInvoicesTable();
  else if (tableKey === 'receipts') renderReceiptsTable();
  else if (tableKey === 'accounts') renderAccountsTables();
}

// Navigation View Switcher
function switchView(viewName) {
  AppState.activeView = viewName;

  // Update navigation links
  document.querySelectorAll('.nav-link').forEach(link => {
    if (link.getAttribute('data-view') === viewName) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });

  // Update section visibility
  document.querySelectorAll('.view-section').forEach(section => {
    if (section.id === `view-${viewName}`) {
      section.classList.remove('hidden');
      section.classList.add('animate-stagger-1');
    } else {
      section.classList.add('hidden');
      section.classList.remove('animate-stagger-1');
    }
  });

  // Update breadcrumb / header title if needed
  const pageTitleMap = {
    'dashboard': 'BILLING DASHBOARD',
    'charges': 'PATIENT SERVICE CHARGES',
    'billing': 'PATIENT BILL PROCESSING',
    'payments': 'PAYMENT PROCESSING',
    'pos': 'POS / PHARMACY RETAIL SALES',
    'accounts': 'PATIENT ACCOUNTS & BALANCES',
    'invoices': 'PATIENT INVOICES',
    'receipts': 'OFFICIAL RECEIPTS',
    'notifications': 'BILLING NOTIFICATIONS',
    'settings': 'CASHIER WORKSTATION SETTINGS'
  };

  const pageSubMap = {
    'dashboard': 'Patient Charges, Bill Processing & Payment Management',
    'charges': 'Patient Service Charges & Charge Computation',
    'billing': 'Compute Total Patient Bills, Apply Discounts & Issue Invoices',
    'payments': 'Record Cash, Card, GCash, Bank Payments & Generate Official Receipts',
    'pos': 'Retail Pharmacy Sales, Real-time Stock Deduction & Fast Receipt Printing',
    'accounts': 'Track Account Ledgers, Aging Balances & Settle Outstanding Bills',
    'invoices': 'Hospital Invoices, Medical Billing Statements & Print Layouts',
    'receipts': 'BIR-Compliant Official Receipts with Amount in Words',
    'notifications': 'Real-time Payment and Billing Alerts',
    'settings': 'Cashier Station, Receipt Printer & Billing Policy Configuration'
  };

  const titleEl = document.getElementById('page-title');
  const subEl = document.getElementById('page-subtitle');
  if (titleEl) titleEl.innerText = pageTitleMap[viewName] || 'BILLING DASHBOARD';
  if (subEl) subEl.innerText = pageSubMap[viewName] || '';

  // Render view-specific content
  if (viewName === 'dashboard') renderDashboard();
  else if (viewName === 'charges') renderChargesTable();
  else if (viewName === 'billing') renderBillProcessing();
  else if (viewName === 'payments') renderPaymentsTable();
  else if (viewName === 'pos') PosModule.init();
  else if (viewName === 'accounts') renderAccountsTables();
  else if (viewName === 'invoices') renderInvoicesTable();
  else if (viewName === 'receipts') renderReceiptsTable();
  else if (viewName === 'notifications') renderNotificationsView();

  window.scrollTo({ top: 0, behavior: 'smooth' });
  lucide.createIcons();
}

// Sidebar Collapse / Expand Toggle
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('main-content');
  const toggleIcon = document.getElementById('sidebar-toggle-icon');
  AppState.sidebarCollapsed = !AppState.sidebarCollapsed;

  if (AppState.sidebarCollapsed) {
    sidebar.classList.add('collapsed', 'w-[72px]');
    sidebar.classList.remove('w-[250px]');
    mainContent.classList.add('ml-[72px]');
    mainContent.classList.remove('ml-[250px]');
    document.querySelectorAll('.sidebar-text').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.sidebar-logo-text').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.sidebar-badge').forEach(el => el.classList.add('hidden'));
    if (toggleIcon) toggleIcon.setAttribute('data-lucide', 'chevron-right');
  } else {
    sidebar.classList.remove('collapsed', 'w-[72px]');
    sidebar.classList.add('w-[250px]');
    mainContent.classList.remove('ml-[72px]');
    mainContent.classList.add('ml-[250px]');
    document.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
    document.querySelectorAll('.sidebar-logo-text').forEach(el => el.classList.remove('hidden'));
    document.querySelectorAll('.sidebar-badge').forEach(el => el.classList.remove('hidden'));
    if (toggleIcon) toggleIcon.setAttribute('data-lucide', 'chevron-left');
  }
  lucide.createIcons();
}

// Render Summary KPIs
function renderKPIs() {
  const c1 = document.getElementById('kpi-charges-today');
  const c2 = document.getElementById('kpi-payments-today');
  const c3 = document.getElementById('kpi-outstanding');
  const c4 = document.getElementById('kpi-pending-bills');

  if (c1) c1.innerText = formatPeso(AppState.metrics.chargesToday);
  if (c2) c2.innerText = formatPeso(AppState.metrics.paymentsToday);
  if (c3) c3.innerText = formatPeso(AppState.metrics.outstandingBalance);
  if (c4) c4.innerText = AppState.metrics.pendingBills;

  // Collection Target Ring & Stats
  const targetPctEl = document.getElementById('target-pct-text');
  const targetRing = document.getElementById('target-ring-circle');
  if (targetPctEl) targetPctEl.innerText = `${AppState.metrics.targetPercentage}%`;
  if (targetRing) {
    const circumference = 2 * Math.PI * 40; // r=40
    const offset = circumference - (AppState.metrics.targetPercentage / 100) * circumference;
    targetRing.style.strokeDasharray = `${circumference}`;
    targetRing.style.strokeDashoffset = `${offset}`;
  }

  const cashEl = document.getElementById('stat-cash');
  const cardEl = document.getElementById('stat-card');
  const gcashEl = document.getElementById('stat-gcash');
  const bankEl = document.getElementById('stat-bank');

  if (cashEl) cashEl.innerText = formatPeso(AppState.metrics.paymentBreakdown.cash);
  if (cardEl) cardEl.innerText = formatPeso(AppState.metrics.paymentBreakdown.card);
  if (gcashEl) gcashEl.innerText = formatPeso(AppState.metrics.paymentBreakdown.gcash);
  if (bankEl) bankEl.innerText = formatPeso(AppState.metrics.paymentBreakdown.bankTransfer);
}

// Render Patient Billing Queue on Dashboard
function renderDashboardQueue() {
  const queueTbody = document.getElementById('dashboard-queue-tbody');
  if (!queueTbody) return;

  const queueItems = AppState.bills.slice(0, 8);
  let html = '';

  queueItems.forEach(b => {
    const statusClass = b.status === 'PAID' ? 'badge-paid' : b.status === 'PARTIALLY PAID' ? 'badge-partially-paid' : b.status === 'OVERDUE' ? 'badge-overdue' : 'badge-unpaid';
    html += `
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="px-4 py-3">
          <div class="font-semibold text-slate-800">${b.patientName}</div>
          <div class="text-xs text-slate-400 font-mono">${b.patientId}</div>
        </td>
        <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-600">${b.invoiceId}</td>
        <td class="px-4 py-3 font-medium text-slate-800">${formatPeso(b.totalBill)}</td>
        <td class="px-4 py-3 font-semibold ${b.outstandingBalance > 0 ? 'text-rose-600' : 'text-emerald-600'}">
          ${formatPeso(b.outstandingBalance)}
        </td>
        <td class="px-4 py-3">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${statusClass}">
            ${b.status}
          </span>
        </td>
        <td class="px-4 py-3 text-right">
          <button onclick="openPatientBill('${b.patientId}')" class="px-3 py-1 text-xs font-medium text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg transition-colors inline-flex items-center gap-1">
            <i data-lucide="calculator" class="w-3.5 h-3.5"></i> Bill
          </button>
          ${b.outstandingBalance > 0 ? `
            <button onclick="openPaymentModalForBill('${b.billId}')" class="ml-1 px-3 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors inline-flex items-center gap-1">
              <i data-lucide="credit-card" class="w-3.5 h-3.5"></i> Pay
            </button>
          ` : `
            <button onclick="viewInvoice('${b.invoiceId}')" class="ml-1 px-3 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors inline-flex items-center gap-1">
              <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Invoice
            </button>
          `}
        </td>
      </tr>
    `;
  });

  queueTbody.innerHTML = html;
}

// Render Recent Activities on Dashboard
function renderActivities() {
  const container = document.getElementById('recent-activities-container');
  if (!container) return;

  let html = '';
  AppState.recentActivities.slice(0, 5).forEach(act => {
    html += `
      <div class="flex items-start gap-3 p-3 hover:bg-slate-50 rounded-xl transition-colors border border-transparent hover:border-slate-100">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 ${act.badgeColor}">
          <i data-lucide="${act.icon === 'CreditCard' ? 'credit-card' : act.icon === 'FileText' ? 'file-text' : act.icon === 'Tag' ? 'tag' : act.icon === 'Receipt' ? 'receipt' : 'plus-circle'}" class="w-4 h-4"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-slate-800 truncate">${act.title}</h4>
            <span class="text-[11px] text-slate-400 font-medium">${act.time}</span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5">${act.description}</p>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

// Full Dashboard Render
function renderDashboard() {
  recalculateMetrics();
  renderDashboardQueue();
  renderActivities();
}

// Render Charges Management Table
function renderChargesTable() {
  const tbody = document.getElementById('charges-tbody');
  if (!tbody) return;

  const search = AppState.searchTerm.toLowerCase();
  const filter = AppState.chargeFilter;

  const filtered = AppState.charges.filter(c => {
    const matchSearch = c.patientName.toLowerCase().includes(search) || 
                        c.patientId.toLowerCase().includes(search) || 
                        c.serviceName.toLowerCase().includes(search) || 
                        c.id.toLowerCase().includes(search);
    const matchFilter = filter === 'all' || c.status.toLowerCase() === filter.toLowerCase();
    return matchSearch && matchFilter;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="10" class="text-center py-10 text-slate-400">
          <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
          No patient charges found matching your criteria.
        </td>
      </tr>
    `;
    lucide.createIcons();
    return;
  }

  const pageSize = AppState.pageSize || 8;
  const currentPage = AppState.pages.charges || 1;
  const totalItems = filtered.length;
  const start = (currentPage - 1) * pageSize;
  const paginated = filtered.slice(start, start + pageSize);

  let html = '';
  paginated.forEach(c => {
    const badgeClass = c.status === 'Paid' ? 'badge-paid' : c.status === 'Included in Bill' ? 'badge-included' : c.status === 'Cancelled' ? 'badge-cancelled' : 'badge-unbilled';
    html += `
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="font-mono text-xs font-semibold text-slate-700">${c.id}</td>
        <td>
          <div class="font-semibold text-slate-800">${c.patientName}</div>
          <div class="text-xs text-slate-400 font-mono">${c.patientId}</div>
        </td>
        <td class="max-w-[220px]">
          <div class="font-medium text-slate-800 truncate" title="${c.serviceName}">${c.serviceName}</div>
          <div class="text-xs text-slate-400">${c.category}</div>
        </td>
        <td>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">
            ${c.department}
          </span>
        </td>
        <td class="text-center font-medium">${c.quantity}</td>
        <td class="font-medium">${formatPeso(c.unitPrice)}</td>
        <td class="font-semibold text-slate-900">${formatPeso(c.total)}</td>
        <td class="text-xs text-slate-500">${c.date}</td>
        <td>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">
            ${c.status}
          </span>
        </td>
        <td class="text-right whitespace-nowrap">
          <button onclick="viewChargeDetails('${c.id}')" title="View Patient Charges" class="p-1.5 text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors">
            <i data-lucide="eye" class="w-4 h-4"></i>
          </button>
          <button onclick="openEditChargeModal('${c.id}')" title="Edit Charge" class="p-1.5 text-slate-600 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
          <button onclick="openDeleteChargeModal('${c.id}')" title="Delete Charge" class="p-1.5 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  renderBillingPaginationHelper('charges', totalItems);
  lucide.createIcons();
}

// Patient search helpers for Bill Processing
let billSearchTimer = null;

function onBillPatientSearchInput(query) {
  const container = document.getElementById('bill-patient-search-results');
  if (!container) return;
  const q = (query || '').toLowerCase().trim();

  const renderList = (matched, isAsync = false) => {
    if (matched.length === 0) {
      if (!isAsync && q.length >= 2) {
        container.innerHTML = `<div class="p-3 text-xs text-slate-400 text-center">Searching hospital records...</div>`;
      } else {
        container.innerHTML = `<div class="p-3 text-xs text-slate-400 text-center">No matching patients found</div>`;
      }
      container.classList.remove('hidden');
      return;
    }

    let html = '';
    matched.slice(0, 10).forEach(p => {
      const pid = p.id || p.PatientID || p.PatientCode;
      const pname = p.name || p.FullName || ((p.FirstName || '') + ' ' + (p.LastName || '')).trim();
      const pcode = p.code || p.PatientCode || pid;
      const room = p.room || p.PatientCategory || 'Outpatient';
      const encoded = encodeURIComponent(JSON.stringify({ id: pid, name: pname, code: pcode, category: p.PatientCategory || 'OPD' }));
      html += `
        <div onclick="selectBillPatientFromObj('${encoded}')" class="p-2.5 hover:bg-brand-50 cursor-pointer transition-colors flex items-center justify-between">
          <div>
            <div class="font-bold text-xs text-slate-800">${pname}</div>
            <div class="text-[11px] text-slate-500 font-mono">${pcode} ${p.studentId ? '• Student ID: ' + p.studentId : ''}</div>
          </div>
          <div class="text-right text-[11px] text-slate-400">
            <div>${room}</div>
            <span class="text-brand-600 font-semibold text-[10px]">Select &rarr;</span>
          </div>
        </div>
      `;
    });
    container.innerHTML = html;
    container.classList.remove('hidden');
  };

  const localMatched = AppState.patients.filter(p => 
    p.name.toLowerCase().includes(q) ||
    p.id.toLowerCase().includes(q) ||
    (p.studentId && p.studentId.toLowerCase().includes(q))
  );

  if (localMatched.length > 0 || !q) {
    renderList(localMatched);
  }

  if (q.length >= 2) {
    clearTimeout(billSearchTimer);
    billSearchTimer = setTimeout(async () => {
      try {
        const res = await fetch(`/api/search/patients?q=${encodeURIComponent(q)}&limit=10`);
        const json = await res.json();
        if (json.success && Array.isArray(json.data) && json.data.length > 0) {
          const merged = [...localMatched];
          const seen = new Set(merged.map(x => String(x.id)));
          json.data.forEach(srv => {
            const sid = String(srv.PatientID || srv.PatientCode);
            if (!seen.has(sid)) {
              seen.add(sid);
              merged.push({
                id: sid,
                name: srv.FullName || `${srv.FirstName} ${srv.LastName}`.trim(),
                code: srv.PatientCode || sid,
                category: srv.PatientCategory || 'OPD',
                room: srv.PatientCategory === 'Inpatient' ? 'Inpatient Ward' : 'OPD'
              });
            }
          });
          renderList(merged, true);
        } else if (localMatched.length === 0) {
          renderList([], true);
        }
      } catch (err) {
        if (localMatched.length === 0) renderList([], true);
      }
    }, 200);
  }
}

function onBillPatientSearchFocus() {
  const val = document.getElementById('bill-patient-search-input')?.value || '';
  onBillPatientSearchInput(val);
}

function selectBillPatientFromObj(encodedJson) {
  try {
    const data = JSON.parse(decodeURIComponent(encodedJson));
    const sel = document.getElementById('bill-patient-select');
    if (sel) {
      let opt = Array.from(sel.options).find(o => o.value === String(data.id));
      if (!opt) {
        opt = new Option(`${data.name} (${data.code || data.id})`, data.id);
        sel.add(opt);
      }
      sel.value = data.id;
    }
    let p = AppState.patients.find(x => String(x.id) === String(data.id));
    if (!p) {
      p = { id: String(data.id), name: data.name, category: data.category || 'OPD' };
      AppState.patients.push(p);
    }
    AppState.selectedPatientForBill = data.id;
    const input = document.getElementById('bill-patient-search-input');
    if (input) {
      input.value = `${data.name} (${data.code || data.id})`;
    }
    document.getElementById('bill-patient-search-results')?.classList.add('hidden');
    renderBillProcessing();
  } catch(e) {
    console.error(e);
  }
}

function selectBillPatient(patientId) {
  AppState.selectedPatientForBill = patientId;
  const sel = document.getElementById('bill-patient-select');
  if (sel) sel.value = patientId;
  const p = AppState.patients.find(x => x.id === patientId);
  const input = document.getElementById('bill-patient-search-input');
  if (input && p) {
    input.value = `${p.name} (${p.id})`;
  }
  document.getElementById('bill-patient-search-results')?.classList.add('hidden');
  renderBillProcessing();
}

// Close search dropdown on click outside
document.addEventListener('click', (e) => {
  const box = document.getElementById('bill-patient-search-results');
  const input = document.getElementById('bill-patient-search-input');
  if (box && input && !box.contains(e.target) && e.target !== input) {
    box.classList.add('hidden');
  }
});

// Resilient Active Bill Finder for Bill Processing, Deductions, Payments, and Invoices
function getActiveBillForProcessing() {
  const sel = document.getElementById('bill-patient-select');
  let selectedId = (sel && sel.value) ? sel.value : AppState.selectedPatientForBill;
  
  if ((!selectedId || selectedId === 'P-2026-001') && AppState.patients && AppState.patients.length > 0) {
    selectedId = AppState.patients[0].id;
  }
  AppState.selectedPatientForBill = selectedId;

  let bill = AppState.bills.find(b => b.patientId === selectedId);

  if (!bill) {
    if (typeof renderBillProcessing === 'function') {
      renderBillProcessing();
    }
    bill = AppState.bills.find(b => b.patientId === selectedId);
  }

  if (!bill && AppState.bills.length > 0) {
    bill = AppState.bills[0];
    AppState.selectedPatientForBill = bill.patientId;
  }

  if (!bill) {
    const patient = (AppState.patients && AppState.patients.find(p => p.id === selectedId)) || 
                    (AppState.patients && AppState.patients[0]) || 
                    { id: selectedId || 'PAT-DEFAULT', name: 'Active Patient', room: 'OPD Triage', physician: 'Attending Physician' };
    bill = {
      billId: "BILL-2026-" + Math.floor(100 + Math.random() * 900),
      patientId: patient.id,
      patientName: patient.name,
      invoiceId: "INV-2026-" + Math.floor(100 + Math.random() * 900),
      date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
      items: [
        { service: "Hospital Clinical Consultation & Diagnostic Care", department: "Clinical Services", quantity: 1, unitPrice: 500, amount: 500 }
      ],
      subtotal: 500,
      philhealthDeduction: 0,
      philhealthId: "",
      discountType: "None",
      discountRate: 0,
      discountAmount: 0,
      discountReason: "",
      authorizedBy: "Dr. Roberto Mendoza, MD",
      totalBill: 500,
      amountPaid: 0,
      outstandingBalance: 500,
      status: "UNPAID",
      dueDate: "Due Upon Discharge"
    };
    AppState.bills.push(bill);
    AppState.invoices.push(bill);
  }

  return bill;
}

// Bill Processing View Interactive Computation
function renderBillProcessing() {
  const patientSelect = document.getElementById('bill-patient-select');
  if (!patientSelect) return;

  // Populate patient select if empty
  if (patientSelect.options.length === 0) {
    let options = '';
    AppState.patients.forEach(p => {
      options += `<option value="${p.id}" ${p.id === AppState.selectedPatientForBill ? 'selected' : ''}>${p.name} (${p.id}) - ${p.room}</option>`;
    });
    patientSelect.innerHTML = options;
  }

  let selectedId = patientSelect.value || AppState.selectedPatientForBill;
  let patient = AppState.patients.find(p => p.id === selectedId);
  if (!patient && AppState.patients.length > 0) {
    patient = AppState.patients[0];
    selectedId = patient.id;
  }
  if (!patient) {
    patient = { id: selectedId || 'PAT-001', name: 'Patient', age: 30, gender: 'Male', room: 'OPD Triage', physician: 'Attending Physician' };
  }
  AppState.selectedPatientForBill = patient.id;
  if (patientSelect.value !== patient.id) {
    patientSelect.value = patient.id;
  }
  
  // Update the search bar text if needed
  const searchInput = document.getElementById('bill-patient-search-input');
  if (searchInput && (!searchInput.value || !searchInput.matches(':focus'))) {
    searchInput.value = `${patient.name} (${patient.id})`;
  }

  // Find or construct bill for patient
  let bill = AppState.bills.find(b => b.patientId === selectedId);
  if (!bill) {
    // Generate a default bill structure from unbilled charges
    const patientCharges = AppState.charges.filter(c => c.patientId === selectedId);
    let subtotal = 0;
    const items = patientCharges.map(c => {
      subtotal += c.total;
      return { service: c.serviceName, department: c.department, quantity: c.quantity, unitPrice: c.unitPrice, amount: c.total };
    });

    bill = {
      billId: "BILL-2026-" + Math.floor(100 + Math.random() * 900),
      patientId: patient.id,
      patientName: patient.name,
      invoiceId: "INV-2026-" + Math.floor(100 + Math.random() * 900),
      date: "Aug 28, 2026",
      items: items.length > 0 ? items : [
        { service: "Attending Specialist Consultation", department: "Internal Medicine", quantity: 1, unitPrice: 500, amount: 500 }
      ],
      subtotal: subtotal || 500,
      philhealthDeduction: 0,
      philhealthId: "",
      discountType: "None",
      discountRate: 0,
      discountAmount: 0,
      discountReason: "",
      authorizedBy: "",
      totalBill: subtotal || 500,
      amountPaid: 0,
      outstandingBalance: subtotal || 500,
      status: "UNPAID",
      dueDate: "Aug 30, 2026"
    };
    AppState.bills.push(bill);
  }

  // Populate Patient Header Details
  document.getElementById('bill-patient-name').innerText = patient.name;
  document.getElementById('bill-patient-id').innerText = patient.id;
  document.getElementById('bill-patient-age-gender').innerText = `${patient.age} yrs old • ${patient.gender}`;
  document.getElementById('bill-patient-room').innerText = patient.room;
  document.getElementById('bill-patient-physician').innerText = patient.physician;
  document.getElementById('bill-id-badge').innerText = bill.billId;

  // Render Charges Table
  const itemsTbody = document.getElementById('bill-items-tbody');
  let itemsHtml = '';
  bill.items.forEach(item => {
    itemsHtml += `
      <tr class="border-b border-slate-100 hover:bg-slate-50">
        <td class="py-3 px-4">
          <div class="font-semibold text-slate-800">${item.service}</div>
          <div class="text-xs text-slate-400">${item.department}</div>
        </td>
        <td class="py-3 px-4 text-center font-medium">${item.quantity}</td>
        <td class="py-3 px-4 text-right font-medium">${formatPeso(item.unitPrice)}</td>
        <td class="py-3 px-4 text-right font-semibold text-slate-900">${formatPeso(item.amount)}</td>
      </tr>
    `;
  });
  itemsTbody.innerHTML = itemsHtml;

  // Compute live amounts according to healthcare formula:
  // Total Hospital Charges (Subtotal)
  // - PhilHealth Deduction
  // - Other Deductions
  // = Remaining Balance (Amount Due)
  // Remaining Balance - Amount Paid = Balance Due
  const subtotal = bill.items.reduce((acc, curr) => acc + curr.amount, 0);
  bill.subtotal = subtotal;

  const philhealth = parseFloat(bill.philhealthDeduction) || 0;
  const otherDeductions = parseFloat(bill.discountAmount) || 0;
  const amountCovered = Math.min(subtotal, philhealth + otherDeductions);
  const remainingBalance = Math.max(0, subtotal - amountCovered);
  bill.totalBill = remainingBalance;

  const amountPaid = parseFloat(bill.amountPaid) || 0;
  const balanceDue = Math.max(0, remainingBalance - amountPaid);
  bill.outstandingBalance = balanceDue;

  bill.status = balanceDue === 0 ? 'PAID' : (amountPaid > 0 ? 'PARTIALLY PAID' : 'UNPAID');

  // Update UI Elements
  const subtotalEl = document.getElementById('bill-subtotal');
  if (subtotalEl) subtotalEl.innerText = formatPeso(subtotal);

  const phEl = document.getElementById('bill-philhealth-deduction');
  if (phEl) phEl.innerText = `- ${formatPeso(philhealth)}`;

  const discEl = document.getElementById('bill-discount');
  if (discEl) discEl.innerText = `- ${formatPeso(otherDeductions)}`;

  const discLabelEl = document.getElementById('bill-discount-label');
  if (discLabelEl) discLabelEl.innerText = bill.discountType !== 'None' ? `(${bill.discountType})` : '';

  const covEl = document.getElementById('bill-amount-covered');
  if (covEl) covEl.innerText = formatPeso(amountCovered);

  const totalEl = document.getElementById('bill-total');
  if (totalEl) totalEl.innerText = formatPeso(remainingBalance);

  const paidEl = document.getElementById('bill-paid');
  if (paidEl) paidEl.innerText = formatPeso(amountPaid);

  const outEl = document.getElementById('bill-outstanding');
  if (outEl) outEl.innerText = formatPeso(balanceDue);

  const statusBadge = document.getElementById('bill-status-badge');
  if (statusBadge) {
    const badgeClass = bill.status === 'PAID' ? 'badge-paid' : bill.status === 'PARTIALLY PAID' ? 'badge-partially-paid' : bill.status === 'OVERDUE' ? 'badge-overdue' : 'badge-unpaid';
    statusBadge.className = `inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${badgeClass}`;
    statusBadge.innerText = bill.status;
  }

  lucide.createIcons();
}

// Select different patient in Bill Processing
function onPatientBillChange() {
  const patientSelect = document.getElementById('bill-patient-select');
  AppState.selectedPatientForBill = patientSelect.value;
  renderBillProcessing();
}

// Open Patient Bill for specific patient from any button
function openPatientBill(patientId) {
  AppState.selectedPatientForBill = patientId;
  switchView('billing');
  const patientSelect = document.getElementById('bill-patient-select');
  if (patientSelect) patientSelect.value = patientId;
  renderBillProcessing();
}

// Render Payments Table
function renderPaymentsTable() {
  const tbody = document.getElementById('payments-tbody');
  if (!tbody) return;

  const search = AppState.searchTerm.toLowerCase();
  const filter = AppState.paymentFilter;

  const filtered = AppState.payments.filter(p => {
    const matchSearch = p.patientName.toLowerCase().includes(search) ||
                        p.patientId.toLowerCase().includes(search) ||
                        p.invoiceId.toLowerCase().includes(search) ||
                        p.paymentId.toLowerCase().includes(search) ||
                        p.referenceNo.toLowerCase().includes(search);
    const matchFilter = filter === 'all' || p.method.toLowerCase().includes(filter.toLowerCase());
    return matchSearch && matchFilter;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-10 text-slate-400">
          <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
          No payment records found.
        </td>
      </tr>
    `;
    lucide.createIcons();
    return;
  }

  const pageSize = AppState.pageSize || 8;
  const currentPage = AppState.pages.payments || 1;
  const totalItems = filtered.length;
  const start = (currentPage - 1) * pageSize;
  const paginated = filtered.slice(start, start + pageSize);

  let html = '';
  paginated.forEach(p => {
    html += `
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="font-mono text-xs font-semibold text-slate-700">${p.paymentId}</td>
        <td>
          <div class="font-semibold text-slate-800">${p.patientName}</div>
          <div class="text-xs text-slate-400 font-mono">${p.patientId}</div>
        </td>
        <td class="font-mono text-xs font-medium text-slate-600">${p.invoiceId}</td>
        <td class="font-semibold text-emerald-700 text-base">${formatPeso(p.amount)}</td>
        <td>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-700">
            <i data-lucide="${p.method === 'Cash' ? 'banknote' : p.method.includes('Card') ? 'credit-card' : p.method === 'GCash' ? 'smartphone' : 'building-2'}" class="w-3.5 h-3.5 text-slate-500"></i>
            ${p.method}
          </span>
          <div class="text-[11px] text-slate-400 font-mono mt-0.5">${p.referenceNo}</div>
        </td>
        <td class="text-xs text-slate-500">${p.date}</td>
        <td class="text-xs text-slate-700 font-medium">${p.cashier}</td>
        <td>
          ${(() => {
            const s = (p.status || '').toUpperCase();
            if (s.includes('VERIF')) {
              return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200">Pending Verification</span>`;
            } else if (s.includes('PENDING') || s.includes('WAITING') || s.includes('PROCESS')) {
              return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200 animate-pulse">Waiting for Payment</span>`;
            } else if (s.includes('FAIL') || s.includes('DECLINE')) {
              return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">FAILED</span>`;
            } else if (s.includes('CANCEL')) {
              return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">CANCELLED</span>`;
            } else {
              return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold badge-paid">PAID</span>`;
            }
          })()}
        </td>
        <td class="text-right whitespace-nowrap">
          ${(() => {
            const s = (p.status || '').toUpperCase();
            if (s.includes('VERIF')) {
              return `
                <button onclick="verifyBankTransferAction('${p.rawId || p.paymentId}')" title="Verify Bank Deposit Slip" class="px-2.5 py-1 text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors inline-flex items-center gap-1 border border-purple-200 shadow-xs">
                  <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Verify Bank
                </button>
              `;
            } else if (s.includes('PENDING') || s.includes('WAITING')) {
              return `
                <button onclick="resumeElectronicPayment('${p.rawId || p.paymentId}')" title="Scan QR / Authorize" class="px-2.5 py-1 text-xs font-bold text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg transition-colors inline-flex items-center gap-1 border border-sky-200 shadow-xs">
                  <i data-lucide="qr-code" class="w-3.5 h-3.5"></i> Scan QR
                </button>
              `;
            } else {
              return `
                <button onclick="openReceiptForPayment('${p.paymentId}')" title="Generate / View Official Receipt" class="px-2.5 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors inline-flex items-center gap-1">
                  <i data-lucide="receipt" class="w-3.5 h-3.5"></i> Receipt
                </button>
                <button onclick="openEditPaymentModal('${p.paymentId}')" title="Update Payment Record" class="p-1.5 text-slate-600 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
                  <i data-lucide="edit-3" class="w-4 h-4"></i>
                </button>
              `;
            }
          })()}
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  renderBillingPaginationHelper('payments', totalItems);
  lucide.createIcons();
}

// Render Accounts and Outstanding Balance Tables
function renderAccountsTables() {
  const tbody = document.getElementById('accounts-tbody');
  const outstandingTbody = document.getElementById('outstanding-tbody');
  if (!tbody || !outstandingTbody) return;

  const search = AppState.searchTerm.toLowerCase();
  
  // All accounts table
  const allAccounts = AppState.bills.filter(b => b.patientName.toLowerCase().includes(search) || b.patientId.toLowerCase().includes(search) || b.invoiceId.toLowerCase().includes(search));
  const accPageSize = AppState.pageSize || 8;
  const accCurrentPage = AppState.pages.accounts || 1;
  const accTotalItems = allAccounts.length;
  const accStart = (accCurrentPage - 1) * accPageSize;
  const accPaginated = allAccounts.slice(accStart, accStart + accPageSize);

  let accountsHtml = '';
  accPaginated.forEach(b => {
    const badgeClass = b.status === 'PAID' ? 'badge-paid' : b.status === 'PARTIALLY PAID' ? 'badge-partially-paid' : b.status === 'OVERDUE' ? 'badge-overdue' : 'badge-unpaid';
    accountsHtml += `
      <tr class="hover:bg-slate-50 transition-colors">
        <td>
          <div class="font-semibold text-slate-800">${b.patientName}</div>
          <div class="text-xs text-slate-400 font-mono">${b.patientId}</div>
        </td>
        <td class="font-medium">${formatPeso(b.subtotal)}</td>
        <td class="font-medium text-purple-600">${formatPeso(b.discountAmount)}</td>
        <td class="font-semibold text-slate-900">${formatPeso(b.totalBill)}</td>
        <td class="font-medium text-emerald-600">${formatPeso(b.amountPaid)}</td>
        <td class="font-bold ${b.outstandingBalance > 0 ? 'text-rose-600' : 'text-emerald-600'}">${formatPeso(b.outstandingBalance)}</td>
        <td>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">
            ${b.status}
          </span>
        </td>
        <td class="text-right">
          <button onclick="openPatientBill('${b.patientId}')" class="px-2.5 py-1 text-xs font-medium text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg">View</button>
        </td>
      </tr>
    `;
  });
  tbody.innerHTML = accountsHtml;
  renderBillingPaginationHelper('accounts', accTotalItems);

  // Outstanding Accounts Table
  let outHtml = '';
  const outstandingList = AppState.bills.filter(b => b.outstandingBalance > 0);
  outstandingList.forEach(b => {
    if (b.patientName.toLowerCase().includes(search) || b.patientId.toLowerCase().includes(search) || b.invoiceId.toLowerCase().includes(search)) {
      const badgeClass = b.status === 'PARTIALLY PAID' ? 'badge-partially-paid' : b.status === 'OVERDUE' ? 'badge-overdue' : 'badge-unpaid';
      outHtml += `
        <tr class="hover:bg-slate-50 transition-colors">
          <td>
            <div class="font-semibold text-slate-800">${b.patientName}</div>
            <div class="text-xs text-slate-400 font-mono">${b.patientId}</div>
          </td>
          <td class="font-mono text-xs font-medium text-slate-600">${b.invoiceId}</td>
          <td class="font-medium">${formatPeso(b.totalBill)}</td>
          <td class="font-medium text-emerald-600">${formatPeso(b.amountPaid)}</td>
          <td class="font-bold text-rose-600 text-base">${formatPeso(b.outstandingBalance)}</td>
          <td class="text-xs font-medium text-slate-600">${b.dueDate}</td>
          <td>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">
              ${b.status}
            </span>
          </td>
          <td class="text-right whitespace-nowrap">
            <button onclick="openPatientBill('${b.patientId}')" class="px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Account</button>
            <button onclick="openPaymentModalForBill('${b.billId}')" class="ml-1 px-3 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg inline-flex items-center gap-1 font-semibold">
              <i data-lucide="credit-card" class="w-3.5 h-3.5"></i> Pay
            </button>
          </td>
        </tr>
      `;
    }
  });
  outstandingTbody.innerHTML = outHtml;
  lucide.createIcons();
}

// Render Invoices Table
function renderInvoicesTable() {
  const tbody = document.getElementById('invoices-tbody');
  if (!tbody) return;

  const search = AppState.searchTerm.toLowerCase();
  const filtered = AppState.invoices.filter(inv => {
    return inv.patientName.toLowerCase().includes(search) || 
           inv.patientId.toLowerCase().includes(search) || 
           inv.invoiceId.toLowerCase().includes(search);
  });

  const pageSize = AppState.pageSize || 8;
  const currentPage = AppState.pages.invoices || 1;
  const totalItems = filtered.length;
  const start = (currentPage - 1) * pageSize;
  const paginated = filtered.slice(start, start + pageSize);

  let html = '';
  paginated.forEach(inv => {
    const badgeClass = inv.status === 'PAID' ? 'badge-paid' : inv.status === 'PARTIALLY PAID' ? 'badge-partially-paid' : inv.status === 'OVERDUE' ? 'badge-overdue' : 'badge-unpaid';
    html += `
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="font-mono text-xs font-semibold text-slate-800">${inv.invoiceId}</td>
        <td>
          <div class="font-semibold text-slate-800">${inv.patientName}</div>
          <div class="text-xs text-slate-400 font-mono">${inv.patientId}</div>
        </td>
        <td class="text-xs text-slate-500">${inv.date}</td>
        <td class="font-semibold text-slate-900">${formatPeso(inv.total)}</td>
        <td class="font-medium text-emerald-600">${formatPeso(inv.paid)}</td>
        <td class="font-bold ${inv.balance > 0 ? 'text-rose-600' : 'text-emerald-600'}">${formatPeso(inv.balance)}</td>
        <td>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">
            ${inv.status}
          </span>
        </td>
        <td class="text-xs text-slate-600">${inv.cashier}</td>
        <td class="text-right whitespace-nowrap">
          <button onclick="viewInvoice('${inv.invoiceId}')" title="View & Print Invoice" class="px-3 py-1 text-xs font-medium text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg transition-colors inline-flex items-center gap-1">
            <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
          </button>
          <button onclick="printInvoiceDirect('${inv.invoiceId}')" title="Direct Print" class="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  renderBillingPaginationHelper('invoices', totalItems);
  lucide.createIcons();
}

// Render Official Receipts Table
function renderReceiptsTable() {
  const tbody = document.getElementById('receipts-tbody');
  if (!tbody) return;

  const search = AppState.searchTerm.toLowerCase();
  const filtered = AppState.officialReceipts.filter(r => {
    return r.receivedFrom.toLowerCase().includes(search) || 
           r.orNumber.toLowerCase().includes(search) || 
           r.invoiceId.toLowerCase().includes(search);
  });

  const rPageSize = AppState.pageSize || 8;
  const rCurrentPage = AppState.pages.receipts || 1;
  const rTotalItems = filtered.length;
  const rStart = (rCurrentPage - 1) * rPageSize;
  const rPaginated = filtered.slice(rStart, rStart + rPageSize);

  let html = '';
  rPaginated.forEach(r => {
    html += `
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="font-mono text-xs font-bold text-indigo-900">${r.orNumber}</td>
        <td class="text-xs text-slate-500">${r.date}</td>
        <td>
          <div class="font-semibold text-slate-800">${r.receivedFrom}</div>
          <div class="text-xs text-slate-400 font-mono">${r.patientId}</div>
        </td>
        <td class="font-mono text-xs text-slate-600">${r.invoiceId}</td>
        <td class="font-bold text-emerald-700 text-base">${formatPeso(r.amount)}</td>
        <td>
          <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
            ${r.paymentMethod}
          </span>
        </td>
        <td class="text-xs text-slate-700">${r.cashier}</td>
        <td>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold badge-paid">
            ${r.status}
          </span>
        </td>
        <td class="text-right whitespace-nowrap">
          <button onclick="viewReceipt('${r.orNumber}')" title="View & Print Official Receipt" class="px-3 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors inline-flex items-center gap-1">
            <i data-lucide="eye" class="w-3.5 h-3.5"></i> View OR
          </button>
          <button onclick="printReceiptDirect('${r.orNumber}')" title="Direct Print Receipt" class="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  lucide.createIcons();
}

// Render Notifications View
function renderNotificationsView() {
  const container = document.getElementById('all-notifications-list');
  if (!container) return;

  let html = '';
  AppState.notifications.forEach(n => {
    html += `
      <div class="p-4 hospity-card flex items-start gap-4 ${n.unread ? 'border-sky-200 bg-sky-50/30' : ''}">
        <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center flex-shrink-0">
          <i data-lucide="${n.type === 'payment' ? 'check-circle' : n.type === 'alert' ? 'alert-triangle' : 'bell'}" class="w-5 h-5"></i>
        </div>
        <div class="flex-1">
          <div class="flex items-center justify-between">
            <h4 class="font-semibold text-slate-800 text-sm">${n.title}</h4>
            <span class="text-xs text-slate-400">${n.time}</span>
          </div>
          <p class="text-sm text-slate-600 mt-1">${n.message}</p>
        </div>
      </div>
    `;
  });
  container.innerHTML = html;
  lucide.createIcons();
}

// =======================================================
// MODAL FLOWS & ACTIONS
// =======================================================

let chargeSearchTimeout = null;
window.onChargePatientSearch = function(q) { onChargePatientSearchInput(q); };

function onChargePatientSearchInput(query) {
  clearTimeout(chargeSearchTimeout);
  const q = (query || '').trim();
  const resultsContainer = document.getElementById('charge-patient-search-results');
  const spinner = document.getElementById('charge-patient-spinner');

  if (q.length < 1) {
    if (resultsContainer) {
      resultsContainer.classList.add('hidden');
      resultsContainer.innerHTML = '';
    }
    return;
  }

  if (spinner) spinner.classList.remove('hidden');

  chargeSearchTimeout = setTimeout(async () => {
    try {
      const res = await fetch(`/api/search/patients?q=${encodeURIComponent(q)}&limit=8`);
      const json = await res.json();
      if (spinner) spinner.classList.add('hidden');

      if (!json.success || !json.data || json.data.length === 0) {
        resultsContainer.innerHTML = `
          <div class="p-3 text-center text-slate-400 text-xs italic">
            No patient records found matching "${q}"
          </div>`;
        resultsContainer.classList.remove('hidden');
        return;
      }

      let html = '';
      json.data.forEach(p => {
        const pCode = p.PatientCode || `TMH-${p.PatientID}`;
        const pName = p.FullName || `${p.FirstName} ${p.LastName}`;
        const catBadge = p.PatientCategory === 'Inpatient' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800';
        html += `
          <button type="button" onclick="selectChargePatient(${p.PatientID}, '${encodeURIComponent(pName)}', '${pCode}')" 
                  class="w-full text-left p-2.5 hover:bg-blue-50/80 transition flex items-center justify-between group">
            <div>
              <div class="font-extrabold text-slate-900 group-hover:text-blue-700 text-xs">${pName}</div>
              <div class="text-[10px] text-slate-400 font-mono mt-0.5">ID: ${pCode} • Age: ${p.Age || 'N/A'} • ${p.Gender || ''}</div>
            </div>
            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full ${catBadge}">
              ${p.PatientCategory || 'Outpatient'}
            </span>
          </button>
        `;
      });

      resultsContainer.innerHTML = html;
      resultsContainer.classList.remove('hidden');
    } catch (e) {
      if (spinner) spinner.classList.add('hidden');
      console.error(e);
    }
  }, 250);
}

function selectChargePatient(id, encName, code) {
  const name = decodeURIComponent(encName);
  const select = document.getElementById('charge-patient-select');
  if (select) {
    select.innerHTML = `<option value="${id}" selected>${name} (${code})</option>`;
  }

  const searchInput = document.getElementById('charge-patient-search-input');
  if (searchInput) searchInput.value = '';

  const results = document.getElementById('charge-patient-search-results');
  if (results) results.classList.add('hidden');

  const badge = document.getElementById('charge-patient-selected-badge');
  const badgeName = document.getElementById('charge-patient-selected-name');
  const badgeCode = document.getElementById('charge-patient-selected-code');

  if (badge && badgeName && badgeCode) {
    badgeName.innerText = name;
    badgeCode.innerText = code;
    badge.classList.remove('hidden');
  }

  const existingPatient = AppState.patients.find(p => p.id === String(id) || p.name === name);
  if (!existingPatient) {
    AppState.patients.push({ id: String(id), name: name, category: 'OPD' });
  }
}

function clearChargePatientSelection() {
  const select = document.getElementById('charge-patient-select');
  if (select) select.innerHTML = '<option value="">-- Select Patient --</option>';

  const badge = document.getElementById('charge-patient-selected-badge');
  if (badge) badge.classList.add('hidden');

  const searchInput = document.getElementById('charge-patient-search-input');
  if (searchInput) {
    searchInput.value = '';
    searchInput.focus();
  }
}

// 1. Create Charge Entry Modal
function openCreateChargeModal() {
  const modal = document.getElementById('modal-create-charge');
  if (!modal) return;

  // Reset live search and badge
  clearChargePatientSelection();

  // Reset inputs
  document.getElementById('charge-category').value = 'Laboratory';
  document.getElementById('charge-service-name').value = '';
  document.getElementById('charge-department').value = 'Laboratory';
  document.getElementById('charge-qty').value = '1';
  document.getElementById('charge-unit-price').value = '500';
  document.getElementById('charge-total-preview').innerText = '₱500';
  document.getElementById('charge-notes').value = '';

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  lucide.createIcons();
}

function updateChargeTotalCalculation() {
  const qty = parseFloat(document.getElementById('charge-qty').value) || 0;
  const unitPrice = parseFloat(document.getElementById('charge-unit-price').value) || 0;
  const total = qty * unitPrice;
  document.getElementById('charge-total-preview').innerText = formatPeso(total);
}

function saveNewCharge(event) {
  event.preventDefault();
  const patientId = document.getElementById('charge-patient-select').value;
  if (!patientId) {
    alert('Please select a patient.');
    return;
  }

  const patient = AppState.patients.find(p => p.id === patientId);
  const category = document.getElementById('charge-category').value;
  const serviceName = document.getElementById('charge-service-name').value;
  const department = document.getElementById('charge-department').value;
  const qty = parseInt(document.getElementById('charge-qty').value) || 1;
  const unitPrice = parseFloat(document.getElementById('charge-unit-price').value) || 0;
  const notes = document.getElementById('charge-notes').value;
  const total = qty * unitPrice;

  const newCharge = {
    id: `CHG-2026-${String(AppState.charges.length + 1).padStart(3, '0')}`,
    patientId: patient.id,
    patientName: patient.name,
    category: category,
    serviceName: serviceName,
    department: department,
    quantity: qty,
    unitPrice: unitPrice,
    total: total,
    date: "Aug 28, 2026",
    status: "Unbilled",
    notes: notes
  };

  AppState.charges.unshift(newCharge);
  closeModal('modal-create-charge');
  showToast(`Charge of ${formatPeso(total)} added for ${patient.name}`);
  addActivityLog('charge', 'New patient charge recorded', `${serviceName} (${formatPeso(total)}) added to ${patient.name}.`, 'PlusCircle', 'text-sky-600 bg-sky-50');
  
  recalculateMetrics();
  renderChargesTable();
}

// 2. Edit Charge Modal
function openEditChargeModal(chargeId) {
  const charge = AppState.charges.find(c => c.id === chargeId);
  if (!charge) return;

  AppState.chargeToEdit = charge;
  const modal = document.getElementById('modal-edit-charge');

  document.getElementById('edit-charge-id').value = charge.id;
  document.getElementById('edit-charge-patient').innerText = `${charge.patientName} (${charge.patientId})`;
  document.getElementById('edit-charge-service-name').value = charge.serviceName;
  document.getElementById('edit-charge-qty').value = charge.quantity;
  document.getElementById('edit-charge-unit-price').value = charge.unitPrice;
  document.getElementById('edit-charge-total-preview').innerText = formatPeso(charge.total);
  document.getElementById('edit-charge-notes').value = charge.notes || '';
  document.getElementById('edit-charge-updated-by').innerText = `${AppState.currentUser.name} • Aug 28, 2026`;

  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function updateEditChargeTotalCalculation() {
  const qty = parseFloat(document.getElementById('edit-charge-qty').value) || 0;
  const unitPrice = parseFloat(document.getElementById('edit-charge-unit-price').value) || 0;
  const total = qty * unitPrice;
  document.getElementById('edit-charge-total-preview').innerText = formatPeso(total);
}

function saveEditedCharge(event) {
  event.preventDefault();
  if (!AppState.chargeToEdit) return;

  const charge = AppState.chargeToEdit;
  const serviceName = document.getElementById('edit-charge-service-name').value;
  const qty = parseInt(document.getElementById('edit-charge-qty').value) || 1;
  const unitPrice = parseFloat(document.getElementById('edit-charge-unit-price').value) || 0;
  const notes = document.getElementById('edit-charge-notes').value;

  charge.serviceName = serviceName;
  charge.quantity = qty;
  charge.unitPrice = unitPrice;
  charge.total = qty * unitPrice;
  charge.notes = notes;

  closeModal('modal-edit-charge');
  showToast(`Charge ${charge.id} updated successfully`);
  recalculateMetrics();
  renderChargesTable();
}

// 3. Delete Charge Modal
function openDeleteChargeModal(chargeId) {
  const charge = AppState.charges.find(c => c.id === chargeId);
  if (!charge) return;

  if (charge.status === 'Paid' || charge.status === 'Included in Bill') {
    alert(`Cannot delete charge ${charge.id} because it is already '${charge.status}'. Please adjust invoice or permissions.`);
    return;
  }

  AppState.chargeToDelete = charge;
  const modal = document.getElementById('modal-delete-charge');
  document.getElementById('delete-charge-name').innerText = charge.serviceName;
  document.getElementById('delete-charge-amount').innerText = formatPeso(charge.total);
  document.getElementById('delete-charge-patient').innerText = `${charge.patientName} (${charge.patientId})`;

  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function confirmDeleteCharge() {
  if (!AppState.chargeToDelete) return;
  const id = AppState.chargeToDelete.id;
  AppState.charges = AppState.charges.filter(c => c.id !== id);
  closeModal('modal-delete-charge');
  showToast(`Charge ${id} deleted successfully.`, 'error');
  recalculateMetrics();
  renderChargesTable();
}

// 4. View Charge Breakdown Modal
function viewChargeDetails(chargeId) {
  const charge = AppState.charges.find(c => c.id === chargeId);
  if (!charge) return;

  const patient = AppState.patients.find(p => p.id === charge.patientId) || AppState.patients[0];
  const patientCharges = AppState.charges.filter(c => c.patientId === patient.id);

  const modal = document.getElementById('modal-view-patient-charges');
  document.getElementById('view-patient-name').innerText = patient.name;
  document.getElementById('view-patient-id').innerText = patient.id;
  document.getElementById('view-patient-room').innerText = patient.room;
  document.getElementById('view-patient-physician').innerText = patient.physician;

  let subtotal = 0;
  let rowsHtml = '';
  patientCharges.forEach(c => {
    subtotal += c.total;
    const bClass = c.status === 'Paid' ? 'badge-paid' : c.status === 'Included in Bill' ? 'badge-included' : 'badge-unbilled';
    rowsHtml += `
      <tr class="border-b border-slate-100 hover:bg-slate-50">
        <td class="py-2.5 px-3 font-medium text-slate-800">${c.serviceName}</td>
        <td class="py-2.5 px-3 text-slate-600 text-xs">${c.department}</td>
        <td class="py-2.5 px-3 text-center font-medium">${c.quantity}</td>
        <td class="py-2.5 px-3 text-right font-medium">${formatPeso(c.unitPrice)}</td>
        <td class="py-2.5 px-3 text-right font-semibold text-slate-900">${formatPeso(c.total)}</td>
        <td class="py-2.5 px-3 text-xs text-slate-400">${c.date}</td>
        <td class="py-2.5 px-3 text-center">
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${bClass}">${c.status}</span>
        </td>
      </tr>
    `;
  });

  document.getElementById('view-charges-tbody').innerHTML = rowsHtml;
  document.getElementById('view-charges-subtotal').innerText = formatPeso(subtotal);
  
  const bill = AppState.bills.find(b => b.patientId === patient.id);
  const discount = bill ? bill.discountAmount : 0;
  const total = Math.max(0, subtotal - discount);
  const paid = bill ? bill.amountPaid : 0;
  const balance = Math.max(0, total - paid);

  document.getElementById('view-charges-discount').innerText = formatPeso(discount);
  document.getElementById('view-charges-total').innerText = formatPeso(total);
  document.getElementById('view-charges-paid').innerText = formatPeso(paid);
  document.getElementById('view-charges-balance').innerText = formatPeso(balance);

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  lucide.createIcons();
}

// 5. Apply Deductions & PhilHealth Benefit Modal
function onDiscountTypeChange() {
  const type = document.getElementById('discount-type').value;
  const modeSel = document.getElementById('discount-mode');
  const valInput = document.getElementById('discount-rate-value');
  const reasonInput = document.getElementById('discount-reason');

  if (type === 'Senior Citizen') {
    modeSel.value = 'percentage';
    valInput.value = '20';
    if (!reasonInput.value) reasonInput.value = 'Senior Citizen RA 9994';
  } else if (type === 'PWD') {
    modeSel.value = 'percentage';
    valInput.value = '20';
    if (!reasonInput.value) reasonInput.value = 'PWD RA 10754';
  } else if (type === 'Hospital Employee') {
    modeSel.value = 'percentage';
    valInput.value = '15';
    if (!reasonInput.value) reasonInput.value = 'TMH Hospital Employee Discount';
  } else if (type === 'Indigent') {
    modeSel.value = 'percentage';
    valInput.value = '100';
    if (!reasonInput.value) reasonInput.value = 'MSWD Indigent Coverage Assistance';
  } else if (type === 'None') {
    valInput.value = '0';
    reasonInput.value = '';
  }
  updateDiscountLivePreview();
}

function openApplyDiscountModal() {
  const bill = getActiveBillForProcessing();
  if (!bill) {
    alert('No active patient bill selected.');
    return;
  }

  const modal = document.getElementById('modal-apply-discount');
  if (!modal) return;

  const origEl = document.getElementById('discount-original-amount');
  if (origEl) origEl.innerText = formatPeso(bill.subtotal || 0);
  
  // PhilHealth Fields
  const phIdInput = document.getElementById('discount-philhealth-id');
  if (phIdInput) phIdInput.value = bill.philhealthId || '';
  const phAmtInput = document.getElementById('discount-philhealth-amount');
  if (phAmtInput) phAmtInput.value = bill.philhealthDeduction || 0;

  // Other Discount Fields
  const discTypeEl = document.getElementById('discount-type');
  if (discTypeEl) discTypeEl.value = bill.discountType || 'None';
  const discModeEl = document.getElementById('discount-mode');
  if (discModeEl) discModeEl.value = bill.discountMode || 'percentage';
  const discRateEl = document.getElementById('discount-rate-value');
  if (discRateEl) discRateEl.value = bill.discountRate || (bill.discountType === 'Senior Citizen' || bill.discountType === 'PWD' ? 20 : 0);
  const discReasonEl = document.getElementById('discount-reason');
  if (discReasonEl) discReasonEl.value = bill.discountReason || '';
  const discAuthEl = document.getElementById('discount-authorized-by');
  if (discAuthEl) discAuthEl.value = bill.authorizedBy || 'Dr. Roberto Mendoza, MD';

  updateDiscountLivePreview();

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  if (typeof lucide !== 'undefined' && lucide.createIcons) {
    lucide.createIcons();
  }
}

function updateDiscountLivePreview() {
  const bill = getActiveBillForProcessing();
  if (!bill) return;

  const subtotal = bill.subtotal || 0;
  const philhealth = Math.max(0, parseFloat(document.getElementById('discount-philhealth-amount')?.value) || 0);

  const mode = document.getElementById('discount-mode')?.value || 'percentage';
  const val = Math.max(0, parseFloat(document.getElementById('discount-rate-value')?.value) || 0);
  let otherDeduction = 0;

  if (document.getElementById('discount-type')?.value !== 'None') {
    if (mode === 'percentage') {
      otherDeduction = (subtotal * val) / 100;
    } else {
      otherDeduction = val;
    }
  }

  const totalCovered = Math.min(subtotal, philhealth + otherDeduction);
  const remainingBalance = Math.max(0, subtotal - totalCovered);

  const origEl = document.getElementById('discount-original-amount');
  if (origEl) origEl.innerText = formatPeso(subtotal);

  const phPrevEl = document.getElementById('discount-philhealth-preview');
  if (phPrevEl) phPrevEl.innerText = `- ${formatPeso(philhealth)}`;

  const calcEl = document.getElementById('discount-calculated-amount');
  if (calcEl) calcEl.innerText = `- ${formatPeso(otherDeduction)}`;

  const covEl = document.getElementById('discount-total-covered');
  if (covEl) covEl.innerText = formatPeso(totalCovered);

  const finalEl = document.getElementById('discount-final-amount');
  if (finalEl) finalEl.innerText = formatPeso(remainingBalance);
}

function saveDiscount(event) {
  event.preventDefault();
  const bill = getActiveBillForProcessing();
  if (!bill) return;

  const subtotal = bill.subtotal || 0;
  const phId = document.getElementById('discount-philhealth-id')?.value || '';
  const philhealth = Math.max(0, parseFloat(document.getElementById('discount-philhealth-amount')?.value) || 0);

  const discType = document.getElementById('discount-type')?.value || 'None';
  const mode = document.getElementById('discount-mode')?.value || 'percentage';
  const val = Math.max(0, parseFloat(document.getElementById('discount-rate-value')?.value) || 0);
  const reason = document.getElementById('discount-reason')?.value || '';
  const authBy = document.getElementById('discount-authorized-by')?.value || 'Dr. Roberto Mendoza, MD';

  let otherDeduction = 0;
  if (discType !== 'None') {
    if (mode === 'percentage') {
      otherDeduction = (subtotal * val) / 100;
    } else {
      otherDeduction = val;
    }
  }

  const amountCovered = Math.min(subtotal, philhealth + otherDeduction);
  const remainingBalance = Math.max(0, subtotal - amountCovered);

  bill.philhealthId = phId;
  bill.philhealthDeduction = philhealth;
  bill.discountType = discType;
  bill.discountMode = mode;
  bill.discountRate = val;
  bill.discountAmount = otherDeduction;
  bill.amountCovered = amountCovered;
  bill.discountReason = reason;
  bill.authorizedBy = authBy;
  bill.totalBill = remainingBalance;
  bill.outstandingBalance = Math.max(0, remainingBalance - (bill.amountPaid || 0));
  bill.status = bill.outstandingBalance === 0 ? 'PAID' : (bill.amountPaid > 0 ? 'PARTIALLY PAID' : 'UNPAID');

  closeModal('modal-apply-discount');
  showToast(`Deductions saved! Total covered: ${formatPeso(amountCovered)}. Remaining Due: ${formatPeso(remainingBalance)}`);
  addActivityLog('discount', 'Billing deductions applied', `PhilHealth (${formatPeso(philhealth)}) + ${discType} (${formatPeso(otherDeduction)}) applied to ${bill.patientName}.`, 'Tag', 'text-blue-600 bg-blue-50');

  // Sync to database if available
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrfToken && bill.invoiceId) {
    fetch('/billing/api/discounts/compute', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        invoice_id: bill.invoiceId,
        patient_id: bill.patientId,
        subtotal: bill.subtotal,
        philhealth_deduction: bill.philhealthDeduction,
        other_deductions: bill.discountAmount,
        discount_type: bill.discountType,
        reason: bill.discountReason
      })
    }).catch(err => console.log('Live sync note:', err));
  }

  renderBillProcessing();
  recalculateMetrics();
}

// 6. Record Payment Modal & Multi-Method Authentic Processing
let paymentPollingInterval = null;

function calculateCashChange() {
  const amount = parseFloat(document.getElementById('payment-input-amount')?.value) || 0;
  const tendered = parseFloat(document.getElementById('cash-tendered-input')?.value) || 0;
  const changeDisplay = document.getElementById('cash-change-display');
  if (!changeDisplay) return;

  const change = Math.max(0, tendered - amount);
  changeDisplay.innerText = formatPeso(change);
  if (tendered < amount && tendered > 0) {
    changeDisplay.classList.remove('text-emerald-700');
    changeDisplay.classList.add('text-rose-600');
    changeDisplay.innerText = `Short by ${formatPeso(amount - tendered)}`;
  } else {
    changeDisplay.classList.remove('text-rose-600');
    changeDisplay.classList.add('text-emerald-700');
  }
}

function onPaymentMethodChange(val) {
  const refLabel = document.getElementById('payment-ref-label');
  const refInput = document.getElementById('payment-ref-no');
  const cashSection = document.getElementById('payment-cash-section');
  const bankSection = document.getElementById('payment-bank-section');
  const ewalletSection = document.getElementById('payment-ewallet-section');
  const emsSection = document.getElementById('payment-ems-section');
  const cashlessSection = document.getElementById('payment-cashless-section');
  const submitBtn = document.getElementById('btn-process-payment-submit');
  const submitLabel = document.getElementById('btn-process-payment-label');

  // Hide all optional helper sections first
  if (cashSection) cashSection.classList.add('hidden');
  if (bankSection) bankSection.classList.add('hidden');
  if (ewalletSection) ewalletSection.classList.add('hidden');
  if (emsSection) emsSection.classList.add('hidden');
  if (cashlessSection) cashlessSection.classList.add('hidden');

  if (val === 'Cash') {
    if (refLabel) refLabel.innerText = 'Official Slip / Receipt Reference';
    if (refInput) {
      refInput.placeholder = 'e.g. CSH-' + Math.floor(1000 + Math.random() * 9000);
      refInput.required = false;
    }
    if (cashSection) cashSection.classList.remove('hidden');
    if (submitLabel) submitLabel.innerText = 'Confirm Cash Payment';
    if (submitBtn) {
      submitBtn.className = 'px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-sm flex items-center gap-1.5';
    }
    calculateCashChange();
  } else if (val === 'Cashless Payment') {
    if (refLabel) refLabel.innerText = 'POS Terminal Trace / Ref #';
    if (refInput) {
      refInput.placeholder = 'e.g. POS-' + Math.floor(100000 + Math.random() * 900000);
      refInput.required = false;
    }
    if (cashlessSection) cashlessSection.classList.remove('hidden');
    if (submitLabel) submitLabel.innerText = 'Confirm Cashless Payment';
    if (submitBtn) {
      submitBtn.className = 'px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold transition-all shadow-sm flex items-center gap-1.5';
    }
  } else if (val === 'MedicalEMS') {
    if (refLabel) refLabel.innerText = 'MedicalEMS Voucher / Guarantee # *';
    if (refInput) {
      refInput.placeholder = 'e.g. EMS-VOUCH-' + Math.floor(1000 + Math.random() * 9000);
      refInput.required = false;
    }
    if (emsSection) emsSection.classList.remove('hidden');
    if (submitLabel) submitLabel.innerText = 'Apply MedicalEMS Guarantee Voucher';
    if (submitBtn) {
      submitBtn.className = 'px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition-all shadow-sm flex items-center gap-1.5';
    }
  } else if (val === 'Credit Card' || val === 'Debit Card' || val === 'Card') {
    if (refLabel) refLabel.innerText = 'Card Terminal Approval Code *';
    if (refInput) {
      refInput.placeholder = 'e.g. AUTH-' + Math.floor(100000 + Math.random() * 900000);
      refInput.required = false;
    }
    if (cashlessSection) cashlessSection.classList.remove('hidden');
    if (submitLabel) submitLabel.innerText = `Process ${val} Settlement`;
    if (submitBtn) {
      submitBtn.className = 'px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all shadow-sm flex items-center gap-1.5';
    }
  } else if (val === 'Bank Transfer') {
    if (refLabel) refLabel.innerText = 'Hospital Bank Deposit Slip # *';
    if (refInput) {
      refInput.placeholder = 'e.g. BT-REF-' + Math.floor(100000 + Math.random() * 900000);
      refInput.required = false;
    }
    if (bankSection) bankSection.classList.remove('hidden');
    if (submitLabel) submitLabel.innerText = 'Record Bank Transfer (Pending Verification)';
    if (submitBtn) {
      submitBtn.className = 'px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold transition-all shadow-sm flex items-center gap-1.5';
    }
  } else {
    // GCash, PayMaya, Maya, QR Ph
    if (refLabel) refLabel.innerText = `${val} Reference (Auto-Generated)`;
    if (refInput) {
      refInput.placeholder = 'Dynamic payment reference will be issued';
      refInput.required = false;
    }
    if (ewalletSection) ewalletSection.classList.remove('hidden');
    if (submitLabel) submitLabel.innerText = `Generate ${val} Dynamic QR & Pay`;
    if (submitBtn) {
      submitBtn.className = 'px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold transition-all shadow-sm flex items-center gap-1.5';
    }
  }
}

function openPaymentModalForBill(billId) {
  let bill = null;
  if (billId && typeof billId === 'string') {
    bill = AppState.bills.find(b => b.billId === billId || b.id === billId || b.invoiceId === billId);
  }
  if (!bill) {
    bill = getActiveBillForProcessing();
  }
  if (!bill) return;

  const modal = document.getElementById('modal-create-payment');
  if (!modal) return;

  const billIdInput = document.getElementById('payment-bill-id');
  if (billIdInput) billIdInput.value = bill.billId;

  const patNameEl = document.getElementById('payment-patient-name');
  if (patNameEl) patNameEl.innerText = `${bill.patientName || 'Patient'} (${bill.patientId || ''})`;

  const invNumEl = document.getElementById('payment-invoice-num');
  if (invNumEl) invNumEl.innerText = bill.invoiceId || bill.id || bill.billId;

  const totBillEl = document.getElementById('payment-total-bill');
  if (totBillEl) totBillEl.innerText = formatPeso(bill.totalBill !== undefined ? bill.totalBill : (bill.subtotal || 0));

  const paidEl = document.getElementById('payment-already-paid');
  if (paidEl) paidEl.innerText = formatPeso(bill.amountPaid || 0);

  const balance = (bill.outstandingBalance !== undefined) ? bill.outstandingBalance : ((bill.totalBill || bill.subtotal || 0) - (bill.amountPaid || 0));
  const outEl = document.getElementById('payment-outstanding');
  if (outEl) outEl.innerText = formatPeso(balance);

  const amtInput = document.getElementById('payment-input-amount');
  if (amtInput) amtInput.value = balance;

  const methodSelect = document.getElementById('payment-method-select');
  if (methodSelect) methodSelect.value = 'Cash';

  const refInput = document.getElementById('payment-ref-no');
  if (refInput) refInput.value = 'CSH-' + Math.floor(1000 + Math.random() * 9000);

  const notesInput = document.getElementById('payment-notes');
  if (notesInput) notesInput.value = 'Full settlement of bill';

  const warnEl = document.getElementById('payment-warning');
  if (warnEl) warnEl.classList.add('hidden');

  const tenderedInput = document.getElementById('cash-tendered-input');
  if (tenderedInput) tenderedInput.value = balance;

  const recByEl = document.getElementById('payment-recorded-by');
  if (recByEl) recByEl.innerText = (AppState.currentUser && AppState.currentUser.name) ? AppState.currentUser.name : 'Collecting Cashier';

  if (typeof onPaymentMethodChange === 'function') {
    onPaymentMethodChange('Cash');
  }

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  if (typeof lucide !== 'undefined' && lucide.createIcons) {
    lucide.createIcons();
  }
}

function openPaymentModalForCurrentBill() {
  openPaymentModalForBill();
}

function onPaymentAmountInput() {
  const billId = document.getElementById('payment-bill-id').value;
  const bill = AppState.bills.find(b => b.billId === billId);
  if (!bill) return;

  const entered = parseFloat(document.getElementById('payment-input-amount').value) || 0;
  const warningEl = document.getElementById('payment-warning');

  if (entered > bill.outstandingBalance) {
    warningEl.classList.remove('hidden');
    warningEl.innerText = `Warning: Payment amount (₱${entered}) exceeds the balance due (₱${bill.outstandingBalance}).`;
  } else {
    warningEl.classList.add('hidden');
  }
  calculateCashChange();
}

async function processPaymentSubmit(event) {
  event.preventDefault();
  const billId = document.getElementById('payment-bill-id').value;
  const bill = AppState.bills.find(b => b.billId === billId);
  if (!bill) return;

  const amount = parseFloat(document.getElementById('payment-input-amount').value) || 0;
  if (amount <= 0) {
    alert('Please enter a valid payment amount.');
    return;
  }

  const method = document.getElementById('payment-method-select').value;
  let refNo = document.getElementById('payment-ref-no')?.value?.trim();
  const notes = document.getElementById('payment-notes')?.value || '';
  const cashReceived = parseFloat(document.getElementById('cash-tendered-input')?.value) || amount;
  const bankName = document.getElementById('bank-name-select')?.value || 'Landbank of the Philippines';
  const bankRef = document.getElementById('bank-ref-input')?.value?.trim() || refNo;

  if (method === 'Cash' && cashReceived < amount) {
    alert(`Cash tendered (₱${cashReceived}) cannot be less than payment amount due (₱${amount}).`);
    return;
  }

  if (method === 'Bank Transfer' && !bankRef) {
    alert('Please provide a Bank Slip / Reference Number for audit tracking.');
    return;
  }

  if (amount > bill.outstandingBalance) {
    if (!confirm('Payment amount exceeds balance due. Do you wish to proceed?')) {
      return;
    }
  }

  const submitBtn = document.getElementById('btn-process-payment-submit');
  const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Processing...`;
  }

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch('/billing/api/payments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        invoice_id: bill.rawInvoiceId || bill.invoiceId,
        patient_id: bill.rawPatientId || bill.patientId,
        amount: amount,
        payment_method: method,
        reference_no: refNo || (method === 'Bank Transfer' ? bankRef : ''),
        cash_received: cashReceived,
        bank_name: bankName,
        bank_reference: bankRef,
        notes: notes
      })
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Payment initiation failed.');
    }

    closeModal('modal-create-payment');

    if (data.status === 'PAID') {
      // 1. CASH PAYMENT COMPLETE
      const newPayId = data.payment_id ? `PAY-2026-${String(data.payment_id).padStart(3, '0')}` : `PAY-${Date.now()}`;
      const receiptNo = data.receipt_number || `OR-2026-${String(AppState.officialReceipts.length + 1).padStart(3, '0')}`;
      
      const paymentObj = {
        paymentId: receiptNo,
        rawId: data.payment_id,
        patientId: bill.patientId,
        patientName: bill.patientName,
        invoiceId: bill.invoiceId,
        amount: amount,
        method: method,
        referenceNo: data.transaction_reference,
        date: new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        cashier: AppState.currentUser.name || 'Collecting Cashier',
        status: 'PAID',
        notes: notes
      };
      AppState.payments.unshift(paymentObj);

      bill.amountPaid += amount;
      bill.outstandingBalance = Math.max(0, bill.totalBill - bill.amountPaid);
      bill.status = bill.outstandingBalance === 0 ? 'PAID' : 'PARTIALLY PAID';

      const inv = AppState.invoices.find(i => i.invoiceId === bill.invoiceId);
      if (inv) {
        inv.paid = bill.amountPaid;
        inv.balance = bill.outstandingBalance;
        inv.status = bill.status;
      }

      // Add Official Receipt
      const orObj = {
        orNumber: receiptNo,
        date: new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }),
        receivedFrom: bill.patientName,
        patientId: bill.patientId,
        invoiceId: bill.invoiceId,
        amount: amount,
        paymentMethod: method,
        cashier: AppState.currentUser.name,
        amountInWords: numberToWords(amount),
        status: "Valid"
      };
      AppState.officialReceipts.unshift(orObj);

      const changeMsg = (data.change && data.change > 0) ? ` Change: ${formatPeso(data.change)}.` : '';
      showToast(`${method} payment of ${formatPeso(amount)} settled!${changeMsg} Receipt ${receiptNo} issued.`);
      addActivityLog('payment', `${method} payment settled`, `${formatPeso(amount)} received from ${bill.patientName} (Ref: ${data.transaction_reference}). Receipt: ${receiptNo}`, 'CreditCard', 'text-emerald-600 bg-emerald-50');

      recalculateMetrics();
      renderPaymentsTable();
      renderInvoicesTable();
      renderAccountsTables();
      if (AppState.activeView === 'billing') renderBillProcessing();

      setTimeout(() => {
        const changePrompt = (data.change && data.change > 0) ? `\nChange to return: ${formatPeso(data.change)}` : '';
        if (confirm(`${method} payment confirmed! Official Receipt ${receiptNo} issued.${changePrompt}\n\nWould you like to view/print the Official Receipt now?`)) {
          viewReceipt(receiptNo);
        }
      }, 300);

    } else if (data.status === 'PENDING_VERIFICATION') {
      // 2. BANK TRANSFER (Pending Verification)
      const newPayId = data.payment_id ? `PAY-2026-${String(data.payment_id).padStart(3, '0')}` : `PAY-${Date.now()}`;
      const paymentObj = {
        paymentId: newPayId,
        rawId: data.payment_id,
        patientId: bill.patientId,
        patientName: bill.patientName,
        invoiceId: bill.invoiceId,
        amount: amount,
        method: 'Bank Transfer',
        referenceNo: data.transaction_reference,
        date: new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        cashier: AppState.currentUser.name || 'Collecting Cashier',
        status: 'Pending Verification',
        notes: `Bank: ${data.bank_name}. Ref: ${data.bank_reference}`
      };
      AppState.payments.unshift(paymentObj);

      showToast(`Bank transfer recorded under Ref: ${data.transaction_reference}. Awaiting staff verification.`, 'info');
      addActivityLog('payment', 'Bank transfer pending', `Bank transfer of ${formatPeso(amount)} for ${bill.patientName} via ${data.bank_name}. Status: Pending Verification.`, 'Building', 'text-purple-600 bg-purple-50');

      renderPaymentsTable();

    } else {
      // 3. ELECTRONIC / QR PAYMENT (GCash, Maya, QR Ph)
      openQrPaymentModal(data, bill);
    }

  } catch (err) {
    alert(err.message || 'Payment processing error');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnHtml;
    }
  }
}

function openQrPaymentModal(paymentData, billContext) {
  AppState.activeElectronicPayment = {
    ...paymentData,
    billContext: billContext || AppState.bills.find(b => b.invoiceId === paymentData.invoice_number)
  };

  const modal = document.getElementById('modal-qr-payment');
  if (!modal) return;

  // Set Waiting State
  document.getElementById('qr-state-waiting')?.classList.remove('hidden');
  document.getElementById('qr-state-success')?.classList.add('hidden');
  document.getElementById('qr-state-failed')?.classList.add('hidden');

  // Fill in invoice info
  document.getElementById('qr-invoice-no').innerText = paymentData.invoice_number || 'INV-2026';
  document.getElementById('qr-patient-name').innerText = paymentData.patient_name || 'Patient Record';
  document.getElementById('qr-amount-due').innerText = formatPeso(paymentData.amount);
  document.getElementById('qr-txn-reference').innerText = paymentData.transaction_reference;
  document.getElementById('qr-live-status').innerText = '● Waiting for Payment...';

  // Render dynamic QR Code graphic
  const qrContainer = document.getElementById('qr-code-graphic');
  if (qrContainer) {
    qrContainer.innerHTML = '';
    if (window.QRCode && paymentData.qr_payload) {
      new QRCode(qrContainer, {
        text: paymentData.qr_payload,
        width: 170,
        height: 170,
        colorDark: "#0f172a",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
      });
    } else {
      qrContainer.innerHTML = `<div class="p-6 text-center text-xs text-slate-500 font-mono break-all">${paymentData.qr_payload || paymentData.transaction_reference}</div>`;
    }
  }

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  if (window.lucide) lucide.createIcons();

  // Start polling every 3 seconds
  if (paymentPollingInterval) clearInterval(paymentPollingInterval);
  paymentPollingInterval = setInterval(() => {
    checkCurrentPaymentStatus(false);
  }, 3000);
}

async function checkCurrentPaymentStatus(manual = false) {
  if (!AppState.activeElectronicPayment?.payment_id) return;
  const payId = AppState.activeElectronicPayment.payment_id;

  try {
    const res = await fetch(`/billing/api/payments/${payId}/status`);
    if (!res.ok) return;
    const data = await res.json();

    if (data.status === 'PAID') {
      if (paymentPollingInterval) {
        clearInterval(paymentPollingInterval);
        paymentPollingInterval = null;
      }
      handleElectronicPaymentSuccess(data);
    } else if (data.status === 'FAILED' || data.status === 'CANCELLED' || data.status === 'EXPIRED') {
      if (paymentPollingInterval) {
        clearInterval(paymentPollingInterval);
        paymentPollingInterval = null;
      }
      handleElectronicPaymentFailure(data);
    } else if (manual) {
      showToast(`Status: ${data.status_label || data.status}. Waiting for payment confirmation...`, 'info');
    }
  } catch (e) {
    console.error('Polling status error:', e);
  }
}

function handleElectronicPaymentSuccess(data) {
  const modal = document.getElementById('modal-qr-payment');
  if (!modal) return;

  document.getElementById('qr-state-waiting')?.classList.add('hidden');
  document.getElementById('qr-state-success')?.classList.remove('hidden');
  document.getElementById('qr-state-failed')?.classList.add('hidden');

  document.getElementById('success-amount').innerText = formatPeso(data.amount);
  document.getElementById('success-method').innerText = (data.payment_method || 'QR Ph').toUpperCase();
  document.getElementById('success-reference').innerText = data.transaction_reference;
  document.getElementById('success-provider-id').innerText = data.provider_transaction_id || 'PROVIDER-VERIFIED';

  // Update local AppState
  const bill = AppState.activeElectronicPayment.billContext;
  if (bill) {
    bill.amountPaid += data.amount;
    bill.outstandingBalance = Math.max(0, bill.totalBill - bill.amountPaid);
    bill.status = bill.outstandingBalance === 0 ? 'PAID' : 'PARTIALLY PAID';

    const inv = AppState.invoices.find(i => i.invoiceId === bill.invoiceId);
    if (inv) {
      inv.paid = bill.amountPaid;
      inv.balance = bill.outstandingBalance;
      inv.status = bill.status;
    }
  }

  const receiptNo = data.receipt_number || `OR-2026-${String(AppState.officialReceipts.length + 1).padStart(3, '0')}`;
  AppState.officialReceipts.unshift({
    orNumber: receiptNo,
    date: new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }),
    receivedFrom: bill?.patientName || 'Patient Record',
    patientId: bill?.patientId || 'P-2026',
    invoiceId: data.invoice_number || bill?.invoiceId || 'INV-2026',
    amount: data.amount,
    paymentMethod: (data.payment_method || 'QR Ph').toUpperCase(),
    cashier: AppState.currentUser.name,
    amountInWords: numberToWords(data.amount),
    status: "Valid"
  });

  AppState.payments.unshift({
    paymentId: receiptNo,
    rawId: data.payment_id,
    patientId: bill?.patientId || 'P-2026',
    patientName: bill?.patientName || 'Patient Record',
    invoiceId: data.invoice_number || bill?.invoiceId || 'INV-2026',
    amount: data.amount,
    method: (data.payment_method || 'QR Ph').toUpperCase(),
    referenceNo: data.transaction_reference,
    date: new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    cashier: AppState.currentUser.name || 'Collecting Cashier',
    status: 'PAID',
    notes: `Provider ID: ${data.provider_transaction_id}`
  });

  showToast(`✓ Payment of ${formatPeso(data.amount)} verified! Official Receipt ${receiptNo} generated.`);
  addActivityLog('payment', 'Payment verified', `Verified ${data.payment_method} payment for ${data.invoice_number}. Provider Ref: ${data.provider_transaction_id}`, 'CheckCircle2', 'text-emerald-600 bg-emerald-50');

  recalculateMetrics();
  renderPaymentsTable();
  renderInvoicesTable();
  renderAccountsTables();
  if (AppState.activeView === 'billing') renderBillProcessing();
  if (window.lucide) lucide.createIcons();
}

function handleElectronicPaymentFailure(data) {
  document.getElementById('qr-state-waiting')?.classList.add('hidden');
  document.getElementById('qr-state-success')?.classList.add('hidden');
  document.getElementById('qr-state-failed')?.classList.remove('hidden');

  const reasonEl = document.getElementById('qr-fail-reason');
  if (reasonEl) {
    reasonEl.innerText = data.failure_reason || 'Payment was declined or cancelled by the customer.';
  }
  if (window.lucide) lucide.createIcons();
}

async function simulateProviderApproval() {
  if (!AppState.activeElectronicPayment?.payment_id) return;
  const payId = AppState.activeElectronicPayment.payment_id;

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(`/billing/api/payments/${payId}/simulate-callback`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ outcome: 'PAID' })
    });
    const data = await res.json();
    if (data.success) {
      checkCurrentPaymentStatus();
    } else {
      alert(data.message || 'Simulation failed');
    }
  } catch (e) {
    alert('Simulation error: ' + e.message);
  }
}

async function simulateProviderFailure() {
  if (!AppState.activeElectronicPayment?.payment_id) return;
  const payId = AppState.activeElectronicPayment.payment_id;

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(`/billing/api/payments/${payId}/simulate-callback`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ outcome: 'FAILED' })
    });
    const data = await res.json();
    checkCurrentPaymentStatus();
  } catch (e) {
    alert('Simulation error: ' + e.message);
  }
}

function cancelElectronicPayment() {
  if (paymentPollingInterval) {
    clearInterval(paymentPollingInterval);
    paymentPollingInterval = null;
  }
  closeModal('modal-qr-payment');
}

function retryElectronicPayment() {
  closeModal('modal-qr-payment');
  const bill = AppState.activeElectronicPayment?.billContext;
  if (bill) {
    openPaymentModalForBill(bill.billId);
  }
}

function viewReceiptFromQrModal() {
  closeModal('modal-qr-payment');
  const r = AppState.officialReceipts[0];
  if (r) viewReceipt(r.orNumber);
}

function printReceiptFromQrModal() {
  closeModal('modal-qr-payment');
  const r = AppState.officialReceipts[0];
  if (r) printReceiptDirect(r.orNumber);
}

async function verifyBankTransferAction(paymentId) {
  const pay = AppState.payments.find(p => p.paymentId === paymentId || p.rawId == paymentId);
  const rawId = pay?.rawId || paymentId;

  const bankSlip = prompt(`Verify Bank Transfer for ${pay ? pay.patientName : 'Patient'}\n\nEnter verified bank deposit / slip transaction number:`, pay?.referenceNo || '');
  if (bankSlip === null) return; // User cancelled

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(`/billing/api/payments/${rawId}/verify-bank`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ bank_reference: bankSlip })
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Verification failed');
    }

    if (pay) {
      pay.status = 'PAID';
      pay.referenceNo = bankSlip || pay.referenceNo;
      pay.notes = `Verified by Staff. Receipt: ${data.receipt_number}`;
    }

    // Update bill & invoice if found
    const bill = AppState.bills.find(b => b.invoiceId === pay?.invoiceId);
    if (bill && pay) {
      bill.amountPaid += pay.amount;
      bill.outstandingBalance = Math.max(0, bill.totalBill - bill.amountPaid);
      bill.status = bill.outstandingBalance === 0 ? 'PAID' : 'PARTIALLY PAID';

      const inv = AppState.invoices.find(i => i.invoiceId === bill.invoiceId);
      if (inv) {
        inv.paid = bill.amountPaid;
        inv.balance = bill.outstandingBalance;
        inv.status = bill.status;
      }
    }

    const receiptNo = data.receipt_number || `OR-2026-${String(AppState.officialReceipts.length + 1).padStart(3, '0')}`;
    AppState.officialReceipts.unshift({
      orNumber: receiptNo,
      date: new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }),
      receivedFrom: pay?.patientName || 'Patient',
      patientId: pay?.patientId || 'P-2026',
      invoiceId: pay?.invoiceId || 'INV-2026',
      amount: pay?.amount || 0,
      paymentMethod: 'Bank Transfer (Verified)',
      cashier: AppState.currentUser.name,
      amountInWords: numberToWords(pay?.amount || 0),
      status: "Valid"
    });

    showToast(`Bank Transfer verified! Status changed to PAID. Official Receipt: ${receiptNo}`);
    addActivityLog('payment', 'Bank transfer verified', `Bank payment of ${formatPeso(pay?.amount || 0)} verified for ${pay?.patientName}. Slip: ${bankSlip}. Receipt: ${receiptNo}`, 'CheckCircle2', 'text-purple-600 bg-purple-50');

    recalculateMetrics();
    renderPaymentsTable();
    renderInvoicesTable();
    renderAccountsTables();
  } catch (err) {
    alert(err.message || 'Verification error');
  }
}

async function resumeElectronicPayment(paymentId) {
  const pay = AppState.payments.find(p => p.paymentId === paymentId || p.rawId == paymentId);
  const rawId = pay?.rawId || paymentId;

  try {
    const res = await fetch(`/billing/api/payments/${rawId}/status`);
    if (!res.ok) throw new Error('Could not fetch payment details');
    const data = await res.json();
    openQrPaymentModal(data, AppState.bills.find(b => b.invoiceId === data.invoice_number));
  } catch (e) {
    alert(e.message);
  }
}

// 7. Edit Payment Record Modal
function openEditPaymentModal(paymentId) {
  const pay = AppState.payments.find(p => p.paymentId === paymentId);
  if (!pay) return;

  AppState.paymentToEdit = pay;
  const modal = document.getElementById('modal-edit-payment');

  document.getElementById('edit-payment-id').innerText = pay.paymentId;
  document.getElementById('edit-payment-patient').innerText = `${pay.patientName} (${pay.patientId})`;
  document.getElementById('edit-payment-amount').innerText = formatPeso(pay.amount);
  document.getElementById('edit-payment-method').value = pay.method;
  document.getElementById('edit-payment-ref-no').value = pay.referenceNo;
  document.getElementById('edit-payment-notes').value = pay.notes || '';
  document.getElementById('edit-payment-updated-by').innerText = `${AppState.currentUser.name} • Aug 28, 2026`;

  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function saveEditedPayment(event) {
  event.preventDefault();
  if (!AppState.paymentToEdit) return;

  const pay = AppState.paymentToEdit;
  pay.method = document.getElementById('edit-payment-method').value;
  pay.referenceNo = document.getElementById('edit-payment-ref-no').value;
  pay.notes = document.getElementById('edit-payment-notes').value;

  closeModal('modal-edit-payment');
  showToast(`Payment record ${pay.paymentId} updated successfully`);
  renderPaymentsTable();
}

// 8. Generate & View Invoice
function viewInvoice(invoiceId) {
  let inv = null;
  if (invoiceId && typeof invoiceId === 'string') {
    inv = AppState.invoices.find(i => i.invoiceId === invoiceId || i.id === invoiceId);
  }
  if (!inv) {
    const bill = getActiveBillForProcessing();
    if (bill) {
      inv = AppState.invoices.find(i => i.invoiceId === bill.invoiceId || i.patientId === bill.patientId) || bill;
    }
  }
  if (!inv && AppState.invoices.length > 0) {
    inv = AppState.invoices[0];
  }
  if (!inv && AppState.bills.length > 0) {
    inv = AppState.bills[0];
  }
  if (!inv) return;

  const patient = (AppState.patients && AppState.patients.find(p => p.id === inv.patientId)) || 
                  (AppState.patients && AppState.patients[0]) || 
                  { name: inv.patientName || 'Patient', id: inv.patientId || 'P-001', room: 'OPD Triage', physician: 'Attending Physician' };
  const bill = (AppState.bills && AppState.bills.find(b => b.invoiceId === inv.invoiceId || b.patientId === inv.patientId)) || inv;

  AppState.activeInvoice = { inv, patient, bill };
  const modal = document.getElementById('modal-view-invoice');
  if (!modal) return;

  // Populate Invoice Preview with safe null-checking
  const hospName = document.getElementById('inv-preview-hospital-name');
  if (hospName) hospName.innerText = (AppState.settings && AppState.settings.hospitalName) || 'Tupi Municipal Hospital';
  const hospAddr = document.getElementById('inv-preview-hospital-address');
  if (hospAddr) hospAddr.innerText = (AppState.settings && AppState.settings.hospitalAddress) || 'National Highway, Poblacion, Tupi, South Cotabato';
  const hospCont = document.getElementById('inv-preview-hospital-contact');
  if (hospCont) hospCont.innerText = (AppState.settings && AppState.settings.hospitalContact) || 'Tel: (083) 228-1234';
  
  const invNum = document.getElementById('inv-preview-num');
  if (invNum) invNum.innerText = inv.invoiceId || inv.id || 'INV-2026-001';
  const invDate = document.getElementById('inv-preview-date');
  if (invDate) invDate.innerText = inv.date || new Date().toLocaleDateString();
  const patName = document.getElementById('inv-preview-patient-name');
  if (patName) patName.innerText = patient.name || inv.patientName || 'Patient';
  const patId = document.getElementById('inv-preview-patient-id');
  if (patId) patId.innerText = patient.id || inv.patientId || '';
  const patRoom = document.getElementById('inv-preview-room');
  if (patRoom) patRoom.innerText = patient.room || 'OPD';
  const patPhys = document.getElementById('inv-preview-physician');
  if (patPhys) patPhys.innerText = patient.physician || 'Attending Physician';

  const tbody = document.getElementById('inv-preview-items-tbody');
  if (tbody) {
    let itemsHtml = '';
    const items = (bill && Array.isArray(bill.items) && bill.items.length > 0) ? bill.items : [
      { service: 'Hospital Diagnostic & Clinical Care', department: 'Clinical Services', quantity: 1, unitPrice: (bill && bill.subtotal) || 500, amount: (bill && bill.subtotal) || 500 }
    ];
    items.forEach(it => {
      itemsHtml += `
        <tr class="border-b border-slate-100">
          <td class="py-2.5 px-3">
            <div class="font-medium text-slate-800">${it.service}</div>
            <div class="text-xs text-slate-400">${it.department || 'Clinical'}</div>
          </td>
          <td class="py-2.5 px-3 text-center">${it.quantity || 1}</td>
          <td class="py-2.5 px-3 text-right">${formatPeso(it.unitPrice || 0)}</td>
          <td class="py-2.5 px-3 text-right font-semibold text-slate-900">${formatPeso(it.amount || 0)}</td>
        </tr>
      `;
    });
    tbody.innerHTML = itemsHtml;
  }

  const subEl = document.getElementById('inv-preview-subtotal');
  if (subEl) subEl.innerText = formatPeso((bill && bill.subtotal) || (inv && inv.subtotal) || 0);

  const discEl = document.getElementById('inv-preview-discount');
  if (discEl) discEl.innerText = `- ${formatPeso((bill && bill.discountAmount) || 0)}`;

  const totEl = document.getElementById('inv-preview-total');
  if (totEl) totEl.innerText = formatPeso((bill && bill.totalBill) || (inv && inv.totalBill) || 0);

  const paidEl = document.getElementById('inv-preview-paid');
  const paidVal = inv.amountPaid !== undefined ? inv.amountPaid : (inv.paid !== undefined ? inv.paid : ((bill && bill.amountPaid) || 0));
  if (paidEl) paidEl.innerText = formatPeso(paidVal);

  const balEl = document.getElementById('inv-preview-balance');
  const balVal = inv.outstandingBalance !== undefined ? inv.outstandingBalance : (inv.balance !== undefined ? inv.balance : ((bill && bill.outstandingBalance) || 0));
  if (balEl) balEl.innerText = formatPeso(balVal);

  const statEl = document.getElementById('inv-preview-status');
  if (statEl) statEl.innerText = inv.status || ((bill && bill.status) || 'UNPAID');

  const cashEl = document.getElementById('inv-preview-cashier');
  if (cashEl) cashEl.innerText = inv.cashier || (AppState.currentUser && AppState.currentUser.name) || 'Collecting Cashier';

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  if (typeof lucide !== 'undefined' && lucide.createIcons) {
    lucide.createIcons();
  }
}

function viewCurrentPatientInvoice() {
  viewInvoice();
}

function printInvoiceDirect(invoiceId) {
  viewInvoice(invoiceId);
  setTimeout(() => {
    printCurrentInvoice();
  }, 200);
}

function printCurrentInvoice() {
  if (!AppState.activeInvoice) return;
  const { inv, patient, bill } = AppState.activeInvoice;

  let itemsHtml = '';
  bill.items.forEach(it => {
    itemsHtml += `
      <tr>
        <td><strong>${it.service}</strong><br><small>${it.department}</small></td>
        <td style="text-align:center;">${it.quantity}</td>
        <td style="text-align:right;">${formatPeso(it.unitPrice)}</td>
        <td style="text-align:right;"><strong>${formatPeso(it.amount)}</strong></td>
      </tr>
    `;
  });

  const printArea = document.getElementById('printable-area');
  printArea.innerHTML = `
    <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; color: #111;">
      <div style="border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
          <h2 style="margin: 0; font-size: 20px; color: #0284c7; font-weight: 800;">TMHIS HEALTHCARE CENTER</h2>
          <p style="margin: 3px 0 0; font-size: 12px; color: #555;">${AppState.settings.hospitalAddress}</p>
          <p style="margin: 2px 0 0; font-size: 12px; color: #555;">${AppState.settings.hospitalContact} | TIN: ${AppState.settings.hospitalTin}</p>
        </div>
        <div style="text-align: right;">
          <h3 style="margin: 0; font-size: 18px; font-weight: bold; letter-spacing: 1px;">PATIENT INVOICE</h3>
          <p style="margin: 4px 0 0; font-family: monospace; font-size: 14px; font-weight: bold;">${inv.invoiceId}</p>
          <p style="margin: 2px 0 0; font-size: 12px;">Date: ${inv.date}</p>
        </div>
      </div>

      <div style="background-color: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
        <div>
          <p style="margin: 0 0 4px;"><strong>Patient Name:</strong> ${patient.name}</p>
          <p style="margin: 0 0 4px;"><strong>Patient ID:</strong> ${patient.id}</p>
          <p style="margin: 0;"><strong>Room / Bed:</strong> ${patient.room}</p>
        </div>
        <div>
          <p style="margin: 0 0 4px;"><strong>Attending Physician:</strong> ${patient.physician}</p>
          <p style="margin: 0 0 4px;"><strong>Payment Status:</strong> <strong>${inv.status}</strong></p>
          <p style="margin: 0;"><strong>Cashier:</strong> ${inv.cashier}</p>
        </div>
      </div>

      <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px;">
        <thead>
          <tr style="border-top: 1.5px solid #000; border-bottom: 1.5px solid #000;">
            <th style="text-align: left; padding: 8px 4px;">SERVICE / DESCRIPTION</th>
            <th style="text-align: center; padding: 8px 4px; width: 60px;">QTY</th>
            <th style="text-align: right; padding: 8px 4px; width: 100px;">UNIT PRICE</th>
            <th style="text-align: right; padding: 8px 4px; width: 110px;">AMOUNT</th>
          </tr>
        </thead>
        <tbody>
          ${itemsHtml}
        </tbody>
      </table>

      <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
        <div style="width: 280px; font-size: 13px;">
          <div style="display: flex; justify-content: space-between; padding: 3px 0;">
            <span>Subtotal:</span>
            <span>${formatPeso(bill.subtotal)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 3px 0; color: #7e22ce;">
            <span>Discount:</span>
            <span>- ${formatPeso(bill.discountAmount)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 6px 0; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-weight: bold; font-size: 15px;">
            <span>TOTAL BILL:</span>
            <span>${formatPeso(bill.totalBill)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 3px 0; margin-top: 4px;">
            <span>Amount Paid:</span>
            <span style="color: #059669; font-weight: bold;">${formatPeso(inv.paid)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 3px 0; font-weight: bold;">
            <span>Outstanding Balance:</span>
            <span style="color: ${inv.balance > 0 ? '#e11d48' : '#059669'};">${formatPeso(inv.balance)}</span>
          </div>
        </div>
      </div>

      <div style="margin-top: 40px; border-top: 1px solid #cbd5e1; padding-top: 16px; display: flex; justify-content: space-between; font-size: 12px; color: #555;">
        <div>
          <p style="margin: 0;">This document serves as an official billing statement.</p>
          <p style="margin: 2px 0 0;">Thank you for trusting TMHIS Healthcare Center.</p>
        </div>
        <div style="text-align: right;">
          <p style="margin: 0; border-bottom: 1px solid #000; padding-bottom: 2px; width: 160px; font-weight: bold; text-align: center;">${inv.cashier}</p>
          <p style="margin: 2px 0 0; text-align: center;">Authorized Cashier Signature</p>
        </div>
      </div>
    </div>
  `;

  printArea.classList.remove('hidden');
  window.print();
  setTimeout(() => {
    printArea.classList.add('hidden');
  }, 500);
}

function openOfficialTMHISPrintInvoice() {
  const invId = AppState.activeInvoice?.inv?.invoiceId || 'INV-2026-001';
  window.open(`/print/billing/${encodeURIComponent(invId)}`, '_blank');
}

function openOfficialTMHISPrintReceipt() {
  const orNum = AppState.activeReceipt?.orNumber || 'OR-2026-001';
  window.open(`/print/receipt/${encodeURIComponent(orNum)}`, '_blank');
}

// 9. View & Print Official Receipt (OR)
function viewReceipt(orNumber) {
  let receipt = AppState.officialReceipts.find(r => r.orNumber === orNumber);
  if (!receipt) {
    receipt = AppState.officialReceipts[0];
  }

  AppState.activeReceipt = receipt;
  const modal = document.getElementById('modal-view-receipt');

  document.getElementById('or-preview-num').innerText = receipt.orNumber;
  document.getElementById('or-preview-date').innerText = receipt.date;
  document.getElementById('or-preview-received-from').innerText = receipt.receivedFrom;
  document.getElementById('or-preview-invoice-num').innerText = receipt.invoiceId;
  document.getElementById('or-preview-amount').innerText = formatPeso(receipt.amount);
  document.getElementById('or-preview-payment-method').innerText = receipt.paymentMethod;
  document.getElementById('or-preview-cashier').innerText = receipt.cashier;
  document.getElementById('or-preview-amount-words').innerText = receipt.amountInWords || numberToWords(receipt.amount);

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  lucide.createIcons();
}

function openReceiptForPayment(paymentId) {
  const pay = AppState.payments.find(p => p.paymentId === paymentId);
  if (!pay) return;

  const receipt = AppState.officialReceipts.find(r => r.invoiceId === pay.invoiceId && r.amount === pay.amount) || AppState.officialReceipts[0];
  viewReceipt(receipt.orNumber);
}

function printReceiptDirect(orNumber) {
  viewReceipt(orNumber);
  setTimeout(() => {
    printCurrentReceipt();
  }, 200);
}

function printCurrentReceipt() {
  if (!AppState.activeReceipt) return;
  const r = AppState.activeReceipt;

  const printArea = document.getElementById('printable-area');
  printArea.innerHTML = `
    <div style="font-family: Arial, sans-serif; padding: 24px; max-width: 650px; margin: 0 auto; border: 2px solid #0f172a; border-radius: 8px; color: #111;">
      <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 16px;">
        <h2 style="margin: 0; font-size: 22px; font-weight: 800; color: #0284c7;">TMHIS HEALTHCARE MEDICAL CENTER</h2>
        <p style="margin: 4px 0 0; font-size: 12px; color: #555;">${AppState.settings.hospitalAddress}</p>
        <p style="margin: 2px 0 0; font-size: 12px; color: #555;">TIN: ${AppState.settings.hospitalTin} | Tel: ${AppState.settings.hospitalContact}</p>
        <div style="display: inline-block; background-color: #0f172a; color: #fff; padding: 4px 16px; border-radius: 4px; font-size: 14px; font-weight: bold; margin-top: 10px; letter-spacing: 1px;">
          OFFICIAL RECEIPT
        </div>
      </div>

      <div style="display: flex; justify-content: space-between; margin-bottom: 14px; font-size: 13px;">
        <div><strong>OR Number:</strong> <span style="font-family: monospace; font-weight: bold; font-size: 15px; color: #0369a1;">${r.orNumber}</span></div>
        <div><strong>Date:</strong> ${r.date}</div>
      </div>

      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 16px; font-size: 13px; line-height: 1.8;">
        <div><strong>Received From:</strong> ${r.receivedFrom}</div>
        <div><strong>Settlement For Invoice:</strong> <span style="font-family: monospace;">${r.invoiceId}</span></div>
        <div><strong>Payment Method:</strong> ${r.paymentMethod}</div>
        <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1;">
          <strong>Amount Received:</strong> <span style="font-size: 18px; font-weight: bold; color: #059669;">${formatPeso(r.amount)}</span>
        </div>
        <div><strong>Amount in Words:</strong> <em>${r.amountInWords}</em></div>
      </div>

      <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 12px;">
        <div>
          <p style="margin: 0; color: #64748b;">BIR Permit / POS Counter #03</p>
          <p style="margin: 2px 0 0; color: #64748b;">Official Hospital Receipt</p>
        </div>
        <div style="text-align: center;">
          <div style="border-bottom: 1.5px solid #000; width: 180px; padding-bottom: 4px; font-weight: bold;">${r.cashier}</div>
          <div style="margin-top: 4px; color: #334155;">Collecting Cashier</div>
        </div>
      </div>
    </div>
  `;

  printArea.classList.remove('hidden');
  window.print();
  setTimeout(() => {
    printArea.classList.add('hidden');
  }, 500);
}

// 10. User Profile Dropdown & Modal
function toggleProfileDropdown() {
  const menu = document.getElementById('profile-dropdown-menu');
  if (menu) menu.classList.toggle('hidden');
}

function openProfileModal() {
  const menu = document.getElementById('profile-dropdown-menu');
  if (menu) menu.classList.add('hidden');
  const modal = document.getElementById('modal-user-profile');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

// Close any open modal
function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Global Filter and Search Listener
function onGlobalSearch(val) {
  AppState.searchTerm = val;
  if (AppState.activeView === 'charges') renderChargesTable();
  else if (AppState.activeView === 'payments') renderPaymentsTable();
  else if (AppState.activeView === 'accounts') renderAccountsTables();
  else if (AppState.activeView === 'invoices') renderInvoicesTable();
  else if (AppState.activeView === 'receipts') renderReceiptsTable();
}

// App Initialization
document.addEventListener('DOMContentLoaded', () => {
  // Initialize Lucide Icons
  lucide.createIcons();

  // Render initial dashboard & pre-prime bill processing state
  renderDashboard();
  if (typeof renderBillProcessing === 'function') {
    renderBillProcessing();
  }

  // Close modals on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-wrapper:not(.hidden)').forEach(modal => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      });
      const profileMenu = document.getElementById('profile-dropdown-menu');
      if (profileMenu) profileMenu.classList.add('hidden');
    }
  });

  // Global search input listener
  const searchInput = document.getElementById('global-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      onGlobalSearch(e.target.value);
    });
  }
});

// ============================================================================
// POINT-OF-SALE (POS) / PHARMACY RETAIL SALES MODULE
// ============================================================================
const PosModule = {
  state: {
    medicines: [],
    cart: [],
    customerType: 'walkin', // 'walkin' | 'patient'
    patientId: null,
    patientName: '',
    patientCode: '',
    discountType: 'None',
    discountAmount: 0.00,
    paymentMethod: 'Cash',
    currentReceiptUrl: '',
    isCheckingOut: false,
    currentDosageFilter: 'All',
    searchQuery: '',
    patientSearchTimer: null
  },

  init: function () {
    this.loadMedicines();
    this.loadSalesHistory();
    this.renderCart();
    this.updateDiscount();
  },

  loadMedicines: async function () {
    try {
      const res = await fetch('/billing/api/pos/medicines');
      const data = await res.json();
      if (data.success) {
        this.state.medicines = data.data || [];
        this.filterMedicines();
      }
    } catch (err) {
      console.error('Failed to load medicines for POS:', err);
    }
  },

  setCustomerType: function (type) {
    this.state.customerType = type;
    const btnWalkin = document.getElementById('pos-btn-walkin');
    const btnPatient = document.getElementById('pos-btn-patient');
    const walkinInputs = document.getElementById('pos-walkin-inputs');
    const patientInputs = document.getElementById('pos-patient-inputs');

    if (type === 'walkin') {
      btnWalkin.className = 'px-3.5 py-1.5 rounded-lg transition-all bg-white text-slate-800 shadow-sm';
      btnPatient.className = 'px-3.5 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800';
      walkinInputs.classList.remove('hidden');
      patientInputs.classList.add('hidden');
    } else {
      btnPatient.className = 'px-3.5 py-1.5 rounded-lg transition-all bg-white text-slate-800 shadow-sm';
      btnWalkin.className = 'px-3.5 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800';
      patientInputs.classList.remove('hidden');
      walkinInputs.classList.add('hidden');
    }
  },

  searchPatients: function (val) {
    clearTimeout(this.state.patientSearchTimer);
    const query = (val || '').trim();
    const dropdown = document.getElementById('pos-patient-dropdown');
    if (!dropdown) return;

    if (query.length < 1) {
      dropdown.classList.add('hidden');
      dropdown.innerHTML = '';
      return;
    }

    this.state.patientSearchTimer = setTimeout(async () => {
      try {
        const res = await fetch('/billing/api/pos/patients?search=' + encodeURIComponent(query));
        const data = await res.json();
        if (data.success && data.data && data.data.length > 0) {
          dropdown.innerHTML = data.data.map(p => `
            <div onclick="PosModule.selectPatient(${JSON.stringify(p).replace(/"/g, '&quot;')})" class="p-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0 flex items-center justify-between">
              <div>
                <span class="font-bold text-slate-800">${p.FullName}</span>
                <span class="text-[10px] text-blue-600 font-mono ml-1.5">${p.PatientCode}</span>
                <span class="block text-[10px] text-slate-400">${p.Age} • ${p.Gender}</span>
              </div>
              <span class="text-[10px] px-2 py-0.5 rounded bg-brand-50 text-brand-700 font-bold">Select</span>
            </div>
          `).join('');
          dropdown.classList.remove('hidden');
        } else {
          dropdown.innerHTML = '<div class="p-3 text-center text-slate-400 text-xs italic">No matching patients found</div>';
          dropdown.classList.remove('hidden');
        }
      } catch (err) {
        console.error('Error searching patients:', err);
      }
    }, 200);
  },

  selectPatient: function (p) {
    this.state.patientId = p.PatientID;
    this.state.patientName = p.FullName;
    this.state.patientCode = p.PatientCode;

    const dropdown = document.getElementById('pos-patient-dropdown');
    if (dropdown) dropdown.classList.add('hidden');

    const searchInput = document.getElementById('pos-patient-search');
    if (searchInput) searchInput.value = '';

    const badge = document.getElementById('pos-selected-patient-badge');
    const badgeName = document.getElementById('pos-badge-patient-name');
    const badgeCode = document.getElementById('pos-badge-patient-code');

    if (badge && badgeName && badgeCode) {
      badgeName.innerText = p.FullName;
      badgeCode.innerText = p.PatientCode;
      badge.classList.remove('hidden');
      badge.classList.add('flex');
    }
    lucide.createIcons();
  },

  clearPatient: function () {
    this.state.patientId = null;
    this.state.patientName = '';
    this.state.patientCode = '';

    const badge = document.getElementById('pos-selected-patient-badge');
    if (badge) {
      badge.classList.add('hidden');
      badge.classList.remove('flex');
    }
  },

  setDosagePill: function (dosage) {
    this.state.currentDosageFilter = dosage;
    const select = document.getElementById('pos-dosage-filter');
    if (select) select.value = dosage;

    const pills = document.querySelectorAll('#pos-category-pills button');
    pills.forEach(p => {
      if (p.innerText.includes(dosage) || (dosage === 'All' && p.innerText.includes('All'))) {
        p.className = 'px-2.5 py-1 rounded-lg bg-brand-50 text-brand-700 font-bold border border-brand-200 whitespace-nowrap';
      } else {
        p.className = 'px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-medium hover:bg-slate-200 transition whitespace-nowrap';
      }
    });

    this.filterMedicines();
  },

  filterMedicines: function () {
    const searchInput = document.getElementById('pos-medicine-search');
    const dosageSelect = document.getElementById('pos-dosage-filter');
    const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
    const dosage = (dosageSelect ? dosageSelect.value : 'All');

    const filtered = this.state.medicines.filter(m => {
      const matchSearch = !query ||
        (m.ItemCode && m.ItemCode.toLowerCase().includes(query)) ||
        (m.GenericName && m.GenericName.toLowerCase().includes(query)) ||
        (m.BrandName && m.BrandName.toLowerCase().includes(query));

      const matchDosage = (dosage === 'All') ||
        (m.DosageForm && m.DosageForm.toLowerCase().includes(dosage.toLowerCase()));

      return matchSearch && matchDosage;
    });

    this.renderMedicineGrid(filtered);
  },

  renderMedicineGrid: function (medicines) {
    const grid = document.getElementById('pos-medicine-grid');
    const emptyBox = document.getElementById('pos-medicine-empty');
    if (!grid) return;

    if (!medicines || medicines.length === 0) {
      grid.innerHTML = '';
      if (emptyBox) emptyBox.classList.remove('hidden');
      return;
    }

    if (emptyBox) emptyBox.classList.add('hidden');

    grid.innerHTML = medicines.map(med => {
      const stock = parseInt(med.CurrentStock, 10) || 0;
      const reorder = parseInt(med.ReorderLevel, 10) || 10;
      const price = parseFloat(med.SellingPrice) || 0.00;
      const isOutOfStock = stock <= 0;
      const isLowStock = stock > 0 && stock <= reorder;

      let stockBadge = '';
      if (isOutOfStock) {
        stockBadge = '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-800">Out of Stock</span>';
      } else if (isLowStock) {
        stockBadge = `<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">Low: ${stock} left</span>`;
      } else {
        stockBadge = `<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">${stock} in stock</span>`;
      }

      const brandText = med.BrandName ? `<span class="text-[10px] font-medium text-slate-400 block">${med.BrandName}</span>` : '';
      const strengthText = med.Strength ? ` (${med.Strength})` : '';

      return `
        <div class="hospity-card p-3.5 flex flex-col justify-between border border-slate-200/80 hover:border-brand-300 hover:shadow-md transition-all select-none">
          <div class="space-y-1">
            <div class="flex items-start justify-between gap-1">
              <span class="text-[10px] font-mono text-slate-400 font-semibold">${med.ItemCode}</span>
              ${stockBadge}
            </div>
            <h4 class="text-xs font-black text-slate-900 leading-tight line-clamp-1">${med.GenericName}${strengthText}</h4>
            ${brandText}
            <span class="text-[10px] text-slate-500 font-medium inline-block">${med.DosageForm || 'Medication'}</span>
          </div>

          <div class="pt-3 border-t border-slate-100 mt-2 flex items-center justify-between">
            <span class="text-sm font-black font-mono text-emerald-800">₱${price.toFixed(2)}</span>
            <button 
              type="button" 
              onclick="PosModule.addToCart(${med.InventoryID})" 
              ${isOutOfStock ? 'disabled' : ''}
              class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 ${isOutOfStock ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-brand-50 text-brand-700 hover:bg-brand-600 hover:text-white active:scale-95 cursor-pointer'}"
              title="${isOutOfStock ? 'Item out of stock' : 'Add to cart'}">
              <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add
            </button>
          </div>
        </div>
      `;
    }).join('');

    lucide.createIcons();
  },

  addToCart: function (invId) {
    const med = this.state.medicines.find(m => m.InventoryID === invId);
    if (!med) return;

    const stock = parseInt(med.CurrentStock, 10) || 0;
    if (stock <= 0) {
      alert('This medicine is out of stock.');
      return;
    }

    const existing = this.state.cart.find(c => c.inventory_id === invId);
    if (existing) {
      if (existing.quantity >= stock) {
        alert(`Cannot add more. Available stock for ${med.GenericName} is ${stock}.`);
        return;
      }
      existing.quantity += 1;
    } else {
      this.state.cart.push({
        inventory_id: med.InventoryID,
        item_code: med.ItemCode,
        medicine_name: med.BrandName ? `${med.BrandName} (${med.GenericName})` : med.GenericName,
        dosage_form: med.DosageForm,
        strength: med.Strength,
        unit_price: parseFloat(med.SellingPrice) || 0.00,
        quantity: 1,
        max_stock: stock
      });
    }

    this.renderCart();
  },

  updateQty: function (invId, delta) {
    const item = this.state.cart.find(c => c.inventory_id === invId);
    if (!item) return;

    const newQty = item.quantity + delta;
    if (newQty <= 0) {
      this.removeFromCart(invId);
      return;
    }
    if (newQty > item.max_stock) {
      alert(`Cannot exceed available stock of ${item.max_stock}.`);
      return;
    }

    item.quantity = newQty;
    this.renderCart();
  },

  setQty: function (invId, rawVal) {
    const item = this.state.cart.find(c => c.inventory_id === invId);
    if (!item) return;

    let val = parseInt(rawVal, 10);
    if (isNaN(val) || val <= 0) val = 1;
    if (val > item.max_stock) {
      alert(`Cannot exceed available stock of ${item.max_stock}.`);
      val = item.max_stock;
    }

    item.quantity = val;
    this.renderCart();
  },

  removeFromCart: function (invId) {
    this.state.cart = this.state.cart.filter(c => c.inventory_id !== invId);
    this.renderCart();
  },

  clearCart: function () {
    this.state.cart = [];
    this.renderCart();
  },

  renderCart: function () {
    const cartBox = document.getElementById('pos-cart-items');
    const countEl = document.getElementById('pos-cart-count');
    const subtotalEl = document.getElementById('pos-subtotal');
    const totalEl = document.getElementById('pos-total-amount');

    if (!cartBox) return;

    if (this.state.cart.length === 0) {
      cartBox.innerHTML = `
        <div id="pos-cart-empty" class="text-center py-8">
          <i data-lucide="shopping-bag" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>
          <p class="text-xs font-semibold text-slate-500">Cart is empty</p>
          <p class="text-[10px] text-slate-400 mt-0.5">Click "Add" on any medicine card to begin.</p>
        </div>
      `;
      if (countEl) countEl.innerText = '0 items selected';
      if (subtotalEl) subtotalEl.innerText = '₱0.00';
      if (totalEl) totalEl.innerText = '₱0.00';
      this.calculateChange();
      lucide.createIcons();
      return;
    }

    let subtotal = 0.00;
    let totalItems = 0;

    cartBox.innerHTML = this.state.cart.map(item => {
      const lineSubtotal = item.unit_price * item.quantity;
      subtotal += lineSubtotal;
      totalItems += item.quantity;

      return `
        <div class="p-2.5 bg-white rounded-xl border border-slate-200/80 shadow-xs flex items-center justify-between gap-2 text-xs">
          <div class="flex-1 min-w-0">
            <h5 class="font-bold text-slate-900 leading-tight truncate">${item.medicine_name}</h5>
            <span class="text-[10px] text-slate-400 font-mono">₱${item.unit_price.toFixed(2)} each</span>
          </div>

          <!-- Quantity Controls -->
          <div class="flex items-center gap-1">
            <button type="button" onclick="PosModule.updateQty(${item.inventory_id}, -1)" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center transition">
              -
            </button>
            <input 
              type="number" 
              min="1" 
              max="${item.max_stock}" 
              value="${item.quantity}" 
              onchange="PosModule.setQty(${item.inventory_id}, this.value)" 
              class="w-10 text-center font-mono font-bold text-xs py-0.5 px-1 bg-slate-50 border border-slate-200 rounded">
            <button type="button" onclick="PosModule.updateQty(${item.inventory_id}, 1)" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center transition">
              +
            </button>
          </div>

          <!-- Line Subtotal & Remove -->
          <div class="text-right min-w-[65px]">
            <span class="block font-mono font-bold text-slate-800">₱${lineSubtotal.toFixed(2)}</span>
            <button type="button" onclick="PosModule.removeFromCart(${item.inventory_id})" class="text-[10px] text-rose-500 hover:text-rose-700 transition" title="Remove item">
              Remove
            </button>
          </div>
        </div>
      `;
    }).join('');

    if (countEl) countEl.innerText = `${totalItems} unit${totalItems > 1 ? 's' : ''} (${this.state.cart.length} item${this.state.cart.length > 1 ? 's' : ''})`;
    if (subtotalEl) subtotalEl.innerText = `₱${subtotal.toFixed(2)}`;

    this.updateDiscount(subtotal);
    lucide.createIcons();
  },

  updateDiscount: function (computedSubtotal) {
    const subtotal = (typeof computedSubtotal === 'number') ? computedSubtotal : this.getCartSubtotal();
    const select = document.getElementById('pos-discount-select');
    const discountType = select ? select.value : 'None';
    this.state.discountType = discountType;

    const customWrap = document.getElementById('pos-custom-discount-wrap');
    const customInput = document.getElementById('pos-custom-discount-input');
    const discountAmountEl = document.getElementById('pos-discount-amount');
    const totalEl = document.getElementById('pos-total-amount');

    let discount = 0.00;
    if (discountType === 'Senior Citizen' || discountType === 'PWD') {
      if (customWrap) customWrap.classList.add('hidden');
      discount = subtotal * 0.20;
    } else if (discountType === 'Employee') {
      if (customWrap) customWrap.classList.add('hidden');
      discount = subtotal * 0.10;
    } else if (discountType === 'Custom') {
      if (customWrap) customWrap.classList.remove('hidden');
      discount = parseFloat(customInput ? customInput.value : 0) || 0.00;
    } else {
      if (customWrap) customWrap.classList.add('hidden');
      discount = 0.00;
    }

    discount = Math.min(subtotal, Math.max(0.00, discount));
    this.state.discountAmount = discount;

    const totalDue = Math.max(0.00, subtotal - discount);

    if (discountAmountEl) discountAmountEl.innerText = `-₱${discount.toFixed(2)}`;
    if (totalEl) totalEl.innerText = `₱${totalDue.toFixed(2)}`;

    this.calculateChange(totalDue);
  },

  getCartSubtotal: function () {
    return this.state.cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0.00);
  },

  getCartTotal: function () {
    const subtotal = this.getCartSubtotal();
    return Math.max(0.00, subtotal - (this.state.discountAmount || 0.00));
  },

  setPaymentMethod: function (method) {
    this.state.paymentMethod = method;
    const btns = document.querySelectorAll('.pos-pm-btn');
    btns.forEach(b => {
      b.className = 'pos-pm-btn py-2 px-2 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 text-center';
    });

    const activeBtn = document.getElementById(`pos-pm-${method.toLowerCase()}`);
    if (activeBtn) {
      activeBtn.className = 'pos-pm-btn py-2 px-2 rounded-xl border border-brand-500 bg-brand-50 text-brand-700 text-center font-bold';
    }

    const cashCalc = document.getElementById('pos-cash-calculator');
    const refWrap = document.getElementById('pos-reference-wrap');

    if (method === 'Cash') {
      if (cashCalc) cashCalc.classList.remove('hidden');
      if (refWrap) refWrap.classList.add('hidden');
    } else {
      if (cashCalc) cashCalc.classList.add('hidden');
      if (refWrap) refWrap.classList.remove('hidden');
    }

    this.calculateChange();
  },

  quickCash: function (val) {
    const input = document.getElementById('pos-amount-tendered');
    const totalDue = this.getCartTotal();
    if (!input) return;

    if (val === 'exact') {
      input.value = totalDue.toFixed(2);
    } else {
      const cur = parseFloat(input.value) || totalDue;
      input.value = (cur + val).toFixed(2);
    }

    this.calculateChange();
  },

  calculateChange: function (currentTotal) {
    const totalDue = (typeof currentTotal === 'number') ? currentTotal : this.getCartTotal();
    const tenderedInput = document.getElementById('pos-amount-tendered');
    const changeDueEl = document.getElementById('pos-change-due');
    const alertEl = document.getElementById('pos-insufficient-alert');

    if (this.state.paymentMethod !== 'Cash') {
      if (changeDueEl) changeDueEl.innerText = '₱0.00';
      if (alertEl) alertEl.classList.add('hidden');
      return;
    }

    const tendered = parseFloat(tenderedInput ? tenderedInput.value : 0) || 0.00;
    if (tendered >= totalDue) {
      const change = tendered - totalDue;
      if (changeDueEl) changeDueEl.innerText = `₱${change.toFixed(2)}`;
      if (alertEl) alertEl.classList.add('hidden');
    } else {
      if (changeDueEl) changeDueEl.innerText = '₱0.00';
      if (tendered > 0 && alertEl) {
        alertEl.classList.remove('hidden');
      } else if (alertEl) {
        alertEl.classList.add('hidden');
      }
    }
  },

  checkout: async function () {
    if (this.state.isCheckingOut) return;

    if (this.state.cart.length === 0) {
      alert('Your cart is empty. Please add items to checkout.');
      return;
    }

    if (this.state.customerType === 'patient' && !this.state.patientId) {
      alert('Please select a valid hospital patient, or switch to Walk-in customer.');
      return;
    }

    const totalDue = this.getCartTotal();
    let amountReceived = totalDue;

    if (this.state.paymentMethod === 'Cash') {
      const tenderedInput = document.getElementById('pos-amount-tendered');
      amountReceived = parseFloat(tenderedInput ? tenderedInput.value : 0) || 0.00;
      if (amountReceived < totalDue) {
        alert(`Insufficient cash amount tendered. Total due is ₱${totalDue.toFixed(2)}, received ₱${amountReceived.toFixed(2)}.`);
        return;
      }
    }

    let customerName = 'Walk-in Customer';
    let customerPhone = null;

    if (this.state.customerType === 'patient') {
      customerName = this.state.patientName;
    } else {
      const nameInput = document.getElementById('pos-customer-name');
      const phoneInput = document.getElementById('pos-customer-phone');
      customerName = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : 'Walk-in Customer';
      customerPhone = phoneInput ? phoneInput.value.trim() : null;
    }

    const refInput = document.getElementById('pos-reference-number');
    const referenceNumber = refInput ? refInput.value.trim() : null;
    const notesInput = document.getElementById('pos-notes');
    const notes = notesInput ? notesInput.value.trim() : null;

    this.state.isCheckingOut = true;
    const btn = document.getElementById('pos-complete-sale-btn');
    const btnLabel = document.getElementById('pos-checkout-btn-label');
    const spinner = document.getElementById('pos-checkout-spinner');

    if (btn) btn.disabled = true;
    if (btnLabel) btnLabel.innerText = 'Processing Sale...';
    if (spinner) spinner.classList.remove('hidden');

    const payload = {
      customer_type: this.state.customerType,
      patient_id: this.state.patientId,
      customer_name: customerName,
      customer_phone: customerPhone,
      items: this.state.cart.map(c => ({
        inventory_id: c.inventory_id,
        quantity: c.quantity
      })),
      discount_type: this.state.discountType,
      discount_amount: this.state.discountAmount,
      payment_method: this.state.paymentMethod,
      amount_received: amountReceived,
      reference_number: referenceNumber,
      notes: notes
    };

    try {
      const res = await fetch('/billing/api/pos/checkout', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(payload)
      });

      const result = await res.json();

      if (result.success && result.sale) {
        this.openReceiptModal(result.sale);
        this.clearCart();
        this.loadMedicines();
        this.loadSalesHistory();
      } else {
        alert(result.message || 'Failed to process POS checkout transaction.');
      }
    } catch (err) {
      console.error('Checkout error:', err);
      alert('An error occurred while connecting to the POS server: ' + err.message);
    } finally {
      this.state.isCheckingOut = false;
      if (btn) btn.disabled = false;
      if (btnLabel) btnLabel.innerText = 'Complete Sale & Print Receipt';
      if (spinner) spinner.classList.add('hidden');
    }
  },

  loadSalesHistory: async function () {
    const searchInput = document.getElementById('pos-history-search');
    const dateInput = document.getElementById('pos-history-date');
    const search = searchInput ? searchInput.value.trim() : '';
    const date = dateInput ? dateInput.value : '';

    let url = '/billing/api/pos/sales?';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    if (date) url += 'start_date=' + encodeURIComponent(date) + '&end_date=' + encodeURIComponent(date) + '&';

    try {
      const res = await fetch(url);
      const data = await res.json();
      if (data.success) {
        this.renderSalesHistory(data.data || []);
      }
    } catch (err) {
      console.error('Failed to load POS sales history:', err);
    }
  },

  renderSalesHistory: function (sales) {
    const tbody = document.getElementById('pos-history-tbody');
    if (!tbody) return;

    if (!sales || sales.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-8 text-slate-400 italic">No POS transactions recorded yet.</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = sales.map(s => {
      const dateFormatted = new Date(s.created_at).toLocaleString('en-US', {
        month: 'short', day: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
      });
      const itemCount = (s.items && s.items.length) ? s.items.length : 0;
      const totalAmount = parseFloat(s.TotalAmount) || 0.00;
      const customerBadge = s.CustomerType === 'Patient'
        ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold">Patient</span>'
        : '<span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">Walk-in</span>';

      return `
        <tr class="hover:bg-slate-50 transition">
          <td class="py-2.5 px-3 font-mono font-bold text-slate-900">${s.ReceiptNumber}</td>
          <td class="py-2.5 px-3 text-slate-500">${dateFormatted}</td>
          <td class="py-2.5 px-3">
            <span class="font-bold text-slate-800 block">${s.CustomerName}</span>
            ${customerBadge}
          </td>
          <td class="py-2.5 px-3 text-slate-700 font-medium">${s.PaymentMethod}</td>
          <td class="py-2.5 px-3 text-right font-mono">${itemCount} item${itemCount > 1 ? 's' : ''}</td>
          <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">₱${totalAmount.toFixed(2)}</td>
          <td class="py-2.5 px-3 text-center text-slate-600">${s.CashierName || 'Cashier'}</td>
          <td class="py-2.5 px-3 text-right">
            <a href="/print/pos-receipt/${s.SaleID}" target="_blank" class="px-2.5 py-1 bg-white border border-slate-200 hover:border-brand-500 hover:text-brand-600 text-slate-600 font-bold rounded-lg text-xs transition inline-flex items-center gap-1 shadow-xs">
              <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
            </a>
          </td>
        </tr>
      `;
    }).join('');

    lucide.createIcons();
  },

  openReceiptModal: function (sale) {
    const modal = document.getElementById('pos-receipt-modal');
    const receiptNo = document.getElementById('pos-modal-receipt-no');
    const body = document.getElementById('pos-modal-receipt-body');

    this.state.currentReceiptUrl = `/print/pos-receipt/${sale.SaleID}`;

    if (receiptNo) receiptNo.innerText = sale.ReceiptNumber;

    if (body) {
      const itemsList = (sale.items || []).map(i => `
        <div class="flex justify-between items-center py-1 border-b border-slate-100 last:border-0 text-xs">
          <div>
            <span class="font-bold text-slate-900 block">${i.MedicineName}</span>
            <span class="text-[10px] text-slate-400 font-mono">${i.Quantity}x @ ₱${parseFloat(i.UnitPrice).toFixed(2)}</span>
          </div>
          <span class="font-mono font-bold text-slate-900">₱${parseFloat(i.SubTotal).toFixed(2)}</span>
        </div>
      `).join('');

      body.innerHTML = `
        <div class="space-y-4">
          <div class="text-center border-b border-slate-200 pb-3">
            <h4 class="font-black text-sm text-slate-900 uppercase">Tupi Municipal Hospital</h4>
            <p class="text-[10px] text-slate-500">Retail Pharmacy Point-of-Sale (POS)</p>
            <p class="text-[10px] text-slate-400 font-mono mt-1">${new Date().toLocaleString()}</p>
          </div>

          <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-2.5 rounded-xl border border-slate-200">
            <div>
              <span class="text-slate-400 block text-[9px] uppercase font-bold">Customer:</span>
              <span class="font-bold text-slate-800">${sale.CustomerName} (${sale.CustomerType})</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[9px] uppercase font-bold">Cashier:</span>
              <span class="font-bold text-slate-800">${sale.CashierName || 'Official Cashier'}</span>
            </div>
          </div>

          <div class="border border-slate-200 rounded-xl p-3 bg-white space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Purchased Medicines:</span>
            ${itemsList}
          </div>

          <div class="bg-emerald-50/60 border border-emerald-200/80 rounded-xl p-3 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-600">
              <span>Gross Subtotal:</span>
              <span class="font-mono font-bold text-slate-800">₱${parseFloat(sale.SubTotal).toFixed(2)}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Discount (${sale.DiscountType}):</span>
              <span class="font-mono font-bold text-emerald-700">- ₱${parseFloat(sale.DiscountAmount).toFixed(2)}</span>
            </div>
            <div class="flex justify-between font-black text-sm text-slate-900 pt-1 border-t border-emerald-200">
              <span>Total Paid:</span>
              <span class="font-mono text-emerald-900">₱${parseFloat(sale.TotalAmount).toFixed(2)}</span>
            </div>
            <div class="flex justify-between text-slate-600 pt-1 text-[11px]">
              <span>Amount Received (${sale.PaymentMethod}):</span>
              <span class="font-mono">₱${parseFloat(sale.AmountReceived).toFixed(2)}</span>
            </div>
            <div class="flex justify-between text-slate-600 text-[11px]">
              <span>Change Due:</span>
              <span class="font-mono font-bold text-blue-900">₱${parseFloat(sale.ChangeAmount).toFixed(2)}</span>
            </div>
          </div>
        </div>
      `;
    }

    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  },

  closeReceiptModal: function () {
    const modal = document.getElementById('pos-receipt-modal');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
  },

  printCurrentReceipt: function () {
    if (this.state.currentReceiptUrl) {
      window.open(this.state.currentReceiptUrl, '_blank');
    }
  }
};
