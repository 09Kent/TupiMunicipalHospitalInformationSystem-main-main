/**
 * Tupi Municipal Hospital Information Management System
 * Hospital Chief / Medical Director Dashboard (Role 2)
 * Client-Side Application Controller
 */

// ===== GLOBAL STATE =====
let currentView = 'dashboard';
let currentReportCode = '';
let chartInstances = {};
let staffFilters = {};
let docFilters = {};

// Helper: Resolve dynamic API endpoints seamlessly
function getApiUrl(path) {
  const clean = path.replace(/^\/+/, '');
  if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) {
    return window.BASE_URL.replace(/\/+$/, '') + '/director/api/' + clean;
  }
  const basePath = window.location.pathname.replace(/\/director(\/.*)?$/, '');
  const prefix = (basePath ? basePath : '') + '/director/api/';
  return prefix + clean;
}

// ===== SIDEBAR =====
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const mw = document.getElementById('mainWrap');
  sb.classList.toggle('collapsed');
  mw.classList.toggle('expanded');
  localStorage.setItem('sidebar_collapsed', sb.classList.contains('collapsed') ? '1' : '0');
}

function openMobileSidebar() {
  document.getElementById('sidebar').classList.add('mobile-open');
}

function closeMobileSidebar() {
  document.getElementById('sidebar').classList.remove('mobile-open');
}

document.getElementById('sidebarToggle')?.addEventListener('click', toggleSidebar);

// Restore sidebar state
if (localStorage.getItem('sidebar_collapsed') === '1') {
  document.getElementById('sidebar')?.classList.add('collapsed');
  document.getElementById('mainWrap')?.classList.add('expanded');
}

// ===== USER POPOVER =====
function toggleUserPopover() {
  const pop = document.getElementById('userPopover');
  pop.classList.toggle('show');
}

document.addEventListener('click', (e) => {
  const pop = document.getElementById('userPopover');
  const trig = document.getElementById('userTrigger');
  if (pop && !pop.contains(e.target) && !trig.contains(e.target)) {
    pop.classList.remove('show');
  }
  // Also close export menu
  const em = document.getElementById('exportMenu');
  if (em && !em.parentElement.contains(e.target)) {
    em.classList.remove('show');
  }
  // Close notif panel
  const np = document.getElementById('notifPanel');
  const nb = document.getElementById('notifBellBtn');
  if (np && !np.contains(e.target) && nb && !nb.contains(e.target)) {
    np.classList.remove('show');
  }
});

// ===== VIEW SWITCHING =====
function switchView(viewName, btn) {
  document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));

  const target = document.getElementById('view-' + viewName);
  if (target) {
    target.classList.add('active');
    // Re-trigger animation
    target.style.animation = 'none';
    target.offsetHeight; // reflow
    target.style.animation = '';
  }

  if (btn) btn.classList.add('active');
  currentView = viewName;

  // Close mobile sidebar
  closeMobileSidebar();

  // Load data for specific views
  if (viewName === 'dashboard') loadDashboard();
  else if (viewName === 'reports') loadReports();
  else if (viewName === 'staff-activity') loadStaffActivity();
  else if (viewName === 'department-performance') loadDeptPerformance();
  else if (viewName === 'doctor-stats') loadDoctorStats();
  else if (viewName === 'notifications') loadNotificationsPage();
  else if (viewName === 'settings') loadAuditTrail();
}

// ===== NOTIFICATION PANEL =====
function toggleNotifPanel() {
  document.getElementById('notifPanel').classList.toggle('show');
  loadNotifications();
}

function markAllRead() {
  fetch(getApiUrl('notifications.php?action=mark_read'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({})
  })
  .then(() => {
    loadNotifications();
    const dot = document.getElementById('notifDot');
    if (dot) dot.style.display = 'none';
    const badge = document.getElementById('sidebarNotifBadge');
    if (badge) badge.style.display = 'none';
    showToast('All notifications marked as read', 'success');
  })
  .catch(err => console.error('Mark read error:', err));
}

function loadNotifications() {
  fetch(getApiUrl('notifications.php?action=list'))
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success') return;
      const list = document.getElementById('notifList');
      if (!list) return;

      const catColors = {
        'Compliance': { bg: 'var(--amber-50)', color: 'var(--amber-600)' },
        'Reports': { bg: 'var(--primary-50)', color: 'var(--primary-600)' },
        'Performance': { bg: 'var(--emerald-50)', color: 'var(--emerald-600)' },
        'Staff': { bg: 'var(--indigo-50)', color: 'var(--indigo-600)' },
        'Pharmacy': { bg: 'var(--rose-50)', color: 'var(--rose-600)' },
        'Doctors': { bg: 'var(--violet-50)', color: 'var(--violet-600)' }
      };

      if (!d.data || d.data.length === 0) {
        list.innerHTML = '<div style="padding:1.5rem;text-align:center;color:var(--slate-400);font-size:.82rem;">No unread notifications</div>';
        return;
      }

      list.innerHTML = d.data.map(n => {
        const cc = catColors[n.category] || catColors['Reports'];
        return `<div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
          <div class="notif-dot-icon" style="background:${cc.bg};color:${cc.color};">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          </div>
          <div class="notif-content">
            <div class="notif-title">${escHtml(n.title)}</div>
            <div class="notif-desc">${escHtml(n.description)}</div>
            <div class="notif-time">${n.relative_time || 'Just now'}</div>
          </div>
        </div>`;
      }).join('');

      // Update badge
      if (d.unread_count > 0) {
        const dot = document.getElementById('notifDot');
        if (dot) dot.style.display = 'block';
        const badge = document.getElementById('sidebarNotifBadge');
        if (badge) { badge.textContent = d.unread_count; badge.style.display = ''; }
      }
    })
    .catch(err => console.error('Notifications load error:', err));
}

