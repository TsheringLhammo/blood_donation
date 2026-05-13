/**
 * BLOOD DONATION WORKFLOW ENHANCEMENTS - Implementation Kit
 * 
 * Date: May 5, 2026
 * Status: Ready for Integration
 * 
 * This file contains all the code snippets needed to upgrade the AdminDashboard.js
 * with the new workflow features including pending test results review and enhanced filtering.
 */

// =======================================================================================
// PART 1: Add this to useEffect (around line 500-550, update the dependency array)
// =======================================================================================

/*
ORIGINAL useEffect (around line 500):
  useEffect(() => {
    if (user?.token && activeTab === "dashboard") {
      fetchStats();
      fetchAppointments();
      fetchCamps();
      fetchDonors();
      fetchPendingDonors();
      fetchStats();
      fetchBloodBanks();
      fetchNotifications();
    }
  }, [user?.token, activeTab, fetchStats, fetchAppointments, fetchCamps, fetchDonors, fetchPendingDonors, fetchBloodBanks, fetchNotifications]);

UPDATED TO:
  useEffect(() => {
    if (user?.token && activeTab === "dashboard") {
      fetchStats();
      fetchAppointments();
      fetchCamps();
      fetchDonors();
      fetchPendingDonors();
      fetchStats();
      fetchBloodBanks();
      fetchNotifications();
      fetchPendingTestResults();
    }
  }, [user?.token, activeTab, fetchStats, fetchAppointments, fetchCamps, fetchDonors, fetchPendingDonors, fetchBloodBanks, fetchNotifications, fetchPendingTestResults]);
*/

// =======================================================================================
// PART 2: Helper functions to add (add these before the return statement, around line 1600)
// =======================================================================================

const getWorkflowStatusLabel = (status) => {
  const labels = {
    'pending_approval': 'Pending Approval',
    'approved_for_blood_draw': 'Approved to Donate',
    'blood_drawn_pending_test': 'Blood Drawn - Awaiting Test',
    'test_result_pending_decision': 'Test Result - Pending Decision',
    'decision_made_accepted': 'Accepted ✓',
    'decision_made_deferred': 'Deferred',
    'decision_made_rejected': 'Rejected ✗',
  };
  return labels[status] || status;
};

const formatSampleStatusLabel = (status) => {
  const labels = {
    'pending': 'Pending Test',
    'eligible': 'Eligible',
    'deferred': 'Deferred',
  };
  return labels[status] || status;
};

const getWorkflowStatusClass = (status) => {
  if (status === 'decision_made_accepted') return 'admin-status-confirmed';
  if (status === 'decision_made_deferred') return 'admin-status-pending';
  if (status === 'decision_made_rejected') return 'admin-status-rejected';
  return 'admin-status-pending';
};

const getTestResultColor = (result) => {
  if (result === 'positive') return 'admin-status-rejected';
  if (result === 'negative') return 'admin-status-confirmed';
  if (result === 'inconclusive') return 'admin-status-pending';
  return 'admin-status-pending';
};

const getFilteredDonors = () => {
  if (!Array.isArray(donors)) return [];

  return donors.filter(donor => {
    const matchesSearch = 
      !searchQuery || 
      (donor.full_name && donor.full_name.toLowerCase().includes(searchQuery.toLowerCase())) ||
      (donor.email && donor.email.toLowerCase().includes(searchQuery.toLowerCase())) ||
      (donor.phone && donor.phone.includes(searchQuery));

    const matchesBloodType = !filterBloodType || donor.blood_type === filterBloodType;
    
    const matchesTestResult = !filterTestResult || 
      (filterTestResult === 'not_tested' && (!donor.latest_test_result || donor.latest_test_result === 'not_tested')) ||
      (donor.latest_test_result === filterTestResult);

    const matchesFinalDecision = !filterFinalDecision;  // Add complex matching as needed
    
    const matchesWorkflowStatus = !filterWorkflowStatus || donor.workflow_status === filterWorkflowStatus;

    return matchesSearch && matchesBloodType && matchesTestResult && 
           matchesFinalDecision && matchesWorkflowStatus;
  });
};

