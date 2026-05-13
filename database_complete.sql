-- Complete Blood Donation Management System Database Schema

-- Drop existing tables if they exist
DROP TABLE IF EXISTS tblnotifications;
DROP TABLE IF EXISTS donors;

-- Donors Table
CREATE TABLE donors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    blood_type VARCHAR(5),
    test_result VARCHAR(50),
    workflow_status ENUM('Awaiting Review', 'Approved', 'Temporarily Deferred', 'Permanently Deferred') DEFAULT 'Awaiting Review',
    deferral_end_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notifications Table
CREATE TABLE tblnotifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    admin_id INT DEFAULT 1,
    decision VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    deferral_months INT NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO donors (name, email, phone, blood_type, test_result, workflow_status) VALUES
('Tashi Wangmo', 'tashi@example.com', '17123456', 'A+', 'Negative', 'Awaiting Review'),
('Karma Dorji', 'karma@example.com', '17123457', 'B+', 'Positive (HIV)', 'Permanently Deferred'),
('Sonam Choden', 'sonam@example.com', '17123458', 'O+', 'Negative', 'Temporarily Deferred'),
('Dawa Penjor', 'dawa@example.com', '17123459', 'AB+', 'Negative', 'Approved'),
('yoyo', 'yoyo@example.com', '17123460', 'O+', 'Reactive (HIV)', 'Permanently Deferred');

-- Insert sample notification for permanently deferred donor
INSERT INTO tblnotifications (donor_id, admin_id, decision, message, deferral_months, is_read) VALUES
(2, 1, 'Permanent Defer', 'Based on your test results showing HIV positive, you are permanently deferred from donating blood for your own health and safety. Please consult a healthcare provider for further guidance.', NULL, 0),
(5, 1, 'Permanent Defer', 'Based on your test results showing HIV reactive, you are permanently deferred from donating blood for your own health and safety. Please consult a healthcare provider for further guidance.', NULL, 0);

-- Update deferral end date for temporarily deferred donor (6 months from now)
UPDATE donors SET deferral_end_date = DATE_ADD(CURDATE(), INTERVAL 6 MONTH) WHERE id = 3;

-- Insert notification for temporarily deferred donor
INSERT INTO tblnotifications (donor_id, admin_id, decision, message, deferral_months, is_read) VALUES
(3, 1, 'Temporary Defer', 'You are temporarily deferred from donating blood for 6 months due to recent medical treatment. You will be eligible to donate again after the deferral period ends.', 6, 0);
