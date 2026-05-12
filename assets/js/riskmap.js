// ─────────────────────────────────────────────
//  EcoProtean — Risk Map
//  Works for both public (index.php) and
//  authenticated (webapp/riskmap/index.php)
//  Guest clicks "Request Sensor Data" → login modal
//  Logged-in → two-step request modal
//  Polls api/locations.php every 5s
// ─────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

  const mapContainer = document.getElementById('map');
  if (!mapContainer) {
    console.error('Map container not found!');
    return;
  }

  const isLoggedIn = window.ecoUser?.loggedIn ?? false;
  const apiBase    = window.ecoMapConfig?.apiBase ?? '/ecoprotean/api';

  // ── Initialize map ─────────────────────────
  const map = L.map('map', { zoomControl: true }).setView([8.3644, 124.8669], 13);
  window._riskmapLeaflet = map;

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  // ── Risk colors & labels ───────────────────
  const riskColors = {
    high:   '#e74c3c',
    medium: '#e67e22',
    low:    '#27ae60'
  };

  const riskLabels = {
    high:   'High Risk',
    medium: 'Medium Risk',
    low:    'Low Risk'
  };

  const markers = {};

  // ── Build popup HTML ───────────────────────
  function buildPopup(area) {
    const color   = riskColors[area.risk] || '#27ae60';
    const riskLbl = riskLabels[area.risk]  || 'Low Risk';
    const mvmt    = area.movement_level !== null
      ? `<span class="eco-movement">Movement: <strong style="color:${color}">${area.movement_level}/100</strong></span>`
      : '';

    return `
      <div class="eco-popup">
        <div class="eco-popup-header">
          <h3>${area.name}</h3>
          <div class="eco-popup-meta">
            <span class="eco-risk-badge ${area.risk}">${riskLbl}</span>
            ${mvmt}
          </div>
        </div>
        <div class="eco-popup-footer">
          <button class="eco-btn-sensor"
            data-area-id="${area.id}"
            data-area-name="${area.name}">
            🔬 Request Sensor Data
          </button>
        </div>
      </div>`;
  }

  // ── Login Modal (guests) ───────────────────
  const loginModal  = document.getElementById('loginModal');
  const modalClose  = document.getElementById('modalClose');
  const modalCancel = document.getElementById('modalCancel');

  function showLoginModal() {
    if (loginModal) loginModal.style.display = 'flex';
  }
  function hideLoginModal() {
    if (loginModal) loginModal.style.display = 'none';
  }

  if (modalClose)  modalClose.addEventListener('click', hideLoginModal);
  if (modalCancel) modalCancel.addEventListener('click', hideLoginModal);
  if (loginModal) {
    loginModal.addEventListener('click', e => {
      if (e.target === loginModal) hideLoginModal();
    });
  }

  // ── Two-step Request Modal state ───────────
  const requestModal        = document.getElementById('requestModal');
  const requestModalClose   = document.getElementById('requestModalClose');
  const requestForm         = document.getElementById('requestForm');
  const requestLocationId   = document.getElementById('requestLocationId');
  const requestLocationName = document.getElementById('requestLocationName');
  const requestError        = document.getElementById('requestError');

  let currentStep = 1;

  function goToStep(n) {
    currentStep = n;

    const step1 = document.getElementById('requestStep1');
    const step2 = document.getElementById('requestStep2');
    const dot1  = document.getElementById('stepDot1');
    const dot2  = document.getElementById('stepDot2');

    if (step1) step1.style.display = n === 1 ? 'block' : 'none';
    if (step2) step2.style.display = n === 2 ? 'block' : 'none';

    if (dot1) {
      dot1.className = 'eco-step-dot ' + (n === 1 ? 'active' : 'done');
    }
    if (dot2) {
      dot2.className = 'eco-step-dot ' + (n === 2 ? 'active' : 'inactive');
    }
  }

  function showRequestModal(areaId, areaName) {
    if (!requestModal) return;
    if (requestLocationId)   requestLocationId.value         = areaId;
    if (requestLocationName) requestLocationName.textContent = areaName;
    if (requestError) {
      requestError.style.display = 'none';
      requestError.textContent   = '';
    }
    if (requestForm) requestForm.reset();
    // re-set after reset
    if (requestLocationId) requestLocationId.value = areaId;
    goToStep(1);
    requestModal.style.display = 'flex';
  }

  function hideRequestModal() {
    if (requestModal) requestModal.style.display = 'none';
  }

  if (requestModalClose) {
    requestModalClose.addEventListener('click', hideRequestModal);
  }
  if (requestModal) {
    requestModal.addEventListener('click', e => {
      if (e.target === requestModal) hideRequestModal();
    });
  }

  // ── Step navigation (delegated) ───────────
  document.addEventListener('click', function (e) {

    // Cancel button
    if (e.target.id === 'requestModalCancel') {
      hideRequestModal();
      return;
    }

    // Next button
    if (e.target.id === 'btnNextStep') {
      const reason       = document.getElementById('requestReason')?.value.trim();
      const intended_use = document.getElementById('requestIntendedUse')?.value.trim();
      const err          = document.getElementById('requestError');

      if (!reason || !intended_use) {
        if (err) {
          err.textContent   = 'Please fill out both fields before continuing.';
          err.style.display = 'block';
        }
        return;
      }
      if (err) err.style.display = 'none';
      goToStep(2);
      return;
    }

    // Back button
    if (e.target.id === 'btnPrevStep') {
      goToStep(1);
      return;
    }

    // Custom date range toggle
    if (e.target.id === 'reqDateRange' || e.target.closest('#reqDateRange')) {
      const val  = document.getElementById('reqDateRange')?.value;
      const wrap = document.getElementById('customDateWrap');
      if (wrap) wrap.style.display = val === 'custom' ? 'block' : 'none';
      return;
    }
  });

  // Also handle date range via change event
  document.addEventListener('change', function (e) {
    if (e.target.id === 'reqDateRange') {
      const wrap = document.getElementById('customDateWrap');
      if (wrap) wrap.style.display = e.target.value === 'custom' ? 'block' : 'none';
    }
  });

  // ── Handle request form submit ─────────────
  if (requestForm) {
    requestForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const location_id  = requestLocationId?.value;
      const reason       = document.getElementById('requestReason')?.value.trim();
      const intended_use = document.getElementById('requestIntendedUse')?.value.trim();
      const date_range   = document.getElementById('reqDateRange')?.value   || 'last_30_days';
      const custom_from  = document.getElementById('reqCustomFrom')?.value  || '';
      const custom_to    = document.getElementById('reqCustomTo')?.value    || '';
      const interval_type= document.getElementById('reqInterval')?.value    || 'raw';
      const format_pref  = document.getElementById('reqFormat')?.value      || 'both';
      const err          = document.getElementById('requestError');
      const submitBtn    = document.getElementById('requestSubmitBtn');

      // Collect checked fields
      const checked = document.querySelectorAll('input[name="fields"]:checked');
      const fields  = Array.from(checked).map(cb => cb.value).join(',');

      if (!reason || !intended_use) {
        if (err) {
          err.textContent   = 'Please fill out all required fields.';
          err.style.display = 'block';
        }
        goToStep(1);
        return;
      }

      if (!fields) {
        if (err) {
          err.textContent   = 'Please select at least one data field.';
          err.style.display = 'block';
        }
        return;
      }

      if (date_range === 'custom' && (!custom_from || !custom_to)) {
        if (err) {
          err.textContent   = 'Please provide both start and end dates.';
          err.style.display = 'block';
        }
        return;
      }

      if (err) err.style.display = 'none';
      if (submitBtn) {
        submitBtn.disabled    = true;
        submitBtn.textContent = '⏳ Submitting...';
      }

      const formData = new FormData();
      formData.append('action',        'submit_request');
      formData.append('location_id',   location_id);
      formData.append('reason',        reason);
      formData.append('intended_use',  intended_use);
      formData.append('date_range',    date_range);
      formData.append('custom_from',   custom_from);
      formData.append('custom_to',     custom_to);
      formData.append('interval_type', interval_type);
      formData.append('format_pref',   format_pref);
      formData.append('fields',        fields);

      fetch(`${apiBase}/sensor_requests.php`, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.error) {
          if (err) {
            err.textContent   = data.message;
            err.style.display = 'block';
          }
        } else {
          hideRequestModal();
          showNotification('✅ Request submitted! Waiting for manager approval.', 'success');
          loadMyRequests();
        }
      })
      .catch(() => {
        if (err) {
          err.textContent   = 'Something went wrong. Please try again.';
          err.style.display = 'block';
        }
      })
      .finally(() => {
        if (submitBtn) {
          submitBtn.disabled    = false;
          submitBtn.textContent = 'Submit Request';
        }
      });
    });
  }

  // ── Sensor button handler (delegated) ──────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.eco-btn-sensor');
    if (!btn) return;
    if (!isLoggedIn) {
      showLoginModal();
    } else {
      const areaId   = btn.getAttribute('data-area-id');
      const areaName = btn.getAttribute('data-area-name');
      showRequestModal(areaId, areaName);
    }
  });

  // ── Notification banner ────────────────────
  function showNotification(message, type = 'success') {
    const banner = document.getElementById('notificationBanner');
    if (!banner) return;
    banner.textContent   = message;
    banner.className     = `eco-notification ${type}`;
    banner.style.display = 'block';
    setTimeout(() => { banner.style.display = 'none'; }, 5000);
  }

  // ── My Requests Panel ──────────────────────
  const myRequestsBtn   = document.getElementById('myRequestsBtn');
  const myRequestsPanel = document.getElementById('myRequestsPanel');
  const myRequestsClose = document.getElementById('myRequestsClose');
  const myRequestsList  = document.getElementById('myRequestsList');

  if (myRequestsBtn) {
    myRequestsBtn.addEventListener('click', function () {
      if (myRequestsPanel) {
        myRequestsPanel.classList.toggle('open');
        if (myRequestsPanel.classList.contains('open')) loadMyRequests();
      }
    });
  }

  if (myRequestsClose) {
    myRequestsClose.addEventListener('click', () => {
      if (myRequestsPanel) myRequestsPanel.classList.remove('open');
    });
  }

  // ── Label helpers ──────────────────────────
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

  // ── Load My Requests ───────────────────────
  function loadMyRequests() {
    if (!myRequestsList) return;
    myRequestsList.innerHTML = '<div class="req-loading">Loading...</div>';

    fetch(`${apiBase}/sensor_requests.php?action=get_my_requests`, {
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(requests => {
      if (!Array.isArray(requests)) {
        myRequestsList.innerHTML = '<div class="req-error">Unexpected response from server.</div>';
        return;
      }
      if (!requests.length) {
        myRequestsList.innerHTML = '<div class="req-empty">No requests yet.</div>';
        return;
      }

      myRequestsList.innerHTML = '';
      requests.forEach(req => {
        const item = document.createElement('div');
        item.className = `req-item req-${req.status}`;

        const statusIcon = { pending:'⏳', approved:'✅', rejected:'❌' }[req.status] || '❓';
        const date = new Date(req.requested_at).toLocaleString('en-US', {
          month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
        });

        const reviewedInfo = req.reviewed_by_name
          ? `<div class="req-reviewed">
               ${req.status === 'approved' ? 'Approved' : 'Rejected'} by
               <strong>${req.reviewed_by_name}</strong>
             </div>`
          : '';

        const remarksHtml = (req.status === 'rejected' && req.rejection_remarks)
          ? `<div class="req-remarks">💬 <em>${req.rejection_remarks}</em></div>`
          : '';

        const prefHtml = `
          <div class="req-prefs">
            <span>📅 ${dateRangeLabel(req.date_range, req.custom_from, req.custom_to)}</span>
            <span>🕐 ${intervalLabel(req.interval_type)}</span>
            <span>📁 ${formatLabel(req.format_pref)}</span>
          </div>`;

        const viewBtn = req.status === 'approved'
          ? `<button class="req-view-btn"
               data-request-id="${req.request_id}"
               data-location-id="${req.location_id}"
               data-location-name="${req.location_name}"
               data-format="${req.format_pref}">
               📊 View Sensor History
             </button>`
          : '';

        item.innerHTML = `
          <div class="req-header">
            <span class="req-location">${req.location_name}</span>
            <span class="req-status-badge req-badge-${req.status}">
              ${statusIcon} ${req.status.charAt(0).toUpperCase() + req.status.slice(1)}
            </span>
          </div>
          <div class="req-reason">${req.reason}</div>
          ${prefHtml}
          <div class="req-date">${date}</div>
          ${reviewedInfo}
          ${remarksHtml}
          ${viewBtn}
        `;
        myRequestsList.appendChild(item);
      });

      // Attach view history handlers
      myRequestsList.querySelectorAll('.req-view-btn').forEach(btn => {
        btn.addEventListener('click', function () {
          const requestId  = this.getAttribute('data-request-id');
          const locationId = this.getAttribute('data-location-id');
          const locName    = this.getAttribute('data-location-name');
          const formatPref = this.getAttribute('data-format');
          loadSensorHistory(requestId, locationId, locName, formatPref);
        });
      });
    })
    .catch(err => {
      console.error('[MyRequests] Fetch error:', err);
      myRequestsList.innerHTML = '<div class="req-error">Failed to load requests. Please try again.</div>';
    });
  }

  // ── Sensor History Modal ───────────────────
  const historyModal      = document.getElementById('historyModal');
  const historyModalClose = document.getElementById('historyModalClose');
  const historyTitle      = document.getElementById('historyTitle');
  const historyContent    = document.getElementById('historyContent');

  if (historyModalClose) {
    historyModalClose.addEventListener('click', () => {
      if (historyModal) historyModal.style.display = 'none';
    });
  }
  if (historyModal) {
    historyModal.addEventListener('click', e => {
      if (e.target === historyModal) historyModal.style.display = 'none';
    });
  }

  function loadSensorHistory(requestId, locationId, locationName, formatPref) {
    if (!historyModal) return;

    if (historyTitle)   historyTitle.textContent = `📍 ${locationName} — Sensor History`;
    if (historyContent) historyContent.innerHTML = '<div class="hist-loading">Loading history...</div>';
    historyModal.style.display = 'flex';

    fetch(`${apiBase}/sensor_requests.php?action=get_sensor_history&request_id=${requestId}&location_id=${locationId}`, {
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
      if (data.error || !data.history || !data.history.length) {
        historyContent.innerHTML = '<div class="hist-empty">No sensor history available for this period.</div>';
        return;
      }

      const history      = data.history;
      const intervalType = data.interval_type || 'raw';
      const fields       = (data.fields || 'movement,risk,cause,timestamp').split(',');
      const showMovement = fields.includes('movement');
      const showRisk     = fields.includes('risk');
      const showCause    = fields.includes('cause');
      const dateFrom     = data.date_from
        ? new Date(data.date_from).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
        : '';
      const dateTo = data.date_to
        ? new Date(data.date_to).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
        : '';

      const showDownload = (formatPref === 'download' || formatPref === 'both');
      const showView     = (formatPref === 'view'     || formatPref === 'both');

      let html = '';

      // Toolbar
      html += `
        <div class="hist-toolbar">
          <div class="hist-range-info">
            📅 <strong>${dateFrom}</strong> → <strong>${dateTo}</strong>
            &nbsp;·&nbsp; ${data.total} ${intervalType === 'raw' ? 'readings' : intervalType === 'hourly' ? 'hourly averages' : 'daily summaries'}
          </div>
          ${showDownload
            ? `<button class="hist-csv-btn" id="downloadCsvBtn">⬇ Download CSV</button>`
            : ''}
        </div>`;

      if (showView) {
        // Latest reading card
        const latest      = history[0];
        const latestMvmt  = latest.movement_level ?? null;
        const latestRisk  = latest.risk || 'low';
        const latestColor = riskColors[latestRisk] || '#27ae60';

        html += `<div class="hist-latest"><div class="hist-latest-label">Latest Reading</div>`;

        if (showMovement && latestMvmt !== null) {
          html += `
            <div class="hist-gauge">
              <div class="hist-gauge-bar">
                <div class="hist-gauge-fill" style="width:${latestMvmt}%;background:${latestColor};"></div>
              </div>
              <span class="hist-gauge-val" style="color:${latestColor};">${latestMvmt}/100</span>
            </div>`;
        }

        html += `<div class="hist-latest-risk">`;
        if (showRisk) {
          html += `<span class="eco-risk-badge ${latestRisk}">${riskLabels[latestRisk] || latestRisk}</span>`;
        }
        html += `
            <span class="hist-latest-time">
              ${new Date(latest.timestamp).toLocaleString('en-US',{
                month:'short', day:'numeric',
                hour:'2-digit', minute:'2-digit', second:'2-digit'
              })}
            </span>
          </div>
        </div>`;

        // Chart
        if (showMovement && history.length > 1) {
          html += `
            <div class="hist-chart-wrap">
              <div class="hist-table-title">Movement Trend</div>
              <canvas id="historyChart" height="120"></canvas>
            </div>`;
        }

        // Table
        html += `
          <div class="hist-table-wrap">
            <div class="hist-table-title">
              All Readings (${data.total})
              ${intervalType !== 'raw'
                ? `<span class="hist-interval-badge">${intervalType === 'hourly' ? 'Hourly avg' : 'Daily summary'}</span>`
                : ''}
            </div>
            <table class="hist-table">
              <thead>
                <tr>
                  <th>#</th>
                  ${showMovement ? '<th>Movement</th>' : ''}
                  ${showRisk     ? '<th>Level</th>'    : ''}
                  ${showCause    ? '<th>Cause</th>'    : ''}
                  <th>Time</th>
                  ${intervalType !== 'raw' ? '<th>Samples</th>' : ''}
                </tr>
              </thead>
              <tbody>`;

        history.forEach((row, i) => {
          const mvmt  = row.movement_level ?? null;
          const risk  = row.risk || 'low';
          const color = riskColors[risk] || '#27ae60';
          const cause = row.cause || '—';
          const time  = new Date(row.timestamp).toLocaleString('en-US', {
            month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
          });

          html += `
            <tr>
              <td class="hist-num">${i + 1}</td>
              ${showMovement && mvmt !== null ? `
                <td>
                  <div class="hist-bar-wrap">
                    <div class="hist-bar-bg">
                      <div class="hist-bar-fill" style="width:${mvmt}%;background:${color};"></div>
                    </div>
                    <span class="hist-bar-val" style="color:${color};">${mvmt}</span>
                  </div>
                </td>` : (showMovement ? '<td>—</td>' : '')}
              ${showRisk  ? `<td><span class="eco-risk-badge ${risk}">${riskLabels[risk] || risk}</span></td>` : ''}
              ${showCause ? `<td><span class="cause-chip">${cause}</span></td>` : ''}
              <td class="hist-time">${time}</td>
              ${intervalType !== 'raw' ? `<td class="hist-num">${row.sample_count || '—'}</td>` : ''}
            </tr>`;
        });

        html += `</tbody></table></div>`;
      }

      historyContent.innerHTML = html;

      // Draw chart
      const chartCanvas = document.getElementById('historyChart');
      if (chartCanvas && showMovement && typeof Chart !== 'undefined') {
        const reversed     = [...history].reverse();
        const chartLabels  = reversed.map(r =>
          new Date(r.timestamp).toLocaleString('en-US', {
            month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
          })
        );
        const chartData   = reversed.map(r => r.movement_level ?? 0);
        const chartColors = reversed.map(r => riskColors[r.risk] || '#27ae60');

        new Chart(chartCanvas, {
          type: 'line',
          data: {
            labels: chartLabels,
            datasets: [{
              label: 'Movement Level',
              data: chartData,
              borderColor: '#1b9e9b',
              backgroundColor: 'rgba(27,158,155,0.08)',
              borderWidth: 2,
              pointBackgroundColor: chartColors,
              pointRadius: 3,
              tension: 0.35,
              fill: true,
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: ctx => `Movement: ${ctx.parsed.y}/100`
                }
              }
            },
            scales: {
              y: {
                min: 0, max: 100,
                ticks: { font: { size: 10 } },
                grid: { color: '#f0f4f3' }
              },
              x: {
                ticks: { font: { size: 9 }, maxRotation: 45, maxTicksLimit: 12 },
                grid: { display: false }
              }
            }
          }
        });
      }

      // CSV download handler
      const csvBtn = document.getElementById('downloadCsvBtn');
      if (csvBtn) {
        csvBtn.addEventListener('click', function () {
          downloadCSV(history, locationName, fields, data.date_from, data.date_to);
        });
      }
    })
    .catch(err => {
      console.error('[SensorHistory] error:', err);
      if (historyContent) historyContent.innerHTML = '<div class="hist-error">Failed to load sensor history.</div>';
    });
  }

  // ── CSV download ───────────────────────────
  function downloadCSV(history, locationName, fields, dateFrom, dateTo) {
    const showMovement = fields.includes('movement');
    const showRisk     = fields.includes('risk');
    const showCause    = fields.includes('cause');

    const headers = ['#', 'Timestamp'];
    if (showMovement) headers.push('Movement Level');
    if (showRisk)     headers.push('Risk Level');
    if (showCause)    headers.push('Cause');

    const rows = history.map((row, i) => {
      const cols = [i + 1, row.timestamp || ''];
      if (showMovement) cols.push(row.movement_level ?? '');
      if (showRisk)     cols.push(row.risk || '');
      if (showCause)    cols.push(row.cause || '');
      return cols.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',');
    });

    const csv      = [headers.join(','), ...rows].join('\n');
    const slug     = locationName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    const fromSlug = dateFrom ? dateFrom.split('T')[0] : 'start';
    const toSlug   = dateTo   ? dateTo.split('T')[0]   : 'end';
    const filename = `ecoprotean_${slug}_${fromSlug}_to_${toSlug}.csv`;

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ── Auto-notification polling ──────────────
  let knownStatuses = {};

  function pollRequestStatus() {
    if (!isLoggedIn) return;

    fetch(`${apiBase}/sensor_requests.php?action=get_my_requests`, {
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(requests => {
      if (!Array.isArray(requests)) return;

      requests.forEach(req => {
        const prev = knownStatuses[req.request_id];
        if (prev === undefined) {
          knownStatuses[req.request_id] = req.status;
          return;
        }
        if (prev === 'pending' && req.status === 'approved') {
          showNotification(
            `✅ Your request for "${req.location_name}" was approved! Click "My Requests" to view sensor history.`,
            'success'
          );
        }
        if (prev === 'pending' && req.status === 'rejected') {
          showNotification(
            `❌ Your request for "${req.location_name}" was rejected.`,
            'error'
          );
        }
        knownStatuses[req.request_id] = req.status;
      });

      updateRequestBadge(requests);
    })
    .catch(() => {});
  }

  function updateRequestBadge(requests) {
    const badge = document.getElementById('myRequestsBadge');
    if (!badge) return;
    const pending = requests.filter(r => r.status === 'pending').length;
    if (pending > 0) {
      badge.textContent   = pending;
      badge.style.display = 'inline-flex';
    } else {
      badge.style.display = 'none';
    }
  }

  // ── Status bar ─────────────────────────────
  function updateStatusBar(locations) {
    let warning = 0, critical = 0;
    locations.forEach(l => {
      const r = (l.risk || '').toLowerCase();
      if (r === 'medium') warning++;
      if (r === 'high')   critical++;
    });
    const el = id => document.getElementById(id);
    if (el('statTotal'))    el('statTotal').textContent    = `${locations.length} sensor${locations.length !== 1 ? 's' : ''}`;
    if (el('statWarning'))  el('statWarning').textContent  = `${warning} warning`;
    if (el('statCritical')) el('statCritical').textContent = `${critical} critical`;
    if (el('mapStatus'))    el('mapStatus').textContent    = `Updated ${new Date().toLocaleTimeString()}`;
  }

  // ── Place or update marker ─────────────────
  function placeMarker(area) {
    const color = riskColors[area.risk] || '#27ae60';
    const popup = buildPopup(area);

    if (markers[area.id]) {
      markers[area.id].setStyle({ fillColor: color, color: color });
      markers[area.id].setPopupContent(popup);
    } else {
      const marker = L.circleMarker(area.coords, {
        radius: 13, fillColor: color, color: color,
        weight: 3, opacity: 1, fillOpacity: 0.85
      }).addTo(map);
      marker.bindPopup(popup, { maxWidth: 280, className: 'eco-popup-wrapper' });
      markers[area.id] = marker;
    }

    if (area.risk === 'high' && !markers['ring_' + area.id]) {
      markers['ring_' + area.id] = L.circle(area.coords, {
        color: '#e74c3c', fillColor: '#e74c3c',
        fillOpacity: 0.08, radius: 150, weight: 1.5
      }).addTo(map);
    }
  }

  // ── Fetch locations ────────────────────────
  function fetchAndUpdate() {
    fetch(`${apiBase}/locations.php`, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(locations => {
        if (!Array.isArray(locations) || locations.error) {
          console.error('Locations API error:', locations);
          return;
        }
        locations.forEach(area => placeMarker(area));
        updateStatusBar(locations);
      })
      .catch(err => {
        console.error('Failed to load locations:', err);
        const status = document.getElementById('mapStatus');
        if (status) status.textContent = 'Connection error — retrying...';
      });
  }

  // ── Legend ─────────────────────────────────
  function addLegend() {
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
      const div = L.DomUtil.create('div', 'map-legend');
      div.innerHTML = `<h4>Risk Levels</h4>`;
      [
        { key:'high',   label:'High Risk   (≥ 60)' },
        { key:'medium', label:'Medium Risk (30–59)' },
        { key:'low',    label:'Low Risk    (0–29)'  },
      ].forEach(({ key, label }) => {
        div.innerHTML += `
          <div class="legend-row">
            <span class="legend-dot" style="background:${riskColors[key]};"></span>
            <span>${label}</span>
          </div>`;
      });
      return div;
    };
    legend.addTo(map);
  }

  // ── Start ──────────────────────────────────
  fetchAndUpdate();
  addLegend();
  setInterval(fetchAndUpdate, 5000);

  if (isLoggedIn) {
    pollRequestStatus();
    setInterval(pollRequestStatus, 5000);
  }

});