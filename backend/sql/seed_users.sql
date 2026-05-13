USE blood_donation;

-- Demo users (safe to run multiple times)
INSERT IGNORE INTO tblusers (name, email, password, role) VALUES
  ('Demo Donor',  'donor@gmail.com', '$2y$10$o0Ih72zBsl7DbWxFJBZlL.1I8DwbYqh0.oFiVLJjyCiZy9H9yi2za', 'donor'),
  ('Admin BTS',   'admin@bts.bt',    '$2y$10$e62rFca.dgck7bwA9uMnG.pz.89W1GUu9at9HzkzSLt3jn7FQXmj2', 'admin'),
  ('Dr. Demo',    'doctor@bts.bt',   '$2y$10$OZPecy69Ry5pwAIKQBBMk.YAfJdedNXdrxMg1olyM14xSAzx0d3Si', 'doctor'),
  ('Staff Demo',  'staff@bts.bt',    '$2y$10$X/2jvxkXnIdwfAV/oZqR5uAdEOSDF1vipCkJyNMuXbljJ99F46ymC', 'staff');
