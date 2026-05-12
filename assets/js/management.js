// ─────────────────────────────────────────────
//  EcoProtean — Manager Dashboard JS
// ─────────────────────────────────────────────

// ── Sidebar mobile toggle ──────────────────────
(function () {
  const toggle  = document.getElementById('menuToggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!toggle || !sidebar || !overlay) return;
  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
  });
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  });
})();

// ── Auto-dismiss messages ──────────────────────
document.addEventListener('DOMContentLoaded', () => {
  ['successMessage','errorMessage'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s ease';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

  if (typeof returnView !== 'undefined' && returnView !== 'dashboard') {
    showView(returnView);
  }

  initMaps();
  startLiveUpdates();
  initReviewModal();
  startRequestsPolling();
});

// ── View switching ─────────────────────────────
function showView(view) {
  ['dashboard', 'sensors', 'requests'].forEach(v => {
    const el  = document.getElementById('view-' + v);
    const nav = document.getElementById('nav-' + v);
    if (el)  el.style.display = 'none';
    if (nav) nav.classList.remove('active');
  });

  const target    = document.getElementById('view-' + view);
  const navTarget = document.getElementById('nav-' + view);
  if (target)    target.style.display    = 'block';
  if (navTarget) navTarget.classList.add('active');

  const titles = {
    dashboard: ['Dashboard',       'Sensor Monitoring — Live Data'],
    sensors:   ['Sensors',         'Manage Sensor Locations'],
    requests:  ['Sensor Requests', 'Review User Data Requests'],
  };
  document.getElementById('pageTitle').textContent = titles[view]?.[0] ?? view;
  document.getElementById('pageSub').textContent   = titles[view]?.[1] ?? '';

  // Refresh requests table when switching to that view
  if (view === 'requests') fetchAndRenderRequests();
}

// ── Helpers ────────────────────────────────────
function getRiskColor(risk) {
  return { low:'#27ae60', medium:'#e67e22', high:'#e74c3c' }[(risk||'low').toLowerCase()] || '#888';
}
function getRiskClass(risk) {
  return 'risk-' + (risk||'low').toLowerCase();
}
function getRiskLabel(risk) {
  return { high:'Critical', medium:'Warning', low:'Normal' }[(risk||'low').toLowerCase()] || 'Normal';
}
function getCause(level) {
  if (level === null || level === undefined) return '—';
  if (level < 30) return 'Wind';
  if (level < 60) return 'Rain / Soil Softening';
  return 'Ground Instability';
}
function padSensor(id) {
  return 'S' + String(id).padStart(2, '0');
}
function dateRangeLabel(r, from, to) {
  return {
    last_7_days:  'Last 7 days',
    last_30_days: 'Last 30 days',
    last_90_days: 'Last 90 days',
    custom:       `${from || '?'} → ${to || '?'}`
  }[r] || r;
}
function intervalLabel(i) {
  return { raw:'Every reading', hourly:'Hourly average', daily:'Daily summary' }[i] || i;
}
function formatLabel(f) {
  return { view:'View in browser', download:'Download CSV', both:'View + Download' }[f] || f;
}

// ══════════════════════════════════════════════
//  Requests — live polling + render
// ══════════════════════════════════════════════

let _lastRequestSnapshot = '';

function fetchAndRenderRequests() {
  fetch('../api/sensor_requests.php?action=get_all_requests&status=all', {
    credentials: 'same-origin'
  })
  .then(r => r.json())
  .then(requests => {
    if (!Array.isArray(requests)) return;

    // Update nav badge
    const pending = requests.filter(r => r.status === 'pending');
    updateNavBadge(pending.length);

    // Only re-render table if data changed
    const snapshot = JSON.stringify(requests);
    if (snapshot === _lastRequestSnapshot) return;
    _lastRequestSnapshot = snapshot;

    renderRequestsTable(requests);
  })
  .catch(err => console.error('[Requests] Fetch error:', err));
}

function updateNavBadge(count) {
  let badge = document.getElementById('navRequestsBadge');
  if (!badge) {
    const nav = document.getElementById('nav-requests');
    if (!nav) return;
    badge    = document.createElement('span');
    badge.id = 'navRequestsBadge';
    badge.className = 'nav-badge';
    nav.appendChild(badge);
  }
  if (count > 0) {
    badge.textContent   = count;
    badge.style.display = 'inline-block';
  } else {
    badge.style.display = 'none';
  }
}

function renderRequestsTable(requests) {
  const tbody = document.getElementById('requestsTableBody');
  if (!tbody) return;

  if (!requests.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" style="text-align:center;padding:40px;color:#aaa;font-size:0.9rem;">
          No requests yet.
        </td>
      </tr>`;
    return;
  }

  // Update count in header
  const countEl = document.getElementById('requestsCount');
  if (countEl) {
    const pending = requests.filter(r => r.status === 'pending').length;
    countEl.textContent = `${pending} pending · ${requests.length} total`;
  }

  tbody.innerHTML = '';
  requests.forEach(r => {
    const isPending  = r.status === 'pending';
    const badgeClass = { approved:'risk-low', rejected:'risk-high', pending:'risk-medium' }[r.status] || 'risk-medium';
    const badgeIcon  = { approved:'✅', rejected:'❌', pending:'⏳' }[r.status] || '⏳';

    const rangeStr = dateRangeLabel(r.date_range, r.custom_from, r.custom_to);
    const intStr   = intervalLabel(r.interval_type);

    const reviewedHtml = (!isPending && r.reviewed_by_name)
      ? `<div class="muted-text" style="font-size:0.68rem;margin-top:3px;">
           by ${r.reviewed_by_name}<br>
           ${r.reviewed_at ? new Date(r.reviewed_at).toLocaleString('en-US',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : ''}
         </div>`
      : '';

    const remarksHtml = (r.status === 'rejected' && r.rejection_remarks)
      ? `<div style="font-size:0.7rem;color:#c0392b;margin-top:3px;font-style:italic;">
           "${r.rejection_remarks}"
         </div>`
      : '';

    const actionHtml = isPending
      ? `<button class="btn btn-sm" onclick="openReviewModal(${escapeJson(r)})">
           🔍 Review
         </button>`
      : `<span class="muted-text" style="font-size:0.78rem;">Reviewed</span>`;

    const tr = document.createElement('tr');
    tr.setAttribute('data-request-id', r.request_id);
    tr.innerHTML = `
      <td><span class="sensor-chip">#${r.request_id}</span></td>
      <td>
        <strong>${r.requester_name || '—'}</strong>
        <div class="muted-text" style="font-size:0.7rem;">${r.requester_email || ''}</div>
      </td>
      <td>${r.location_name}</td>
      <td>
        <div style="max-width:160px;font-size:0.8rem;color:#555;">${r.reason}</div>
      </td>
      <td style="font-size:0.78rem;color:#555;">
        <div>📅 ${rangeStr}</div>
        <div>🕐 ${intStr}</div>
        <div>📁 ${formatLabel(r.format_pref)}</div>
      </td>
      <td class="muted-text" style="font-size:0.78rem;white-space:nowrap;">
        ${new Date(r.requested_at).toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'})}
      </td>
      <td class="req-status-cell">
        <span class="risk-badge ${badgeClass}">${badgeIcon} ${r.status.charAt(0).toUpperCase()+r.status.slice(1)}</span>
        ${reviewedHtml}
        ${remarksHtml}
      </td>
      <td class="req-action-cell">${actionHtml}</td>`;
    tbody.appendChild(tr);
  });
}

// Safely encode a request object for inline onclick
function escapeJson(obj) {
  return "'" + JSON.stringify(obj).replace(/'/g, "\\'").replace(/</g, '\\u003c') + "'";
}

function startRequestsPolling() {
  // Poll every 5s — same interval as sensor simulation
  fetchAndRenderRequests();
  setInterval(fetchAndRenderRequests, 5000);
}

// ══════════════════════════════════════════════
//  Review Modal
// ══════════════════════════════════════════════
function initReviewModal() {
  if (document.getElementById('reviewModal')) return;

  const modal = document.createElement('div');
  modal.id        = 'reviewModal';
  modal.className = 'mgr-modal-overlay';
  modal.innerHTML = `
    <div class="mgr-modal">
      <button class="mgr-modal-close" id="reviewModalClose">&times;</button>

      <div class="mgr-modal-icon">📋</div>
      <h2 class="mgr-modal-title">Review Sensor Request</h2>

      <!-- Full request details -->
      <div class="mgr-review-details" id="reviewDetails"></div>

      <!-- Error -->
      <div class="mgr-modal-error" id="reviewError" style="display:none;"></div>

      <!-- Rejection remarks — shown only when rejecting -->
      <div class="mgr-form-group" id="remarksGroup" style="display:none;">
        <label for="rejectionRemarks">
          Rejection Remarks <span style="color:#e74c3c;">*</span>
          <span style="font-size:0.75rem;color:#aaa;font-weight:400;">
            — The user will see this message.
          </span>
        </label>
        <textarea id="rejectionRemarks"
          placeholder="Explain why this request is being rejected..."
          rows="3"></textarea>
      </div>

      <!-- Primary action buttons -->
      <div class="mgr-modal-actions" id="reviewActions">
        <button class="mgr-btn secondary" id="reviewCancelBtn">Cancel</button>
        <button class="mgr-btn danger"    id="reviewRejectBtn">❌ Reject</button>
        <button class="mgr-btn primary"   id="reviewApproveBtn">✅ Approve</button>
      </div>

      <!-- Confirm step -->
      <div class="mgr-confirm-step" id="reviewConfirmStep" style="display:none;">
        <div class="mgr-confirm-msg" id="reviewConfirmMsg"></div>
        <div class="mgr-modal-actions" style="margin-top:14px;">
          <button class="mgr-btn secondary" id="reviewConfirmBack">← Go Back</button>
          <button class="mgr-btn primary"   id="reviewConfirmSubmit">Confirm</button>
        </div>
      </div>
    </div>`;

  document.body.appendChild(modal);

  // ── Inject modal styles ──
  if (!document.getElementById('reviewModalStyles')) {
    const style = document.createElement('style');
    style.id = 'reviewModalStyles';
    style.textContent = `
      .mgr-modal-overlay {
        position:fixed;inset:0;background:rgba(0,0,0,0.55);
        z-index:9999;display:none;justify-content:center;
        align-items:center;padding:16px;
      }
      .mgr-modal {
        background:#fff;border-radius:16px;padding:32px;
        width:100%;max-width:540px;max-height:88vh;
        overflow-y:auto;position:relative;
        box-shadow:0 8px 32px rgba(0,0,0,0.2);
        font-family:'Poppins',sans-serif;
      }
      .mgr-modal-close {
        position:absolute;top:16px;right:20px;
        background:none;border:none;font-size:1.4rem;
        cursor:pointer;color:#aaa;line-height:1;
      }
      .mgr-modal-close:hover{color:#333;}
      .mgr-modal-icon{font-size:2.2rem;text-align:center;margin-bottom:10px;}
      .mgr-modal-title{text-align:center;font-size:1.1rem;color:#2c5f5d;margin-bottom:20px;}
      .mgr-review-details{background:#f7faf9;border-radius:10px;padding:16px;margin-bottom:20px;}
      .mgr-detail-row{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #edf2f1;font-size:0.85rem;}
      .mgr-detail-row:last-child{border-bottom:none;}
      .mgr-detail-label{min-width:120px;color:#888;font-weight:500;flex-shrink:0;}
      .mgr-detail-val{color:#333;flex:1;word-break:break-word;}
      .mgr-detail-email{display:block;font-size:0.75rem;color:#aaa;}
      .mgr-detail-divider{
        font-size:0.72rem;font-weight:600;color:#999;
        text-transform:uppercase;letter-spacing:0.05em;
        padding:12px 0 4px;border-top:1px solid #edf2f1;margin-top:4px;
      }
      .mgr-field-chip{
        display:inline-block;background:#e8f4f3;color:#2c5f5d;
        border-radius:6px;padding:1px 8px;font-size:0.75rem;
        font-weight:600;margin:2px 2px 0 0;
      }
      .mgr-form-group{margin-bottom:16px;}
      .mgr-form-group label{display:block;font-size:0.83rem;font-weight:500;color:#555;margin-bottom:6px;}
      .mgr-form-group textarea{
        width:100%;padding:10px 12px;border:2px solid #e0e0e0;
        border-radius:8px;font-family:'Poppins',sans-serif;
        font-size:0.88rem;resize:vertical;min-height:80px;
        outline:none;box-sizing:border-box;transition:border-color 0.2s;
      }
      .mgr-form-group textarea:focus{border-color:#e74c3c;}
      .mgr-modal-error{
        background:#fdecea;color:#c0392b;border-radius:8px;
        padding:10px 14px;font-size:0.83rem;margin-bottom:14px;
      }
      .mgr-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
      .mgr-btn{
        padding:10px 20px;border-radius:8px;font-family:'Poppins',sans-serif;
        font-size:0.88rem;font-weight:600;cursor:pointer;border:none;transition:opacity 0.2s;
      }
      .mgr-btn:hover{opacity:0.88;}
      .mgr-btn:disabled{opacity:0.6;cursor:not-allowed;}
      .mgr-btn.primary{background:linear-gradient(135deg,#2c5f5d,#1b9e9b);color:#fff;}
      .mgr-btn.secondary{background:#f0f4f3;color:#555;}
      .mgr-btn.danger{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;}
      .mgr-confirm-step{background:#f7faf9;border-radius:10px;padding:20px;margin-top:16px;text-align:center;}
      .mgr-confirm-icon{font-size:2rem;display:block;margin-bottom:8px;}
      .mgr-confirm-msg{font-size:0.9rem;color:#444;line-height:1.6;}
      .nav-badge{
        background:#e74c3c;color:#fff;border-radius:10px;
        padding:1px 7px;font-size:0.7rem;font-weight:700;margin-left:6px;
      }
    `;
    document.head.appendChild(style);
  }

  // ── State ──
  let currentRequest = null;
  let pendingAction  = null;

  // ── Refs ──
  const overlay       = modal;
  const closeBtn      = document.getElementById('reviewModalClose');
  const cancelBtn     = document.getElementById('reviewCancelBtn');
  const approveBtn    = document.getElementById('reviewApproveBtn');
  const rejectBtn     = document.getElementById('reviewRejectBtn');
  const actionsEl     = document.getElementById('reviewActions');
  const confirmStep   = document.getElementById('reviewConfirmStep');
  const confirmMsg    = document.getElementById('reviewConfirmMsg');
  const confirmBack   = document.getElementById('reviewConfirmBack');
  const confirmSubmit = document.getElementById('reviewConfirmSubmit');
  const remarksGroup  = document.getElementById('remarksGroup');
  const remarksInput  = document.getElementById('rejectionRemarks');
  const errorEl       = document.getElementById('reviewError');
  const detailsEl     = document.getElementById('reviewDetails');

  function resetModal() {
    errorEl.style.display      = 'none';
    errorEl.textContent        = '';
    remarksGroup.style.display = 'none';
    remarksInput.value         = '';
    actionsEl.style.display    = 'flex';
    confirmStep.style.display  = 'none';
    pendingAction              = null;
  }

  function openModal(req) {
    // req may arrive as a string from inline onclick
    if (typeof req === 'string') {
      try { req = JSON.parse(req); } catch(e) { console.error('Bad req JSON', e); return; }
    }
    currentRequest = req;
    resetModal();

    // ── Build details ──
    const fields = (req.fields || 'movement,risk,cause,timestamp').split(',');

    detailsEl.innerHTML = `
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">👤 Requester</span>
        <span class="mgr-detail-val">
          <strong>${req.requester_name || '—'}</strong>
          <span class="mgr-detail-email">${req.requester_email || ''}</span>
        </span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">📍 Location</span>
        <span class="mgr-detail-val"><strong>${req.location_name}</strong></span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">📝 Reason</span>
        <span class="mgr-detail-val">${req.reason}</span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">🎯 Intended Use</span>
        <span class="mgr-detail-val">${req.intended_use}</span>
      </div>

      <div class="mgr-detail-divider">Data Preferences Requested</div>

      <div class="mgr-detail-row">
        <span class="mgr-detail-label">📅 Date Range</span>
        <span class="mgr-detail-val">
          ${dateRangeLabel(req.date_range, req.custom_from, req.custom_to)}
          ${req.date_range === 'custom'
            ? `<span class="muted-text" style="font-size:0.75rem;display:block;">
                 ${req.custom_from || '?'} → ${req.custom_to || '?'}
               </span>`
            : ''}
        </span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">📊 Fields</span>
        <span class="mgr-detail-val">
          ${fields.map(f => `<span class="mgr-field-chip">${f}</span>`).join('')}
        </span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">🕐 Interval</span>
        <span class="mgr-detail-val">${intervalLabel(req.interval_type)}</span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">📁 Format</span>
        <span class="mgr-detail-val">${formatLabel(req.format_pref)}</span>
      </div>
      <div class="mgr-detail-row">
        <span class="mgr-detail-label">🕒 Requested</span>
        <span class="mgr-detail-val">
          ${new Date(req.requested_at).toLocaleString('en-US',{
            month:'long', day:'numeric', year:'numeric',
            hour:'2-digit', minute:'2-digit'
          })}
        </span>
      </div>`;

    overlay.style.display = 'flex';
  }

  function closeModal() {
    overlay.style.display = 'none';
    currentRequest        = null;
    pendingAction         = null;
  }

  function showConfirm(action) {
    pendingAction = action;
    errorEl.style.display  = 'none';
    actionsEl.style.display = 'none';

    if (action === 'reject') {
      remarksGroup.style.display = 'block';
      remarksInput.focus();
      confirmMsg.innerHTML = `
        <span class="mgr-confirm-icon">❌</span>
        You are about to <strong>reject</strong> this request.<br>
        <small style="color:#888;">Please fill in the remarks above — the user will see your reason.</small>`;
      confirmSubmit.textContent = 'Confirm Rejection';
      confirmSubmit.className   = 'mgr-btn danger';
    } else {
      remarksGroup.style.display = 'none';
      confirmMsg.innerHTML = `
        <span class="mgr-confirm-icon">✅</span>
        You are about to <strong>approve</strong> this request.<br>
        <small style="color:#888;">
          The user will gain access to sensor history matching their requested preferences.
        </small>`;
      confirmSubmit.textContent = 'Confirm Approval';
      confirmSubmit.className   = 'mgr-btn primary';
    }

    confirmStep.style.display = 'block';
  }

  function submitReview() {
    const remarks = remarksInput.value.trim();

    if (pendingAction === 'reject' && !remarks) {
      errorEl.textContent   = 'Rejection remarks are required. Please explain why you are rejecting this request.';
      errorEl.style.display = 'block';
      return;
    }

    confirmSubmit.disabled    = true;
    confirmSubmit.textContent = '⏳ Processing...';
    errorEl.style.display     = 'none';

    const formData = new FormData();
    formData.append('action',      'review_request');
    formData.append('request_id',  currentRequest.request_id);
    
    // FIX 1: Change 'review' to 'review_action' so your PHP POST handler recognizes it!
    formData.append('review_action', pendingAction === 'approve' ? 'approve' : 'reject');
    
    if (pendingAction === 'reject') {
      formData.append('rejection_remarks', remarks);
    }

    fetch('../api/sensor_requests.php', {
      method:      'POST',
      credentials: 'same-origin',
      body:        formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        errorEl.textContent       = data.message;
        errorEl.style.display     = 'block';
        confirmSubmit.disabled    = false;
        confirmSubmit.textContent = pendingAction === 'approve' ? 'Confirm Approval' : 'Confirm Rejection';
        return;
      }

      closeModal();
      showMgrNotification(
        pendingAction === 'approve'
          ? `✅ Request #${currentRequest.request_id} approved — user now has access to the sensor data.`
          : `❌ Request #${currentRequest.request_id} rejected.`,
        pendingAction === 'approve' ? 'success' : 'warning'
      );

      // FIX 2: Clear snapshot track cache to guarantee an immediate interface table re-draw
      _lastRequestSnapshot = ''; 
      fetchAndRenderRequests();
    })
    .catch((err) => {
      console.error(err);
      errorEl.textContent       = 'Something went wrong. Please try again.';
      errorEl.style.display     = 'block';
      confirmSubmit.disabled    = false;
      confirmSubmit.textContent = pendingAction === 'approve' ? 'Confirm Approval' : 'Confirm Rejection';
    });
  }

  // ── Event listeners ──
  closeBtn.addEventListener('click',    closeModal);
  cancelBtn.addEventListener('click',   closeModal);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
  approveBtn.addEventListener('click',  () => showConfirm('approve'));
  rejectBtn.addEventListener('click',   () => showConfirm('reject'));
  confirmBack.addEventListener('click', () => {
    confirmStep.style.display  = 'none';
    remarksGroup.style.display = 'none';
    actionsEl.style.display    = 'flex';
    errorEl.style.display      = 'none';
  });
  confirmSubmit.addEventListener('click', submitReview);

  // Expose globally so inline onclick can call it
  window.openReviewModal = openModal;
}

// ── Manager notification banner ───────────────
function showMgrNotification(message, type = 'success') {
  let banner = document.getElementById('mgrNotificationBanner');
  if (!banner) {
    banner    = document.createElement('div');
    banner.id = 'mgrNotificationBanner';
    banner.style.cssText = `
      position:fixed;top:80px;left:50%;transform:translateX(-50%);
      z-index:9999;padding:14px 24px;border-radius:10px;
      font-family:'Poppins',sans-serif;font-size:0.88rem;font-weight:500;
      box-shadow:0 4px 16px rgba(0,0,0,0.15);
      max-width:480px;text-align:center;display:none;`;
    document.body.appendChild(banner);
  }
  const styles = {
    success: 'background:#e8f8f5;color:#1e8449;border:1px solid #a9dfbf;',
    warning: 'background:#fdecea;color:#c0392b;border:1px solid #f5b7b1;',
    info:    'background:#eaf4fb;color:#1a6fa0;border:1px solid #aed6f1;',
  };
  banner.style.cssText += styles[type] || styles.info;
  banner.textContent    = message;
  banner.style.display  = 'block';
  setTimeout(() => { banner.style.display = 'none'; }, 5000);
}

// ══════════════════════════════════════════════
//  Map initialization
// ══════════════════════════════════════════════
function initMaps() {
  if (document.getElementById('map')) {
    const dashMap = L.map('map', { scrollWheelZoom: false }).setView([8.378, 124.900], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(dashMap);
    window._dashMap     = dashMap;
    window._dashMarkers = {};

    if (typeof allLocations !== 'undefined') {
      allLocations.forEach(loc => {
        window._dashMarkers[loc.id] = L.circleMarker([loc.lat, loc.lng], {
          radius: 11, color: '#fff', weight: 2.5,
          fillColor: '#1b9e9b', fillOpacity: 0.85
        }).addTo(dashMap).bindPopup(`<strong>${loc.name}</strong>`);
      });
    }
  }

  if (document.getElementById('sensorPickerMap')) {
    const spMap = L.map('sensorPickerMap', { scrollWheelZoom: false })
      .setView([8.378, 124.900], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(spMap);
    window._sensorPickerMap    = spMap;
    window._sensorPickerMarker = null;

    if (typeof allLocations !== 'undefined') {
      allLocations.forEach(loc => {
        L.circleMarker([loc.lat, loc.lng], {
          radius: 8, color: '#fff', weight: 2,
          fillColor: '#2c5f5d', fillOpacity: 0.75
        }).addTo(spMap)
          .bindPopup(`<strong>${loc.name}</strong><br><small style="color:#888">Existing sensor</small>`);
      });
    }

    spMap.on('click', function (e) {
      const { lat, lng } = e.latlng;
      document.getElementById('sensorLat').value = lat.toFixed(8);
      document.getElementById('sensorLng').value = lng.toFixed(8);

      if (window._sensorPickerMarker) {
        window._sensorPickerMarker.setLatLng([lat, lng]);
      } else {
        window._sensorPickerMarker = L.marker([lat, lng]).addTo(spMap);
      }

      document.getElementById('sensorPickerMap').classList.add('picked');
      document.getElementById('mapClickHint').style.display = 'none';
      reverseGeocode(lat, lng);
    });
  }
}

// ── Reverse geocoding ──────────────────────────
function reverseGeocode(lat, lng) {
  const statusEl  = document.getElementById('geocodeStatus');
  const nameInput = document.getElementById('sensorLocName');
  const submitBtn = document.getElementById('addSensorBtn');

  statusEl.style.display = '';
  statusEl.className     = 'geocode-status loading';
  statusEl.textContent   = '🔍 Fetching location name...';
  submitBtn.disabled     = true;

  fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`, {
    headers: { 'Accept-Language': 'en' }
  })
  .then(r => r.json())
  .then(data => {
    const addr = data.address || {};
    const name =
      addr.village       || addr.suburb    || addr.neighbourhood ||
      addr.town          || addr.city_district || addr.city      ||
      addr.county        || addr.state     ||
      data.display_name?.split(',')[0]     || 'Unknown Location';

    nameInput.value      = name;
    statusEl.className   = 'geocode-status done';
    statusEl.textContent = '✅ Location name fetched — you can edit it below.';
    submitBtn.disabled   = false;
  })
  .catch(() => {
    nameInput.value      = '';
    statusEl.className   = 'geocode-status error';
    statusEl.textContent = '⚠ Could not fetch name. Please type it manually.';
    submitBtn.disabled   = false;
    nameInput.focus();
  });
}

// ══════════════════════════════════════════════
//  Live sensor simulation + updates
// ══════════════════════════════════════════════
function startLiveUpdates() {

  function simulateSensors() {
    return fetch('../api/simulate.php', {
      method: 'POST', credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) console.warn('Simulate warning:', data.message);
      else console.log('[' + new Date().toLocaleTimeString() + '] Simulated:', data.inserted);
      return data;
    })
    .catch(err => console.error('Simulate failed:', err));
  }

  function fetchAndUpdate() {
    fetch('../api/locations.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(locations => {
      if (!Array.isArray(locations) || locations.error) {
        console.error('Locations API error:', locations);
        return;
      }
      updateDashMap(locations);
      updateSensorTable(locations);
      updateKPI(locations);
      updateAlerts(locations);
      const el = document.getElementById('lastUpdate');
      if (el) el.textContent = 'Last update: ' + new Date().toLocaleTimeString();
    })
    .catch(err => console.error('Fetch failed:', err));
  }

  async function tick() {
    await simulateSensors();
    fetchAndUpdate();
  }

  tick();
  setInterval(tick, 5000);
}

// ── Dashboard map update ───────────────────────
function updateDashMap(locations) {
  if (!window._dashMap) return;
  locations.forEach(loc => {
    const color = getRiskColor(loc.risk);
    const mvmt  = loc.movement_level !== null ? loc.movement_level + '/100' : 'No data';
    const popup = `
      <div style="font-family:'Poppins',sans-serif;min-width:170px;">
        <div style="font-weight:600;color:#2c5f5d;font-size:0.88rem;margin-bottom:6px;">${loc.name}</div>
        <div style="font-size:0.78rem;color:#555;margin-bottom:3px;">
          Sensor: <strong>${padSensor(loc.sensor_id)}</strong>
        </div>
        <div style="font-size:0.78rem;color:#555;margin-bottom:3px;">
          Movement: <strong style="color:${color}">${mvmt}</strong>
        </div>
        <div style="font-size:0.78rem;color:#555;">
          Status: <strong style="color:${color}">${getRiskLabel(loc.risk)}</strong>
        </div>
      </div>`;

    if (window._dashMarkers[loc.id]) {
      window._dashMarkers[loc.id].setStyle({ fillColor: color });
      window._dashMarkers[loc.id].setPopupContent(popup);
    } else {
      window._dashMarkers[loc.id] = L.circleMarker(loc.coords, {
        radius: 11, color: '#fff', weight: 2.5,
        fillColor: color, fillOpacity: 0.9
      }).addTo(window._dashMap).bindPopup(popup);
    }
  });
}

// ── Sensor table update ────────────────────────
function updateSensorTable(locations) {
  const tbody = document.getElementById('sensorTable');
  if (!tbody) return;
  tbody.innerHTML = '';
  locations.forEach(loc => {
    const lvl   = loc.movement_level ?? 0;
    const color = getRiskColor(loc.risk);
    tbody.innerHTML += `
      <tr>
        <td><span class="sensor-chip">${padSensor(loc.sensor_id)}</span></td>
        <td>${loc.name}</td>
        <td>Motion</td>
        <td>
          <div class="movement-wrap">
            <div class="movement-bar-bg">
              <div class="movement-bar-fill" style="width:${lvl}%;background:${color};"></div>
            </div>
            <span class="movement-val" style="color:${color};">${lvl}</span>
          </div>
        </td>
        <td><span class="cause-tag">${getCause(lvl)}</span></td>
        <td><span class="risk-badge ${getRiskClass(loc.risk)}">${getRiskLabel(loc.risk)}</span></td>
      </tr>`;
  });
}

// ── KPI update ─────────────────────────────────
function updateKPI(locations) {
  let atRisk = 0, critical = 0;
  locations.forEach(loc => {
    const r = (loc.risk || '').toLowerCase();
    if (r !== 'low')  atRisk++;
    if (r === 'high') critical++;
  });
  const t = document.getElementById('totalSensors');
  const a = document.getElementById('atRisk');
  const c = document.getElementById('critical');
  if (t) t.textContent = locations.length;
  if (a) a.textContent = atRisk;
  if (c) c.textContent = critical;

  const banner = document.getElementById('alertBanner');
  if (banner) {
    if (critical > 0) {
      banner.className = 'critical-banner';
      banner.innerHTML = `<div class="pulse"></div>
        <strong>${critical} sensor${critical > 1 ? 's' : ''} critical</strong>
        — movement ≥ 60 detected.`;
    } else {
      banner.className = 'all-clear';
      banner.innerHTML = '✅ All clear — no critical sensor readings at this time.';
    }
  }
}

// ── Alerts update ──────────────────────────────
function updateAlerts(locations) {
  const alertList = document.getElementById('alerts');
  if (!alertList) return;
  const highRisk = locations.filter(l => (l.risk || '').toLowerCase() === 'high');
  highRisk.forEach(loc => {
    const existing = [...alertList.querySelectorAll('.alert-item')].map(el => el.dataset.id);
    if (!existing.includes(String(loc.id))) {
      const placeholder = alertList.querySelector('.no-alerts');
      if (placeholder) placeholder.remove();
      const li      = document.createElement('li');
      li.className  = 'alert-item';
      li.dataset.id = loc.id;
      li.innerHTML  = `
        <div>
          <strong>${loc.name}</strong>
          <span class="sensor-chip" style="margin-left:5px;">${padSensor(loc.sensor_id)}</span>
          <div style="margin-top:3px;font-size:0.77rem;color:#888;">
            Movement: <strong style="color:#c0392b;">${loc.movement_level}/100</strong>
            — Ground Instability
          </div>
        </div>
        <span class="alert-time">${new Date().toLocaleTimeString()}</span>`;
      alertList.prepend(li);
    }
  });
}