-- Rule: reactive -> Discarded
--       eligible + past expiry -> Expired

USE blood_donation;

START TRANSACTION;

-- 1) Reactive test results must never be Expired.
UPDATE tblblood_units u
JOIN tbldonation_tests t
  ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
SET u.status = 'Discarded'
WHERE t.final_result IN ('Discard', 'Rejected')
  AND u.status = 'Expired';

-- 2) Eligible units that are past expiry must be Expired.
UPDATE tblblood_units u
JOIN tbldonation_tests t
  ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
SET u.status = 'Expired'
WHERE t.final_result IN ('Eligible', 'Safe')
  AND u.expiry_date < CURDATE()
  AND u.status IN ('Available', 'Reserved', 'Discarded');

-- 3) Eligible units that are still within expiry should be Available.
UPDATE tblblood_units u
JOIN tbldonation_tests t
  ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
SET u.status = 'Available'
WHERE t.final_result IN ('Eligible', 'Safe')
  AND u.expiry_date >= CURDATE()
  AND u.status IN ('Discarded', 'Expired');

COMMIT;

-- Verification queries
-- SELECT status, COUNT(*) FROM tblblood_units GROUP BY status;
-- SELECT u.unit_id, u.donation_id, u.status, u.expiry_date, t.final_result
-- FROM tblblood_units u
-- JOIN tbldonation_tests t ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
-- ORDER BY u.id DESC;
