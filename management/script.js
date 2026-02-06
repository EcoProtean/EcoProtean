window.onload = function() {
const trees = [
  {
    id: "T01",
    lat: 8.341176,
    lng: 124.892993,
    location: "...",
    movement: 0,
    cause: "Stable",
    risk: "Low",
    sensorStatus: "Active",
    batteryLevel: 85
  },
  {
    id: "T02",
    lat: 8.374973,
    lng: 124.902427,
    location: "...",
    movement: 0,
    cause: "Stable",
    risk: "Low",
    sensorStatus: "Active",
    batteryLevel: 90
  },
  {
    id: "T03",
    lat:8.402315,
    lng: 124.899830,
    location: "...",
    movement: 0,
    cause: "Stable",
    risk: "Low",
    sensorStatus: "Active",
    batteryLevel: 89
  }
];

const map = L.map("map").setView([8.378, 124.868], 12);

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: "© OpenStreetMap contributors"
}).addTo(map);

const markers = {};

function getMarkerColor(risk) {
  if (risk === "Low") return "green";
  if (risk === "Medium") return "orange";
  return "red";
}

trees.forEach(tree => {
  const color = getMarkerColor(tree.risk);
  markers[tree.id] = L.circleMarker([tree.lat, tree.lng], {
    radius: 10,
    color: color,
    fillColor: color,
    fillOpacity: 0.8
  }).addTo(map)
    .bindPopup(`<strong>${tree.id}</strong><br>Movement: ${tree.movement} cm<br>Risk: ${tree.risk}`);
});

function simulateSensorData() {
  trees.forEach(tree => {
    // random movement (cm)
    tree.movement = (Math.random() * 10).toFixed(2);

    if (tree.movement < 3) {
      tree.cause = "Wind";
      tree.risk = "Low";
    } else if (tree.movement < 6) {
      tree.cause = "Rain / Soil Softening";
      tree.risk = "Medium";
    } else {
      tree.cause = "Ground Instability";
      tree.risk = "High";
      addAlert(tree);
    }
  });

  updateDashboard();
}

function updateDashboard() {
  const table = document.getElementById("treeTable");
  table.innerHTML = "";

  let atRisk = 0;
  let critical = 0;

  trees.forEach(tree => {
    if (tree.risk !== "Low") atRisk++;
    if (tree.risk === "High") critical++;

    const row = `
      <tr>
        <td>${tree.id}</td>
        <td>${tree.movement} cm</td>
        <td>${tree.cause}</td>
        <td class="${getRiskClass(tree.risk)}">${tree.risk}</td>
      </tr>
    `;
    table.innerHTML += row;
  });

  document.getElementById("atRisk").textContent = atRisk;
  document.getElementById("critical").textContent = critical;
  document.getElementById("lastUpdate").textContent =
    "Last update: " + new Date().toLocaleTimeString();
}

function addAlert(tree) {
  const alertList = document.getElementById("alerts");
  const alert = document.createElement("li");

  alert.textContent = `${tree.id} – High Risk detected (${tree.cause}) at ${new Date().toLocaleTimeString()}`;
  alertList.prepend(alert);
}

function getRiskClass(risk) {
  if (risk === "Low") return "risk-low";
  if (risk === "Medium") return "risk-medium";
  return "risk-high";
}

setInterval(simulateSensorData, 5000);
simulateSensorData();

};