# Donor Deferral System - Quick Reference Guide

**Last Updated**: April 27, 2026  
**For**: Developers, Testers, and System Admins  

---

## 🚀 Quick Start (5 Minutes)

### 1. Database Setup
```bash
mysql -u root -p blood_donation < backend/sql/deferral_system_complete_schema.sql
SET GLOBAL event_scheduler = ON;  # Enable auto-expiry
```

### 2. Copy Backend Files
```powershell
# To XAMPP backend/api directory:
- check_donor_eligibility.php
- record_blood_test_with_deferral.php
- override_deferral.php
```

### 3. Update Frontend Routes (App.js)
```javascript
import DonorDashboard from './pages/DonorDashboard';
import BookAppointment from './pages/BookAppointment';

// Add routes:
<Route path="/dashboard" element={<DonorDashboard />} />
<Route path="/book-appointment" element={<BookAppointment />} />
```

### 4. Build & Deploy
```bash
npm run build
# Copy build/* to XAMPP htdocs
```

---

## 🎯 API Quick Reference

### Check Eligibility
```bash
GET /api/check_donor_eligibility.php?donor_id=123
Response: {eligible: true/false, status, deferral_reason, deferred_until}
```

### Record Blood Test (+ Auto-Deferral)
```bash
POST /api/record_blood_test_with_deferral.php
Body: {donation_id, donor_id, hiv_result, hbsag_result, hcv_result, syphilis_result, malaria_result, tested_by_user_id}
Response: {final_result, donor_status, deferral_reason, deferred_until}
```

### Override Deferral (Admin)
```bash
POST /api/override_deferral.php
Body: {donor_id, notes}
Response: {new_status: "Confirmed"}
```

---

## 📊 Database Status Values

| Value | Meaning | Can Book? | Can Donate? |
|-------|---------|-----------|-----------|
| `Pending` | Awaiting admin approval | ❌ | ❌ |
| `Confirmed` | Approved | ✅ | ✅ |
| `Active` | Approved (alternate value) | ✅ | ✅ |
| `Deferred` | Temp hold (6 months) | ❌ | ❌ |
| `Rejected` | Permanent denial | ❌ | ❌ |

---

## 🔍 Key Queries

### Find All Deferred Donors
```sql
SELECT id, full_name, deferral_reason, deferred_until
FROM tbldonors
WHERE status = 'Deferred'
ORDER BY deferred_until;
```

### Find Expired Deferrals (Ready to Restore)
```sql
SELECT * FROM tbldonors
WHERE status = 'Deferred' AND deferred_until <= CURDATE();
```

### View Deferral Audit Trail
```sql
SELECT * FROM tbldeferral_history
ORDER BY deferred_at DESC;
```

### Find Donor by Email
```sql
SELECT * FROM tbldonors
WHERE email = 'donor@example.com';
```

---

## 🧪 Test Data Setup

### Create Test Donor (Already Approved)
```sql
INSERT INTO tbldonors (full_name, email, blood_type, status, created_at)
VALUES ('Test Donor', 'test@example.com', 'O+', 'Confirmed', NOW());
```

### Manually Defer a Donor
```sql
UPDATE tbldonors
SET status = 'Deferred',
    deferral_reason = 'Positive HIV result',
    deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
WHERE id = 1;
```

### Restore a Deferred Donor
```sql
UPDATE tbldonors
SET status = 'Confirmed',
    deferred = 0,
    deferral_reason = NULL,
    deferred_until = NULL
WHERE id = 1;
```

---

## ✅ Verification Checklist

### After Database Setup
- [ ] `SELECT COUNT(*) FROM tbldonors;` returns count
- [ ] `SELECT COUNT(*) FROM tblblood_tests;` returns 0
- [ ] `SHOW PROCEDURE STATUS;` shows 3 procedures
- [ ] `SHOW EVENTS;` shows evt_check_deferral_expiry

### After Backend Deployment
- [ ] `php -l check_donor_eligibility.php` = OK
- [ ] `php -l record_blood_test_with_deferral.php` = OK
- [ ] `php -l override_deferral.php` = OK
- [ ] Test each API with curl/Postman

### After Frontend Deployment
- [ ] `/dashboard` loads for confirmed donor
- [ ] `/book-appointment` shows form for confirmed donor
- [ ] `/book-appointment` shows error for deferred donor
- [ ] Admin dashboard shows "Deferred" tab
- [ ] Status badge displays correctly

---

## 🐛 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| Donor can book when deferred | Check `deferred_until` column exists, has valid DATE value |
| No deferral email sent | Verify `bts_send_email()` in config/mailer.php |
| Status doesn't update in DB | Check stored procedure permissions, logs |
| Admin can't override deferral | Verify donor status is exactly 'Deferred' |
| Appointments still booked for deferred | Ensure `check_donor_eligibility.php` is called before booking |
| Event scheduler not auto-expiring | Run `SET GLOBAL event_scheduler = ON;` |

