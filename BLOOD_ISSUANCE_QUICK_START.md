# Blood Issuance Workflow - Quick Start Guide

## For Staff Using the System

### Normal Issuance Workflow (Step-by-Step)

#### 1. Approve the Request
- Go to **Staff Dashboard** → **Requests Tab**
- Find pending request from doctor
- Click **Approve** button
- Request status changes to "Approved"

#### 2. Start Cross-Matching
- Same request row appears in requests list
- Click **Start Cross-Match** button
- Status changes to "Cross-Matching"

#### 3. Lab Records Test Result
- Lab technician tests patient blood + donor units
- Tech records result in system:
  - **Record Compatible** → status becomes "Ready to Issue" ✓
  - **Record Incompatible** → status becomes "Rejected" ✗
  - **Record Pending** → status stays "Cross-Matching"

#### 4. Issue Blood (The New Confirmation Modal)
- When status = "Ready to Issue", **Issue Blood** button appears
- Click **Issue Blood**
- **Confirmation Modal Opens** showing:
  ```
  Request ID:        REQ-2026-00524
  Patient:           Ahmed Hassan
  Doctor:            Dr. Sarah Khan
  Blood Type:        O+
  Component:         Packed Red Cells
  Units:             2
  Urgency:           Critical
  Status:            Ready to Issue ✓
  ```

#### 5. Optional: Add Staff Comment
- Type in **"Staff Comment"** field
- Examples:
  - "Prioritized due to emergency surgery"
  - "Patient scheduled for 14:30 OR"
  - "Has previous reaction to some donors"

#### 6. Confirm & Issue
- Review all details above
- Click **Confirm & Issue Blood** (blue button)
- Modal closes
- System processes issuance automatically:
  - Marks blood units as "Issued"
  - Updates inventory count
  - Creates audit log entry
  - Sends notification to doctor

#### 7. Done ✓
- Request moves to **"Issue Log"** tab
- Doctor receives notification: "Blood Issued"
- Blood is now in patient care

---

## Emergency Issuance (When Every Second Counts)

### When to Use Emergency Mode
- ✓ Critical/life-threatening situation
- ✓ Massive blood loss
- ✓ Patient unstable, cannot wait for cross-match
- ✗ NOT for convenience or to skip steps

### How to Use

#### 1. Get to Issue Modal (Same as Normal)
- Request must be at least "Approved"
- Click **Issue Blood**
- Confirmation modal opens

#### 2. Check Emergency Checkbox
- Scroll down to yellow section
- Find checkbox: **"🚨 Emergency Issuance (Skip non-critical checks)"**
- Check the box
- Red warning banner appears:
  ```
  ⚠️ Warning: Emergency mode will bypass certain standard
  validations and create an audit flag. Use only in critical
  life-threatening situations. This action will be logged separately.
  ```

#### 3. Add Justification Comment (IMPORTANT!)
- In **"Staff Comment"** field, explain why:
  - "Critical blood loss, unstable vitals"
  - "Massive trauma, need units immediately"
  - "Patient in cardiogenic shock"

#### 4. Issue with Emergency Flag
- Button changes to red: **"Issue Blood (Emergency)"**
- Click button
- System issues blood while:
  - Bypassing cross-match requirement (if not done)
  - Still checking blood type & inventory
  - Creating emergency audit trail
  - Alerting all admins

#### 5. What Happens Next
- Request status: "Issued"
- Doctor receives notification: "🚨 Blood Issued (Emergency)"
- All admin users get alert: "⚠️ Emergency Blood Issuance Alert"
- Issue log shows: "🚨 EMERGENCY ISSUANCE"

---

## Troubleshooting

### "Issue Blood" Button Not Appearing
**Check:** Request status in table
- If "Pending" → Click **Approve** first
- If "Approved" → Click **Start Cross-Match**
- If "Cross-Matching" → Wait for cross-match result
- If "Rejected" → Cross-match failed, request failed ✗

### Modal Won't Submit - Error Message Appears
**Common Errors:**
- "Request must be in Matched status..."
  - Solution: Complete cross-match first (or use Emergency Mode)
- "Insufficient inventory units..."
  - Solution: Not enough blood in stock, wait for donations
- "Request already issued..."
  - Solution: Another staff member just issued, refresh your screen

### Emergency Mode Checkbox Not Visible
- Scroll down in modal
- It's in yellow box below comment field
- If still not visible, try resizing browser window

### Not Receiving Notifications
- If you're a doctor: Check **Doctor Dashboard** or notifications panel
- If you're admin: Check **Admin Dashboard** or notifications panel
- Notifications appear within ~30 seconds of issuance

---

## Best Practices

### ✓ DO
- Review request details in modal before submitting
- Add meaningful comments for all emergency issues
- Check patient name matches actual patient
- Verify blood type with another staff member (two-check rule)
- Use modal comment field to document clinical urgency
- For emergency: clearly state WHY it's emergency in comment

### ✗ DON'T
- Click "Issue" without reviewing modal details
- Use emergency mode for non-emergency situations
- Assume blood type without checking modal
- Rush through the confirmation step
- Issue same request twice (system prevents this)
- Leave comment blank on emergency issues

---

## FAQ

**Q: What if I need to issue units from multiple donors?**  
A: Modal issues all requested units automatically. System selects oldest available units first (FIFO).

**Q: Can I cancel after clicking Issue?**  
A: Yes - click **Cancel** button before modal closes. After it closes, issuance is complete and cannot be undone.

**Q: Do I need cross-match to issue in emergency mode?**  
A: No - emergency mode allows issuance from "Approved" status onward. But recommend cross-match whenever possible.

**Q: Who sees emergency alerts?**  
A: All users with "Admin" role. They get notification + message in Activity Log.

**Q: Is there a log of who issued what blood when?**  
A: Yes - **Issue Log** tab shows complete history:
- Date/time issued
- Request code
- Patient name
- Blood type & component
- Units issued
- Staff member name

**Q: Can I see which specific donation IDs were used?**  
A: Yes - Issue Log notes show individual unit IDs if available.

**Q: What happens to inventory after I issue?**  
A: Automatically decremented. Inventory tab updates immediately (refresh to see).

---

## Contact & Support

If you encounter issues:
1. Check troubleshooting section above
2. Review Staff Dashboard error messages
3. Contact system administrator with:
   - Request ID (REQ-XXXX)
   - Error message (if any)
   - What you were trying to do
   - Your username/ID
