
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Location on Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .map-container {
            width: 100%;
            max-width: 800px;
            height: 500px;
            margin: 20px auto;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info {
            margin: 20px auto;
            max-width: 800px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info p {
            margin: 10px 0;
        }
        .info span {
            color: #666;
        }
        .button-container {
            margin-top: 20px;
        }
        .confirm-btn {
            background: #f5b754;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .confirm-btn:hover {
            background: #e4a643;
        }
        .confirm-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <h1>Select Location on Map</h1>
    <p>Click anywhere on the map to set your location.</p>

    <div id="map" class="map-container"></div>

    <div class="info">
        <p><strong>Latitude:</strong> <span id="lat">Not selected</span></p>
        <p><strong>Longitude:</strong> <span id="lng">Not selected</span></p>
        <p><strong>Address:</strong> <span id="address">Not selected</span></p>
        <div class="button-container">
            <button id="confirmBtn" class="confirm-btn" disabled>Confirm Location</button>
        </div>
    </div>

    <script>
        let map = L.map('map').setView([20.5937, 78.9629], 5); // Default: India
        let locationMarker;
        const confirmBtn = document.getElementById('confirmBtn');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        function setLocation(e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);

            if (locationMarker) {
                map.removeLayer(locationMarker);
            }

            locationMarker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`Selected Location<br>Lat: ${lat}, Lng: ${lng}`)
                .openPopup();

            document.getElementById("lat").textContent = lat;
            document.getElementById("lng").textContent = lng;
            confirmBtn.disabled = false;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    let address = data.display_name || "Address not found";
                    document.getElementById("address").textContent = address;
                })
                .catch(() => document.getElementById("address").textContent = "Unable to fetch address");
        }

        map.on('click', setLocation);

        confirmBtn.addEventListener('click', function() {
            const lat = document.getElementById("lat").textContent;
            const lng = document.getElementById("lng").textContent;
            const address = document.getElementById("address").textContent;

            if (lat !== "Not selected" && lng !== "Not selected") {
                window.opener.setPickupLocation(address, lat, lng);
                window.close();
            }
        });
    </script>
</body>
</html>
