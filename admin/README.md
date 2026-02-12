# Admin Panel Guide

## Overview
The Admin Panel provides administrators with complete control and oversight of the Law Enforcement Incident Report System.

## Access
- **URL**: `/admin/dashboard.php`
- **Requirements**: User account with `role = 'Admin'` in the database
- **Navigation**: Admin users will see an "Admin Panel" link in the sidebar dashboard

## Features

### 1. **Admin Dashboard** (`admin/dashboard.php`)
Main admin overview page with:
- **5 KPI Cards**:
  - Total Users
  - Verified Users  
  - Unverified Users
  - Total Blotters
  - Pending Blotters
- **Admin Menu** (4 sections):
  - User Management
  - Blotter Records
  - Reports & Analytics
  - System Settings
- **Recent Activity Tables**:
  - Last 5 blotters created
  - Last 5 user signups

### 2. **User Management** (`admin/users.php`)
Complete user account management:
- **List All Users**: View all non-admin users with details
  - Full Name
  - Email Address
  - Username
  - Verification Status
  - Role
  - Signup Date
- **Actions**:
  - **Verify Email**: Toggle email verification status for unverified users
  - **Delete User**: Permanently remove user accounts (with confirmation)

### 3. **Blotter Records** (`admin/blotters.php`)
Comprehensive blotter/incident report management:
- **Filter by Status**:
  - All Records
  - Pending
  - Under Investigation
  - Resolved
  - Archived
- **View Blotter Details**:
  - Blotter Number
  - Complainant Name
  - Incident Type
  - Location
  - Status (color-coded badge)
  - Priority (color-coded badge)
  - Creation Date
- **Actions**:
  - View full blotter details in detail page

**Status Colors**:
- Yellow: Pending
- Blue: Under Investigation
- Green: Resolved
- Gray: Archived

**Priority Colors**:
- Red: High
- Yellow: Medium
- Blue: Low

### 4. **Reports & Analytics** (`admin/reports.php`)
System-wide analytics and statistics:

**User Statistics**:
- Total Users Count
- Verified Users Count
- Unverified Users Count
- Terms Accepted Count
- Verification Rate %
- Terms Acceptance Rate %

**Blotter Statistics**:
- Status Breakdown (Pending, Investigating, Resolved, Archived)
- Priority Distribution (High, Medium, Low)
- Resolution Rate %
- Total Blotters

**Summary Metrics**:
- Total Blotters
- Resolution Rate Percentage
- Average Resolution Time
- Total Users

**Export Options**:
- Export as CSV (coming soon)
- Print Report

### 5. **System Settings** (`admin/settings.php`)
Configure system parameters and view system information:

**Email Settings**:
- SMTP Host (default: smtp.gmail.com)
- SMTP Port (default: 465)
- Email Address (default: alertaraqc@gmail.com)
- Change Password Modal for SMTP credentials

**System Settings**:
- Email Verification Required (toggle)
- Terms Acceptance Required (toggle)
- Two-Factor Authentication (toggle)
- Email Token Expiry Hours (default: 24)

**Admin Users**:
- View list of all admin accounts
- Full Name, Email, Username
- Account creation date

**System Information**:
- PHP Version
- Server Software
- Database Type
- Framework (Bootstrap 5)

**Database Information**:
- Database Name
- MySQL Version
- Database Size (MB)
- Total Tables

## User Flows

### Creating an Admin User
1. Sign up as regular user
2. Manually update database:
   ```sql
   UPDATE signup SET role = 'Admin' WHERE user_id = [user_id];
   ```
3. Log in - Admin Panel link will appear in sidebar

### Email Verification
- **Admins can verify unverified users**:
  1. Go to User Management
  2. Find user with unverified status
  3. Click "Verify Email" button
  4. User's `email_verified` status updates to 1
  5. User can now log in

### Viewing Blotter Details
1. Go to Blotter Records
2. Filter by desired status (optional)
3. Click "View" button on any blotter
4. See full details including complete description

## Security Notes

⚠️ **Important**:
- Admin accounts should be carefully controlled
- Only trusted users should have Admin role
- Changing system settings affects all users
- Deleting users is permanent
- SMTP password is not displayed for security (change via modal)

## Database Columns Used

**Signup Table**:
- `user_id` - Primary key
- `fullname` - User's full name
- `email` - Email address
- `username` - Username
- `role` - User role ('Admin' or other)
- `email_verified` - Email verification status (0/1)
- `terms_accepted` - Terms acceptance (0/1)
- `created_at` - Account creation timestamp

**Blotters Table**:
- `id` - Primary key
- `blotter_no` - Blotter number
- `complainant_name` - Complainant name
- `incident_type` - Type of incident
- `location` - Incident location
- `status` - Blotter status
- `priority` - Incident priority
- `description` - Full incident description
- `created_at` - Creation timestamp

## Troubleshooting

**"Admin access denied" message**:
- Verify user has `role = 'Admin'` in database
- Log out and log back in

**Statistics showing 0**:
- Wait for users to sign up / blotters to be created
- Check database connection

**Email settings not saving**:
- Check database write permissions
- Verify SMTP credentials are correct
- Test with valid Gmail app password

## Next Steps

Planned enhancements:
- Export to CSV functionality
- Advanced filtering and search
- User activity logs
- Bulk user import
- Email notification templates
- Custom report builder
