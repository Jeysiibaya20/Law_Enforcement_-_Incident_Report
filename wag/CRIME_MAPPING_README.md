# Crime Mapping & Heatmaps Module - Complete Documentation

## Overview

The **Crime Mapping & Heatmaps** module is a comprehensive geographic information system (GIS) designed for law enforcement to visualize, analyze, and respond to crime incidents in real-time. It integrates seamlessly with the Incident Report system and routes incidents to **GRP6 (Crime Analytics)** for analysis and strategic response.

### Key Features

✓ **Real-time Crime Visualization** - Interactive heatmaps of crime incidents  
✓ **Geographic Analysis** - Crime hotspots identification by barangay/district  
✓ **Statistical Dashboards** - Comprehensive crime statistics and trends  
✓ **Advanced Filtering** - Filter by crime type, location, date range, urgency  
✓ **Incident Clustering** - Identify crime patterns and correlations  
✓ **Responsive Design** - Mobile-friendly interface  
✓ **System Integration** - Seamless integration with incident routing system  

---

## Installation & Setup

### Step 1: Run Database Setup Script

Access the setup script from your admin dashboard or directly:
```
http://your-domain.com/modules/setup_crime_mapping.php
```

This script will:
- Create required database tables
- Set up indexes for performance
- Initialize default crime categories
- Configure automatic heatmap refresh events

### Step 2: Verify Installation

Check that the following tables were created:
```sql
SHOW TABLES LIKE '%location%';
SHOW TABLES LIKE 'heatmap%';
SHOW TABLES LIKE 'crime_categories';
```

Expected output:
- `incident_locations` - Stores geographic coordinates for incidents
- `heatmap_data` - Pre-calculated heatmap intensity data
- `crime_categories` - Crime type classifications

### Step 3: Verify Navbar Link

The navbar should now display "Crime Mapping & Heatmaps" under modules for admins.

---

## Architecture & Integration

### Database Schema

#### 1. **incident_locations** Table
Stores geographic coordinates for each incident.

