# Blood Bank Donor Workflow Design

## Current Problem
1. **No post-registration confirmation** – Donor registers, sees generic "Success" popup, but doesn't know their new status
2. **Silent rejection on positive test** – When blood test is Reactive, donor status becomes "Rejected" with no explanation or notification
3. **No deferral distinction** – System doesn't differentiate between "permanently rejected" vs "temporarily deferred due to positive test"
4. **Admin can't see rejection reason** – Admin doesn't know which test result caused the rejection

---

## Solution Overview

### 1. Donor Lifecycle Statuses
```
Pending         → Registration approved by admin (email notification)
    ↓
Confirmed/Active → Eligible to donate (email + dashboard notification)
    ↓
Deferred        → Positive blood test detected (email: "Please wait 6 months")
    ↓
Rejected        → Admin manual rejection (email: "Contact blood bank")
```

### 2. Post-Registration Success Screen
**What donor sees after registration:**
- Confirmation of registration
- Current status: "Pending"
- What's next: "Your registration is under review. You'll receive an email when approved."
- Estimated wait time: "Usually within 24-48 hours"
- Link to dashboard or back to home

### 3. Blood Test Positive Result Workflow

**Backend Logic (record_donation_test.php):**
```
IF any test = Reactive:
  - Set tbldonors.status = 'Deferred'
  - Set tbldonors.deferral_reason = 'Positive [TEST_NAME] result'
  - Set tblblood_units.status = 'Rejected'
  - Create notification: "Your blood test detected [TEST_NAME]. You're temporarily deferred for 6 months."
  - Send email to donor
  ELSE IF all tests = Non-reactive:
  - Set status = 'Active' (if previously Pending)
  - Create notification: "✓ All blood tests passed! You're eligible to donate."
```

**Donor sees (Dashboard):**
- ⏸️ **Deferred Status**: "Your donation was not suitable at this time due to positive screening results. You may reapply in 6 months."
- Action: "Contact Blood Bank" button with helpline

**Admin sees (Admin Dashboard):**
- Donor status: **Deferred** (not just "Rejected")
- Reason: **"Positive HIV result"** or **"Positive Hepatitis B"** etc.
- Option: "Override deferral" (manual recovery button for edge cases)

### 4. Status Change Notifications

**Email + Dashboard Notice to Donor:**

**On Admin Approval (Status: Pending → Confirmed):**
```
Subject: ✓ Your Blood Donor Registration is Approved!

Dear [Donor Name],

Your blood donor registration has been approved. You are now eligible to donate blood.

Next step: Book an appointment at your nearest blood bank.
Link: [Dashboard appointment booking]

Thank you for saving lives!
```

**On Positive Test (Status: Pending/Confirmed → Deferred):**
```
Subject: ⏸️ Your Blood Donation - Temporary Deferral

Dear [Donor Name],

Your recent blood test showed reactive result for [TEST NAME].
We appreciate your willingness to donate, but we must defer you temporarily for 6 months.

You can apply again on or after: [DATE]

This is a standard safety measure. For questions, call: 1095

Stay healthy!
```

**On Admin Rejection (Status: → Rejected):**
```
Subject: Blood Donation Registration - Need More Info

Dear [Donor Name],

We received your registration but need additional information.
Please contact the Blood Bank at 1095 to discuss.

Thank you for your interest.
```

---

## Frontend Implementation

### POST-REGISTRATION SCREEN
Location: `src/pages/RegistrationSuccess.js` (new)
```
Shows:
- ✓ Registration confirmed
- Status badge: "Pending Approval"
- Timeline: "Your application will be reviewed within 24-48 hours"
- "Check your email for updates"
- Buttons: "Go to Dashboard" or "Back to Home"
```

### DONOR DASHBOARD ENHANCEMENTS
Location: `src/pages/Dashboard.js` (update)
```
Status banner changes:
- pending: "Your registration is under review. We'll email you when approved."
- confirmed: "✓ You're eligible! Book an appointment below."
- deferred: "⏸️ Temporarily deferred due to positive screening. You can reapply on [DATE]."
- rejected: "⚠️ Your registration was not approved. Call 1095 for details."

Add notification panel:
- Show all status changes + dates
- E.g., "April 27, 2026 - Registered as donor"
- E.g., "April 28, 2026 - Approved for donation"
```