// ===== DASHBOARD =====
function loadDashboard() {
  fetch(getApiUrl('dashboard-stats.php'))
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success') return;
      const data = d.data;

      // Summary cards
      const sc = data.summary_cards;
      if (sc) {
        setText('cardCensus', sc.today_patient_census?.value || '--');
        setText('cardPerf', sc.monthly_performance?.value || '--');
        setText('cardRevenue', sc.monthly_revenue?.value || '--');
        setText('cardCompliance', sc.doh_compliance?.value || '--');
      }

      // KPIs
      if (data.kpis) renderKPIs(data.kpis);

      // Activity Feed
      if (data.recent_activity) renderActivityFeed(data.recent_activity);

      // Charts
      if (data.charts) {
        renderCensusTrendChart(data.charts.census_trend);
        renderRevenueChart(data.charts.revenue_breakdown);
      }
    })
    .catch(err => console.error('Dashboard load error:', err));
}

function renderKPIs(kpis) {
  const el = document.getElementById('kpiList');
  if (!el || !Array.isArray(kpis)) return;
  el.innerHTML = kpis.map(k => `
    <div class="kpi-item">
      <div class="kpi-header">
        <span class="kpi-name">${escHtml(k.name)}</span>
        <span class="kpi-val">${k.value}%</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill ${k.color}" style="width:0%" data-width="${k.value}"></div>
      </div>
    </div>
  `).join('');

  // Animate progress bars
  setTimeout(() => {
    el.querySelectorAll('.progress-fill').forEach(bar => {
      bar.style.width = (bar.dataset.width || 0) + '%';
    });
  }, 100);
}

function renderActivityFeed(items) {
  const el = document.getElementById('activityFeed');
  if (!el || !Array.isArray(items)) return;
  el.innerHTML = items.map(a => `
    <div class="feed-item">
      <div class="feed-dot completed">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="feed-body">
        <div class="feed-title">${escHtml(a.title)}</div>
        <div class="feed-desc">${escHtml(a.desc)}</div>
        <div class="feed-time">${escHtml(a.time)}</div>
      </div>
    </div>
  `).join('');
}

// ===== CHARTS =====
function destroyChart(id) {
  if (chartInstances[id]) { 
    try { chartInstances[id].destroy(); } catch (e) {}
    delete chartInstances[id]; 
  }
}

function renderCensusTrendChart(data) {
  destroyChart('censusTrendChart');
  const ctx = document.getElementById('censusTrendChart');
  if (!ctx || !data) return;
  if (typeof Chart === 'undefined') return;

  let labels = [];
  let inpatients = [];
  let outpatients = [];
  let totals = [];

  if (Array.isArray(data.labels) && Array.isArray(data.inpatients)) {
    labels = data.labels;
    inpatients = data.inpatients;
    outpatients = data.outpatients || [];
    totals = inpatients.map((v, i) => v + (outpatients[i] || 0));
  } else if (Array.isArray(data)) {
    labels = data.map(d => {
      const dt = new Date(d.census_date);
      return dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
    });
    inpatients = data.map(d => d.inpatients || 0);
    outpatients = data.map(d => d.outpatients || 0);
    totals = data.map(d => d.total_patients || ((d.inpatients || 0) + (d.outpatients || 0)));
  }

  try {
    chartInstances['censusTrendChart'] = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Total Patients', data: totals, borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,0.08)', fill: true, tension: 0.35, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#0284c7' },
          { label: 'Inpatients', data: inpatients, borderColor: '#10b981', backgroundColor: 'transparent', tension: 0.35, borderWidth: 2, pointRadius: 2, borderDash: [4,4] },
          { label: 'Outpatients', data: outpatients, borderColor: '#6366f1', backgroundColor: 'transparent', tension: 0.35, borderWidth: 2, pointRadius: 2, borderDash: [4,4] }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 11, family: 'Inter' } } } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#94a3b8' } },
          y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11, family: 'Inter' }, color: '#94a3b8' } }
        }
      }
    });
  } catch (e) {
    console.warn('Census trend chart error:', e);
  }
}

function renderRevenueChart(data) {
  destroyChart('revenueChart');
  const ctx = document.getElementById('revenueChart');
  if (!ctx || !data) return;
  if (typeof Chart === 'undefined') return;

  let labels = [];
  let values = [];

  if (Array.isArray(data.labels) && Array.isArray(data.data)) {
    labels = data.labels;
    values = data.data;
  } else if (typeof data === 'object') {
    labels = Object.keys(data);
    values = Object.values(data);
  }

  const colors = ['#0284c7', '#6366f1', '#10b981', '#f59e0b'];

  try {
    chartInstances['revenueChart'] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data: values, backgroundColor: colors.slice(0, Math.max(1, labels.length)), borderWidth: 0, hoverOffset: 8 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 11, family: 'Inter' } } },
          tooltip: { callbacks: { label: (c) => ` ₱${Number(c.raw).toLocaleString()}` } }
        }
      }
    });
  } catch (e) {
    console.warn('Revenue chart error:', e);
  }
}

