# Suspect and Witness Management System
## Implementation Guide

---

## Overview

The Suspect and Witness Management system provides law enforcement with a secure, organized way to record, track, and manage information about suspects and witnesses connected to criminal cases. The system integrates seamlessly with the Case Management system and ensures sensitive data is properly controlled and audited.

---

## ✓ Implemented Features

### 1. **Suspect Information Forms**
- **Location**: `modules/suspects_management.php`
- **Accessible from**: Case Details page → "Add Suspect" button
- **Features**:
  - Comprehensive form with all relevant fields
  - Support for basic identification details
  - Criminal history tracking
  - Alias recording
  - Status management

### 2. **Witness Information Forms**
- **Location**: `modules/witnesses_management.php`
- **Accessible from**: Case Details page → "Add Witness" button
- **Features**:
  - Detailed witness information collection
  - Statement recording
  - Reliability assessment
  - Witness type classification
  - Court availability tracking
  - Witness protection indicator

### 3. **Record Basic Details**
Both forms capture:
- **Personal Information**: First, middle, last name
- **Demographics**: Age, date of birth, gender
- **Location**: Complete address, barangay, city, province, ZIP code
- **Contact Information**: Phone number, email
- **Identification**: ID type, ID number
- **Additional Details**: Special notes, remarks, history

### 4. **Case Linking**
- Every suspect and witness is automatically linked to a specific case
- Case number is recorded with each record
- Viewing suspects/witnesses always filtered by case_id
- Ensures data organization and case-based access

### 5. **Record Updates & History**
- **Suspect Updates Table**: Tracks all changes to suspect records
- **Witness Updates Table**: Tracks all changes to witness records
- **Audit Trail**: Records who made changes and when
- **Update Types**: Record Created, Record Updated, Record Deleted

### 6. **Secure Access Control**
- **Admin-only Access**: Pages check for Admin role via session
- **403 Forbidden Response**: Unauthorized users denied access
- **Session Validation**: User ID and role verified on each page
- **Data Isolation**: Users can only see data linked to their case

### 7. **Case-Linked Views**
- **Case Details Page**: Shows summary of suspects and witnesses
- **List Display**: Quick view tables with key information
- **Edit Access**: Click to edit any suspect or witness record
- **Count Badges**: Visual indicators showing number of suspects/witnesses

### 8. **Status & Reliability Tracking**
- **Suspect Statuses**: Active, Arrested, Released, Deceased, Unknown
- **Witness Types**: Direct, Indirect, Hearsay, Character
- **Witness Reliability**: High, Medium, Low ratings
- **Protection Needs**: Flag if witness needs protection
- **Court Availability**: Track if witness can appear in court

---

## 📋 Database Schema

### **suspects** Table
```sql
CREATE TABLE `suspects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `case_number` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100),
  `last_name` VARCHAR(100) NOT NULL,
  `age` INT,
  `date_of_birth` DATE,
  `gender` ENUM('Male', 'Female', 'Other'),
  `address` VARCHAR(255),
  `barangay` VARCHAR(100),
  `city` VARCHAR(100),
  `province` VARCHAR(100),
  `zip_code` VARCHAR(10),
  `contact_number` VARCHAR(20),
  `email` VARCHAR(150),
  `id_type` VARCHAR(50),
  `id_number` VARCHAR(100),
  `physical_description` TEXT,
  `known_aliases` VARCHAR(255),
  `criminal_history` TEXT,
  `remarks` TEXT,
  `status` ENUM('Active', 'Arrested', 'Released', 'Deceased', 'Unknown'),
  `created_by` INT NOT NULL,
  `updated_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_suspects_case_id` (`case_id`),
  INDEX `idx_suspects_status` (`status`)
)
```

### **witnesses** Table
```sql
CREATE TABLE `witnesses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `case_number` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100),
  `last_name` VARCHAR(100) NOT NULL,
  `age` INT,
  `date_of_birth` DATE,
  `gender` ENUM('Male', 'Female', 'Other'),
  `address` VARCHAR(255),
  `barangay` VARCHAR(100),
  `city` VARCHAR(100),
  `province` VARCHAR(100),
  `zip_code` VARCHAR(10),
  `contact_number` VARCHAR(20),
  `email` VARCHAR(150),
  `id_type` VARCHAR(50),
  `id_number` VARCHAR(100),
  `relationship_to_case` VARCHAR(100),
  `witness_type` ENUM('Direct', 'Indirect', 'Hearsay', 'Character'),
  `statement` TEXT,
  `reliability` ENUM('High', 'Medium', 'Low'),
  `available_for_court` BOOLEAN DEFAULT TRUE,
  `protection_needed` BOOLEAN DEFAULT FALSE,
  `remarks` TEXT,
  `created_by` INT NOT NULL,
  `updated_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_witnesses_case_id` (`case_id`),
  INDEX `idx_witnesses_reliability` (`reliability`)
)
```

