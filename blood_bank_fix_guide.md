# Blood Bank Management System - Fix Workflow & Dashboard Issues

## 1. BACKUP INSTRUCTIONS

### Backup tbldonors Table Before Any Changes

```sql
-- Create backup table
CREATE TABLE tbldonors_backup_YYYYMMDD AS SELECT * FROM tbldonors;

-- Alternative: Export to file
-- SELECT * FROM tbldonors INTO OUTFILE '/tmp/tbldonors_backup.csv' FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';
```

### Verify Backup
```sql
-- Check backup count matches original
SELECT COUNT(*) as original_count FROM tbldonors;
SELECT COUNT(*) as backup_count FROM tbldonors_backup_YYYYMMDD;
```

## 2. SQL TO FIX WRONG RECORDS

### Update Statements for Specific Donors

```sql
-- Fix Henry: Positive (Malaria) should be Temporary Deferral (6 months)
UPDATE tbldonors 
SET 
    workflow_status = 'decision_made_deferred',
    deferred = 1,
    deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
    deferral_reason = 'Positive (Malaria)',
    status = 'Temporary Deferral (6 months)'
WHERE full_name = 'Henry' AND latest_test_result = 'positive';

-- Fix Nado: Negative should be Approved for Blood Donation
UPDATE tbldonors 
SET 
    workflow_status = 'decision_made_accepted',
    deferred = 0,
    deferred_until = NULL,
    deferral_reason = NULL,
    status = 'Approved for Blood Donation'
WHERE full_name = 'Nado' AND latest_test_result = 'negative';

-- Fix tts: No test result should be Ready for Blood Draw (Stage 2)
UPDATE tbldonors 
SET 
    workflow_status = 'approved_for_blood_draw',
    deferred = 0,
    deferred_until = NULL,
    deferral_reason = NULL,
    status = 'Ready for Blood Draw (Stage 2)'
WHERE full_name = 'tts' AND latest_test_result = 'not_tested';

-- Fix yoyo: Negative should be Approved for Blood Donation
UPDATE tbldonors 
SET 
    workflow_status = 'decision_made_accepted',
    deferred = 0,
    deferred_until = NULL,
    deferral_reason = NULL,
    status = 'Approved for Blood Donation'
WHERE full_name = 'yoyo' AND latest_test_result = 'negative';
```

### Verify Updates
```sql
-- Check each donor after update
SELECT full_name, latest_test_result, workflow_status, status, deferred, deferred_until, deferral_reason 
FROM tbldonors 
WHERE full_name IN ('Henry', 'Nado', 'tts', 'yoyo')
ORDER BY full_name;
```

## 3. SAFETY CHECK QUERIES

### Find Other Inconsistent Records

```sql
-- Find negative test results with wrong workflow status
SELECT 
    full_name, 
    latest_test_result, 
    workflow_status, 
    status,
    'Negative test should be decision_made_accepted' as issue
FROM tbldonors 
WHERE 
    latest_test_result = 'negative' 
    AND workflow_status != 'decision_made_accepted'
    AND workflow_status != 'pending_approval'  -- Allow pending status
    AND workflow_status != 'approved_for_blood_draw'  -- Allow not yet drawn
    AND workflow_status != 'blood_drawn_pending_test';  -- Allow awaiting test

-- Find positive test results with wrong workflow status
SELECT 
    full_name, 
    latest_test_result, 
    workflow_status, 
    status,
    'Positive test should be deferred or rejected' as issue
FROM tbldonors 
WHERE 
    latest_test_result = 'positive' 
    AND workflow_status NOT IN ('decision_made_deferred', 'decision_made_rejected');

-- Find donors with no test result but wrong workflow status
SELECT 
    full_name, 
    latest_test_result, 
    workflow_status, 
    status,
    'No test result should be pending or approved_for_blood_draw' as issue
FROM tbldonors 
WHERE 
    latest_test_result = 'not_tested' 
    AND workflow_status NOT IN ('pending_approval', 'approved_for_blood_draw');

-- Find deferred donors with no deferral reason
SELECT 
    full_name, 
    latest_test_result, 
    workflow_status, 
    status,
    deferred,
    deferred_until,
    deferral_reason,
    'Deferred donor missing deferral reason or date' as issue
FROM tbldonors 
WHERE 
    deferred = 1 
    AND (deferral_reason IS NULL OR deferred_until IS NULL);

-- Find expired deferrals that should be reviewed
SELECT 
    full_name, 
    latest_test_result, 
    workflow_status, 
    status,
    deferred_until,
    'Deferral expired - should be reviewed' as issue
FROM tbldonors 
WHERE 
    deferred = 1 
    AND deferred_until < CURDATE();
```

