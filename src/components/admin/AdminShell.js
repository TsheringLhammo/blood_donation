import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { authFetch } from "../../utils/auth";
import NotificationBar from "../NotificationBar";
import ConfirmDialog from "../ConfirmDialog";
import "./AdminShell.css";

/* ---------- Icons ---------- */
const I = {
  Dashboard: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <rect x="3" y="3" width="7" height="9" rx="1.5" />
      <rect x="14" y="3" width="7" height="5" rx="1.5" />
      <rect x="14" y="12" width="7" height="9" rx="1.5" />
      <rect x="3" y="16" width="7" height="5" rx="1.5" />
    </svg>
  ),
  Drop: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" {...p}>
      <path d="M12 2.5c-2.6 3.6-6.8 8.2-6.8 12.2A6.8 6.8 0 0 0 12 21.5a6.8 6.8 0 0 0 6.8-6.8c0-4-4.2-8.6-6.8-12.2z" />
    </svg>
  ),
  Calendar: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <rect x="3" y="5" width="18" height="16" rx="2" />
      <path d="M8 3v4M16 3v4M3 10h18" />
    </svg>
  ),
  Tent: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M12 3 3 20h18L12 3z" />
      <path d="M12 3v17M9 20l3-5 3 5" />
    </svg>
  ),
  List: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <rect x="4" y="3" width="16" height="18" rx="2" />
      <path d="M8 8h8M8 12h8M8 16h5" />
    </svg>
  ),
  Scroll: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M4 5a2 2 0 0 1 2-2h12v15H6a2 2 0 0 0-2 2V5z" />
      <path d="M8 8h7M8 12h7" />
    </svg>
  ),
  Bank: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M3 9 12 4l9 5" />
      <path d="M5 9v10M19 9v10M9 9v10M15 9v10M3 21h18" />
    </svg>
  ),
  Award: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <circle cx="12" cy="9" r="6" />
      <path d="m9 13-2 8 5-3 5 3-2-8" />
    </svg>
  ),
  Card: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M3 10h18M7 15h4" />
    </svg>
  ),
  Menu: (p) => (
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" {...p}>
      <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  ),
  Chat: (p) => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" {...p}>
      <path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z" />
    </svg>
  ),
  ChevronDown: (p) => (
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="m6 9 6 6 6-6" />
    </svg>
  ),
  User: (p) => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" />
    </svg>
  ),
  Logout: (p) => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <path d="m16 17 5-5-5-5" />
      <path d="M21 12H9" />
    </svg>
  ),
};

