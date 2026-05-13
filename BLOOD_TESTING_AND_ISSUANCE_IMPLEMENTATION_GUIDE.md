# Blood Testing and Safe Issuance Implementation Guide

Date: 2026-04-20
Project: Blood Bank Management System

## 1. What has been implemented in code

- Added staff lab form flow to record donation screening for:
  - HIV
  - HBsAg
  - HCV
  - Syphilis
  - Malaria
- Auto-calculation of final test result in backend:
  - Eligible (stored as Safe) when all five are Non-reactive
  - Discard (stored as Rejected) when any test is Reactive
- Automatic blood unit status update after test save:
  - Eligible -> Available
  - Reactive -> Discarded/Rejected fallback based on DB enum
- Safe issuance enforcement:
  - Cannot issue blood unless compatible unit has donation_id
  - Cannot issue blood unless donation has test record
  - Cannot issue blood unless latest final result is Safe/Eligible
- Staff warning message path added:
  - Cannot issue - donation test results missing or reactive
- Staff request data now includes:
  - diagnosis
  - reason_for_transfusion fallback
  - compatible_donation_id
  - compatible_test_final_result

## 2. Files changed

Backend APIs:
- backend/api/record_donation_test.php
- backend/api/issue_blood_unit.php
- backend/api/get_staff_dashboard.php
- backend/api/get_untested_donations.php (new)

Frontend:
- src/pages/StaffDashboard.js
- src/pages/StaffDashboard.css

Database migration script:
- backend/sql/migrate_donation_testing_safety.sql (new)

## 3. Database adjustments and SQL

Run this migration once:

- backend/sql/migrate_donation_testing_safety.sql

It ensures:
- tbldonation_tests has required screening columns
- final_result, tested_by_user_id, tested_at are present
- indexes for donation/test lookup are present
- helper view v_blood_unit_test_status exists

Manual verification queries:

1) Check untested linked donation IDs:
SELECT u.donation_id, COUNT(*) AS units
FROM tblblood_units u
LEFT JOIN tbldonation_tests t
  ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
WHERE u.donation_id IS NOT NULL
  AND TRIM(CAST(u.donation_id AS CHAR)) <> ''
  AND t.id IS NULL
GROUP BY u.donation_id
ORDER BY u.donation_id;

2) Check final test distribution:
SELECT final_result, COUNT(*)
FROM tbldonation_tests
GROUP BY final_result;

3) Check units that are currently unsafe for issue:
SELECT unit_id, donation_id, status
FROM tblblood_units
WHERE donation_id IS NULL
   OR TRIM(CAST(donation_id AS CHAR)) = ''
   OR NOT EXISTS (
      SELECT 1
      FROM tbldonation_tests t
      WHERE CAST(t.donation_id AS CHAR) = CAST(tblblood_units.donation_id AS CHAR)
        AND t.final_result IN ('Safe', 'Eligible')
   );

## 4. Staff dashboard workflow

### Step A: Record donation test result

Open Staff Dashboard -> Lab tab -> Record Donation Test Results

Required fields:
- Donation ID (dropdown from untested donations)
- HIV: Non-reactive or Reactive
- HBsAg: Non-reactive or Reactive
- HCV: Non-reactive or Reactive
- Syphilis: Non-reactive or Reactive
- Malaria: Non-reactive or Reactive
- Remarks: optional

Submit behavior:
- Saves into tbldonation_tests
- Auto-computes final_result
- Updates linked tblblood_units status

### Step B: Issue blood safely

Issue button is only active when:
- request is at issue stage (matched/compatible)
- compatible unit is linked
- compatible unit status is usable
- donation test exists and final result is Safe/Eligible

If test data is missing/reactive:
- Issue action is blocked
- Warning shown: Cannot issue - donation test results missing or reactive.

## 5. Diagnosis workflow status

Current behavior now:
- Doctor request API and dashboard use diagnosis
- History fallback supports reason_for_transfusion
- Staff request table shows diagnosis with reason fallback

Recommended policy:
- Keep diagnosis mandatory on doctor request submit
- Do not allow request submission with blank diagnosis

## 6. Role-based operating instructions

Staff (Lab Tech):
- Record all five screening results before issue
- Issue only when final result is Safe/Eligible
- If warning appears, do not bypass; complete testing first

Doctor:
- Enter mandatory diagnosis when creating request
- Track request and history with diagnosis context

Admin:
- Monitor test completion rate
- Audit issue logs against test results
- Ensure no issuance happened with missing/reactive tests

Donor:
- Book appointment
- Track donation status (testing pending, safe/rejected)

## 7. API examples for testing

Record test result:
POST /backend/api/record_donation_test.php
{
  "donationId": 54,
  "hivResult": "Non-reactive",
  "hbsagResult": "Non-reactive",
  "hcvResult": "Non-reactive",
  "syphilisResult": "Non-reactive",
  "malariaResult": "Non-reactive",
  "remarks": "All screening markers non-reactive"
}

Expected backend storage mapping:
- Non-reactive -> Negative
- Reactive -> Reactive

Get pending untested donations:
GET /backend/api/get_untested_donations.php

## 8. Go-live checklist

- Run migration script
- Hard refresh staff browser
- Record one positive and one reactive test in UAT
- Confirm reactive donation cannot be issued
- Confirm safe donation can be issued
- Confirm diagnosis appears in staff and doctor views
