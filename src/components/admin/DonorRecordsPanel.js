import React, { useEffect, useMemo, useState } from "react";
import { toast } from "react-toastify";
import { authFetch, clearAuthSession, getStoredUser } from "../../utils/auth";
import "./DonorRecordsPanel.css";

const BLOOD_GROUP_OPTIONS = ["", "A+", "A-", "B+", "B-", "O+", "O-", "AB+", "AB-"];
const STATUS_OPTIONS = ["", "Active", "Pending", "Deferred"];
const DEFERRAL_OPTIONS = ["", "None", "Temporary", "Permanent"];
const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const VIEW_TABS = ["donations", "tests", "health", "deferrals"];

const createBlankEditForm = () => ({
  id: null,
  cid: "",
  blood_group: "",
  name: "",
  email: "",
  phone: "",
  address: "",
});

const getBadgeClass = (status) => {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "active") return "active";
  if (normalized === "deferred") return "deferred";
  if (normalized === "pending") return "pending";
  return "none";
};

const formatCell = (value, fallback = "N/A") => {
  const text = String(value ?? "").trim();
  return text === "" ? fallback : text;
};

const getStatusLabel = (status) => {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "active") return "Active";
  if (normalized === "pending") return "Pending";
  if (normalized === "deferred") return "Deferred";
  return formatCell(status, "Pending");
};

const formatCidDisplay = (maskedCid, cid) => {
  // Always display full CID number
  const digits = String(cid ?? "").replace(/\D+/g, "");
  if (digits.length === 0) {
    return "—";
  }

  return digits;
};

function DownloadLinkBlob(blob, filename) {
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(url);
}

function openPrintPreview(title, bodyHtml) {
  const popup = window.open("", "_blank", "width=980,height=760,scrollbars=yes,resizable=yes");
  if (!popup || popup.closed) {
    toast.error("Popup blocked by browser. Please allow popups for this site.");
    return;
  }

  popup.document.write(`
    <!doctype html>
    <html>
      <head>
        <title>${title}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <style>
          body { font-family: Arial, sans-serif; margin: 0; background: #f7f0ea; color: #1f2937; }
          .page { max-width: 900px; margin: 32px auto; background: #fff; border-radius: 20px; box-shadow: 0 24px 60px rgba(0,0,0,.15); overflow: hidden; }
          .header { background: linear-gradient(135deg, #7b0013, #b30020); color: #fff; padding: 24px; }
          .content { padding: 24px; }
          .grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
          .card { border: 1px solid #eadfd4; border-radius: 16px; padding: 14px; background: #fffaf8; }
          .label { font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #8b6c5e; }
          .value { font-size: 16px; font-weight: 700; color: #4d0010; margin-top: 6px; }
          table { width: 100%; border-collapse: collapse; }
          th, td { text-align: left; border-bottom: 1px solid #eee; padding: 10px 8px; font-size: 13px; }
          th { color: #8b6c5e; text-transform: uppercase; letter-spacing: .08em; font-size: 11px; }
          .muted { color: #6b7280; }
          @media print { .no-print { display: none; } body { background: #fff; } .page { box-shadow: none; margin: 0; border-radius: 0; } }
        </style>
      </head>
      <body>
        ${bodyHtml}
      </body>
    </html>
  `);
  popup.document.close();
  popup.focus();
}