// ===== OPERATIONAL REPORTS =====
function loadReports() {
  const search = document.getElementById('reportSearch')?.value || '';
  const cat = document.getElementById('reportCategoryFilter')?.value || 'all';
  const grid = document.getElementById('reportsGrid');
  const tbody = document.getElementById('reportsTableBody');

  if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--slate-500);">Loading operational reports...</td></tr>';

  fetch(`${getApiUrl('reports.php')}?action=list&search=${encodeURIComponent(search)}&category=${encodeURIComponent(cat)}`)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success' || !d.data || d.data.length === 0) {
        if (grid) grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--slate-500);">No operational reports found.</div>';
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--slate-500);">No archived reports available.</td></tr>';
        return;
      }

      // Grid cards (core reports only)
      const core = d.data.filter(r => !r.report_code.startsWith('RPT-ARCH'));
      if (grid) {
        grid.innerHTML = core.map(r => {
          const metrics = r.summary_metrics || {};
          const mainMetric = Object.values(metrics)[0] || 'Finalized';
          const statusCls = r.status === 'Finalized' ? 'finalized' : 'archived';
          return `<div class="rpt-card" onclick="openReport('${r.report_code}')">
            <div class="rpt-top">
              <div class="rpt-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg></div>
              <div class="rpt-meta">
                <div class="rpt-name">${escHtml(r.report_name)}</div>
                <div class="rpt-period">${escHtml(r.period_label)}</div>
              </div>
            </div>
            <div class="rpt-body">
              <div><span class="rpt-stat-lbl">Category</span><br><span class="rpt-stat-val">${escHtml(r.category)}</span></div>
              <div style="text-align:right;"><span class="rpt-stat-lbl">Key Metric</span><br><span class="rpt-stat-val">${escHtml(String(mainMetric))}</span></div>
            </div>
            <div class="rpt-foot">
              <span class="badge ${statusCls}">${escHtml(r.status)}</span>
              <button class="btn btn-sm btn-outline" onclick="event.stopPropagation();openReport('${r.report_code}')">View Report</button>
            </div>
          </div>`;
        }).join('');
      }

      // Archive table (all reports)
      if (tbody) {
        tbody.innerHTML = d.data.map(r => {
          const statusCls = r.status === 'Finalized' ? 'finalized' : 'archived';
          return `<tr>
            <td style="font-weight:600;font-size:.82rem;">${escHtml(r.report_code)}</td>
            <td>${escHtml(r.report_name)}</td>
            <td>${escHtml(r.category)}</td>
            <td>${escHtml(r.period_label)}</td>
            <td><span class="badge ${statusCls}">${escHtml(r.status)}</span></td>
            <td><button class="btn btn-sm btn-outline" onclick="openReport('${r.report_code}')">View</button></td>
          </tr>`;
        }).join('');
      }
    })
    .catch(err => {
      console.error('Reports load error:', err);
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--rose-600);">Unable to load operational reports. <button class="btn btn-sm btn-outline" onclick="loadReports()" style="margin-left:.5rem;">Retry</button></td></tr>';
    });
}

function filterReports() { loadReports(); }

// ===== REPORT VIEWER MODAL =====
function openReport(code) {
  currentReportCode = code;
  const modal = document.getElementById('reportModal');
  if (modal) modal.classList.add('show');
  document.getElementById('reportModalTitle').textContent = 'Loading...';
  document.getElementById('reportModalBody').innerHTML = '<div style="text-align:center;padding:3rem;color:var(--slate-400);">Loading report...</div>';

  fetch(`${getApiUrl('reports.php')}?action=get&code=${encodeURIComponent(code)}`)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success') throw new Error('Report failed to load');
      const rpt = d.data;
      document.getElementById('reportModalTitle').textContent = rpt.report_name;
      renderReportContent(rpt);
    })
    .catch(err => {
      document.getElementById('reportModalBody').innerHTML = `<div style="text-align:center;padding:3rem;color:var(--rose-600);">Failed to load report. Please retry.</div>`;
    });
}

