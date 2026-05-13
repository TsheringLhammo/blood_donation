# BLOOD TRANSFUSION HISTORY & DIAGNOSIS - STAFF MANUAL

## Quick Reference: What is Diagnosis and Why It Matters

**Diagnosis** = The medical reason WHY the patient needs blood transfusion.  
Examples: postpartum hemorrhage, trauma, anemia, surgery, sepsis.

**Why it matters:**
- Helps doctors understand the patient's clinical history
- Critical for patient safety if there are future transfusions
- Required for accurate medical records and audits
- Helps identify trends in blood usage (e.g., high trauma cases)

---

## 1. ROLES AND RESPONSIBILITIES

### Doctor / Physician
**WHEN:** Creating a blood request in the system  
**WHAT TO DO:**
1. Fill out the blood request form
2. **Diagnosis field is now MANDATORY** - select from dropdown or enter "Other" for custom text
3. Common diagnoses to choose from:
   - Trauma / Hemorrhage
   - Gastrointestinal Bleed
   - Postpartum Hemorrhage
   - Anemia
   - Surgical Blood Loss
   - Cancer / Chemotherapy
   - Sepsis
   - Burn Injury
   - Hemophilia / Bleeding Disorder
   - Other (describe in text box)

**See Example Request Form at bottom**

### Lab/Blood Bank Staff
**WHEN:** Processing blood requests and performing cross-matching  
**WHAT TO DO:**
1. Approve/reject requests (diagnosis is already there from doctor)
2. Perform cross-match testing
3. **DO NOT need to enter diagnosis** - it's already in the request
4. When you issue blood, the diagnosis automatically flows into the Patient Transfusion History

**Important:** If a request is missing diagnosis, REJECT it and send it back to the doctor with comment: "Diagnosis is mandatory for all blood requests."

### Nurse / Clinical Staff
**WHEN:** Recording transfusion outcome or patient reactions  
**WHAT TO DO:**
1. Diagnosis is already available (entered by doctor)
2. Record any adverse reactions or patient response
3. Outcome is tracked with the diagnosis for complete history

---

## 2. COMMON TRANSFUSION DIAGNOSES REFERENCE

Use this list to quickly understand diagnosis categories:

### Hemorrhage / Bleeding
- **Trauma / Hemorrhage** - Injury, accident, blood loss from trauma
- **Gastrointestinal Bleed** - Bleeding in stomach, intestines, or esophagus
- **Postpartum Hemorrhage** - Excessive bleeding after childbirth

### Anemia & Production Issues
- **Anemia** - Low red blood cell count or hemoglobin
- **Cancer / Chemotherapy** - Blood loss due to cancer treatment

### Surgery & Procedures
- **Surgical Blood Loss** - Loss during planned surgical procedures

### Critical Illness
- **Sepsis** - Severe infection with systemic response
- **Burn Injury** - Massive fluid and blood loss from burns

### Genetic Disorders
- **Hemophilia / Bleeding Disorder** - Genetic bleeding tendency

### Other Cases
- **Other (specify)** - Any diagnosis not in the list above

---

## 3. WHAT HAPPENED: THE PROBLEM & THE FIX

### The Problem
Until now, many blood requests did NOT have a diagnosis recorded, showing "Not recorded" in the Patient Transfusion History table. This happened because:
- Diagnosis field was optional
- Some old requests didn't capture it
- History display didn't show status clearly

### The Fix (NOW IMPLEMENTED)
✅ **Mandatory Diagnosis Field**  
- Doctor MUST select or enter a diagnosis when creating a request
- System rejects request if diagnosis is empty
- Shows clear error message requiring diagnosis

✅ **Existing Records Updated**  
- Old records with missing diagnosis now show: "Pending - Please review"
- This alerts staff that these need manual update
- Lab/staff will update these gradually as they process requests

✅ **Visible in Staff Dashboard**  
- Staff dashboard now shows Diagnosis column in request table
- Makes it easy to see what diagnosis each request has
- Helps prioritize urgent cases

✅ **Auto-flows to History**  
- When blood is issued, diagnosis automatically appears in Patient Transfusion History
- No extra entry needed by staff

---

## 4. CURRENT STATUS & TO-DO

### Current Status
- ✅ Diagnosis field is now mandatory for NEW requests
- ✅ Backend validation enforces diagnosis requirement
- ✅ Doctor form has dropdown with common diagnoses
- ✅ Staff dashboard displays diagnosis for each request
- ✅ History shows diagnosis when blood is issued
- 🔄 Existing records: 1,800 requests updated to "Pending - Please review"

### Manual Follow-Up Actions (For Admin/Supervisors)
1. Review top 20-30 urgent cases with "Pending - Please review"
2. Check patient files / request notes for actual diagnosis
3. Update using the SQL tool (see "MANUAL UPDATE GUIDE" section below)
4. Repeat as time permits (suggest 50-100 per week)

### Testing the Fix
1. Log in as Doctor
2. Try to submit request WITHOUT selecting diagnosis
3. Should see error: "Diagnosis is mandatory"
4. Select or enter diagnosis, then submit works
5. Check Staff Dashboard - new request shows diagnosis column
6. When issued, check Patient Transfusion History - diagnosis appears there

---

## 5. MANUAL UPDATE GUIDE - FOR ADMIN STAFF

### Scenario: Need to Update Old Records with Wrong/Missing Diagnosis

#### Option A: Update Records in Database (SQL)