export default function AdminShell({
  user,
  onLogout,
  activeView,
  onChangeView,
  title,
  subtitle,
  children,
}) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [logoutConfirmOpen, setLogoutConfirmOpen] = useState(false);
  const navigate = useNavigate();
  const adminProfileFromStorage = useMemo(() => {
    if (typeof window === "undefined") return null;
    try {
      const raw = window.localStorage.getItem("dev_admin_profile");
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }, []);
  const adminAvatarSrc = user?.profile_picture || adminProfileFromStorage?.profile_picture || "";
  const adminInitials = user?.name
    ? user.name.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase()
    : "A";
  const adminRoleLabel = user?.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : "Administrator";

  const navItems = useMemo(
    () => [
      { id: "dashboard", icon: <I.Dashboard />, label: "Dashboard" },
      { id: "donors", icon: <I.Drop />, label: "Donors" },
      { id: "appointments", icon: <I.Calendar />, label: "Appointments" },
      { id: "donorRecords", icon: <I.List />, label: "Donor Records" },
      { id: "donationHistory", icon: <I.Scroll />, label: "Donation History" },
      { id: "bloodBanks", icon: <I.Bank />, label: "Blood Banks" },
      { id: "camps", icon: <I.Tent />, label: "Camp Requests" },
      { id: "certificates", icon: <I.Award />, label: "Certificates" },
      { id: "donorCards", icon: <I.Card />, label: "Donor Cards" },
    ],
    []
  );

  const switchView = (id) => {
    if (typeof window !== "undefined") {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    navigate("/admin", { state: { tab: id, ts: Date.now() } });

    if (typeof onChangeView === "function") {
      onChangeView(id);
    }
    setSidebarOpen(false);
  };

  const openProfile = () => {
    setSidebarOpen(false);
    navigate("/admin/profile");
  };

  const requestLogout = () => {
    setSidebarOpen(false);
    setLogoutConfirmOpen(true);
  };

  const confirmLogout = () => {
    setLogoutConfirmOpen(false);
    if (typeof onLogout === "function") onLogout();
  };

  return (
    <div className="admin-shell">
      <aside className={`admin-shell-sidebar ${sidebarOpen ? "open" : ""}`}>
        <div className="admin-shell-brand">
          <div className="admin-shell-brand-logo" aria-hidden="true">
            <I.Drop />
          </div>
          <div className="admin-shell-brand-text">
            <div className="admin-shell-brand-title">Blood Transfusion Services</div>
            <div className="admin-shell-brand-subtitle">Bhutan Hospital System</div>
          </div>
        </div>

        <nav className="admin-shell-nav">
          {navItems.map((item) => (
            <button
              key={item.id}
              className={`admin-shell-nav-item${activeView === item.id ? " active" : ""}`}
              onClick={() => switchView(item.id)}
              type="button"
            >
              <span className="admin-shell-nav-icon">{item.icon}</span>
              <span className="admin-shell-nav-text">{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="admin-shell-support">
          <button
            type="button"
            className="admin-shell-support-card admin-shell-support-card-button"
            onClick={openProfile}
            title="Edit your profile"
          >
            <div className="admin-shell-support-icon"><I.User /></div>
            <div className="admin-shell-support-text">
              <strong>Edit Your Profile</strong>
              <span>Update your personal information</span>
            </div>
          </button>
          <div className="admin-shell-support-card">
            <div className="admin-shell-support-icon"><I.Chat /></div>
            <div className="admin-shell-support-text">
              <strong>Need Help?</strong>
              <span>Contact Support</span>
            </div>
          </div>
          <button type="button" className="admin-shell-support-cta" onClick={requestLogout}>
            <I.Logout /> Logout
          </button>
        </div>
      </aside>

      <header className="admin-shell-header">
        <button
          className="admin-shell-menu"
          type="button"
          onClick={() => setSidebarOpen((v) => !v)}
          aria-label="Toggle admin menu"
        >
          <I.Menu />
        </button>
        <div className="admin-shell-titleblock">
          <h1>{title}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        <div className="admin-shell-user-actions">
          <NotificationBar
            role="admin"
            mode="compact"
            apiUrl="backend/api/get_admin_notifications.php"
            onMarkAllRead={async () => {
              await authFetch("backend/api/mark_notifications_read.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({}),
              });
            }}
          />
          <button
            className="admin-shell-user-pill"
            type="button"
            onClick={openProfile}
            title="Edit profile"
          >
            <span className="admin-shell-avatar">
              {adminAvatarSrc ? <img src={adminAvatarSrc} alt={user?.name || "Admin"} /> : adminInitials}
            </span>
            <span className="admin-shell-user-meta">
              <strong>{user?.name || "Admin"}</strong>
              <span>{adminRoleLabel}</span>
            </span>
          </button>
        </div>
      </header>

      <main className="admin-shell-main">
        <div className="admin-shell-content">{children}</div>
      </main>

      {sidebarOpen ? <div className="admin-shell-backdrop" onClick={() => setSidebarOpen(false)} /> : null}

      <ConfirmDialog
        isOpen={logoutConfirmOpen}
        title="Logout"
        message="Are you sure you want to logout?"
        confirmText="Logout"
        cancelText="Cancel"
        type="danger"
        onConfirm={confirmLogout}
        onCancel={() => setLogoutConfirmOpen(false)}
      />
    </div>
  );
}