### ADMIN DASHBOARD ENHANCEMENTS
Location: `src/pages/AdminDashboard.js` (update)
```
Donor list shows:
- Status column: Pending | Confirmed | Deferred | Rejected
- Reason column (for Deferred/Rejected):
  - "Positive HIV result"
  - "Positive Hepatitis B"
  - "Manual rejection"
  - etc.

Donor detail panel:
- Full test results if deferred
- Deferral end date
- "Override Deferral" button (admin only)
```

---

## Backend Implementation

### Database Updates Required
```sql
-- Already exists:
ALTER TABLE tbldonors ADD COLUMN status VARCHAR(20) DEFAULT 'Pending';
ALTER TABLE tbldonors ADD COLUMN deferral_reason VARCHAR(255);
ALTER TABLE tbldonors ADD COLUMN deferred_until DATE NULL;

-- New (notifications):
CREATE TABLE tblnotifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50),
  message VARCHAR(500),
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES tblusers(id)
);
```

### API Endpoints to Update

**1. register_donor.php (DONE - just needs return value clarification)**
```
Response now includes:
{
  "success": true,
  "id": 123,
  "message": "Registration submitted. Your status is: Pending",
  "status": "Pending",
  "nextSteps": "Your application will be reviewed within 24-48 hours"
}
```

**2. record_donation_test.php (UPDATE NEEDED)**
```php
IF hasReactive test:
  UPDATE tbldonors SET 
    status = 'Deferred',
    deferral_reason = 'Positive [TEST_NAME] result',
    deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
  WHERE id = $donorId;
  
  INSERT INTO tblnotifications VALUES (
    $userId, 
    'deferral',
    "Your blood test detected $testName. You're temporarily deferred until $deferralDate.",
    0,
    NOW()
  );
  
  SEND EMAIL to donor@email.com with deferral notice;
```

**3. get_my_profile.php (NO CHANGE - already returns status)**

**4. get_notifications.php (NEW ENDPOINT)**
```php
GET /api/get_notifications.php

Returns:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "approval",
      "message": "Your registration was approved!",
      "created_at": "2026-04-28 10:00:00",
      "is_read": false
    },
    {
      "id": 2,
      "type": "deferral",
      "message": "Your blood test showed positive HIV. You're deferred until 2026-10-27.",
      "created_at": "2026-04-29 14:30:00",
      "is_read": false
    }
  ]
}
```

---

## Implementation Checklist

### Frontend
- [ ] Create `RegistrationSuccess.js` with post-reg confirmation screen
- [ ] Update `DonorRegister.js` to redirect to success screen on registration
- [ ] Update `Dashboard.js` to show status-specific messages and notifications
- [ ] Create notifications panel in Dashboard
- [ ] Update `AdminDashboard.js` to show deferral reason and date

### Backend
- [ ] Update `register_donor.php` response to include status
- [ ] Update `record_donation_test.php` to set deferral instead of just rejection
- [ ] Create `get_notifications.php` API
- [ ] Add email notification on deferral
- [ ] Add database columns if missing (deferred_until)

### Database
- [ ] Verify `tbldonors.deferral_reason` exists
- [ ] Verify `tbldonors.deferred_until` exists
- [ ] Create `tblnotifications` table
- [ ] Add indexes on `tblnotifications(user_id, is_read, created_at)`

---

## Status Transitions Visual

```
┌─────────────────────────────────────────────────────────────┐
│                    DONOR REGISTRATION FLOW                   │
└─────────────────────────────────────────────────────────────┘

                      [REGISTER]
                          ↓
                    ┌─────────────┐
                    │   PENDING   │ ← Email: "Under review"
                    └─────────────┘
                         ↙ ↖
                    /            \
              [ADMIN]          [TEST]
               /                   \
              ↓                      ↓
     ┌──────────────┐      ┌─────────────────┐
     │  CONFIRMED   │      │  DEFERRED       │ (6-month wait)
     │ (ACTIVE)     │      │ (Positive test) │
     └──────────────┘      └─────────────────┘
         ↓                        ↓
   Can book                Can reapply
   appointment             after 6 months
       ↓                        ↓
   [APPOINTMENT]           [DEFERRED]
       ↓                   (or → CONFIRMED)
   Donation                   ↓
                          Can book
                          appointment
```

---

## Key Points for User
1. **After Registration**: Donor knows they're "Pending" and what happens next
2. **After Approval**: Donor gets email + dashboard notification to book appointment
3. **After Positive Test**: Donor understands it's temporary deferral, not permanent rejection, with specific reason and wait period
4. **Admin Transparency**: Admin sees exact test results that caused deferral
5. **Reapply Path**: Clear date when donor can try again

