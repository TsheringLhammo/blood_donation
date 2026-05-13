# Donor Workflow Implementation - Code Examples

## 1. Enhanced Deferral Logic (record_donation_test.php snippet)

When a blood test returns a **Reactive** result, the backend should:

```php
// Determine which test was reactive
$reactiveTestName = 'Blood Test';
if ($hiv === 'Reactive') {
    $reactiveTestName = 'HIV';
} elseif ($hbsag === 'Reactive') {
    $reactiveTestName = 'Hepatitis B (HBsAg)';
} elseif ($hcv === 'Reactive') {
    $reactiveTestName = 'Hepatitis C';
} elseif ($syphilis === 'Reactive') {
    $reactiveTestName = 'Syphilis';
} elseif ($malaria === 'Reactive') {
    $reactiveTestName = 'Malaria';
}

if ($finalResult === 'Discard' && $donorIdFromDonation > 0) {
    // Set status to 'Deferred' and calculate deferral end date
    $deferralReason = "Positive {$reactiveTestName} result";
    $deferralUntilDate = date('Y-m-d', strtotime('+6 months'));
    
    $pdo->prepare('
        UPDATE tbldonors
        SET status = ?, 
            deferral_reason = ?,
            deferred_until = ?,
            deferred = 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ')->execute(['Deferred', $deferralReason, $deferralUntilDate, $donorIdFromDonation]);
    
    // Create notification for donor
    $pdo->prepare('
        INSERT INTO tblnotifications (user_id, title, message, type, severity, channel)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([
        $donorUserId,
        '⏸️ Temporary Deferral Notice',
        sprintf(
            'Your recent blood test showed positive result for %s. ' .
            'You are temporarily deferred for 6 months as a safety measure. ' .
            'You can reapply on %s. Please contact the blood bank for more information.',
            $reactiveTestName,
            $deferralUntilDate
        ),
        'deferral',
        'warning',
        'both'  // Send as in-app AND email
    ]);
    
    // Send email to donor
    bts_send_email(
        $donorEmail,
        '⏸️ Your Blood Donation - Temporary Deferral',
        sprintf(
            "Dear %s,\n\n" .
            "Your recent blood screening showed a positive result for: %s\n\n" .
            "As a safety measure, we must defer you temporarily for 6 months.\n" .
            "You can reapply on: %s\n\n" .
            "This is standard practice. For questions, call our helpline: 1095\n\n" .
            "Best regards,\nBlood Transfusion Services",
            $donorName,
            $reactiveTestName,
            date('F j, Y', strtotime($deferralUntilDate))
        )
    );
}
```

---

## 2. Registration Success Response (register_donor.php)

Update the response to include status information:

```php
// After successful registration
echo json_encode([
    'success' => true,
    'id' => $donorId,
    'name' => $fullName,
    'email' => $email,
    'role' => 'donor',
    'token' => bts_create_token(['id' => $donorId, 'email' => $email, 'role' => 'donor']),
    'status' => 'Pending',  // New field
    'message' => 'Registration submitted successfully!',
    'nextSteps' => 'Your application will be reviewed within 24-48 hours.'
]);
```

---

## 3. Dashboard Status Messages (Dashboard.js)

```javascript
// Comprehensive status messaging
const statusMessages = {
  pending: {
    badge: "⏳ Pending Approval",
    message: "Your registration is under review. We'll email you when approved.",
    action: "Check your email for updates",
    color: "warning"
  },
  confirmed: {
    badge: "✅ Eligible",
    message: "You're approved to donate! Book an appointment below.",
    action: "Book appointment",
    color: "success"
  },
  active: {
    badge: "✅ Eligible",
    message: "You're approved to donate! Book an appointment below.",
    action: "Book appointment",
    color: "success"
  },
  deferred: {
    badge: "⏸️ Temporarily Deferred",
    message: "Your donation was not suitable at this time. Please contact the blood bank for details.",
    deferralDate: profile?.deferred_until,  // Will show "Can reapply on: April 27, 2027"
    action: "Contact Blood Bank (1095)",
    color: "danger"
  },
  rejected: {
    badge: "❌ Not Approved",
    message: "Your registration was not approved. Please contact the blood bank for more information.",
    action: "Call 1095 (24/7)",
    color: "danger"
  }
};

// Render status banner
const status = String(profile?.status || "pending").toLowerCase();
const config = statusMessages[status];

return (
  <div className={`status-banner status-${config.color}`}>
    <div className="status-header">
      <span className="status-badge">{config.badge}</span>
    </div>
    <p className="status-message">{config.message}</p>
    {config.deferralDate && (
      <p className="status-deferral">
        You can reapply on: <strong>{new Date(config.deferralDate).toLocaleDateString()}</strong>
      </p>
    )}
    <button className="status-action-btn">{config.action}</button>
  </div>
);
```

---

## 4. Admin Donor Detail View Enhancement

