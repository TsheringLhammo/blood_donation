import React, { useEffect, useState, useCallback, useRef } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import "./DoctorDashboard.css";
import { authFetch, clearAuthSession, getStoredUser } from "../utils/auth";
import DoctorShell from "../components/doctor/DoctorShell";
import { titleCase } from "../utils/strings";

const INITIAL_FORM = {
  hospitalName: "JDWNRH",
  patientName: "",
  patientRefNo: "",
  ward: "",
  patientGender: "",
  bloodType: "",
  component: "Packed Red Cells",
  units: 1,
  urgency: "Routine",
  diagnosis: "",
  diagnosisOther: "",
  reason: "",
  dateRequired: "",
  doctorName: "",
};

function formatHistoryDate(value) {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return String(value);
  }

  return parsed.toLocaleDateString();
}

function formatGender(value) {
  const normalized = String(value || "").trim().toLowerCase();
  if (normalized === "m" || normalized === "male") {
    return "Male";
  }
  if (normalized === "f" || normalized === "female") {
    return "Female";
  }
  if (normalized === "other") {
    return "Other";
  }
  return value || "—";
}

function buildHistoryFromRequests(requestRows) {
  if (!Array.isArray(requestRows)) {
    return [];
  }

  return requestRows
    .map((row) => {
      const component = row.component || "Blood component";
      const unitsRaw = Number(row.units ?? row.units_requested ?? 0);
      const units = Number.isFinite(unitsRaw) && unitsRaw > 0
        ? `${unitsRaw} unit${unitsRaw > 1 ? "s" : ""}`
        : null;
      const bloodType = row.blood_type ? `for ${row.blood_type}` : null;
      const diagnosis = row.diagnosis || row.reason_for_transfusion || row.reason || "Not recorded";

      return {
        date: formatHistoryDate(row.requested_at || row.created_at || row.date_required),
        diagnosis,
        transfusion: [component, units, bloodType].filter(Boolean).join(" "),
        outcome: row.outcome || row.status || "Pending",
        _sortDate: new Date(row.requested_at || row.created_at || row.date_required || 0).getTime() || 0,
      };
    })
    .sort((a, b) => b._sortDate - a._sortDate)
    .map(({ _sortDate, ...row }) => row);
}

