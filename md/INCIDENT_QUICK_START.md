# Incident Logging & Classification System - Quick Start

## ⚡ 30-Second Setup

### 1️⃣ Create Database Tables
```
Navigate to: http://localhost/setup_incidents_table.php
Click: (runs automatically)
Expect: "Table verification passed" message
```

### 2️⃣ Access the System
```
Click in navbar: "Incident Logging Report"
Or: http://localhost/modules/incident_report.php
```

### 3️⃣ Start Reporting
- Click **"Report Incident"** button (red button, top right)
- Fill out the form
- Click **"Submit Incident Report"**
- System auto-classifies your report

---

## 📋 What Gets Auto-Detected

When you submit an incident, the system **automatically**:

✅ **Classifies the incident** based on keywords:
- "hit", "punch", "abuse" → **Abuse**
- "neglected", "abandoned" → **Neglect**  
- "violence", "attack", "fight" → **Violence**
- "steal", "robbery" → **Theft**

✅ **Detects high-risk cases** containing:
- Words: "danger", "critical", "emergency", "life-threatening"
- Types: Violence, Assault, Abuse
- Result: Red warning badge + flagged for admin

✅ **Assigns urgency level**:
- High-risk incidents → **Critical** priority
- Violence/Assault → **High**
- Abuse/Neglect → **High**
- Others → **Medium/Low**

---

## 👤 User vs Admin - What You Can Do

### Regular Users Can:
✅ Create incident reports  
✅ View own reports  
✅ View verified/resolved incidents  
❌ Cannot edit other's reports  
❌ Cannot change classifications  

### Admins Can:
✅ View ALL incidents  
✅ Correct classification (if system got it wrong)  
✅ Update urgency level  
✅ Mark as high-risk  
✅ Add notes  
✅ Assign to officer  
✅ Change status  

---

## 🔍 Key Fields Explained

| Field | Purpose | Required |
|-------|---------|----------|
| **Case #** | Unique ID (auto-generated) | Auto |
| **Reporter Name** | Who is filing report | Yes |
| **Reporter Type** | Citizen/Parent/Officer/Org | Yes |
| **Incident Date** | When it happened | Yes |
| **Incident Time** | What time | No |
| **Category** | Abuse/Neglect/Violence/etc | Yes |
| **Location** | Where it happened | Yes |
| **Narrative** | What happened (in detail) | Yes |
| **Victim Name** | Who was affected | No |
| **Victim Age** | Age of victim | No |
| **Suspect Name** | Who did it (if known) | No |
| **Evidence** | Photos, items, etc | No |
| **Coordinates** | GPS lat/long (optional) | No |

---

## 📊 Dashboard Stats

When you load the incident page, you immediately see:

- **Total Reports**: Number of all incidents
- **Critical Cases**: High-urgency incidents
- **High-Risk Cases**: Flagged by system (violence/abuse/danger)
- **Pending Review**: Not yet verified

---

## 🔧 Admin Functions

### View an Incident
1. Click **Eye icon** (View)
2. See all details in modal
3. Click **Close**

### Edit/Correct Classification
1. Click **Pencil icon** (Edit) - Admin only
2. Change **Correct Classification** dropdown if system was wrong
3. Adjust **Urgency Level** if needed
4. Add **Admin Notes** explaining correction
5. Click **Assign To Officer** if needed
6. Update **Status** (Submitted→Under Review→Verified→Resolved)
7. Click **Save Changes**

---

## 🎯 Common Scenarios

### Scenario 1: Report Child Abuse
1. Open "Report Incident"
2. **Reporter**: Jane Smith (Parent)
3. **Category**: Abuse
4. **Narrative**: "My child was hit by their guardian. Has bruises on arm."
5. Submit
6. **System Result**: Auto-classifies as Abuse, marks High-risk, sets Critical urgency
7. **Admin sees**: Red badge, critical flag, ready for investigation

### Scenario 2: Report Theft
1. Open "Report Incident"
2. **Reporter**: Store Manager
3. **Category**: Theft
4. **Narrative**: "Electronics stolen from shelf overnight"
5. Submit
6. **System Result**: Classifies as Theft, Medium urgency, no high-risk flag
7. **Admin sees**: Normal blue badge, medium priority

### Scenario 3: Admin Corrects Misclassification
1. System classified as "Neglect" but should be "Abuse"
2. Admin clicks pencil icon
3. Changes **Correct Classification** → Abuse
4. Adds note: "Changed from neglect - clear signs of physical abuse"
5. Sets urgency to **Critical**
6. Assigns to **Officer Martinez**
7. Changes status to **Under Review**
8. Clicks **Save Changes**
9. **System tracks**: Who changed it, when, why (via admin notes)

---

## 🚀 Filters & Search

### Filter by Status
- **Draft**: Not submitted yet
- **Submitted**: Just filed, needs review
- **Under Review**: Admin looking at it
- **Verified**: Confirmed, proceeding
- **Resolved**: Investigation complete

### Filter by Urgency
- **Low**: Minor incidents
- **Medium**: Standard cases
- **High**: Serious issues
- **Critical**: Immediate action needed

---

## ✅ Testing Checklist

- [ ] Setup page runs without errors
- [ ] "Report Incident" button works
- [ ] Form validates (requires reporter name, date, narrative)
- [ ] Auto-classification detects keywords correctly
- [ ] High-risk detection works (violence/abuse keywords trigger red badge)
- [ ] Admin can view all incidents
- [ ] Admin can edit classifications
- [ ] Users can only see own + verified incidents
- [ ] Filters work (status, urgency)
- [ ] Statistics cards update correctly

---

## 🆘 Troubleshooting

### Issue: "Table doesn't exist" error
**Solution**: Run `setup_incidents_table.php` first

### Issue: Can't see report after submitting
**Solution**: Log out and back in - session needs refresh

### Issue: Pencil/Edit button not showing
**Solution**: You might not be Admin - check your role

### Issue: Classification doesn't seem right
**Solution**: Admin can override - use "Correct Classification" dropdown

---

## 📞 Quick Reference

**To Report an Incident**: Click "Report Incident" → Fill form → Submit  
**To View Details**: Click eye icon  
**To Correct (Admin)**: Click pencil icon  
**To Filter**: Use dropdown menus, click "Apply Filters"  
**To Assign (Admin)**: Open edit modal → Select officer → Save  

---

## 💡 Pro Tips

💡 **Always fill Narrative field completely** - system uses it to auto-classify  
💡 **Use keywords** - "violence", "abuse", "emergency" → better detection  
💡 **Admin notes are important** - explain why you corrected classification  
💡 **Assign quickly** - get incidents to right officer fast  
💡 **Update status** - track progress through investigation  
💡 **Critical cases get red badges** - easy to spot urgent incidents  

---

**Ready to use!** 🎉  
Start by visiting: `http://localhost/modules/incident_report.php`
