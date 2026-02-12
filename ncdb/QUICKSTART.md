# NCDB Quick Start Guide

## 🚀 Get Started in 5 Minutes

### 1. Import Database Schema (2 minutes)

Open phpMyAdmin and run this SQL:

```sql
-- Copy entire contents of ncdb/config/ncdb_schema.sql and paste here
```

Or use command line:
```bash
mysql -u your_username -p your_database < ncdb/config/ncdb_schema.sql
```

### 2. Verify Tables Created (1 minute)

In phpMyAdmin, check that these tables exist:
- ✓ ncdb_connections
- ✓ ncdb_cache
- ✓ ncdb_access_logs
- ✓ ncdb_sync_history
- ✓ ncdb_duplicate_detection
- ✓ ncdb_verification_results
- ✓ ncdb_rate_limits

### 3. Test System (1 minute)

Navigate to: `/ncdb/views/test.php`

Click "Run All Tests" and verify:
- ✓ Configuration Tests: PASS
- ✓ Database Tables Test: PASS
- ✓ Security Tests: PASS

### 4. Add First Connection (1 minute)

Go to: `/ncdb/views/admin_dashboard.php`

Fill in:
- **Connection Name:** "Test PNP Database"
- **API Endpoint:** `https://ncdb.pnp.gov.ph/api/v1`
- **Connection Type:** REST API
- **API Key:** Your key here
- **API Secret:** Your secret here

Click "Add Connection"

### 5. Test Connection (1 minute)

Back in Admin Dashboard:
- Find your connection
- Click "Test"
- Verify status shows "Active" ✓

## ✅ You're Ready!

Access the NCDB system:

| Page | URL | Purpose |
|------|-----|---------|
| **Verification** | `/ncdb/views/index.php` | Verify records against NCDB |
| **Admin Dashboard** | `/ncdb/views/admin_dashboard.php` | Manage connections & settings |
| **Testing** | `/ncdb/views/test.php` | Run system tests |

## 🎯 First Verification

1. Go to `/ncdb/views/index.php`
2. Select **Record Type:** "Blotter"
3. Select a **Record** from dropdown
4. Choose **Verification Type:** "Identity Verification"
5. Click **"Verify Record"**

System will:
- Query NCDB
- Check for duplicates
- Display matches
- Log operation

## 📊 Monitor Activity

View what's happening:

1. **Admin Dashboard** → **Access Logs** tab
   - See all verifications
   - Check success/failure rates
   - Monitor suspicious activity

2. **Admin Dashboard** → **Sync History** tab
   - Track synchronization events
   - Monitor data processing

## 🔒 Security Features (Automatic)

- ✓ All API credentials encrypted
- ✓ All operations logged
- ✓ Duplicate detection active
- ✓ Rate limiting enforced
- ✓ Suspicious activity monitored

## ⚠️ Important Notes

1. **Encryption Key**
   - Change from default in production
   - Set `NCDB_ENCRYPTION_KEY` environment variable

2. **API Credentials**
   - Always use HTTPS
   - Store credentials securely
   - Rotate quarterly

3. **Access Control**
   - Only Officers and Admins can access
   - All actions are logged
   - Reviews required for sensitive operations

## 🆘 Troubleshooting

**Tables not found?**
→ Run the SQL schema file again

**Connection fails?**
→ Check API endpoint URL
→ Verify API key and secret
→ Check firewall/VPN settings

**Encryption errors?**
→ Verify `NCDB_ENCRYPTION_KEY` is set
→ Check PHP openssl extension is enabled

**No records showing?**
→ Ensure blotters/cases exist in database
→ Check user has proper permissions

## 📚 Learn More

See [README.md](./README.md) for complete documentation.

---

**Next Steps:**
1. ✅ Complete setup above
2. 📖 Review [README.md](./README.md) for detailed features
3. 🔧 Configure settings in Admin Dashboard
4. 🧪 Run tests regularly
5. 📊 Monitor access logs for activity
