-- Standardize request status values to Cross-Matching.
-- Run once on the target database.

ALTER TABLE tblblood_requests
  MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending';

UPDATE tblblood_requests
SET status = 'Cross-Matching'
WHERE LOWER(REPLACE(status, ' ', '-')) IN ('cross-match', 'crossmatching', 'cross-matching');