const handleTestDecision = async (sampleId, donorName, donorEmail, testResults) => {
  setTestDecisionModal({
    open: true,
    sampleId,
    donorName,
    donorEmail,
    testResults,
    selectedDecision: "accept",
    decisionNotes: "",
    deferDate: new Date(Date.now() + 6 * 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    notifyDonor: true,
  });
};

const handleSubmitTestDecision = async () => {
  if (!testDecisionModal.sampleId) return;

  try {
    setActionError("");
    const res = await authFetch("finalize_decision_with_notification.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        sample_id: testDecisionModal.sampleId,
        decision: testDecisionModal.selectedDecision,
        defer_until: testDecisionModal.selectedDecision === 'defer' ? testDecisionModal.deferDate : null,
        decision_notes: testDecisionModal.decisionNotes,
        notify_donor: testDecisionModal.notifyDonor,
      }),
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || "Failed to save decision.");
    }

    setBannerToast({
      type: "success",
      message: `Decision saved for ${testDecisionModal.donorName}.`,
    });
    handleCloseTestDecisionModal();
    fetchPendingTestResults();
    fetchDonors();
  } catch (error) {
    const message = error.message || "Could not save decision.";
    setActionError(message);
    setBannerToast({ type: "error", message });
  }
};

const handleCloseTestDecisionModal = () => {
  setTestDecisionModal({
    open: false,
    sampleId: null,
    donorName: "",
    donorEmail: "",
    testResults: {},
    selectedDecision: "accept",
    decisionNotes: "",
    deferDate: "",
    notifyDonor: true,
  });
};

// =======================================================================================
// PART 3: Add Filter Search Bar in the dashboard (in the return/render section)
// =======================================================================================

/*
Add this BEFORE the activeTab === "dashboard" sections, around line 1100-1150:

{activeTab === "dashboard" && (
  <div className="admin-search-container">
    <div className="admin-search-input-group">
      <input
        type="text"
        placeholder="Search by name, email, or phone..."
        value={searchQuery}
        onChange={(e) => setSearchQuery(e.target.value)}
        className="admin-search-input"
        aria-label="Search donors"
      />
    </div>
    
    <div className="admin-filter-group">
      <select
        value={filterBloodType}
        onChange={(e) => setFilterBloodType(e.target.value)}
        className="admin-filter-select"
        aria-label="Filter by blood type"
      >
        <option value="">All Blood Types</option>
        {["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"].map(bt => (
          <option key={bt} value={bt}>{bt}</option>
        ))}
      </select>

      <select
        value={filterTestResult}
        onChange={(e) => setFilterTestResult(e.target.value)}
        className="admin-filter-select"
        aria-label="Filter by test result"
      >
        <option value="">All Test Results</option>
        <option value="positive">Positive</option>
        <option value="negative">Negative</option>
        <option value="inconclusive">Inconclusive</option>
        <option value="not_tested">Not Tested</option>
      </select>

      <select
        value={filterWorkflowStatus}
        onChange={(e) => setFilterWorkflowStatus(e.target.value)}
        className="admin-filter-select"
        aria-label="Filter by workflow status"
      >
        <option value="">All Workflow Status</option>
        <option value="pending_approval">Pending Approval</option>
        <option value="approved_for_blood_draw">Approved to Donate</option>
        <option value="blood_drawn_pending_test">Blood Drawn - Awaiting Test</option>
        <option value="test_result_pending_decision">Test Result - Pending Decision</option>
        <option value="decision_made_accepted">Accepted</option>
        <option value="decision_made_deferred">Deferred</option>
        <option value="decision_made_rejected">Rejected</option>
      </select>

      <button 
        onClick={() => {
          setSearchQuery("");
          setFilterBloodType("");
          setFilterTestResult("");
          setFilterWorkflowStatus("");
        }}
        className="admin-filter-reset-btn"
      >
        Clear Filters
      </button>
    </div>
  </div>
)}
*/

// =======================================================================================
// PART 4: Pending Test Results Section (add in return/render after filters)
// =======================================================================================

