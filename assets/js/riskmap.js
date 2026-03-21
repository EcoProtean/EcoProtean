// ─────────────────────────────────────────────
//  EcoProtean - Risk Map Services
//  Fetches locations from the database via API
// ─────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

  const mapContainer = document.getElementById('map');
  if (!mapContainer) {
    console.error('Map container not found!');
    return;
  }

  // Initialize map centered on Manolo Fortich, Northern Mindanao, Philippines
  const map = L.map('map').setView([8.3644, 124.8669], 13);
  window._riskmapLeaflet = map;

  // OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  // Risk color mapping
  const riskColors = {
    high:   '#e74c3c',
    medium: '#f39c12',
    low:    '#f1c40f',
    safe:   '#2ecc71'
  };

  // ── Fetch locations from the database ──
  fetch('../../api/locations.php')
    .then(response => {
      if (!response.ok) throw new Error('Network response was not ok');
      return response.json();
    })
    .then(locations => {
      if (locations.error) {
        console.error('API error:', locations.message);
        return;
      }

      locations.forEach(area => {
        const color = riskColors[area.risk] || '#888';

        const marker = L.circleMarker(area.coords, {
          radius:      12,
          fillColor:   color,
          color:       '#fff',
          weight:      2,
          opacity:     1,
          fillOpacity: 0.7
        }).addTo(map);

        //  added the button for requesting a data on that specific sensor ──
        const popupContent = `
          <div style="min-width:220px; font-family:'Poppins',sans-serif;">
            <h3 style="color:#2c5f5d; margin-bottom:8px; font-size:15px;">${area.name}</h3>
            <p style="margin:5px 0;"><strong>Risk Level:</strong>
              <span style="color:${color}; font-weight:700;">${area.risk.toUpperCase()}</span>
            </p>
            <p style="margin:5px 0; color:#555;">${area.description}</p>
            <hr style="margin:10px 0; border-color:#eee;">
            <div id="rec-${area.id}" style="font-size:13px; color:#777;">
              <em>Loading recommendations…</em>
            </div>
            <hr style="margin:10px 0; border-color:#eee;">

            <button
              style="
                width: 100%;
                padding: 9px 0;
                background: #2c5f5d;
                color: #fff;
                border: none;
                border-radius: 5px;
                font-family: 'Poppins', sans-serif;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.2s;
              "
              onmouseover="this.style.background='#1b9e9b'"
              onmouseout="this.style.background='#2c5f5d'"
            >
              🔬 Request Sensor Data
            </button>

          </div>
        `;

        marker.bindPopup(popupContent, { maxWidth: 280 });

        // Load tree recommendations when popup opens
        marker.on('popupopen', () => {
          fetch(`../../api/recommendations.php?location_id=${area.id}`)
            .then(r => r.json())
            .then(recs => {
              const el = document.getElementById(`rec-${area.id}`);
              if (!el) return;
              if (recs.length === 0) {
                el.innerHTML = '<em>No tree recommendations yet.</em>';
              } else {
                let html = '<strong style="color:#2c5f5d;">🌱 Tree Recommendations</strong><ul style="margin:6px 0 0 15px;">';
                recs.forEach(r => {
                  html += `<li style="margin-bottom:4px;">
                    <strong>${r.tree_name}</strong> — ${r.reason}
                  </li>`;
                });
                html += '</ul>';
                el.innerHTML = html;
              }
            })
            .catch(() => {
              const el = document.getElementById(`rec-${area.id}`);
              if (el) el.innerHTML = '<em style="color:#c0392b;">Could not load recommendations.</em>';
            });
        });
      });

      console.log('Risk Map loaded with', locations.length, 'locations from database.');
      addLegend();
    })
    .catch(err => {
      console.error('Failed to load locations:', err);
    });

  // ── Legend ──
  function addLegend() {
    const legend = L.control({ position: 'bottomright' });

    legend.onAdd = function () {
      const div = L.DomUtil.create('div', 'info legend');
      div.style.cssText = 'background:white;padding:15px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.2);font-family:Poppins,sans-serif;';

      const levels = [
        { key: 'high',   label: 'High Risk' },
        { key: 'medium', label: 'Medium Risk' },
        { key: 'low',    label: 'Low Risk' },
        { key: 'safe',   label: 'Safe' },
      ];

      div.innerHTML = '<h4 style="margin:0 0 10px 0;color:#2c5f5d;font-size:14px;">Risk Levels</h4>';
      levels.forEach(({ key, label }) => {
        div.innerHTML += `
          <div style="margin:5px 0;font-size:13px;">
            <span style="display:inline-block;width:16px;height:16px;background:${riskColors[key]};
              border-radius:50%;margin-right:8px;vertical-align:middle;"></span>
            <span style="vertical-align:middle;">${label}</span>
          </div>`;
      });
      return div;
    };

    legend.addTo(map);
  }
});