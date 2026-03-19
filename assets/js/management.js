// ─────────────────────────────────────────────
//  EcoProtean - Manager Dashboard Script
//
//  Every 5 seconds:
//  1. POST to simulate.php  → inserts random
//     movement into simulation_data (mimics gyro)
//  2. GET  from locations.php → SQL calculates
//     risk from latest reading → updates map
//
//  When real gyro sensors are ready, just remove
//  the simulateSensors() call — everything else
//  stays the same.
// ─────────────────────────────────────────────

    // ── Sidebar mobile toggle ──
    // Add this at the top of admin.js AND management.js

    (function () {
      const toggle   = document.getElementById('menuToggle');
      const sidebar  = document.querySelector('.sidebar');
      const overlay  = document.getElementById('sidebarOverlay');

      if (!toggle || !sidebar || !overlay) return;

      toggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
      });

      overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      });
    })();
window.onload = function () {

  // ── Map setup ──
  const map = L.map('map').setView([8.378, 124.900], 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  const markers = {};

  function getRiskColor(risk) {
    switch ((risk || '').toLowerCase()) {
      case 'low':    return 'green';
      case 'medium': return 'orange';
      case 'high':   return 'red';
      default:       return 'gray';
    }
  }

  function getRiskClass(risk) {
    switch ((risk || '').toLowerCase()) {
      case 'low':    return 'risk-low';
      case 'medium': return 'risk-medium';
      case 'high':   return 'risk-high';
      default:       return '';
    }
  }

  // ── STEP 1: Simulate gyro sensor readings ──
  // Inserts a new random movement_level row for
  // each sensor into simulation_data.
  // Replace/remove this when using real sensors.
  function simulateSensors() {
    return fetch('../api/simulate.php', { method: 'POST' })
      .then(res => res.json())
      .then(data => {
        if (data.error) console.error('Simulate error:', data.message);
      })
      .catch(err => console.error('Simulate failed:', err));
  }

  // ── STEP 2: Fetch latest data from DB ──
  // SQL calculates risk from the latest
  // simulation_data row per sensor.
  function fetchAndUpdate() {
    fetch('../api/locations.php')
      .then(res => res.json())
      .then(locations => {
        if (locations.error) {
          console.error('API error:', locations.message);
          return;
        }
        updateMap(locations);
        updateTable(locations);
        updateKPI(locations);
        updateAlerts(locations);

        document.getElementById('lastUpdate').textContent =
          'Last update: ' + new Date().toLocaleTimeString();
      })
      .catch(err => console.error('Fetch failed:', err));
  }

  // ── Run both steps together ──
  function tick() {
    simulateSensors().then(() => fetchAndUpdate());
  }

  // ── Map pins ──
  function updateMap(locations) {
    locations.forEach(loc => {
      const color    = getRiskColor(loc.risk);
      const movement = loc.movement_level !== null ? loc.movement_level + '/100' : 'No data yet';

      const popupText = `
        <strong>${loc.name}</strong><br>
        Movement: ${movement}<br>
        Risk: <span style="color:${color};font-weight:bold;">
          ${(loc.risk || 'unknown').toUpperCase()}
        </span>
      `;

      if (markers[loc.id]) {
        markers[loc.id].setStyle({ color, fillColor: color });
        markers[loc.id].setPopupContent(popupText);
      } else {
        markers[loc.id] = L.circleMarker(loc.coords, {
          radius: 10, color, fillColor: color, fillOpacity: 0.8
        }).addTo(map).bindPopup(popupText);
      }
    });
  }

  // ── Table ──
  function updateTable(locations) {
    const table = document.getElementById('treeTable');
    table.innerHTML = '';

    locations.forEach(loc => {
      const movement = loc.movement_level !== null ? loc.movement_level + '/100' : '—';

      let cause = '—';
      if (loc.movement_level !== null) {
        if      (loc.movement_level < 30) cause = 'Wind';
        else if (loc.movement_level < 60) cause = 'Rain / Soil Softening';
        else                              cause = 'Ground Instability';
      }

      table.innerHTML += `
        <tr>
          <td>S${String(loc.sensor_id).padStart(2, '0')}</td>
          <td>${loc.name}</td>
          <td>${movement}</td>
          <td>${cause}</td>
          <td class="${getRiskClass(loc.risk)}">${(loc.risk || '—').toUpperCase()}</td>
        </tr>
      `;
    });
  }

  // ── KPI cards ──
  function updateKPI(locations) {
    let atRisk = 0, critical = 0;
    locations.forEach(loc => {
      if ((loc.risk || '').toLowerCase() !== 'low')   atRisk++;
      if ((loc.risk || '').toLowerCase() === 'high') critical++;
    });
    document.getElementById('totalTrees').textContent = locations.length;
    document.getElementById('atRisk').textContent     = atRisk;
    document.getElementById('critical').textContent   = critical;
  }

  // ── Alerts (high risk only) ──
  function updateAlerts(locations) {
    const alertList = document.getElementById('alerts');
    const highRisk  = locations.filter(l => (l.risk || '').toLowerCase() === 'high');

    highRisk.forEach(loc => {
      const existingIds = [...alertList.querySelectorAll('.alert-item')]
        .map(el => el.dataset.id);

      if (!existingIds.includes(String(loc.id))) {
        const placeholder = alertList.querySelector('.no-alerts');
        if (placeholder) placeholder.remove();

        const li = document.createElement('li');
        li.className  = 'alert-item';
        li.dataset.id = loc.id;
        li.innerHTML  = `
          <strong>S${String(loc.sensor_id).padStart(2, '0')} – ${loc.name}</strong>
          — High Risk detected (Ground Instability)
          <span class="alert-time">${new Date().toLocaleTimeString()}</span>
        `;
        alertList.prepend(li);
      }
    });
  }

  // ── Start: run immediately then every 5s ──
  tick();
  setInterval(tick, 5000);
};