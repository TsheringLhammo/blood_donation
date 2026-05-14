import React, { useEffect, useState, useCallback } from "react";
/* eslint-disable no-unused-vars, react-hooks/exhaustive-deps */
import { Link, useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import "./AdminDashboardRestored.css";
import { authFetch, clearAuthSession, getStoredUser } from "../utils/auth";
import ConfirmDialog from "../components/ConfirmDialog";

export default function AdminDashboardRestored() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [stats, setStats] = useState({
    totalDonors: 0,
    upcomingAppointments: 0,
    lowStockAlerts: 0,
    campRequests: 0
  });
  const [appointments, setAppointments] = useState([]);
  const [campRequests, setCampRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);
  const [confirmAction, setConfirmAction] = useState(null);
  const [selectedItem, setSelectedItem] = useState(null);

  // Authentication check
  useEffect(() => {
    const parsed = getStoredUser();
    if (!parsed?.token) { 
      navigate("/login"); 
      return; 
    }
    if (parsed.role !== "admin") { 
      navigate("/login"); 
      return; 
    }
    setUser(parsed);
  }, [navigate]);

  // Fetch dashboard stats
  const fetchStats = useCallback(async () => {
    try {
      const res = await authFetch("get_admin_stats.php?_ts=" + Date.now(), {
        cache: "no-store",
      });
      const data = await res.json();
      if (data.success) {
        setStats(data.data);
      }
    } catch (error) {
      console.error("Failed to fetch stats:", error);
    }
  }, []);

  // Fetch appointments
  const fetchAppointments = useCallback(async () => {
    try {
      const res = await authFetch("get_admin_appointments.php?_ts=" + Date.now(), {
        cache: "no-store",
      });
      const data = await res.json();
      if (data.success) {
        setAppointments(data.data || []);
      }
    } catch (error) {
      console.error("Failed to fetch appointments:", error);
    }
  }, []);

  // Fetch camp requests
  const fetchCampRequests = useCallback(async () => {
    try {
      const res = await authFetch("get_camp_requests.php?_ts=" + Date.now(), {
        cache: "no-store",
      });
      const data = await res.json();
      if (data.success) {
        setCampRequests(data.data || []);
      }
    } catch (error) {
      console.error("Failed to fetch camp requests:", error);
    }
  }, []);

  // Load all data
  useEffect(() => {
    if (user) {
      setLoading(true);
      Promise.all([
        fetchStats(),
        fetchAppointments(),
        fetchCampRequests()
      ]).finally(() => setLoading(false));
    }
  }, [user, fetchStats, fetchAppointments, fetchCampRequests]);

  // Handle appointment actions
  const handleAcceptAppointment = (appointment) => {
    setSelectedItem(appointment);
    setConfirmAction('accept-appointment');
    setShowConfirmDialog(true);
  };

  const handleRejectAppointment = (appointment) => {
    setSelectedItem(appointment);
    setConfirmAction('reject-appointment');
    setShowConfirmDialog(true);
  };

  const handleViewAppointment = (appointment) => {
    // Navigate to appointment details or show modal
    toast.info(`Viewing details for ${appointment.full_name}`, {
      position: "top-right",
    });
  };

  // Handle camp request actions
  const handleAcceptCamp = (camp) => {
    setSelectedItem(camp);
    setConfirmAction('accept-camp');
    setShowConfirmDialog(true);
  };

  const handleRejectCamp = (camp) => {
    setSelectedItem(camp);
    setConfirmAction('reject-camp');
    setShowConfirmDialog(true);
  };

  const handleViewCamp = (camp) => {
    toast.info(`Viewing details for ${camp.organization}`, {
      position: "top-right",
    });
  };

  // Confirm actions
  const handleConfirmAction = async () => {
    setShowConfirmDialog(false);
    
    try {
      let endpoint = '';
      let successMessage = '';
      
      if (confirmAction === 'accept-appointment') {
        endpoint = 'accept_appointment.php';
        successMessage = 'Appointment confirmed successfully';
      } else if (confirmAction === 'reject-appointment') {
        endpoint = 'reject_appointment.php';
        successMessage = 'Appointment rejected successfully';
      } else if (confirmAction === 'accept-camp') {
        endpoint = 'accept_camp_request.php';
        successMessage = 'Camp request accepted successfully';
      } else if (confirmAction === 'reject-camp') {
        endpoint = 'reject_camp_request.php';
        successMessage = 'Camp request rejected successfully';
      }

      const res = await authFetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: selectedItem.id })
      });

      const data = await res.json();
      if (data.success) {
        toast.success(successMessage, {
          position: "top-right",
          autoClose: 3000,
        });
        
        // Refresh data
        fetchAppointments();
        fetchCampRequests();
        fetchStats();
      } else {
        throw new Error(data.message || 'Action failed');
      }
    } catch (error) {
      toast.error(error.message, {
        position: "top-right",
      });
    }
  };

  const handleLogout = () => {
    clearAuthSession();
    navigate("/login");
  };

  if (loading) {
    return (
      <div className="admin-loading">
        <div className="spinner"></div>
        <p>Loading dashboard...</p>
      </div>
    );
  }

  return (
    <div className="admin-dashboard">
      {/* Header */}
      <header className="admin-header">
        <div className="admin-header-left">
          <div className="admin-logo">
            <span className="logo-icon">🩸</span>
            <span className="logo-text">Blood Transfusion Services</span>
          </div>
          <h1 className="admin-title">Admin Dashboard</h1>
        </div>
        <div className="admin-header-right">
          <div className="admin-profile">
            <span className="admin-name">{user?.name}</span>
            <button className="btn btn-outline btn-sm">Admin BTS</button>
          </div>
          <button className="btn btn-danger" onClick={handleLogout}>
            Logout
          </button>
        </div>
      </header>

      <div className="admin-content">
        {/* Sidebar */}
        <aside className="admin-sidebar">
          <nav className="admin-nav">
            <Link to="/admin" className="admin-nav-item active">
              📊 Dashboard
            </Link>
            <Link to="/admin/blood-banks" className="admin-nav-item">
              🏥 Blood Banks
            </Link>
            <Link to="/admin/donors" className="admin-nav-item">
              👥 Donors
            </Link>
            <Link to="/admin/appointments" className="admin-nav-item">
              📅 Appointments
            </Link>
            <Link to="/admin/camp-requests" className="admin-nav-item">
              🏕️ Camp Requests
            </Link>
          </nav>
        </aside>

        {/* Main Content */}
        <main className="admin-main">
          {/* Metric Cards */}
          <div className="admin-metrics">
            <div className="metric-card">
              <div className="metric-icon donors">👥</div>
              <div className="metric-content">
                <div className="metric-value">{stats.totalDonors}</div>
                <div className="metric-label">Total Donors</div>
                <div className="metric-sublabel">Registered Donors</div>
              </div>
            </div>

            <div className="metric-card">
              <div className="metric-icon upcoming">📅</div>
              <div className="metric-content">
                <div className="metric-value">{stats.upcomingAppointments}</div>
                <div className="metric-label">Upcoming</div>
                <div className="metric-sublabel">Next 5 Scheduled</div>
              </div>
            </div>

            <div className="metric-card">
              <div className="metric-icon alerts">⚠️</div>
              <div className="metric-content">
                <div className="metric-value">{stats.lowStockAlerts}</div>
                <div className="metric-label">Low Stock</div>
                <div className="metric-sublabel">Alerts</div>
              </div>
            </div>

            <div className="metric-card">
              <div className="metric-icon camps">🏕️</div>
              <div className="metric-content">
                <div className="metric-value">{stats.campRequests}</div>
                <div className="metric-label">Camp Requests</div>
                <div className="metric-sublabel">All time</div>
              </div>
            </div>
          </div>

          {/* Appointments Table */}
          <section className="admin-section">
            <h2 className="section-title">Appointments</h2>
            <div className="table-container">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th>Full Name</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Blood Bank</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {appointments.map((appointment) => (
                    <tr key={appointment.id}>
                      <td>{appointment.full_name}</td>
                      <td>{appointment.date}</td>
                      <td>{appointment.time}</td>
                      <td>{appointment.blood_bank}</td>
                      <td>
                        <span className={`status-badge ${appointment.status}`}>
                          {appointment.status}
                        </span>
                      </td>
                      <td>
                        <div className="action-buttons">
                          {appointment.status === 'pending' && (
                            <>
                              <button
                                className="btn btn-success btn-sm"
                                onClick={() => handleAcceptAppointment(appointment)}
                              >
                                Accept
                              </button>
                              <button
                                className="btn btn-danger btn-sm"
                                onClick={() => handleRejectAppointment(appointment)}
                              >
                                Reject
                              </button>
                            </>
                          )}
                          <button
                            className="btn btn-primary btn-sm"
                            onClick={() => handleViewAppointment(appointment)}
                          >
                            View
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {/* Camp Requests Table */}
          <section className="admin-section">
            <h2 className="section-title">Camp Requests</h2>
            <div className="table-container">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th>Organization</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {campRequests.map((camp) => (
                    <tr key={camp.id}>
                      <td>{camp.organization}</td>
                      <td>{camp.date}</td>
                      <td>
                        <span className={`status-badge ${camp.status}`}>
                          {camp.status}
                        </span>
                      </td>
                      <td>
                        <div className="action-buttons">
                          {camp.status === 'pending' && (
                            <>
                              <button
                                className="btn btn-success btn-sm"
                                onClick={() => handleAcceptCamp(camp)}
                              >
                                Accept
                              </button>
                              <button
                                className="btn btn-danger btn-sm"
                                onClick={() => handleRejectCamp(camp)}
                              >
                                Reject
                              </button>
                            </>
                          )}
                          <button
                            className="btn btn-primary btn-sm"
                            onClick={() => handleViewCamp(camp)}
                          >
                            View
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </main>
      </div>

      {/* Confirmation Dialog */}
      {showConfirmDialog && (
        <ConfirmDialog
          isOpen={showConfirmDialog}
          title={
            confirmAction === 'accept-appointment' ? 'Confirm Appointment' :
            confirmAction === 'reject-appointment' ? 'Reject Appointment' :
            confirmAction === 'accept-camp' ? 'Accept Camp Request' :
            confirmAction === 'reject-camp' ? 'Reject Camp Request' :
            'Confirm Action'
          }
          message={
            confirmAction === 'accept-appointment' ? `Confirm appointment for ${selectedItem?.full_name}?` :
            confirmAction === 'reject-appointment' ? `Reject appointment for ${selectedItem?.full_name}?` :
            confirmAction === 'accept-camp' ? `Accept camp request from ${selectedItem?.organization}?` :
            confirmAction === 'reject-camp' ? `Reject camp request from ${selectedItem?.organization}?` :
            'Are you sure you want to proceed?'
          }
          confirmText={
            confirmAction === 'accept-appointment' ? 'Accept' :
            confirmAction === 'reject-appointment' ? 'Reject' :
            confirmAction === 'accept-camp' ? 'Accept' :
            confirmAction === 'reject-camp' ? 'Reject' :
            'Confirm'
          }
          cancelText="Cancel"
          onConfirm={handleConfirmAction}
          onCancel={() => setShowConfirmDialog(false)}
          type={
            confirmAction?.includes('reject') ? 'danger' : 'primary'
          }
        />
      )}
    </div>
  );
}
