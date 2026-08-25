# Alertara QC - Law Enforcement & Incident Report System
## Comprehensive System API Endpoints & Integration Guide

This guide documents **ALL Inbound (Receiving)** and **Outbound (Sending)** API endpoints in the system codebase. You can use this document to dictate and configure endpoints with partner groups (**Aldrin's Group**, **Marto's Group**, **Group 2**, **Group 4**, **Group 5**, **Group 7**).

---

## 🌐 System Base URLs

- **Local Development**: `http://localhost/Law_Enforcement_-_Incident_Report/`
- **Live Production / Domain**: `https://[your-domain]/`
- **Content-Type**: `application/json; charset=utf-8`
- **Allowed Methods**: `GET, POST, PUT, OPTIONS`
- **CORS Support**: Enabled (`Access-Control-Allow-Origin: *`)

---

## 📥 1. INBOUND API ENDPOINTS (RECEIVING DATA FROM OTHER GROUPS)

### A. Emergency Response & Incident Ingestion Endpoint
Receives live emergency incident and call dispatch logs from the External Emergency Response System. Automatically stores the incident in `received_emergency_calls`, registers an official case (`EMR-YYYYMMDD-XXXX`) in the `incidents` table, and triggers the Emergency Response & Dispatch Unit workflow.

- **Primary Dedicated Endpoint**: `POST /api/receive_emergency_call.php`
- **Alternative Gateway Endpoints**:
  - `POST /api/external_integration.php?action=receive_emergency_call`
  - `POST /api/api.php?action=receive_emergency_call`

#### Authentication & Headers:
- **HTTP Method**: `POST`
- **Content-Type**: `application/json`
- **API Key Header**: `X-API-KEY: ALERTARA-EMERGENCY-2026` *(o `X-External-Secret: ALERTARA-EMERGENCY-2026`)*