---

## 📱 Frontend Component Map

```
Dashboard (Donor Home)
  ├─ Status Banner (Pending/Confirmed/Deferred/Rejected)
  ├─ Profile Section
  ├─ Notifications Panel
  ├─ Appointments List
  └─ Help Section

Book Appointment
  ├─ Eligibility Check (blocks if deferred)
  ├─ Booking Form (blood bank, date, time)
  ├─ Success Message
  └─ Deferral Info (if not eligible)

Admin Dashboard
  ├─ Pending Tab (Approve/Reject)
  ├─ Deferred Tab (Override button)
  ├─ Rejected Tab (view reasons)
  └─ All Tab (full list)
```

---

## 📧 Email Templates Used

### Approval Email
```
Subject: ✅ Your Blood Donor Registration is Approved!
Body: You're approved to donate. Book an appointment.
```

### Deferral Email
```
Subject: ⏸️ Your Blood Donation - Temporary Deferral
Body: Your test showed [TEST] positive. 
      Deferred for 6 months.
      Can reapply on [DATE].
```

### Deferral Lifted Email
```
Subject: 🔄 Deferral Period Expired - You Can Donate Again!
Body: Your deferral has ended. You're eligible again.
```

---

## 🔐 Security Notes

- ✅ All API endpoints require authentication (`bts_require_auth()`)
- ✅ Override deferral requires ADMIN role only
- ✅ SQL uses prepared statements (no injection)
- ✅ Timestamps use CURRENT_TIMESTAMP (server time)
- ✅ Sensitive data (test results) logged with confidentiality note
- ✅ Email addresses validated before sending

---

## 📞 Common Tasks

### Task: Test Positive Blood Test Flow
1. Get test donor ID: `SELECT id FROM tbldonors WHERE email='test@example.com';`
2. Create donation: (via donation booking/creation API)
3. Record test: `POST /api/record_blood_test_with_deferral.php` with `hiv_result: "Reactive"`
4. Verify: `SELECT * FROM tbldonors WHERE id=123;` → should show status='Deferred'
5. Check notification: `SELECT * FROM tblnotifications WHERE donor_id=123;`
6. Test blocking: `GET /api/check_donor_eligibility.php?donor_id=123` → should return eligible=false

### Task: Manually Restore a Deferred Donor
1. Admin: POST `/api/override_deferral.php` with `{donor_id: 123}`
2. Verify: `SELECT status FROM tbldonors WHERE id=123;` → should be 'Confirmed'
3. Check: Notification should exist in tblnotifications

### Task: Check Deferral Status on Dashboard
1. User logs in
2. Dashboard fetches `get_my_profile.php` → includes status, deferred_until
3. If status='Deferred', show deferral reason and countdown
4. If booking attempted, `check_donor_eligibility.php` returns eligible=false

---

## 🔗 Related Files

**Core Implementation**:
- SQL: `backend/sql/deferral_system_complete_schema.sql`
- PHP APIs: `backend/api/check_donor_eligibility.php`, `record_blood_test_with_deferral.php`, `override_deferral.php`
- React: `src/pages/DonorDashboard.js`, `BookAppointment.js`, `AdminDashboard.js`

**Documentation**:
- Full Guide: `DEFERRAL_SYSTEM_IMPLEMENTATION_GUIDE.md`
- Code Examples: `DONOR_WORKFLOW_CODE_EXAMPLES.md`
- Files Summary: `DEFERRAL_SYSTEM_FILES_SUMMARY.md`

---

## 💡 Pro Tips

1. **Testing**: Always test with real dates (deferred_until = actual date 6 months away)
2. **Timezone**: Ensure MySQL server time matches app expectations (use NOW() consistently)
3. **Notifications**: Check tblnotifications table to verify emails were queued
4. **Audit**: Review tbldeferral_history for all deferral actions
5. **Batch Restore**: Use SQL event to auto-restore expired deferrals nightly
6. **Backup**: Always backup database before running migration

---

## 🎓 Understanding the Workflow

```
1. Donor registers → status='Pending'
2. Admin approves → status='Confirmed' + email sent
3. Donor books appointment → checks eligibility first
4. Donor donates → staff enters test results
5. If positive → AUTO: status='Deferred' + email + notification
6. Donor waits 6 months OR admin overrides
7. After 6 months → AUTO: status='Confirmed' (event scheduler)
8. Donor can book again
```

The system automatically handles deferral - **no manual intervention required** for positive test results.
