-- Add audit log table for tracking donor changes
CREATE TABLE IF NOT EXISTS tbldonor_audit_log (
    id                    INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    donor_id              INT UNSIGNED        NOT NULL,
    changed_by_admin_id   INT UNSIGNED        NOT NULL,
    changed_by_admin_name VARCHAR(120)        NOT NULL,
    field_name            VARCHAR(100)        NOT NULL,
    old_value             TEXT                NULL,
    new_value             TEXT                NULL,
    change_reason         VARCHAR(255)        NULL,
    changed_at            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES tbldonors(id) ON DELETE CASCADE,
    INDEX idx_donor_id (donor_id),
    INDEX idx_changed_at (changed_at)
);

-- Ensure tbldonors has deferred_until field if not already present
ALTER TABLE tbldonors 
ADD COLUMN IF NOT EXISTS workflow_status VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS deferred_until DATE NULL,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