## 4. STAGE 2 CONFIRMATION

### Query to Verify Stage 2 Donors After Fixes
```sql
-- This should show ONLY donors ready for blood draw
SELECT 
    full_name, 
    email, 
    phone, 
    blood_type,
    latest_test_result,
    workflow_status,
    status
FROM tbldonors 
WHERE workflow_status = 'approved_for_blood_draw'
ORDER BY full_name;
```

### Expected Stage 2 Donors After Fixes:
- **tts** (no test result, approved for blood draw)
- **Tshering yangdon** (no test result, approved for blood draw) ✅
- **tt** (no test result, approved for blood draw) ✅

## 5. AUTO-UPDATE LOGIC (PHP/Pseudocode)

### PHP Function to Update Workflow Status Based on Test Results

```php
<?php
function updateDonorWorkflowStatus($pdo, $donorId, $testResults, $bloodDrawn = false) {
    try {
        // Get current donor info
        $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE id = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Determine workflow status based on conditions
        $workflowStatus = '';
        $deferred = 0;
        $deferredUntil = null;
        $deferralReason = null;
        $status = '';
        
        if ($bloodDrawn) {
            // Blood drawn, awaiting lab results
            $workflowStatus = 'blood_drawn_pending_test';
            $status = 'Awaiting Test Results';
        } else {
            // Based on test results
            if ($testResults['all_negative']) {
                // All tests negative
                $workflowStatus = 'decision_made_accepted';
                $status = 'Approved for Blood Donation';
                $deferred = 0;
                $deferredUntil = null;
                $deferralReason = null;
            } elseif ($testResults['malaria_positive'] && !$testResults['hiv_positive'] && !$testResults['hepatitis_positive'] && !$testResults['syphilis_positive']) {
                // Only Malaria positive - temporary deferral (6 months)
                $workflowStatus = 'decision_made_deferred';
                $status = 'Temporary Deferral (6 months)';
                $deferred = 1;
                $deferredUntil = date('Y-m-d', strtotime('+6 months'));
                $deferralReason = 'Positive (Malaria)';
            } elseif ($testResults['hiv_positive'] || $testResults['hepatitis_positive'] || $testResults['syphilis_positive']) {
                // HIV/Hepatitis/Syphilis positive - permanent deferral
                $workflowStatus = 'decision_made_rejected';
                $status = 'Permanently Deferred';
                $deferred = 1;
                $deferredUntil = null;
                $deferralReason = 'Positive (' . 
                    ($testResults['hiv_positive'] ? 'HIV' : '') .
                    ($testResults['hepatitis_positive'] ? ($testResults['hiv_positive'] ? '/Hepatitis' : 'Hepatitis') : '') .
                    ($testResults['syphilis_positive'] ? ($testResults['hiv_positive'] || $testResults['hepatitis_positive'] ? '/Syphilis' : 'Syphilis') : '') . ')';
            } else {
                // Other positive results - temporary deferral
                $workflowStatus = 'decision_made_deferred';
                $status = 'Temporary Deferral (6 months)';
                $deferred = 1;
                $deferredUntil = date('Y-m-d', strtotime('+6 months'));
                $deferralReason = 'Positive test result';
            }
        }
        
        // Update donor record
        $updateStmt = $pdo->prepare("
            UPDATE tbldonors SET 
                workflow_status = ?,
                status = ?,
                deferred = ?,
                deferred_until = ?,
                deferral_reason = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $workflowStatus,
            $status,
            $deferred,
            $deferredUntil,
            $deferralReason,
            $donorId
        ]);
        
        return [
            'success' => true,
            'new_status' => $workflowStatus,
            'status_label' => $status
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Example usage when test results are finalized:
$testResults = [
    'all_negative' => false,
    'hiv_positive' => false,
    'hepatitis_positive' => false,
    'syphilis_positive' => false,
    'malaria_positive' => true
];

$result = updateDonorWorkflowStatus($pdo, $donorId, $testResults);
?>
```

### Trigger-Based Auto-Update (MySQL)