**Step 1: Connect to MySQL/MariaDB**
```
mysql -u root -p blood_donation
```

**Step 2: View records needing update**
```sql
SELECT id, request_code, patient_name, urgency, reason_for_transfusion, diagnosis
FROM tblblood_requests 
WHERE diagnosis = 'Pending - Please review'
ORDER BY urgency DESC, created_at DESC
LIMIT 10;
```

**Step 3: Update specific record**
```sql
UPDATE tblblood_requests 
SET diagnosis = 'Postpartum Hemorrhage'
WHERE id = 123 AND request_code = 'REQ-ABC12345';
```

**Step 4: Verify update**
```sql
SELECT id, diagnosis FROM tblblood_requests WHERE id = 123;
```

#### Option B: Bulk Update by Type (Example)

If multiple requests are for same diagnosis:
```sql
UPDATE tblblood_requests 
SET diagnosis = 'Anemia'
WHERE diagnosis = 'Pending - Please review'
  AND reason_for_transfusion LIKE '%anemia%'
  AND created_at > '2026-03-01';
```

#### Option C: Review Before Updating

For highest accuracy, check each record:

```sql
SELECT 
  id,
  request_code,
  patient_name,
  urgency,
  reason_for_transfusion,
  component,
  units_requested,
  DATE(created_at) as request_date
FROM tblblood_requests 
WHERE diagnosis = 'Pending - Please review'
  AND urgency = 'Critical'
ORDER BY created_at DESC;
```

Then update one at a time with the actual diagnosis from patient file.

---

## 6. PREVENTION: ENSURING THIS DOESN'T HAPPEN AGAIN

### What the System Does (Automatic)
✅ Rejects requests without diagnosis  
✅ Forces doctor to select from list or enter custom text  
✅ Diagnoses flow into history automatically  
✅ Staff dashboard shows diagnosis for monitoring  

### What Staff Should Do (Manual Quality Checks)
- **Weekly:** Check if any requests still show blank/empty diagnosis
- **Monthly:** Audit a sample of 10 requests to verify diagnosis matches patient condition
- **Per Shift:** If you see "Pending - Please review" in history, update it when you process that request

### Training New Staff
When onboarding new doctors/nurses:
1. Show them the diagnosis dropdown in request form
2. Explain it's MANDATORY
3. Have them complete 2-3 practice requests for training
4. Verify they understand common diagnoses
5. Add to staff onboarding checklist

---

## 7. COMMON QUESTIONS & ANSWERS

**Q: What if I'm not sure which diagnosis?**  
A: Contact the requesting doctor or check the patient's medical file. If still unsure, select "Other" and describe it in text.

**Q: Can diagnosis be changed after submitting?**  
A: Currently, editing is a manual database task. If diagnosis is wrong, contact admin to update. Future version may add edit feature.

**Q: Why is diagnosis mandatory if blood bank already knows urgency/type?**  
A: Diagnosis is CLINICAL context. Urgency tells HOW FAST you need it. Diagnosis tells WHY you need it. Both together tell the full story.

**Q: Do we need to update ALL old "Not recorded" entries?**  
A: Yes, eventually. Start with Critical/Urgent cases first, then work through Routine. Even 50 per week clears a year's backlog in several months.

**Q: Who gets notified if diagnosis is missing?**  
A: The system rejects the request and shows an error to the doctor. It's not submitted until diagnosis is filled.

---

## 8. EXAMPLE: DOCTOR BLOOD REQUEST FORM

```
BLOOD REQUEST FORM

Hospital: JDWNRH
Date/Time Required: 2026-04-20, 14:30

--- PATIENT DETAILS ---
Patient Name: Dorji Wangchuk
Hospital Ref No: MRN-000456
Gender: Male
Ward: ICU
Blood Group: O+

--- BLOOD REQUIRED ---
Component: Packed Red Cells
Units: 2
Blood Type: O+
Urgency: Urgent

--- CLINICAL INFORMATION ---
Diagnosis: *  [DROPDOWN - Mandatory]
  Selected: "Gastrointestinal Bleed"    ← Doctor selected from list
  
Additional Notes: Patient with active GI bleed, stable but needs transfusion support. Non-operative management planned.

Doctor Name: Dr. Tenzin Dorji
```

---

## 9. CHECKLIST: IMMEDIATE ACTIONS

### DO THIS NOW (Today/This Week)
- [ ] All staff read this guide
- [ ] Doctors understand diagnosis is mandatory
- [ ] Lab staff understand they don't need to enter diagnosis
- [ ] Verify one new request has diagnosis in the system
- [ ] Check Staff Dashboard shows "Diagnosis" column

### DO THIS THIS MONTH
- [ ] Update top 50 "Pending - Please review" records with real diagnoses
- [ ] Audit 10 random requests to verify diagnosis accuracy
- [ ] Add "Diagnosis is Mandatory" note to room/lab visible location
- [ ] Train any new staff on this process

### DO THIS ONGOING
- [ ] Weekly: Check for any blank diagnosis entries (should be zero)
- [ ] Monthly: Spot-check 5 requests for diagnosis accuracy
- [ ] Quarterly: Report statistics on diagnosis data quality

---

## 10. STILL HAVE QUESTIONS?

Contact: Blood Bank System Administrator  
Email: [admin.email]  
Phone: [admin.phone]  
Hours: Monday-Friday, 9 AM - 5 PM

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-20  
**Approved by:** Blood Bank Director  
**Next Review:** 2026-07-20
