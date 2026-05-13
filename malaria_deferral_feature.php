<?php
// Flexible Malaria Deferral Feature Implementation
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

// ========================================
// BACKEND AUTO-UPDATE LOGIC
// ========================================

function updateDonorWorkflowStatus($pdo, $donorId, $testResult, $malariaMonths = 6) {
    try {
        // Get current donor info
        $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE id = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) {
            return ['success' => false, 'message' => 'Donor not found'];
        }
        
        // Determine workflow status based on test results
        $workflowStatus = '';
        $deferred = 0;
        $deferredUntil = null;
        $deferralReason = null;
        $status = '';
        
        if ($testResult == 'Positive (HIV)' || $testResult == 'Positive (Hepatitis B)' || $testResult == 'Positive (Hepatitis C)' || $testResult == 'Positive (Syphilis)') {
            // Permanent deferral for serious diseases
            $workflowStatus = 'decision_made_rejected';
            $status = 'Permanently Deferred';
            $deferred = 1;
            $deferredUntil = null;
            $deferralReason = 'Positive (' . str_replace('Positive ', '', $testResult) . ') - Permanent Deferral';
            
        } elseif ($testResult == 'Positive (Malaria)') {
            // Temporary deferral for malaria (flexible duration)
            $workflowStatus = 'decision_made_deferred';
            $status = 'Temporarily Deferred';
            $deferred = 1;
            $deferredUntil = date('Y-m-d', strtotime("+$malariaMonths months"));
            $deferralReason = 'Positive (Malaria) - Temporary deferral ' . $malariaMonths . ' months';
            
        } elseif ($testResult == 'Negative' || $testResult == 'Negative (all clear)') {
            // No deferral for negative results
            $workflowStatus = 'decision_made_accepted';
            $status = 'Approved for Blood Donation';
            $deferred = 0;
            $deferredUntil = null;
            $deferralReason = null;
            
        } elseif ($testResult == 'No test result yet' || $testResult == 'not_tested') {
            // No test result - ready for blood draw
            $workflowStatus = 'approved_for_blood_draw';
            $status = 'Ready for Blood Draw';
            $deferred = 0;
            $deferredUntil = null;
            $deferralReason = null;
            
        } else {
            // Other cases - awaiting test results
            $workflowStatus = 'blood_drawn_pending_test';
            $status = 'Awaiting Test Results';
            $deferred = 0;
            $deferredUntil = null;
            $deferralReason = null;
        }
        
        // Update donor record
        $updateStmt = $pdo->prepare("
            UPDATE tbldonors SET 
                workflow_status = ?,
                status = ?,
                latest_test_result = ?,
                sample_tested = ?,
                deferred = ?,
                deferred_until = ?,
                deferral_reason = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $sampleTested = ($testResult == 'Positive') ? 'Reactive' : (($testResult == 'Negative') ? 'Negative' : 'Pending');
        
        $result = $updateStmt->execute([
            $workflowStatus,
            $status,
            $testResult,
            $sampleTested,
            $deferred,
            $deferredUntil,
            $deferralReason,
            $donorId
        ]);
        
        return [
            'success' => $result,
            'new_status' => $workflowStatus,
            'status_label' => $status,
            'deferred_until' => $deferredUntil,
            'deferral_reason' => $deferralReason
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ========================================
// FRONTEND JAVASCRIPT FOR EDIT MODAL
// ========================================

echo "
<script>
// Function to show/hide malaria deferral options
function toggleMalariaDeferralOptions(testResult) {
    const malariaOptions = document.getElementById('malaria-deferral-options');
    if (malariaOptions) {
        if (testResult === 'Positive (Malaria)') {
            malariaOptions.style.display = 'block';
        } else {
            malariaOptions.style.display = 'none';
        }
    }
}

// Function to calculate deferral end date
function calculateDeferredUntil() {
    const months = document.getElementById('malaria-deferral-duration').value;
    const deferredUntil = document.getElementById('deferred_until');
    
    if (months && deferredUntil) {
        const today = new Date();
        const endDate = new Date(today.getFullYear(), today.getMonth() + parseInt(months), today.getDate());
        deferredUntil.value = endDate.toISOString().split('T')[0];
    }
}

// Function to update deferral reason
function updateDeferralReason() {
    const testResult = document.getElementById('test_result').value;
    const months = document.getElementById('malaria-deferral-duration').value;
    const deferralReason = document.getElementById('deferral_reason');
    
    if (testResult === 'Positive (Malaria)' && months) {
        deferralReason.value = 'Positive (Malaria) - Temporary deferral ' + months + ' months';
    } else if (testResult === 'Positive (HIV)') {
        deferralReason.value = 'Positive (HIV) - Permanent Deferral';
    } else if (testResult === 'Positive (Hepatitis B)') {
        deferralReason.value = 'Positive (Hepatitis B) - Permanent Deferral';
    } else if (testResult === 'Positive (Hepatitis C)') {
        deferralReason.value = 'Positive (Hepatitis C) - Permanent Deferral';
    } else if (testResult === 'Positive (Syphilis)') {
        deferralReason.value = 'Positive (Syphilis) - Permanent Deferral';
    } else if (testResult === 'Negative') {
        deferralReason.value = '';
    }
}
</script>

<!-- HTML FOR EDIT MODAL WITH CONDITIONAL FIELDS -->
<div class='form-group'>
    <label>Test Result *</label>
    <select id='test_result' name='test_result' onchange='toggleMalariaDeferralOptions(this.value); updateDeferralReason();' required>
        <option value=''>Select Test Result</option>
        <option value='Negative'>Negative (all clear)</option>
        <option value='Positive (Malaria)'>Positive (Malaria)</option>
        <option value='Positive (HIV)'>Positive (HIV)</option>
        <option value='Positive (Hepatitis B)'>Positive (Hepatitis B)</option>
        <option value='Positive (Hepatitis C)'>Positive (Hepatitis C)</option>
        <option value='Positive (Syphilis)'>Positive (Syphilis)</option>
        <option value='No test result yet'>No test result yet</option>
    </select>
</div>

<!-- Malaria Deferral Duration (conditional) -->
<div id='malaria-deferral-options' style='display: none;' class='form-group'>
    <label>Malaria Deferral Duration *</label>
    <select id='malaria-deferral-duration' name='malaria_deferral_duration' onchange='calculateDeferredUntil(); updateDeferralReason();'>
        <option value='6'>6 months</option>
        <option value='9'>9 months</option>
        <option value='12'>12 months</option>
    </select>
</div>

<div class='form-group'>
    <label>Deferral Until Date</label>
    <input type='date' id='deferred_until' name='deferred_until' readonly>
</div>

<div class='form-group'>
    <label>Deferral Reason</label>
    <textarea id='deferral_reason' name='deferral_reason' readonly></textarea>
</div>
";

// ========================================
// PHP API ENDPOINT FOR UPDATING DONOR
// ========================================

function updateDonorWithMalariaDeferral($pdo, $data) {
    $donorId = $data['id'];
    $testResult = $data['test_result'];
    $malariaMonths = $data['malaria_deferral_duration'] ?? 6;
    
    return updateDonorWorkflowStatus($pdo, $donorId, $testResult, $malariaMonths);
}

// Example usage:
/*
$data = [
    'id' => 123,
    'test_result' => 'Positive (Malaria)',
    'malaria_deferral_duration' => 9, // User selected 9 months
    'full_name' => 'John Doe',
    'email' => 'john@example.com',
    // ... other fields
];

$result = updateDonorWithMalariaDeferral($pdo, $data);
*/
?>

</script>
";
