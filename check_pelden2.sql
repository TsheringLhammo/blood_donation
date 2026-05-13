SELECT 
  s.id as sample_id,
  d.full_name,
  s.hiv_result,
  s.hbsag_result,
  s.hcv_result,
  s.syphilis_result,
  s.malaria_result,
  s.positive_diseases,
  s.decision_after_test,
  s.decision_date
FROM tbldonor_samples s
JOIN tbldonors d ON s.donor_id = d.id
WHERE d.full_name LIKE '%elden%'
LIMIT 1;
