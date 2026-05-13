SELECT 
  s.id as sample_id,
  s.donor_id,
  d.full_name,
  s.hiv_result,
  s.hbsag_result,
  s.hcv_result,
  s.syphilis_result,
  s.malaria_result,
  s.extra_diseases,
  s.staff_deferral_reason,
  s.staff_deferral_duration
FROM tbldonor_samples s
JOIN tbldonors d ON s.donor_id = d.id
WHERE d.full_name LIKE '%elden%'
LIMIT 1;