function renderReportContent(rpt) {
  const body = document.getElementById('reportModalBody');
  const metrics = rpt.summary_metrics || {};
  const detail = rpt.detailed_payload || {};

  let html = `
    <div class="report-formal-hdr">
      <div>
        <div class="hospital-name">Tupi Municipal Hospital</div>
        <div class="report-title-formal">${escHtml(rpt.report_name)}</div>
        <div style="font-size:.82rem;color:var(--text-muted);margin-top:.35rem;">Hospital Operations Report</div>
      </div>
      <div class="report-meta">
        <div><strong>Period:</strong> ${escHtml(rpt.period_label)}</div>
        <div><strong>Generated:</strong> ${new Date().toLocaleDateString('en-PH', { year:'numeric',month:'long',day:'numeric' })}</div>
        <div><strong>Prepared For:</strong> Dr. Maria Santos</div>
        <div style="margin-top:.25rem;"><span class="badge finalized">${escHtml(rpt.status)}</span></div>
      </div>
    </div>`;

  // Summary boxes
  const metricEntries = Object.entries(metrics).slice(0, 8);
  if (metricEntries.length > 0) {
    html += '<div class="report-boxes">';
    metricEntries.forEach(([k, v]) => {
      const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      html += `<div class="rpt-box"><div class="lbl">${escHtml(label)}</div><div class="val">${escHtml(String(v))}</div></div>`;
    });
    html += '</div>';
  }

  // Summary & Findings
  if (detail.summary) {
    html += `<div class="card" style="margin-bottom:1.25rem;"><div style="padding:1.25rem;"><h4 style="margin-bottom:.5rem;font-size:.95rem;">Executive Summary</h4><p style="font-size:.88rem;line-height:1.6;">${escHtml(detail.summary)}</p></div></div>`;
  }
  if (detail.findings) {
    html += `<div class="card" style="margin-bottom:1.25rem;"><div style="padding:1.25rem;"><h4 style="margin-bottom:.5rem;font-size:.95rem;">Key Findings</h4><p style="font-size:.88rem;line-height:1.6;">${escHtml(detail.findings)}</p></div></div>`;
  }

  // Live tables
  if (rpt.census_history && rpt.census_history.length > 0) {
    html += renderLiveTable('Patient Census History', rpt.census_history, ['census_date','total_patients','outpatients','emergency']);
  }
  if (rpt.laboratory_tests && rpt.laboratory_tests.length > 0) {
    html += renderLiveTable('Laboratory Diagnostic Breakdown', rpt.laboratory_tests, ['test_name','category','requests_count','completed_count','pending_count','utilization_rate']);
  }
  if (rpt.pharmacy_inventory && rpt.pharmacy_inventory.length > 0) {
    html += renderLivePharmacyTable(rpt.pharmacy_inventory);
  }

  body.innerHTML = html;
}

function renderLiveTable(title, rows, cols) {
  let html = `<div class="card" style="margin-bottom:1.25rem;"><div style="padding:1.25rem;">
    <h4 style="margin-bottom:.75rem;font-size:.95rem;">${escHtml(title)}</h4>
    <div class="tbl-wrap"><table class="tbl"><thead><tr>`;
  cols.forEach(c => { html += `<th>${escHtml(c.replace(/_/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase()))}</th>`; });
  html += `</tr></thead><tbody>`;
  rows.forEach(r => {
    html += '<tr>';
    cols.forEach(c => { html += `<td>${escHtml(String(r[c] ?? '-'))}</td>`; });
    html += '</tr>';
  });
  html += `</tbody></table></div></div></div>`;
  return html;
}

function renderLivePharmacyTable(items) {
  let html = `<div class="card" style="margin-bottom:1.25rem;"><div style="padding:1.25rem;">
    <h4 style="margin-bottom:.75rem;font-size:.95rem;">Pharmacy Medication Stock Levels</h4>
    <div class="tbl-wrap"><table class="tbl"><thead><tr>
      <th>Medicine</th><th>Generic / Category</th><th>Current Stock</th><th>Min Threshold</th><th>Status</th><th>Expiry Date</th>
    </tr></thead><tbody>`;
  items.forEach(m => {
    const cls = m.status === 'Active' || m.status === 'Normal' ? 'completed' : 'attention';
    html += `<tr>
      <td style="font-weight:600;">${escHtml(m.medicine_name)}</td>
      <td>${escHtml(m.generic_name || '-')}</td>
      <td>${m.current_stock}</td>
      <td>${m.min_level}</td>
      <td><span class="badge ${cls}">${escHtml(m.status)}</span></td>
      <td>${escHtml(m.expiry_date || '-')}</td>
    </tr>`;
  });
  html += `</tbody></table></div></div></div>`;
  return html;
}

// ===== EXPORT & PRINT =====
function toggleExportMenu() {
  document.getElementById('exportMenu')?.classList.toggle('show');
}

function exportReport(format) {
  document.getElementById('exportMenu')?.classList.remove('show');
  if (!currentReportCode) currentReportCode = 'RPT-CENSUS-01';
  window.open(`${getApiUrl('export-report.php')}?code=${encodeURIComponent(currentReportCode)}&format=${format}`, '_blank');
  showToast(`Report exported as ${format.toUpperCase()} successfully.`, 'success');
}

function printReport() {
  window.print();
}

