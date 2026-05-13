# AdminDashboard.js - Malaria Deferral Duration Feature
Code changes to add flexible 6/9/12 month deferral selection for Malaria-positive donors.

## CHANGE 1: Add deferralMonths state hook

**Location:** Near top of component, with other useState declarations

```javascript
const [deferralMonths, setDeferralMonths] = useState(6); // default 6 months
```

---

## CHANGE 2: Add handler function for Malaria deferral

**Location:** After existing deferral handler functions (around line 920-990)

```javascript
const handleDeferMalariaDecision = async (donorId, donorName) => {
  const months = deferralMonths || 6;
  
  try {
    setApproving(true);
    setActionError('');

    // Find the sample for this donor
    const sample = donors && Array.isArray(donors)
      ? donors.find(d => d.id === donorId)
      : null;
    
    if (!sample?.sample_id) {
      setActionError('Sample ID not found.');
      return;
    }

    // Call finalize_test_decision with defer_temporary and deferral months
    const response = await fetch(
      'http://localhost:3001/backend/api/finalize_test_decision.php',
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          sample_id: sample.sample_id,
          decision: 'defer_temporary',
          defer_months: months,
          deferral_reason: `Positive (Malaria) - Temporary deferral ${months} months`
        })
      }
    );

    if (!response.ok) {
      const errorData = await response.text();
      throw new Error(errorData || 'Failed to defer donor.');
    }

    const data = await response.json();
    toast.success(`${donorName} deferred for ${months} month(s). Notification email sent.`);
    setDeferralMonths(6); // reset to default
    fetchDonors();
  } catch (err) {
    setActionError(err.message || 'Unable to defer donor.');
  } finally {
    setApproving(false);
  }
};
```

---

## CHANGE 3: Update Defer Temporary button/modal rendering

**Location:** In the table rendering section where deferral type buttons appear (around line 2090-2110)

Find this section:
```javascript
{deferralType === 'temporary' && (
  <button
    className="admin-action-btn warn"
    onClick={() => handleDeferTemporaryDecision(row.id, row.full_name)}
    disabled={approving}
  >
    ⏸️ {approving ? 'Processing...' : 'Defer (Temporary)'}
  </button>
)}
```

Replace with:
```javascript
{deferralType === 'temporary' && (
  <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
    {/* Show duration selector for Malaria donors */}
    {row?.positive_diseases?.toLowerCase().includes('malaria') && (
      <select
        value={deferralMonths}
        onChange={(e) => setDeferralMonths(parseInt(e.target.value))}
        style={{
          padding: '6px 12px',
          borderRadius: '4px',
          border: '1px solid #f0ad4e',
          backgroundColor: '#fff',
          fontSize: '12px',
          cursor: 'pointer',
          fontWeight: '500'
        }}
      >
        <option value={6}>6 months</option>
        <option value={9}>9 months</option>
        <option value={12}>12 months</option>
      </select>
    )}
    
    <button
      className="admin-action-btn warn"
      onClick={() => {
        if (row?.positive_diseases?.toLowerCase().includes('malaria')) {
          handleDeferMalariaDecision(row.id, row.full_name);
        } else {
          handleDeferTemporaryDecision(row.id, row.full_name);
        }
      }}
      disabled={approving}
    >
      ⏸️ {approving ? 'Processing...' : 'Defer'}
    </button>
  </div>
)}
```

---

## CHANGE 4: Update finalize_test_decision.php backend

**File:** `backend/api/finalize_test_decision.php`

**Location:** Find the section where you calculate `deferUntilValue` (around line 100-110)

Find this:
```php
$deferUntilValue = ($normalizedDecision === 'temp_defer' && $deferUntil) ? $deferUntil : null;
```

Replace with:
```php
$deferUntilValue = null;

if ($normalizedDecision === 'temp_defer') {
  // Check for explicit deferral duration in months (for Malaria, etc.)
  $deferMonths = isset($_POST['defer_months']) ? (int)$_POST['defer_months'] : 6;
  
  if ($deferUntil) {
    // Use specified date if provided
    $deferUntilValue = $deferUntil;
  } elseif ($deferMonths > 0) {
    // Calculate date by adding months
    $deferDate = new DateTime();
    $deferDate->add(new DateInterval("P{$deferMonths}M"));
    $deferUntilValue = $deferDate->format('Y-m-d');
  }
}
```

---

## CHANGE 5: Update deferral_reason in finalize_test_decision.php

**Location:** Find where `$messageContent` is built (around line 155-170)

Find this:
```php
elseif ($normalizedDecision === 'temp_defer') {
  $deferralStatusText = 'Temporarily Deferred';
  ...
}
```

Update the message construction to include months:
```php
elseif ($normalizedDecision === 'temp_defer') {
  $deferralStatusText = 'Temporarily Deferred';
  $deferMonths = isset($_POST['defer_months']) ? (int)$_POST['defer_months'] : 6;
  
  // Include deferral reason with months in message
  $customReason = isset($_POST['deferral_reason']) ? $_POST['deferral_reason'] : 
                  "Temporary deferral for {$deferMonths} months";
  
  $messageContent = "Dear {$donorName},\n\n" .
    "Thank you for your interest in donating blood. Your recent blood test results indicate that you are not eligible to donate at this time.\n\n" .
    "Reason: {$customReason}\n\n" .
    "You may be eligible to donate again after the deferral period ends. Please contact us for more information.\n\n" .
    "Your local blood bank team";
  ...
}
```

---

## Summary of Changes

| File | Change | Purpose |
|------|--------|---------|
| AdminDashboard.js | Add `deferralMonths` state | Track selected deferral duration |
| AdminDashboard.js | Add `handleDeferMalariaDecision()` | Handle Malaria-specific deferral |
| AdminDashboard.js | Update Defer button render | Show duration selector for Malaria |
| finalize_test_decision.php | Handle `defer_months` POST param | Accept and process duration |
| finalize_test_decision.php | Calculate date by months | Set future deferred_until date |

---

## Testing Checklist

After applying these changes:

- [ ] Reload admin UI (Ctrl+F5)
- [ ] Find a Malaria-positive donor in Stage 2
- [ ] Confirm Defer button shows duration dropdown
- [ ] Test selecting 6/9/12 months
- [ ] Click Defer button
- [ ] Verify:
  - Toast message shows selected duration
  - Workflow status changes to `decision_made_deferred`
  - Database `deferred_until` = today + X months
  - Donor notification email sent with correct duration info

---

## Troubleshooting

**Issue:** Dropdown doesn't appear for Malaria donor  
**Check:** Confirm `positive_diseases` includes "Malaria" (case-insensitive match)

**Issue:** Deferral date calculation is wrong  
**Check:** Database timezone is consistent; use `CURDATE()` not `NOW()`

**Issue:** Email doesn't include duration  
**Check:** `deferral_reason` parameter is passed from frontend and used in email template
