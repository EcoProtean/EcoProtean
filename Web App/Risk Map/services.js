// Wait for DOM to fully load
document.addEventListener('DOMContentLoaded', function() {
  
  // Check if map container exists
  const mapContainer = document.getElementById('map');
  if (!mapContainer) {
    console.error('Map container not found!');
    return;
  }

  // Initialize map centered on Manolo Fortich, Northern Mindanao, Philippines
  const map = L.map('map').setView([8.3644, 124.8669], 13);

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  // Risk areas data (example coordinates for Manolo Fortich area)
  const riskAreas = [
    {
      name: "High Risk Area - Mountain Slope",
      coords: [8.379887176344212, 124.88201302507801],
      risk: "high",
      description: "Steep slope with high landslide risk. Recommend planting deep-rooted trees."
    },
    {
      name: "Medium Risk Area - Hill Region",
      coords: [8.371139623708695, 124.8781215887567],
      risk: "medium",
      description: "Moderate slope. Plant native trees for soil stabilization."
    },
    {
      name: "Low Risk Area - Gentle Slope",
      coords: [8.37668277773737, 124.85904276007969],
      risk: "low",
      description: "Low landslide risk. Suitable for various tree species."
    },
    {
      name: "Safe Area - Flatland",
      coords: [8.38195567260636, 124.84190196303977],
      risk: "safe",
      description: "Minimal landslide risk. Ideal for agriculture and reforestation."
    }
  ];

  // Risk color mapping
  const riskColors = {
    high: '#e74c3c',
    medium: '#f39c12',
    low: '#f1c40f',
    safe: '#2ecc71'
  };

  // Add markers for each risk area
  riskAreas.forEach(area => {
    const marker = L.circleMarker(area.coords, {
      radius: 12,
      fillColor: riskColors[area.risk],
      color: '#fff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.7
    }).addTo(map);

    // Add popup with information
    marker.bindPopup(`
      <div style="min-width: 200px;">
        <h3 style="color: #2c5f5d; margin-bottom: 8px;">${area.name}</h3>
        <p style="margin: 5px 0;"><strong>Risk Level:</strong> ${area.risk.toUpperCase()}</p>
        <p style="margin: 5px 0;">${area.description}</p>
      </div>
    `);
  });

  // Add a legend
  const legend = L.control({ position: 'bottomright' });

  legend.onAdd = function (map) {
    const div = L.DomUtil.create('div', 'info legend');
    div.style.background = 'white';
    div.style.padding = '15px';
    div.style.borderRadius = '8px';
    div.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
    div.style.fontFamily = 'Poppins, sans-serif';
    
    const risks = ['high', 'medium', 'low', 'safe'];
    const labels = ['High Risk', 'Medium Risk', 'Low Risk', 'Safe'];
    
    div.innerHTML = '<h4 style="margin: 0 0 10px 0; color: #2c5f5d; font-size: 14px;">Risk Levels</h4>';
    
    for (let i = 0; i < risks.length; i++) {
      div.innerHTML +=
        '<div style="margin: 5px 0; font-size: 13px;">' +
        '<span style="display: inline-block; width: 20px; height: 20px; background:' + 
        riskColors[risks[i]] + '; border-radius: 50%; margin-right: 8px; vertical-align: middle;"></span> ' +
        '<span style="vertical-align: middle;">' + labels[i] + '</span>' +
        '</div>';
    }
    
    return div;
  };

  legend.addTo(map);

  console.log('Risk Map loaded successfully with', riskAreas.length, 'risk areas');
  
});