// ===== STAFF ACTIVITY =====
function loadStaffActivity() {
  const search = document.getElementById('staffSearch')?.value || '';
  const dept = document.getElementById('staffDeptFilter')?.value || 'all';
  const role = document.getElementById('staffRoleFilter')?.value || 'all';
  const tbody = document.getElementById('staffActivityBody');

  if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--slate-500);">Loading staff activity logs...</td></tr>';

  fetch(`${getApiUrl('staff-activity.php')}?search=${encodeURIComponent(search)}&department=${encodeURIComponent(dept)}&role=${encodeURIComponent(role)}`)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success' || !d.data || d.data.length === 0) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--slate-500);">No activity logs recorded.</td></tr>';
        return;
      }

      if (tbody) {
        tbody.innerHTML = d.data.map(log => `
          <tr>
            <td>${escHtml(log.formatted_date || '')}</td>
            <td>${escHtml(log.formatted_time || '')}</td>
            <td style="font-weight:600;">${escHtml(log.staff_name || '')}</td>
            <td>${escHtml(log.role_title || '')}</td>
            <td>${escHtml(log.department_name || '')}</td>
            <td style="max-width:280px;">${escHtml(log.activity_description || '')}</td>
            <td><span class="badge completed">${escHtml(log.status || 'Completed')}</span></td>
            <td><button class="btn btn-sm btn-secondary" onclick='showStaffDetail(${JSON.stringify(log)})'>Details</button></td>
          </tr>
        `).join('');
      }
    })
    .catch(err => {
      console.error('Staff activity load error:', err);
      if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--rose-600);">Unable to load activity logs. <button class="btn btn-sm btn-outline" onclick="loadStaffActivity()" style="margin-left:.5rem;">Retry</button></td></tr>';
    });
}

function filterStaffActivity() { loadStaffActivity(); }

function showStaffDetail(log) {
  const body = document.getElementById('staffDetailBody');
  if (!body) return;
  body.innerHTML = `
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
      <div class="avatar" style="width:48px;height:48px;">${escHtml(log.staff_name.split(' ').map(n=>n[0]).join('').slice(0,2))}</div>
      <div>
        <div style="font-size:1.05rem;font-weight:700;color:var(--slate-900);">${escHtml(log.staff_name)}</div>
        <div style="font-size:.85rem;color:var(--primary-700);">${escHtml(log.role_title)}</div>
        <div style="font-size:.78rem;color:var(--slate-500);">${escHtml(log.department_name)}</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
      <div style="background:var(--slate-50);padding:.85rem;border-radius:var(--radius-md);">
        <div style="font-size:.72rem;font-weight:600;color:var(--slate-500);text-transform:uppercase;">Date</div>
        <div style="font-size:.88rem;font-weight:600;color:var(--slate-900);">${escHtml(log.formatted_date)}</div>
      </div>
      <div style="background:var(--slate-50);padding:.85rem;border-radius:var(--radius-md);">
        <div style="font-size:.72rem;font-weight:600;color:var(--slate-500);text-transform:uppercase;">Time</div>
        <div style="font-size:.88rem;font-weight:600;color:var(--slate-900);">${escHtml(log.formatted_time)}</div>
      </div>
    </div>
    <div style="margin-bottom:1rem;">
      <div style="font-size:.78rem;font-weight:600;color:var(--slate-500);text-transform:uppercase;margin-bottom:.35rem;">Activity Type</div>
      <div style="font-size:.88rem;color:var(--slate-800);">${escHtml(log.activity_type)}</div>
    </div>
    <div style="margin-bottom:1rem;">
      <div style="font-size:.78rem;font-weight:600;color:var(--slate-500);text-transform:uppercase;margin-bottom:.35rem;">Activity Description</div>
      <div style="font-size:.88rem;color:var(--slate-800);line-height:1.6;">${escHtml(log.activity_description)}</div>
    </div>
    <div style="display:flex;gap:1rem;">
      <div><span class="badge completed">${escHtml(log.status)}</span></div>
    </div>
  `;
  document.getElementById('staffDetailModal')?.classList.add('show');
}