```sql
-- Create trigger to automatically update workflow status when test result changes
DELIMITER //
CREATE TRIGGER update_workflow_on_test_result
BEFORE UPDATE ON tbldonors
FOR EACH ROW
BEGIN
    -- Only trigger if test result is actually changing
    IF NEW.latest_test_result != OLD.latest_test_result THEN
        -- Blood drawn case
        IF NEW.sample_tested = 'Pending' AND OLD.sample_tested != 'Pending' THEN
            SET NEW.workflow_status = 'blood_drawn_pending_test';
            SET NEW.status = 'Awaiting Test Results';
        -- Negative test result
        ELSEIF NEW.latest_test_result = 'negative' THEN
            SET NEW.workflow_status = 'decision_made_accepted';
            SET NEW.status = 'Approved for Blood Donation';
            SET NEW.deferred = 0;
            SET NEW.deferred_until = NULL;
            SET NEW.deferral_reason = NULL;
        -- Positive test result (will need manual review for type)
        ELSEIF NEW.latest_test_result = 'positive' THEN
            SET NEW.workflow_status = 'test_result_pending_decision';
            SET NEW.status = 'Test Result – Pending Decision';
        END IF;
    END IF;
END//
DELIMITER ;
```

## 6. MALARIA DEFERRAL RULE CONFIRMATION

✅ **CONFIRMED:** Malaria is **temporary deferral (6 months)**, NOT permanent.

### Malaria Deferral Logic:
- **Malaria Positive** → `decision_made_deferred` + 6 months deferral
- **HIV/Hepatitis/Syphilis Positive** → `decision_made_rejected` (permanent)
- **Other Positive Results** → `decision_made_deferred` + 6 months deferral

### SQL to Handle Malaria Specifically:
```sql
-- Update malaria-positive donors to temporary deferral
UPDATE tbldonors 
SET 
    workflow_status = 'decision_made_deferred',
    status = 'Temporary Deferral (6 months)',
    deferred = 1,
    deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
    deferral_reason = 'Positive (Malaria)'
WHERE 
    latest_test_result = 'positive' 
    AND (deferral_reason LIKE '%malaria%' OR deferral_reason LIKE '%Malaria%')
    AND workflow_status != 'decision_made_deferred';
```

## 7. EDIT BUTTON FEATURE IMPLEMENTATION PLAN

### Phase 1: Database Structure

#### Create Audit Log Table
```sql
CREATE TABLE donor_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by VARCHAR(100),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_reason VARCHAR(255),
    FOREIGN KEY (donor_id) REFERENCES tbldonors(id)
);
```

### Phase 2: Frontend Implementation

#### Replace "View Details" Button with Edit Button
```javascript
// Replace this in your admin dashboard HTML
<button class="btn btn-edit" onclick="openEditModal(${donor.id})">
    ✏️ Edit
</button>
```

#### Edit Modal HTML Structure
```html
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Donor Information</h3>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="edit-form">
            <input type="hidden" id="edit-donor-id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="edit-name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="edit-email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" id="edit-phone">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" id="edit-dob">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Gender</label>
                    <select id="edit-gender">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Blood Type</label>
                    <select id="edit-blood-type">
                        <option value="">Select</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Workflow Status</label>
                    <select id="edit-workflow-status">
                        <option value="pending_approval">Pending Review</option>
                        <option value="approved_for_blood_draw">Ready for Blood Draw (Stage 2)</option>
                        <option value="blood_drawn_pending_test">Awaiting Test Results</option>
                        <option value="test_result_pending_decision">Test Result – Pending Decision</option>
                        <option value="decision_made_accepted">Approved for Blood Donation</option>
                        <option value="decision_made_deferred">Temporary Deferral (6 months)</option>
                        <option value="decision_made_rejected">Permanently Deferred</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Deferral Date</label>
                    <input type="date" id="edit-defer-date">
                </div>
            </div>
            
            <div class="form-group">
                <label>Change Reason *</label>
                <textarea id="edit-reason" placeholder="Why are you making these changes?" required></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDonorChanges()">Save Changes</button>
            </div>
        </form>
    </div>
</div>
```

### Phase 3: JavaScript Functions

