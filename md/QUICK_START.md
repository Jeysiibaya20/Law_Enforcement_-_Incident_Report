# NLP System - One-Click Setup & Test

## 🚀 Setup (5 Minutes)

### Step 1: Activate
Open in browser:
```
http://localhost/Law_Enforcement_-_Incident_Report/activate_nlp.php
```
Click "Activate NLP System" → Wait for success ✅

### Step 2: Test
Go to: http://localhost/Law_Enforcement_-_Incident_Report/modules/Incident_report.php

Create incident with narrative:
```
"A witness reported seeing the suspect fleeing the crime scene armed with a 
weapon. The victim appeared frightened and traumatized by the violent assault."
```

Click Submit → System automatically:
- ✅ Analyzes narrative (NLP)
- ✅ Creates blotter entry
- ✅ Assigns to officer
- ✅ Sends notifications
- ✅ Logs workflow events

### Step 3: Verify
Check incident list - should show:
- Severity: 85/100
- Threat Level: HIGH
- Sentiment: NEGATIVE

---

## 🧠 What NLP Does

### Analyzes Narrative For:

| Feature | Example | Range |
|---------|---------|-------|
| **Severity** | "armed", "violent" | 0-100 |
| **Threat Level** | HIGH if severity > 70 | LOW / MED / HIGH |
| **Sentiment** | "frightened", "angry" | Negative / Neutral / Positive |
| **Emotion** | angry, scared, calm | Emotion list |
| **Entities** | suspect, weapon, location | Keywords extracted |
| **Confidence** | How sure analysis is | 0-100% |

---

## 🔧 If Something Goes Wrong

### Error: "Table doesn't exist"
**Fix**: Run activate_nlp.php again

### Error: "Foreign key constraint"
**Status**: ✅ Already fixed in code, shouldn't happen

### NLP not working?
**Check**:
1. Does workflow_events table exist?
   ```sql
   SHOW TABLES LIKE 'workflow_events';
   ```
2. Are IncidentWorkflowManager classes included?
3. Check PHP error log: `C:\xampp\logs\php_error.log`

---

## 📊 Database Check

After activation, verify all tables exist:
```sql
SHOW TABLES LIKE '%notification%';
SHOW TABLES LIKE '%workflow%';
SHOW TABLES LIKE '%review%';
SHOW TABLES LIKE '%nlp%';
```

All should return results ✅

---

## 🎯 Example Incident Test Cases

### Test 1: Low Severity
```
"Noisy neighbor complaint. Dog barking at night."
```
Expected: Severity ~20, Threat: LOW ✅

### Test 2: Medium Severity
```
"Suspect shoplifting merchandise. No weapons involved. 
Witness claims suspect ran away when confronted."
```
Expected: Severity ~45, Threat: MEDIUM ✅

### Test 3: High Severity
```
"Armed robbery reported. Suspect threatening cashier with gun. 
Witness victim appears extremely frightened. 
Police response needed immediately!"
```
Expected: Severity ~85, Threat: HIGH ✅

---

## 📁 Files Created

| File | Purpose |
|------|---------|
| `NaturalLanguageProcessor.php` | NLP engine (9 capabilities) |
| `IncidentWorkflowManager.php` | Automation (7-step pipeline) |
| `NotificationSystem.php` | Alert distribution |
| `ReviewRequestSystem.php` | Review workflow |
| `nlp_workflow_migration.sql` | Database tables |
| `activate_nlp.php` | One-click activation |
| `setup_nlp_tables.php` | CLI setup tool |

---

## ⚡ Quick Command Reference

**Activate via web:**
```
http://localhost/Law_Enforcement_-_Incident_Report/activate_nlp.php
```

**Check database:**
```sql
SELECT COUNT(*) FROM incidents WHERE nlp_severity IS NOT NULL;
SELECT * FROM workflow_events LIMIT 5;
SELECT * FROM notifications LIMIT 5;
```

**Monitor logs:**
```bash
tail -f C:\xampp\logs\php_error.log
```

---

## ✅ Success Indicators

After creating test incident, you should see:

- ✅ Incident record created with NLP data
- ✅ Blotter entry auto-created  
- ✅ Severity score (0-100)
- ✅ Threat level (LOW/MED/HIGH)
- ✅ Officer auto-assigned
- ✅ Notification generated
- ✅ Workflow events logged
- ✅ No FK constraint errors

---

## 🚨 Critical Fix Applied

**Foreign Key Constraint Issue**: ✅ FIXED

Problem was: Code tried to insert `performed_by = 0` for system actions, but 0 doesn't exist in signup table.

Solution: 
- Database: `performed_by INT NULL` (allows NULL)
- Code: Passes `NULL` instead of `0`

Status: ✅ Ready for production

---

## 📞 Need Help?

Check these files:
1. `NLP_SYSTEM_READY.md` - Full deployment guide
2. `IMPLEMENTATION_FIX_SUMMARY.md` - Technical details
3. `DEPLOYMENT_CHECKLIST.md` - Verification steps
4. `INCIDENT_SYSTEM_README.md` - Feature documentation

---

**Status**: 🟢 READY TO DEPLOY

Time to activate: **5 minutes**
Time to test: **5 minutes**
Total: **10 minutes**

Next Action: Visit activate_nlp.php in your browser

