/**
 * DonorDashboard.js
 * 
 * PURPOSE:
 *   Donor dashboard showing registration status, deferral information,
 *   appointments, and next steps based on current status
 * 
 * STATUS DISPLAY:
 *   - Pending: "Awaiting approval - check email"
 *   - Confirmed: "Approved - you can book appointments"
 *   - Deferred: "Temporarily deferred until [DATE] - Reason: [TEST]"
 *   - Rejected: "Not approved - contact blood bank"
 */

import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { authFetch, clearAuthSession, getStoredUser } from '../utils/auth';
import EditProfile from '../components/EditProfile';
import DonorShell from '../components/donor/DonorShell';
import { maskCidNumber } from '../utils/strings';
import './DonorDashboard.css';

const DonorDashboard = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const [profile, setProfile] = useState(null);
  const [appointments, setAppointments] = useState([]);
  const [donationHistory, setDonationHistory] = useState([]);
  const [donationSummary, setDonationSummary] = useState({ total: 0, this_year: 0, units: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showEditModal, setShowEditModal] = useState(false);

  // Fetch donor profile
  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const res = await authFetch(`get_my_profile.php?_ts=${Date.now()}`, {
          cache: 'no-store'
        });
        if (res.status === 401 || res.status === 403) {
          clearAuthSession();
          navigate('/login', { replace: true });
          return;
        }
        if (res.status === 404) {
          // User is authenticated but has no donor record yet.
          setError('NO_DONOR_PROFILE');
          return;
        }
        if (!res.ok) {
          throw new Error(`Failed to fetch profile (HTTP ${res.status})`);
        }
        const data = await res.json();

        if (data.success && data.data) {
          setProfile(data.data);
        } else {
          setError(data.message || 'Could not load profile');
        }
      } catch (err) {
        setError(err.message || 'Could not load profile');
      } finally {
        setLoading(false);
      }
    };

    fetchProfile();
  }, [navigate]);

  // Fetch appointments (does not depend on tbldonors record — keyed by
  // tblusers.id from the JWT, so it works even when profile is missing).
  useEffect(() => {
    const fetchAppointments = async () => {
      try {
        const res = await authFetch(`get_my_appointments.php?_ts=${Date.now()}`, {
          cache: 'no-store'
        });
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && Array.isArray(data.data)) {
          setAppointments(data.data);
        }
      } catch (err) {
        console.error('Error fetching appointments:', err);
      }
    };

    fetchAppointments();
  }, []);

  // Smooth-scroll to the anchor whenever the hash changes AND the page is
  // ready. Retries briefly because sections render right after the
  // profile fetch resolves.
  useEffect(() => {
    if (loading) return undefined;
    const rawHash = (location.hash || '').replace(/^#/, '');
    if (!rawHash) return undefined;

    let attempt = 0;
    const tick = () => {
      const el = document.getElementById(rawHash);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      attempt += 1;
      if (attempt < 10) {
        setTimeout(tick, 100);
      }
    };
    tick();
    return undefined;
  }, [location.hash, loading]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await authFetch(`get_my_donation_history.php?_ts=${Date.now()}`, { cache: 'no-store' });
        if (!res.ok) return;
        const json = await res.json();
        if (cancelled || !json?.success) return;
        setDonationHistory(Array.isArray(json.data) ? json.data : []);
        setDonationSummary(json.summary || { total: 0, this_year: 0, units: 0 });
      } catch (_) {
        /* ignore — history is non-critical */
      }
    })();
    return () => { cancelled = true; };
  }, []);

  if (loading) {
    return (
      <DonorShell user={getStoredUser()} title="Donor Dashboard">
        <div className="donor-dashboard">
          <div className="dashboard-loading">Loading dashboard...</div>
        </div>
      </DonorShell>
    );
  }

  // If profile didn't load, fall back to a minimal placeholder so the rest
  // of the dashboard layout (sidebar, top bar, sections) still renders.
  const isMissingProfile = error === 'NO_DONOR_PROFILE';
  const profileMissingNotice = !profile ? (
    isMissingProfile
      ? "Your donor profile isn't linked yet. Complete registration to unlock the full dashboard."
      : (error || 'Unable to load profile right now.')
  ) : null;

  // ========================================
  // STATUS-SPECIFIC MESSAGE AND STYLING
  // ========================================
  // Use computed current_status from backend (which maps workflow_status correctly)
  const effectiveStatus = String(profile?.current_status || 'pending').toLowerCase();
  const sampleTested = String(profile?.sample_tested || 'Pending').toLowerCase();

  console.log('🔍 DEBUG: Using computed status from backend:', {
    current_status: profile?.current_status,
    workflow_status: profile?.workflow_status,
    status: profile?.status,
    effectiveStatus
  });
  
  const statusConfig = {
    pending: {
      badge: '⏳ Pending Approval',
      badgeColor: '#856404',
      backgroundColor: '#fff3cd',
      borderColor: '#ffc107',
      message: 'Your registration is under review.',
      action: 'Check your email for updates',
      actionColor: 'warning',
      showAppointmentButton: false,
      additionalInfo: 'We typically review applications within 24-48 hours.'
    },
    pending_review: {
      badge: '🕒 Awaiting Admin Review',
      badgeColor: '#8a5a00',
      backgroundColor: '#fff7e6',
      borderColor: '#e0a800',
      message: 'Your sample result has been recorded and is waiting for admin review.',
      action: 'Await Admin Review',
      actionColor: 'warning',
      showAppointmentButton: false,
      additionalInfo: 'Admin will review the test result and decide whether to accept or defer your case.'
    },
    confirmed: {
      badge: '✅ Eligible',
      badgeColor: '#155724',
      backgroundColor: '#d4edda',
      borderColor: '#28a745',
      message: sampleTested === 'negative'
        ? 'Great! Your sample test is negative and you are approved to donate.'
        : 'Your registration is approved, but you must complete a negative sample test before booking.',
      action: sampleTested === 'negative' ? 'Book an appointment' : 'Await sample testing',
      actionColor: 'success',
      showAppointmentButton: sampleTested === 'negative',
      additionalInfo: sampleTested === 'negative'
        ? 'You can help save lives by booking an appointment to donate blood.'
        : 'Visit the blood bank so staff can collect and test a small sample first.'
    },
    active: {
      badge: '✅ Eligible',
      badgeColor: '#155724',
      backgroundColor: '#d4edda',
      borderColor: '#28a745',
      message: sampleTested === 'negative'
        ? 'Great! Your sample test is negative and you are approved to donate.'
        : 'Your registration is approved, but you must complete a negative sample test before booking.',
      action: sampleTested === 'negative' ? 'Book an appointment' : 'Await sample testing',
      actionColor: 'success',
      showAppointmentButton: sampleTested === 'negative',
      additionalInfo: sampleTested === 'negative'
        ? 'You can help save lives by booking an appointment to donate blood.'
        : 'Visit the blood bank so staff can collect and test a small sample first.'
    },
    deferred: {
      badge: '⏸️ Temporarily Deferred',
      badgeColor: '#704214',
      backgroundColor: '#f0e5d8',
      borderColor: '#d2691e',
      message: 'Your donation was not suitable at this time.',
      action: 'Contact Blood Bank',
      actionColor: 'danger',
      showAppointmentButton: false,
      additionalInfo: `Your deferral reason: ${profile?.deferral_reason || 'Medical hold'}`,
      deferredUntil: profile?.deferred_until
    },
    rejected: {
      badge: '❌ Not Approved',
      badgeColor: '#721c24',
      backgroundColor: '#f8d7da',
      borderColor: '#dc3545',
      message: 'Your application was not approved.',
      action: 'Contact Blood Bank',
      actionColor: 'danger',
      showAppointmentButton: false,
      additionalInfo: 'Please contact the blood bank for more information about your application status.'
    }
  };

  const config = statusConfig[effectiveStatus] || statusConfig.pending;

  // Format deferral date for display
  const formatDate = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  };

  const deferralDate = profile?.deferred_until ? formatDate(profile.deferred_until) : null;
  const today = new Date();
  const deferralEnd = profile?.deferred_until ? new Date(profile.deferred_until) : null;
  const daysRemaining = deferralEnd ? Math.ceil((deferralEnd - today) / (1000 * 60 * 60 * 24)) : null;

  const handleSaveProfile = (updatedProfile) => {
    setProfile(updatedProfile);
    setShowEditModal(false);
  };

  const handleLogout = () => {
    clearAuthSession();
    navigate('/login', { replace: true });
  };

  const shellUser = getStoredUser();
  const subtitleText = profile?.full_name
    ? `Welcome back, ${profile.full_name}`
    : 'Track your status, appointments, and updates';

  return (
    <DonorShell user={shellUser} onLogout={handleLogout} title="Donor Dashboard" subtitle={subtitleText}>
    <div className="donor-dashboard">
      {profileMissingNotice ? (
        <div className="status-banner" style={{ borderLeft: '4px solid #c2410c', background: '#fff7ed' }}>
          <div className="status-header">
            <span className="status-badge" style={{ color: '#c2410c', background: '#fff', borderColor: '#c2410c' }}>
              ⚠ Profile not linked
            </span>
          </div>
          <p className="status-message" style={{ color: '#7c2d12' }}>{profileMissingNotice}</p>
          {isMissingProfile ? (
            <button
              type="button"
              className="btn btn-primary"
              style={{ marginTop: 10 }}
              onClick={() => navigate('/register')}
            >
              Complete Donor Registration
            </button>
          ) : null}
        </div>
      ) : null}
      {/* STATUS BANNER - ONLY SHOW AFTER PROFILE LOADED */}
      {!loading && profile && (
      <div 
        className="status-banner"
        style={{
          backgroundColor: config.backgroundColor,
          borderColor: config.borderColor,
          borderLeft: `4px solid ${config.borderColor}`
        }}
      >
        <div className="status-header">
          <span 
            className="status-badge"
            style={{ color: config.badgeColor }}
          >
            {config.badge}
          </span>
        </div>
        
        <p className="status-message">{config.message}</p>

        {/* DEFERRAL SPECIFIC INFO */}
        {effectiveStatus === 'deferred' && deferralDate && (
          <div className="deferral-details">
            <p className="deferral-date">
              Can reapply on: <strong>{deferralDate}</strong>
            </p>
            {daysRemaining > 0 && (
              <p className="deferral-countdown">
                ({daysRemaining} days remaining)
              </p>
            )}
            <p className="deferral-reason">
              Reason: <strong>{config.additionalInfo.replace('Your deferral reason: ', '')}</strong>
            </p>
          </div>
        )}

        {/* GENERAL ADDITIONAL INFO */}
        {effectiveStatus !== 'deferred' && config.additionalInfo && (
          <p className="status-info">{config.additionalInfo}</p>
        )}

        {/* ACTION BUTTON */}
        <button 
          className={`status-action-btn btn btn-${config.actionColor}`}
          onClick={() => {
            if (config.showAppointmentButton) {
              navigate('/book-appointment');
            } else {
              window.open('tel:1095');
            }
          }}
        >
          {config.action}
        </button>
      </div>
      )}

      {/* PROFILE SUMMARY */}
      <div className="profile-section">
        <div className="profile-header">
          <h2>Your Profile</h2>
          <button
            className="btn btn-primary edit-profile-btn"
            onClick={() => setShowEditModal(true)}
            disabled={!profile}
            title={profile ? 'Edit profile' : 'Complete registration first'}
          >
            Edit Profile
          </button>
        </div>
        <div className="profile-grid">
          <div className="profile-item">
            <label>Name</label>
            <value>{profile?.full_name || (shellUser?.name || '—')}</value>
          </div>
          <div className="profile-item">
            <label>Email</label>
            <value>{profile?.email || (shellUser?.email || '—')}</value>
          </div>
            <div className="profile-item">
              <label>CID Number</label>
              <value>{profile?.cid_number_masked || maskCidNumber(profile?.cid_number) || 'Not available'}</value>
            </div>
          <div className="profile-item">
            <label>Blood Type</label>
            <value>{profile?.blood_type || 'Not specified'}</value>
          </div>
          <div className="profile-item">
            <label>Sample Test</label>
            <value>{profile?.sample_tested || 'Pending'}</value>
          </div>
        </div>
      </div>

      {/* APPOINTMENTS */}
      <div id="appointments" className="appointments-section">
        <h2>Your Appointments</h2>
        {appointments.length === 0 ? (
          <p className="dh-empty-text">No appointments yet.</p>
        ) : (
          <div className="appointments-list">
            {appointments.map((apt) => {
              const aptDate = apt.appointment_date || apt.preferred_date;
              const aptTime = apt.appointment_time || apt.preferred_time;
              const aptBank = apt.blood_bank_name || apt.blood_bank;
              const aptStatus = String(apt.status || 'pending').toLowerCase();
              return (
                <div key={apt.id} className="appointment-card">
                  <div className="appointment-date">
                    📅 {formatDate(aptDate)}{aptTime ? ` at ${aptTime}` : ''}
                  </div>
                  <div className="appointment-location">
                    📍 {aptBank || '—'}
                  </div>
                  <div className={`appointment-status status-${aptStatus}`}>
                    {aptStatus}
                  </div>
                </div>
              );
            })}
          </div>
        )}
        {config.showAppointmentButton ? (
          <button
            className="btn btn-primary"
            onClick={() => navigate('/donating-blood')}
            style={{ marginTop: 12 }}
          >
            {appointments.length === 0 ? 'Book Appointment' : 'Schedule Another Appointment'}
          </button>
        ) : null}
      </div>

      {/* DONATION HISTORY */}
      <div id="history" className="appointments-section">
        <h2>Donation History</h2>
        <div className="dh-mini-stats">
          <div className="dh-mini-stat"><span>Total Donations</span><strong>{donationSummary.total}</strong></div>
          <div className="dh-mini-stat"><span>This Year</span><strong>{donationSummary.this_year}</strong></div>
          <div className="dh-mini-stat"><span>Units Donated</span><strong>{donationSummary.units}</strong></div>
        </div>
        {donationHistory.length === 0 ? (
          <p className="dh-empty-text">No completed donations yet. Once you donate, the record will appear here.</p>
        ) : (
          <div className="dh-mini-table-wrap">
            <table className="dh-mini-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Blood Bank</th>
                  <th>Component</th>
                  <th>Units</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {donationHistory.map((row) => (
                  <tr key={row.id}>
                    <td>{formatDate(row.donation_date)}</td>
                    <td>{row.blood_bank_name || `Bank #${row.blood_bank_id}`}</td>
                    <td>{row.component || 'Whole Blood'}</td>
                    <td>{row.units_collected}</td>
                    <td><span className="appointment-status status-completed">{row.status}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* HELP SECTION */}
      <div className="help-section">
        <h2>Need Help?</h2>
        <div className="help-content">
          <p><strong>📞 Call Us:</strong> 1095 (24/7 Hotline)</p>
          <p><strong>📧 Email Us:</strong> donors@bloodbank.bt</p>
          <p><strong>🏥 Visit Us:</strong> Blood Bank Office, Ministry of Health</p>
        </div>
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
    </DonorShell>
  );
};

export default DonorDashboard;