/*
Add this AFTER the filter search container and BEFORE "All Registered Donors" section:

{activeTab === "dashboard" && (
  <section className="admin-section">
    <div className="admin-panel-head">
      <h3>
        🔬 Pending Test Results Review
        {pendingTestResults && pendingTestResults.length > 0 && (
          <span className="admin-badge-count">{pendingTestResults.length}</span>
        )}
      </h3>
      <button 
        onClick={fetchPendingTestResults} 
        className="admin-refresh-btn"
        aria-label="Refresh pending test results"
      >
        🔄 Refresh
      </button>
    </div>

    {errorPendingTestResults && (
      <div className="admin-error-box">{errorPendingTestResults}</div>
    )}

    {loadingPendingTestResults ? (
      <div className="admin-loading">⏳ Loading pending test results...</div>
    ) : !Array.isArray(pendingTestResults) || pendingTestResults.length === 0 ? (
      <div className="admin-no-data">✓ No pending test results to review. All results have been processed.</div>
    ) : (
      <div className="admin-table-wrapper">
        <table className="admin-table">
          <thead>
            <tr>
              <th style={{ width: "60px" }}>#</th>
              <th>Donor Name</th>
              <th className="admin-th-hidden-tablet">Email</th>
              <th>Phone</th>
              <th>Blood Type</th>
              <th>Test Date</th>
              <th>Test Status</th>
              <th style={{ width: "200px" }}>Test Results</th>
              <th>Tested By</th>
              <th style={{ width: "80px" }}>Action</th>
            </tr>
          </thead>
          <tbody>
            {pendingTestResults.map((result) => (
              <tr key={result.sample_id} className="admin-pending-result-row">
                <td className="admin-td-id">{result.id}</td>
                <td className="admin-td-name">{result.donor_name}</td>
                <td className="admin-td-hidden-tablet admin-td-long">{result.email}</td>
                <td>{result.phone}</td>
                <td>
                  <span className="admin-badge-blood">{result.blood_type}</span>
                </td>
                <td className="admin-td-date">{result.collection_date}</td>
                <td>
                  <span className={`admin-status-badge ${result.test_status === 'eligible' ? 'admin-status-confirmed' : result.test_status === 'deferred' ? 'admin-status-rejected' : 'admin-status-pending'}`}>
                    {result.test_status === 'eligible' ? '✓ Eligible' : result.test_status === 'deferred' ? '⚠ Deferred' : '⏳ Pending'}
                  </span>
                </td>
                <td className="admin-td-test-results">
                  <div className="test-result-mini">
                    HIV: {result.hiv_result ? <strong>{result.hiv_result}</strong> : 'Pending'}
                  </div>
                  <div className="test-result-mini">
                    HBsAg: {result.hbsag_result ? <strong>{result.hbsag_result}</strong> : 'Pending'}
                  </div>
                  <div className="test-result-mini">
                    HCV: {result.hcv_result ? <strong>{result.hcv_result}</strong> : 'Pending'}
                  </div>
                </td>
                <td>{result.tested_by || 'Staff'}</td>
                <td className="admin-td-actions">
                  <button
                    className="admin-action-btn confirm"
                    onClick={() => handleTestDecision(result.sample_id, result.donor_name, result.email, {
                      hiv: result.hiv_result,
                      hbsag: result.hbsag_result,
                      hcv: result.hcv_result,
                      syphilis: result.syphilis_result,
                      malaria: result.malaria_result,
                    })}
                  >
                    Review
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </section>
)}
*/

// =======================================================================================
// PART 5: Update Donors Table Headers (modify existing donors.map section around line 1324)
// =======================================================================================

/*
ADD these columns to the <thead> in the "All Registered Donors" table after Blood Type:
                        <th>Test Result</th>
                        <th>Admin Decision</th>
                        <th>Donor Notified</th>
                        <th>Workflow Status</th>
*/

// =======================================================================================
// PART 6: Update Donors Table Body (modify the donors.map rendering)
// =======================================================================================

