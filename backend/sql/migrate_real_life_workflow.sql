-- Migration: Real-life blood workflow tables and status normalization
-- Target DB: blood_donation

USE blood_donation;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS tbldonations (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id             INT UNSIGNED NULL,
    donor_name           VARCHAR(160) NULL,
    blood_bank_id        INT UNSIGNED NOT NULL,
    blood_type           VARCHAR(5) NOT NULL,
    component            ENUM('Whole Blood','Packed Red Cells','Plasma','Platelets') NOT NULL DEFAULT 'Whole Blood',
    units_collected      SMALLINT UNSIGNED NOT NULL,
    donation_date        DATETIME NOT NULL,
    status               ENUM('Pending Collection','Collected','Testing Pending','Safe','Rejected','Stocked') NOT NULL DEFAULT 'Testing Pending',
    collected_by_user_id INT UNSIGNED NULL,
    tested_by_user_id    INT UNSIGNED NULL,
    notes                VARCHAR(255) NULL,
    stocked_at           DATETIME NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tbldonations_status (status),
    INDEX idx_tbldonations_bank_type (blood_bank_id, blood_type),
    INDEX idx_tbldonations_donor (donor_id)
);

CREATE TABLE IF NOT EXISTS tbldonation_tests (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id       INT UNSIGNED NOT NULL,
    hiv_result        ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    hbsag_result      ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    hcv_result        ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    syphilis_result   ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    malaria_result    ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    final_result      ENUM('Pending','Safe','Rejected') NOT NULL DEFAULT 'Pending',
    remarks           VARCHAR(255) NULL,
    tested_by_user_id INT UNSIGNED NULL,
    tested_at         DATETIME NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tbldonation_tests_donation (donation_id),
    INDEX idx_tbldonation_tests_final (final_result)
);

CREATE TABLE IF NOT EXISTS tblstock_ledger (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blood_bank_id  INT UNSIGNED NOT NULL,
    blood_type     VARCHAR(5) NOT NULL,
    component      ENUM('Whole Blood','Packed Red Cells','Plasma','Platelets') NOT NULL,
    movement_type  ENUM('IN','OUT','ADJUST') NOT NULL,
    units          SMALLINT UNSIGNED NOT NULL,
    reference_type ENUM('DONATION','ISSUE','MANUAL') NOT NULL,
    reference_id   INT UNSIGNED NULL,
    before_units   INT UNSIGNED NOT NULL,
    after_units    INT UNSIGNED NOT NULL,
    actor_user_id  INT UNSIGNED NULL,
    notes          VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblstock_ledger_bank_type (blood_bank_id, blood_type),
    INDEX idx_tblstock_ledger_reference (reference_type, reference_id),
    INDEX idx_tblstock_ledger_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS tblrequest_status_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id    INT UNSIGNED NOT NULL,
    from_status   VARCHAR(40) NULL,
    to_status     VARCHAR(40) NOT NULL,
    action        VARCHAR(50) NOT NULL,
    notes         VARCHAR(255) NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblrequest_status_logs_request (request_id),
    INDEX idx_tblrequest_status_logs_created (created_at)
);

-- Normalize legacy statuses to the new workflow naming.
UPDATE tblblood_requests SET status = 'Cross-Matching' WHERE status = 'Cross-match';
UPDATE tblblood_requests SET status = 'Matched' WHERE status = 'Cross-match Complete';
UPDATE tblblood_requests SET status = 'Rejected' WHERE status = 'Cross-match Failed';

COMMIT;
