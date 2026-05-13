import React, { useEffect, useMemo, useState } from "react";
import { toast } from "react-toastify";
import "../AdminDashboard.css";
import AdminShell from "../../components/admin/AdminShell";
import { getStoredUser } from "../../utils/auth";
import { approveDonor, approveSampleTest, getAllDonors, rejectDonor } from "../../api/donorApi";

const BLOOD_TYPES = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

const normalizeStatusLabel = (status) => {
  if (!status) return "Unknown";
  const labels = {
    awaiting_review: "Awaiting Review",
    approved_to_donate: "Approved to Donate",
    approved_donor: "Approved Donor",
    tested_negative: "Tested - Negative",
    blood_donated: "Blood Donated",
    temporarily_deferred: "Temporarily Deferred",
    permanently_deferred: "Permanently Deferred",
    approved_for_blood_draw: "Approved to Donate",
    decision_made_accepted: "Approved Donor",
    decision_made_deferred: "Temporarily Deferred",
  };
  if (labels[status]) return labels[status];
  return status
    .replace(/_/g, " ")
    .split(" ")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
};

const getStatusBadgeClass = (status) => {
  if (!status) return "bg-secondary text-white";
  const normalized = String(status).toLowerCase();
  if (normalized.includes("approved")) return "bg-success text-white";
  if (normalized.includes("rejected")) return "bg-danger text-white";
  if (normalized.includes("pending") || normalized.includes("await")) return "bg-warning text-dark";
  if (normalized.includes("deferred")) return "bg-info text-white";
  return "bg-primary text-white";
};