#### Request Payload Format (Exact JSON format):
```json
{
  "Call ID": "CALL-EMR-2026-9901",
  "Timestamp": "2026-08-26 14:30:00",
  "Caller Location": "Susano Road, Brgy San Agustin, Novaliches, Quezon City",
  "Emergency Level": "Critical",
  "Incident Description": "Multi-vehicle collision with severe pedestrian injuries requiring immediate ambulance and traffic police response."
}
```
*(Also accepts: `call_id`, `timestamp`, `caller_location`, `location`, `emergency_level`, `incident_description`, `api_key`)*

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Incident successfully received and integrated with Emergency Response & Law Enforcement System.",
  "record_id": "5",
  "call_id": "CALL-EMR-2026-9901",
  "case_no": "EMR-20260826-D92E",
  "caller_location": "Susano Road, Brgy San Agustin, Novaliches, Quezon City",
  "emergency_level": "Critical",
  "incident_type": "General Law Enforcement Incident",
  "timestamp": "2026-08-26 14:30:00",
  "emergency_response": {
    "dispatch_status": "Active Response Initiated / Unit Dispatched",
    "priority_code": "CODE 1 - IMMEDIATE EMERGENCY RESPONSE",
    "assigned_unit": "EMS Ambulance & Paramedics Response Team + Police Escort",
    "estimated_arrival": "4-7 minutes",
    "police_district": "District 1 (Central)"
  },
  "received_at": "2026-08-26 14:30:01"
}
```

---

### B. Marto's Group — CCTV Footage Request Receiving
Allows partner surveillance units or external agencies (PNP, Blotter, QCPD) to submit formal CCTV footage requests.

- **Primary Dedicated Endpoint**: `POST /api/receive_cctv_request.php`
- **Alternative Endpoints**:
  - `POST /api/external_integration.php?action=receive_cctv_request`
  - `POST /api/api.php?action=receive_cctv_request`

#### Request Payload Format (JSON):
```json
{
  "requesting_agency": "Quezon City Police District",
  "contact_person": "P/Cpt. Ana Reyes",
  "position_designation": "Lead Investigator",
  "contact_number": "09171234567",
  "email_address": "ana.reyes@qcpd.gov.ph",
  "office_unit": "Investigation Division",
  "case_reference": "INV-2026-SAMPLE-01",
  "related_complaint_id": "COMP-2026-362",
  "legal_basis": "Law enforcement request",
  "purpose_reason": "Footage needed for ongoing investigation of suspicious activity near barangay hall.",
  "incident_location": "Susano Road, Barangay San Agustin, Quezon City",
  "camera_id": "CAM-001 — Main Entrance Camera",
  "location_description": "Main entrance camera facing Susano Road",
  "incident_date": "2026-08-26",
  "incident_type": "Suspicious Activity",
  "footage_start_time": "16:30",
  "footage_end_time": "17:00",
  "incident_description": "Persons loitering near the main entrance after hours.",
  "delivery_method": "Secure download link"
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "CCTV Footage Request received and recorded successfully.",
  "record_id": 1,
  "request_id_code": "CCTV-REQ-2026-4821",
  "requesting_agency": "Quezon City Police District",
  "status": "Pending",
  "received_at": "2026-08-26 14:30:05"
}
```

---

### C. Marto's Group — Receiving Fulfilled CCTV Footage & Evidence
When Marto's surveillance team fulfills or uploads CCTV footage clips, they send it to this webhook.

- **Primary Dedicated Endpoint**: `POST /api/cctv_footage_receive.php`
- **Alternative Endpoint**: `POST /api/external_integration.php?action=receive_cctv_footage`

#### Request Payload Format (JSON):
```json
{
  "request_id": "CCTV-REQ-2026-4821",
  "incident_id": "INC-2026-001",
  "cctv_url": "https://surveillance.alertaraqc.com/media/feeds/sample_footage_001.mp4",
  "camera_id": "CAM-001 — Main Entrance Camera",
  "location": "Susano Road, Barangay San Agustin, Quezon City",
  "video_format": "video/mp4",
  "duration": "30 mins",
  "notes": "Footage verified and exported from Main Entrance NVR."
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "CCTV footage payload received and stored successfully.",
  "record_id": 1,
  "request_id": "CCTV-REQ-2026-4821",
  "incident_id": "INC-2026-001",
  "cctv_url": "https://surveillance.alertaraqc.com/media/feeds/sample_footage_001.mp4",
  "camera_id": "CAM-001 — Main Entrance Camera",
  "received_at": "2026-08-26 14:31:00"
}
```

---

### D. Group 2 — Accident and Violation Reporting
Receives vehicular accident tickets, violation fees, and damage estimates from Group 2.

- **Endpoint**: `POST /api/receive_accident_report.php`
- **Alternative**: `POST /api/external_integration.php?action=receive_accident_report`

#### Request Payload Format (JSON):
```json
{
  "report_id": "ACC-REP-20260826-001",
  "ticket_number": "TKT-2026-892",
  "incident_type": "Vehicular Collision / Reckless Imprudence",
  "violator_name": "Juan Dela Cruz",
  "vehicle_details": "Toyota Vios (Silver)",
  "plate_number": "NBD-5421",
  "violation_type": "Reckless Driving & Over-speeding",
  "fine_amount": 2500.00,
  "severity_level": "High",
  "collision_type": "Side-impact Collision (T-Bone)",
  "location": "Quezon Ave. cor. Timog Ave., Quezon City",
  "narrative": "Sedan beat red light and collided with motorcycle at intersection.",
  "casualties_count": 1,
  "property_damage_estimate": 45000.00,
  "reporting_officer": "Traffic Enforcer Officer #44",
  "incident_date_time": "2026-08-26 14:15:00"
}
```

---

### E. Group 4 — Resolved Tips & Community Complaints
Receives verified community tips and complaints.

- **Resolved Tips Endpoint**: `POST /api/receive_resolved_tips.php` (or `action=receive_resolved_tip`)
- **Community Complaints Endpoint**: `POST /api/external_integration.php?action=receive_community_complaint`

---

## 📤 2. OUTBOUND API ENDPOINTS (SENDING DATA TO PARTNER SYSTEMS)

Our system automatically formats and dispatches payloads to partner endpoints configured in the **Integration Settings Registry** (`config/integration_config.php` & `admin/external_integrations.php`):

| Target External Group | Function / Purpose | Config Key | Default / Configured URL |
| :--- | :--- | :--- | :--- |
| **Marto's CCTV Group** | Send CCTV Query / Request | `cctv_request_api_url` | `https://surveillance.alertaraqc.com/api/cctv_requests_receive.php` |
| **Group 7 Inspection** | Dispatch Photo & Video Media Files | `group7_evidence_upload_api_url` | `https://inspection.alertaraqc.com/api/upload_evidence.php` |
| **Group 7 Inspection** | Field Inspection Scheduling | `group7_inspection_api_url` | `https://inspection.alertaraqc.com/api/schedule_inspection.php` |
| **Group 5 Crime Map** | Spatial Coordinates & Heatmap | `group5_crime_map_api_url` | `https://crimemap.alertaraqc.com/api/update_heatmap.php` |
| **Group 3 EMS / Police** | Officer & Resource Allocation | `group3_resource_api_url` | `https://dispatch.alertaraqc.com/api/assign_officer.php` |
| **Group 1 Campaigns** | Public Safety Campaigns Sync | `campaign_api_url` | `https://campaign.alertaraqc.com/api/v1/campaigns/public` |

