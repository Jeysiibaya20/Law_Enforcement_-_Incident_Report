# Case Assignment and Tracking System - Implementation Guide

## Overview
The Case Assignment and Tracking system is a comprehensive solution for managing incident cases in the Law Enforcement system. It enables admin users and barangay chairpersons to create, assign, track, and manage cases digitally.

---

## ✓ Implemented Features

### 1. **Automatic Case Number Generation**
- **Location**: `includes/case_management.php` - `generateCaseNumber()` function
- **Format**: `CASE-YYYY-MM-DD-XXX` (e.g., `CASE-2026-01-06-001`)
- **Functionality**: 
  - Automatically generates sequential case numbers based on the current date
  - Increments sequence number for each case created on the same day
  - Ensures unique case numbers throughout the system

### 2. **Digital Case Assignment**
- **Location**: `admin/cases.php` - Create New Case Modal
- **Features**:
  - Admin users can create new cases through the web interface
  - Cases can be assigned to BCPC officers during creation
  - Cases can also be assigned to barangay chairpersons
  - Supports unassigned cases for later assignment

### 3. **Available BCPC Officers List**
- **Location**: `includes/case_management.php` - `getAvailableBCPCOfficers()` function
- **Criteria for Availability**:
  - Officer must be marked as available
  - Officer's current case load must be below their maximum case load
  - User must be active in the system
- **Display**: Officers are sorted by current case load (ascending) and rank

### 4. **Case Status Tracking**
- **Location**: `includes/case_management.php` - `updateCaseStatus()` function
- **Status Options**:
  - **New**: Case just created, awaiting investigation
  - **Ongoing**: Case is being investigated/processed
  - **Resolved**: Case has been resolved
  - **Closed**: Case is officially closed
- **Admin Interface**: Status can be updated via the "Update Case Status" modal

### 5. **Follow-up Actions Recording**
- **Location**: `includes/case_management.php` - `addFollowUpAction()` function
- **Features**:
  - Admins can add follow-up actions to any case
  - Follow-ups are recorded in the `case_updates` table
  - Automatically creates timeline entries
  - Notifies the assigned officer of follow-up requirements
  - **Admin Interface**: "Add Follow-up Action" button on cases list

### 6. **Case Progress Timeline**
- **Location**: `modules/case_details.php` - Timeline Section
- **Event Types Tracked**:
  - Case Created
  - Assigned
  - Status Changed
  - Follow-up
  - Reassigned
  - Resolved
  - Closed
- **Features**:
  - Visual timeline with chronological event display
  - Shows event type, date, time, and performer
  - All events are automatically recorded when actions are taken

### 7. **Officer Notifications**
- **Location**: `includes/case_management.php` - `createNotification()` function
- **Notification Types**:
  - **New Assignment**: When a case is assigned to an officer
  - **Status Update**: When case status changes
  - **Reassignment**: When a case is reassigned
  - **Follow-up Required**: When a follow-up action is added
- **Automatic Triggers**:
  - Notification sent when case is created and assigned
  - Notification sent when status is updated
  - Notification sent when follow-up action is added
  - Notification sent when case is reassigned

### 8. **Case Reassignment**
- **Location**: `includes/case_management.php` - `reassignCase()` function
- **Features**:
  - Cases can be reassigned from one officer to another
  - Reason for reassignment is recorded
  - Officer case loads are automatically updated
  - Timeline entry is created for the reassignment
  - Both old and new officers are notified
  - **Admin Interface**: "Reassign Case" button (shows only if case is currently assigned)

### 9. **Detailed Case View**
- **Location**: `modules/case_details.php`
- **Information Displayed**:
  - Case basic information (number, type, status, priority)
  - Complainant and respondent details
  - Incident date, time, and location
  - Full incident description
  - Assignment information
  - Case timeline with all events
  - Updates and follow-up actions
  - Assignment and update performers

---

## 📋 Database Tables Created

