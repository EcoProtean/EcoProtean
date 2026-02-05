const trees = [
  {
    id: "T01",
    lat: 8.341176,
    lng: 124.892993,
    movement: 0,
    cause: "Stable",
    risk: "Low",
  },
  {
    id: "T02",
    lat: 8.374973,
    lng: 124.902427,
    movement: 0,
    cause: "Stable",
    risk: "Low",
  },
  {
    id: "T03",
    lat:8.402315,
    lng: 124.899830,
    movement: 0,
    cause: "Stable",
    risk: "Low",
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
