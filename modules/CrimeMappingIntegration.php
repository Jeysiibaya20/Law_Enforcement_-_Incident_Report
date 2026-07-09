<?php
/**
 * Crime Mapping Integration Helper
 * Helper functions to integrate crime mapping with incident report system
 * Include this file in incident_report.php or other modules that handle incident creation
 */

class CrimeMappingIntegration {
    private $pdo;
    private $crimeMappingManager;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once __DIR__ . '/CrimeMappingManager.php';
        $this->crimeMappingManager = new CrimeMappingManager($pdo);
    }

    /**
     * Process incident location data from form submission
     * Called automatically after incident is created
     */
    public function processIncidentLocation($incidentId, $formData) {
        try {
            // Extract location data from form
            $latitude = $formData['latitude'] ?? null;
            $longitude = $formData['longitude'] ?? null;
            $address = $formData['address'] ?? null;
            $barangay = $formData['barangay'] ?? null;
            $district = $formData['district'] ?? null;
            $zone = $formData['zone'] ?? null;

            // Add location if we have at least coordinates
            if ($latitude && $longitude) {
                return $this->crimeMappingManager->addIncidentLocation(
                    $incidentId,
                    $latitude,
                    $longitude,
                    $address,
                    $barangay,
                    $district,
                    $zone
                );
            }

            return false;
        } catch (Exception $e) {
            error_log("Error processing incident location: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get location data for an incident
     */
    public function getIncidentLocation($incidentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM incident_locations 
                WHERE incident_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$incidentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error retrieving incident location: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update incident location data
     */
    public function updateIncidentLocation($incidentId, $formData) {
        try {
            $latitude = $formData['latitude'] ?? null;
            $longitude = $formData['longitude'] ?? null;
            $address = $formData['address'] ?? null;
            $barangay = $formData['barangay'] ?? null;
            $district = $formData['district'] ?? null;
            $zone = $formData['zone'] ?? null;

            $stmt = $this->pdo->prepare("
                UPDATE incident_locations SET
                    latitude = ?,
                    longitude = ?,
                    address = ?,
                    barangay = ?,
                    district = ?,
                    zone = ?
                WHERE incident_id = ?
            ");

            return $stmt->execute([
                $latitude, $longitude, $address, 
                $barangay, $district, $zone, $incidentId
            ]);
        } catch (Exception $e) {
            error_log("Error updating incident location: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get nearby incidents (radius search)
     */
    public function getNearbyIncidents($latitude, $longitude, $radiusKm = 1) {
        return $this->crimeMappingManager->getIncidentsInRadius($latitude, $longitude, $radiusKm);
    }

    /**
     * Get incident heatmap data
     */
    public function getHeatmapData($crimeType = 'all', $startDate = null, $endDate = null) {
        return $this->crimeMappingManager->getHeatmapData($crimeType, $startDate, $endDate);
    }

    /**
     * Get crime statistics for reporting
     */
    public function getCrimeStats($startDate = null, $endDate = null) {
        return $this->crimeMappingManager->getCrimeOverallStats($startDate, $endDate);
    }

    /**
     * Generate heatmap visualization data
     */
    public function generateHeatmap() {
        return $this->crimeMappingManager->generateHeatmapData();
    }

    /**
     * Get form field HTML for location input
     * Returns HTML for embedding in incident report form
     */
    public static function getLocationFormFields() {
        return <<<HTML
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Incident Location (for Crime Mapping)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="latitude" class="form-label">Latitude</label>
                        <input type="number" id="latitude" name="latitude" class="form-control" 
                               step="0.0001" placeholder="e.g., 14.5995" title="Decimal format">
                        <small class="text-muted">Optional: Enter decimal coordinates</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="longitude" class="form-label">Longitude</label>
                        <input type="number" id="longitude" name="longitude" class="form-control" 
                               step="0.0001" placeholder="e.g., 120.9842" title="Decimal format">
                        <small class="text-muted">Optional: Enter decimal coordinates</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="address" class="form-label">Specific Address</label>
                        <input type="text" id="address" name="address" class="form-control" 
                               placeholder="e.g., 123 Main Street">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="barangay" class="form-label">Barangay</label>
                        <select id="barangay" name="barangay" class="form-select">
                            <option value="">Select Barangay</option>
                            <option value="Barangay 1">Barangay 1</option>
                            <option value="Barangay 2">Barangay 2</option>
                            <option value="Barangay 3">Barangay 3</option>
                            <!-- Add more barangays as needed -->
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="district" class="form-label">District</label>
                        <input type="text" id="district" name="district" class="form-control" 
                               placeholder="e.g., District 1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="zone" class="form-label">Zone</label>
                        <input type="number" id="zone" name="zone" class="form-control" 
                               placeholder="e.g., 1">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="getCoordinatesFromMap()">
                    <i class="bi bi-map"></i> Get Coordinates from Map
                </button>
                <small class="text-muted d-block mt-2">
                    Coordinates help visualize the incident on the crime mapping heatmap
                </small>
            </div>
        </div>
        HTML;
    }

    /**
     * Get JavaScript helper for map integration
     */
    public static function getLocationFormScript() {
        return <<<JS
        <script>
        function getCoordinatesFromMap() {
            // Placeholder for map integration
            alert('Map integration feature coming soon!');
            // Example: Open map modal where user can click to select location
        }

        function setCoordinates(latitude, longitude) {
            document.getElementById('latitude').value = latitude;
            document.getElementById('longitude').value = longitude;
        }

        // Auto-populate zone based on barangay
        document.getElementById('barangay').addEventListener('change', function() {
            const barangayZones = {
                'Barangay 1': '1',
                'Barangay 2': '2',
                'Barangay 3': '3'
                // Add zone mapping as needed
            };
            const zone = barangayZones[this.value];
            if (zone) {
                document.getElementById('zone').value = zone;
            }
        });
        </script>
        JS;
    }

    /**
     * Get quick reference for API calls
     */
    public static function getQuickReference() {
        return [
            'process_location' => 'CrimeMappingIntegration->processIncidentLocation($incidentId, $formData)',
            'get_location' => 'CrimeMappingIntegration->getIncidentLocation($incidentId)',
            'nearby_incidents' => 'CrimeMappingIntegration->getNearbyIncidents($lat, $lon, $radius)',
            'heatmap_data' => 'CrimeMappingIntegration->getHeatmapData($crimeType, $startDate, $endDate)',
            'stats' => 'CrimeMappingIntegration->getCrimeStats($startDate, $endDate)',
        ];
    }
}

/**
 * Quick integration functions
 */

/**
 * Process incident location data after incident creation
 * Usage in incident_report.php:
 * $integration = new CrimeMappingIntegration($pdo);
 * $integration->processIncidentLocation($newIncidentId, $_POST);
 */
function processCrimeIncidentLocation($pdo, $incidentId, $formData) {
    $integration = new CrimeMappingIntegration($pdo);
    return $integration->processIncidentLocation($incidentId, $formData);
}

/**
 * Get nearby incidents for correlation
 * Helps identify crime patterns
 */
function getNearbyIncidentsForAnalysis($pdo, $latitude, $longitude, $radiusKm = 1) {
    $integration = new CrimeMappingIntegration($pdo);
    return $integration->getNearbyIncidents($latitude, $longitude, $radiusKm);
}

/**
 * Get heatmap data as JSON for display
 */
function getHeatmapVisualizationData($pdo, $crimeType = 'all', $days = 30) {
    $integration = new CrimeMappingIntegration($pdo);
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $endDate = date('Y-m-d');
    return $integration->getHeatmapData($crimeType, $startDate, $endDate);
}

?>
