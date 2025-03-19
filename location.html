<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Premium Car Rental Centers Map</title>

    <!-- Add Leaflet CSS -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css"
    />
    <!-- Add Leaflet JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>

    <style>
      @import url("https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Syncopate:wght@400;700&display=swap");
      body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        background-color: #f8f8f8;
        color: #333;
      }

      .main-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
      }

      .search-container {
        margin-bottom: 20px;
      }

      .search-box {
        display: flex;
        gap: 10px;
      }

      .search-input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1rem;
      }

      .search-button {
        padding: 10px 20px;
        background-color: #1e90ff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1rem;
      }

      #appContent {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
      }

      #map {
        height: 600px;
        border-radius: 8px;
        border: 1px solid #ccc;
      }

      #centersList {
        height: 600px;
        overflow-y: auto;
      }

      .center-card {
        padding: 15px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        cursor: pointer;
        transition: box-shadow 0.2s;
        background-color: white;
      }

      .center-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      }

      .center-name {
        font-weight: bold;
        margin-bottom: 5px;
        font-size: 1.2rem;
      }

      .center-info {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 3px;
      }

      .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        margin-bottom: 5px;
      }

      .status-available {
        background-color: #e3f2fd;
        color: #1e88e5;
      }

      .status-limited {
        background-color: #fffde7;
        color: #fbc02d;
      }

      .no-results {
        padding: 20px;
        text-align: center;
        color: #666;
      }
    </style>
  </head>
  <body>
    <main class="main-container">
      <div class="search-container">
        <div class="search-box">
          <input
            type="text"
            id="searchInput"
            class="search-input"
            placeholder="Enter location or city"
          />
          <button class="search-button" onclick="displayCenters()">
            Find Centers
          </button>
        </div>
      </div>
      <div id="appContent">
        <div id="map"></div>
        <div id="centersList"></div>
      </div>
    </main>

    <script>
      // Sample data for premium car rental centers
      const centers = [
        {
          id: 1,
          name: "Luxury Drive - Trivandrum",
          city: "Trivandrum",
          operator: "Luxury Drive Inc.",
          status: "Available",
          address: "PMG Junction, Thiruvananthapuram",
          location: [8.4855, 76.9492],
          carTypes: ["Sedan", "SUV"],
        },
        {
          id: 2,
          name: "Elite Wheels - Kochi",
          city: "Kochi",
          operator: "Elite Rentals",
          status: "Limited",
          address: "Marine Drive, Ernakulam",
          location: [9.9312, 76.2673],
          carTypes: ["Convertible", "SUV"],
        },
        {
          id: 3,
          name: "Prestige Motors - Kozhikode",
          city: "Kozhikode",
          operator: "Prestige Motors",
          status: "Available",
          address: "Beach Road, Kozhikode",
          location: [11.2588, 75.7804],
          carTypes: ["Luxury Sedan", "SUV"],
        },
        {
          id: 4,
          name: "Prime Cars - Thrissur",
          city: "Thrissur",
          operator: "Prime Rentals",
          status: "Available",
          address: "M.G. Road, Thrissur",
          location: [10.5276, 76.2144],
          carTypes: ["Convertible", "Sedan"],
        },
      ];

      // Initialize map
      const map = L.map("map").setView([10.8505, 76.2711], 7);

      // Add tile layer (using OpenStreetMap)
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors",
      }).addTo(map);

      // Store markers array for later reference
      let markers = [];

      // Function to create custom marker icon
      function createMarkerIcon(status) {
        return L.divIcon({
          className: "custom-marker",
          html: `<div style="
                    width: 12px;
                    height: 12px;
                    background-color: ${
                      status === "Available" ? "#1E90FF" : "#FBC02D"
                    };
                    border: 2px solid white;
                    border-radius: 50%;
                    box-shadow: 0 0 4px rgba(0,0,0,0.3);
                "></div>`,
          iconSize: [16, 16],
        });
      }

      // Function to display centers
      function displayCenters(centersToShow = centers) {
        // Clear existing markers
        markers.forEach((marker) => map.removeLayer(marker));
        markers = [];

        // Clear existing center cards
        const centersList = document.getElementById("centersList");
        centersList.innerHTML = "";

        if (centersToShow.length === 0) {
          centersList.innerHTML =
            '<div class="no-results">No centers found matching your search.</div>';
          return;
        }

        centersToShow.forEach((center) => {
          // Add marker to map
          const marker = L.marker(center.location, {
            icon: createMarkerIcon(center.status),
          }).addTo(map);

          // Create popup content
          marker.bindPopup(`
                    <strong>${center.name}</strong><br>
                    ${center.address}<br>
                    Operator: ${center.operator}<br>
                    Car Types: ${center.carTypes.join(", ")}
                `);

          markers.push(marker);

          // Create center card
          const card = document.createElement("div");
          card.className = "center-card";
          card.innerHTML = `
                    <div class="status-badge ${
                      center.status === "Available"
                        ? "status-available"
                        : "status-limited"
                    }">${center.status}</div>
                    <div class="center-name">${center.name}</div>
                    <div class="center-info">${center.address}</div>
                    <div class="center-info">Operator: ${center.operator}</div>
                    <div class="center-info">Car Types: ${center.carTypes.join(
                      ", "
                    )}</div>
                `;

          // Add click event to center map on center
          card.addEventListener("click", () => {
            map.setView(center.location, 15);
            marker.openPopup();
          });

          centersList.appendChild(card);
        });

        // Adjust the map to fit all markers
        if (markers.length > 0) {
          const group = L.featureGroup(markers);
          map.fitBounds(group.getBounds());
        }
      }

      // Initialize search functionality
      function initializeSearch() {
        const searchInput = document.getElementById("searchInput");

        searchInput.addEventListener("input", (e) => {
          const searchTerm = e.target.value.toLowerCase();

          const filteredCenters = centers.filter(
            (center) =>
              center.name.toLowerCase().includes(searchTerm) ||
              center.city.toLowerCase().includes(searchTerm) ||
              center.operator.toLowerCase().includes(searchTerm) ||
              center.address.toLowerCase().includes(searchTerm)
          );

          displayCenters(filteredCenters);
        });
      }

      // Initialize the application
      function init() {
        displayCenters();
        initializeSearch();
      }

      // Call init when the page loads
      window.addEventListener("load", init);
    </script>
  </body>
</html>
