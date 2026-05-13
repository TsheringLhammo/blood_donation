-- Two-stage donor workflow migration
-- Stage 1: initial approval / rejection
-- Stage 2: test result decision / final decision

ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS initial_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS approval_rejection_reason TEXT NULL,
    ADD COLUMN IF NOT EXISTS blood_drawn TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS test_result ENUM('positive','negative','inconclusive','not_tested') NOT NULL DEFAULT 'not_tested',
    ADD COLUMN IF NOT EXISTS final_decision ENUM('accepted','temp_defer','perm_defer','retest','pending') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS defer_until_date DATE NULL,
    ADD COLUMN IF NOT EXISTS donor_notified_stage1 TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS donor_notified_stage2 TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS workflow_status VARCHAR(80) NOT NULL DEFAULT 'pending_initial_approval';

ALTER TABLE tbldonor_samples
    ADD COLUMN IF NOT EXISTS decision_after_test ENUM('accept','defer','reject','retest','pending') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS decision_date DATETIME NULL,
    ADD COLUMN IF NOT EXISTS decision_notes VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS donor_notified ENUM('yes','no','pending') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS notification_sent_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS admin_finalized TINYINT(1) NOT NULL DEFAULT 0;

UPDATE tbldonors
SET email = REPLACE(TRIM(email), ' ', '')
WHERE email LIKE '% %';

UPDATE tbldonors
SET workflow_status = CASE
    WHEN LOWER(COALESCE(initial_approval_status, 'pending')) = 'approved' AND LOWER(COALESCE(final_decision, 'pending')) = 'accepted' THEN 'active_donor'
    WHEN LOWER(COALESCE(initial_approval_status, 'pending')) = 'approved' AND LOWER(COALESCE(final_decision, 'pending')) = 'temp_defer' THEN 'deferred_until_date'
    WHEN LOWER(COALESCE(initial_approval_status, 'pending')) = 'approved' AND LOWER(COALESCE(final_decision, 'pending')) = 'perm_defer' THEN 'permanently_deferred'
    WHEN LOWER(COALESCE(initial_approval_status, 'pending')) = 'approved' AND LOWER(COALESCE(final_decision, 'pending')) = 'retest' THEN 'retest_requested'
    WHEN LOWER(COALESCE(initial_approval_status, 'pending')) = 'approved' THEN 'blood_drawn_awaiting_test_result'
    WHEN LOWER(COALESCE(initial_approval_status, 'pending')) = 'rejected' THEN 'initially_rejected'
    ELSE 'pending_initial_approval'
END;
