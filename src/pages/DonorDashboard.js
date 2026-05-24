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
import { useNavigate } from 'react-router-dom';
import { authFetch } from '../utils/auth';
import EditProfile from '../components/EditProfile';
import { maskCidNumber } from '../utils/strings';
import './DonorDashboard.css';

const DonorDashboard = () => {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [appointments, setAppointments] = useState([]);
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
        if (!res.ok) throw new Error('Failed to fetch profile');
        const data = await res.json();
        
        if (data.success && data.data) {
          console.log('🔍 DEBUG: Received profile from backend:', {
            id: data.data.id,
            status: data.data.status,
            workflow_status: data.data.workflow_status,
            deferred: data.data.deferred,
            deferred_until: data.data.deferred_until,
            deferral_reason: data.data.deferral_reason
          });
          setProfile(data.data);
        } else {
          setError('Could not load profile');
        }
      } catch (err) {
        setError(err.message);
      }
    };

    fetchProfile();
  }, []);

  // Fetch appointments
  useEffect(() => {
    if (!profile) return;

    const fetchAppointments = async () => {
      try {
        const res = await authFetch(`get_my_appointments.php?_ts=${Date.now()}`, {
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('Failed to fetch appointments');
        const data = await res.json();
        
        if (data.success && Array.isArray(data.data)) {
          setAppointments(data.data);
        }
      } catch (err) {
        console.error('Error fetching appointments:', err);
      }
    };

    fetchAppointments();
  }, [profile]);

  useEffect(() => {
    if (!profile) return;
    setLoading(false);
  }, [profile]);

  if (loading) {
    return <div className="dashboard-loading">Loading dashboard...</div>;
  }

  if (error || !profile) {
    return <div className="dashboard-error">Error: {error || 'Profile not found'}</div>;
  }

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
      additionalInfo: `Your deferral reason: ${profile.deferral_reason || 'Medical hold'}`,
      deferredUntil: profile.deferred_until
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

  const deferralDate = profile.deferred_until ? formatDate(profile.deferred_until) : null;
  const today = new Date();
  const deferralEnd = profile.deferred_until ? new Date(profile.deferred_until) : null;
  const daysRemaining = deferralEnd ? Math.ceil((deferralEnd - today) / (1000 * 60 * 60 * 24)) : null;

  const handleSaveProfile = (updatedProfile) => {
    setProfile(updatedProfile);
    setShowEditModal(false);
  };

  return (
    <div className="donor-dashboard">
      {/* STATUS BANNER - ONLY SHOW AFTER PROFILE LOADED */}
      {!loading && (
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
          <button className="btn btn-primary edit-profile-btn" onClick={() => setShowEditModal(true)}>
            Edit Profile
          </button>
        </div>
        <div className="profile-grid">
          <div className="profile-item">
            <label>Name</label>
            <value>{profile.full_name}</value>
          </div>
          <div className="profile-item">
            <label>Email</label>
            <value>{profile.email}</value>
          </div>
            <div className="profile-item">
              <label>CID Number</label>
              <value>{profile.cid_number_masked || maskCidNumber(profile.cid_number) || 'Not available'}</value>
            </div>
          <div className="profile-item">
            <label>Blood Type</label>
            <value>{profile.blood_type || 'Not specified'}</value>
          </div>
          <div className="profile-item">
            <label>Sample Test</label>
            <value>{profile.sample_tested || 'Pending'}</value>
          </div>
        </div>
      </div>

      {/* APPOINTMENTS */}
      {config.showAppointmentButton && appointments.length > 0 && (
        <div className="appointments-section">
          <h2>Your Appointments</h2>
          <div className="appointments-list">
            {appointments.map((apt) => (
              <div key={apt.id} className="appointment-card">
                <div className="appointment-date">
                  📅 {formatDate(apt.appointment_date)} at {apt.appointment_time}
                </div>
                <div className="appointment-location">
                  📍 {apt.blood_bank_name}
                </div>
                <div className={`appointment-status status-${apt.status.toLowerCase()}`}>
                  {apt.status}
                </div>
              </div>
            ))}
          </div>
          <button 
            className="btn btn-primary"
            onClick={() => navigate('/book-appointment')}
          >
            Schedule Another Appointment
          </button>
        </div>
      )}

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
  );
};

export default DonorDashboard;
