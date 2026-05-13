-- ============================================================================
-- BLOOD BANK MANAGEMENT SYSTEM - DEFERRAL SYSTEM SCHEMA
-- Purpose: Complete database schema for donor deferral and status management
-- Date: April 2026
-- ============================================================================

-- ============================================================================
-- 1. DONORS TABLE - Enhanced with deferral fields
-- ============================================================================

ALTER TABLE tbldonors 
ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Pending' COMMENT 'Status: Pending, Confirmed, Deferred, Rejected',
ADD COLUMN IF NOT EXISTS deferred TINYINT(1) DEFAULT 0 COMMENT 'Legacy flag for deferral (use status field)',
ADD COLUMN IF NOT EXISTS deferred_until DATE NULL COMMENT 'Date when donor can reapply after deferral',
ADD COLUMN IF NOT EXISTS deferral_reason VARCHAR(500) NULL COMMENT 'Specific reason for deferral (e.g., Positive HIV result)',
ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL COMMENT 'Reason for rejection (if rejected)',
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last status update timestamp';

-- Create indexes for efficient lookups
CREATE INDEX IF NOT EXISTS idx_donor_status ON tbldonors(status);
CREATE INDEX IF NOT EXISTS idx_donor_deferred_until ON tbldonors(deferred_until);
CREATE INDEX IF NOT EXISTS idx_donor_created ON tbldonors(created_at);

-- ============================================================================
-- 2. APPOINTMENTS TABLE - With deferral validation
-- ============================================================================