// ===== DEPARTMENT PERFORMANCE =====
function loadDeptPerformance() {
  const period = document.getElementById('dateRangeFilter')?.value || 'month';
  const loadingEl = document.getElementById('deptPerfChartLoading');
  const errorEl = document.getElementById('deptPerfChartError');
  const emptyEl = document.getElementById('deptPerfChartEmpty');
  const fallbackTable = document.getElementById('deptPerfTableFallback');
  const statusEl = document.getElementById('deptChartStatus');

  if (loadingEl) loadingEl.style.display = 'block';
  if (errorEl) errorEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  if (fallbackTable) fallbackTable.style.display = 'none';
  if (statusEl) statusEl.innerHTML = '<span style="color:var(--slate-400)">Synchronizing...</span>';

  fetch(`${getApiUrl('department-performance.php')}?period=${encodeURIComponent(period)}`)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP error ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (loadingEl) loadingEl.style.display = 'none';
      if (d.status !== 'success' || !d.data || d.data.length === 0) {
        if (emptyEl) emptyEl.style.display = 'block';
        if (statusEl) statusEl.innerHTML = '<span style="color:var(--amber-600)">No data for period</span>';
        return;
      }

      if (statusEl) statusEl.innerHTML = `<span style="color:var(--emerald-600)">● Live Database (${d.data.length} depts)</span>`;

      // Summary cards
      const s = d.summary;
      const sumCards = document.getElementById('deptSummaryCards');
      if (sumCards && s) {
        sumCards.innerHTML = `
          <div class="card summary-card c-primary"><div class="sc-top"><span class="sc-title">TOTAL DEPARTMENTS</span></div><div class="sc-value">${s.total_departments}</div><div class="sc-bottom"><span class="sc-desc">${s.total_staff} total assigned staff</span></div></div>
          <div class="card summary-card c-emerald"><div class="sc-top"><span class="sc-title">HOSPITAL EFFICIENCY</span></div><div class="sc-value">${s.hospital_efficiency_score}%</div><div class="sc-bottom"><span class="sc-desc">Average operational throughput</span></div></div>
          <div class="card summary-card c-indigo"><div class="sc-top"><span class="sc-title">TASK COMPLETION</span></div><div class="sc-value">${s.task_completion_rate}%</div><div class="sc-bottom"><span class="sc-desc">${s.total_completed_tasks} completed / ${s.total_pending_tasks} pending</span></div></div>
        `;
      }

      // Dept chart
      renderDeptChart(d.data);

      // Render accessible fallback table
      if (fallbackTable) {
        fallbackTable.innerHTML = `
          <div style="margin-top:1rem;overflow-x:auto;">
            <table class="tbl" style="width:100%;font-size:.82rem;">
              <thead>
                <tr>
                  <th>Department</th>
                  <th>Supervisor / Head</th>
                  <th>Staff</th>
                  <th>Completed Tasks</th>
                  <th>Pending Tasks</th>
                  <th>Efficiency Score</th>
                </tr>
              </thead>
              <tbody>
                ${d.data.map(dept => `
                  <tr>
                    <td style="font-weight:600;">${escHtml(dept.name)}</td>
                    <td>${escHtml(dept.head)}</td>
                    <td>${dept.total_staff}</td>
                    <td style="color:var(--emerald-600);font-weight:600;">${dept.completed_tasks}</td>
                    <td style="color:var(--amber-600);font-weight:600;">${dept.pending_tasks}</td>
                    <td><strong>${dept.performance_score}%</strong></td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
        fallbackTable.style.display = 'block';
      }

      // Dept grid cards
      const grid = document.getElementById('deptGrid');
      if (grid) {
        grid.innerHTML = d.data.map(dept => {
          const scoreColor = dept.performance_score >= 95 ? 'emerald' : dept.performance_score >= 90 ? 'primary' : 'amber';
          return `<div class="card dept-card">
            <div class="dept-name">${escHtml(dept.name)}</div>
            <div class="dept-head">${escHtml(dept.head)}</div>
            <div class="dept-stats">
              <div class="dept-stat-item"><div class="dept-stat-val">${dept.total_staff}</div><div class="dept-stat-lbl">Staff</div></div>
              <div class="dept-stat-item"><div class="dept-stat-val">${dept.active_rate}%</div><div class="dept-stat-lbl">Activity Rate</div></div>
              <div class="dept-stat-item"><div class="dept-stat-val">${dept.completed_tasks}</div><div class="dept-stat-lbl">Completed</div></div>
              <div class="dept-stat-item"><div class="dept-stat-val">${dept.pending_tasks}</div><div class="dept-stat-lbl">Pending</div></div>
            </div>
            <div class="kpi-item">
              <div class="kpi-header"><span class="kpi-name">Performance</span><span class="kpi-val">${dept.performance_score}%</span></div>
              <div class="progress-track"><div class="progress-fill ${scoreColor}" style="width:${dept.performance_score}%"></div></div>
            </div>
          </div>`;
        }).join('');
      }
    })
    .catch(err => {
      console.error('Department performance load error:', err);
      if (loadingEl) loadingEl.style.display = 'none';
      if (errorEl) errorEl.style.display = 'block';
      if (statusEl) statusEl.innerHTML = '<span style="color:var(--rose-600)">Error loading data</span>';
    });
}

function renderDeptChart(departments) {
  destroyChart('deptPerfChart');
  const ctx = document.getElementById('deptPerfChart');
  if (!ctx) return;
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js not loaded, fallback table displays');
    return;
  }

  try {
    chartInstances['deptPerfChart'] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: departments.map(d => d.name),
        datasets: [
          { 
            label: 'Efficiency Score (%)', 
            data: departments.map(d => d.performance_score), 
            backgroundColor: departments.map(d => d.performance_score >= 95 ? '#10b981' : d.performance_score >= 90 ? '#0284c7' : '#f59e0b'), 
            borderRadius: 6 
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { 
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context) {
                const dept = departments[context.dataIndex];
                return `Efficiency: ${dept.performance_score}% (${dept.completed_tasks} completed, ${dept.pending_tasks} pending)`;
              }
            }
          }
        },
        scales: { 
          x: { 
            suggestedMin: 0, 
            max: 100, 
            grid: { color: '#f1f5f9' }, 
            ticks: { callback: v => v + '%', font: { size: 11, family: 'Inter' } } 
          }, 
          y: { 
            grid: { display: false }, 
            ticks: { font: { size: 11, family: 'Inter' } } 
          } 
        }
      }
    });
  } catch (e) {
    console.warn('Dept chart render error:', e);
  }
}

