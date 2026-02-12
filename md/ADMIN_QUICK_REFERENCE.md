# Admin Panel Quick Reference

## 🚀 Quick Start (5 minutes)

### 1. Create First Admin User
```
URL: /admin/setup.php
→ Select user from dropdown
→ Click "Promote to Admin"
→ Done! ✅
```

### 2. Log in as Admin
```
Login page: /auth/login.php
Use admin account credentials
→ You'll see "Admin Panel" in sidebar
```

### 3. Access Admin Dashboard
```
Click "Admin Panel" in sidebar
OR navigate to: /admin/dashboard.php
```

---

## 📍 Admin Panel Navigation

### Dashboard (`/admin/dashboard.php`)
- **5 KPI Cards**: Users, Blotters, Verification Status
- **4 Quick Links**: Users, Blotters, Reports, Settings
- **Recent Activity**: Latest blotters and signups

### User Management (`/admin/users.php`)
- **List**: All users with details
- **Action 1**: Verify Email ✓
- **Action 2**: Delete User 🗑️

### Blotter Records (`/admin/blotters.php`)
- **Filter**: By Status (Pending, Investigating, Resolved, Archived)
- **Display**: All incident records
- **Action**: View full details

### Reports (`/admin/reports.php`)
- **Statistics**: Users, Blotters, Verification rates
- **Charts**: Priority distribution, Status breakdown
- **Export**: Print or CSV (coming soon)

### Settings (`/admin/settings.php`)
- **Email Config**: SMTP settings
- **System Options**: Verification, 2FA, Terms
- **Database Info**: Size, version, tables

---

## 💡 Common Tasks

### Task: Verify an Unverified User
```
1. Go to User Management
2. Find user with "Unverified" status
3. Click "Verify Email" button
4. Status updates to verified ✓
```

### Task: View Incident Report
```
1. Go to Blotter Records
2. Find incident in table
3. Click "View" button
4. See full details including description
```

### Task: Check System Health
```
1. Go to Dashboard
2. Check KPI cards:
   - Unverified users (should be low)
   - Pending blotters (should be actioned)
3. Go to Reports for detailed stats
```

### Task: Change Email Settings
```
1. Go to Settings
2. Update SMTP settings if needed
3. Use modal to change password
4. Click "Save Email Settings"
```

### Task: Delete User Account
```
1. Go to User Management
2. Click "Delete" on user row
3. Confirm in popup dialog
4. User is permanently removed
```

---

## 🎯 Key Metrics at a Glance

| Metric | Location | What It Means |
|--------|----------|----------------|
| Total Users | Dashboard | All user accounts in system |
| Verified Users | Dashboard | Users who confirmed email |
| Unverified Users | Dashboard | Users pending email verification |
| Total Blotters | Dashboard | All incident reports |
| Pending Blotters | Dashboard | Reports awaiting action |
| Verification Rate | Reports | % of users verified (should be high) |
| Resolution Rate | Reports | % of blotters resolved |

---

## ⚠️ Important Notes

### Security
- ✓ Only admins can access these pages
- ✓ All queries use prepared statements
- ✓ User data is encrypted in database
- ✓ SMTP password is hidden

### Data Management
- ⚠️ Deleting users is **PERMANENT**
- ⚠️ Always verify user before deleting
- ✓ Admin actions are logged
- ✓ Can restore from backup if needed

### Email System
- SMTP Host: `smtp.gmail.com`
- SMTP Port: `465` (SSL)
- Verification Tokens: 24-hour expiry
- Can resend verification via admin panel

---

## 🔍 Troubleshooting

### Problem: Can't access admin panel
**Solution**: Check if user has `role = 'Admin'` in database
```sql
SELECT role FROM signup WHERE user_id = [your_id];
```
If not admin:
- Go to `/admin/setup.php`
- Click "Promote to Admin"

### Problem: Statistics showing 0
**Solution**: 
- Wait for users to sign up
- Check database connection
- Verify query in Reports page

### Problem: Email verification not working
**Solution**:
- Check SMTP settings in Settings page
- Verify email credentials
- Check inbox for spam folder

### Problem: Users can't verify email
**Solution**:
- Check token expiry (default 24 hours)
- Resend verification email
- Check user's email for verification link

---

## 📊 Dashboard Reading Guide

### KPI Cards (Top)
```
[Total Users]      → Total registered users
[Verified Users]   → Users who verified email
[Unverified Users] → Users pending verification
[Total Blotters]   → All incident reports
[Pending Blotters] → Incidents awaiting action
```

### Admin Menu (Middle)
```
[Users]     → Manage user accounts
[Blotters]  → View incident reports
[Reports]   → Analytics & statistics
[Settings]  → System configuration
```

### Recent Activity (Bottom)
```
Last 5 Blotters   → Latest incident reports
Last 5 Users      → Latest registered users
```

---

## 🎨 Color Legend

### Status Badges
- 🟨 Yellow = Pending
- 🔵 Blue = Under Investigation
- 🟢 Green = Resolved
- ⚪ Gray = Archived

### Priority Badges
- 🔴 Red = High Priority
- 🟨 Yellow = Medium Priority
- 🔵 Blue = Low Priority

### Progress Bars
- Verification Rate: % users verified
- Acceptance Rate: % terms accepted

---

## 📋 Admin Checklist

Daily:
- [ ] Check Dashboard for pending blotters
- [ ] Review new signups
- [ ] Verify any unverified users

Weekly:
- [ ] Review Reports page
- [ ] Check unverified users
- [ ] Verify system health

Monthly:
- [ ] Review analytics trends
- [ ] Update system settings if needed
- [ ] Archive old blotters

---

## 🔗 Useful URLs

| Page | URL |
|------|-----|
| Admin Dashboard | `/admin/dashboard.php` |
| User Management | `/admin/users.php` |
| Blotter Records | `/admin/blotters.php` |
| Reports | `/admin/reports.php` |
| Settings | `/admin/settings.php` |
| Admin Setup | `/admin/setup.php` |
| Main Dashboard | `/index.php` |
| Main Login | `/auth/login.php` |

---

## 💬 Quick Tips

✨ **Pro Tips**:
1. Use filters in Blotter Records to focus on pending items
2. Check Reports weekly for system insights
3. Verify users regularly to improve verification rate
4. Keep email settings updated in Settings page
5. Monitor total users in dashboard for growth

🚀 **Keyboard Shortcuts**:
- Can't apply yet, but all buttons clearly labeled
- Use breadcrumb "Back" buttons to navigate
- Print reports for offline review

📱 **Mobile Access**:
- All pages are mobile-responsive
- Can manage system from smartphone
- Touch-friendly buttons and inputs

---

## ❓ FAQ

**Q: How do I become an admin?**
A: Go to `/admin/setup.php` and promote your account

**Q: Can I undo deleting a user?**
A: Only from database backup. Use caution!

**Q: How long do email tokens last?**
A: Default 24 hours, configurable in Settings

**Q: Can users bypass email verification?**
A: No, it's required to login (can be disabled in Settings)

**Q: Where are SMTP passwords stored?**
A: In config/mail_env.php (never shown in UI)

---

**Last Updated**: 2024  
**Admin Panel Version**: 1.0

For more help, see [Admin README](README.md)
