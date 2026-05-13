-- Migration: Add Deferral System Support
-- Purpose: Enhance donor status tracking with specific deferral reasons and dates

-- Add deferral columns to tbldonors if they don't exist
ALTER TABLE tbldonors 
ADD COLUMN IF NOT EXISTS deferred_until DATE NULL COMMENT 'Date when donor can re-apply after deferral',
ADD COLUMN IF NOT EXISTS deferral_reason VARCHAR(500) COMMENT 'Reason for deferral (e.g., Positive HIV)';

-- Create or verify notifications table structure
CREATE TABLE IF NOT EXISTS tblnotifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL COMMENT 'Recipient user ID (NULL for broadcast notifications)',
  role_target VARCHAR(50) NULL COMMENT 'Target role (admin, doctor, staff, donor)',
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) DEFAULT 'generic' COMMENT 'Type: approval, rejection, deferral, alert, etc.',
  severity VARCHAR(20) DEFAULT 'info' COMMENT 'info, warning, critical',
  channel VARCHAR(50) DEFAULT 'in_app' COMMENT 'in_app, email, both',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_read (user_id, is_read),
  INDEX idx_role_created (role_target, created_at),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure donor status values are standardized
-- Note: Status should be: Pending, Confirmed/Active, Deferred, Rejected
-- If your table uses VARCHAR, no changes needed. If it's ENUM, run:
-- ALTER TABLE tbldonors MODIFY status ENUM('Pending', 'Confirmed', 'Active', 'Deferred', 'Rejected') DEFAULT 'Pending';

-- Add index for deferral lookups
CREATE INDEX IF NOT EXISTS idx_deferred_until ON tbldonors(deferred_until);

-- Add counselling table for tracking donor notifications post-test
CREATE TABLE IF NOT EXISTS tbldonor_counselling (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id INT UNSIGNED NOT NULL,
  donation_id VARCHAR(100) NULL,
  notes TEXT,
  counselling_status ENUM('Pending','Contacted','Completed') NOT NULL DEFAULT 'Pending',
  contacted_at TIMESTAMP NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (donor_id) REFERENCES tbldonors(id),
  INDEX idx_donor (donor_id),
  INDEX idx_status (counselling_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
