# Database Schema Fixes - Fullname Column References

## Summary
Fixed multiple database queries that were referencing `users.fullname` when the `fullname` column only exists in the `signup` table. This was causing SQLSTATE[42S22] "Unknown column" errors.

## Root Cause
The database schema has user information split across two tables:
- **users table**: Contains authentication data (user_id, username, password_hash, role, is_active, phone_number, email)
- **signup table**: Contains profile data (user_id, fullname, emailadd, username, password, role, created_at, email_verified)

The `fullname` column only exists in the `signup` table, but several queries were attempting to join with the `users` table to retrieve it.

## Files Fixed

### 1. includes/case_management.php
**Function: getCaseAssignments($filters)**
- **Lines**: 154-160
- **Issue**: Was joining `users` table to get fullname for assigned_by, assigned_to, and barangay_chairperson_id
- **Fix**: Changed to join `signup` table instead
```php
// Before:
LEFT JOIN users u1 ON ca.assigned_by = u1.user_id
LEFT JOIN users u2 ON ca.assigned_to = u2.user_id
LEFT JOIN users u3 ON ca.barangay_chairperson_id = u3.user_id

// After:
LEFT JOIN signup s1 ON ca.assigned_by = s1.user_id
LEFT JOIN signup s2 ON ca.assigned_to = s2.user_id
LEFT JOIN signup s3 ON ca.barangay_chairperson_id = s3.user_id
```

**Function: getAvailableBCPCOfficers()**
- **Lines**: 52
- **Issue**: Was selecting `u.fullname` from `users` table
- **Fix**: Added JOIN with `signup` table for fullname retrieval

**Function: getAllBCPCOfficers()**
- **Lines**: 76
- **Issue**: Was selecting `u.fullname` from `users` table
- **Fix**: Added JOIN with `signup` table for fullname retrieval

**Function: getCaseTimeline($id)**
- **Lines**: 424
- **Issue**: Was LEFT JOINing `users` table to get `fullname` for performed_by
- **Fix**: Changed to LEFT JOIN with `signup` table

**Function: getCaseUpdates($id)**
- **Lines**: 446
- **Issue**: Was LEFT JOINing `users` table to get `fullname` for updated_by
- **Fix**: Changed to LEFT JOIN with `signup` table

### 2. modules/case_details.php
**Main Query**
- **Lines**: 28-30
- **Issue**: Had 3 LEFT JOINs with `users` table trying to get fullname
- **Fix**: Changed all 3 JOINs to use `signup` table
```php
// Before:
LEFT JOIN users u1 ON ca.assigned_by = u1.user_id
LEFT JOIN users u2 ON ca.assigned_to = u2.user_id
LEFT JOIN users u3 ON ca.barangay_chairperson_id = u3.user_id

// After:
LEFT JOIN signup s1 ON ca.assigned_by = s1.user_id
LEFT JOIN signup s2 ON ca.assigned_to = s2.user_id
LEFT JOIN signup s3 ON ca.barangay_chairperson_id = s3.user_id
```

### 3. modules/CaseAssign.php
**Function: getCaseAssignments($filters)**
- **Lines**: 152-158
- **Issue**: Was joining `users` table to get fullname for case assignment names
- **Fix**: Changed to join `signup` table

**Function: getCaseTimeline()**
- **Issue**: Was LEFT JOINing `users` table
- **Fix**: Changed to LEFT JOIN with `signup` table

**Function: getCaseUpdates()**
- **Issue**: Was LEFT JOINing `users` table
- **Fix**: Changed to LEFT JOIN with `signup` table

**Function: getAvailableBCPCOfficers()**
- **Lines**: 49
- **Issue**: Was selecting `u.fullname` from `users` table
- **Fix**: Added JOIN with `signup` table

**Function: getAllBCPCOfficers()**
- **Lines**: 73
- **Issue**: Was selecting `u.fullname` from `users` table
- **Fix**: Changed to JOIN with `signup` table

### 4. debug_blotter.php
**Query**
- **Lines**: 11-15
- **Issue**: Was using `u.fullname` from `users` table
- **Fix**: Changed to join `signup` table
```php
// Before:
LEFT JOIN users u ON u.user_id = b.officer_id

// After:
LEFT JOIN signup s ON s.user_id = b.officer_id
```

## Files Already Correct
The following files were already using the correct table structure:

### modules/Incident_report.php
- **Lines**: 275-276
- Status: ✅ Already joining `signup` table correctly
- Queries use CONCAT(u.username, u.fullname) from signup table aliases

### admin/cases.php
- **Line**: 99
- Status: ✅ Already querying from `signup` table directly
- Query: `SELECT user_id, fullname FROM signup WHERE role IN ...`

### includes/suspect_witness_management.php
- Status: ✅ Uses `username` from `users` table (which exists in both tables)
- No changes needed - username is available in both users and signup tables

## Testing Verification
All pages tested and confirmed working:
- ✅ modules/case_details.php?case_id=1 - Loads without errors
- ✅ admin/cases.php - Case management dashboard loads successfully
- ✅ Case assignments display with correct user names
- ✅ Case timeline shows performer names correctly
- ✅ Case updates show updater names correctly
- ✅ BCPC officers lists display with full names

## Summary of Changes
- **Total functions fixed**: 9
- **Files modified**: 4
- **Files verified**: 3
- **Total JOIN statements updated**: 15+
- **Error pattern fixed**: SQLSTATE[42S22] Unknown column 'u*.fullname' in 'field list'

## Important Notes
1. The `signup` table contains both `username` and `fullname` columns, making it the authoritative source for user display names
2. The `users` table is used for authentication and system information but lacks `fullname`
3. For future development, always join `signup` table when displaying user full names
4. Both `username` and `email` fields can be retrieved from either table, but `fullname` must come from `signup`
