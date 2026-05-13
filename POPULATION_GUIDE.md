/**
 * ============================================================================
 * POPULATION SCRIPT - COMPLETE GUIDE
 * ============================================================================
 * 
 * Your tblblood_units population has 3 options. Choose ONE based on your needs:
 * 
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ OPTION 1: SQL Stored Procedure (RECOMMENDED - Easiest)                  │
 * ├─────────────────────────────────────────────────────────────────────────┤
 * │ File: backend/sql/recreate_tblblood_units_and_populate.sql              │
 * │ Time: 2 minutes                                                         │
 * │ Requirements: phpMyAdmin access                                         │
 * │ Best for: Quick setup, no PHP needed                                    │
 * │                                                                         │
 * │ STEPS:                                                                  │
 * │ 1. Open http://localhost/phpmyadmin                                     │
 * │ 2. Go to Import tab                                                     │
 * │ 3. Choose: backend/sql/recreate_tblblood_units_and_populate.sql         │
 * │ 4. Click Import                                                         │
 * │ 5. Wait for completion (should show row count output)                   │
 * │ 6. Verify: SELECT COUNT(*) FROM tblblood_units; should be > 0              │
 * │                                                                         │
 * │ RESULT: Automatic table drop + create + populate ✓                     │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ OPTION 2: PHP Script (Detailed Output)                                 │
 * ├─────────────────────────────────────────────────────────────────────────┤
 * │ File: backend/scripts/populate_tblblood_units_from_inventory_exact.php  │
 * │ Time: 1 minute                                                          │
 * │ Requirements: Browser or command line                                   │
 * │ Best for: Detailed breakdown, troubleshooting                           │
 * │                                                                         │
 * │ STEPS:                                                                  │
 * │ 1. Open in browser:                                                     │
 * │    http://localhost/blood_donation/backend/scripts/              │
 * │    populate_tblblood_units_from_inventory_exact.php              │
 * │ 2. View JSON response showing inserted count and per-component breakdown│
 * │ 3. Verify: "success": true and "inserted" > 0                          │
 * │                                                                         │
 * │ OR command line:                                                        │
 * │ C:\xampp\php\php.exe backend/scripts/                            │
 * │   populate_tblblood_units_from_inventory_exact.php               │
 * │                                                                         │
 * │ RESULT: Populates without dropping table, shows JSON stats ✓            │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ OPTION 3: Manual Test Data + Population (For Testing)                   │
 * ├─────────────────────────────────────────────────────────────────────────┤
 * │ File: backend/sql/populate_with_test_data.sql                           │
 * │ Time: 3 minutes                                                         │
 * │ Requirements: phpMyAdmin and test data                                  │
 * │ Best for: First-time testing, empty inventory                           │
 * │                                                                         │
 * │ STEPS:                                                                  │
 * │ 1. Ensure tblblood_units table exists (Option 1 or 2 must run first)  │
 * │ 2. Open http://localhost/phpmyadmin                                     │
 * │ 3. Select "blood_donation" database                                     │
 * │ 4. Go to SQL tab                                                        │
 * │ 5. Copy entire content of populate_with_test_data.sql                   │
 * │ 6. Paste into SQL editor                                                │
 * │ 7. Click Go                                                             │
 * │ 8. View output showing inserted units per component                     │
 * │                                                                         │
 * │ RESULT: Test data added + populated + verified in one go ✓              │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * ============================================================================
 * STEP-BY-STEP FOR FIRST-TIME SETUP
 * ============================================================================
 * 
 * SCENARIO A: Fresh Start (No Inventory Data Yet)
 * ──────────────────────────────────────────────────────
 * 1. Run OPTION 3 (populate_with_test_data.sql) first
 *    - This adds sample inventory to tblinventory
 *    - Then automatically populates tblblood_units
 * 2. Verify output shows units created
 * 3. Done - ready to use the application
 * 
 * SCENARIO B: Already Have Inventory Data
 * ──────────────────────────────────────────────────────
 * 1. Run OPTION 1 (recreate_tblblood_units_and_populate.sql)
 *    or OPTION 2 (PHP script)
 * 2. Choose based on preference (Option 1 easier, Option 2 more detailed)
 * 3. Verify: SELECT COUNT(*) FROM tblblood_units; should be > 0
 * 4. Done
 * 
 * SCENARIO C: Want Detailed Information
 * ──────────────────────────────────────────────────────
 * 1. Run OPTION 2 (PHP script)
 * 2. View JSON showing exact breakdown:
 *    - How many units per blood type
 *    - How many per component (Whole Blood, PRBC, Platelets, FFP)
 *    - How many existed before, how many added
 * 3. Great for troubleshooting if counts don't match
 * 
 * ============================================================================
 * TROUBLESHOOTING
 * ============================================================================
 * 
 * Problem: "tblblood_units is empty after running scripts"
 * ──────────────────────────────────────────────────────
 *   1. Check tblinventory: SELECT COUNT(*) FROM tblinventory;
 *      If 0 rows: Add inventory first using OPTION 3
 *   2. Check for errors in import/execution
 *      - phpMyAdmin shows "successful" but no rows? Check error log
 *   3. Try OPTION 2 (PHP) for detailed error messages
 * 
 * Problem: "Script says 'No inventory rows found'"
 * ──────────────────────────────────────────────────────
 *   1. Your tblinventory is empty
 *   2. OR all rows have NULL/empty blood_type
 *   3. Solution: Run OPTION 3 to add test data first
 * 
 * Problem: "Missing column: fpp_units" error
 * ──────────────────────────────────────────────────────
 *   1. Your tblinventory doesn't have the fpp_units column
 *   2. Check: SHOW COLUMNS FROM tblinventory;
 *   3. Solution: Update your tblinventory schema to include all columns
 *      (whole_units, prbc_units, platelets_units, fpp_units)
 * 
 * Problem: "Inserted 0 rows"
 * ──────────────────────────────────────────────────────
 *   1. Most likely: all values in tblinventory are 0
 *   2. Check: SELECT * FROM tblinventory;
 *   3. Solution: Run OPTION 3 which adds sample data with non-zero values
 * 
 * ============================================================================
 * VERIFICATION QUERIES
 * ============================================================================
 * 
 * Run these in phpMyAdmin SQL tab to verify population worked:
 * 
 * 1. Total units created:
 *    SELECT COUNT(*) as total_units FROM tblblood_units;
 * 
 * 2. Units per component:
 *    SELECT blood_bank_id, blood_type, component, COUNT(*) as count
 *    FROM tblblood_units
 *    WHERE status = 'Available'
 *    GROUP BY blood_bank_id, blood_type, component;
 * 
 * 3. Compare with inventory:
 *    SELECT blood_bank_id, blood_type,
 *           SUM(whole_units) as inv_whole,
 *           SUM(prbc_units) as inv_prbc,
 *           SUM(platelets_units) as inv_platelets,
 *           SUM(fpp_units) as inv_fpp
 *    FROM tblinventory
 *    GROUP BY blood_bank_id, blood_type;
 * 
 *    Counts should match between:
 *    - inv_whole ↔ units with component='Whole Blood'
 *    - inv_prbc ↔ units with component='PRBC'
 *    - inv_platelets ↔ units with component='Platelets'
 *    - inv_fpp ↔ units with component='FFP'
 * 
 * ============================================================================
 */