CREATE TABLE IF NOT EXISTS tblappointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id INT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL COMMENT 'Date of scheduled appointment',
  appointment_time TIME NOT NULL COMMENT 'Time of scheduled appointment',
  appointment_datetime DATETIME GENERATED ALWAYS AS (CONCAT(appointment_date, ' ', appointment_time)) STORED,
  blood_bank_id INT UNSIGNED NOT NULL COMMENT 'Which blood bank',
  status ENUM('Scheduled', 'Completed', 'Cancelled', 'No-show') DEFAULT 'Scheduled',
  notes TEXT COMMENT 'Additional notes about appointment',
  cancelled_at TIMESTAMP NULL,
  cancelled_by VARCHAR(255) COMMENT 'System or user who cancelled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (donor_id) REFERENCES tbldonors(id) ON DELETE CASCADE,
  FOREIGN KEY (blood_bank_id) REFERENCES tblblood_banks(id),
  
  INDEX idx_donor_date (donor_id, appointment_date),
  INDEX idx_appointment_date (appointment_date),
  INDEX idx_status (status),
  UNIQUE KEY unique_donor_appointment (donor_id, appointment_date, appointment_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. BLOOD TEST RESULTS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS tblblood_tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id VARCHAR(100) NOT NULL,
  donor_id INT UNSIGNED NOT NULL,
  
  -- Test results (Positive/Negative/Reactive/Non-reactive/etc)
  hiv_result ENUM('Negative', 'Positive', 'Reactive', 'Non-reactive', 'Inconclusive', 'Not Tested') DEFAULT 'Not Tested',
  hbsag_result ENUM('Negative', 'Positive', 'Reactive', 'Non-reactive', 'Inconclusive', 'Not Tested') DEFAULT 'Not Tested',
  hcv_result ENUM('Negative', 'Positive', 'Reactive', 'Non-reactive', 'Inconclusive', 'Not Tested') DEFAULT 'Not Tested',
  syphilis_result ENUM('Negative', 'Positive', 'Reactive', 'Non-reactive', 'Inconclusive', 'Not Tested') DEFAULT 'Not Tested',
  malaria_result ENUM('Negative', 'Positive', 'Reactive', 'Non-reactive', 'Inconclusive', 'Not Tested') DEFAULT 'Not Tested',
  
  -- Overall result
  final_result ENUM('Eligible', 'Discard', 'Pending', 'Inconclusive') DEFAULT 'Pending',
  
  -- If deferred, what triggered it
  deferral_trigger VARCHAR(100) COMMENT 'Which test caused deferral: HIV, HBsAg, HCV, Syphilis, Malaria',
  
  -- Testing metadata
  tested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  tested_by INT UNSIGNED COMMENT 'User ID of lab technician',
  comments TEXT COMMENT 'Lab technician notes',
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (donor_id) REFERENCES tbldonors(id),
  INDEX idx_donor (donor_id),
  INDEX idx_donation (donation_id),
  INDEX idx_final_result (final_result),
  INDEX idx_tested_at (tested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. NOTIFICATIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS tblnotifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL COMMENT 'Recipient user ID (NULL for broadcast)',
  donor_id INT UNSIGNED NULL COMMENT 'If related to a donor',
  role_target VARCHAR(50) NULL COMMENT 'Target role: admin, doctor, staff, donor',
  
  -- Notification content
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('approval', 'rejection', 'deferral', 'expiry', 'appointment', 'alert', 'generic') DEFAULT 'generic',
  severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
  
  -- Delivery
  channel ENUM('in_app', 'email', 'sms', 'both') DEFAULT 'in_app',
  
  -- Status tracking
  is_read TINYINT(1) DEFAULT 0,
  read_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_user_read (user_id, is_read),
  INDEX idx_role_created (role_target, created_at),
  INDEX idx_donor (donor_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. DEFERRAL AUDIT TRAIL
-- ============================================================================

CREATE TABLE IF NOT EXISTS tbldeferral_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id INT UNSIGNED NOT NULL,
  
  -- Deferral details
  deferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deferred_until DATE NOT NULL,
  deferral_reason VARCHAR(500),
  deferral_trigger_type ENUM('Positive Test', 'Admin Action', 'Medical Review', 'Other') DEFAULT 'Positive Test',
  
  -- Related test or action
  blood_test_id INT UNSIGNED NULL,
  triggered_by_user_id INT UNSIGNED COMMENT 'Admin or staff who recorded test',
  
  -- Expiry handling
  expired_at TIMESTAMP NULL COMMENT 'When deferral expired',
  expired_action ENUM('Auto-Confirm', 'Awaiting Admin', 'Manual Override') DEFAULT 'Awaiting Admin',
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (donor_id) REFERENCES tbldonors(id),
  FOREIGN KEY (blood_test_id) REFERENCES tblblood_tests(id),
  INDEX idx_donor (donor_id),
  INDEX idx_deferred_until (deferred_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. STORED PROCEDURES
-- ============================================================================

-- Procedure: Check if donor is eligible for appointment (not deferred)
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_is_donor_eligible_for_appointment$$
CREATE PROCEDURE sp_is_donor_eligible_for_appointment(
  IN p_donor_id INT,
  OUT p_is_eligible TINYINT,
  OUT p_status VARCHAR(50),
  OUT p_deferral_reason VARCHAR(500),
  OUT p_deferred_until DATE
)
BEGIN
  SELECT 
    CASE 
      WHEN LOWER(COALESCE(status, 'pending')) NOT IN ('confirmed', 'active') THEN 0
      WHEN LOWER(COALESCE(status, 'pending')) = 'deferred' AND deferred_until > CURDATE() THEN 0
      ELSE 1
    END as eligible,
    status,
    deferral_reason,
    deferred_until
  INTO p_is_eligible, p_status, p_deferral_reason, p_deferred_until
  FROM tbldonors
  WHERE id = p_donor_id;
  
  IF p_is_eligible IS NULL THEN
    SET p_is_eligible = 0;
  END IF;
END$$

-- Procedure: Defer a donor after positive test
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_defer_donor$$
CREATE PROCEDURE sp_defer_donor(
  IN p_donor_id INT,
  IN p_deferral_reason VARCHAR(500),
  IN p_test_trigger VARCHAR(100),
  IN p_triggered_by_user_id INT
)
BEGIN
  DECLARE v_deferred_until DATE;
  SET v_deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH);
  
  -- Update donor status
  UPDATE tbldonors
  SET status = 'Deferred',
      deferred = 1,
      deferral_reason = p_deferral_reason,
      deferred_until = v_deferred_until,
      updated_at = CURRENT_TIMESTAMP
  WHERE id = p_donor_id;
  
  -- Log in audit trail
  INSERT INTO tbldeferral_history 
    (donor_id, deferred_until, deferral_reason, deferral_trigger, triggered_by_user_id)
  VALUES 
    (p_donor_id, v_deferred_until, p_deferral_reason, p_test_trigger, p_triggered_by_user_id);
  
  -- Create notification for donor
  INSERT INTO tblnotifications
    (donor_id, user_id, title, message, type, severity, channel)
  SELECT
    p_donor_id,
    u.id,
    '⏸️ Temporary Deferral Notice',
    CONCAT(
      'Your recent blood test showed: ', p_deferral_reason, '. ',
      'You are temporarily deferred for 6 months as a safety measure. ',
      'You can reapply on ', DATE_FORMAT(v_deferred_until, '%B %d, %Y'), '. ',
      'Please contact the blood bank for more information.'
    ),
    'deferral',
    'warning',
    'both'
  FROM tblusers u
  WHERE u.id IN (SELECT id FROM tblusers WHERE id = p_donor_id);
END$$

-- Procedure: Expire deferral and restore eligibility
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_expire_deferral$$
CREATE PROCEDURE sp_expire_deferral(
  IN p_donor_id INT
)
BEGIN
  DECLARE v_old_deferred_until DATE;
  
  -- Get the deferral expiry date
  SELECT deferred_until INTO v_old_deferred_until
  FROM tbldonors
  WHERE id = p_donor_id AND status = 'Deferred';
  
  IF v_old_deferred_until IS NOT NULL AND v_old_deferred_until <= CURDATE() THEN
    -- Update donor status back to Confirmed
    UPDATE tbldonors
    SET status = 'Confirmed',
        deferred = 0,
        deferral_reason = NULL,
        deferred_until = NULL,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_donor_id;
    
    -- Log expiry in history
    UPDATE tbldeferral_history
    SET expired_at = CURRENT_TIMESTAMP,
        expired_action = 'Auto-Confirm'
    WHERE donor_id = p_donor_id
      AND deferred_until = v_old_deferred_until
      AND expired_at IS NULL;
    
    -- Notify donor
    INSERT INTO tblnotifications
      (donor_id, title, message, type, severity, channel)
    VALUES
      (p_donor_id,
       '✅ Deferral Period Expired',
       'Your deferral period has ended. You are now eligible to donate again. Thank you for your patience!',
       'expiry',
       'info',
       'both');
  END IF;
END$$

DELIMITER ;

-- ============================================================================
-- 7. SCHEDULED JOBS - To run deferral expiry checks
-- ============================================================================

-- This event runs daily to check for expired deferrals and auto-restore eligibility
-- (Note: MySQL event scheduler must be enabled: SET GLOBAL event_scheduler = ON;)

DROP EVENT IF EXISTS evt_check_deferral_expiry;

CREATE EVENT evt_check_deferral_expiry
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  UPDATE tbldonors
  SET status = 'Confirmed',
      deferred = 0,
      deferral_reason = NULL,
      deferred_until = NULL,
      updated_at = CURRENT_TIMESTAMP
  WHERE status = 'Deferred'
    AND deferred_until <= CURDATE();

-- ============================================================================
-- 8. SAMPLE DATA (Optional - for testing)
-- ============================================================================

-- Insert sample donors with different statuses
INSERT INTO tbldonors (full_name, email, phone, blood_type, status, created_at) VALUES
  ('John Doe', 'john@example.com', '17112345', 'O+', 'Pending', CURRENT_TIMESTAMP),
  ('Jane Smith', 'jane@example.com', '17112346', 'A+', 'Confirmed', CURRENT_TIMESTAMP),
  ('Bob Wilson', 'bob@example.com', '17112347', 'B+', 'Deferred', CURRENT_TIMESTAMP)
  ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- 9. VERIFICATION QUERIES
-- ============================================================================

-- Verify schema
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tbldonors';

-- View all deferred donors
-- SELECT id, full_name, status, deferral_reason, deferred_until FROM tbldonors WHERE status = 'Deferred';

-- View deferral history
-- SELECT * FROM tbldeferral_history ORDER BY deferred_at DESC;
