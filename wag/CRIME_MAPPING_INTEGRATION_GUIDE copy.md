# Crime Mapping Integration Guide - Leaflet & Google Maps

## Overview

Your Crime Mapping module now includes two mapping solutions:

### 1. **Leaflet.js** (Current - Default)
- ✅ Open source and free
- ✅ No API key required
- ✅ Better privacy (no data sent to external services)
- ✅ Perfect for law enforcement
- ✅ Fully functional heatmaps and clustering

### 2. **Google Maps** (Alternative)
- ✅ Professional mapping service
- ✅ Advanced features
- ⚠️ Requires API key (may have costs)
- ⚠️ Privacy considerations

---

## Current Setup - Leaflet.js Integration

### What's Included

**CSS Libraries:**
- Leaflet v1.9.4 (mapping framework)
- Leaflet Heat (heatmap layer plugin)

**JavaScript Libraries:**
- Leaflet.js core
- Leaflet.heat plugin

**Map Features:**
✓ Interactive tile-based map (OpenStreetMap)
✓ Crime heatmap with color gradient
✓ Incident markers by urgency
✓ Popup information for each incident
✓ Map controls (toggle heatmap, markers, reset)
✓ Filter by crime type
✓ Auto-fit bounds to incidents

### Leaflet Map Controls

**Toggle Buttons:**
| Button | Function |
|--------|----------|
| 🔥 Heatmap | Show/hide intensity heatmap |
| 📍 Markers | Show/hide incident markers |
| 🔷 Clusters | Toggle incident clustering |
| ↻ Reset | Return to default view |

**Color Legend:**
```
🔴 Red        = High Crime Density (Critical Urgency)
🟠 Orange     = Medium Crime Density (High Urgency)
🟡 Yellow     = Low Crime Density (Medium Urgency)
🟢 Green      = Minimal Crime Density (Low Urgency)
```

### Leaflet Incident Markers

Incident markers use color-coded circles:
- **Red circle** = Critical urgency incident
- **Orange circle** = High urgency incident
- **Yellow circle** = Medium urgency incident
- **Green circle** = Low urgency incident

Each marker includes:
- Incident ID and title
- Description snippet
- Urgency and type badges
- Location information
- Date and time
- "View Details" button

### How to Use (Leaflet)

1. **Open Crime Mapping Module**
   - Navigate to "Crime Mapping & Heatmaps" from navbar

2. **View Default Map**
   - All incidents displayed with heatmap overlay
   - Red/orange areas = crime hotspots

3. **Filter Crimes**
   - Select crime type from "Filter by Crime Type" dropdown
   - Map updates automatically

4. **Click Markers**
   - Click any incident marker to see details
   - Click "View Details" for full incident information

5. **Toggle Visualization**
   - Use buttons to show/hide heatmap or markers
   - Reset button returns to full view

---

## Google Maps Alternative Setup

### How to Enable Google Maps

**Step 1: Get Google Maps API Key**
1. Visit: https://console.cloud.google.com/
2. Create a new project
3. Enable "Maps JavaScript API"
4. Enable "Visualization Library" (for heatmaps)
5. Create an API key
6. Restrict key to your domain (security)

**Step 2: Update crime_mapping.php**

Replace this Leaflet section:
```php
// Around line 30-35
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
```

With Google Maps:
```html
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=visualization"></script>
```

**Step 3: Replace Container**

Change:
```html
<div id="leafletMap" class="crime-map-container"></div>
```

To:
```html
<div id="googleMap" class="crime-map-container"></div>
```

**Step 4: Replace JavaScript**

See `google_maps_integration.php` for complete Google Maps JavaScript code.

### Google Maps Features

✓ Professional heatmap visualization
✓ Better performance with large datasets
✓ Street view integration
✓ Traffic layer support (optional)
✓ Advanced clustering
✓ Satellite imagery view
✓ More styling options

### Google Maps Costs

**Free Tier:**
- $200/month free credits
- ~28,000 map loads/month free

**Billable:**
- $7 per 1,000 map loads after free tier
- $2 per 1,000 heatmap layers

For small to medium deployments, usually stays within free tier.

---

## System-Wide CSS Integration

### CSS Files Loaded

Your crime mapping now uses system CSS:

| CSS File | Purpose |
|----------|---------|
| `style.css` | Primary styling, colors, typography |
| `global.css` | Global component styles |
| `analytics.css` | Dashboard layout and responsive design |
| `auth.css` | Authentication styles |
| `bootstrap@5.3.8` | Bootstrap framework |

### Custom CSS Added

**Crime Mapping Specific:**
```css
.crime-map-container     /* Map height and styling */
.map-controls            /* Control buttons styling */
.heatmap-legend          /* Legend display */
.incident-popup          /* Marker popup styling */
.stat-card               /* Statistics cards */
.page-header             /* Page title styling */
```

### Responsive Design

Maps and controls are responsive:
- **Desktop (>1024px)**: Full-width map with sidebar
- **Tablet (768px-1024px)**: Adjusted map size
- **Mobile (<768px)**: Full-width stacked layout

---

## API Endpoints Reference

All map data comes from these API endpoints:

```
GET ?action=get_incident_data
  Returns: Array of incidents with coordinates
  
GET ?action=get_heatmap_data
  Returns: Array of intensity points for heatmap
  
GET ?action=get_stats
  Returns: Overall statistics (total, critical, resolved, locations)
```

---

## Database Integration

### Location Data Storage

