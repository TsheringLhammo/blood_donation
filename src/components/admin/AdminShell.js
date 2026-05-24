import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { authFetch } from "../../utils/auth";
import NotificationBar from "../NotificationBar";
import "./AdminShell.css";

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

  const navItems = useMemo(
    () => [
      { id: "dashboard", icon: "📊", label: "Dashboard" },
      { id: "bloodBanks", icon: "🏥", label: "Blood Banks" },
      { id: "donors", icon: "🩸", label: "Donors" },
      { id: "appointments", icon: "📅", label: "Appointments" },
      { id: "camps", icon: "🏕️", label: "Camp Requests" },
      { id: "donorRecords", icon: "📋", label: "Donor Records" },
      { id: "donationHistory", icon: "📜", label: "Donation History" },
      { id: "certificates", icon: "🏆", label: "Certificates" },
      { id: "donorCards", icon: "💳", label: "Donor Cards" },
    ],
    []
  );

  const switchView = (id) => {
    if (typeof window !== "undefined") {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    if (id === "donors") {
      navigate("/admin/donors");
      setSidebarOpen(false);
      return;
    }

    if (id === "dashboard") {
      navigate("/admin");
    }

    if (typeof onChangeView === "function") {
      onChangeView(id);
    }
    setSidebarOpen(false);
  };

  return (
    <div className="admin-shell">
      <header className="admin-shell-header">
        <div className="admin-shell-brand-block">
          <div className="admin-shell-brand-title">Blood Transfusion Admin</div>
          <div className="admin-shell-brand-subtitle">Bhutan hospital system</div>
        </div>
        <button
          className="admin-shell-menu"
          type="button"
          onClick={() => setSidebarOpen((v) => !v)}
          aria-label="Toggle admin menu"
        >
          ☰
        </button>
        <div>
          <h1>{title}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        <div className="admin-shell-user-actions">
          <NotificationBar
            role="admin"
            mode="compact"
            apiUrl="backend/api/get_admin_notifications.php?unread=1"
            onMarkAllRead={async () => {
              await authFetch("backend/api/mark_notifications_read.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({}),
              });
            }}
            onOpenNotifications={() => navigate("/admin")}
          />
          <button className="admin-shell-user-pill admin-shell-profile-button" type="button" onClick={() => navigate("/admin/profile")} title="Edit profile">
            <span className="admin-shell-avatar">
              {adminAvatarSrc ? <img src={adminAvatarSrc} alt={user?.name || "Admin"} /> : adminInitials}
            </span>
            <span>{user?.name || "Admin"}</span>
          </button>
          <button className="admin-shell-logout" type="button" onClick={onLogout}>
            Logout
          </button>
        </div>
      </header>

      <aside className={`admin-shell-sidebar ${sidebarOpen ? "open" : ""}`}>
        <nav className="admin-shell-nav">
          {navItems.map((item) => (
            <button
              key={item.id}
              className={`admin-shell-nav-item${activeView === item.id ? " active" : ""}`}
              onClick={() => switchView(item.id)}
              type="button"
            >
              <span>{item.icon}</span>
              <span className="admin-shell-nav-text">
                <span>{item.label}</span>
              </span>
            </button>
          ))}
        </nav>
      </aside>

      <div className="admin-shell-main">
        <main className="admin-shell-content">{children}</main>
      </div>

      {sidebarOpen ? <div className="admin-shell-backdrop" onClick={() => setSidebarOpen(false)} /> : null}
    </div>
  );
}