---

## 🚀 3. CENTRAL REST API GATEWAY (`/api/api.php`)

Single unified entrypoint for mobile apps, external clients, and web dashboards:

| Action (`?action=`) | Method | Description |
| :--- | :---: | :--- |
| `all` / `all_in_one` | `GET` | Returns full data across all system modules in a single JSON payload. |
| `health` / `ping` | `GET` | System health check and database connectivity status. |
| `modules` | `GET` | Lists all enabled modules in the application. |
| `dashboard_stats` | `GET` | KPI metrics (Users, Blotters, Pending Cases, Resolved Cases). |
| `emergency_calls` | `GET` | Retrieves recent emergency calls received from Aldrin's group. |
| `receive_emergency_call` | `POST` | Ingests new emergency call (Aldrin's group). |
| `cctv_requests` | `GET` | Retrieves list of CCTV requests (supports filter `&status=Pending`). |
| `receive_cctv_request` | `POST` | Ingests new CCTV footage request (Marto's group). |
| `cctv_request_update_status`| `POST`| Updates CCTV request status (`Approved`, `Rejected`, `Fulfilled`). |
| `blotters` | `GET` | Retrieves recent blotter records (supports `&status=Pending`). |
| `cases` | `GET` | Retrieves registered incident cases. |
| `users` | `GET` | Retrieves system user accounts. |
| `notifications` | `GET` | Real-time unread alert count and pending items. |
| `chatbot` | `POST` | NLP incident reporting and assistant responses. |

---

## 🧪 4. HOW TO TEST THE APIS

### A. PowerShell One-Liner (Test Aldrin's Call Receiving):
```powershell
Invoke-RestMethod -Method Post -Uri "http://localhost/Law_Enforcement_-_Incident_Report/api/receive_emergency_call.php" -ContentType "application/json" -Body '{"Call ID":"CALL-TEST-01","Timestamp":"2026-08-26 14:30:00","Caller":"Aldrin Tester","Location":"Susano Road, QC","Emergency Level":"High","Incident Description":"Testing emergency call data receiving"}'
```

### B. cURL Command:
```bash
curl -X POST "http://localhost/Law_Enforcement_-_Incident_Report/api/receive_emergency_call.php" \
  -H "Content-Type: application/json" \
  -d '{
    "Call ID": "CALL-2026-0091",
    "Timestamp": "2026-08-26 14:30:00",
    "Caller": "Aldrin Tester",
    "Location": "Susano Road, Brgy San Agustin, QC",
    "Emergency Level": "High",
    "Incident Description": "Street commotion reported"
  }'
```

### C. Web Sandboxes:
- **Interactive REST API Sandbox**: Open `http://localhost/Law_Enforcement_-_Incident_Report/api/tester.php` in your browser.
- **External Integrations Hub**: Open `http://localhost/Law_Enforcement_-_Incident_Report/admin/external_integrations.php` in your browser.