export default function DonorRecordsPanel({ embedded = false }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchDraft, setSearchDraft] = useState("");
  const [bloodGroupDraft, setBloodGroupDraft] = useState("");
  const [statusDraft, setStatusDraft] = useState("");
  const [deferralDraft, setDeferralDraft] = useState("");
  const [search, setSearch] = useState("");
  const [bloodGroup, setBloodGroup] = useState("");
  const [status, setStatus] = useState("");
  const [deferral, setDeferral] = useState("");
  const [exporting, setExporting] = useState(false);
  const [viewModal, setViewModal] = useState({ open: false, loading: false, error: "", data: null });
  const [editModal, setEditModal] = useState({ open: false, loading: false, saving: false, error: "", form: createBlankEditForm() });
  const [profileTab, setProfileTab] = useState("donations");

  useEffect(() => {
    const stored = getStoredUser();
    if (!stored?.token) {
      clearAuthSession();
      window.location.href = "/blood_donation/login";
      return;
    }

    if (stored.role !== "admin") {
      clearAuthSession();
      window.location.href = "/blood_donation/login";
      return;
    }

    setUser(stored);
  }, []);

  useEffect(() => {
    const handleMessage = (event) => {
      const data = event.data || {};
      if (data.type !== "donor-card-download" || !data.html || !data.filename) {
        return;
      }

      DownloadLinkBlob(new Blob([data.html], { type: "text/html;charset=utf-8" }), data.filename);
      toast.success("Card downloaded.");
    };

    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, []);

  const queryString = useMemo(() => {
    const params = new URLSearchParams();
    params.set("page", String(page));
    params.set("per_page", String(perPage));
    if (search.trim()) params.set("search", search.trim());
    if (bloodGroup) params.set("blood_group", bloodGroup);
    if (status) params.set("status", status);
    if (deferral) params.set("deferral", deferral);
    params.set("_ts", String(Date.now()));
    return params.toString();
  }, [page, perPage, search, bloodGroup, status, deferral]);

  const fetchRows = async () => {
    setLoading(true);
    setError("");

    try {
      const response = await authFetch(`api/donors/list.php?${queryString}`, { cache: "no-store" });
      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.message || "Failed to load donor records.");
      }

      const data = await response.json();
      if (!data.success) {
        throw new Error(data.message || "Failed to load donor records.");
      }

      setRows(Array.isArray(data.data) ? data.data : []);
      setTotal(Number(data.total || 0));
    } catch (fetchError) {
      setError(fetchError.message || "Unable to load donor records.");
      setRows([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!user?.token) return;
    fetchRows();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.token, queryString]);

  const applyFilters = () => {
    setSearch(searchDraft);
    setBloodGroup(bloodGroupDraft);
    setStatus(statusDraft);
    setDeferral(deferralDraft);
    setPage(1);
  };

  const clearFilters = () => {
    setSearchDraft("");
    setBloodGroupDraft("");
    setStatusDraft("");
    setDeferralDraft("");
    setSearch("");
    setBloodGroup("");
    setStatus("");
    setDeferral("");
    setPage(1);
  };

  const openView = async (donorId) => {
    setViewModal({ open: true, loading: true, error: "", data: null });
    try {
      const response = await authFetch(`api/donors/view.php?id=${encodeURIComponent(String(donorId))}&_ts=${Date.now()}`, { cache: "no-store" });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to load donor profile.");
      }

      setViewModal({ open: true, loading: false, error: "", data: data.data });
      setProfileTab("donations");
    } catch (viewError) {
      setViewModal({ open: true, loading: false, error: viewError.message || "Failed to load donor profile.", data: null });
    }
  };

  const closeView = () => {
    setViewModal({ open: false, loading: false, error: "", data: null });
    setProfileTab("donations");
  };

  const openEdit = async (donorId) => {
    setEditModal({ open: true, loading: true, saving: false, error: "", form: createBlankEditForm() });
    try {
      const response = await authFetch(`api/donors/view.php?id=${encodeURIComponent(String(donorId))}&_ts=${Date.now()}`, { cache: "no-store" });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to load donor profile.");
      }

      const donor = data.data?.basic || {};
      setEditModal({
        open: true,
        loading: false,
        saving: false,
        error: "",
        form: {
          id: donor.id,
          cid: donor.cid || "",
          blood_group: donor.blood_group || "",
          name: donor.name || "",
          email: donor.email || "",
          phone: donor.phone || "",
          address: donor.address || "",
        },
      });
    } catch (editError) {
      setEditModal({ open: true, loading: false, saving: false, error: editError.message || "Failed to load donor profile.", form: createBlankEditForm() });
    }
  };

  const closeEdit = () => {
    setEditModal({ open: false, loading: false, saving: false, error: "", form: createBlankEditForm() });
  };

  const saveEdit = async () => {
    if (!editModal.form.id) return;
    if (!editModal.form.name.trim() || !editModal.form.email.trim() || !editModal.form.phone.trim()) {
      toast.error("Name, email, and phone are required.");
      return;
    }
    const cidDigits = String(editModal.form.cid || "").replace(/\D+/g, "");
    if (cidDigits && cidDigits.length !== 11) {
      toast.error("CID must be exactly 11 digits.");
      return;
    }

    setEditModal((current) => ({ ...current, saving: true, error: "" }));
    try {
      const response = await authFetch("api/donors/update.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: editModal.form.id,
          cid: cidDigits,
          name: editModal.form.name.trim(),
          email: editModal.form.email.trim(),
          phone: editModal.form.phone.trim(),
          address: editModal.form.address.trim(),
        }),
      });

      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to update donor.");
      }

      toast.success(data.message || "Donor updated successfully.");
      closeEdit();
      fetchRows();
      if (viewModal.open && viewModal.data?.basic?.id === editModal.form.id) {
        await openView(editModal.form.id);
      }
    } catch (saveError) {
      setEditModal((current) => ({ ...current, saving: false, error: saveError.message || "Failed to update donor." }));
      toast.error(saveError.message || "Failed to update donor.");
    }
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const params = new URLSearchParams();
      if (search.trim()) params.set("search", search.trim());
      if (bloodGroup) params.set("blood_group", bloodGroup);
      if (status) params.set("status", status);
      if (deferral) params.set("deferral", deferral);

      const response = await authFetch(`api/donors/export.php?${params.toString()}`, { cache: "no-store" });
      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.message || "Failed to export CSV.");
      }

      const blob = await response.blob();
      DownloadLinkBlob(blob, `donor-records-${new Date().toISOString().slice(0, 10)}.csv`);
      toast.success("CSV export downloaded.");
    } catch (exportError) {
      toast.error(exportError.message || "Failed to export CSV.");
    } finally {
      setExporting(false);
    }
  };

  const exportHistory = () => {
    const donor = viewModal.data;
    if (!donor) return;

    const lines = [
      ["Date", "Blood Bank", "Component", "Units", "Status", "Staff"],
      ...(donor.donation_history || []).map((entry) => [entry.date, entry.blood_bank, entry.component, entry.units, entry.status, entry.staff]),
    ];

    const csv = lines.map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
    DownloadLinkBlob(new Blob([csv], { type: "text/csv;charset=utf-8;" }), `donor-history-${donor.basic?.id || "record"}.csv`);
    toast.success("History exported.");
  };

  const renderDonationHistory = () => {
    const donor = viewModal.data;
    const history = donor?.donation_history || [];

    if (!history.length) {
      return <div className="donor-records-empty">No donation history is available for this donor.</div>;
    }

    return (
      <div className="donor-records-table-wrap">
        <table className="donor-records-history-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Blood Bank</th>
              <th>Component</th>
              <th>Units</th>
              <th>Status</th>
              <th>Staff</th>
            </tr>
          </thead>
          <tbody>
            {history.map((row, index) => (
              <tr key={`${row.date}-${index}`}>
                <td>{formatCell(row.date)}</td>
                <td>{formatCell(row.blood_bank)}</td>
                <td>{formatCell(row.component, "Whole Blood")}</td>
                <td>{formatCell(row.units, "1")}</td>
                <td>{formatCell(row.status, "Completed")}</td>
                <td>{formatCell(row.staff)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  const renderTestResults = () => {
    const donor = viewModal.data;
    const tests = donor?.test_results || [];

    if (!tests.length) {
      return <div className="donor-records-empty">No screening results are available for this donor.</div>;
    }

    return (
      <div className="donor-records-detail-grid">
        {tests.map((result, index) => (
          <div className="donor-records-detail-card" key={`test-${index}`}>
            <span>{result.date}</span>
            <strong>
              HIV: {formatCell(result.hiv)} | HBsAg: {formatCell(result.hbsag)} | HCV: {formatCell(result.hcv)}
            </strong>
            <div className="donor-records-muted" style={{ marginTop: 8 }}>
              Syphilis: {formatCell(result.syphilis)} | Malaria: {formatCell(result.malaria)}
            </div>
          </div>
        ))}
      </div>
    );
  };

  const renderHealthChecks = () => {
    const donor = viewModal.data;
    const checks = donor?.health_checks || [];

    return (
      <div className="donor-records-detail-grid">
        {checks.map((entry) => (
          <div className="donor-records-detail-card" key={entry.label}>
            <span>{entry.label}</span>
            <strong>{formatCell(entry.value)}</strong>
          </div>
        ))}
      </div>
    );
  };

  const renderDeferrals = () => {
    const donor = viewModal.data;
    const deferrals = donor?.deferral_history || [];

    if (!deferrals.length) {
      return <div className="donor-records-empty">No deferral history is available for this donor.</div>;
    }

    return (
      <div className="donor-records-table-wrap">
        <table className="donor-records-history-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Field</th>
              <th>Old Value</th>
              <th>New Value</th>
              <th>Changed By</th>
            </tr>
          </thead>
          <tbody>
            {deferrals.map((row, index) => (
              <tr key={`${row.date}-${index}`}>
                <td>{formatCell(row.date)}</td>
                <td>{formatCell(row.field)}</td>
                <td>{formatCell(row.old_value, "-")}</td>
                <td>{formatCell(row.new_value, "-")}</td>
                <td>{formatCell(row.changed_by)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  const renderProfileModal = () => {
    const donor = viewModal.data;
    if (!viewModal.open) return null;

    return (
      <div className="donor-records-modal-overlay" onClick={closeView}>
        <div className="donor-records-modal" onClick={(event) => event.stopPropagation()}>
          <div className="donor-records-modal-header">
            <div>
              <h3>{donor?.basic?.name || "Donor Profile"}</h3>
              <p>
                CID {formatCell(donor?.basic?.cid || donor?.basic?.cid_masked)} · {formatCell(donor?.basic?.blood_group)} · {formatCell(donor?.basic?.status)}
              </p>
            </div>
            <button className="donor-records-close-btn" type="button" onClick={closeView} aria-label="Close donor profile">
              ×
            </button>
          </div>

          <div className="donor-records-modal-summary">
            <div className="donor-records-summary-box">
              <span>Total Donations</span>
              <strong>{formatCell(donor?.summary?.total_donations, 0)}</strong>
            </div>
            <div className="donor-records-summary-box">
              <span>First Donation</span>
              <strong>{formatCell(donor?.summary?.first_donation, "N/A")}</strong>
            </div>
            <div className="donor-records-summary-box">
              <span>Last Donation</span>
              <strong>{formatCell(donor?.summary?.last_donation, "Never")}</strong>
            </div>
            <div className="donor-records-summary-box">
              <span>Next Eligible</span>
              <strong>{formatCell(donor?.summary?.next_eligible_date, "N/A")}</strong>
            </div>
            <div className="donor-records-summary-box">
              <span>Days Left</span>
              <strong>{donor?.summary?.days_left === null || donor?.summary?.days_left === undefined ? "N/A" : donor.summary.days_left}</strong>
            </div>
          </div>

          <div className="donor-records-modal-basic">
            <div className="donor-records-basic-item"><span>CID</span><strong>{formatCell(donor?.basic?.cid || donor?.basic?.cid_masked)}</strong></div>
            <div className="donor-records-basic-item"><span>Name</span><strong>{formatCell(donor?.basic?.name)}</strong></div>
            <div className="donor-records-basic-item"><span>Blood Group</span><strong>{formatCell(donor?.basic?.blood_group)}</strong></div>
            <div className="donor-records-basic-item"><span>DOB</span><strong>{formatCell(donor?.basic?.dob, "N/A")}</strong></div>
            <div className="donor-records-basic-item"><span>Gender</span><strong>{formatCell(donor?.basic?.gender, "N/A")}</strong></div>
            <div className="donor-records-basic-item"><span>Email</span><strong>{formatCell(donor?.basic?.email, "N/A")}</strong></div>
            <div className="donor-records-basic-item"><span>Phone</span><strong>{formatCell(donor?.basic?.phone, "N/A")}</strong></div>
            <div className="donor-records-basic-item"><span>Address</span><strong>{formatCell(donor?.basic?.address, "N/A")}</strong></div>
            <div className="donor-records-basic-item"><span>Status</span><strong>{formatCell(donor?.basic?.status)}</strong></div>
            <div className="donor-records-basic-item"><span>Workflow</span><strong>{formatCell(donor?.basic?.workflow_status, "N/A")}</strong></div>
            <div className="donor-records-basic-item"><span>Deferral</span><strong>{formatCell(donor?.basic?.deferral_type, "None")}</strong></div>
            <div className="donor-records-basic-item"><span>Reason</span><strong>{formatCell(donor?.basic?.deferral_reason, "N/A")}</strong></div>
          </div>

          <div className="donor-records-modal-tabs">
            {VIEW_TABS.map((tab) => (
              <button
                key={tab}
                type="button"
                className={`donor-records-tab-btn${profileTab === tab ? " active" : ""}`}
                onClick={() => setProfileTab(tab)}
              >
                {tab === "donations" ? "Donation History" : tab === "tests" ? "Test Results" : tab === "health" ? "Health Checks" : "Deferral History"}
              </button>
            ))}
          </div>

          <div className="donor-records-modal-body">
            {viewModal.loading ? (
              <div className="donor-records-loading">Loading donor profile...</div>
            ) : viewModal.error ? (
              <div className="donor-records-error">{viewModal.error}</div>
            ) : (
              <>
                {profileTab === "donations" && renderDonationHistory()}
                {profileTab === "tests" && renderTestResults()}
                {profileTab === "health" && renderHealthChecks()}
                {profileTab === "deferrals" && renderDeferrals()}
              </>
            )}
          </div>

          <div className="donor-records-modal-actions">
            <div className="donor-records-pagination">
              <button
                type="button"
                className="donor-records-button secondary"
                disabled={!donor || (donor.summary?.total_donations || 0) < 5}
                onClick={() => {
                  if (!donor) return;
                  const html = `
                    <div class="page">
                      <div class="header">
                        <h1>Certificate of Appreciation</h1>
                        <div class="muted">Blood Transfusion Admin</div>
                      </div>
                      <div class="content">
                        <div class="card"><div class="label">Recipient</div><div class="value">${donor.basic?.name || "Donor"}</div></div>
                        <div style="height:16px"></div>
                        <div class="grid">
                          <div class="card"><div class="label">CID</div><div class="value">${donor.basic?.cid || donor.basic?.cid_masked || "N/A"}</div></div>
                          <div class="card"><div class="label">Total Donations</div><div class="value">${donor.summary?.total_donations || 0}</div></div>
                        </div>
                        <div style="height:16px"></div>
                        <div class="card"><div class="label">Message</div><div class="value">Thank you for helping save lives through repeated blood donation.</div></div>
                      </div>
                    </div>`;
                  openPrintPreview("Donation Certificate", html);
                }}
              >
                Generate Certificate
              </button>
              <button
                type="button"
                className="donor-records-button ghost"
                onClick={() => {
                  if (!donor) return;
                  const donorName = donor.basic?.name || "Donor";
                  const donorId = `DONOR-${String(donor.basic?.id || "0").padStart(5, "0")}`;
                  const fullCid = String(donor.basic?.cid || "").trim() || "N/A";
                  const bloodGroup = donor.basic?.blood_group || "N/A";
                  const issueDate = new Date().toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
                  const initials = donorName
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part.charAt(0).toUpperCase())
                    .join("") || "D";
                  const qrValue = encodeURIComponent(`donor:${donorId}|cid:${fullCid}|name:${donorName}|group:${bloodGroup}`);
                  const html = `
                    <style>
                      body { margin: 0; background: #f5f0eb; font-family: Arial, sans-serif; color: #111827; }
                      * { box-sizing: border-box; }
                      .donor-id-page { max-width: 1080px; margin: 0 auto; padding: 18px 0 28px; }
                      .donor-id-title { margin: 0 0 14px; padding: 0 18px; font-size: 20px; font-weight: 800; color: #111827; }
                      .donor-id-shell { max-width: 980px; margin: 28px auto; background: #f5f6f8; border-radius: 24px; box-shadow: 0 20px 60px rgba(19, 18, 28, 0.22); overflow: hidden; }
                      .donor-id-topbar { display: flex; align-items: center; justify-content: space-between; padding: 18px 26px; background: linear-gradient(90deg, #bf131e 0%, #9a111c 100%); color: #fff; }
                      .donor-id-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; letter-spacing: .03em; font-size: 14px; line-height: 1.15; }
                      .donor-id-brand-badge { width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; font-size: 16px; }
                      .donor-id-pill { background: #fff; color: #b31622; border-radius: 999px; font-weight: 800; font-size: 12px; padding: 8px 16px; letter-spacing: .06em; }
                      .donor-id-body { background: #fff; margin: 20px; border-radius: 20px; padding: 22px; border: 1px solid #ece7e7; }
                      .donor-id-main { display: grid; gap: 18px; grid-template-columns: 160px minmax(0, 1fr) 170px; align-items: start; }
                      .donor-id-avatar-wrap { display: flex; justify-content: center; }
                      .donor-id-avatar { width: 138px; height: 138px; border-radius: 50%; background: radial-gradient(circle at 30% 20%, #ffffff 0%, #f0f3f8 60%, #e0e6ef 100%); border: 4px solid #bf131e; display: flex; align-items: center; justify-content: center; font-size: 44px; font-weight: 800; color: #2f3a4a; }
                      .donor-id-details h2 { margin: 0 0 4px; font-size: 40px; font-weight: 800; color: #b31622; line-height: 1.1; }
                      .donor-id-sub { color: #374151; font-size: 21px; font-weight: 700; margin-bottom: 16px; }
                      .donor-id-row { display: grid; grid-template-columns: 132px 12px minmax(0, 1fr); gap: 8px; padding: 6px 0; font-size: 20px; color: #111827; }
                      .donor-id-row-label { color: #4b5563; font-weight: 600; }
                      .donor-id-row-value { font-weight: 800; word-break: break-word; }
                      .donor-id-qr { border: 1px solid #e5e7eb; border-radius: 14px; padding: 10px; background: #fff; display: flex; align-items: center; justify-content: center; }
                      .donor-id-qr img { width: 148px; height: 148px; display: block; }
                      .donor-id-footer { margin: 0 20px 20px; border-radius: 0 0 18px 18px; padding: 14px 20px; color: #fff; font-size: 18px; font-weight: 700; background: linear-gradient(90deg, #b0111c 0%, #8e0f18 100%); display: flex; align-items: center; gap: 10px; }
                      .donor-id-footer-dot { width: 26px; height: 26px; border-radius: 50%; background: rgba(255,255,255,0.2); display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
                      .donor-id-actions { display: flex; gap: 16px; justify-content: center; align-items: center; margin: 14px 20px 10px; flex-wrap: wrap; }
                      .donor-id-action-btn { min-width: 170px; height: 42px; border-radius: 8px; border: 1px solid #d7d9df; background: #fff; color: #222; font-size: 14px; font-weight: 700; cursor: pointer; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 1px 0 rgba(0,0,0,0.02); }
                      .donor-id-action-btn.primary { background: linear-gradient(90deg, #c01722, #b11420); border-color: #b11420; color: #fff; }
                      .donor-id-action-btn.ghost { background: #fff; }
                      .donor-id-action-btn:hover { filter: brightness(0.98); }
                      .donor-id-action-btn.primary:hover { filter: brightness(1.03); }
                      @media (max-width: 860px) { .donor-id-main { grid-template-columns: 1fr; } .donor-id-avatar-wrap { justify-content: flex-start; } .donor-id-qr { justify-self: start; } }
                      @media (max-width: 640px) { .donor-id-title { font-size: 18px; } .donor-id-shell { margin: 12px; } .donor-id-details h2 { font-size: 30px; } .donor-id-sub, .donor-id-row { font-size: 16px; } .donor-id-action-btn { width: 100%; min-width: 0; } }
                      @media print {
                        body { background: #fff !important; }
                        .donor-id-shell { box-shadow: none; margin: 0; border-radius: 0; }
                        .donor-id-actions { display: none !important; }
                        .donor-id-title { display: none; }
                      }
                    </style>
                    <div class="donor-id-page">
                      <div class="donor-id-title">Donor Card Preview</div>
                      <div class="donor-id-shell">
                        <div class="donor-id-topbar">
                          <div class="donor-id-brand">
                            <span class="donor-id-brand-badge">❤</span>
                            <span>BLOOD TRANSFUSION SERVICE<br/>ROYAL GOVERNMENT OF BHUTAN</span>
                          </div>
                          <div class="donor-id-pill">DONOR CARD</div>
                        </div>
                        <div class="donor-id-body">
                          <div class="donor-id-main">
                            <div class="donor-id-avatar-wrap">
                              <div class="donor-id-avatar">${initials}</div>
                            </div>
                            <div class="donor-id-details">
                              <h2>${donorName}</h2>
                              <div class="donor-id-sub">Donor ID: ${donorId}</div>
                              <div class="donor-id-row"><span class="donor-id-row-label">CID</span><span>:</span><span class="donor-id-row-value">${fullCid}</span></div>
                              <div class="donor-id-row"><span class="donor-id-row-label">Blood Group</span><span>:</span><span class="donor-id-row-value">${bloodGroup}</span></div>
                              <div class="donor-id-row"><span class="donor-id-row-label">Issue Date</span><span>:</span><span class="donor-id-row-value">${issueDate}</span></div>
                              <div class="donor-id-row"><span class="donor-id-row-label">Blood Type</span><span>:</span><span class="donor-id-row-value">Whole Blood</span></div>
                            </div>
                            <div class="donor-id-qr">
                              <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${qrValue}" alt="Donor QR" />
                            </div>
                          </div>
                        </div>
                        <div class="donor-id-footer"><span class="donor-id-footer-dot">❤</span>Every Drop Counts, Every Donor Matters</div>
                      </div>
                      <div class="donor-id-actions">
                        <button class="donor-id-action-btn ghost" type="button" onclick="window.print()">Print Card</button>
                        <button class="donor-id-action-btn ghost" type="button" onclick="(function(){if(window.opener){window.opener.postMessage({type:'donor-card-download', filename:'${donorId.toLowerCase()}-card.html', html: document.documentElement.outerHTML}, '*');}})()">Download</button>
                        <button class="donor-id-action-btn primary" type="button" onclick="(function(){var fullSize=window.open('', '_blank', 'width=1400,height=980,scrollbars=yes,resizable=yes');if(!fullSize){return;}fullSize.document.open();fullSize.document.write(document.documentElement.outerHTML);fullSize.document.close();fullSize.focus();})()">View Full Size</button>
                      </div>
                    </div>
                  `;
                  openPrintPreview("Donor ID Card", html);
                }}
              >
                View ID Card
              </button>
              <button type="button" className="donor-records-button secondary" onClick={exportHistory}>
                Export History
              </button>
            </div>

            <div className="donor-records-pagination">
              <button className="donor-records-button secondary" type="button" onClick={closeView}>Close</button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderEditModal = () => {
    if (!editModal.open) return null;

    return (
      <div className="donor-records-modal-overlay" onClick={closeEdit}>
        <div className="donor-records-modal" onClick={(event) => event.stopPropagation()} style={{ width: "min(860px, 100%)" }}>
          <div className="donor-records-modal-header">
            <div>
              <h3>Edit Donor Information</h3>
              <p>CID can be filled for missing records. Blood group remains read-only to protect the donor record.</p>
            </div>
            <button className="donor-records-close-btn" type="button" onClick={closeEdit} aria-label="Close edit modal">×</button>
          </div>

          {editModal.loading ? (
            <div className="donor-records-loading">Loading donor details...</div>
          ) : (
            <div className="donor-records-modal-body">
              {editModal.error ? <div className="donor-records-error">{editModal.error}</div> : null}
              <div className="donor-records-form-grid">
                <div className="donor-records-form-field">
                  <label>CID</label>
                  <input
                    type="text"
                    value={editModal.form.cid}
                    onChange={(event) => setEditModal((current) => ({ ...current, form: { ...current.form, cid: event.target.value } }))}
                    placeholder="Enter 11-digit CID"
                    maxLength={20}
                  />
                </div>
                <div className="donor-records-form-field">
                  <label>Blood Group</label>
                  <input type="text" value={editModal.form.blood_group} readOnly />
                </div>
                <div className="donor-records-form-field">
                  <label>Name</label>
                  <input
                    type="text"
                    value={editModal.form.name}
                    onChange={(event) => setEditModal((current) => ({ ...current, form: { ...current.form, name: event.target.value } }))}
                  />
                </div>
                <div className="donor-records-form-field">
                  <label>Email</label>
                  <input
                    type="email"
                    value={editModal.form.email}
                    onChange={(event) => setEditModal((current) => ({ ...current, form: { ...current.form, email: event.target.value } }))}
                  />
                </div>
                <div className="donor-records-form-field">
                  <label>Phone</label>
                  <input
                    type="text"
                    value={editModal.form.phone}
                    onChange={(event) => setEditModal((current) => ({ ...current, form: { ...current.form, phone: event.target.value } }))}
                  />
                </div>
                <div className="donor-records-form-field donor-records-form-full">
                  <label>Address</label>
                  <textarea
                    value={editModal.form.address}
                    onChange={(event) => setEditModal((current) => ({ ...current, form: { ...current.form, address: event.target.value } }))}
                    placeholder="Street, dzongkhag, or locality"
                  />
                </div>
                <div className="donor-records-form-field donor-records-form-full">
                  <p className="donor-records-form-note">Contact fields are editable. You can now fill missing CID values (11 digits).</p>
                </div>
              </div>
            </div>
          )}

          <div className="donor-records-modal-actions">
            <button className="donor-records-button secondary" type="button" onClick={closeEdit}>
              Cancel
            </button>
            <button className="donor-records-button primary" type="button" onClick={saveEdit} disabled={editModal.loading || editModal.saving}>
              {editModal.saving ? "Saving..." : "Save Changes"}
            </button>
          </div>
        </div>
      </div>
    );
  };

  const totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
  const startRow = total === 0 ? 0 : (page - 1) * perPage + 1;
  const endRow = Math.min(total, page * perPage);
  const pageNumbers = useMemo(() => {
    const windowSize = 5;
    const start = Math.max(1, page - Math.floor(windowSize / 2));
    const end = Math.min(totalPages, start + windowSize - 1);
    const safeStart = Math.max(1, end - windowSize + 1);
    const pages = [];

    for (let current = safeStart; current <= end; current += 1) {
      pages.push(current);
    }

    return pages;
  }, [page, totalPages]);

  if (!user) return null;

  return (
    <div className="donor-records-panel">
      {!embedded ? (
        <div className="donor-records-hero">
          <div className="donor-records-hero-grid">
            <div>
              <div className="donor-records-kicker">Admin Dashboard</div>
              <h2 className="donor-records-title">Donor Records</h2>
              <p className="donor-records-subtitle">
                Manage and view all donor information.
              </p>
            </div>
          </div>
        </div>
      ) : null}

      <div className="donor-records-toolbar">
        <div className="donor-records-toolbar-labels">
          <div className="donor-records-label-group">
            <div className="donor-records-filter-label">Search by CID, name, email, or phone</div>
          </div>
          <div className="donor-records-label-group">
            <div className="donor-records-filter-label">All Blood Groups</div>
          </div>
          <div className="donor-records-label-group">
            <div className="donor-records-filter-label">All Statuses</div>
          </div>
          <div className="donor-records-label-group">
            <div className="donor-records-filter-label">All Deferrals</div>
          </div>
        </div>

        <div className="donor-records-toolbar-inputs">
          <div className="donor-records-search-wrap">
            <span className="donor-records-search-icon">🔍</span>
            <input
              className="donor-records-search"
              type="search"
              placeholder="Search..."
              value={searchDraft}
              onChange={(event) => {
                const value = event.target.value;
                setSearchDraft(value);
                setSearch(value);
                setPage(1);
              }}
              onKeyDown={(event) => {
                if (event.key === "Enter") {
                  applyFilters();
                }
              }}
            />
          </div>

          <select
            className="donor-records-select"
            value={bloodGroupDraft}
            onChange={(event) => {
              const value = event.target.value;
              setBloodGroupDraft(value);
              setBloodGroup(value);
              setPage(1);
            }}
          >
            {BLOOD_GROUP_OPTIONS.map((option) => (
              <option key={option || "all-bg"} value={option}>
                {option || "All Blood Groups"}
              </option>
            ))}
          </select>

          <select
            className="donor-records-select"
            value={statusDraft}
            onChange={(event) => {
              const value = event.target.value;
              setStatusDraft(value);
              setStatus(value);
              setPage(1);
            }}
          >
            {STATUS_OPTIONS.map((option) => (
              <option key={option || "all-status"} value={option}>
                {option || "All Statuses"}
              </option>
            ))}
          </select>

          <select
            className="donor-records-select"
            value={deferralDraft}
            onChange={(event) => {
              const value = event.target.value;
              setDeferralDraft(value);
              setDeferral(value);
              setPage(1);
            }}
          >
            {DEFERRAL_OPTIONS.map((option) => (
              <option key={option || "all-deferral"} value={option}>
                {option || "All Deferrals"}
              </option>
            ))}
          </select>
        </div>

        <div className="donor-records-toolbar-actions">
          <button className="donor-records-button secondary" type="button" onClick={clearFilters}>
            Clear Filters
          </button>
          <button className="donor-records-button primary" type="button" onClick={applyFilters}>
            Apply Filters
          </button>
          <button className="donor-records-button secondary" type="button" onClick={exportCsv} disabled={exporting || loading}>
            {exporting ? "Exporting..." : "Export CSV"}
          </button>
        </div>
      </div>

      <div className="donor-records-card">
        <div className="donor-records-card-head">
          <div>
            <h3>Donor Records</h3>
            <p>Full CID, live workflow status, and profile actions are all driven by the database.</p>
          </div>
        </div>

        {loading ? <div className="donor-records-loading">Loading donor records...</div> : error ? <div className="donor-records-error">{error}</div> : rows.length === 0 ? <div className="donor-records-empty">No donors found. Try adjusting your filters.</div> : (
          <div className="donor-records-table-wrap">
            <table className="donor-records-table">
              <colgroup>
                <col style={{ width: "44px" }} />
                <col style={{ width: "122px" }} />
                <col style={{ width: "150px" }} />
                <col style={{ width: "78px" }} />
                <col style={{ width: "92px" }} />
                <col style={{ width: "92px" }} />
                <col style={{ width: "170px" }} />
                <col style={{ width: "110px" }} />
                <col style={{ width: "96px" }} />
                <col style={{ width: "118px" }} />
                <col style={{ width: "98px" }} />
                <col style={{ width: "96px" }} />
              </colgroup>
              <thead>
                <tr>
                  <th>#</th>
                  <th>CID</th>
                  <th>Name</th>
                  <th>Blood Group</th>
                  <th>Deferral</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Last Donation</th>
                  <th>Total Donations</th>
                  <th>Next Eligible</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, index) => (
                  <tr key={row.id}>
                    <td>{startRow + index}</td>
                        <td title={formatCidDisplay(row.cid_masked, row.cid)}><span className="donor-records-code">{formatCidDisplay(row.cid_masked, row.cid)}</span></td>
                    <td><span className="donor-records-name">{formatCell(row.name)}</span></td>
                    <td>{formatCell(row.blood_group)}</td>
                    <td>{formatCell(row.deferral_type, "None")}</td>
                    <td>{formatCell(row.phone)}</td>
                    <td>{formatCell(row.email)}</td>
                    <td>{formatCell(row.last_donation, "—")}</td>
                    <td>{formatCell(row.total_donations, 0)}</td>
                    <td>{Number(row.total_donations || 0) > 0 ? formatCell(row.next_eligible, "—") : "Not Eligible"}</td>
                    <td>
                      <span className={`donor-records-badge ${getBadgeClass(row.status)}`}>{getStatusLabel(row.status)}</span>
                    </td>
                    <td>
                      <div className="donor-records-actions">
                        <button type="button" className="donor-records-action-btn view" onClick={() => openView(row.id)} aria-label={`View donor ${row.name}`}>
                          <span aria-hidden="true">👁</span>
                        </button>
                        <button type="button" className="donor-records-action-btn edit" onClick={() => openEdit(row.id)} aria-label={`Edit donor ${row.name}`}>
                          <span aria-hidden="true">✎</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div className="donor-records-footer">
          <div className="donor-records-footer-left">
            <select className="donor-records-select" value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }}>
              {PER_PAGE_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {option} rows per page
                </option>
              ))}
            </select>
            <span className="donor-records-page-indicator">
              Showing {startRow} to {endRow} of {total} donors
            </span>
          </div>
          <div className="donor-records-pagination">
            <div className="donor-records-page-numbers">
              {pageNumbers.map((pageNumber) => (
                <button
                  key={pageNumber}
                  type="button"
                  className={`donor-records-page-number${pageNumber === page ? " active" : ""}`}
                  onClick={() => setPage(pageNumber)}
                  disabled={loading || pageNumber === page}
                >
                  {pageNumber}
                </button>
              ))}
            </div>
            <button className="donor-records-button ghost" type="button" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={page <= 1 || loading}>
              Previous
            </button>
            <button className="donor-records-button ghost" type="button" onClick={() => setPage((current) => Math.min(totalPages, current + 1))} disabled={page >= totalPages || loading}>
              Next
            </button>
          </div>
        </div>
      </div>

      {renderProfileModal()}
      {renderEditModal()}
    </div>
  );
}