### **suspect_updates** Table
```sql
CREATE TABLE `suspect_updates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `suspect_id` INT NOT NULL,
  `update_type` VARCHAR(50),
  `update_description` TEXT,
  `updated_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

### **witness_updates** Table
```sql
CREATE TABLE `witness_updates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `witness_id` INT NOT NULL,
  `update_type` VARCHAR(50),
  `update_description` TEXT,
  `updated_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

---

## 🚀 Setup Steps

### Step 1: Create Database Tables
Run: `http://localhost/Law_Enforcement_-_Incident_Report/setup_suspect_witness_tables.php`

This will create:
- suspects
- witnesses
- suspect_updates
- witness_updates
- Necessary indexes

### Step 2: System is Ready
Once tables are created, the suspect and witness management system is immediately available from the Case Details page.

---

## 📖 User Workflows

### Adding a New Suspect

1. Navigate to Case Details page for desired case
2. Click "Add Suspect" button in Suspects section
3. Fill in suspect information:
   - **Required**: First name, Last name
   - **Optional**: Middle name, age, DOB, gender, address details, contact info, ID info, physical description, aliases, criminal history, remarks
4. Select suspect status (Active, Arrested, Released, Deceased, Unknown)
5. Click "Add Suspect"
6. Suspect added to case and displayed in the list

### Updating Suspect Information

1. From Case Details or Suspects Management page, click "Edit" on the suspect
2. Modify any information
3. Click "Update Suspect"
4. Changes recorded with timestamp and user who made the change

### Adding a New Witness

1. Navigate to Case Details page for desired case
2. Click "Add Witness" button in Witnesses section
3. Fill in witness information:
   - **Required**: First name, Last name
   - **Optional**: Personal details (address, contact, ID, etc.)
   - **Case Details**: Relationship to case, witness type, statement
   - **Flags**: Reliability rating, court availability, protection needed
4. Click "Add Witness"
5. Witness added to case and displayed in the list

### Updating Witness Information

1. From Case Details or Witnesses Management page, click "Edit" on the witness
2. Modify statement, reliability, availability, protection status, or personal information
3. Click "Update Witness"
4. Changes recorded with audit trail

### Viewing Case Summary

1. Go to Case Details page (`modules/case_details.php?case_id=X`)
2. View:
   - Suspects section with count badge
   - Witnesses section with count badge
   - Quick view tables showing key information
   - Links to add or edit records

---

## 🔧 Technical Implementation

### Core Functions in `includes/suspect_witness_management.php`

#### Suspect Functions
| Function | Purpose |
|----------|---------|
| `createSuspect($data)` | Create new suspect record |
| `getSuspectById($id)` | Retrieve single suspect |
| `getSuspectsByCase($case_id)` | Get all suspects for case |
| `updateSuspect($id, $data, $by)` | Modify suspect information |
| `deleteSuspect($id, $by)` | Mark suspect as deleted |
| `addSuspectUpdate($id, $type, $desc, $by)` | Log suspect update |
| `getSuspectUpdates($id)` | Get update history |

