# Suspect Photo Management - Implementation Guide

## Overview
This feature adds photo upload capability to the Suspect Management system, allowing law enforcement officers to attach photographs of suspects when creating or updating suspect records.

## Features Added

### 1. **Photo Upload in New Suspect Form**
   - File upload field with drag-and-drop support
   - Accepted formats: JPG, PNG, GIF, WebP
   - Real-time photo preview as you select an image
   - Maximum file size: 5MB recommended

### 2. **Photo Display in Form**
   - Shows current photo when editing a suspect
   - Placeholder icon when no photo is available
   - Easy to change or update photos

### 3. **Photo Display in Suspect List**
   - 80x100px thumbnail photos displayed next to suspect information
   - Quick visual identification of suspects
   - Organized layout with suspect details

### 4. **Photo Management**
   - Photos automatically deleted when updated (no orphaned files)
   - Secure storage in `/uploads/suspects/` directory
   - Automatic directory creation on first use

## Installation Steps

### Step 1: Run Setup Script
Open in your browser or run from terminal:
```
http://localhost/Law_Enforcement_-_Incident_Report/setup_suspect_photo.php
```

This script will:
- Add `photo_path` column to the suspects table
- Create `/uploads/suspects/` directory
- Set proper security permissions

### Step 2: Verify Installation
Check that the setup completed successfully. You should see green checkmarks indicating:
- ✓ photo_path column added to suspects table
- ✓ uploads/suspects directory created
- ✓ .htaccess security file created

## File Structure

### Modified Files:
1. **modules/suspects_management.php**
   - Added photo upload form field
   - Added photo preview functionality
   - Updated suspect list to display thumbnails
   - Added JavaScript for real-time preview

2. **includes/suspect_witness_management.php**
   - Updated `createSuspect()` function to handle photo_path
   - Updated `updateSuspect()` function to handle photo_path

### New Files:
1. **setup_suspect_photo.php**
   - Database migration script
   - Directory creation and security setup

2. **uploads/suspects/** (directory)
   - Stores all suspect photos
   - Protected with .htaccess for security

## Database Changes

### Schema Update:
```sql
ALTER TABLE suspects 
ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL 
AFTER known_aliases;
```

The `photo_path` column stores the relative path to the photo file:
- Example: `uploads/suspects/suspect_1705600000_507abc123.jpg`

## Usage

### Adding a Suspect with Photo:
1. Navigate to Case Management → Suspects Management
2. Click "Add New Suspect"
3. Fill in suspect information
4. In the "Suspect Photo" section, click to upload or drag a photo
5. See instant preview of the photo
6. Complete the form and click "Add Suspect"

### Updating Suspect Photo:
1. Click "Edit" on an existing suspect
2. Current photo is displayed
3. Upload a new photo to replace it
4. Old photo is automatically deleted
5. Click "Update Suspect"

### Viewing Photos:
- Photos appear as thumbnails (80x100px) in the suspects list
- Click "Edit" to see full photo with original dimensions

## Technical Details

### Supported Formats:
- JPEG (.jpg, .jpeg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)

### File Naming Convention:
Photos are stored with unique names:
```
suspect_[timestamp]_[random].ext
Example: suspect_1705600000_507abc123.jpg
```

This prevents:
- Filename conflicts
- Overwriting of existing files
- File enumeration attacks

### Security Features:
1. **File Type Validation**
   - Only image files accepted
   - MIME type and extension checking

2. **.htaccess Protection**
   - Only image files can be accessed
   - PHP execution disabled
   - Prevents unauthorized access

3. **Size Restrictions**
   - 5MB recommended maximum
   - Validation on both client and server

## Troubleshooting

### Photos Not Uploading?
1. Check that `/uploads/suspects/` directory exists
2. Verify directory has write permissions (755)
3. Check server's maximum file upload size in php.ini

### Photos Not Showing?
1. Verify the image file exists in `/uploads/suspects/`
2. Check file path in database for photo_path column
3. Ensure relative paths are correct

### Setup Script Errors?
Run this to check database connection:
```php
<?php require 'config/db_connect.php'; echo "Connected!"; ?>
```

## Database Rollback (if needed)

If you need to remove the photo feature:
```sql
ALTER TABLE suspects DROP COLUMN photo_path;
```

You can then safely delete the `/uploads/suspects/` directory.

## Performance Considerations

- Photos are stored on the server file system (not database)
- Thumbnails load quickly from browser cache
- Consider compression if handling many large photos
- Regular cleanup of old photos recommended for disk space

## Future Enhancements

Potential improvements for future versions:
1. Photo crop/resize functionality
2. Multiple photos per suspect
3. Photo compression on upload
4. Photo galleries
5. Integration with facial recognition

## Support

For issues or questions:
1. Check this guide's troubleshooting section
2. Review browser console for JavaScript errors
3. Check server error logs
4. Verify database schema changes were applied

---
**Feature Implementation Date:** January 2026
**Tested with:** Bootstrap 5.3.0, PHP 7.4+, MySQL 5.7+