```sql
CREATE TABLE incident_locations (
    location_id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    address VARCHAR(255),
    barangay VARCHAR(100),
    district VARCHAR(100),
    zone INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 2. **heatmap_data** Table
Pre-calculated heatmap intensity and crime density.

```sql
CREATE TABLE heatmap_data (
    heatmap_id INT PRIMARY KEY AUTO_INCREMENT,
    incident_count INT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    intensity DECIMAL(5, 2),
    crime_type VARCHAR(100),
    date_recorded DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 3. **crime_categories** Table
Classification system for crime types.

```sql
CREATE TABLE crime_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE,
    category_type VARCHAR(50),
    color_code VARCHAR(7),
    severity_level INT
);
```

### System Integration Points

#### Integration with Incident Report Module
When an incident is reported in `modules/incident_report.php`:
1. Incident data is stored in `incidents` table
2. Geographic location is stored in `incident_locations`
3. Automatically routed to GRP6 (Crime Analytics) via IncidentRoutingManager
4. Heatmap data is updated

#### Connection to IncidentRoutingManager
The system automatically routes crime-related incidents:

```php
require_once 'IncidentRoutingManager.php';
$routing = new IncidentRoutingManager($pdo);

// Crime incidents automatically route to GRP6
$routing->routeIncident($incidentId, 'Crime');
```

#### Integration with Incident Routing System
- **GRP6 (Crime Analytics)** - Receives all crime analysis incidents
- Automatically populated based on incident type and urgency
- Officers in GRP6 have access to crime mapping data

---

## API Endpoints

All endpoints return JSON responses. Access via AJAX in `crime_mapping.php`:

### 1. Get Incident Data
**Endpoint:** `crime_mapping.php?action=get_incident_data`

**Parameters:**
```
incident_type=string  (optional)
urgency=string        (optional) - Critical, High, Medium, Low
barangay=string       (optional)
start_date=YYYY-MM-DD (optional)
end_date=YYYY-MM-DD   (optional)
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "incident_id": 1,
            "title": "Theft Report",
            "incident_type": "Theft",
            "urgency": "High",
            "latitude": 14.5995,
            "longitude": 120.9842,
            "address": "123 Main St",
            "barangay": "Barangay X"
        }
    ]
}
```

### 2. Get Heatmap Data
**Endpoint:** `crime_mapping.php?action=get_heatmap_data`

**Parameters:**
```
type=string           (optional) - Crime type or 'all'
start_date=YYYY-MM-DD (optional)
end_date=YYYY-MM-DD   (optional)
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "latitude": 14.5995,
            "longitude": 120.9842,
            "intensity": 0.75,
            "crime_type": "Theft"
        }
    ]
}
```

### 3. Get Statistics
**Endpoint:** `crime_mapping.php?action=get_stats`

**Parameters:**
```
start_date=YYYY-MM-DD (optional)
end_date=YYYY-MM-DD   (optional)
```

**Response:**
```json
{
    "success": true,
    "overall": {
        "total_incidents": 150,
        "critical_incidents": 15,
        "resolved_incidents": 120,
        "affected_barangays": 5
    },
    "by_type": [
        {"incident_type": "Theft", "count": 45}
    ],
    "by_location": [
        {"barangay": "Barangay A", "incident_count": 50}
    ]
}
```

### 4. Get Crime Trends
**Endpoint:** `crime_mapping.php?action=get_trends`

**Parameters:**
```
days=integer (default: 30) - Number of days to analyze
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "crime_date": "2024-01-01",
            "incident_count": 5,
            "incident_type": "Theft"
        }
    ]
}
```

### 5. Get Available Locations
**Endpoint:** `crime_mapping.php?action=get_locations`

**Response:**
```json
{
    "success": true,
    "data": [
        {"barangay": "Barangay A", "district": "District 1"}
    ]
}
```

### 6. Get Crime Types
**Endpoint:** `crime_mapping.php?action=get_crime_types`

**Response:**
```json
{
    "success": true,
    "data": [
        {"incident_type": "Theft"},
        {"incident_type": "Assault"}
    ]
}
```

### 7. Get Incident Detail
**Endpoint:** `crime_mapping.php?action=get_incident_detail`

**Parameters:**
```
incident_id=integer
```

**Response:**
```json
{
    "success": true,
    "data": {
        "incident_id": 1,
        "title": "Theft Report",
        "description": "Full description...",
        "latitude": 14.5995,
        "longitude": 120.9842
    }
}
```

### 8. Generate Heatmap Data
**Endpoint:** `crime_mapping.php?action=generate_heatmap`

**Response:**
```json
{
    "success": true
}
```

---

## Using the CrimeMappingManager Class

### Initialization
```php
require_once 'CrimeMappingManager.php';

$crimeMappingManager = new CrimeMappingManager($pdo);
```

### Get Crime Statistics
```php
// Get statistics for a date range
$stats = $crimeMappingManager->getCrimeOverallStats('2024-01-01', '2024-01-31');
echo $stats['total_incidents'];      // Total incidents
echo $stats['critical_incidents'];   // Critical priority incidents
echo $stats['resolved_incidents'];   // Resolved incidents
echo $stats['affected_barangays'];   // Number of affected areas
```

### Get Crime by Type
```php
$crimeByType = $crimeMappingManager->getCrimeStatsByType('2024-01-01', '2024-01-31');
foreach ($crimeByType as $crime) {
    echo $crime['incident_type'] . ': ' . $crime['count'];
}
```

### Get Crime by Location
```php
$crimeByLocation = $crimeMappingManager->getCrimeStatsByLocation(10);
foreach ($crimeByLocation as $location) {
    echo $location['barangay'] . ': ' . $location['incident_count'] . ' incidents';
}
```

### Get Crime Trends
```php
$trends = $crimeMappingManager->getCrimeTrends(30); // Last 30 days
foreach ($trends as $trend) {
    echo $trend['crime_date'] . ': ' . $trend['incident_count'] . ' incidents';
}
```

### Add Incident Location
```php
$crimeMappingManager->addIncidentLocation(
    $incidentId = 123,
    $latitude = 14.5995,
    $longitude = 120.9842,
    $address = "123 Main Street",
    $barangay = "Barangay X",
    $district = "District 1",
    $zone = 1
);
```

### Get Incidents in Radius
```php
$incidents = $crimeMappingManager->getIncidentsInRadius(
    $centerLat = 14.5995,
    $centerLon = 120.9842,
    $radiusKm = 1
);
```

---

## Integration with Incident Report Module

### Automatically Adding Location Data

In `modules/incident_report.php`, after incident submission:

```php
require_once 'CrimeMappingManager.php';
$crimeMappingManager = new CrimeMappingManager($pdo);

// Extract location from form
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$address = $_POST['address'] ?? null;
$barangay = $_POST['barangay'] ?? null;
$district = $_POST['district'] ?? null;

// Add location data
if ($latitude && $longitude) {
    $crimeMappingManager->addIncidentLocation(
        $newIncidentId,
        $latitude,
        $longitude,
        $address,
        $barangay,
        $district
    );
}
```

### Routing to Crime Analytics (GRP6)

```php
require_once 'IncidentRoutingManager.php';
$routing = new IncidentRoutingManager($pdo);

// Crimes automatically route to GRP6
if (in_array($incidentType, ['Theft', 'Assault', 'Drug', 'Robbery', 'Burglary'])) {
    $routing->routeIncident($incidentId, 'Crime');
}
```

---

## User Interface Features

### Dashboard Components

1. **Filter Section**
   - Crime Type dropdown
   - Location/Barangay dropdown
   - Date range picker
   - Apply Filters button

2. **Statistics Cards**
   - Total Incidents
   - Critical Incidents
   - Resolved Incidents
   - Affected Areas

3. **Interactive Heatmap**
   - Color-coded intensity visualization
   - Ready for Google Maps/Leaflet integration
   - Real-time updates

4. **Crime by Type Chart**
   - Doughnut chart visualization
   - Distribution of crime types
   - Interactive legend

5. **Top Crime Locations Table**
   - Ranking by incident count
   - Urgency level indicators
   - District information

6. **Crime Trends Chart**
   - Line chart showing trends over time
   - 30-day default view
   - Customizable date range

7. **Recent Incidents Table**
   - Comprehensive incident listing
   - Quick view details
   - Status tracking

---

## Customization Guide

### Adding Custom Crime Categories

```php
$stmt = $pdo->prepare("
    INSERT INTO crime_categories 
    (category_name, category_type, color_code, severity_level, description)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    'Cybercrime',           // category_name
    'digital_crime',        // category_type
    '#FF00FF',             // color_code (hex)
    2,                     // severity_level
    'Digital/cyber crimes' // description
]);
```

### Customizing Heatmap Colors

Modify colors in `crime_mapping.php`:
```js
backgroundColor: [
    '#FF6384', // Red
    '#36A2EB', // Blue
    '#FFCE56', // Yellow
    // Add more colors as needed
]
```

### Adding New Visualization

Create new chart in JavaScript:
```js
const ctx = document.getElementById('newChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [...],
        datasets: [...]
    }
});
```

---

## Access Control

- **Admin Only**: Full access to all features
- **Officer Role**: Access restricted (can be enabled in navbar)
- **User Role**: No access

To enable for officers, modify navbar.php:
```php
<?php if ($userRole && (strtolower($userRole['role']) === 'admin' || strtolower($userRole['role']) === 'officer')): ?>
    <!-- Show crime mapping link -->
<?php endif; ?>
```

---

## Troubleshooting

### Issue: Heatmap data not appearing
**Solution:** 
1. Run setup script: `setup_crime_mapping.php`
2. Verify incident_locations table has data
3. Run: `crime_mapping.php?action=generate_heatmap`

### Issue: No incidents showing
**Solution:**
1. Ensure incidents have been created
2. Check if location data was saved
3. Verify incident status is not 'Deleted'

### Issue: Charts not rendering
**Solution:**
1. Check browser console for JavaScript errors
2. Verify Chart.js library loaded
3. Ensure JSON data is valid

### Issue: Slow performance with large datasets
**Solution:**
1. Check database indexes
2. Implement pagination
3. Use date filters to limit data
4. Archive old incidents

---

## API Integration Examples

### JavaScript (Frontend)
```js
// Fetch crime data with filters
fetch('crime_mapping.php?action=get_incident_data&barangay=Barangay%20A&start_date=2024-01-01')
    .then(response => response.json())
    .then(data => {
        console.log(data.data);
    });
```

### PHP (Backend Integration)
```php
require_once 'CrimeMappingManager.php';
$manager = new CrimeMappingManager($pdo);

// Get crimes for custom report
$crimes = $manager->getIncidentData([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31'
]);
```

### External System Integration
```bash
# Get crime stats via cURL
curl "http://your-domain.com/modules/crime_mapping.php?action=get_stats"
```

---

## Performance Optimization

### Database Indexes
All tables include proper indexes for common queries:
- Location queries: `idx_location (latitude, longitude)`
- Date queries: `idx_date (date_recorded)`
- Type queries: `idx_crime_type (crime_type)`

### Query Optimization
```php
// Use date filters to limit results
$crimeMappingManager->getIncidentData([
    'start_date' => date('Y-m-d', strtotime('-30 days')),
    'end_date' => date('Y-m-d')
]);
```

### Caching (Recommended)
```php
// Cache heatmap data for 1 hour
$cacheKey = 'heatmap_data_' . date('YmdH');
$data = $cache->get($cacheKey);
if (!$data) {
    $data = $crimeMappingManager->getHeatmapData();
    $cache->set($cacheKey, $data, 3600);
}
```

---

## Future Enhancements

Potential features to add:
- [ ] Real-time WebSocket updates
- [ ] Integration with Google Maps API
- [ ] Leaflet.js for offline mapping
- [ ] Predictive crime analysis
- [ ] Automated incident clustering
- [ ] Mobile app integration
- [ ] Export to PDF/Excel reports
- [ ] Custom boundary creation
- [ ] Multi-jurisdiction support
- [ ] AI-powered crime hotspot detection

---

## Support & Maintenance

- **Database Maintenance**: Run heatmap generation monthly
- **Regular Backups**: Backup location and heatmap tables
- **User Training**: Train officers on using filters and dashboards
- **Performance Monitoring**: Track query performance monthly

---

## Files Created

✓ `modules/crime_mapping.php` - Main module interface  
✓ `modules/CrimeMappingManager.php` - Business logic class  
✓ `modules/setup_crime_mapping.php` - Database setup script  
✓ `includes/navbar.php` - Updated with crime mapping link  
✓ `CRIME_MAPPING_README.md` - This documentation  

---

## Version Information

- **Module Version**: 1.0
- **Compatibility**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Bootstrap Version**: 5.3+
- **Chart.js Version**: 3.9.1+

---

**Module Created**: 2024
**Last Updated**: 2024
**Status**: ✓ Fully Functional and Integrated