/*
ADD these cells to each <tr> in the "All Registered Donors" table after Blood Type column:

                            <td>
                              {row.latest_test_result && row.latest_test_result !== 'not_tested' ? (
                                <span className={`admin-status-badge ${getTestResultColor(row.latest_test_result)}`}>
                                  {row.latest_test_result.charAt(0).toUpperCase() + row.latest_test_result.slice(1)}
                                </span>
                              ) : "—"}
                            </td>

                            <td>
                              {row.workflow_status ? (
                                <span className={`admin-status-badge ${getWorkflowStatusClass(row.workflow_status)}`}>
                                  {getWorkflowStatusLabel(row.workflow_status)}
                                </span>
                              ) : "—"}
                            </td>

                            <td>
                              {row.donor_notified === 'yes' ? '✓ Yes' : row.donor_notified === 'no' ? '✗ No' : '⏳ Pending'}
                            </td>

                            <td>
                              <span className={`admin-status-badge ${getWorkflowStatusClass(row.workflow_status)}`}>
                                {getWorkflowStatusLabel(row.workflow_status)}
                              </span>
                            </td>
*/

// =======================================================================================
// PART 7: Test Decision Modal (add before final </AdminShell> closing tag)
// =======================================================================================

/*
Add this code near the end of the return statement, before </AdminShell>:

        {testDecisionModal.open && (
          <div className="admin-modal-backdrop" role="dialog" aria-label="Test decision dialog">
            <div className="admin-modal admin-test-decision-modal">
              <div className="admin-modal-header">
                <h2>Review & Finalize Test Decision</h2>
                <button 
                  className="admin-modal-close" 
                  onClick={handleCloseTestDecisionModal}
                  aria-label="Close dialog"
                >
                  ×
                </button>
              </div>

              <div className="admin-modal-body">
                <div className="modal-donor-info">
                  <h3>{testDecisionModal.donorName}</h3>
                  <p>{testDecisionModal.donorEmail}</p>
                </div>

                <div className="modal-test-results">
                  <h4>Test Results Summary:</h4>
                  <div className="results-grid">
                    {Object.entries(testDecisionModal.testResults).map(([test, result]) => (
                      <div key={test} className="result-item">
                        <span className="result-name">{test.toUpperCase()}:</span>
                        <span className={`result-value ${result ? result.toLowerCase() : 'pending'}`}>
                          {result || 'Not tested'}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="modal-form-group">
                  <label htmlFor="decision-select">Decision:</label>
                  <select
                    id="decision-select"
                    value={testDecisionModal.selectedDecision}
                    onChange={(e) => setTestDecisionModal({...testDecisionModal, selectedDecision: e.target.value})}
                    className="admin-select"
                  >
                    <option value="accept">✓ Accept as Donor</option>
                    <option value="defer">⏸ Temporarily Defer (6 months)</option>
                    <option value="reject">✗ Permanently Reject</option>
                    <option value="retest">🔄 Request Retest</option>
                  </select>
                </div>

                {testDecisionModal.selectedDecision === 'defer' && (
                  <div className="modal-form-group">
                    <label htmlFor="defer-until">Defer Until Date:</label>
                    <input
                      id="defer-until"
                      type="date"
                      value={testDecisionModal.deferDate}
                      onChange={(e) => setTestDecisionModal({...testDecisionModal, deferDate: e.target.value})}
                      className="admin-input"
                      min={new Date().toISOString().split('T')[0]}
                    />
                  </div>
                )}

                <div className="modal-form-group">
                  <label htmlFor="decision-notes">Decision Notes:</label>
                  <textarea
                    id="decision-notes"
                    value={testDecisionModal.decisionNotes}
                    onChange={(e) => setTestDecisionModal({...testDecisionModal, decisionNotes: e.target.value})}
                    placeholder="Add any notes about this decision (e.g., reason for deferral, required follow-ups)..."
                    className="admin-textarea"
                    rows="3"
                  />
                </div>

                <div className="modal-form-group modal-checkbox-group">
                  <label>
                    <input
                      type="checkbox"
                      checked={testDecisionModal.notifyDonor}
                      onChange={(e) => setTestDecisionModal({...testDecisionModal, notifyDonor: e.target.checked})}
                    />
                    Notify Donor of Decision (via email)
                  </label>
                </div>
              </div>

              <div className="admin-modal-footer">
                <button 
                  className="admin-btn-cancel" 
                  onClick={handleCloseTestDecisionModal}
                >
                  Cancel
                </button>
                <button 
                  className="admin-btn-submit" 
                  onClick={handleSubmitTestDecision}
                >
                  Save Decision
                </button>
              </div>
            </div>
          </div>
        )}
*/

export default null; // Documentation only
