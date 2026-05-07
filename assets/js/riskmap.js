// ─────────────────────────────────────────────
//  EcoProtean — Risk Map
//  Works for both public (index.php) and
//  authenticated (webapp/riskmap/index.php)
//  Guest clicks "Request Sensor Data" → modal
//  Logged-in → button works normally
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
        <div class="eco-popup-footer">
          <button class="eco-btn-sensor" data-area-id="${area.id}">🔬 Request Sensor Data</button>
        </div>
      </div>`;
  }


  // ── Modal logic ────────────────────────────
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
    loginModal.addEventListener('click', function (e) {
      if (e.target === loginModal) hideLoginModal();
    });
  }

  // ── Sensor button handler (delegated) ──────
  // Using event delegation on the map container
  // because popups are re-created on each update
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('eco-btn-sensor')) return;
    if (!isLoggedIn) {
      showLoginModal();
    } else {
      const areaId = e.target.getAttribute('data-area-id');
      // TODO: your existing sensor data request logic here
      console.log('[RiskMap] Requesting sensor data for area:', areaId);
    }
  });

  // ── Update status bar ──────────────────────
  function updateStatusBar(locations) {
    let warning = 0, critical = 0;
    locations.forEach(l => {
      const r = (l.risk || '').toLowerCase();
      if (r === 'medium') warning++;
      if (r === 'high')   critical++;
    });

    const el = (id) => document.getElementById(id);
    if (el('statTotal'))    el('statTotal').textContent    = `${locations.length} sensor${locations.length !== 1 ? 's' : ''}`;
    if (el('statWarning'))  el('statWarning').textContent  = `${warning} warning`;
    if (el('statCritical')) el('statCritical').textContent = `${critical} critical`;
    if (el('mapStatus'))    el('mapStatus').textContent    = `Updated ${new Date().toLocaleTimeString()}`;
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

    // Pulse ring for high risk areas
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
        { key: 'high',   label: 'High Risk'   },
        { key: 'medium', label: 'Medium Risk'  },
        { key: 'low',    label: 'Low Risk'     },
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

});