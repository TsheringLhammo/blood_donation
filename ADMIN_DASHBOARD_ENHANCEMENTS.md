/**
 * ADMIN DASHBOARD ENHANCEMENTS - Implementation Guide
 * 
 * This file documents all the changes needed to implement the blood donation workflow improvements
 * Date: May 5, 2026
 */

// ============================================================================
// 1. NEW STATE DECLARATIONS (ADD TO AdminDashboard.js around line 80-100)
// ============================================================================

/*
  // Pending test results section
  const [pendingTestResults, setPendingTestResults] = useState(null);
  const [loadingPendingTestResults, setLoadingPendingTestResults] = useState(true);
  const [errorPendingTestResults, setErrorPendingTestResults] = useState("");

  // Search and filter state
  const [searchQuery, setSearchQuery] = useState("");
  const [filterBloodType, setFilterBloodType] = useState("");
  const [filterTestResult, setFilterTestResult] = useState("");
  const [filterFinalDecision, setFilterFinalDecision] = useState("");
  const [filterWorkflowStatus, setFilterWorkflowStatus] = useState("");

  // Decision modal for pending test results
  const [testDecisionModal, setTestDecisionModal] = useState({
    open: false,
    sampleId: null,
    donorName: "",
    donorEmail: "",
    testResults: {},
    selectedDecision: "accept",
    decisionNotes: "",
    notifyDonor: true,
  });
*/

// ============================================================================
// 2. FETCH FUNCTION FOR PENDING TEST RESULTS (ADD AROUND LINE 250)
// ============================================================================

/*
  const fetchPendingTestResults = useCallback(async () => {
    setLoadingPendingTestResults(true);
    setErrorPendingTestResults("");
    try {
      const res = await authFetch("get_pending_test_results.php");
      if (!res.ok) {
        throw new Error(await parseApiError(res, "Failed to load pending test results."));
      }

      const data = await res.json();
      if (data.success) {
        setPendingTestResults(data.data);
      } else {
        setErrorPendingTestResults(data.message || "Failed to load pending test results.");
      }
    } catch (error) {
      setErrorPendingTestResults(error.message || "Could not reach server.");
    }
    finally {
      setLoadingPendingTestResults(false);
    }
  }, [parseApiError]);
*/

// ============================================================================
// 3. FILTERED DONORS FUNCTION (ADD AROUND LINE 350)
// ============================================================================

/*
  const getFilteredDonors = useCallback(() => {
    if (!Array.isArray(donors)) return [];

    return donors.filter(donor => {
      const matchesSearch = 
        !searchQuery || 
        (donor.full_name && donor.full_name.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (donor.email && donor.email.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (donor.phone && donor.phone.includes(searchQuery));

      const matchesBloodType = !filterBloodType || donor.blood_type === filterBloodType;
      
      const matchesTestResult = !filterTestResult || 
        (filterTestResult === 'not_tested' && !donor.latest_test_result) ||
        (donor.latest_test_result === filterTestResult);

      const matchesFinalDecision = !filterFinalDecision || 
        (filterFinalDecision === 'pending' && donor.workflow_status?.startsWith('test')) ||
        (filterFinalDecision === 'accepted' && donor.workflow_status === 'decision_made_accepted') ||
        (filterFinalDecision === 'deferred' && donor.workflow_status === 'decision_made_deferred') ||
        (filterFinalDecision === 'rejected' && donor.workflow_status === 'decision_made_rejected');

      const matchesWorkflowStatus = !filterWorkflowStatus || donor.workflow_status === filterWorkflowStatus;

      return matchesSearch && matchesBloodType && matchesTestResult && 
             matchesFinalDecision && matchesWorkflowStatus;
    });
  }, [donors, searchQuery, filterBloodType, filterTestResult, filterFinalDecision, filterWorkflowStatus]);
*/

// ============================================================================
// 4. TEST DECISION HANDLER (ADD AROUND LINE 400)
// ============================================================================