// ===== DOCTOR STATISTICS =====
function loadDoctorStats() {
  const search = document.getElementById('docSearch')?.value || '';
  const dept = document.getElementById('docDeptFilter')?.value || 'all';
  const tbody = document.getElementById('docTableBody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--slate-500);">Loading doctor statistics...</td></tr>';

  fetch(`${getApiUrl('doctor-stats.php')}?search=${encodeURIComponent(search)}&department=${encodeURIComponent(dept)}`)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success' || !d.data) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--slate-500);">No doctor records found.</td></tr>';
        return;
      }
      const s = d.summary;

      // Populate dept filter
      const depts = [...new Set(d.data.map(doc => doc.department_name))];
      populateSelect('docDeptFilter', depts, dept);

      // Summary cards
      const cards = document.getElementById('docSummaryCards');
      if (cards && s) {
        cards.innerHTML = `
          <div class="card summary-card c-primary"><div class="sc-top"><span class="sc-title">TOTAL DOCTORS</span></div><div class="sc-value">${s.total_doctors}</div><div class="sc-bottom"><span class="sc-desc">Active physicians</span></div></div>
          <div class="card summary-card c-emerald"><div class="sc-top"><span class="sc-title">TOTAL CONSULTATIONS</span></div><div class="sc-value">${Number(s.total_consultations).toLocaleString()}</div><div class="sc-bottom"><span class="sc-desc">This month</span></div></div>
          <div class="card summary-card c-indigo"><div class="sc-top"><span class="sc-title">COMPLETION RATE</span></div><div class="sc-value">${s.completion_rate}%</div><div class="sc-bottom"><span class="sc-desc">${s.cancelled_consultations} cancelled</span></div></div>
          <div class="card summary-card c-amber"><div class="sc-top"><span class="sc-title">AVG DAILY / DOCTOR</span></div><div class="sc-value">${s.avg_daily_per_doctor}</div><div class="sc-bottom"><span class="sc-desc">Consultations per day</span></div></div>
        `;
      }

      // Charts
      if (d.charts) {
        renderDocDeptChart(d.charts.by_department);
        renderDocTrendChart(d.charts.monthly_trend);
      }

      // Table
      if (tbody) {
        if (d.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--slate-500);">No doctor records match criteria.</td></tr>';
        } else {
          tbody.innerHTML = d.data.map(doc => `
            <tr>
              <td style="font-weight:600;">${escHtml(doc.doctor_name)}</td>
              <td>${escHtml(doc.department_name)}</td>
              <td style="font-size:.82rem;">${escHtml(doc.specialty)}</td>
              <td style="font-weight:700;">${doc.total_consultations}</td>
              <td>${doc.completed_consultations}</td>
              <td>${doc.cancelled_consultations}</td>
              <td>${doc.avg_daily_consultations}</td>
              <td><span class="badge compliant">${doc.satisfaction_score}%</span></td>
            </tr>
          `).join('');
        }
      }
    })
    .catch(err => {
      console.error('Doctor stats load error:', err);
      if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--rose-600);">Unable to load doctor statistics. <button class="btn btn-sm btn-outline" onclick="loadDoctorStats()" style="margin-left:.5rem;">Retry</button></td></tr>';
    });
}

function filterDoctors() { loadDoctorStats(); }

function renderDocDeptChart(data) {
  destroyChart('docDeptChart');
  const ctx = document.getElementById('docDeptChart');
  if (!ctx || !data) return;
  if (typeof Chart === 'undefined') return;

  let labels = [];
  let values = [];

  if (Array.isArray(data)) {
    labels = data.map(d => d.department_name || d.name || 'Department');
    values = data.map(d => d.total_vol || d.vol || d.count || 0);
  } else if (data.labels && data.data) {
    labels = data.labels;
    values = data.data;
  }

  const colors = ['#0284c7','#10b981','#6366f1','#f59e0b','#8b5cf6','#f43f5e','#14b8a6'];

  try {
    chartInstances['docDeptChart'] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data: values, backgroundColor: colors.slice(0, Math.max(1, labels.length)), borderWidth: 0, hoverOffset: 6 }]
      },
      options: { 
        responsive: true, maintainAspectRatio: false, cutout: '55%', 
        plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 10, family: 'Inter' } } } } 
      }
    });
  } catch (e) {
    console.warn('Doctor dept chart error:', e);
  }
}

function renderDocTrendChart(data) {
  destroyChart('docTrendChart');
  const ctx = document.getElementById('docTrendChart');
  if (!ctx || !data) return;
  if (typeof Chart === 'undefined') return;

  let labels = [];
  let values = [];

  if (Array.isArray(data)) {
    labels = data.map(d => d.month || d.label);
    values = data.map(d => d.consultations || d.value || 0);
  } else if (data.labels && data.data) {
    labels = data.labels;
    values = data.data;
  }

  try {
    chartInstances['docTrendChart'] = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{ label: 'Consultations', data: values, borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,0.08)', fill: true, tension: 0.35, borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#0284c7' }]
      },
      options: { 
        responsive: true, maintainAspectRatio: false, 
        plugins: { legend: { display: false } }, 
        scales: { x: { grid: { display: false } }, y: { grid: { color: '#f1f5f9' } } } 
      }
    });
  } catch (e) {
    console.warn('Doc trend chart error:', e);
  }
}