### `case_assignments`
Stores the main case information
```
- id (PRIMARY KEY)
- case_number (UNIQUE)
- incident_type
- complainant_name
- respondent_name
- location
- incident_date
- incident_time
- description
- priority (ENUM: High, Medium, Low)
- status (ENUM: New, Ongoing, Resolved, Closed)
- assigned_by (user_id)
- assigned_to (user_id)
- barangay_chairperson_id (user_id)
- assignment_date
- created_at, updated_at
```

### `case_timeline`
Tracks all case events chronologically
```
- id (PRIMARY KEY)
- case_id (FOREIGN KEY)
- case_number
- event_type
- event_description
- performed_by (user_id)
- event_date
```

### `case_updates`
Records detailed updates and follow-up actions
```
- id (PRIMARY KEY)
- case_id (FOREIGN KEY)
- case_number
- update_type (ENUM: Status Change, Follow-up Action, Note, Reassignment)
- previous_status
- new_status
- action_description
- updated_by (user_id)
- updated_at
```

### `case_notifications`
Stores notifications sent to officers
```
- id (PRIMARY KEY)
- recipient_id (user_id)
- case_id (FOREIGN KEY)
- case_number
- notification_type
- title
- message
- is_read (BOOLEAN)
- created_at
```

### `bcpc_officers`
Links users to officer roles and tracks case load
```
- id (PRIMARY KEY)
- user_id (UNIQUE, FOREIGN KEY)
- barangay
- rank
- specialization
- contact_number
- is_available (BOOLEAN)
- current_case_load
- max_case_load
- created_at, updated_at
```

---

## 🚀 Setup Steps

### 1. Create Database Tables
Run: `http://localhost/Law_Enforcement_-_Incident_Report/setup_case_management_tables.php`

This will create:
- case_assignments
- bcpc_officers
- case_updates
- case_notifications
- case_timeline

### 2. Setup BCPC Officers
Run: `http://localhost/Law_Enforcement_-_Incident_Report/setup_bcpc_officers.php`

This will:
- Associate active users with barangay officer roles
- Set their rank and specialization
- Initialize their case load tracking

### 3. Add Sample Cases (Optional)
Run: `http://localhost/Law_Enforcement_-_Incident_Report/add_sample_cases.php`

This will:
- Create 4 sample cases for testing
- Assign them to different officers
- Populate the system with demo data

---

## 🎯 User Workflows

### Creating a New Case
1. Navigate to `admin/cases.php`
2. Click "Create New Case" button
3. Fill in case details:
   - Incident type (required)
   - Complainant name (required)
   - Incident date (required)
   - Description (required)
   - Optional: Respondent, location, time, priority
4. Select an officer to assign (optional)
5. Select barangay chairperson (optional)
6. Click "Create Case"
7. System will:
   - Generate case number automatically
   - Create timeline entry
   - Send notification to assigned officer (if assigned)

### Viewing Case Details
1. From `admin/cases.php`, click the eye icon on any case
2. Opens detailed case view showing:
   - All case information
   - Case timeline with all events
   - Updates and follow-up actions
   - Assignment information

### Updating Case Status
1. From `admin/cases.php`, click the edit icon
2. Select new status from dropdown
3. Add notes (optional)
4. Submit
5. System will:
   - Update case status
   - Create timeline entry
   - Create notification for assigned officer
   - Record update in case_updates table

### Adding Follow-up Action
1. From `admin/cases.php`, click the plus icon
2. Enter follow-up action description
3. Submit
4. System will:
   - Record follow-up action
   - Create timeline entry
   - Notify assigned officer
   - Record in case_updates table

### Reassigning a Case
1. From `admin/cases.php`, click the reassign icon (arrow icon)
2. Select new officer
3. Enter reason for reassignment (optional)
4. Submit
5. System will:
   - Update case assignment
   - Update officer case loads
   - Create timeline entry
   - Notify both old and new officers
   - Record reassignment action

