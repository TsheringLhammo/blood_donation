CREATE DATABASE IF NOT EXISTS blood_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blood_donation;

DROP TABLE IF EXISTS tblnotifications;
DROP TABLE IF EXISTS tbldonors;

CREATE TABLE tbldonors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(50) NOT NULL,
  cid_number VARCHAR(11) UNIQUE DEFAULT NULL,
  blood_type VARCHAR(10) NOT NULL,
  test_result VARCHAR(30) NOT NULL DEFAULT 'pending',
  workflow_status VARCHAR(40) NOT NULL DEFAULT 'awaiting_review',
  deferral_reason VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tblnotifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  admin_id INT NOT NULL,
  decision VARCHAR(30) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_donor_read (donor_id, is_read),
  INDEX idx_notifications_created (created_at),
  CONSTRAINT fk_notifications_donor FOREIGN KEY (donor_id) REFERENCES tbldonors(id) ON DELETE CASCADE
);

INSERT INTO tbldonors (full_name, email, phone, blood_type, test_result, workflow_status, deferral_reason) VALUES
('Tshering Wangchuk', 'tshering@example.com', '175555111', 'A+', 'negative', 'approved', NULL),
('Henry Dorji', 'henry@example.com', '175555222', 'O+', 'negative', 'temporarily_deferred', 'Low hemoglobin. Recheck after 3 months.'),
('Nado Lhamo', 'nado@example.com', '175555333', 'B+', 'pending', 'awaiting_review', NULL),
('yoyo', 'yoyo@example.com', '175555444', 'AB+', 'reactive', 'permanently_deferred', 'Reactive screening result. Permanent deferral required.');
