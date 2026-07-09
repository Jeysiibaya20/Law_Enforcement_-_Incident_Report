<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/attachment_manager.php';
require_once __DIR__ . '/CrimeMappingManager.php';
require_once __DIR__ . '/../config/LanguageManager.php';

// Ensure database connection
if (!isset($pdo) || !$pdo) {
    $pdo = getDBConnection();
}

// Authentication checks
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'Costumer';

// Check if user is banned
$isBanned = false;
if ($userId) {
    try {
        $bStmt = $pdo->prepare("SELECT banned FROM signup WHERE user_id = ?");
        $bStmt->execute([$userId]);
        $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
        $isBanned = !empty($bRow['banned']);
    } catch (Exception $e) {
        $isBanned = false;
    }
}

if (!empty($isBanned)) {
    require '../includes/header.php';
    echo '<div class="main-content"><div class="content-container">';
    echo '<div class="alert alert-danger"><h4>Account Suspended</h4><p>Your account has been banned. Contact the administrator for more information.</p></div>';
    echo '</div></div>';
    require '../includes/footer.php';
    exit;
}

// Initialize Crime Mapping Manager
$isAdmin = $userId && strtolower($userRole) === 'admin';

// Initialize Crime Mapping Manager
$crimeMappingManager = new CrimeMappingManager($pdo);

