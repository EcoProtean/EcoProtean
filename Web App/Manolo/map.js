class LeafletMap {
    constructor(containerId, center, zoom) {
        this.map = L.map(containerId, {
            center: center,
            zoom: zoom,
            zoomControl: false, // Disable default zoom control (we'll reposition it)
            attributionControl: true, // Show attribution
        });
        this.initTileLayer();
        L.control.zoom({ position: 'topright' }).addTo(this.map); // Move zoom control to top right
        this.markers = []; // To store added markers for search functionality
    }

    // Method to initialize tile layer (map design)
    initTileLayer() {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            minZoom: 10,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            id: 'mapbox/streets-v11',
        }).addTo(this.map);
    }

    // Method to add a marker with detailed description and seedling suggestion
    addMarker(lat, lng, message, description, seedlingSuggestion) {
        const popupContent = `
            <div class="custom-popup">
                <h3>${message}</h3>
                <div class="popup-content">
                    <p><strong>Description:</strong> ${description}</p>
                    <p><strong>Tree Seedling Suggestion:</strong> ${seedlingSuggestion}</p>
                </div>
            </div>`;

        const marker = L.marker([lat, lng]).addTo(this.map);
        marker.bindPopup(popupContent, {
            className: 'custom-leaflet-popup' // Custom class for additional CSS styling
        });

        // Store the marker and message for search functionality
        this.markers.push({ message, marker });
    }

    // Method to load markers dynamically from a JSON file
    loadMarkersFromJson(url) {
        fetch(url)
            .then(response => response.json())
            .then(data => {
                data.forEach(marker => {
                    this.addMarker(marker.latitude, marker.longitude, marker.message, marker.description, marker.seedling_suggestion);
                });
            })
            .catch(error => console.error('Error loading markers:', error));
    }

    // Method to search for a marker by message
    searchMarker(searchTerm) {
        const lowerCaseSearchTerm = searchTerm.toLowerCase();
        const foundMarker = this.markers.find(m => m.message.toLowerCase().includes(lowerCaseSearchTerm));

        if (foundMarker) {
            this.map.setView(foundMarker.marker.getLatLng(), 16); // Zoom to the marker's location
            foundMarker.marker.openPopup(); // Open the popup of the marker
        } else {
            alert('No matching area found.');
        }
    }
}

// Initialize map with a broader view of Manolo Fortich
const myMap = new LeafletMap('map', [8.3628, 124.8639], 13); // Centered at Manolo Fortich with wider zoom

// Load markers from JSON (containing more places and suggestions)
myMap.loadMarkersFromJson('map.json');

// Handle search functionality from the form
document.querySelector('form').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    const searchQuery = document.querySelector('input[type="search"]').value;
    myMap.searchMarker(searchQuery); // Search for the marker based on input
});