
# Complete Deferral System Implementation Guide

**Date**: April 27, 2026  
**Status**: Ready for deployment  
**Stack**: PHP/MySQL (XAMPP) + React.js

---

## 📋 Table of Contents
1. [Database Setup](#database-setup)
2. [Backend API Endpoints](#backend-api-endpoints)
3. [Frontend Components](#frontend-components)
4. [Complete Workflow](#complete-workflow)
5. [Testing Checklist](#testing-checklist)
6. [Deployment Steps](#deployment-steps)

---

## 🗄️ Database Setup

### Step 1: Run SQL Migration

Execute the migration file to create all necessary tables and stored procedures:

```bash
cd c:\Users\tlham\blood_donation\backend\sql
mysql -u root -p blood_donation < deferral_system_complete_schema.sql
```

**Tables Created**:
- `tbldonors` - Enhanced with status, deferred_until, deferral_reason columns
- `tblappointments` - Donor appointment scheduling
- `tblblood_tests` - Blood test results with individual test outcomes
- `tblnotifications` - Status change notifications for users
- `tbldeferral_history` - Audit trail of all deferral events

**Stored Procedures Created**:
- `sp_is_donor_eligible_for_appointment()` - Check if donor can book
- `sp_defer_donor()` - Apply deferral after positive test
- `sp_expire_deferral()` - Restore eligibility after 6 months

### Step 2: Enable MySQL Event Scheduler

```sql
SET GLOBAL event_scheduler = ON;
```

This allows automatic daily checks for expired deferrals.

---

## 🔌 Backend API Endpoints

### 1. Check Donor Eligibility

**File**: `backend/api/check_donor_eligibility.php`

Determines if a donor can book an appointment.

```bash
GET /api/check_donor_eligibility.php?donor_id=123
```

**Response**:
```json
{
  "success": true,
  "eligible": true,
  "status": "Confirmed",
  "message": "Donor is eligible for appointment",
  "deferral_reason": null,
  "deferred_until": null
}
```

**Or if deferred**:
```json
{
  "success": true,
  "eligible": false,
  "status": "Deferred",
  "message": "Donor is temporarily deferred until 2026-10-27",
  "deferral_reason": "Positive HIV result",
  "deferred_until": "2026-10-27"
}
```

### 2. Record Blood Test with Automatic Deferral

**File**: `backend/api/record_blood_test_with_deferral.php`

Records blood test results. If any test is Reactive, automatically defers the donor for 6 months.

```bash
POST /api/record_blood_test_with_deferral.php
```

**Request Body**:
```json
{
  "donation_id": "DON-2026-001",
  "donor_id": 123,
  "hiv_result": "Reactive",
  "hbsag_result": "Negative",
  "hcv_result": "Negative",
  "syphilis_result": "Negative",
  "malaria_result": "Negative",
  "tested_by_user_id": 45,
  "notes": "Lab findings from centrifugation test"
}
```

**Response on Positive Result**:
```json
{
  "success": true,
  "message": "Test results recorded. Donor has been deferred.",
  "donation_id": "DON-2026-001",
  "blood_test_id": 56,
  "final_result": "Discard",
  "donor_status": "Deferred",
  "deferral_reason": "Positive HIV result",
  "deferred_until": "2026-10-27",
  "hiv_result": "Reactive",
  "hbsag_result": "Negative",
  "hcv_result": "Negative",
  "syphilis_result": "Negative",
  "malaria_result": "Negative"
}
```

**Automatic Actions on Positive Test**:
✅ Donor status changed to "Deferred"  
✅ Deferral reason set to "Positive [TEST_NAME]"  
✅ Deferred until = TODAY + 6 MONTHS  
✅ Entry added to tbldeferral_history table  
✅ In-app notification created for donor  
✅ Email sent to donor with deferral details  
✅ Admin notification sent (in-app)  

### 3. Override Deferral (Admin Action)

**File**: `backend/api/override_deferral.php`

Admin can manually restore a deferred donor to Confirmed status.

```bash
POST /api/override_deferral.php
```

**Request Body**:
```json
{
  "donor_id": 123,
  "notes": "Approved by medical director after review"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Deferral overridden successfully",
  "donor_id": 123,
  "new_status": "Confirmed"
}
```

---

## 🎨 Frontend Components

### 1. DonorDashboard.js

Donor's home page showing registration status and deferral information.

**Location**: `src/pages/DonorDashboard.js`

**Features**:
- Status banner showing: Pending ⏳ / Confirmed ✅ / Deferred ⏸️ / Rejected ❌
- For deferred: Shows deferral reason, re-apply date, days remaining
- Displays recent notifications
- Shows upcoming appointments
- Contact information for help

**Status-Specific Messages**:
```javascript
Pending: "Your registration is under review. Check your email for updates."
Confirmed: "Great! You're approved to donate. Book an appointment."
Deferred: "Your deferral reason: Positive HIV result. Can reapply on April 27, 2027."
Rejected: "Not approved. Contact blood bank for information."
```

### 2. BookAppointment.js

Appointment booking form with eligibility checking.

**Location**: `src/pages/BookAppointment.js`

**Features**:
- ✅ Checks donor eligibility before showing form
- ❌ Blocks deferred donors with message
- Shows deferral details if deferred
- Form fields: Blood bank, Date, Time, Notes
- Automatic confirmation email
- Redirects to dashboard on success

**Eligibility Check Logic**:
```javascript
IF donor.status = "Deferred" AND deferred_until > TODAY
  THEN show error "Temporarily deferred until [DATE]"
ELSE IF donor.status = "Confirmed" OR "Active"
  THEN show booking form
ELSE
  THEN show error "Must be Confirmed to book"
```

### 3. DonorDashboard.css & BookAppointment.css

Complete styling with:
- Responsive design (mobile, tablet, desktop)
- Color-coded status banners
- Deferral details styling
- Form validation visual feedback
- Accessible button states

### 4. Enhanced AdminDashboard.js

Admin interface for donor management.

**Location**: `src/pages/AdminDashboard.js` (enhanced)

**Features**:
- Tabbed interface: Pending, Deferred, Rejected, All
- Donor cards with quick actions
- For deferred donors:
  - Shows deferral reason
  - Shows can reapply date
  - Shows days remaining
  - "Override Deferral" button for manual restoration
- For pending donors:
  - Approve button
  - Reject button (with reason)
- Real-time status updates

---

## 🔄 Complete Workflow

### Scenario: Donor Registration → Approval → Donation → Positive Test → Deferral

```
┌─────────────────────────────────────────────────────────────┐
│ STEP 1: DONOR REGISTRATION                                  │
├─────────────────────────────────────────────────────────────┤
│ Donor: Fills form with health declarations (all ticked)     │
│ Frontend: Submits to /api/register_donor.php                │
│ Backend: Creates tbldonors record with status='Pending'     │
│ Frontend: Shows RegistrationSuccess screen                  │
│ Email: Sent to donor "Application received"                 │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 2: ADMIN APPROVAL                                      │
├─────────────────────────────────────────────────────────────┤
│ Admin: Views pending donors in AdminDashboard               │
│ Admin: Clicks "Approve" button                              │
│ Frontend: Calls /api/update_donor_registration_status.php  │
│ Backend: Updates tbldonors status='Confirmed'              │
│ Database: Creates notification for donor (type=approval)   │
│ Email: Sent to donor "You're approved! Book appointment"   │
│ Dashboard: Donor sees "✅ Approved" with book button        │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 3: DONOR BOOKS APPOINTMENT                              │
├─────────────────────────────────────────────────────────────┤
│ Donor: Clicks "Book Appointment" button                     │
│ Frontend: Calls /api/check_donor_eligibility.php            │
│   Result: eligible=true, status='Confirmed'                 │
│ Donor: Selects blood bank, date, time                       │
│ Frontend: Calls /api/book_appointment.php                   │
│ Backend: Creates tblappointments record                     │
│ Email: Sent confirmation to donor                           │
│ Status: Remains "Confirmed" (doesn't change)                │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 4: BLOOD DONATION DAY                                  │
├─────────────────────────────────────────────────────────────┤
│ Staff: Collects blood sample                                │
│ Lab: Tests for HIV, HBsAg, HCV, Syphilis, Malaria         │
│ Result: HIV = REACTIVE  (Positive)                          │
│ Status: Blood unit = Discard (not usable)                   │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 5: DEFERRAL APPLIED (AUTOMATIC)                        │
├─────────────────────────────────────────────────────────────┤
│ Staff: Enters test result "HIV: Reactive" in form          │
│ Frontend: Calls /api/record_blood_test_with_deferral.php  │
│ Backend: Detects Reactive result                           │
│   Action 1: UPDATE tbldonors status='Deferred'            │
│   Action 2: Set deferral_reason='Positive HIV result'     │
│   Action 3: Set deferred_until='2026-10-27' (6 months)    │
│   Action 4: INSERT into tbldeferral_history (audit)       │
│   Action 5: INSERT notification for donor                 │
│   Action 6: SEND EMAIL to donor explaining deferral      │
│   Action 7: INSERT admin notification                     │
│ Email: Sent to donor "⏸️ Temporary Deferral Notice"       │
│   Content: Test result, reason, reapply date               │
│ Donor Dashboard: Now shows "⏸️ Deferred until April 27"    │
│ Admin Dashboard: Deferred tab shows donor with reason      │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 6: DEFERRED PERIOD (6 MONTHS)                          │
├─────────────────────────────────────────────────────────────┤
│ Donor: Tries to book appointment                            │
│ Frontend: Calls /api/check_donor_eligibility.php            │
│   Result: eligible=false, "Deferred until April 27"        │
│ BookAppointment: Shows error message                        │
│ Donor: Cannot book until deferral expires                  │
│ Dashboard: Shows "⏸️ Can reapply on April 27, 2027"        │
│ Notifications: "Your deferral reason: Positive HIV"       │
│               "Days remaining: 123 days"                   │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 7: DEFERRAL EXPIRES (AUTO or MANUAL)                  │
├─────────────────────────────────────────────────────────────┤
│ Date: April 27, 2027 (deferral_until date reached)        │
│                                                              │
│ AUTOMATIC (if event scheduler enabled):                    │
│   Nightly event: evt_check_deferral_expiry runs            │
│   UPDATE tbldonors status='Confirmed' WHERE deferred_until  │
│         <= CURDATE()                                        │
│                                                              │
│ MANUAL (admin override anytime):                            │
│   Admin: Clicks "Override Deferral" button                 │
│   Frontend: Calls /api/override_deferral.php               │
│   Backend: Sets status='Confirmed' immediately             │
│   Notification: Sent to donor "Deferral lifted"            │
│                                                              │
│ Donor: Now eligible to book again                           │
│ Dashboard: Shows "✅ Approved - You can donate"            │
│ Can Now: Book appointments normally                         │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ Testing Checklist

### Phase 1: Database Validation
- [ ] Run migration without errors
- [ ] Verify all 5 tables created: `SHOW TABLES;`
- [ ] Check column structure: `DESC tbldonors;`
- [ ] Verify stored procedures: `SHOW PROCEDURE STATUS;`
- [ ] Test event scheduler: `SHOW EVENTS;`

### Phase 2: Backend API Tests

**Test 1: Eligibility Check**
```bash
# Eligible donor
curl -X GET "http://localhost/blood_donation/backend/api/check_donor_eligibility.php?donor_id=1"

# Should return: {"success": true, "eligible": true, "status": "Confirmed"}
```

**Test 2: Record Blood Test with Deferral**
```bash
curl -X POST "http://localhost/blood_donation/backend/api/record_blood_test_with_deferral.php" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "donation_id": "DON-TEST-001",
    "donor_id": 1,
    "hiv_result": "Reactive",
    "hbsag_result": "Negative",
    "hcv_result": "Negative",
    "syphilis_result": "Negative",
    "malaria_result": "Negative",
    "tested_by_user_id": 5,
    "notes": "Testing positive result"
  }'

# Should return deferral details
```

**Test 3: Check Eligibility After Deferral**
```bash
# After setting deferral, check again
curl -X GET "http://localhost/blood_donation/backend/api/check_donor_eligibility.php?donor_id=1"

# Should return: {"success": true, "eligible": false, "deferred_until": "2026-10-27"}
```

**Test 4: Override Deferral**
```bash
curl -X POST "http://localhost/blood_donation/backend/api/override_deferral.php" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "donor_id": 1,
    "notes": "Test override"
  }'

# Should return: {"success": true, "new_status": "Confirmed"}
```

### Phase 3: Frontend Component Tests

- [ ] DonorDashboard displays correct status for each state
- [ ] Status banner colors match specification (pending=yellow, confirmed=green, deferred=orange, rejected=red)
- [ ] Deferral message shows: "Can reapply on [DATE]"
- [ ] Days remaining countdown is accurate
- [ ] BookAppointment blocks deferred donors
- [ ] BookAppointment shows booking form for confirmed donors
- [ ] AdminDashboard tabs filter donors correctly
- [ ] Deferral reason and date visible in admin cards
- [ ] "Override Deferral" button appears only for deferred donors
- [ ] Approve/Reject buttons work for pending donors

### Phase 4: End-to-End Workflow Test

0. **Pre-test data check**
  - [ ] Pick a donation that has `donor_id` NOT NULL in `tblblood_units`
  - [ ] If no linked donation exists, add a blood unit linked to a real donor first

1. **Register as new donor**
   - [ ] Fill form with all health declarations
   - [ ] See RegistrationSuccess screen
   - [ ] Receive "Application received" email

2. **Admin approves**
   - [ ] Admin logs in
   - [ ] Sees donor in Pending tab
   - [ ] Clicks Approve
   - [ ] Donor status becomes "Confirmed"
   - [ ] Donor receives approval email

3. **Donor books appointment**
   - [ ] Donor logs in to dashboard
   - [ ] Sees "✅ Approved" status
   - [ ] Clicks "Book Appointment"
   - [ ] Eligibility check passes
   - [ ] Can select date and time
   - [ ] Receives confirmation email

4. **Positive blood test recorded**
   - [ ] Staff enters blood test results with HIV=Reactive
   - [ ] System records test
   - [ ] Donor status changes to "Deferred"
   - [ ] Deferral reason set to "Positive HIV result"
   - [ ] Deferred until set to 6 months from today
   - [ ] Donor receives email "Temporary Deferral Notice"
   - [ ] Admin sees donor in Deferred tab
  - [ ] 6a. Check that the donor received an email confidentially, with deferral reason and date.
  - [ ] 6b. Without logging out, refresh the donor dashboard (or wait for auto-refresh) and verify the deferral message appears.
  - [ ] 6c. Try to book a new appointment as that donor - the system should prevent it or show an error.

5. **Donor tries to book during deferral**
   - [ ] Donor tries to book appointment
   - [ ] Eligibility check returns eligible=false
   - [ ] Error message shows: "Deferred until [DATE]"
   - [ ] Cannot book appointment

6. **Admin overrides deferral**
   - [ ] Admin clicks "Override Deferral"
   - [ ] Donor status back to "Confirmed"
   - [ ] Donor receives "Deferral lifted" notification
   - [ ] Donor can now book appointments again

---

## 🚀 Deployment Steps

### Step 1: Database Preparation

```bash
# SSH into server or local MySQL
mysql -u root -p

# Select database
USE blood_donation;

# Run migration
source c:\Users\tlham\blood_donation\backend\sql\deferral_system_complete_schema.sql;

# Enable event scheduler
SET GLOBAL event_scheduler = ON;

# Verify
SHOW TABLES;
SELECT COUNT(*) FROM tbldonors;
```

### Step 2: Sync Backend Files to XAMPP

Copy these new files to `c:\xampp\htdocs\blood_donation\backend\api\`:

```powershell
# From workspace to XAMPP
Copy-Item "c:\Users\tlham\blood_donation\backend\api\check_donor_eligibility.php" `
          "c:\xampp\htdocs\blood_donation\backend\api\"

Copy-Item "c:\Users\tlham\blood_donation\backend\api\record_blood_test_with_deferral.php" `
          "c:\xampp\htdocs\blood_donation\backend\api\"

Copy-Item "c:\Users\tlham\blood_donation\backend\api\override_deferral.php" `
          "c:\xampp\htdocs\blood_donation\backend\api\"
```

### Step 3: Validate PHP Syntax

```bash
cd c:\xampp\php
php.exe -l c:\xampp\htdocs\blood_donation\backend\api\check_donor_eligibility.php
php.exe -l c:\xampp\htdocs\blood_donation\backend\api\record_blood_test_with_deferral.php
php.exe -l c:\xampp\htdocs\blood_donation\backend\api\override_deferral.php
```

### Step 4: Update Frontend Routes

Add to `src/App.js`:

```javascript
import DonorDashboard from './pages/DonorDashboard';
import BookAppointment from './pages/BookAppointment';

// In route list:
<Route path="/dashboard" element={<DonorDashboard />} />
<Route path="/book-appointment" element={<BookAppointment />} />
```

### Step 5: Build and Deploy React

```bash
cd c:\Users\tlham\blood_donation
npm run build

# Copy build to server/XAMPP
Copy-Item -Recurse "build/*" "c:\xampp\htdocs\blood_donation\build\"
```

### Step 6: Test Live System

1. Navigate to http://localhost/blood_donation
2. Follow "Testing Checklist" above
3. Verify all notifications are sent
4. Check MySQL logs for errors

---

## 📝 Database Verification Queries

```sql
-- View all donor statuses
SELECT id, full_name, email, status, deferral_reason, deferred_until 
FROM tbldonors 
ORDER BY status;

-- View deferred donors with days remaining
SELECT 
  id, full_name, deferral_reason, deferred_until,
  DATEDIFF(deferred_until, CURDATE()) as days_remaining
FROM tbldonors
WHERE status = 'Deferred'
ORDER BY deferred_until;

-- View deferral history
SELECT * FROM tbldeferral_history ORDER BY deferred_at DESC;

-- View recent notifications
SELECT * FROM tblnotifications 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY created_at DESC;

-- View blood test results
SELECT d.full_name, bt.hiv_result, bt.hbsag_result, bt.final_result, bt.tested_at
FROM tblblood_tests bt
JOIN tbldonors d ON bt.donor_id = d.id
ORDER BY bt.tested_at DESC;
```

---

## 🐛 Troubleshooting

**Issue**: Donor deferred but email not sent  
**Solution**: Verify `bts_send_email()` function is defined in config/mailer.php

**Issue**: AdminDashboard not showing deferred donors  
**Solution**: Check that `get_donors.php` query includes deferred_reason and deferred_until in SELECT

**Issue**: "Override Deferral" button not appearing  
**Solution**: Ensure donor status is exactly 'Deferred' (case-insensitive but trimmed)

**Issue**: Eligibility check always returns false  
**Solution**: Verify deferred_until column exists and contains valid DATE values

---

## 📞 Support

For issues or questions:
- Email: developers@bloodbank.bt
- Phone: 1095 (Help desk)
- Documentation: See individual .md files in project root
