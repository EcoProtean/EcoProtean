// ─────────────────────────────────────────────
//  EcoProtean - Manager Dashboard Script
//  Uses sensor data injected from PHP (dbSensors)
//  Simulates live readings and saves to DB via API
// ─────────────────────────────────────────────

window.onload = function () {

  // Build working tree list from DB sensor data
  const trees = dbSensors.map(s => ({
    id:        'S' + String(s.sensor_id).padStart(2, '0'),
    sensor_id: parseInt(s.sensor_id),
    lat:       parseFloat(s.latitude),
    lng:       parseFloat(s.longitude),
    location:  s.location_name,
    movement:  parseFloat(s.last_movement) || 0,
    cause:     'Stable',
    risk:      s.risk_level || 'Low',
    dbRisk:    s.risk_level
  }));

  // ── Map setup ──
  const map = L.map('map').setView([8.378, 124.868], 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  const markers = {};

  function getMarkerColor(risk) {
    if (!risk) return 'gray';
    const r = risk.toLowerCase();
    if (r === 'low')    return 'green';
    if (r === 'medium') return 'orange';
    return 'red';
  }

  // Place markers on map
  trees.forEach(tree => {
    const color = getMarkerColor(tree.risk);
    markers[tree.id] = L.circleMarker([tree.lat, tree.lng], {
      radius:      10,
      color:       color,
      fillColor:   color,
      fillOpacity: 0.8
    }).addTo(map)
      .bindPopup(`<strong>${tree.id}</strong><br>${tree.location}<br>Movement: ${tree.movement}/100<br>Risk: ${tree.risk}`);
  });

  // ── Simulate sensor readings ──
  function simulateSensorData() {
    trees.forEach(tree => {
      // Simulate movement on 0–100 scale (matching DB movement_level 0–100)
      tree.movement = Math.floor(Math.random() * 100);

      if (tree.movement < 30) {
        tree.cause = 'Wind';
        tree.risk  = 'Low';
      } else if (tree.movement < 60) {
        tree.cause = 'Rain / Soil Softening';
        tree.risk  = 'Medium';
      } else {
        tree.cause = 'Ground Instability';
        tree.risk  = 'High';
        addAlert(tree);
      }

      // Update marker color live
      const color = getMarkerColor(tree.risk);
      markers[tree.id].setStyle({ color, fillColor: color });
      markers[tree.id].setPopupContent(
        `<strong>${tree.id}</strong><br>${tree.location}<br>Movement: ${tree.movement}/100<br>Risk: ${tree.risk}`
      );

      // Save reading to database
      saveReading(tree.sensor_id, tree.movement);
    });

    updateDashboard();
  }

  // ── Save simulation reading to DB via API ──
  function saveReading(sensor_id, movement_level) {
    fetch('../api/save_reading.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ sensor_id, movement_level })
    }).catch(err => console.warn('Could not save reading:', err));
  }

  // ── Update table and KPI cards ──
  function updateDashboard() {
    const table = document.getElementById('treeTable');
    table.innerHTML = '';

    let atRisk   = 0;
    let critical = 0;

    trees.forEach(tree => {
      if (tree.risk !== 'Low')   atRisk++;
      if (tree.risk === 'High')  critical++;

      table.innerHTML += `
        <tr>
          <td>${tree.id}</td>
          <td>${tree.location}</td>
          <td>${tree.movement}/100</td>
          <td>${tree.cause}</td>
          <td class="${getRiskClass(tree.risk)}">${tree.risk}</td>
        </tr>
      `;
    });

    document.getElementById('atRisk').textContent   = atRisk;
    document.getElementById('critical').textContent = critical;
    document.getElementById('lastUpdate').textContent =
      'Last update: ' + new Date().toLocaleTimeString();
  }

  // ── Add alert to list ──
  function addAlert(tree) {
    const alertList = document.getElementById('alerts');

    // Remove "no alerts" placeholder if present
    const placeholder = alertList.querySelector('.no-alerts');
    if (placeholder) placeholder.remove();

    const li = document.createElement('li');
    li.className = 'alert-item';
    li.innerHTML = `
      <strong>${tree.id} – ${tree.location}</strong>
      — High Risk detected (${tree.cause})
      <span class="alert-time">${new Date().toLocaleTimeString()}</span>
    `;
    alertList.prepend(li);
  }

  function getRiskClass(risk) {
    if (risk === 'Low')    return 'risk-low';
    if (risk === 'Medium') return 'risk-medium';
    return 'risk-high';
  }

  // ── Start simulation ──
  simulateSensorData();
  setInterval(simulateSensorData, 5000);
};
