-- Run this SQL in phpMyAdmin or from mysql client to create demo DB and sample data
CREATE DATABASE IF NOT EXISTS blood_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blood_donation;

-- donors table
CREATE TABLE IF NOT EXISTS tbldonors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NULL,
  phone VARCHAR(50) NULL,
  cid_number VARCHAR(11) UNIQUE DEFAULT NULL,
  blood_type VARCHAR(10) NULL,
  test_result VARCHAR(50) DEFAULT 'not_tested',
  workflow_status VARCHAR(60) DEFAULT 'awaiting_review',
  deferral_reason VARCHAR(255) NULL,
  deferred_until DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- notifications table
CREATE TABLE IF NOT EXISTS tblnotifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  admin_id INT NULL,
  decision VARCHAR(30) NOT NULL,
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_donor (donor_id),
  FOREIGN KEY (donor_id) REFERENCES tbldonors(id) ON DELETE CASCADE
);

-- sample data
INSERT INTO tbldonors (full_name, email, phone, blood_type, test_result, workflow_status) VALUES
('Tshering Wangchuk', 'tshering@example.local', '176555111', 'A+', 'negative', 'approved'),
('Henry', 'henry@example.local', '176555222', 'O+', 'positive', 'temporarily_deferred'),
('Nado', 'nado@example.local', '176555333', 'B+', 'negative', 'awaiting_review'),
('yoyo', 'yoyo@example.local', '176555444', 'AB+', 'positive', 'permanently_deferred');

-- ensure yoyo is permanently deferred with reason
UPDATE tbldonors SET deferral_reason = 'Reactive test - HCV', deferred_until = NULL WHERE full_name = 'yoyo';