---

## 📊 Statistics & Metrics

### Available Statistics (getCaseStatistics function)
- **Total Cases**: Total number of cases in system
- **Cases by Status**: 
  - New
  - Ongoing
  - Resolved
  - Closed
- **Active Officers**: Number of available officers

### Officer Metrics
- **Current Case Load**: Number of active cases assigned
- **Max Case Load**: Maximum cases officer can handle
- **Availability**: Whether officer is available for new assignments

---

## 🔧 Technical Implementation Details

### Key Functions in `includes/case_management.php`

| Function | Purpose |
|----------|---------|
| `generateCaseNumber()` | Creates unique sequential case numbers |
| `createCaseAssignment($data)` | Creates new case and sends notifications |
| `getCaseAssignments($filters)` | Retrieves cases with optional filters |
| `updateCaseStatus($id, $status, $by, $notes)` | Updates case status and creates timeline |
| `addFollowUpAction($id, $action, $by)` | Records follow-up action |
| `reassignCase($id, $officer, $by, $reason)` | Reassigns case to different officer |
| `addCaseTimeline($id, $num, $type, $desc, $by)` | Records timeline event |
| `createNotification($recipient, $case, $num, $type, $title, $msg)` | Sends notification to user |
| `getCaseTimeline($id)` | Retrieves all timeline events for case |
| `getCaseUpdates($id)` | Retrieves all updates/follow-ups for case |
| `getCaseStatistics()` | Retrieves dashboard statistics |

---

## 📱 Admin Interface Components

### Case Management Dashboard
- **Location**: `admin/cases.php`
- **Features**:
  - Statistics cards showing total cases, status breakdown, active officers
  - Advanced filter panel (status, priority, officer, date range)
  - Cases table with all case information
  - Action buttons: View, Update Status, Add Follow-up, Reassign

### Case Details Page
- **Location**: `modules/case_details.php`
- **Display**:
  - Case header with status, priority, type, creation date
  - Case information section
  - Timeline section with chronological events
  - Assignment information
  - Updates and actions section
  - Back button to cases list

---

## ✅ Verification Checklist

- [x] Auto case number generation working
- [x] Cases can be assigned to officers
- [x] Available officers list populated
- [x] Case status changes tracked
- [x] Follow-up actions recorded
- [x] Officer notifications sent on assignment
- [x] Case timeline displays all events
- [x] Cases can be reassigned
- [x] Detailed case view page functional
- [x] All database tables created
- [x] Sample data can be loaded

---

## 🔗 Quick Links

- **Cases Management**: [http://localhost/Law_Enforcement_-_Incident_Report/admin/cases.php](http://localhost/Law_Enforcement_-_Incident_Report/admin/cases.php)
- **Case Details**: [http://localhost/Law_Enforcement_-_Incident_Report/modules/case_details.php?case_id=1](http://localhost/Law_Enforcement_-_Incident_Report/modules/case_details.php?case_id=1)
- **Setup Database**: [http://localhost/Law_Enforcement_-_Incident_Report/setup_case_management_tables.php](http://localhost/Law_Enforcement_-_Incident_Report/setup_case_management_tables.php)
- **Setup Officers**: [http://localhost/Law_Enforcement_-_Incident_Report/setup_bcpc_officers.php](http://localhost/Law_Enforcement_-_Incident_Report/setup_bcpc_officers.php)
- **Add Sample Data**: [http://localhost/Law_Enforcement_-_Incident_Report/add_sample_cases.php](http://localhost/Law_Enforcement_-_Incident_Report/add_sample_cases.php)

---

## 📝 Notes

- All case operations are logged in the timeline
- All notifications are stored in database for officer reference
- Officer case loads are automatically managed
- Case numbers are unique and auto-generated
- All dates and times are recorded for audit trail
- Notifications are created for all significant case events

---

**Implementation Date**: January 6, 2026
**Status**: Complete and Tested
