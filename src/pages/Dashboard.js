import React, { useEffect, useState, useCallback, useRef } from "react";
import { useNavigate, Link } from "react-router-dom";
import { toast } from "react-toastify";
import "./Dashboard.css";
import { authFetch, clearAuthSession, getStoredUser } from "../utils/auth";
import EditProfile from "../components/EditProfile";
import NotificationDropdown from "../components/NotificationDropdown";

export default function Dashboard() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [profile, setProfile] = useState(null);
  const [appointments, setAppointments] = useState(null);
  const [loading, setLoading] = useState(true);
  const [showEditModal, setShowEditModal] = useState(false);
  const profileRef = useRef(null);

  useEffect(() => {
    profileRef.current = profile;
  }, [profile]);

  useEffect(() => {
    const parsed = getStoredUser();
    console.log('Stored user:', parsed);
    
    if (!parsed?.token) { 
      console.log('No token found, redirecting to login');
      navigate("/login"); 
      return; 
    }
    if (parsed.role === "admin") { 
      console.log('Admin user, redirecting to admin');
      navigate("/admin"); 
      return; 
    }
    if (parsed.role !== "donor") { 
      console.log('Non-donor user, redirecting to login');
      navigate("/login"); 
      return; 
    }
    
    console.log('Donor user authenticated:', parsed.name);
    setUser(parsed);
  }, [navigate]);

  const fetchAppointments = useCallback(async () => {
    setLoading(true);
    try {
      const res = await authFetch("get_my_appointments.php?_ts=" + Date.now(), {
        cache: "no-store",
      });
      const data = await res.json();
      if (data.success) {
        setAppointments(data.data);
      } else {
        setAppointments([]);
      }
    } catch {
      setAppointments([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const fetchProfile = useCallback(async () => {
    try {
      const mergeProfile = (nextProfile) => {
        if (!nextProfile) return;
        setProfile(nextProfile);
      };

      // Primary endpoint using donor_id from JWT; fallback to legacy profile endpoint.
      const res = await authFetch("get_donor_profile.php?_ts=" + Date.now(), {
        cache: "no-store",
      });
      const data = await res.json();

      if (data.success) {
        mergeProfile(data.data);
        return;
      }

      const fallbackRes = await authFetch("get_my_profile.php?_ts=" + Date.now(), {
        cache: "no-store",
      });
      const fallbackData = await fallbackRes.json();
      if (fallbackData.success) {
        mergeProfile(fallbackData.data);
      } else if (!profileRef.current) {
        setProfile(null);
      }
    } catch {
      if (!profileRef.current) {
        setProfile(null);
      }
    }
  }, []);

  useEffect(() => {
    if (user) {
      fetchProfile();
      fetchAppointments();
    }
  }, [user, fetchAppointments, fetchProfile]);

  useEffect(() => {
    if (!user) return undefined;

    const refresh = () => {
      fetchProfile();
    };

    const intervalId = window.setInterval(refresh, 30000);
    window.addEventListener("focus", refresh);

    return () => {
      window.clearInterval(intervalId);
      window.removeEventListener("focus", refresh);
    };
  }, [user, fetchProfile]);

  const handleLogout = () => {
    clearAuthSession();
    navigate("/login");
  };

  const handleSaveProfile = (updatedProfile) => {
    setProfile(updatedProfile);
    setShowEditModal(false);
    toast.success("✅ Profile Updated Successfully!", {
      position: "top-right",
      autoClose: 3000,
      hideProgressBar: false,
      closeOnClick: true,
      pauseOnHover: true,
      draggable: true,
      progress: undefined,
      theme: "light",
    });
  };

  const handleBookAppointment = () => {
    navigate('/donating-blood?tab=book');
  };

  if (!user) return null;

  const normalize = (value) => String(value ?? "").trim().toLowerCase();
  const formatDate = (value) => {
    if (!value) return null;
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime())
      ? null
      : parsed.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
  };

  const initialApprovalStatus = normalize(profile?.initial_approval_status);
  const finalDecision = normalize(profile?.final_decision);
  const workflowStatus = normalize(profile?.workflow_status);
  const deferredUntil = profile?.defer_until_date || profile?.deferred_until || null;
  const deferredUntilLabel = formatDate(deferredUntil);

  const actualDonorStatus = (() => {
    const approvedToDonateStatuses = new Set(["approved_for_blood_draw", "approved_to_donate"]);
    const approvedDonorStatuses = new Set(["approved_donor", "decision_made_accepted", "active_donor"]);
    const testedNegativeStatuses = new Set(["tested_negative", "test_result_negative", "blood_donated"]);
    const temporarilyDeferredStatuses = new Set(["temp_defer", "deferred_until_date", "decision_made_deferred", "deferred", "temporarily_deferred"]);
    const permanentlyDeferredStatuses = new Set(["perm_defer", "permanently_deferred", "permanent_deferral", "permanently deferred"]);
    const statusValue = normalize(profile?.status);
    const sampleTestedValue = normalize(profile?.sample_tested);
    const hasCollectedSample = workflowStatus === "blood_drawn_pending_test" || sampleTestedValue === "collected";
    const hasPendingApprovedDonorState = statusValue === "approved to donate" && !["negative", "reactive"].includes(sampleTestedValue);

    if (permanentlyDeferredStatuses.has(finalDecision) || permanentlyDeferredStatuses.has(workflowStatus)) {
      return "permanently_deferred";
    }
    if (temporarilyDeferredStatuses.has(finalDecision) || temporarilyDeferredStatuses.has(workflowStatus)) {
      return "temporarily_deferred";
    }
    if (approvedDonorStatuses.has(finalDecision) || approvedDonorStatuses.has(workflowStatus)) {
      return "approved_donor";
    }
    if (workflowStatus === "test_result_pending_decision" || sampleTestedValue === "negative") {
      return "awaiting_review";
    }
    if (testedNegativeStatuses.has(finalDecision) || testedNegativeStatuses.has(workflowStatus)) {
      return "tested_negative";
    }
    if (hasCollectedSample || hasPendingApprovedDonorState || approvedToDonateStatuses.has(initialApprovalStatus) || approvedToDonateStatuses.has(finalDecision) || approvedToDonateStatuses.has(workflowStatus)) {
      return "awaiting_review";
    }
    return "awaiting_review";
  })();

  const donorStatus = actualDonorStatus;

  let bannerBadge = "⏳ Awaiting Review";
  let bannerMessage = "Your registration is pending admin approval.";
  let bannerColor = { badgeColor: "#856404", backgroundColor: "#fff8e1", borderColor: "#f0c36d" };
  let actionLabel = "Book Appointment";

  if (donorStatus === "approved_donor") {
    bannerBadge = "✅ Approved Donor";
    bannerMessage = "Your blood test results are negative and you are now an Approved Donor.";
    bannerColor = { badgeColor: "#1f7a1f", backgroundColor: "#e9f7ef", borderColor: "#63c174" };
    actionLabel = "Book Appointment";
  } else if (donorStatus === "tested_negative") {
    bannerBadge = "🧪 Tested - Negative";
    bannerMessage = "Your blood sample tested negative. Your final approval is being completed.";
    bannerColor = { badgeColor: "#1f4d7a", backgroundColor: "#e8f3ff", borderColor: "#68a5e6" };
    actionLabel = "Awaiting Final Approval";
  } else if (donorStatus === "temporarily_deferred") {
    bannerBadge = "⏸️ Temporarily Deferred";
    bannerMessage = `Your blood test results require temporary deferral. Please contact the blood bank for guidance. Next eligible date: ${deferredUntilLabel || "pending"}.`;
    bannerColor = { badgeColor: "#8a5a00", backgroundColor: "#fff6df", borderColor: "#d9a441" };
    actionLabel = "Contact Blood Bank";
  } else if (donorStatus === "permanently_deferred") {
    bannerBadge = "⛔ Permanently Deferred";
    bannerMessage = "Based on your test results, you cannot donate blood. Please contact the blood bank for support.";
    bannerColor = { badgeColor: "#8a1c1c", backgroundColor: "#fdeaea", borderColor: "#ef9a9a" };
    actionLabel = "Contact Blood Bank";
  } else {
    bannerBadge = "⏳ Awaiting Review";
    bannerMessage = "Your registration is pending admin approval. Please wait for the blood bank team to review your submission.";
    bannerColor = { badgeColor: "#856404", backgroundColor: "#fff8e1", borderColor: "#f0c36d" };
    actionLabel = "Pending Approval";
  }

  // Removed duplicate workflow label to prevent double status display
  const showStatusBanner = !loading && profile;

  const today = new Date().toISOString().split("T")[0];
  const upcoming  = appointments ? appointments.filter(a => a.preferred_date >= today && a.status !== "rejected") : [];
  const confirmed = appointments ? appointments.filter(a => a.status === "confirmed") : [];
  const totalCount = appointments ? appointments.length : 0;

  return (
    <div className="dash-page">
      <div className="dash-nav">
        <div className="dash-nav-inner">
          <div className="dash-nav-brand">
            <span>🩸</span>
            <span>Blood Transfusion Services</span>
          </div>
          <div className="dash-nav-right">
            <NotificationDropdown />
            <button className="dash-nav-user" onClick={() => navigate('/profile')} title="View profile" style={{background: 'transparent', border: 'none', cursor: 'pointer', marginRight: '12px', display: 'flex', alignItems: 'center', gap: '8px'}}>
              { (profile?.profile_picture || getStoredUser()?.profile_picture) ? (
                <img src={profile?.profile_picture || getStoredUser()?.profile_picture} alt={user.name} style={{width: 34, height: 34, borderRadius: 999, objectFit: 'cover', border: '2px solid rgba(255,255,255,0.12)'}} />
              ) : (
                <span>👤</span>
              )}
              <span>{user.name}</span>
            </button>
            <button className="dash-logout-btn" onClick={handleLogout}>Logout</button>
          </div>
        </div>
      </div>

      <div className="dash-body">
        <aside className="dash-sidebar">
          <nav className="dash-sidenav">
            <a href="#overview" className="dash-navitem active">📊 Overview</a>
            <a href="#appointments" className="dash-navitem">📅 Appointments</a>
            <a href="#history" className="dash-navitem">🩸 Donation History</a>
            <Link to="/donating-blood?tab=book" className="dash-navitem">➕ Book Appointment</Link>
            <Link to="/about-blood" className="dash-navitem">🧬 Blood Information</Link>
            <Link to="/blood-banks" className="dash-navitem">🏥 Blood Banks</Link>
            <Link to="/" className="dash-navitem">🏠 Home</Link>
          </nav>
        </aside>

        <main className="dash-main">
          {showStatusBanner && (
            <section className="dash-deferred-card" aria-live="polite" style={{ borderLeft: `4px solid ${bannerColor.borderColor}`, background: bannerColor.backgroundColor }}>
              <h2>Donation Status</h2>
              <p style={{ color: bannerColor.badgeColor, fontWeight: 700 }}>{bannerBadge}</p>
              <p>{bannerMessage}</p>
              {deferredUntilLabel && donorStatus === "temporarily_deferred" && (
                <p><strong>Next eligible date:</strong> {deferredUntilLabel}</p>
              )}
            </section>
          )}

          <div className="dash-welcome">
            <div>
              <h1>Welcome back, {user.name.split(" ")[0]}! 👋</h1>
              <p>Thank you for being a blood donor. Your donations save lives.</p>
            </div>
            <div className="dash-welcome-actions">
              <Link to="/about-blood" className="dash-book-btn dash-book-btn-secondary">View Complete Blood Information</Link>
              {donorStatus === "approved_donor" ? (
                <button className="dash-book-btn" onClick={handleBookAppointment}>+ {actionLabel}</button>
              ) : (donorStatus === "awaiting_review" || donorStatus === "temporarily_deferred" || donorStatus === "tested_negative" || donorStatus === "permanently_deferred") ? (
                <span className="dash-status-pill">Not Eligible Yet</span>
              ) : (
                <button
                  className="dash-book-btn"
                  type="button"
                  onClick={() => {
                    // No popup: always navigate to home when Contact is clicked
                    navigate('/');
                  }}
                >
                  {actionLabel}
                </button>
              )}
            </div>
          </div>

          <div className="dash-stats" id="overview">
            <div className="dash-stat-card red">
              <div className="dash-stat-icon">🩸</div>
              <div className="dash-stat-num">{loading ? "…" : totalCount}</div>
              <div className="dash-stat-label">Total Appointments</div>
            </div>
            <div className="dash-stat-card green">
              <div className="dash-stat-icon">✅</div>
              <div className="dash-stat-num">{loading ? "…" : confirmed.length}</div>
              <div className="dash-stat-label">Confirmed</div>
            </div>
            <div className="dash-stat-card blue">
              <div className="dash-stat-icon">📅</div>
              <div className="dash-stat-num">{loading ? "…" : upcoming.length}</div>
              <div className="dash-stat-label">Upcoming</div>
            </div>
          </div>

          <section className="dash-section" id="appointments">
            <h2 className="dash-section-title">Upcoming Appointments</h2>
            {loading ? (
              <div className="dash-empty">Loading…</div>
            ) : upcoming.length === 0 ? (
              <div className="dash-empty">
                No upcoming appointments. <Link to="/donating-blood?tab=book">Book one now →</Link>
              </div>
            ) : (
              <div className="dash-table-wrap">
                <table className="dash-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Blood Bank</th>
                      <th>Blood Group</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {upcoming.map((a) => (
                      <tr key={a.id}>
                        <td>{a.preferred_date}</td>
                        <td>{a.preferred_time ?? "—"}</td>
                        <td>{a.blood_bank}</td>
                        <td>{a.blood_group ? <span className="dash-blood-badge">{a.blood_group}</span> : "—"}</td>
                        <td>
                          <span className={`dash-badge ${a.status === "confirmed" ? "completed" : a.status === "rejected" ? "rejected" : "upcoming"}`}>
                            {a.status === "confirmed" ? "Confirmed" : a.status === "rejected" ? "Rejected" : "Pending"}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          <section className="dash-section" id="history">
            <h2 className="dash-section-title">Appointment History</h2>
            {loading ? (
              <div className="dash-empty">Loading…</div>
            ) : !appointments || appointments.length === 0 ? (
              <div className="dash-empty">No appointment history yet.</div>
            ) : (
              <div className="dash-table-wrap">
                <table className="dash-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Blood Bank</th>
                      <th>Blood Group</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {appointments.map((h) => (
                      <tr key={h.id}>
                        <td>{h.preferred_date}</td>
                        <td>{h.blood_bank}</td>
                        <td>{h.blood_group ? <span className="dash-blood-badge">{h.blood_group}</span> : "—"}</td>
                        <td>
                          <span className={`dash-badge ${h.status === "confirmed" ? "completed" : h.status === "rejected" ? "rejected" : "upcoming"}`}>
                            {h.status === "confirmed" ? "Confirmed" : h.status === "rejected" ? "Rejected" : "Pending"}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          <section className="dash-section">
            <h2 className="dash-section-title">Your Donor Card</h2>
            <div className="dash-donor-card">
              <div className="dash-donor-card-left">
                <div className="dash-donor-drop">🩸</div>
                <div>
                  <div className="dash-donor-label">BLOOD TRANSFUSION SERVICES</div>
                  <div className="dash-donor-name">{user.name}</div>
                  <div className="dash-donor-info">
                    {profile?.blood_type ? `Blood Type ${profile.blood_type}` : "Registered Voluntary Donor"}
                    {profile?.city ? ` • ${profile.city}` : ""}
                    {profile?.dzongkhag ? `, ${profile.dzongkhag}` : ""}
                  </div>
                </div>
              </div>
              <div className="dash-donor-card-right">
                <div className="dash-donor-stat">
                  <span className="dash-donor-stat-num">{loading ? "…" : totalCount}</span>
                  <span className="dash-donor-stat-label">Appointments</span>
                </div>
                <div className="dash-donor-stat">
                  <span className="dash-donor-stat-num">{loading ? "…" : confirmed.length}</span>
                  <span className="dash-donor-stat-label">Confirmed</span>
                </div>
              </div>
            </div>
          </section>

          {/* Contact modal removed - Contact button now navigates to home */}
        </main>
      </div>

      {/* EDIT PROFILE MODAL */}
      {showEditModal && (
        <EditProfile
          profile={profile}
          onSave={handleSaveProfile}
          onCancel={() => setShowEditModal(false)}
        />
      )}


    </div>
  );
}