/*
  const handleTestDecision = async (sampleId, donorName, donorEmail, testResults) => {
    setTestDecisionModal({
      open: true,
      sampleId,
      donorName,
      donorEmail,
      testResults,
      selectedDecision: "accept",
      decisionNotes: "",
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
          defer_until: testDecisionModal.deferDate,
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
      setTestDecisionModal({ open: false, sampleId: null, donorName: "", donorEmail: "", testResults: {}, selectedDecision: "accept", decisionNotes: "", notifyDonor: true });
      fetchPendingTestResults();
      fetchDonors();
    } catch (error) {
      const message = error.message || "Could not save decision.";
      setActionError(message);
      setBannerToast({ type: "error", message });
    }
  };

  const handleCloseTestDecisionModal = () => {
    setTestDecisionModal({ open: false, sampleId: null, donorName: "", donorEmail: "", testResults: {}, selectedDecision: "accept", decisionNotes: "", notifyDonor: true });
  };
*/

// ============================================================================
// 5. USEEFFECT HOOK UPDATE (UPDATE AROUND LINE 500)
// ============================================================================

/*
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
      fetchPendingTestResults();  // ADD THIS LINE
    }
  }, [user?.token, activeTab, fetchStats, fetchAppointments, fetchCamps, fetchDonors, fetchPendingDonors, fetchBloodBanks, fetchNotifications, fetchPendingTestResults]);
*/

// ============================================================================
// 6. FILTER SEARCH BAR IN UI (REPLACE IN RENDER SECTION AROUND LINE 1100)
// ============================================================================

/*
  <div className="admin-search-container">
    <div className="admin-search-input-group">
      <input
        type="text"
        placeholder="Search by name, email, or phone..."
        value={searchQuery}
        onChange={(e) => setSearchQuery(e.target.value)}
        className="admin-search-input"
      />
    </div>
    
    <div className="admin-filter-group">
      <select
        value={filterBloodType}
        onChange={(e) => setFilterBloodType(e.target.value)}
        className="admin-filter-select"
      >
        <option value="">All Blood Types</option>
        <option value="A+">A+</option>
        <option value="A-">A-</option>
        <option value="B+">B+</option>
        <option value="B-">B-</option>
        <option value="AB+">AB+</option>
        <option value="AB-">AB-</option>
        <option value="O+">O+</option>
        <option value="O-">O-</option>
      </select>

      <select
        value={filterTestResult}
        onChange={(e) => setFilterTestResult(e.target.value)}
        className="admin-filter-select"
      >
        <option value="">All Test Results</option>
        <option value="positive">Positive</option>
        <option value="negative">Negative</option>
        <option value="inconclusive">Inconclusive</option>
        <option value="not_tested">Not Tested</option>
      </select>

      <select
        value={filterFinalDecision}
        onChange={(e) => setFilterFinalDecision(e.target.value)}
        className="admin-filter-select"
      >
        <option value="">All Decisions</option>
        <option value="pending">Pending Decision</option>
        <option value="accepted">Accepted</option>
        <option value="deferred">Deferred</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>
  </div>
*/

// ============================================================================
// 7. PENDING TEST RESULTS SECTION (ADD BEFORE "All Registered Donors" TABLE)
// ============================================================================