```javascript
// Open edit modal with donor data
function openEditModal(donorId) {
    const donor = donors.find(d => d.id === donorId);
    if (!donor) return;
    
    // Populate form fields
    document.getElementById('edit-donor-id').value = donor.id;
    document.getElementById('edit-name').value = donor.full_name || '';
    document.getElementById('edit-email').value = donor.email || '';
    document.getElementById('edit-phone').value = donor.phone || '';
    document.getElementById('edit-dob').value = donor.date_of_birth || '';
    document.getElementById('edit-gender').value = donor.gender || '';
    document.getElementById('edit-blood-type').value = donor.blood_type || '';
    document.getElementById('edit-workflow-status').value = donor.workflow_status || '';
    document.getElementById('edit-defer-date').value = donor.deferred_until || '';
    
    document.getElementById('edit-modal').style.display = 'block';
}

// Save donor changes
function saveDonorChanges() {
    const donorId = document.getElementById('edit-donor-id').value;
    const reason = document.getElementById('edit-reason').value;
    
    if (!reason.trim()) {
        alert('Please provide a reason for the changes');
        return;
    }
    
    // Get form data
    const formData = {
        id: donorId,
        full_name: document.getElementById('edit-name').value,
        email: document.getElementById('edit-email').value,
        phone: document.getElementById('edit-phone').value,
        date_of_birth: document.getElementById('edit-dob').value,
        gender: document.getElementById('edit-gender').value,
        blood_type: document.getElementById('edit-blood-type').value,
        workflow_status: document.getElementById('edit-workflow-status').value,
        deferred_until: document.getElementById('edit-defer-date').value,
        change_reason: reason,
        changed_by: 'admin' // Get from session
    };
    
    // Send to backend
    fetch('api/update_donor.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Donor updated successfully');
            closeEditModal();
            refreshDonorList(); // Reload donor data
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating donor');
    });
}

// Close modal
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
    document.getElementById('edit-form').reset();
}
```

### Phase 4: Backend API (update_donor.php)

```php
<?php
header('Content-Type: application/json');

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=blood_donation", "username", "password");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$donorId = $data['id'];
$changedBy = $data['changed_by'] ?? 'system';
$changeReason = $data['change_reason'] ?? '';

// Get current donor data for audit
$stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE id = ?");
$stmt->execute([$donorId]);
$oldData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oldData) {
    echo json_encode(['success' => false, 'message' => 'Donor not found']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update donor record
    $updateFields = [];
    $updateValues = [];
    
    $fieldMap = [
        'full_name' => 'full_name',
        'email' => 'email',
        'phone' => 'phone',
        'date_of_birth' => 'date_of_birth',
        'gender' => 'gender',
        'blood_type' => 'blood_type',
        'workflow_status' => 'workflow_status',
        'deferred_until' => 'deferred_until'
    ];
    
    foreach ($fieldMap as $dataField => $dbField) {
        if (isset($data[$dataField])) {
            $updateFields[] = "$dbField = ?";
            $updateValues[] = $data[$dataField];
        }
    }
    
    if (!empty($updateFields)) {
        $updateFields[] = "updated_at = NOW()";
        $updateValues[] = $donorId;
        
        $updateSQL = "UPDATE tbldonors SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($updateSQL);
        $stmt->execute($updateValues);
        
        // Create audit log entries
        foreach ($fieldMap as $dataField => $dbField) {
            if (isset($data[$dataField]) && $oldData[$dbField] != $data[$dataField]) {
                $auditStmt = $pdo->prepare("
                    INSERT INTO donor_audit_log 
                    (donor_id, field_name, old_value, new_value, changed_by, change_reason) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $auditStmt->execute([
                    $donorId,
                    $dbField,
                    $oldData[$dbField],
                    $data[$dataField],
                    $changedBy,
                    $changeReason
                ]);
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Donor updated successfully']);
    
} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
```

### Phase 5: Audit Log Viewing

```sql
-- View audit log for a specific donor
SELECT * FROM donor_audit_log 
WHERE donor_id = ? 
ORDER BY changed_at DESC;

-- View all recent changes
SELECT 
    al.field_name,
    al.old_value,
    al.new_value,
    al.changed_by,
    al.changed_at,
    al.change_reason,
    d.full_name as donor_name
FROM donor_audit_log al
JOIN tbldonors d ON al.donor_id = d.id
ORDER BY al.changed_at DESC
LIMIT 50;
```

## EXECUTION PLAN

1. **Run backup commands first**
2. **Run safety check queries** to identify all issues
3. **Run specific UPDATE statements** for the 4 problematic donors
4. **Verify Stage 2 donors** list
5. **Implement auto-update logic** for future prevention
6. **Plan Edit button feature** implementation

All SQL scripts are provided for manual review and execution. Do not run automatically - review each statement before execution.
