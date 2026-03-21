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
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

  // Restore view after POST
  if (typeof returnView !== 'undefined' && returnView !== 'dashboard') {
    showView(returnView);
  }

  initMaps();
  startLiveUpdates(); // always running regardless of view
});

// ── View switching ─────────────────────────────
const viewMeta = {
  dashboard:       ['Dashboard',       'Tree Monitoring — Live Data'],
  sensors:         ['Sensors',         'Add sensor — click the map to set location'],
  recommendations: ['Recommendations', 'Tree recommendations by location'],
};

function showView(view) {
  Object.keys(viewMeta).forEach(v => {
    document.getElementById('view-' + v).style.display = v === view ? '' : 'none';
    document.getElementById('nav-' + v).classList.toggle('active', v === view);
  });
  const [title, sub] = viewMeta[view] || ['Dashboard',''];
  document.getElementById('pageTitle').textContent = title;
  document.getElementById('pageSub').textContent   = sub;

  // Invalidate maps after view switch so tiles render correctly
  setTimeout(() => {
    if (view === 'dashboard'  && window._dashMap)          window._dashMap.invalidateSize();
    if (view === 'sensors'    && window._sensorPickerMap)  window._sensorPickerMap.invalidateSize();
  }, 120);
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
  if (level < 30)  return 'Wind';
  if (level < 60)  return 'Rain / Soil Softening';
  return 'Ground Instability';
}
function padSensor(id) {
  return 'S' + String(id).padStart(2, '0');
}

// ── Map initialization ─────────────────────────
function initMaps() {

  // ── 1. Dashboard live map ──
  if (document.getElementById('map')) {
    const dashMap = L.map('map', { scrollWheelZoom: false }).setView([8.378, 124.900], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(dashMap);
    window._dashMap     = dashMap;
    window._dashMarkers = {};

    // Show existing locations immediately on load
    if (typeof allLocations !== 'undefined') {
      allLocations.forEach(loc => {
        window._dashMarkers[loc.id] = L.circleMarker([loc.lat, loc.lng], {
          radius: 11, color: '#fff', weight: 2.5,
          fillColor: '#1b9e9b', fillOpacity: 0.85
        }).addTo(dashMap).bindPopup(`<strong>${loc.name}</strong>`);
      });
    }
  }

  // ── 2. Sensor picker map (click to pick location) ──
  if (document.getElementById('sensorPickerMap')) {
    const spMap = L.map('sensorPickerMap', { scrollWheelZoom: false })
      .setView([8.378, 124.900], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(spMap);
    window._sensorPickerMap    = spMap;
    window._sensorPickerMarker = null;

    // Show existing sensor locations as reference
    if (typeof allLocations !== 'undefined') {
      allLocations.forEach(loc => {
        L.circleMarker([loc.lat, loc.lng], {
          radius: 8, color: '#fff', weight: 2,
          fillColor: '#2c5f5d', fillOpacity: 0.75
        }).addTo(spMap)
          .bindPopup(`<strong>${loc.name}</strong><br><small style="color:#888">Existing sensor</small>`);
      });
    }

    // ── Click to pick location ──
    spMap.on('click', function (e) {
      const { lat, lng } = e.latlng;

      // Fill coordinates
      document.getElementById('sensorLat').value = lat.toFixed(8);
      document.getElementById('sensorLng').value = lng.toFixed(8);

      // Move or place marker
      if (window._sensorPickerMarker) {
        window._sensorPickerMarker.setLatLng([lat, lng]);
      } else {
        window._sensorPickerMarker = L.marker([lat, lng]).addTo(spMap);
      }

      // Visual feedback
      document.getElementById('sensorPickerMap').classList.add('picked');
      document.getElementById('mapClickHint').style.display = 'none';

      // Reverse geocode with Nominatim
      reverseGeocode(lat, lng);
    });
  }
}

// ── Reverse geocoding via Nominatim ────────────
function reverseGeocode(lat, lng) {
  const statusEl  = document.getElementById('geocodeStatus');
  const nameInput = document.getElementById('sensorLocName');
  const submitBtn = document.getElementById('addSensorBtn');

  statusEl.style.display = '';
  statusEl.className = 'geocode-status loading';
  statusEl.textContent = '🔍 Fetching location name...';
  submitBtn.disabled = true;

  fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`, {
    headers: { 'Accept-Language': 'en' }
  })
  .then(r => r.json())
  .then(data => {
    const addr    = data.address || {};
    // Build a meaningful name from address parts
    const name =
      addr.village     ||
      addr.suburb      ||
      addr.neighbourhood ||
      addr.town        ||
      addr.city_district ||
      addr.city        ||
      addr.county      ||
      addr.state       ||
      data.display_name?.split(',')[0] ||
      'Unknown Location';

    nameInput.value = name;
    statusEl.className = 'geocode-status done';
    statusEl.textContent = '✅ Location name fetched — you can edit it below.';
    submitBtn.disabled = false;
  })
  .catch(() => {
    nameInput.value = '';
    statusEl.className = 'geocode-status error';
    statusEl.textContent = '⚠ Could not fetch name. Please type it manually.';
    submitBtn.disabled = false;
    nameInput.focus();
  });
}

// ── Load sensors for location (recommendations) ──
function loadSensorsForLocation(locationId) {
  const group = document.getElementById('sensorsForLocGroup');
  const list  = document.getElementById('sensorsForLocList');
  if (!locationId || !sensorsByLocation[locationId]) {
    group.style.display = 'none';
    return;
  }
  const sensors = sensorsByLocation[locationId];
  list.innerHTML = sensors.map(s =>
    `<span class="sensor-chip">${padSensor(s.sensor_id)} — ${s.sensor_type}</span>`
  ).join('');
  group.style.display = '';
}

// ── Live simulation + data fetch ───────────────
function startLiveUpdates() {

  function simulateSensors() {
    return fetch('../api/simulate.php', {
      method: 'POST',
      credentials: 'same-origin'  // sends session cookie so PHP session works
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        console.warn('Simulate warning:', data.message);
      } else {
        console.log('[' + new Date().toLocaleTimeString() + '] Simulated:', data.inserted);
      }
      return data;
    })
    .catch(err => console.error('Simulate failed:', err));
  }

  function fetchAndUpdate() {
    fetch('../api/locations.php', {
      credentials: 'same-origin'  // sends session cookie
    })
    .then(r => r.json())
    .then(locations => {
      if (!Array.isArray(locations) || locations.error) {
        console.error('Locations API error:', locations);
        return;
      }
      console.log('[' + new Date().toLocaleTimeString() + '] Fetched locations:', locations.map(l => l.name + ':' + l.movement_level));
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

  // Run immediately then every 5s
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
      const li = document.createElement('li');
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