<?php
/**
 * Google Maps Integration for Crime Mapping Module
 * Optional file - provides Google Maps alternative to Leaflet
 * 
 * To use Google Maps instead of Leaflet:
 * 1. Get a Google Maps API key from https://console.cloud.google.com/
 * 2. Update the api_key variable below
 * 3. Include this file in crime_mapping.php by replacing Leaflet with Google Maps code
 * 
 * Configuration:
 * - Replace Leaflet links in crime_mapping.php with Google Maps scripts
 * - Use the JavaScript functions below
 */

/**
 * Configuration for Google Maps Integration
 */
define('GOOGLE_MAPS_API_KEY', 'YOUR_API_KEY_HERE');

/**
 * HTML/CSS for Google Maps Integration (replaces Leaflet section)
 * 
 * Include in crime_mapping.php header section:
 * 
 * <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=visualization"></script>
 * 
 * Replace the heatmap container with:
 * <div id="googleMap" style="height: 550px; border-radius: 8px; overflow: hidden;"></div>
 */

/**
 * JavaScript for Google Maps Integration
 * Add this to the script section in crime_mapping.php
 */
$googlemaps_js = <<<'GMJS'
// ===== GOOGLE MAPS INTEGRATION =====
let googleMap = null;
let heatmapLayer = null;
let markerCluster = null;
let criminalMarkers = [];
let allIncidentsGM = [];

function initGoogleMap() {
    // Default location (Manila, Philippines)
    const defaultCenter = { lat: 14.5995, lng: 120.9842 };
    
    // Create map
    googleMap = new google.maps.Map(document.getElementById('googleMap'), {
        zoom: 13,
        center: defaultCenter,
        mapTypeId: 'roadmap',
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });
    
    // Load incident data
    loadGoogleMapData();
}

function loadGoogleMapData() {
    const crimeFilter = document.getElementById('mapCrimeFilter')?.value || '';
    const params = new URLSearchParams();
    if (crimeFilter) params.append('incident_type', crimeFilter);
    
    fetch(`crime_mapping.php?action=get_incident_data&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allIncidentsGM = data.data;
                updateGoogleMapDisplay();
            }
        })
        .catch(error => console.error('Error loading map data:', error));
}

function updateGoogleMapDisplay() {
    // Clear existing markers
    criminalMarkers.forEach(marker => marker.setMap(null));
    criminalMarkers = [];
    
    if (!allIncidentsGM || allIncidentsGM.length === 0) {
        console.warn('No incidents to display');
        return;
    }
    
    // Prepare heatmap data
    const heatData = allIncidentsGM
        .filter(incident => incident.latitude && incident.longitude)
        .map(incident => ({
            location: new google.maps.LatLng(incident.latitude, incident.longitude),
            weight: incident.urgency === 'Critical' ? 1.0 : 
                   incident.urgency === 'High' ? 0.7 : 
                   incident.urgency === 'Medium' ? 0.4 : 0.2
        }));
    
    // Add heatmap layer
    if (heatData.length > 0 && showHeatmap) {
        if (heatmapLayer) heatmapLayer.setMap(null);
        heatmapLayer = new google.maps.visualization.HeatmapLayer({
            data: heatData,
            map: googleMap,
            gradient: [
                'rgba(0, 255, 0, 0)',
                'rgba(255, 255, 0, 1)',
                'rgba(255, 165, 0, 1)',
                'rgba(255, 0, 0, 1)',
                'rgba(139, 0, 0, 1)'
            ],
            maxIntensity: 1.0,
            radius: 30
        });
    }
    
    // Add incident markers
    if (showMarkers) {
        allIncidentsGM.forEach(incident => {
            if (incident.latitude && incident.longitude) {
                const urgencyColor = incident.urgency === 'Critical' ? 'red' : 
                                    incident.urgency === 'High' ? 'orange' : 
                                    incident.urgency === 'Medium' ? 'yellow' : 'green';
                
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(incident.latitude), lng: parseFloat(incident.longitude) },
                    map: googleMap,
                    title: incident.title,
                    icon: `http://maps.google.com/mapfiles/ms/icons/${urgencyColor}-dot.png`
                });
                
                const infoWindow = new google.maps.InfoWindow({
                    content: createGoogleMapPopup(incident)
                });
                
                marker.addListener('click', () => {
                    // Close all other info windows
                    document.querySelectorAll('.gm-infowindow').forEach(w => w.style.display = 'none');
                    infoWindow.open(googleMap, marker);
                });
                
                criminalMarkers.push(marker);
            }
        });
    }
    
    // Fit bounds
    if (criminalMarkers.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        criminalMarkers.forEach(marker => bounds.extend(marker.getPosition()));
        googleMap.fitBounds(bounds);
    }
}

function createGoogleMapPopup(incident) {
    const urgencyClass = incident.urgency === 'Critical' ? 'danger' : 
                        incident.urgency === 'High' ? 'warning' : 'info';
    return `
        <div class="incident-popup" style="font-family: Arial, sans-serif; padding: 10px; max-width: 300px;">
            <h6 style="margin: 0 0 8px 0;">#${incident.incident_id}</h6>
            <p style="margin: 5px 0; font-weight: bold;">${incident.title}</p>
            <p style="margin: 5px 0; font-size: 12px;">${incident.description?.substring(0, 100)}...</p>
            <p style="margin: 5px 0;">
                <span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">${incident.urgency}</span>
                <span style="background: #0d6efd; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">${incident.incident_type}</span>
            </p>
            <p style="margin: 5px 0; font-size: 12px;"><strong>Location:</strong> ${incident.barangay || 'N/A'}</p>
            <p style="margin: 5px 0; font-size: 12px;"><strong>Date:</strong> ${new Date(incident.created_at).toLocaleString()}</p>
            <button onclick="viewIncidentDetail(${incident.incident_id})" style="background: #0d6efd; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">View Details</button>
        </div>
    `;
}

// Toggle functions
function toggleGoogleHeatmap() {
    showHeatmap = !showHeatmap;
    if (heatmapLayer) heatmapLayer.setMap(showHeatmap ? googleMap : null);
}

function toggleGoogleMarkers() {
    showMarkers = !showMarkers;
    criminalMarkers.forEach(marker => marker.setVisible(showMarkers));
}

function resetGoogleMap() {
    if (googleMap) {
        googleMap.setCenter({ lat: 14.5995, lng: 120.9842 });
        googleMap.setZoom(13);
    }
}

// Call on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('googleMap')) {
        initGoogleMap();
    }
});
GMJS;

/**
 * Instructions for using this file:
 * 
 * 1. GET API KEY:
 *    - Go to https://console.cloud.google.com/
 *    - Create a new project
 *    - Enable Maps JavaScript API
 *    - Create an API key
 * 
 * 2. UPDATE crime_mapping.php:
 *    - Replace Leaflet script tags with Google Maps script tag
 *    - Change heatmap container ID from 'leafletMap' to 'googleMap'
 *    - Include this file or copy the JavaScript code
 * 
 * 3. PROS OF GOOGLE MAPS:
 *    - Better visualization quality
 *    - Built-in heatmap layer
 *    - Official support and documentation
 *    - Street view integration
 * 
 * 4. CONS:
 *    - Requires API key (potential costs)
 *    - Rate limiting
 *    - Privacy concerns (location data sent to Google)
 * 
 * 5. LEAFLET ALTERNATIVE (Current):
 *    - Open source and free
 *    - No API key required
 *    - Better for privacy
 *    - Good enough for law enforcement mapping
 */

?>