/*
  {activeTab === "dashboard" && (
    <>
      <section className="admin-section">
        <div className="admin-panel-head">
          <h3>
            🔬 Pending Test Results Review
            {pendingTestResults && pendingTestResults.length > 0 && (
              <span className="admin-badge-count">{pendingTestResults.length}</span>
            )}
          </h3>
          <button onClick={fetchPendingTestResults} className="admin-refresh-btn">Refresh</button>
        </div>

        {errorPendingTestResults && (
          <div className="admin-error-box">{errorPendingTestResults}</div>
        )}

        {loadingPendingTestResults ? (
          <div className="admin-loading">Loading pending test results...</div>
        ) : !Array.isArray(pendingTestResults) || pendingTestResults.length === 0 ? (
          <div className="admin-no-data">No pending test results to review.</div>
        ) : (
          <table className="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Donor Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Blood Type</th>
                <th>Test Date</th>
                <th>Test Status</th>
                <th>Test Results</th>
                <th>Tested By</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pendingTestResults.map((result) => (
                <tr key={result.sample_id} className="admin-pending-result-row">
                  <td>{result.id}</td>
                  <td className="admin-td-name">{result.donor_name}</td>
                  <td className="admin-td-long">{result.email}</td>
                  <td>{result.phone}</td>
                  <td>
                    <span className="admin-badge-blood">{result.blood_type}</span>
                  </td>
                  <td>{result.collection_date}</td>
                  <td>
                    <span className={`admin-status-badge ${result.test_status === 'eligible' ? 'admin-status-confirmed' : 'admin-status-rejected'}`}>
                      {result.test_status === 'eligible' ? 'Eligible' : 'Deferred'}
                    </span>
                  </td>
                  <td className="admin-td-test-results">
                    <div className="test-result-item">
                      HIV: <strong>{result.hiv_result || 'Pending'}</strong>
                    </div>
                    <div className="test-result-item">
                      HBsAg: <strong>{result.hbsag_result || 'Pending'}</strong>
                    </div>
                    <div className="test-result-item">
                      HCV: <strong>{result.hcv_result || 'Pending'}</strong>
                    </div>
                    <div className="test-result-item">
                      Syphilis: <strong>{result.syphilis_result || 'Pending'}</strong>
                    </div>
                    <div className="test-result-item">
                      Malaria: <strong>{result.malaria_result || 'Pending'}</strong>
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
        )}
      </section>
    </>
  )}
*/

// ============================================================================
// 8. UPDATE DONORS TABLE (REPLACE EXISTING "All Registered Donors" TABLE)
// ============================================================================

/*
  CHANGES TO EXISTING DONORS TABLE:
  
  1. Add new columns to thead after "Blood Type":
     <th>Test Result</th>
     <th>Admin Decision</th>
     <th>Donor Notified</th>
  
  2. Update the table body rendering to include:
     <td>
       {row.latest_test_result ? (
         <span className={`admin-status-badge ${
           row.latest_test_result === 'positive' ? 'admin-status-rejected' :
           row.latest_test_result === 'negative' ? 'admin-status-confirmed' :
           'admin-status-pending'
         }`}>
           {row.latest_test_result.charAt(0).toUpperCase() + row.latest_test_result.slice(1)}
         </span>
       ) : "—"}
     </td>
     
     <td>
       {row.workflow_status ? (
         <span className={`admin-badge-decision ${
           row.workflow_status === 'decision_made_accepted' ? 'accepted' :
           row.workflow_status === 'decision_made_deferred' ? 'deferred' :
           row.workflow_status === 'decision_made_rejected' ? 'rejected' :
           'pending'
         }`}>
           {getWorkflowStatusLabel(row.workflow_status)}
         </span>
       ) : "—"}
     </td>
     
     <td>{row.donor_notified === 'yes' ? '✓ Yes' : row.donor_notified === 'no' ? '✗ No' : 'Pending'}</td>
*/

// ============================================================================
// 9. TEST DECISION MODAL (ADD BEFORE END OF RETURN)
// ============================================================================

/*
  {testDecisionModal.open && (
    <div className="admin-modal-backdrop" role="dialog">
      <div className="admin-modal admin-test-decision-modal">
        <div className="admin-modal-header">
          <h2>Review & Decide on Test Results</h2>
          <button className="admin-modal-close" onClick={handleCloseTestDecisionModal}>×</button>
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
            <label>Decision:</label>
            <select
              value={testDecisionModal.selectedDecision}
              onChange={(e) => setTestDecisionModal({...testDecisionModal, selectedDecision: e.target.value})}
              className="admin-select"
            >
              <option value="accept">Accept as Donor</option>
              <option value="defer">Temporarily Defer (6 months)</option>
              <option value="reject">Permanently Reject</option>
              <option value="retest">Request Retest</option>
            </select>
          </div>

          <div className="modal-form-group">
            <label>Decision Notes:</label>
            <textarea
              value={testDecisionModal.decisionNotes}
              onChange={(e) => setTestDecisionModal({...testDecisionModal, decisionNotes: e.target.value})}
              placeholder="Add any notes about this decision..."
              className="admin-textarea"
              rows="3"
            />
          </div>

          <div className="modal-form-group">
            <label>
              <input
                type="checkbox"
                checked={testDecisionModal.notifyDonor}
                onChange={(e) => setTestDecisionModal({...testDecisionModal, notifyDonor: e.target.checked})}
              />
              Notify Donor of Decision
            </label>
          </div>
        </div>

        <div className="admin-modal-footer">
          <button className="admin-btn-cancel" onClick={handleCloseTestDecisionModal}>Cancel</button>
          <button className="admin-btn-submit" onClick={handleSubmitTestDecision}>Save Decision</button>
        </div>
      </div>
    </div>
  )}
*/

