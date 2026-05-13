-- Blood Transfusion Service - Database Schema
-- Database: blood_donation
-- Run this file once to initialise a fresh installation.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS blood_donation
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE blood_donation;

-- ─── 1. Donors ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tbldonors (
    id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(120)     NOT NULL,
    email         VARCHAR(160)     NOT NULL UNIQUE,
    phone         VARCHAR(30)      NOT NULL,
    date_of_birth DATE             NOT NULL,
    gender        ENUM('Male','Female','Other') NOT NULL,
    blood_type    VARCHAR(5)       NOT NULL,
    address       VARCHAR(255)     NOT NULL,
    city          VARCHAR(120)     NOT NULL,
    dzongkhag     VARCHAR(120)     NOT NULL,
    weight        DECIMAL(5,2)     NOT NULL DEFAULT 45.00,
    last_donation_date DATE        NULL,
    health_tattoo TINYINT(1)       NOT NULL DEFAULT 0,
    health_antibiotics TINYINT(1)  NOT NULL DEFAULT 0,
    health_surgery TINYINT(1)      NOT NULL DEFAULT 0,
    health_no_cold_flu TINYINT(1)  NOT NULL DEFAULT 0,
    consent_medical TINYINT(1)     NOT NULL DEFAULT 0,
    emergency_contact_name  VARCHAR(120) NOT NULL DEFAULT '',
    emergency_contact_phone VARCHAR(30)  NOT NULL DEFAULT '',
    deferred      TINYINT(1)       NOT NULL DEFAULT 0,
    deferral_reason VARCHAR(255)   NULL,
    status        VARCHAR(20)      NOT NULL DEFAULT 'Pending',
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── 2. Appointments ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblappointments (
    id             INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED     NULL,
    full_name      VARCHAR(120)     NOT NULL,
    age            TINYINT UNSIGNED NULL,
    gender         ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other',
    blood_group    VARCHAR(5)       NULL,
    phone_number   VARCHAR(30)      NULL,
    preferred_date DATE             NOT NULL,
    preferred_time VARCHAR(20)      NULL,
    blood_bank     VARCHAR(255)     NOT NULL,
    status         ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblappointments_user_id (user_id)
);

-- ─── 3. Blood Donation Camps ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblblood_camps (
    id                   INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    organization_name    VARCHAR(255)      NOT NULL,
    contact_person       VARCHAR(120)      NOT NULL,
    phone_number         VARCHAR(20)       NOT NULL,
    email                VARCHAR(160)      NULL,
    dzongkhag            VARCHAR(120)      NOT NULL,
    camp_type            VARCHAR(120)      NOT NULL,
    venue_address        VARCHAR(255)      NOT NULL,
    preferred_date       DATE              NOT NULL,
    alternate_date       DATE              NULL,
    expected_donors      SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    facilities_available TEXT              NULL,
    additional_info      TEXT              NULL,
    status               ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
    created_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── 4. Users (authentication) ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblusers (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)  NOT NULL,
    email      VARCHAR(160)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,   -- bcrypt hash (PASSWORD_BCRYPT)
    role       ENUM('admin','doctor','staff','donor') NOT NULL DEFAULT 'donor',
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── 5. Blood Request Workflow ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblblood_requests (
    weight        DECIMAL(5,2)     NOT NULL,
    last_donation_date DATE         NULL,
    health_declaration JSON         NULL,
    consent       TINYINT(1)       NOT NULL DEFAULT 0,
    emergency_contact_name  VARCHAR(120) NOT NULL,
    emergency_contact_phone VARCHAR(30)   NOT NULL,
    deferred      TINYINT(1)       NOT NULL DEFAULT 0,
    deferral_reason VARCHAR(255)   NULL,
    status        VARCHAR(20)      NOT NULL DEFAULT 'Pending',
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code           VARCHAR(32)  NOT NULL UNIQUE,
    doctor_user_id         INT UNSIGNED NULL,
    doctor_name            VARCHAR(160) NOT NULL,
    hospital_name          VARCHAR(255) NOT NULL,
    patient_name           VARCHAR(160) NOT NULL,
    patient_dob            DATE         NULL,
    patient_age            TINYINT UNSIGNED NULL,
    patient_gender         ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other',
    patient_address        VARCHAR(255) NULL,
    patient_ref_no         VARCHAR(80)  NULL,
    ward                   VARCHAR(120) NULL,
    blood_type             VARCHAR(5)   NULL,
    component              VARCHAR(80)  NOT NULL,
    units_requested        SMALLINT UNSIGNED NOT NULL,
    urgency                ENUM('Routine','Urgent','Critical') NOT NULL DEFAULT 'Routine',
    diagnosis              VARCHAR(255) NULL,
    reason_for_transfusion VARCHAR(255) NULL,
    date_time_required     DATETIME     NOT NULL,
    status                 VARCHAR(40)  NOT NULL DEFAULT 'Pending',
    created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tblblood_requests_doctor_user_id (doctor_user_id),
    INDEX idx_tblblood_requests_status (status),
    UNIQUE KEY uk_tblblood_requests_duplicate(patient_name, hospital_name, component, units_requested, date_time_required, doctor_user_id)
);

-- ─── 6. Blood Inventory ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblinventory (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blood_bank_id   INT UNSIGNED NOT NULL,
    blood_type      VARCHAR(5) NOT NULL UNIQUE,
    whole_units     INT UNSIGNED NOT NULL DEFAULT 0,
    prbc_units      INT UNSIGNED NOT NULL DEFAULT 0,
    platelets_units INT UNSIGNED NOT NULL DEFAULT 0,
    ffp_units       INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─── 7. Lab Logs ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tbllab_logs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_name        VARCHAR(120) NOT NULL,
    sample_reference VARCHAR(80)  NOT NULL,
    request_id       INT UNSIGNED NULL,
    patient_name     VARCHAR(160) NULL,
    blood_type       VARCHAR(5)   NULL,
    component        VARCHAR(80)  NULL,
    units_requested  SMALLINT UNSIGNED NULL,
    donor_unit_refs  VARCHAR(255) NULL,
    test_parameters  TEXT         NULL,
    notes            VARCHAR(255) NULL,
    result           VARCHAR(120) NOT NULL,
    technician_name  VARCHAR(120) NOT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tbllab_logs_request_id (request_id),
    INDEX idx_tbllab_logs_result (result),
    INDEX idx_tbllab_logs_created_at (created_at)
);

-- ─── 8. Blood Banks ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblblood_banks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(180) NOT NULL,
    hospital   VARCHAR(255) NOT NULL,
    dzongkhag  VARCHAR(120) NOT NULL,
    address    VARCHAR(255) NOT NULL,
    phone      VARCHAR(30)  NOT NULL,
    email      VARCHAR(160) NULL,
    hours      VARCHAR(120) NOT NULL,
    emergency  VARCHAR(120) NOT NULL,
    status     ENUM('open','limited') NOT NULL DEFAULT 'open',
    types_csv  VARCHAR(255) NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── 9. Blood Unit Inventory (individual bags) ───────────────────────────────
CREATE TABLE IF NOT EXISTS tblblood_units (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id        VARCHAR(20) NOT NULL,
    donation_id    VARCHAR(60) NULL,
    blood_bank_id  INT UNSIGNED NOT NULL DEFAULT 1,
    blood_type     VARCHAR(5) NOT NULL,
    component      VARCHAR(30) NOT NULL,
    expiry_date    DATE NOT NULL,
    status         ENUM('Available','Reserved','Issued','Expired','Rejected','Quarantined','Discarded') NOT NULL DEFAULT 'Available',
    request_id     INT UNSIGNED NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tblblood_units_unit_id (unit_id),
    KEY idx_tblblood_units_bank_type_component (blood_bank_id, blood_type, component),
    KEY idx_tblblood_units_status (status),
    KEY idx_tblblood_units_request_id (request_id),
    CONSTRAINT fk_tblblood_units_blood_bank FOREIGN KEY (blood_bank_id) REFERENCES tblblood_banks(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tblblood_units_request FOREIGN KEY (request_id) REFERENCES tblblood_requests(id) ON UPDATE CASCADE ON DELETE SET NULL
);

ALTER TABLE tblinventory
    DROP INDEX blood_type,
    ADD CONSTRAINT uq_tblinventory_bank_blood UNIQUE (blood_bank_id, blood_type),
    ADD CONSTRAINT fk_tblinventory_blood_bank FOREIGN KEY (blood_bank_id) REFERENCES tblblood_banks(id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- ─── 9. Issue Logs (audit trail) ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblissue_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      INT UNSIGNED NOT NULL,
    request_code    VARCHAR(32)  NOT NULL,
    patient_name    VARCHAR(160) NOT NULL,
    blood_type      VARCHAR(5)   NULL,
    component       VARCHAR(80)  NOT NULL,
    units_issued    SMALLINT UNSIGNED NOT NULL,
    issued_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    staff_user_id   INT UNSIGNED NULL,
    staff_name      VARCHAR(160) NOT NULL,
    notes           VARCHAR(255) NULL,
    INDEX idx_tblissue_logs_request_id (request_id),
    INDEX idx_tblissue_logs_issued_at (issued_at)
);

-- ─── 10. Notifications ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tblnotifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id      INT UNSIGNED NULL,
    admin_id      INT UNSIGNED NULL,
    user_id       INT UNSIGNED NULL,
    role_target   ENUM('admin','doctor','staff','donor') NULL,
    type          VARCHAR(30) NOT NULL DEFAULT 'info',
    request_id    INT UNSIGNED NULL,
    title         VARCHAR(160) NOT NULL,
    message       VARCHAR(255) NOT NULL,
    action_url    VARCHAR(255) NULL,
    action_type   VARCHAR(80) NULL,
    severity      ENUM('info','success','warning','critical') NOT NULL DEFAULT 'info',
    channel       ENUM('in_app','email','sms') NOT NULL DEFAULT 'in_app',
    is_read       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblnotifications_donor_id (donor_id),
    INDEX idx_tblnotifications_admin_id (admin_id),
    INDEX idx_tblnotifications_user_id (user_id),
    INDEX idx_tblnotifications_role_target (role_target),
    INDEX idx_tblnotifications_type (type),
    INDEX idx_tblnotifications_is_read (is_read),
    INDEX idx_tblnotifications_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS tbldonor_counselling (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id           INT UNSIGNED NULL,
    donation_id        INT UNSIGNED NULL,
    counselling_status ENUM('Pending','Contacted','Completed') NOT NULL DEFAULT 'Pending',
    notes              VARCHAR(255) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tbldonor_counselling_donor (donor_id),
    INDEX idx_tbldonor_counselling_donation (donation_id),
    INDEX idx_tbldonor_counselling_status (counselling_status)
);

-- ─── 11. Donation Workflow ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tbldonations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id            INT UNSIGNED NULL,
    donor_name          VARCHAR(160) NULL,
    blood_bank_id       INT UNSIGNED NOT NULL,
    blood_type          VARCHAR(5) NOT NULL,
    component           ENUM('Whole Blood','Packed Red Cells','Plasma','Platelets') NOT NULL DEFAULT 'Whole Blood',
    units_collected     SMALLINT UNSIGNED NOT NULL,
    donation_date       DATETIME NOT NULL,
    status              ENUM('Pending Collection','Collected','Testing Pending','Safe','Rejected','Stocked') NOT NULL DEFAULT 'Testing Pending',
    collected_by_user_id INT UNSIGNED NULL,
    tested_by_user_id   INT UNSIGNED NULL,
    notes               VARCHAR(255) NULL,
    stocked_at          DATETIME NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tbldonations_status (status),
    INDEX idx_tbldonations_bank_type (blood_bank_id, blood_type),
    INDEX idx_tbldonations_donor (donor_id)
);

CREATE TABLE IF NOT EXISTS tbldonation_tests (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id      INT UNSIGNED NOT NULL,
    hiv_result       ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    hbsag_result     ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    hcv_result       ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    syphilis_result  ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    malaria_result   ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
    final_result     ENUM('Pending','Eligible','Discard','Safe','Rejected') NOT NULL DEFAULT 'Pending',
    remarks          VARCHAR(255) NULL,
    tested_by_user_id INT UNSIGNED NULL,
    tested_at        DATETIME NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

-- ─── Demo seed accounts ───────────────────────────────────────────────────────
-- Passwords (plain-text for reference only): donor123 | admin123 | doctor123 | staff123
INSERT IGNORE INTO tblusers (name, email, password, role) VALUES
  ('Demo Donor',  'donor@gmail.com', '$2y$10$o0Ih72zBsl7DbWxFJBZlL.1I8DwbYqh0.oFiVLJjyCiZy9H9yi2za', 'donor'),
  ('Admin BTS',   'admin@bts.bt',    '$2y$10$e62rFca.dgck7bwA9uMnG.pz.89W1GUu9at9HzkzSLt3jn7FQXmj2', 'admin'),
  ('Dr. Demo',    'doctor@bts.bt',   '$2y$10$OZPecy69Ry5pwAIKQBBMk.YAfJdedNXdrxMg1olyM14xSAzx0d3Si', 'doctor'),
  ('Staff Demo',  'staff@bts.bt',    '$2y$10$X/2jvxkXnIdwfAV/oZqR5uAdEOSDF1vipCkJyNMuXbljJ99F46ymC', 'staff');

-- ─── Blood bank seed (migrated from frontend hardcoded list) ────────────────
INSERT IGNORE INTO tblblood_banks (id, name, hospital, dzongkhag, address, phone, email, hours, emergency, status, types_csv) VALUES
    (1, 'National Blood Bank', 'Jigme Dorji Wangchuck National Referral Hospital', 'Thimphu', 'Gongphel Lam, Thimphu', '02-322496', 'national.bb@health.bt', 'Mon-Sat: 9:00 AM - 5:00 PM', '24/7 Emergency', 'open', 'A+, A-, B+, B-, O+, O-, AB+, AB-'),
    (2, 'Phuentsholing Blood Bank', 'Phuentsholing General Hospital', 'Chukha', 'Hospital Road, Phuentsholing', '05-252431', 'phuentsholing.bb@health.bt', 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, O+, AB+'),
    (3, 'Paro District Blood Bank', 'Paro General Hospital', 'Paro', 'Paro Town', '08-271116', NULL, 'Mon-Fri: 8:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, O+, AB+'),
    (4, 'Punakha District Blood Bank', 'Punakha District Hospital', 'Punakha', 'Punakha Town', '02-581116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, O+'),
    (5, 'Gasa District Blood Bank', 'Gasa District Hospital', 'Gasa', 'Gasa Town', '02-681116', NULL, 'Mon-Fri: 10:00 AM - 3:00 PM', 'Emergency on call', 'limited', 'A+, O+'),
    (6, 'Haa District Blood Bank', 'Haa District Hospital', 'Haa', 'Haa Town', '02-841116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'A+, B+, O+'),
    (7, 'Wangdue Blood Bank', 'Wangdue District Hospital', 'Wangdue Phodrang', 'Wangdue Phodrang Town', '02-481116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, O+, B+'),
    (8, 'Trongsa Blood Bank', 'Trongsa District Hospital', 'Trongsa', 'Trongsa Town', '03-521116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'A+, O+'),
    (9, 'Bumthang Blood Bank', 'Bumthang District Hospital', 'Bumthang', 'Jakar, Bumthang', '03-631116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'O+, A+'),
    (10, 'Mongar Blood Bank', 'Mongar Regional Referral Hospital', 'Mongar', 'Mongar Town', '04-641114', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, O+, O-'),
    (11, 'Trashigang Blood Bank', 'Trashigang Regional Referral Hospital', 'Trashigang', 'Trashigang Town', '04-721116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, O+, O-'),
    (12, 'Trashiyangtse District Blood Bank', 'Trashiyangtse District Hospital', 'Trashiyangtse', 'Trashiyangtse Town', '04-951116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'limited', 'A+, B+, O+'),
    (13, 'Lhuntse Blood Bank', 'Lhuntse District Hospital', 'Lhuntse', 'Lhuntse Town', '04-331116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'A+, O+, B+'),
    (14, 'Samdrup Jongkhar Blood Bank', 'Samdrup Jongkhar District Hospital', 'Samdrup Jongkhar', 'Samdrup Jongkhar Town', '07-251116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, O+'),
    (15, 'Gelephu Blood Bank', 'Gelephu Regional Referral Hospital', 'Sarpang', 'Gelephu Town, Sarpang', '06-251116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, B+, B-, O+'),
    (16, 'Dagana District Blood Bank', 'Dagana District Hospital', 'Dagana', 'Dagana Town', '05-351116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'A+, O+'),
    (17, 'Zhemgang District Blood Bank', 'Zhemgang District Hospital', 'Zhemgang', 'Zhemgang Town', '06-351116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'A+, B+, O+'),
    (18, 'Tsirang District Blood Bank', 'Tsirang District Hospital', 'Tsirang', 'Tsirang Town', '06-151116', NULL, 'Mon-Fri: 9:00 AM - 3:30 PM', 'Emergency on call', 'limited', 'A+, O+'),
    (19, 'Lingkortakha District Blood Bank', 'Lingkortakha District Hospital', 'Lingkortakha', 'Lingkortakha Town', '03-411116', NULL, 'Mon-Fri: 9:00 AM - 3:00 PM', 'Emergency on call', 'limited', 'A+, O+'),
    (20, 'Pemagatshel District Blood Bank', 'Pemagatshel District Hospital', 'Pemagatshel', 'Pemagatshel Town', '07-531116', NULL, 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'limited', 'A+, B+, O+');

-- ─── Inventory seed (default to National Blood Bank id=1) ───────────────────
INSERT IGNORE INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ffp_units) VALUES
    (1, 'A+', 11, 7, 3, 5),
    (1, 'A-', 3, 2, 1, 2),
    (1, 'B+', 6, 4, 2, 3),
    (1, 'B-', 2, 1, 0, 1),
    (1, 'AB+', 5, 3, 1, 2),
    (1, 'AB-', 1, 1, 0, 0),
    (1, 'O+', 14, 10, 4, 6),
    (1, 'O-', 4, 3, 1, 2);