#### Witness Functions
| Function | Purpose |
|----------|---------|
| `createWitness($data)` | Create new witness record |
| `getWitnessById($id)` | Retrieve single witness |
| `getWitnessesByCase($case_id)` | Get all witnesses for case |
| `updateWitness($id, $data, $by)` | Modify witness information |
| `deleteWitness($id, $by)` | Remove witness record |
| `addWitnessUpdate($id, $type, $desc, $by)` | Log witness update |
| `getWitnessUpdates($id)` | Get update history |

#### Summary Function
| Function | Purpose |
|----------|---------|
| `getCaseSuspectWitnessSummary($case_id)` | Get counts and all records |

---

## 🔒 Security Features

### Access Control
- **Admin-Only Pages**: Both management pages require Admin role
- **Session Verification**: User ID and role checked on page load
- **Unauthorized Denial**: 403 Forbidden response for non-admins
- **Data Isolation**: Records filtered by case_id to prevent unauthorized access

### Data Integrity
- **Audit Trail**: All changes logged with timestamp and user
- **Update History**: Complete history of modifications preserved
- **Soft Deletes**: Suspects marked as deleted, not removed
- **Prepared Statements**: Protection against SQL injection

### Data Protection
- **Sensitive Information**: Criminal history, aliases, statements properly stored
- **Contact Information**: Protected from public access
- **Identification Numbers**: Secure recording
- **Protection Flags**: Witness protection needs clearly marked

---

## 📊 Data Fields Reference

### Suspect Key Fields
- **Status Tracking**: Active, Arrested, Released, Deceased, Unknown
- **Identification**: ID type (Driver's License, Passport, etc.) and number
- **Physical Description**: For identification purposes
- **Criminal History**: Previous offenses or arrests
- **Aliases**: Known names suspect uses

### Witness Key Fields
- **Witness Type**: Direct (saw), Indirect (heard about), Hearsay, Character witness
- **Reliability Rating**: High, Medium, Low credibility assessment
- **Statement**: Verbatim or summary of what witness said
- **Relationship**: How witness is connected to case (neighbor, friend, victim)
- **Protection**: Flag if witness needs safety measures
- **Court Status**: Whether witness can testify in court

---

## 📱 User Interface Components

### Case Details Page
- Suspects section with count badge
- Witnesses section with count badge
- Quick-view tables with essential information
- "Add" buttons for creating new records
- "Edit" buttons for modifying existing records

### Suspects Management Page
- Left column: Form for creating/editing suspects
- Right column: List of suspects for this case
- Form fields include all personal, location, and case details
- Status dropdown for tracking suspect state
- Criminal history and remarks for narrative information

### Witnesses Management Page
- Left column: Form for creating/editing witnesses
- Right column: List of witnesses for this case
- Form fields include personal details, statement, and flags
- Witness type selector (Direct, Indirect, Hearsay, Character)
- Reliability assessment dropdown
- Checkboxes for court availability and protection needs

---

## 🔗 Quick Links

- **Case Details**: [Case Details Page](modules/case_details.php?case_id=1)
- **Suspects Management**: [Manage Suspects](modules/suspects_management.php?case_id=1)
- **Witnesses Management**: [Manage Witnesses](modules/witnesses_management.php?case_id=1)
- **Setup Database**: [Initialize Tables](setup_suspect_witness_tables.php)

---

## ✅ Verification Checklist

- [x] Database tables created successfully
- [x] Helper functions working for CRUD operations
- [x] Suspect forms functional
- [x] Witness forms functional
- [x] Case linking working
- [x] Access control enforced
- [x] Audit trails recording
- [x] UI components integrated into case details
- [x] Edit/update functionality working
- [x] Status and reliability tracking working

---

## 📝 Notes

- All suspect and witness records are tied to specific cases
- Updates create audit log entries with timestamps
- Admin-only access prevents unauthorized viewing
- Forms validate required fields
- Support for multiple suspects and witnesses per case
- Comprehensive history tracking for compliance and audit purposes

---

**Implementation Date**: January 6, 2026  
**Status**: Complete and Tested  
**Requires**: Admin authentication and active case