// ============================================================================
// 10. HELPER FUNCTIONS (ADD AT END OF COMPONENT)
// ============================================================================

/*
  const getWorkflowStatusLabel = (status) => {
    const labels = {
      'pending_approval': 'Pending Approval',
      'approved_for_blood_draw': 'Approved for Blood Draw',
      'blood_drawn_pending_test': 'Blood Drawn - Awaiting Test',
      'test_result_pending_decision': 'Test Result - Pending Decision',
      'decision_made_accepted': 'Accepted',
      'decision_made_deferred': 'Deferred',
      'decision_made_rejected': 'Rejected',
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
*/

// ============================================================================
// 11. CSS CLASSES TO ADD (AdminDashboard.css)
// ============================================================================

/*
  .admin-search-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 20px;
    background: white;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }

  .admin-search-input-group {
    display: flex;
    gap: 8px;
  }

  .admin-search-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
  }

  .admin-search-input:focus {
    outline: none;
    border-color: #8B0000;
    box-shadow: 0 0 0 3px rgba(139,0,0,0.1);
  }

  .admin-filter-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
  }

  .admin-filter-select {
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    transition: border-color 0.2s;
  }

  .admin-filter-select:focus {
    outline: none;
    border-color: #8B0000;
  }

  .admin-badge-count {
    background: #8B0000;
    color: white;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
  }

  .admin-pending-result-row {
    border-left: 4px solid #ff9800;
  }

  .admin-td-test-results {
    font-size: 12px;
  }

  .test-result-item {
    padding: 2px 0;
  }

  .test-result-item strong {
    font-weight: 600;
  }

  .admin-test-decision-modal {
    max-width: 600px;
  }

  .modal-donor-info {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 20px;
  }

  .modal-donor-info h3 {
    margin: 0 0 4px 0;
    font-size: 18px;
  }

  .modal-donor-info p {
    margin: 0;
    color: #666;
    font-size: 14px;
  }

  .modal-test-results {
    margin-bottom: 20px;
  }

  .modal-test-results h4 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
  }

  .results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
  }

  .result-item {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    font-size: 12px;
  }

  .result-name {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
  }

  .result-value {
    display: block;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
  }

  .result-value.negative {
    background: #d4edda;
    color: #155724;
  }

  .result-value.positive {
    background: #f8d7da;
    color: #721c24;
  }

  .result-value.pending,
  .result-value.inconclusive {
    background: #fff3cd;
    color: #856404;
  }

  .admin-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: white;
    padding: 0;
  }

  .admin-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
  }

  .admin-textarea:focus {
    outline: none;
    border-color: #8B0000;
  }

  .admin-badge-decision {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
  }

  .admin-badge-decision.accepted {
    background: #d4edda;
    color: #155724;
  }

  .admin-badge-decision.deferred {
    background: #fff3cd;
    color: #856404;
  }

  .admin-badge-decision.rejected {
    background: #f8d7da;
    color: #721c24;
  }

  .admin-badge-decision.pending {
    background: #e7e7ff;
    color: #333;
  }

  @media (max-width: 768px) {
    .admin-filter-group {
      grid-template-columns: 1fr;
    }

    .results-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }
*/

export default null; // This is documentation only
