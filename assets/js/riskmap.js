// ─────────────────────────────────────────────
//  EcoProtean — Risk Map (User Side)
//  Fetches live sensor data from API every 10s
//  and updates map pins, popups, and status bar
// ─────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

  const mapContainer = document.getElementById('map');
  if (!mapContainer) {
    console.error('Map container not found!');
    return;
  }

  // ── Initialize map ─────────────────────────
  const map = L.map('map', { zoomControl: true }).setView([8.3644, 124.8669], 13);
  window._riskmapLeaflet = map;

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  // ── Risk color map ─────────────────────────
  // Matches exactly what admin and manager use
  const riskColors = {
    high:   '#e74c3c',  // red
    medium: '#e67e22',  // orange
    low:    '#27ae60'   // green — matches admin/manager
  };

  // ── Risk label map ─────────────────────────
  const riskLabels = {
    high:   'High Risk',
    medium: 'Medium Risk',
    low:    'Low Risk'
  };

  // Markers stored by location id for live updates
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
        <div class="eco-popup-body">
          <p class="eco-popup-desc">${area.description || ''}</p>
          <div id="rec-${area.id}" class="eco-popup-recs">
            <em>Loading recommendations…</em>
          </div>
        </div>
        <div class="eco-popup-footer">
          <button class="eco-btn-sensor">🔬 Request Sensor Data</button>
        </div>
      </div>`;
  }

  // ── Load tree recommendations on popup open ─
  function loadRecommendations(areaId) {
    fetch(`../../api/recommendations.php?location_id=${areaId}`, {
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(recs => {
      const el = document.getElementById(`rec-${areaId}`);
      if (!el) return;
      if (!recs || recs.length === 0) {
        el.innerHTML = '<em>No tree recommendations yet.</em>';
        return;
      }
      let html = `<div class="eco-popup-recs-title">🌱 Recommended Trees</div>`;
      recs.forEach(r => {
        html += `<div class="eco-rec-item">
          <strong>${r.tree_name}</strong> — ${r.reason}
        </div>`;
      });
      el.innerHTML = html;
    })
    .catch(() => {
      const el = document.getElementById(`rec-${areaId}`);
      if (el) el.innerHTML = '<em style="color:#c0392b;">Could not load recommendations.</em>';
    });
  }

  // ── Update status bar ──────────────────────
  function updateStatusBar(locations) {
    let warning = 0, critical = 0;
    locations.forEach(l => {
      const r = (l.risk || '').toLowerCase();
      if (r === 'medium') warning++;
      if (r === 'high')   critical++;
    });

    const total  = document.getElementById('statTotal');
    const warn   = document.getElementById('statWarning');
    const crit   = document.getElementById('statCritical');
    const status = document.getElementById('mapStatus');

    if (total)  total.textContent  = `${locations.length} sensor${locations.length !== 1 ? 's' : ''}`;
    if (warn)   warn.textContent   = `${warning} warning`;
    if (crit)   crit.textContent   = `${critical} critical`;
    if (status) status.textContent = `Updated ${new Date().toLocaleTimeString()}`;
  }

  // ── Place or update a marker ───────────────
  function placeMarker(area) {
    const color = riskColors[area.risk] || '#27ae60';
    const popup = buildPopup(area);

    if (markers[area.id]) {
      markers[area.id].setStyle({ fillColor: color, color: color });
      markers[area.id].setPopupContent(popup);
    } else {
      const marker = L.circleMarker(area.coords, {
        radius:      13,
        fillColor:   color,
        color:       color,
        weight:      3,
        opacity:     1,
        fillOpacity: 0.85
      }).addTo(map);

      marker.bindPopup(popup, { maxWidth: 280, className: 'eco-popup-wrapper' });
      marker.on('popupopen', () => loadRecommendations(area.id));
      markers[area.id] = marker;
    }

    // Pulse ring for critical areas
    if (area.risk === 'high' && !markers['ring_' + area.id]) {
      markers['ring_' + area.id] = L.circle(area.coords, {
        color:       '#e74c3c',
        fillColor:   '#e74c3c',
        fillOpacity: 0.08,
        radius:      150,
        weight:      1.5
      }).addTo(map);
    }
  }

  // ── Fetch locations + update map ───────────
  // User just reads — simulation is handled
  // server-side only when manager fetches
  function fetchAndUpdate() {
    fetch('../../api/locations.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(locations => {
        if (!Array.isArray(locations) || locations.error) {
          console.error('Locations API error:', locations);
          return;
        }
        locations.forEach(area => placeMarker(area));
        updateStatusBar(locations);
        console.log('[RiskMap] Updated:', locations.length, 'locations at', new Date().toLocaleTimeString());
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
        { key:'high',   label:'High Risk'   },
        { key:'medium', label:'Medium Risk'  },
        { key:'low',    label:'Low Risk'     },
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

  // Refresh every 2s — simulation inserts every 5s
  // so polling at 2s means user sees new data almost instantly
  setInterval(fetchAndUpdate, 2000);

});