// ─────────────────────────────────────────────
//  EcoProtean - Manager Dashboard Script
//  Fetches latest sensor readings from the DB
//  every 5 seconds and updates the map + table.
//  Risk level is calculated by SQL (not JS).
// ─────────────────────────────────────────────

window.onload = function () {

  // ── Map setup ──
  const map = L.map('map').setView([8.378, 124.900], 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  const markers = {}; // stores marker references by location id

  function getRiskColor(risk) {
    if (!risk) return 'gray';
    switch (risk.toLowerCase()) {
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

  // ── Fetch latest data from DB and update everything ──
  function fetchAndUpdate() {
    fetch('../../api/locations.php')
      .then(res => {
        if (!res.ok) throw new Error('Network error');
        return res.json();
      })
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
      .catch(err => console.error('Failed to fetch locations:', err));
  }

  // ── Update map pins ──
  function updateMap(locations) {
    locations.forEach(loc => {
      const color = getRiskColor(loc.risk);
      const movement = loc.movement_level !== null
        ? loc.movement_level + '/100'
        : 'No data yet';

      const popupText = `
        <strong>${loc.name}</strong><br>
        Movement: ${movement}<br>
        Risk: <span style="color:${color};font-weight:bold;">${(loc.risk || 'unknown').toUpperCase()}</span>
      `;

      if (markers[loc.id]) {
        // Update existing marker
        markers[loc.id].setStyle({ color, fillColor: color });
        markers[loc.id].setPopupContent(popupText);
      } else {
        // Create new marker
        markers[loc.id] = L.circleMarker(loc.coords, {
          radius:      10,
          color:       color,
          fillColor:   color,
          fillOpacity: 0.8
        }).addTo(map).bindPopup(popupText);
      }
    });
  }

  // ── Update monitoring table ──
  function updateTable(locations) {
    const table = document.getElementById('treeTable');
    table.innerHTML = '';

    locations.forEach(loc => {
      const movement = loc.movement_level !== null
        ? loc.movement_level + '/100'
        : '—';

      let cause = '—';
      if (loc.movement_level !== null) {
        if (loc.movement_level < 30)      cause = 'Wind';
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

  // ── Update KPI cards ──
  function updateKPI(locations) {
    let atRisk   = 0;
    let critical = 0;

    locations.forEach(loc => {
      if ((loc.risk || '').toLowerCase() !== 'low') atRisk++;
      if ((loc.risk || '').toLowerCase() === 'high') critical++;
    });

    document.getElementById('totalTrees').textContent = locations.length;
    document.getElementById('atRisk').textContent     = atRisk;
    document.getElementById('critical').textContent   = critical;
  }

  // ── Update alerts list (high risk only) ──
  function updateAlerts(locations) {
    const alertList = document.getElementById('alerts');
    const highRisk  = locations.filter(l => (l.risk || '').toLowerCase() === 'high');

    if (highRisk.length === 0) return;

    highRisk.forEach(loc => {
      const existingIds = [...alertList.querySelectorAll('.alert-item')]
        .map(el => el.dataset.id);

      if (!existingIds.includes(String(loc.id))) {
        const li = document.createElement('li');
        li.className  = 'alert-item';
        li.dataset.id = loc.id;
        li.innerHTML  = `
          <strong>S${String(loc.sensor_id).padStart(2, '0')} – ${loc.name}</strong>
          — High Risk detected (Ground Instability)
          <span class="alert-time">${new Date().toLocaleTimeString()}</span>
        `;
        alertList.prepend(li);

        const placeholder = alertList.querySelector('.no-alerts');
        if (placeholder) placeholder.remove();
      }
    });
  }

  // ── Initial load + poll every 5 seconds ──
  fetchAndUpdate();
  setInterval(fetchAndUpdate, 5000);

};