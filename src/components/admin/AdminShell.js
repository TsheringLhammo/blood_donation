import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
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

  const navItems = useMemo(
    () => [
      { id: "dashboard", icon: "📊", label: "Dashboard" },
      { id: "bloodBanks", icon: "🏥", label: "Blood Banks" },
      { id: "donors", icon: "🩸", label: "Donors" },
      { id: "appointments", icon: "📅", label: "Appointments" },
      { id: "camps", icon: "🏕️", label: "Camp Requests" },
    ],
    []
  );

  const switchView = (id) => {
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
      <aside className={`admin-shell-sidebar ${sidebarOpen ? "open" : ""}`}>
        <div className="admin-shell-brand">Blood Transfusion Admin</div>
        <nav className="admin-shell-nav">
          {navItems.map((item) => (
            <button
              key={item.id}
              className={`admin-shell-nav-item${activeView === item.id ? " active" : ""}`}
              onClick={() => switchView(item.id)}
              type="button"
            >
              <span>{item.icon}</span>
              {item.label}
            </button>
          ))}
        </nav>
      </aside>

      <div className="admin-shell-main">
        <header className="admin-shell-header">
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
            <span className="admin-shell-user-pill">{user?.name || "Admin"}</span>
            <button className="admin-shell-logout" type="button" onClick={onLogout}>
              Logout
            </button>
          </div>
        </header>

        <main className="admin-shell-content">{children}</main>
      </div>

      {sidebarOpen ? <div className="admin-shell-backdrop" onClick={() => setSidebarOpen(false)} /> : null}
    </div>
  );
}
