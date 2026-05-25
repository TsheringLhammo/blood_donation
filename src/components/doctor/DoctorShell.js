import React, { useMemo, useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { clearAuthSession, getStoredUser } from "../../utils/auth";
import ConfirmDialog from "../ConfirmDialog";
import "./DoctorShell.css";

export default function DoctorShell({ user, title, subtitle, children, onLogout }) {
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);

  const storedUser = useMemo(() => user || getStoredUser() || {}, [user]);
  const doctorName = storedUser?.name || "Doctor";
  const doctorRoleLabel = storedUser?.role
    ? storedUser.role.charAt(0).toUpperCase() + storedUser.role.slice(1)
    : "Doctor";
  const initials = doctorName.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase() || "D";
  const avatarSrc = storedUser?.profile_picture || "";

  const navItems = useMemo(
    () => [
      { id: "dashboard", icon: "📊", label: "Dashboard", to: "/doctor" },
      { id: "requests", icon: "📝", label: "New Blood Request", to: "/doctor#request-form" },
      { id: "status", icon: "🕒", label: "Request Status", to: "/doctor#request-status" },
    ],
    []
  );

  const activeId = useMemo(() => {
    const path = location.pathname;
    const hash = location.hash || "";
    if (path === "/doctor" && hash) {
      const m = navItems.find((it) => it.to.endsWith(hash));
      if (m) return m.id;
    }
    const candidates = navItems
      .filter((it) => {
        const target = it.to.split("#")[0];
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
    navigate("/doctor/profile");
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
    <div className="doctor-shell">
      <aside className={`doctor-shell-sidebar ${sidebarOpen ? "open" : ""}`}>
        <div className="doctor-shell-brand">
          <div className="doctor-shell-brand-logo" aria-hidden="true">🩸</div>
          <div className="doctor-shell-brand-text">
            <div className="doctor-shell-brand-title">Hospital Blood Desk</div>
            <div className="doctor-shell-brand-subtitle">Doctor / Nurse Console</div>
          </div>
        </div>

        <nav className="doctor-shell-nav">
          {navItems.map((item) => (
            <button
              key={item.id}
              type="button"
              className={`doctor-shell-nav-item${activeId === item.id ? " active" : ""}`}
              onClick={() => go(item.to)}
            >
              <span className="doctor-shell-nav-icon">{item.icon}</span>
              <span className="doctor-shell-nav-text">{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="doctor-shell-support">
          <button
            type="button"
            className="doctor-shell-support-card doctor-shell-support-card-button"
            onClick={openProfile}
            title="Edit your profile"
          >
            <div className="doctor-shell-support-icon">👤</div>
            <div className="doctor-shell-support-text">
              <strong>Edit Your Profile</strong>
              <span>Update your personal information</span>
            </div>
          </button>
          <div className="doctor-shell-support-card">
            <div className="doctor-shell-support-icon">💬</div>
            <div className="doctor-shell-support-text">
              <strong>Need Help?</strong>
              <span>Contact Blood Bank Admin</span>
            </div>
          </div>
          <button type="button" className="doctor-shell-support-cta" onClick={requestLogout}>
            ⎋ Logout
          </button>
        </div>
      </aside>

      <header className="doctor-shell-header">
        <button
          className="doctor-shell-menu"
          type="button"
          onClick={() => setSidebarOpen((v) => !v)}
          aria-label="Toggle menu"
        >
          ☰
        </button>
        <div className="doctor-shell-titleblock">
          <h1>{title || "Doctor Dashboard"}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        <div className="doctor-shell-user-actions">
          <button
            className="doctor-shell-user-pill"
            type="button"
            onClick={openProfile}
            title="Edit profile"
          >
            <span className="doctor-shell-avatar">
              {avatarSrc ? <img src={avatarSrc} alt={doctorName} /> : initials}
            </span>
            <span className="doctor-shell-user-meta">
              <strong>{doctorName}</strong>
              <span>{doctorRoleLabel}</span>
            </span>
          </button>
        </div>
      </header>

      <main className="doctor-shell-main">
        <div className="doctor-shell-content">{children}</div>
      </main>

      {sidebarOpen ? <div className="doctor-shell-backdrop" onClick={() => setSidebarOpen(false)} /> : null}

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