// Handle AJAX requests
if (!empty($_GET['action'])) {
    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'get_heatmap_data':
            $type = $_GET['type'] ?? 'all';
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $data = $crimeMappingManager->getHeatmapData($type, $startDate, $endDate);
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

        case 'get_incident_data':
            $filters = [
                'incident_type' => $_GET['incident_type'] ?? null,
                'urgency' => $_GET['urgency'] ?? null,
                'barangay' => $_GET['barangay'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];
            $data = $crimeMappingManager->getIncidentData($filters);
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

        case 'get_stats':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $stats = $crimeMappingManager->getCrimeOverallStats($startDate, $endDate);
            $statsByType = $crimeMappingManager->getCrimeStatsByType($startDate, $endDate);
            $statsByLocation = $crimeMappingManager->getCrimeStatsByLocation(10, $startDate, $endDate);
            echo json_encode(['success' => true, 'overall' => $stats, 'by_type' => $statsByType, 'by_location' => $statsByLocation]);
            exit;

        case 'get_trends':
            $days = intval($_GET['days'] ?? 30);
            $trends = $crimeMappingManager->getCrimeTrends($days);
            echo json_encode(['success' => true, 'data' => $trends]);
            exit;

        case 'get_locations':
            $locations = $crimeMappingManager->getAvailableLocations();
            echo json_encode(['success' => true, 'data' => $locations]);
            exit;

        case 'get_crime_types':
            $crimeTypes = $crimeMappingManager->getAvailableCrimeTypes();
            echo json_encode(['success' => true, 'data' => $crimeTypes]);
            exit;

        case 'get_incident_detail':
            $incidentId = intval($_GET['incident_id'] ?? 0);
            $detail = $crimeMappingManager->getIncidentDetails($incidentId);
            echo json_encode(['success' => true, 'data' => $detail]);
            exit;

        case 'generate_heatmap':
            $result = $crimeMappingManager->generateHeatmapData();
            echo json_encode(['success' => $result]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// Get initial data for page load
$overallStats = $crimeMappingManager->getCrimeOverallStats();
$crimeTypes = $crimeMappingManager->getAvailableCrimeTypes();
$locations = $crimeMappingManager->getAvailableLocations();
$trends = $crimeMappingManager->getCrimeTrends(30);
$incidentData = $crimeMappingManager->getIncidentData();

// Set page title and additional head content for CSS
$page_title = "Crime Mapping & Heatmaps";
$body_class = "analytics-page";
$additional_head = <<<'HEAD'
    <!-- Leaflet CSS for interactive mapping -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.css" />
    
    <!-- Leaflet HeatLayer Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    
    <!-- Custom Crime Mapping Styles -->
    <style>
        .crime-map-container {
            height: 550px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 10px 0;
        }
        
        .leaflet-popup-content {
            font-family: 'Quicksand', sans-serif;
            line-height: 1.5;
        }
        
        .leaflet-popup-content h6 {
            margin: 0 0 8px 0;
            font-weight: 600;
        }
        
        .incident-popup {
            min-width: 250px;
        }
        
        .incident-popup .badge {
            margin: 2px 2px 2px 0;
        }
        
        .map-controls {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 12px;
        }
        
        .map-toggle-btn {
            margin: 4px 4px 4px 0;
        }
        
        .heatmap-legend {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 12px;
        }
        
        .heatmap-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
        }
        
        .heatmap-legend-color {
            width: 30px;
            height: 20px;
            border-radius: 3px;
        }
    </style>
HEAD;
?>

<?php require '../includes/header.php'; ?>

<!-- Load Analytics CSS -->
<link href="<?php echo $base_url ?? ''; ?>assets/css/analytics.css" rel="stylesheet">

<div class="main-content">
    <div class="content-container">
        <div class="page-header mb-4">
            <h1 class="page-title">
                <i class="bi bi-map"></i> Crime Mapping & Heatmaps
            </h1>
            <p class="page-subtitle">Real-time crime incident visualization and geographic analysis</p>
        </div>

        <!-- Filters Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="filterCrimeType" class="form-label">Crime Type</label>
                        <select id="filterCrimeType" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($crimeTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['incident_type']); ?>">
                                    <?php echo htmlspecialchars($type['incident_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filterLocation" class="form-label">Location</label>
                        <select id="filterLocation" class="form-select">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['barangay']); ?>">
                                    <?php echo htmlspecialchars($location['barangay']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filterStartDate" class="form-label">Start Date</label>
                        <input type="date" id="filterStartDate" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="filterEndDate" class="form-label">End Date</label>
                        <input type="date" id="filterEndDate" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="applyFilters()">
                            <i class="bi bi-search"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalIncidents">
                            <?php echo $overallStats['total_incidents'] ?? 0; ?>
                        </div>
                        <div class="stat-label">Total Incidents</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="bi bi-lightning-fill"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="criticalIncidents">
                            <?php echo $overallStats['critical_incidents'] ?? 0; ?>
                        </div>
                        <div class="stat-label">Critical Incidents</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="resolvedIncidents">
                            <?php echo $overallStats['resolved_incidents'] ?? 0; ?>
                        </div>
                        <div class="stat-label">Resolved Incidents</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="affectedAreas">
                            <?php echo $overallStats['affected_barangays'] ?? 0; ?>
                        </div>
                        <div class="stat-label">Affected Areas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Heatmap -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-map"></i> Interactive Crime Heatmap (Leaflet)</h5>
            </div>
            <div class="card-body">
                <!-- Map Controls -->
                <div class="map-controls">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary map-toggle-btn" onclick="toggleHeatmap()" title="Toggle Heatmap">
                            <i class="bi bi-fire"></i> Heatmap
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary map-toggle-btn" onclick="toggleMarkers()" title="Toggle Incident Markers">
                            <i class="bi bi-geo-alt"></i> Markers
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary map-toggle-btn" onclick="toggleClusters()" title="Toggle Clusters">
                            <i class="bi bi-grid-3x3"></i> Clusters
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary map-toggle-btn" onclick="resetMap()" title="Reset Map View">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </div>
                    <div class="mt-2">
                        <label for="mapCrimeFilter" class="form-label me-2">Filter by Crime Type:</label>
                        <select id="mapCrimeFilter" class="form-select form-select-sm" style="max-width: 250px; display: inline-block;" onchange="updateMapData()">
                            <option value="">All Types</option>
                            <?php foreach ($crimeTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['incident_type']); ?>">
                                    <?php echo htmlspecialchars($type['incident_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Leaflet Map Container -->
                <div id="leafletMap" class="crime-map-container"></div>
                
                <!-- Heatmap Legend -->
                <div class="heatmap-legend">
                    <strong><i class="bi bi-info-circle"></i> Legend:</strong>
                    <div class="heatmap-legend-item">
                        <div class="heatmap-legend-color" style="background: #ff0000;"></div>
                        <span>High Crime Density (Critical)</span>
                    </div>
                    <div class="heatmap-legend-item">
                        <div class="heatmap-legend-color" style="background: #ff6600;"></div>
                        <span>Medium Crime Density (High)</span>
                    </div>
                    <div class="heatmap-legend-item">
                        <div class="heatmap-legend-color" style="background: #ffff00;"></div>
                        <span>Low Crime Density (Medium)</span>
                    </div>
                    <div class="heatmap-legend-item">
                        <div class="heatmap-legend-color" style="background: #00ff00;"></div>
                        <span>Minimal Crime Density (Low)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crime by Type and Location -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Incidents by Crime Type</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="crimeTypeChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Top 10 Crime Locations</h5>
                    </div>
                    <div class="card-body">
                        <div id="topLocationsTable">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th>Incidents</th>
                                        <th>Urgency Level</th>
                                    </tr>
                                </thead>
                                <tbody id="topLocationsBody">
                                    <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crime Trends -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Crime Trends (Last 30 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="trendsChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Recent Incidents Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Recent Crime Incidents</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="incidentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Urgency</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="incidentsTableBody">
                            <?php foreach (array_slice($incidentData, 0, 10) as $incident): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($incident['incident_id']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($incident['title'], 0, 50)); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($incident['incident_type']); ?></span></td>
                                    <td>
                                        <?php 
                                        $urgencyClass = match($incident['urgency']) {
                                            'Critical' => 'bg-danger',
                                            'High' => 'bg-warning',
                                            'Medium' => 'bg-info',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $urgencyClass; ?>"><?php echo htmlspecialchars($incident['urgency']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($incident['barangay'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($incident['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($incident['status']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewIncidentDetail(<?php echo $incident['incident_id']; ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incident Detail Modal -->
<div class="modal fade" id="incidentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Incident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incidentDetailContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin-right: 15px;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.page-header {
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 15px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.page-subtitle {
    color: #666;
    font-size: 14px;
    margin: 0;
}
</style>

<script>
// ===== LEAFLET MAP GLOBALS =====
let map = null;
let heatLayer = null;
let markerClusterGroup = null;
let markers = [];
let incidentMarkers = [];
let allIncidents = [];
let showHeatmap = true;
let showMarkers = true;
let showClusters = false;

// Charts context
let crimeTypeChart = null;
let trendsChart = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initLeafletMap();
    loadCrimeStats();
    loadTrendsData();
});

// ===== LEAFLET MAP FUNCTIONS =====
function initLeafletMap() {
    const mapContainer = document.getElementById('leafletMap');
    if (!mapContainer) return;

    if (typeof L === 'undefined') {
        mapContainer.innerHTML = '<div class="alert alert-danger m-3">Leaflet failed to load. Please refresh the page or check your network connection.</div>';
        return;
    }

    // Default coordinates (Manila, Philippines)
    const defaultCenter = [14.5995, 120.9842];
    
    // Create map
    map = L.map('leafletMap').setView(defaultCenter, 13);
    
    // Add tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 250);
    
    // Load incident data
    loadMapData();
}

function loadMapData() {
    const crimeFilter = document.getElementById('mapCrimeFilter')?.value || '';
    const params = new URLSearchParams();
    if (crimeFilter) params.append('incident_type', crimeFilter);
    
    fetch(`crime_mapping.php?action=get_incident_data&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allIncidents = data.data;
                updateMapDisplay();
            }
        })
        .catch(error => console.error('Error loading map data:', error));
}

function updateMapDisplay() {
    // Clear existing layers
    if (heatLayer) map.removeLayer(heatLayer);
    incidentMarkers.forEach(marker => map.removeLayer(marker));
    incidentMarkers = [];
    
    if (!allIncidents || allIncidents.length === 0) {
        console.warn('No incidents to display');
        return;
    }
    
    // Prepare heatmap data
    const heatData = allIncidents
        .filter(incident => incident.latitude && incident.longitude)
        .map(incident => {
            const intensity = incident.urgency === 'Critical' ? 1.0 : 
                            incident.urgency === 'High' ? 0.7 : 
                            incident.urgency === 'Medium' ? 0.4 : 0.2;
            return [incident.latitude, incident.longitude, intensity];
        });
    
    // Add heatmap layer
    if (showHeatmap && heatData.length > 0) {
        heatLayer = L.heatLayer(heatData, {
            radius: 25,
            blur: 15,
            maxZoom: 17,
            gradient: {
                0.0: '#00ff00',
                0.25: '#ffff00',
                0.5: '#ff6600',
                0.75: '#ff3300',
                1.0: '#ff0000'
            }
        }).addTo(map);
    }
    
    // Add incident markers
    if (showMarkers) {
        allIncidents.forEach(incident => {
            if (incident.latitude && incident.longitude) {
                const urgencyColor = incident.urgency === 'Critical' ? '#FF0000' : 
                                    incident.urgency === 'High' ? '#FF6600' : 
                                    incident.urgency === 'Medium' ? '#FFFF00' : '#00FF00';
                
                const marker = L.circleMarker([incident.latitude, incident.longitude], {
                    radius: 6,
                    fillColor: urgencyColor,
                    color: '#000',
                    weight: 1,
                    opacity: 0.8,
                    fillOpacity: 0.7
                });
                
                marker.bindPopup(createIncidentPopup(incident));
                marker.addTo(map);
                incidentMarkers.push(marker);
            }
        });
    }
    
    // Fit map bounds to all incidents
    if (incidentMarkers.length > 0) {
        const group = new L.featureGroup(incidentMarkers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

function createIncidentPopup(incident) {
    const urgencyClass = incident.urgency === 'Critical' ? 'danger' : 
                        incident.urgency === 'High' ? 'warning' : 'info';
    return `
        <div class="incident-popup">
            <h6>#${incident.incident_id}</h6>
            <p><strong>${incident.title}</strong></p>
            <p><small>${incident.description?.substring(0, 100)}...</small></p>
            <p>
                <span class="badge bg-${urgencyClass}">${incident.urgency}</span>
                <span class="badge bg-info">${incident.incident_type}</span>
            </p>
            <p><small><strong>Location:</strong> ${incident.barangay || 'N/A'}</small></p>
            <p><small><strong>Date:</strong> ${new Date(incident.created_at).toLocaleString()}</small></p>
            <button class="btn btn-sm btn-primary" onclick="viewIncidentDetail(${incident.incident_id})">View Details</button>
        </div>
    `;
}

function toggleHeatmap() {
    showHeatmap = !showHeatmap;
    updateMapDisplay();
}

function toggleMarkers() {
    showMarkers = !showMarkers;
    updateMapDisplay();
}

function toggleClusters() {
    showClusters = !showClusters;
    updateMapDisplay();
}

function resetMap() {
    if (map) {
        map.setView([14.5995, 120.9842], 13);
    }
}

function updateMapData() {
    loadMapData();
}

// ===== CHARTS AND INCIDENT DETAIL FUNCTIONS =====
function loadCrimeStats() {
    fetch('crime_mapping.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalIncidents').textContent = data.overall.total_incidents || 0;
                document.getElementById('criticalIncidents').textContent = data.overall.critical_incidents || 0;
                document.getElementById('resolvedIncidents').textContent = data.overall.resolved_incidents || 0;
                document.getElementById('affectedAreas').textContent = data.overall.affected_barangays || 0;

                const crimeTypes = {};
                data.by_type.forEach(item => {
                    crimeTypes[item.incident_type] = (crimeTypes[item.incident_type] || 0) + item.count;
                });

                updateCrimeTypeChart(crimeTypes);
                updateTopLocationsTable(data.by_location);
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

function loadTrendsData() {
    fetch('crime_mapping.php?action=get_trends&days=30')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTrendsChart(data.data);
            }
        })
        .catch(error => console.error('Error loading trends:', error));
}

function updateCrimeTypeChart(crimeTypes) {
    const ctx = document.getElementById('crimeTypeChart').getContext('2d');
    if (crimeTypeChart) crimeTypeChart.destroy();

    crimeTypeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(crimeTypes),
            datasets: [{
                data: Object.values(crimeTypes),
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

function updateTrendsChart(trendsData) {
    const dates = {};
    trendsData.forEach(item => {
        if (!dates[item.crime_date]) dates[item.crime_date] = 0;
        dates[item.crime_date] += item.incident_count;
    });

    const ctx = document.getElementById('trendsChart').getContext('2d');
    if (trendsChart) trendsChart.destroy();

    trendsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: Object.keys(dates),
            datasets: [{
                label: 'Incident Count',
                data: Object.values(dates),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function updateTopLocationsTable(locations) {
    const tbody = document.getElementById('topLocationsBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!locations || locations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No location data available.</td></tr>';
        return;
    }

    locations.forEach(location => {
        const urgencyClass = location.avg_urgency > 2 ? 'bg-danger' : (location.avg_urgency > 1 ? 'bg-warning' : 'bg-info');
        const row = `
            <tr>
                <td>${location.barangay || 'Unknown'}${location.district ? ' (' + location.district + ')' : ''}</td>
                <td><strong>${location.incident_count || 0}</strong></td>
                <td><span class="badge ${urgencyClass}">Avg: ${parseFloat(location.avg_urgency || 0).toFixed(1)}</span></td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function applyFilters() {
    const filters = {
        incident_type: document.getElementById('filterCrimeType').value,
        barangay: document.getElementById('filterLocation').value,
        start_date: document.getElementById('filterStartDate').value,
        end_date: document.getElementById('filterEndDate').value
    };

    const params = new URLSearchParams(filters);
    fetch(`crime_mapping.php?action=get_incident_data&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateIncidentsTable(data.data);
                allIncidents = data.data;
                updateMapDisplay();
            }
        })
        .catch(error => console.error('Error loading filtered data:', error));
}

function updateIncidentsTable(incidents) {
    const tbody = document.getElementById('incidentsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!incidents || incidents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No incidents found.</td></tr>';
        return;
    }

    incidents.slice(0, 20).forEach(incident => {
        const urgencyClass = incident.urgency === 'Critical' ? 'bg-danger' : (incident.urgency === 'High' ? 'bg-warning' : 'bg-info');
        const row = `
            <tr>
                <td>#${incident.incident_id}</td>
                <td>${incident.title ? incident.title.substring(0, 50) : 'No title'}</td>
                <td><span class="badge bg-info">${incident.incident_type || 'Unknown'}</span></td>
                <td><span class="badge ${urgencyClass}">${incident.urgency || 'Unknown'}</span></td>
                <td>${incident.barangay || 'N/A'}</td>
                <td>${incident.created_at ? new Date(incident.created_at).toLocaleDateString() : 'N/A'}</td>
                <td>${incident.status || 'N/A'}</td>
                <td><button class="btn btn-sm btn-outline-primary" onclick="viewIncidentDetail(${incident.incident_id})">View</button></td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function viewIncidentDetail(incidentId) {
    fetch(`crime_mapping.php?action=get_incident_detail&incident_id=${incidentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const incident = data.data;
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Incident ID:</strong> #${incident.incident_id}</p>
                            <p><strong>Title:</strong> ${incident.title || 'N/A'}</p>
                            <p><strong>Type:</strong> ${incident.incident_type || 'N/A'}</p>
                            <p><strong>Urgency:</strong> ${incident.urgency || 'N/A'}</p>
                            <p><strong>Status:</strong> ${incident.status || 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Location:</strong> ${incident.barangay || 'N/A'}</p>
                            <p><strong>Address:</strong> ${incident.address || 'N/A'}</p>
                            <p><strong>Date:</strong> ${incident.created_at ? new Date(incident.created_at).toLocaleString() : 'N/A'}</p>
                            <p><strong>Coordinates:</strong> ${incident.latitude || 'N/A'}, ${incident.longitude || 'N/A'}</p>
                        </div>
                    </div>
                    <hr>
                    <p><strong>Description:</strong></p>
                    <p>${incident.description || 'No description provided'}</p>
                `;
                document.getElementById('incidentDetailContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('incidentDetailModal')).show();
            }
        })
        .catch(error => console.error('Error loading incident detail:', error));
}

// Ensure charts and map load once the DOM is ready.
document.addEventListener('DOMContentLoaded', function() {
    initLeafletMap();
    loadCrimeStats();
    loadTrendsData();
});
</script>
<?php require '../includes/footer.php'; ?>