```javascript
// In AdminDashboard.js - show rejection reason
{donor?.status?.toLowerCase() === 'deferred' && (
  <div className="admin-detail-field">
    <span className="admin-detail-label">Deferral Reason</span>
    <span className="admin-detail-value" style={{ color: '#856404' }}>
      {donor.deferral_reason || 'No reason specified'}
    </span>
  </div>
)}

{donor?.deferred_until && (
  <div className="admin-detail-field">
    <span className="admin-detail-label">Can Reapply On</span>
    <span className="admin-detail-value">
      {new Date(donor.deferred_until).toLocaleDateString()}
    </span>
  </div>
)}
```

---

## 5. Notifications Panel (Dashboard.js)

```javascript
const [notifications, setNotifications] = useState([]);
const [showNotifications, setShowNotifications] = useState(false);

// Fetch notifications on mount
useEffect(() => {
  if (user) {
    authFetch('get_notifications.php')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setNotifications(data.data);
        }
      });
  }
}, [user]);

// Notification types and icons
const notificationIcons = {
  approval: '✅',
  deferral: '⏸️',
  rejection: '❌',
  alert: '⚠️',
  generic: 'ℹ️'
};

return (
  <div className="dash-notifications">
    <button 
      className="notification-bell"
      onClick={() => setShowNotifications(!showNotifications)}
    >
      🔔 {notifications.filter(n => !n.is_read).length}
    </button>
    
    {showNotifications && (
      <div className="notification-dropdown">
        {notifications.length === 0 ? (
          <p className="no-notifications">No notifications</p>
        ) : (
          <ul className="notification-list">
            {notifications.map(notif => (
              <li key={notif.id} className={`notif-item ${notif.type}`}>
                <span className="notif-icon">
                  {notificationIcons[notif.type] || '📬'}
                </span>
                <div className="notif-content">
                  <strong>{notif.title}</strong>
                  <p>{notif.message}</p>
                  <time>{new Date(notif.created_at).toLocaleString()}</time>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    )}
  </div>
);
```

---

## 6. Email Template (for deferral notification)

```text
From: Blood Transfusion Services <noreply@bloodbank.bt>
To: [donor_email]
Subject: ⏸️ Your Blood Donation - Temporary Deferral

Dear [Donor Name],

Your recent blood screening showed a POSITIVE RESULT for: [TEST NAME]

WHAT THIS MEANS:
As a safety measure to protect blood recipients, we must defer you temporarily.
This is standard medical practice, not a permanent rejection.

WHEN YOU CAN DONATE AGAIN:
You can reapply for donation on or after: [DEFERRAL_DATE]

That's approximately 6 months from today.

NEXT STEPS:
1. Your donation was not used
2. Your health information is confidential
3. We recommend consulting with your healthcare provider
4. Contact us anytime with questions

CONTACT US:
Helpline: 1095 (24/7)
Email: donors@bloodbank.bt
Website: https://www.bloodbank.bt

Thank you for your interest in saving lives.
Once you're eligible again, we'd welcome your donation.

Best regards,
Blood Transfusion Services
Ministry of Health, Bhutan
```

---

## 7. Status Transition Chart (Database View)

```
Registration Form
    ↓ [Submit] → INSERT tbldonors(status='Pending')
    ↓
Donor sees: RegistrationSuccess screen
    ↓ [Wait for admin review]
    ↓
Dashboard shows: "⏳ Pending Approval"
    ├─ [If Admin clicks Approve]
    │   └─ UPDATE tbldonors SET status='Confirmed'
    │       → Email sent to donor
    │       → Notification: "✅ You're approved!"
    │       → Donor can book appointments
    │
    ├─ [If Staff records positive test]
    │   └─ UPDATE tbldonors SET status='Deferred', deferral_until='[DATE+6m]'
    │       → Email sent to donor: "⏸️ Temporarily deferred"
    │       → Notification: "⏸️ Can reapply on [DATE]"
    │       → Admin sees deferral reason: "Positive HIV"
    │
    └─ [If Admin manually rejects]
        └─ UPDATE tbldonors SET status='Rejected'
            → Email sent to donor
            → Notification: "❌ Not approved - contact us"
```

---

## Summary of Status Values

| Status | Donor Sees | Can Donate? | Next Action |
|--------|-----------|-----------|-------------|
| **Pending** | ⏳ Awaiting review | No | Wait for approval email |
| **Confirmed** | ✅ Approved | Yes | Book appointment |
| **Active** | ✅ Approved | Yes | Book appointment |
| **Deferred** | ⏸️ Temporary hold | No | Contact bank or wait until date |
| **Rejected** | ❌ Not approved | No | Contact blood bank |

---

## Key Implementation Points

1. **Post-Registration**: Show `RegistrationSuccess.js` with pending status
2. **Notifications**: Use `tblnotifications` table for all status changes
3. **Deferral Reasons**: Store specific test name (e.g., "Positive HIV")
4. **Deferral Date**: Set `deferred_until` to exactly 6 months from test date
5. **Email on Status Change**: Send emails for approval, deferral, and rejection
6. **Admin Visibility**: Show deferral reason and re-apply date in donor detail view
7. **Donor Clarity**: Dashboard message explains what each status means and what to do

