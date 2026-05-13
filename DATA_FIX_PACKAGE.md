# Blood Donation System - Data & Code Fix Package
**Date:** May 9, 2026  
**Status:** Ready for Manual Review & Execution

---

## ⚠️ IMPORTANT: Safety First
1. **Backup your database** before running any SQL
2. **Run safety check queries FIRST** to confirm the problems
3. **Review each SQL fix** before executing
4. **Execute one category at a time**, verify results, then move to next

---

## PART 1: SAFETY CHECK QUERIES
Run these **READ-ONLY** queries first to verify the issues exist.

### Check 1: HIV Donors Not Permanently Deferred
```sql
-- Should show donors with HIV in deferral_reason but NOT in decision_made_rejected status
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  deferral_reason,
  deferred_until
FROM tbldonors 
WHERE deferral_reason LIKE '%HIV%' 
  AND workflow_status != 'decision_made_rejected'
ORDER BY full_name;
```

**Expected Result:** Should show `Tshering sonam` with `workflow_status = 'temporarily_deferred'` or similar (WRONG)

---

### Check 2: Stage 2 Donors with Wrong Test Result
```sql
-- Stage 2 donors should have latest_test_result = 'not_tested', not 'negative'
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  sample_tested
FROM tbldonors 
WHERE workflow_status = 'approved_for_blood_draw' 
  AND latest_test_result != 'not_tested'
ORDER BY full_name;
```

**Expected Result:** Should show Gaki Pem, Rinchen, Tshering, Dorji Wangmo with `latest_test_result = 'negative'` (WRONG)

---

### Check 3: Negative Donors with Deferred Status
```sql
-- Negative test result should NEVER have deferred = 1
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  deferred,
  deferral_reason
FROM tbldonors 
WHERE latest_test_result = 'negative' 
  AND deferred = 1
ORDER BY full_name;
```

**Expected Result:** Should show Yedam Lham, Lhamo with deferred = 1 (WRONG)

---

### Check 4: Malaria Donors Without Deferral Date
```sql
-- Malaria temporary deferral should have a future deferred_until date
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  deferral_reason,
  deferred_until,
  DATEDIFF(deferred_until, CURDATE()) as days_remaining
FROM tbldonors 
WHERE deferral_reason LIKE '%Malaria%'
  AND workflow_status = 'decision_made_deferred'
  AND (deferred_until IS NULL OR deferred_until < CURDATE())
ORDER BY full_name;
```

**Expected Result:** May show Henry with NULL or past date (potentially WRONG)

---

## PART 2: DATA FIX SQL SCRIPTS

### FIX A: Stage 2 Donors - Reset to "Not Tested"
**Problem:** Donors in Stage 2 (approved_for_blood_draw) incorrectly show `latest_test_result = 'negative'`  
**Solution:** Change to `'not_tested'` so UI shows blank/—

```sql
-- Fix Stage 2 donors that incorrectly have 'negative' test result
UPDATE tbldonors 
SET 
  latest_test_result = 'not_tested',
  sample_tested = 'Pending'
WHERE workflow_status = 'approved_for_blood_draw' 
  AND latest_test_result = 'negative';

-- Verify the update
SELECT full_name, workflow_status, latest_test_result, sample_tested
FROM tbldonors
WHERE workflow_status = 'approved_for_blood_draw'
ORDER BY full_name;
```

---

### FIX B: HIV Donor - Enforce Permanent Deferral
**Problem:** Tshering sonam (HIV+) shows "Temporarily Deferred" instead of "Permanently Deferred"  
**Solution:** Set workflow_status to `'decision_made_rejected'` and clear deferred_until

```sql
-- Fix HIV donor to permanent deferral
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_rejected',
  status = 'Permanently Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = NULL,  -- NULL = permanent (no end date)
  deferral_reason = 'Positive (HIV) - Permanent Deferral'
WHERE full_name = 'Tshering sonam';

-- Verify the update
SELECT full_name, workflow_status, status, latest_test_result, deferred_until, deferral_reason
FROM tbldonors
WHERE full_name = 'Tshering sonam';
```

---

### FIX C: Malaria Donor - Set Temporary Deferral (6 months default)
**Problem:** Henry (Malaria+) may not have correct deferral date  
**Solution:** Set to temporary deferral with 6-month default, allow admin to adjust later