export default function DonorsManagement() {
  const [user, setUser] = useState(null);
  const [donors, setDonors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [filterBloodType, setFilterBloodType] = useState("");
  const [filterWorkflowStatus, setFilterWorkflowStatus] = useState("");

  useEffect(() => {
    const stored = getStoredUser();
    if (!stored?.token) {
      window.location.href = "/blood_donation/login";
      return;
    }
    if (stored.role !== "admin") {
      window.location.href = "/blood_donation/dashboard";
      return;
    }
    setUser(stored);
  }, []);

  useEffect(() => {
    if (!user) return;
    fetchDonors();
  }, [user]);

  const fetchDonors = async () => {
    setLoading(true);
    setError("");
    try {
      const data = await getAllDonors();
      setDonors(data || []);
    } catch (err) {
      setError(err.message || "Unable to load donor list.");
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (donorId) => {
    try {
      await approveDonor(donorId);
      toast.success("Donor approved successfully");
      fetchDonors();
    } catch (err) {
      toast.error(err.message || "Unable to approve donor.");
    }
  };

  const handleApproveSample = async (sampleId, donorName) => {
    if (!sampleId) return;
    if (!window.confirm(`Approve sample and send result message to ${donorName}?`)) return;
    try {
      const result = await approveSampleTest(sampleId);
      const eligibility = result?.data?.eligibility || "Eligible";
      const message = eligibility.toLowerCase() === "eligible"
        ? "Sample approved and message sent."
        : "Sample processed and donor deferred.";
      toast.success(message);
      fetchDonors();
    } catch (err) {
      toast.error(err.message || "Unable to approve sample.");
    }
  };

  const handleReject = async (donorId) => {
    const reason = prompt("Enter rejection reason:");
    if (reason === null || reason.trim() === "") return;
    try {
      await rejectDonor(donorId, reason.trim());
      toast.success("Donor rejected successfully");
      fetchDonors();
    } catch (err) {
      toast.error(err.message || "Unable to reject donor.");
    }
  };

  const workflowStatusOptions = useMemo(() => {
    return Array.from(new Set(donors.map((donor) => donor.workflow_status).filter(Boolean))).sort();
  }, [donors]);

  const filteredDonors = donors.filter((donor) => {
    const query = searchQuery.trim().toLowerCase();
    const matchesSearch =
      !query ||
      donor.full_name?.toLowerCase().includes(query) ||
      donor.email?.toLowerCase().includes(query) ||
      donor.phone?.toLowerCase().includes(query);

    const matchesBloodType = !filterBloodType || donor.blood_type === filterBloodType;
    const matchesWorkflowStatus = !filterWorkflowStatus || donor.workflow_status === filterWorkflowStatus;

    return matchesSearch && matchesBloodType && matchesWorkflowStatus;
  });

  if (!user) return null;

  return (
    <AdminShell
      user={user}
      activeView="donors"
      onChangeView={() => {}}
      title="Donors"
      subtitle="Manage registered donors and workflow status"
    >
      <div className="container-fluid py-4">
        <div className="card mb-4 shadow-sm">
          <div className="card-body">
            <h2 className="card-title">Donors Management</h2>
            <p className="card-text text-muted mb-0">
              View all donors, search by name/email/phone, filter by blood type or workflow status, and approve or reject donors.
            </p>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="row gy-3 mb-4">
              <div className="col-12 col-md-4">
                <input
                  type="search"
                  className="form-control"
                  placeholder="Search by name, email, or phone"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-3">
                <select
                  className="form-select"
                  value={filterBloodType}
                  onChange={(e) => setFilterBloodType(e.target.value)}
                >
                  <option value="">All Blood Types</option>
                  {BLOOD_TYPES.map((type) => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
              <div className="col-12 col-md-3">
                <select
                  className="form-select"
                  value={filterWorkflowStatus}
                  onChange={(e) => setFilterWorkflowStatus(e.target.value)}
                >
                  <option value="">All Workflow Statuses</option>
                  {workflowStatusOptions.map((status) => (
                    <option key={status} value={status}>{normalizeStatusLabel(status)}</option>
                  ))}
                </select>
              </div>
              <div className="col-12 col-md-2 d-grid">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={() => {
                    setSearchQuery("");
                    setFilterBloodType("");
                    setFilterWorkflowStatus("");
                  }}
                >
                  Clear Filters
                </button>
              </div>
            </div>

            {loading ? (
              <div className="text-center py-5">⏳ Loading donors...</div>
            ) : error ? (
              <div className="alert alert-danger">{error}</div>
            ) : filteredDonors.length === 0 ? (
              <div className="alert alert-info mb-0">No donors match the current filters.</div>
            ) : (
              <div className="table-responsive">
                <table className="table table-bordered table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th style={{ width: 50 }}>#</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Blood Type</th>
                      <th>Test Result</th>
                      <th>Workflow Status</th>
                      <th style={{ width: 240 }}>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredDonors.map((donor) => (
                      <tr key={donor.id}>
                        <td>{donor.id}</td>
                        <td>{donor.full_name}</td>
                        <td>{donor.email || "—"}</td>
                        <td>{donor.phone || "—"}</td>
                        <td>{donor.blood_type || "—"}</td>
                        <td>{(donor.positive_diseases && donor.positive_diseases !== '') ? `Positive (${donor.positive_diseases})` : (donor.hiv_result || donor.hbsag_result || donor.hcv_result || donor.syphilis_result || donor.malaria_result ? 'Negative' : '—')}</td>
                        <td>
                          <span className={`badge ${getStatusBadgeClass(donor.workflow_status)}`}>
                            {normalizeStatusLabel(donor.workflow_status)}
                          </span>
                          <div>
                            <small className="text-muted">{String(donor.review_status || "").replace(/_/g, ' ')} {donor.sample_id ? `| sample:${donor.sample_id}` : ''}</small>
                          </div>
                        </td>
                        <td>
                          <div className="d-flex flex-wrap gap-2">
                            {donor.review_status === "Pending Admin Approval" && donor.sample_id ? (
                              <button
                                type="button"
                                className="btn btn-sm btn-primary"
                                onClick={() => handleApproveSample(donor.sample_id, donor.full_name)}
                              >
                                Approve & Send Message
                              </button>
                            ) : null}
                            <button
                              type="button"
                              className="btn btn-sm btn-success"
                              onClick={() => handleApprove(donor.id)}
                            >
                              Approve
                            </button>
                            <button
                              type="button"
                              className="btn btn-sm btn-danger"
                              onClick={() => handleReject(donor.id)}
                            >
                              Reject
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>
    </AdminShell>
  );
}