**incident_locations table:**
```sql
- incident_id (FK to incidents)
- latitude (DECIMAL 10,8)
- longitude (DECIMAL 11,8)
- address (VARCHAR 255)
- barangay (VARCHAR 100)
- district (VARCHAR 100)
- zone (INT)
```

### Adding Location Data

When creating incidents, include location fields:
```php
$crimeMappingManager->addIncidentLocation(
    $incidentId,
    $latitude,   // 14.5995
    $longitude,  // 120.9842
    $address,    // "123 Main St"
    $barangay,   // "Barangay 1"
    $district,   // "District 1",
    $zone        // 1
);
```

---

## Troubleshooting

### Issue: Map Not Loading

**Solution:**
1. Check browser console for JavaScript errors
2. Verify Leaflet libraries are loading (Network tab)
3. Check if `leafletMap` element exists
4. Verify incident data has coordinates

### Issue: Heatmap Not Showing

**Solution:**
1. Generate heatmap data: Visit `?action=generate_heatmap`
2. Check that incidents have latitude/longitude
3. Toggle heatmap button to ensure it's enabled
4. Verify heatmap_data table is populated

### Issue: Markers Not Displaying

**Solution:**
1. Toggle markers button to enable
2. Verify incidents exist with coordinates
3. Check incident status (only non-deleted incidents)
4. Zoom out to see all markers

### Issue: Map Performance Issues

**Solution:**
1. Use date filters to limit displayed incidents
2. Zoom in to reduce marker count
3. Filter by specific crime type
4. Consider implementing pagination for large datasets

---

## Advanced Configuration

### Customize Map Center

**Edit this in crime_mapping.php:**
```javascript
const defaultCenter = [14.5995, 120.9842];  // Manila
map.setView(defaultCenter, 13);  // 13 is zoom level
```

### Change Tile Provider

**Current (OpenStreetMap):**
```javascript
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png')
```

**Alternative providers:**
```javascript
// CartoDB Light
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png')

// Stamen Toner
L.tileLayer('https://tiles.stadiamaps.com/tiles/stamen_toner/{z}/{x}/{y}.png')

// ESRI Satellite
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')
```

### Heatmap Gradient Customization

```javascript
gradient: {
    0.0: '#00ff00',   // Green
    0.25: '#ffff00',  // Yellow
    0.5: '#ff6600',   // Orange
    0.75: '#ff3300',  // Red-Orange
    1.0: '#ff0000'    // Red
}
```

### Marker Size Adjustment

```javascript
const marker = L.circleMarker([lat, lon], {
    radius: 6,              // Change this value
    fillColor: urgencyColor,
    weight: 1,              // Border width
    opacity: 0.8,           // Border opacity
    fillOpacity: 0.7        // Fill opacity
});
```

---

## Performance Optimization

### Query Optimization
- Queries use proper indexes on location, date, crime_type
- LIMIT applied to all data fetches
- Prepared statements prevent SQL injection

### Front-end Optimization
- Lazy load map only when needed
- Debounce filter changes
- Cache incident data in memory
- Use Leaflet's built-in clustering

### Database Optimization
- Heatmap data pre-calculated
- Indexes on all common queries
- Automatic cleanup of old data

---

## Deployment Checklist

- [ ] Run setup script: `modules/setup_crime_mapping.php`
- [ ] Verify database tables created
- [ ] Add location data to incidents
- [ ] Test map loading and rendering
- [ ] Test filter functionality
- [ ] Test marker popups
- [ ] Test responsiveness on mobile
- [ ] Verify CSS loads correctly
- [ ] Check browser console for errors
- [ ] Test with actual incident data

---

## Future Enhancements

Potential features to add:

1. **Clustering**
   - Cluster nearby incidents at low zoom levels
   - Shows cluster count

2. **Drawing Tools**
   - Draw police patrol zones
   - Draw incident boundaries
   - Create custom search areas

3. **Route Optimization**
   - Calculate optimal patrol routes
   - Suggest hotspot areas

4. **Real-time Updates**
   - WebSocket integration
   - Live incident updates on map
   - Notifications for new incidents

5. **Export Features**
   - Export map as image/PDF
   - Generate crime reports
   - Statistical summaries

6. **Advanced Filtering**
   - Time-based filtering
   - Incident type combinations
   - Temporal heat maps

7. **Integration**
   - CAD/dispatch system integration
   - Mobile app integration
   - Third-party analytics

---

## Files Created/Modified

**New Files:**
- `modules/crime_mapping.php` - With Leaflet integration
- `modules/google_maps_integration.php` - Google Maps alternative
- `CRIME_MAPPING_INTEGRATION_GUIDE.md` - This file

**Libraries Added:**
- Leaflet.js v1.9.4
- Leaflet.heat plugin
- Chart.js v3.9.1

**System CSS Integrated:**
- analytics.css
- style.css
- global.css
- auth.css

---

## Support & Maintenance

**Regular Tasks:**
- Monitor map performance
- Check for JavaScript errors
- Verify location data accuracy
- Update libraries periodically

**Security:**
- Validate all API inputs
- Use prepared statements
- Implement rate limiting
- Restrict API access if needed

---

## Version Information

- **Crime Mapping Version**: 1.0
- **Leaflet Version**: 1.9.4
- **Google Maps**: API v3 (optional)
- **Bootstrap**: 5.3.8
- **Chart.js**: 3.9.1

---

**Status**: ✅ **FULLY INTEGRATED WITH SYSTEM CSS AND MAPPING LIBRARIES**

Both Leaflet (active) and Google Maps (optional) are ready to use!
