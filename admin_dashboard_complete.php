<?php
// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle admin decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'handleDecision') {
    $donorId = $_POST['donor_id'] ?? 0;
    $decision = $_POST['decision'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $message = $_POST['message'] ?? '';
    $deferralMonths = $_POST['deferral_months'] ?? null;
    
    if ($donorId > 0 && in_array($decision, ['approve', 'temp_defer', 'perm_defer'])) {
        try {
            $pdo->beginTransaction();
            
            // Update donor status
            $statusMap = [
                'approve' => 'Approved',
                'temp_defer' => 'Temporarily Deferred',
                'perm_defer' => 'Permanently Deferred'
            ];
            
            $newStatus = $statusMap[$decision];
            $deferralEndDate = null;
            
            if ($decision === 'temp_defer' && $deferralMonths) {
                $deferralEndDate = date('Y-m-d', strtotime("+$deferralMonths months"));
            }
            
            $updateStmt = $pdo->prepare("UPDATE donors SET workflow_status = ?, deferral_end_date = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newStatus, $deferralEndDate, $donorId]);
            
            // Create notification
            $decisionText = [
                'approve' => 'Approved',
                'temp_defer' => 'Temporary Defer',
                'perm_defer' => 'Permanent Defer'
            ];
            
            $notifStmt = $pdo->prepare("INSERT INTO tblnotifications (donor_id, admin_id, decision, message, deferral_months, is_read) VALUES (?, 1, ?, ?, ?, 0)");
            $notifStmt->execute([$donorId, $decisionText[$decision], $message, $deferralMonths]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Decision processed successfully']);
            exit;
        } catch (Exception $e) {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get all donors
$donors = [];
try {
    $stmt = $pdo->query("SELECT * FROM donors ORDER BY created_at DESC");
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "Error fetching donors: " . $e->getMessage();
}

// Get recent notifications
$notifications = [];
try {
    $stmt = $pdo->query("SELECT n.*, d.name as donor_name FROM tblnotifications n JOIN donors d ON n.donor_id = d.id ORDER BY n.created_at DESC LIMIT 10");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "Error fetching notifications: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Blood Donation Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 10px 0 0 0; opacity: 0.9; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 36px; font-weight: bold; color: #667eea; margin-bottom: 10px; }
        .stat-label { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .section { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section h2 { margin: 0; color: #333; font-size: 20px; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; font-size: 14px; }
        tr:hover { background: #f8f9fa; }
        .status-badge { padding: 4px 8px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-awaiting { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-temp-deferred { background: #fff3cd; color: #856404; }
        .status-perm-deferred { background: #f8d7da; color: #721c24; }
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; margin: 2px; transition: all 0.3s; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn-approve { background: #28a745; color: white; }
        .btn-temp-defer { background: #ffc107; color: #212529; }
        .btn-perm-defer { background: #dc3545; color: white; }
        .btn-details { background: #6c757d; color: white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 100px auto; padding: 30px; border-radius: 10px; width: 500px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { margin: 0; font-size: 18px; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
        .notification-item { padding: 15px; border-left: 4px solid #667eea; margin-bottom: 10px; background: #f8f9fa; border-radius: 5px; }
        .notification-title { font-weight: 600; margin-bottom: 5px; }
        .notification-time { font-size: 12px; color: #666; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .deferral-info { background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🩸 Admin Dashboard</h1>
            <p>Blood Donation Management System - Control Panel</p>
        </div>

        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($donors); ?></div>
                <div class="stat-label">Total Donors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($donors, fn($d) => $d['workflow_status'] === 'Awaiting Review')); ?></div>
                <div class="stat-label">Awaiting Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($donors, fn($d) => $d['workflow_status'] === 'Approved')); ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($donors, fn($d) => $d['workflow_status'] === 'Temporarily Deferred')); ?></div>
                <div class="stat-label">Temporarily Deferred</div>
            </div>
        </div>

        <!-- Donors Section -->
        <div class="section">
            <div class="section-header">
                <h2>🩸 Donors Management</h2>
                <span>(<?php echo count($donors); ?> donors)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>PHONE</th>
                            <th>BLOOD TYPE</th>
                            <th>TEST RESULT</th>
                            <th>WORKFLOW STATUS</th>
                            <th>ADMIN DECISION</th>
                            <th>DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $donor): ?>
                        <tr>
                            <td><?php echo $donor['id']; ?></td>
                            <td><?php echo htmlspecialchars($donor['name']); ?></td>
                            <td><?php echo htmlspecialchars($donor['email']); ?></td>
                            <td><?php echo htmlspecialchars($donor['phone'] ?? '-'); ?></td>
                            <td><span class="status-badge"><?php echo htmlspecialchars($donor['blood_type'] ?? '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($donor['test_result'] ?? '-'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($donor['workflow_status'])); ?>">
                                    <?php echo htmlspecialchars($donor['workflow_status']); ?>
                                </span>
                                <?php if ($donor['deferral_end_date']): ?>
                                <br><small style="color: #666;">Until <?php echo date('M d, Y', strtotime($donor['deferral_end_date'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($donor['workflow_status'] === 'Awaiting Review'): ?>
                                    <button class="btn btn-approve" onclick="openDecisionModal(<?php echo $donor['id']; ?>, 'approve', '<?php echo htmlspecialchars($donor['name']); ?>')">✅ Approve</button>
                                    <button class="btn btn-temp-defer" onclick="openDecisionModal(<?php echo $donor['id']; ?>, 'temp_defer', '<?php echo htmlspecialchars($donor['name']); ?>')">⏸️ Temp Defer</button>
                                    <button class="btn btn-perm-defer" onclick="openDecisionModal(<?php echo $donor['id']; ?>, 'perm_defer', '<?php echo htmlspecialchars($donor['name']); ?>')">❌ Permanent Defer</button>
                                <?php else: ?>
                                    <span class="status-badge">Decision Made</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-details" onclick="viewDetails(<?php echo $donor['id']; ?>)">View</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notifications Section -->
        <div class="section">
            <div class="section-header">
                <h2>🔔 Recent Notifications</h2>
                <button class="btn btn-details" onclick="location.reload()">Refresh</button>
            </div>
            <div id="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item">
                    <div class="notification-title"><?php echo htmlspecialchars($notification['decision']); ?> - <?php echo htmlspecialchars($notification['donor_name']); ?></div>
                    <div><?php echo htmlspecialchars($notification['message']); ?></div>
                    <div class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Decision Modal -->
    <div id="decision-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">Admin Decision</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="decision-form">
                <input type="hidden" id="donor-id" name="donor_id">
                <input type="hidden" id="decision-type" name="decision">
                
                <div id="deferral-options" style="display: none;" class="deferral-info">
                    <label>Select deferral period:</label>
                    <select id="deferral-months" name="deferral_months">
                        <option value="6">6 months</option>
                        <option value="12">12 months</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reason for decision</label>
                    <textarea id="decision-reason" name="reason" placeholder="Enter reason for this decision" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Additional message to donor</label>
                    <textarea id="message-to-donor" name="message" placeholder="Enter additional message for the donor..." required></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-details" onclick="closeModal()">Cancel</button>
                    <button type="button" class="btn btn-approve" id="confirm-decision">Confirm Decision</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentDonorId = null;
        let currentDecision = null;
        let currentDonorName = null;

        function openDecisionModal(donorId, decision, donorName) {
            currentDonorId = donorId;
            currentDecision = decision;
            currentDonorName = donorName;
            
            document.getElementById('donor-id').value = donorId;
            document.getElementById('decision-type').value = decision;
            
            const deferralOptions = document.getElementById('deferral-options');
            const modalTitle = document.getElementById('modal-title');
            const reasonInput = document.getElementById('decision-reason');
            const messageInput = document.getElementById('message-to-donor');
            
            // Reset form
            reasonInput.value = '';
            messageInput.value = '';
            deferralOptions.style.display = 'none';
            
            if (decision === 'approve') {
                modalTitle.textContent = '✅ Approve Donor';
                reasonInput.value = 'Donor meets all eligibility requirements';
                messageInput.value = 'Congratulations! You have been approved to donate blood. Your donation can help save lives.';
            } else if (decision === 'temp_defer') {
                modalTitle.textContent = '⏸️ Temporary Deferral';
                deferralOptions.style.display = 'block';
                reasonInput.value = 'Temporary deferral required';
                messageInput.value = 'You are temporarily deferred from donating blood. Please follow the provided instructions and contact us after the deferral period.';
            } else if (decision === 'perm_defer') {
                modalTitle.textContent = '❌ Permanent Deferral';
                reasonInput.value = 'Permanent deferral based on medical conditions';
                messageInput.value = 'Based on your medical test results, you are permanently deferred from donating blood for your own health and safety. Please consult a healthcare provider for further guidance.';
            }
            
            document.getElementById('decision-modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('decision-modal').style.display = 'none';
            document.getElementById('decision-form').reset();
        }

        function confirmDecision() {
            const form = document.getElementById('decision-form');
            const formData = new FormData(form);
            formData.append('action', 'handleDecision');
            
            fetch('admin_dashboard_complete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Decision processed successfully! Donor has been notified.');
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing decision');
            });
        }

        function viewDetails(donorId) {
            alert('Donor details view - Feature coming soon for donor ID: ' + donorId);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('decision-modal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Handle form submission
        document.getElementById('confirm-decision').onclick = confirmDecision;
    </script>
</body>
</html>
