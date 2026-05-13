/**
 * ============================================================================
 * QUICK START: Manual Population for Testing
 * ============================================================================
 * 
 * Use this script if:
 * 1. Your tblinventory table is empty and you need test data
 * 2. You want to manually add specific inventory before populating units
 * 3. You're testing the population script logic
 * 
 * HOW TO USE:
 * 1. Open http://localhost/phpmyadmin
 * 2. Select "blood_donation" database
 * 3. Go to SQL tab
 * 4. Copy and paste ENTIRE content below
 * 5. Click Go
 * 
 * WHAT IT DOES:
 * - Adds sample inventory records to tblinventory
 * - Then runs the population script to fill tblblood_units
 * 
 * ============================================================================
 */

USE blood_donation;

-- Step 1: Check if tblinventory has data
-- (Remove comment if you want to see before/after)
-- SELECT COUNT(*) as inventory_count FROM tblinventory;

-- Step 2: Insert sample inventory data (if empty)
-- Modify these values to match your blood supply
INSERT IGNORE INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, fpp_units)
VALUES
  (1, 'O+', 2, 3, 1, 1),
  (1, 'O-', 1, 2, 0, 1),
  (1, 'A+', 2, 2, 1, 0),
  (1, 'A-', 1, 1, 0, 0),
  (1, 'B+', 1, 2, 0, 1),
  (1, 'AB+', 0, 1, 1, 2),
  (1, 'AB-', 1, 0, 0, 1);

-- Step 3: Verify inventory was added
SELECT '--- INVENTORY AFTER INSERT ---' as status;
SELECT blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, fpp_units 
FROM tblinventory 
WHERE blood_bank_id = 1
ORDER BY blood_type;

-- Step 4: Clear existing blood units (optional - uncomment if needed)
-- DELETE FROM tblblood_units WHERE unit_id LIKE 'U-2026-%';

-- Step 5: Run the population procedure
SELECT '--- RUNNING POPULATION PROCEDURE ---' as status;
CALL sp_populate_tblblood_units_from_inventory();

-- Step 6: Verify units were created
SELECT '--- RESULTS ---' as status;
SELECT COUNT(*) as total_units_created FROM tblblood_units WHERE status = 'Available';

SELECT '--- UNITS BY COMPONENT ---' as status;
SELECT blood_bank_id, blood_type, component, COUNT(*) as unit_count
FROM tblblood_units
WHERE status = 'Available'
GROUP BY blood_bank_id, blood_type, component
ORDER BY blood_bank_id, blood_type, component;

-- Step 7: Show sample unit rows
SELECT '--- SAMPLE UNITS (first 10) ---' as status;
SELECT id, unit_id, blood_bank_id, blood_type, component, expiry_date, status 
FROM tblblood_units 
LIMIT 10;
