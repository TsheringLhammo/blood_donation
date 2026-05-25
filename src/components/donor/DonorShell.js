import React, { useMemo, useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { clearAuthSession, getStoredUser } from "../../utils/auth";
import ConfirmDialog from "../ConfirmDialog";
import "./DonorShell.css";

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
  Bank: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M3 9 12 4l9 5" />
      <path d="M5 9v10M19 9v10M9 9v10M15 9v10M3 21h18" />
    </svg>
  ),
  Info: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v.01M11 12h1v5h1" />
    </svg>
  ),
  User: (p) => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" />
    </svg>
  ),
  Chat: (p) => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" {...p}>
      <path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z" />
    </svg>
  ),
  Menu: (p) => (
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" {...p}>
      <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  ),
  Bell: (p) => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
      <path d="M10 21a2 2 0 0 0 4 0" />
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

export default function DonorShell({ user, title, subtitle, children, onLogout }) {
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);

  const storedUser = useMemo(() => user || getStoredUser() || {}, [user]);
  const donorName = storedUser?.name || "Donor";
  const donorRoleLabel = storedUser?.role
    ? storedUser.role.charAt(0).toUpperCase() + storedUser.role.slice(1)
    : "Donor";
  const initials = donorName.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase() || "D";
  const avatarSrc = storedUser?.profile_picture || "";

  const navItems = useMemo(
    () => [
      { id: "overview", icon: "📊", label: "Overview", to: "/dashboard" },
      { id: "appointments", icon: "📅", label: "Appointments", to: "/dashboard#appointments" },
      { id: "history", icon: "🩸", label: "Donation History", to: "/dashboard#history" },
      { id: "book", icon: "➕", label: "Book Appointment", to: "/donating-blood" },
      { id: "blood-info", icon: "🧬", label: "Blood Information", to: "/about-blood" },
    ],
    []
  );

  const activeId = useMemo(() => {
    const path = location.pathname;
    const hash = location.hash || "";

    // Hash-anchored items (Appointments / Donation History) take priority
    // when their anchor is in the URL.
    if (path === "/dashboard" && hash) {
      const hashMatch = navItems.find((item) => item.to.endsWith(hash));
      if (hashMatch) return hashMatch.id;
    }

    // Pick the longest matching path (so "/dashboard" beats "/").
    const candidates = navItems
      .filter((item) => {
        const target = item.to.split("#")[0];
        if (target === "/") return path === "/";
        return path === target || path.startsWith(`${target}/`);
      })
      .sort((a, b) => b.to.length - a.to.length);

    return candidates[0]?.id || "";
  }, [location.pathname, location.hash, navItems]);

  const go = (to) => {
    const [path, hash] = to.split("#");
    navigate(hash ? { pathname: path, hash: `#${hash}` } : to);
    setSidebarOpen(false);
    // Defer scroll until the target element exists (next paint).
    setTimeout(() => {
      if (hash) {
        const el = document.getElementById(hash);
        if (el) {
          el.scrollIntoView({ behavior: "smooth", block: "start" });
          return;
        }
      }
      if (typeof window !== "undefined") window.scrollTo({ top: 0, behavior: "smooth" });
    }, 60);
  };

  const openProfile = () => {
    setSidebarOpen(false);
    navigate("/profile");
  };

  const requestLogout = () => {
    setSidebarOpen(false);
    setLogoutOpen(true);
  };

  const confirmLogout = () => {
    setLogoutOpen(false);
    if (typeof onLogout === "function") {
      onLogout();
    } else {
      clearAuthSession();
      navigate("/login", { replace: true });
    }
  };

  return (
    <div className="donor-shell">
      <aside className={`donor-shell-sidebar ${sidebarOpen ? "open" : ""}`}>
        <div className="donor-shell-brand">
          <div className="donor-shell-brand-logo" aria-hidden="true"><I.Drop /></div>
          <div className="donor-shell-brand-text">
            <div className="donor-shell-brand-title">Blood Transfusion Services</div>
            <div className="donor-shell-brand-subtitle">Bhutan Hospital System</div>
          </div>
        </div>

        <nav className="donor-shell-nav">
          {navItems.map((item) => (
            <button
              key={item.id}
              type="button"
              className={`donor-shell-nav-item${activeId === item.id ? " active" : ""}`}
              onClick={() => go(item.to)}
            >
              <span className="donor-shell-nav-icon">{item.icon}</span>
              <span className="donor-shell-nav-text">{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="donor-shell-support">
          <button
            type="button"
            className="donor-shell-support-card donor-shell-support-card-button"
            onClick={openProfile}
            title="Edit your profile"
          >
            <div className="donor-shell-support-icon"><I.User /></div>
            <div className="donor-shell-support-text">
              <strong>Edit Your Profile</strong>
              <span>Update your personal information</span>
            </div>
          </button>
          <div className="donor-shell-support-card">
            <div className="donor-shell-support-icon"><I.Chat /></div>
            <div className="donor-shell-support-text">
              <strong>Need Help?</strong>
              <span>Call 1095 — 24/7 Hotline</span>
            </div>
          </div>
          <button type="button" className="donor-shell-support-cta" onClick={requestLogout}>
            <I.Logout /> Logout
          </button>
        </div>
      </aside>

      <header className="donor-shell-header">
        <button
          className="donor-shell-menu"
          type="button"
          onClick={() => setSidebarOpen((v) => !v)}
          aria-label="Toggle menu"
        >
          <I.Menu />
        </button>
        <div className="donor-shell-titleblock">
          <h1>{title || "Donor Dashboard"}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        <div className="donor-shell-user-actions">
          <button type="button" className="donor-shell-bell" aria-label="Notifications" onClick={() => navigate("/dashboard")}>
            <I.Bell />
          </button>
          <button
            className="donor-shell-user-pill"
            type="button"
            onClick={openProfile}
            title="Edit profile"
          >
            <span className="donor-shell-avatar">
              {avatarSrc ? <img src={avatarSrc} alt={donorName} /> : initials}
            </span>
            <span className="donor-shell-user-meta">
              <strong>{donorName}</strong>
              <span>{donorRoleLabel}</span>
            </span>
          </button>
        </div>
      </header>

      <main className="donor-shell-main">
        <div className="donor-shell-content">{children}</div>
      </main>

      {sidebarOpen ? <div className="donor-shell-backdrop" onClick={() => setSidebarOpen(false)} /> : null}

      <ConfirmDialog
        isOpen={logoutOpen}
        title="Logout"
        message="Are you sure you want to logout?"
        confirmText="Logout"
        cancelText="Cancel"
        type="danger"
        onConfirm={confirmLogout}
        onCancel={() => setLogoutOpen(false)}
      />
    </div>
  );
}