export default function DoctorDashboard() {
  const navigate = useNavigate();
  const location = useLocation();

  // Scroll to the anchored section when the URL hash changes
  // (so sidebar links #request-form / #request-status land correctly).
  useEffect(() => {
    const rawHash = (location.hash || "").replace(/^#/, "");
    if (!rawHash) return undefined;
    let attempt = 0;
    const tick = () => {
      const el = document.getElementById(rawHash);
      if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "start" });
        return;
      }
      attempt += 1;
      if (attempt < 10) setTimeout(tick, 100);
    };
    tick();
    return undefined;
  }, [location.hash]);
  const [user, setUser] = useState(null);
  const [form, setForm] = useState(INITIAL_FORM);
  const [submitted, setSubmitted] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [loading, setLoading] = useState(false);
  const [requests, setRequests] = useState([]);
  const [history, setHistory] = useState([]);
  const [modalMessage, setModalMessage] = useState("");
  const successBannerRef = useRef(null);
  const previousStatusRef = useRef(new Map());

  useEffect(() => {
    const parsed = getStoredUser();
    if (!parsed?.token) {
      clearAuthSession();
      navigate("/login", { replace: true });
      return;
    }
    if (parsed.role !== "doctor") {
      clearAuthSession();
      navigate("/", { replace: true });
      return;
    }
    setUser(parsed);
    setForm((prev) => ({ ...prev, doctorName: parsed.name || "" }));
  }, [navigate]);

  const notifyIssued = useCallback((requestRow) => {
    const title = "Blood Request Issued";
    const body = `${requestRow.patient || "Patient"} (${requestRow.request_code || requestRow.id}) is now Issued.`;

    if (typeof window !== "undefined" && "Notification" in window && Notification.permission === "granted") {
      new Notification(title, { body });
      return;
    }

    setModalMessage(body);
  }, []);

  const loadRequests = useCallback(async (patientName = "", options = { silent: false }) => {
    if (!user) return;
    if (!options.silent) {
      setLoading(true);
    }
    try {
      const suffix = patientName
        ? `?patient=${encodeURIComponent(patientName)}`
        : "";
      const res = await authFetch(`get_requests.php${suffix}`);
      const data = await res.json();
      if (data.success) {
        const nextRequests = Array.isArray(data.data) ? data.data : [];
        const apiHistory = Array.isArray(data.history) ? data.history : [];
        const derivedHistory = buildHistoryFromRequests(nextRequests);

        const nextStatusMap = new Map();
        nextRequests.forEach((row) => {
          const id = String(row.id ?? row.request_id ?? "");
          const currentStatus = String(row.status || "");
          if (!id) {
            return;
          }

          const previousStatus = previousStatusRef.current.get(id);
          if (
            previousStatus &&
            previousStatus.toLowerCase() !== "issued" &&
            currentStatus.toLowerCase() === "issued"
          ) {
            notifyIssued(row);
          }

          nextStatusMap.set(id, currentStatus);
        });

        previousStatusRef.current = nextStatusMap;
        setRequests(nextRequests);
        setHistory(apiHistory.length > 0 ? apiHistory : derivedHistory);
      } else {
        setRequests([]);
        setHistory([]);
      }
    } catch {
      if (!options.silent) {
        setRequests([]);
        setHistory([]);
      }
    } finally {
      if (!options.silent) {
        setLoading(false);
      }
    }
  }, [notifyIssued, user]);

  useEffect(() => {
    if (user) {
      loadRequests();
    }
  }, [user, loadRequests]);

  useEffect(() => {
    if (!user) {
      return undefined;
    }

    if (typeof window !== "undefined" && "Notification" in window && Notification.permission === "default") {
      Notification.requestPermission().catch(() => {});
    }

    const intervalId = window.setInterval(() => {
      loadRequests("", { silent: true });
    }, 30000);

    return () => window.clearInterval(intervalId);
  }, [user, loadRequests]);

  useEffect(() => {
    if (submitted && successBannerRef.current) {
      successBannerRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }, [submitted]);

  const openProfileEditor = useCallback(() => {
    navigate('/doctor/profile');
  }, [navigate]);

  const handleLogout = () => {
    clearAuthSession();
    navigate("/login");
  };

  const autoCapitalizeName = (value) => {
    if (!value) return value;
    return value
      .split(' ')
      .map(word => word.length > 0 ? word.charAt(0).toUpperCase() + word.slice(1).toLowerCase() : word)
      .join(' ');
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    let newValue = value;
    
    // Auto-capitalize for name fields
    if ((name === "patientName" || name === "doctorName") && value) {
      newValue = autoCapitalizeName(value);
    }
    
    setForm((prev) => ({ ...prev, [name]: newValue }));
    setSubmitError("");
    setSubmitted(false);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitError("");

    let diagnosisValue = form.diagnosis;
    if (form.diagnosis === "Other") {
      diagnosisValue = (form.diagnosisOther || "").trim();
      if (!diagnosisValue) {
        setSubmitError("Please specify the diagnosis when selecting 'Other'.");
        return;
      }
    }

    if (!form.patientName || !form.hospitalName || !form.dateRequired || !diagnosisValue) {
      setSubmitError("Please fill all required fields: Patient Name, Hospital, Date/Time, and Diagnosis.");
      return;
    }

    try {
      const res = await authFetch("submit_blood_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...form,
          patientName: titleCase(form.patientName),
          doctorName: titleCase(form.doctorName),
          diagnosis: form.diagnosis === "Other" ? (form.diagnosisOther || "").trim() : form.diagnosis,
          patientGender: form.patientGender || null,
          units: Number(form.units) || 1,
        }),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        if (res.status === 409) {
          const code = data?.duplicateRequestCode ? ` (${data.duplicateRequestCode})` : "";
          throw new Error(`Patient already has an active request${code}.`);
        }
        throw new Error(data?.message || "Could not submit request.");
      }

      setSubmitted(true);
      setForm((prev) => ({
        ...INITIAL_FORM,
        doctorName: prev.doctorName,
        hospitalName: prev.hospitalName,
      }));
      await loadRequests(form.patientName);
    } catch (error) {
      const message = error.message || "Could not submit request.";
      setSubmitError(message);
      setModalMessage(message);
    }
  };

  if (!user) return null;

  const initials = user?.name
    ? user.name.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase()
    : "?";
  const docAvatarSrc = user?.profile_picture || getStoredUser()?.profile_picture || "";

  return (
    <DoctorShell
      user={user}
      onLogout={handleLogout}
      title="Doctor Dashboard"
      subtitle="Submit and track official hospital blood requests"
    >
    <div className="doctor-page">
        <section className="doctor-content">
          <section className="doctor-hero">
            <h1>Doctor / Nurse Dashboard</h1>
            <p>Submit official blood requests and track live processing status.</p>
          </section>

          <section className="doctor-layout">
            <article id="request-form" className="doctor-card">
            <div className="form-header">
              <div className="form-hospital-title">
                <p>BHUTAN BLOOD TRANSFUSION SERVICES</p>
                <h2>BLOOD REQUEST FORM</h2>
              </div>
            </div>

            {submitted && (
              <div className="success-banner" ref={successBannerRef}>
                Blood request submitted successfully. Staff have been notified.
              </div>
            )}
            {submitError && (
              <div className="bf-important">{submitError}</div>
            )}

            <form className="blood-form" onSubmit={handleSubmit}>
              <div className="bf-row bf-two">
                <div className="bf-field">
                  <label>Hospital *</label>
                  <input name="hospitalName" value={form.hospitalName} onChange={handleChange} required />
                </div>
                <div className="bf-field">
                  <label>Date / Time Required *</label>
                  <input type="datetime-local" name="dateRequired" value={form.dateRequired} onChange={handleChange} required />
                </div>
              </div>

              <div className="bf-section-title">Patient Details</div>
              <div className="bf-row bf-two">
                <div className="bf-field">
                  <label>Patient Name *</label>
                  <input name="patientName" value={form.patientName} onChange={handleChange} required />
                </div>
                <div className="bf-field">
                  <label>Hospital Reference No.</label>
                  <input name="patientRefNo" value={form.patientRefNo} onChange={handleChange} placeholder="MRN-000123" />
                </div>
              </div>

              <div className="bf-row bf-two">
                <div className="bf-field">
                  <label>Gender</label>
                  <select name="patientGender" value={form.patientGender} onChange={handleChange}>
                    <option value="">Unknown</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div className="bf-field">
                  <label>Ward</label>
                  <input name="ward" value={form.ward} onChange={handleChange} placeholder="ICU, Maternity, OT" />
                </div>
              </div>

              <div className="bf-row bf-two">
                <div className="bf-field">
                  <label>Blood Group</label>
                  <select name="bloodType" value={form.bloodType} onChange={handleChange}>
                    <option value="">Unknown</option>
                    <option>A+</option>
                    <option>A-</option>
                    <option>B+</option>
                    <option>B-</option>
                    <option>AB+</option>
                    <option>AB-</option>
                    <option>O+</option>
                    <option>O-</option>
                  </select>
                </div>
              </div>

              <div className="bf-section-title">Request</div>
              <div className="bf-row bf-two">
                <div className="bf-field">
                  <label>Component *</label>
                  <select name="component" value={form.component} onChange={handleChange}>
                    <option>Whole Blood</option>
                    <option>Packed Red Cells</option>
                    <option>Plasma</option>
                    <option>Platelets</option>
                    <option>Others</option>
                  </select>
                </div>
                <div className="bf-field">
                  <label>Units *</label>
                  <input type="number" min="1" name="units" value={form.units} onChange={handleChange} required />
                </div>
              </div>

              <div className="bf-row bf-two">
                <div className="bf-field">
                  <label>Urgency *</label>
                  <select name="urgency" value={form.urgency} onChange={handleChange}>
                    <option>Routine</option>
                    <option>Urgent</option>
                    <option>Critical</option>
                  </select>
                </div>
                <div className="bf-field">
                  <label>Doctor Name *</label>
                  <input name="doctorName" value={form.doctorName} onChange={handleChange} required />
                </div>
              </div>

              <div className="bf-field">
                <label>Diagnosis</label>
                <input name="diagnosis" value={form.diagnosis} onChange={handleChange} placeholder="e.g. Postpartum hemorrhage" />
              </div>

              <div className="bf-field">
                <label>Reason for Transfusion</label>
                <input name="reason" value={form.reason} onChange={handleChange} placeholder="e.g. Severe blood loss" />
              </div>

              <div className="bf-actions">
                <button type="button" className="bf-btn-ghost" onClick={() => setForm((prev) => ({ ...INITIAL_FORM, doctorName: prev.doctorName, hospitalName: prev.hospitalName }))}>Reset</button>
                <button type="submit" className="bf-btn-primary">Submit Official Request</button>
              </div>
            </form>
          </article>

          <article id="request-status" className="doctor-card">
            <h2>Request Status</h2>
            <div className="doctor-table-wrap">
              <table className="doctor-table">
                <thead>
                  <tr>
                    <th>Request ID</th>
                    <th>Patient</th>
                    <th>Gender</th>
                    <th>Type</th>
                    <th>Component</th>
                    <th>Units</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Requested At</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan="9">Loading...</td></tr>
                  ) : requests.length === 0 ? (
                    <tr><td colSpan="9">No requests submitted yet.</td></tr>
                  ) : requests.map((r) => (
                    <tr key={r.id}>
                      <td>{r.request_code || `REQ-${r.id}`}</td>
                      <td>{r.patient || r.patient_name}</td>
                      <td>{formatGender(r.patient_gender)}</td>
                      <td><span className="blood-tag">{r.blood_type || "-"}</span></td>
                      <td>{r.component}</td>
                      <td>{r.units || r.units_requested}</td>
                      <td><span className={"badge " + String(r.priority || r.urgency || "routine").toLowerCase()}>{r.priority || r.urgency}</span></td>
                      <td>{r.status}</td>
                      <td>{new Date(r.requested_at || r.created_at).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </article>

          <article className="doctor-card">
            <h2>Patient Transfusion History</h2>
            <div className="doctor-table-wrap">
              <table className="doctor-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Diagnosis</th>
                    <th>Transfusion</th>
                    <th>Outcome</th>
                  </tr>
                </thead>
                <tbody>
                  {history.length === 0 ? (
                    <tr><td colSpan="4">No patient transfusion history yet. Submit or search patient requests to populate this section.</td></tr>
                  ) : history.map((row, idx) => (
                    <tr key={idx}>
                      <td>{row.date}</td>
                      <td>{row.diagnosis || row.reason_for_transfusion || "-"}</td>
                      <td>{row.transfusion || "-"}</td>
                      <td>{row.outcome || "-"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </article>

          </section>
        </section>

        {modalMessage && (
          <div className="doctor-modal-overlay" role="dialog" aria-modal="true" aria-label="Request notice">
            <div className="doctor-modal">
              <h3>Cannot Submit Request</h3>
              <p>{modalMessage}</p>
              <div className="doctor-modal-actions">
                <button type="button" onClick={() => setModalMessage("")}>OK</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </DoctorShell>
  );
}