// ===== NOTIFICATIONS PAGE =====
function loadNotificationsPage() {
  const el = document.getElementById('notificationsFullList');
  if (el) el.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--slate-500);">Loading notifications...</div>';

  fetch(getApiUrl('notifications.php?action=list'))
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success' || !d.data || d.data.length === 0) {
        if (el) el.innerHTML = '<div style="padding:2.5rem;text-align:center;color:var(--slate-400);">No administrative notifications at this time.</div>';
        return;
      }

      const catColors = {
        'Compliance': { bg: 'var(--amber-50)', color: 'var(--amber-600)', icon: 'shield' },
        'Reports': { bg: 'var(--primary-50)', color: 'var(--primary-600)', icon: 'file' },
        'Performance': { bg: 'var(--emerald-50)', color: 'var(--emerald-600)', icon: 'chart' },
        'Staff': { bg: 'var(--indigo-50)', color: 'var(--indigo-600)', icon: 'user' },
        'Pharmacy': { bg: 'var(--rose-50)', color: 'var(--rose-600)', icon: 'pill' },
        'Doctors': { bg: 'var(--violet-50)', color: 'var(--violet-600)', icon: 'stethoscope' }
      };

      if (el) {
        el.innerHTML = d.data.map(n => {
          const cc = catColors[n.category] || catColors['Reports'];
          return `<div style="display:flex;gap:1rem;padding:1.25rem;border-bottom:1px solid var(--border-subtle);${n.is_read == 0 ? 'background:#f0f9ff;' : ''}">
            <div style="width:40px;height:40px;border-radius:var(--radius-full);background:${cc.bg};color:${cc.color};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </div>
            <div style="flex:1;">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="font-weight:600;color:var(--slate-900);font-size:.95rem;">${escHtml(n.title)}</div>
                <span class="badge active" style="font-size:.68rem;">${escHtml(n.category)}</span>
              </div>
              <div style="font-size:.85rem;color:var(--text-muted);margin-top:.25rem;">${escHtml(n.description)}</div>
              <div style="font-size:.75rem;color:var(--slate-400);margin-top:.35rem;">${n.relative_time || 'Just now'} • ${escHtml(n.category)}</div>
            </div>
          </div>`;
        }).join('');
      }
    })
    .catch(err => {
      console.error('Notifications page load error:', err);
      if (el) el.innerHTML = '<div style="padding:2.5rem;text-align:center;color:var(--rose-600);">Unable to load notifications. <button class="btn btn-sm btn-outline" onclick="loadNotificationsPage()" style="margin-left:.5rem;">Retry</button></div>';
    });
}

// ===== AUDIT TRAIL =====
function loadAuditTrail() {
  const tbody = document.getElementById('auditTableBody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--slate-500);">Loading executive audit trail...</td></tr>';

  fetch(getApiUrl('audit-trail.php'))
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(d => {
      if (d.status !== 'success' || !d.data || d.data.length === 0) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--slate-500);">No audit records found.</td></tr>';
        return;
      }

      if (tbody) {
        tbody.innerHTML = d.data.map(a => `
          <tr>
            <td style="font-weight:600;">${escHtml(a.user_name)}</td>
            <td>${escHtml(a.role_title)}</td>
            <td>${escHtml(a.action_performed)}</td>
            <td>${escHtml(a.report_affected)}</td>
            <td>${escHtml(a.date)}</td>
            <td>${escHtml(a.time)}</td>
          </tr>
        `).join('');
      }
    })
    .catch(err => {
      console.error('Audit trail load error:', err);
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--rose-600);">Unable to load audit trail. <button class="btn btn-sm btn-outline" onclick="loadAuditTrail()" style="margin-left:.5rem;">Retry</button></td></tr>';
    });
}

function showSettingsTab(tab) {
  document.getElementById('settingsProfilePane').style.display = tab === 'profile' ? 'block' : 'none';
  document.getElementById('settingsAuditPane').style.display = tab === 'audit' ? 'block' : 'none';
  document.getElementById('profileTab').className = tab === 'profile' ? 'btn btn-primary' : 'btn btn-secondary';
  document.getElementById('auditTab').className = tab === 'audit' ? 'btn btn-primary' : 'btn btn-secondary';
  if (tab === 'audit') loadAuditTrail();
}

// ===== UTILITY =====
function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function escHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function populateSelect(id, options, currentVal) {
  const sel = document.getElementById(id);
  if (!sel) return;
  const firstOption = sel.options[0]; // "All..." option
  sel.innerHTML = '';
  if (firstOption) sel.appendChild(firstOption);
  options.forEach(o => {
    const opt = document.createElement('option');
    opt.value = o;
    opt.textContent = o;
    if (o === currentVal) opt.selected = true;
    sel.appendChild(opt);
  });
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove('show');
}

function showToast(message, type = 'info') {
  const box = document.getElementById('toastBox');
  if (!box) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>${escHtml(message)}`;
  box.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function handleGlobalSearch(query) {
  if (query.length > 2) {
    console.log('Global search:', query);
  }
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  // Connect date filter
  const dateFilter = document.getElementById('dateRangeFilter');
  if (dateFilter) {
    dateFilter.addEventListener('change', () => {
      if (currentView === 'department-performance') loadDeptPerformance();
      else if (currentView === 'doctor-stats') loadDoctorStats();
      else if (currentView === 'dashboard') loadDashboard();
      else if (currentView === 'reports') loadReports();
    });
  }

  loadDashboard();
  loadNotifications();
});