```sql
-- Fix Malaria donor to temporary deferral (6 months default)
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_deferred',
  status = 'Temporarily Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),  -- 6 months from today
  deferral_reason = 'Positive (Malaria) - Temporary deferral 6 months'
WHERE full_name = 'Henry';

-- Verify the update
SELECT full_name, workflow_status, status, latest_test_result, deferred_until, deferral_reason
FROM tbldonors
WHERE full_name = 'Henry';
```

---

### FIX D: Negative Donors with Wrong Status - Reset to Approved
**Problem:** Yedam Lham and Lhamo are negative but marked as Deferred  
**Solution:** Set to `'decision_made_accepted'` (Approved for Blood Donation)

```sql
-- Fix Yedam Lham: Negative but Temporarily Deferred → Approved Donor
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_accepted',
  status = 'Approved Donor',
  latest_test_result = 'negative',
  deferred = 0,
  deferred_until = NULL,
  deferral_reason = NULL
WHERE full_name = 'Yedam Lham';

-- Fix Lhamo: Negative but Permanently Deferred → Approved Donor
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_accepted',
  status = 'Approved Donor',
  latest_test_result = 'negative',
  deferred = 0,
  deferred_until = NULL,
  deferral_reason = NULL
WHERE full_name = 'Lhamo';

-- Verify the updates
SELECT full_name, workflow_status, status, latest_test_result, deferred, deferral_reason
FROM tbldonors
WHERE full_name IN ('Yedam Lham', 'Lhamo')
ORDER BY full_name;
```

---

## PART 3: COMPREHENSIVE FIX SCRIPT
If you want to run all fixes at once (after verifying individually), use this:

```sql
-- ============================================
-- BLOOD DONATION SYSTEM - DATA FIX SCRIPT
-- ============================================
-- Backup before running!
-- Run safety checks FIRST to confirm issues

-- FIX 1: Stage 2 Donors - Reset to "Not Tested"
UPDATE tbldonors 
SET latest_test_result = 'not_tested', sample_tested = 'Pending'
WHERE workflow_status = 'approved_for_blood_draw' AND latest_test_result = 'negative';

-- FIX 2: HIV Donor - Permanent Deferral
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_rejected',
  status = 'Permanently Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = NULL,
  deferral_reason = 'Positive (HIV) - Permanent Deferral'
WHERE full_name = 'Tshering sonam';

-- FIX 3: Malaria Donor - Temporary Deferral (6 months)
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_deferred',
  status = 'Temporarily Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
  deferral_reason = 'Positive (Malaria) - Temporary deferral 6 months'
WHERE full_name = 'Henry';

-- FIX 4: Negative Donors - Reset to Approved
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_accepted',
  status = 'Approved Donor',
  latest_test_result = 'negative',
  deferred = 0,
  deferred_until = NULL,
  deferral_reason = NULL
WHERE full_name IN ('Yedam Lham', 'Lhamo');

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
SELECT 'AFTER FIXES - All Donors' as verification;
SELECT full_name, workflow_status, latest_test_result, deferred, deferred_until, deferral_reason
FROM tbldonors
WHERE full_name IN ('Gaki Pem', 'Rinchen', 'Tshering', 'Dorji Wangmo', 'Tshering sonam', 'Henry', 'Yedam Lham', 'Lhamo')
ORDER BY full_name;
```

---

## PART 4: CODE CHANGES - Malaria Deferral Duration Selector

### New Feature: Flexible Malaria Deferral (6/9/12 months)
The admin should be able to choose deferral duration when deferring a Malaria+ donor.

#### File: `src/pages/AdminDashboard.js`
Add this state and handler in the component (alongside existing defer handlers):

```javascript
// Add to component state hooks:
const [deferralMonths, setDeferralMonths] = useState(6); // default 6 months

// Add this handler function alongside existing deferral handlers:
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

#### File: `src/pages/AdminDashboard.js` - Update Defer Temporary Modal
Add a dropdown for malaria-specific deferral duration (look for the temp defer button/modal rendering):

```jsx
// In the render section where temp defer button appears:
{deferralType === 'temporary' && (
  <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
    {/* Check if donor has Malaria - show duration selector */}
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
          cursor: 'pointer'
        }}
      >
        <option value={6}>6 months</option>
        <option value={9}>9 months</option>
        <option value={12}>12 months</option>
      </select>
    )}
    
    <button
      className="admin-action-btn warn"
      onClick={() => handleDeferMalariaDecision(row.id, row.full_name)}
      disabled={approving}
    >
      ⏸️ {approving ? 'Processing...' : 'Defer'}
    </button>
  </div>
)}
```

#### File: `backend/api/finalize_test_decision.php`
Update to accept `defer_months` parameter for temporary deferrals:

```php
// In the existing finalize_test_decision.php, update this section:

// Get deferral duration (default 6 months for temp_defer)
$deferMonths = isset($_POST['defer_months']) ? (int)$_POST['defer_months'] : 6;
$deferUntilValue = null;

if ($normalizedDecision === 'temp_defer') {
  // Calculate future date based on months specified
  $deferDate = new DateTime();
  $deferDate->add(new DateInterval("P{$deferMonths}M")); // Add X months
  $deferUntilValue = $deferDate->format('Y-m-d');
}

// Then in UPDATE tbldonors:
UPDATE tbldonors 
SET 
  workflow_status = ?,
  deferred_until = ?,
  deferral_reason = ?,
  ...
```

---

## PART 5: BACKEND VALIDATION RULES (Optional - Future Enhancement)

Add these validation checks to prevent future data corruption:

### File: `backend/api/validate_donor_state.php` (NEW FILE)
```php
<?php
/**
 * Validation helper: enforce medical + workflow consistency
 */

function validateDonorState($mysqli, $donor_id, $workflow_status, $latest_test_result, $deferred_until) {
  $errors = [];

  // Rule 1: Stage 2 must have not_tested
  if ($workflow_status === 'approved_for_blood_draw' && $latest_test_result !== 'not_tested') {
    $errors[] = "Stage 2 donors must have latest_test_result = 'not_tested'";
  }

  // Rule 2: Permanent deferral (decision_made_rejected) must have NULL deferred_until
  if ($workflow_status === 'decision_made_rejected' && $deferred_until !== NULL) {
    $errors[] = "Permanent deferral must have deferred_until = NULL";
  }

  // Rule 3: Temporary deferral must have future date
  if ($workflow_status === 'decision_made_deferred' && 
      ($deferred_until === NULL || strtotime($deferred_until) <= time())) {
    $errors[] = "Temporary deferral must have a future deferred_until date";
  }

  // Rule 4: Negative results cannot be deferred
  if ($latest_test_result === 'negative' && $workflow_status !== 'decision_made_accepted') {
    $errors[] = "Negative test results cannot be deferred";
  }

  return $errors; // empty = valid, otherwise list of violation messages
}

// Usage in other API endpoints:
// $validation = validateDonorState($mysqli, $donor_id, $workflow_status, $test_result, $defer_date);
// if (!empty($validation)) {
//   http_response_code(400);
//   echo json_encode(['error' => 'Validation failed', 'violations' => $validation]);
//   exit;
// }
?>
```

---

## EXECUTION CHECKLIST

- [ ] **Step 1:** Backup your database
- [ ] **Step 2:** Run all 4 safety check queries → verify problems exist
- [ ] **Step 3:** Run FIX A (Stage 2 donors)
- [ ] **Step 4:** Run FIX B (HIV donor)
- [ ] **Step 5:** Run FIX C (Malaria donor)
- [ ] **Step 6:** Run FIX D (Negative donors)
- [ ] **Step 7:** Run final verification query (shows expected state)
- [ ] **Step 8:** Hard refresh admin UI (Ctrl+F5)
- [ ] **Step 9:** Verify all donors display correctly
- [ ] **Step 10:** (Optional) Add malaria deferral dropdown code

---

## Expected Final State

| Donor | Test Result | Workflow Status | Deferral Type |
|-------|-------------|-----------------|---------------|
| Tshering sonam | Positive (HIV) | decision_made_rejected | Permanent ✅ |
| Henry | Positive (Malaria) | decision_made_deferred | Temporary (6 mo) ✅ |
| Yedam Lham | Negative | decision_made_accepted | No deferral ✅ |
| Lhamo | Negative | decision_made_accepted | No deferral ✅ |
| Gaki Pem | — (not_tested) | approved_for_blood_draw | Stage 2 ✅ |
| Rinchen | — (not_tested) | approved_for_blood_draw | Stage 2 ✅ |
| Tshering | — (not_tested) | approved_for_blood_draw | Stage 2 ✅ |
| Dorji Wangmo | — (not_tested) | approved_for_blood_draw | Stage 2 ✅ |

---

## Questions Before You Execute?
- Any donor names spelled differently in your DB?
- Do you have other negative donors with wrong status?
- Should Malaria always default to 6 months, or should it ask first?

