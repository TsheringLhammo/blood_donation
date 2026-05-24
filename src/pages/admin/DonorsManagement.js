import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import "../AdminDashboard.css";
import AdminShell from "../../components/admin/AdminShell";
import DonorRecordsPanel from "../../components/admin/DonorRecordsPanel";
import { clearAuthSession, getStoredUser } from "../../utils/auth";

export default function DonorsManagement() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);

  useEffect(() => {
    const stored = getStoredUser();
    if (!stored?.token) {
      clearAuthSession();
      navigate("/login", { replace: true });
      return;
    }

    if (stored.role !== "admin") {
      clearAuthSession();
      navigate("/login", { replace: true });
      return;
    }

    setUser(stored);
  }, [navigate]);

  if (!user) return null;

  return (
    <AdminShell
      user={user}
      activeView="donorRecords"
      onChangeView={(viewId) => {
        if (viewId === "dashboard") navigate("/admin");
        if (viewId === "donors") navigate("/admin/donors");
      }}
      title="Donor Records"
      subtitle="Search, filter, preview, export, and update registered donor records"
    >
      <DonorRecordsPanel embedded={false} />
    </AdminShell>
  );
}
