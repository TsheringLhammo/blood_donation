import React, { useEffect, useMemo, useState } from "react";
import { toast } from "react-toastify";
import { authFetch, clearAuthSession, getStoredUser } from "../../utils/auth";
import "./DonationHistoryPanel.css";

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

const formatDateTime = (value) => {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
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

export default function DonationHistoryPanel({ embedded = false }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState({ total_donations: 0, this_month: 0, total_units: 0, active_blood_banks: 0 });
  const [options, setOptions] = useState({ blood_groups: [], component_types: [], blood_banks: [] });
  const [pagination, setPagination] = useState({ page: 1, per_page: 10, total: 0, total_pages: 1 });

  const [draftFilters, setDraftFilters] = useState({
    search: "",
    blood_group: "",
    component_type: "",
    blood_bank: "",
    date_from: "",
    date_to: "",
    per_page: 10,
  });

  const [filters, setFilters] = useState(draftFilters);
  const [viewRow, setViewRow] = useState(null);
  const [exporting, setExporting] = useState(false);

  useEffect(() => {
    const stored = getStoredUser();
    if (!stored?.token) {
      clearAuthSession();
      window.location.href = "/blood_donation/login";
      return;
    }

    setUser(stored);
  }, []);

  const queryString = useMemo(() => {
    const params = new URLSearchParams();
    params.set("page", String(pagination.page));
    params.set("per_page", String(filters.per_page));
    if (filters.search.trim()) params.set("search", filters.search.trim());
    if (filters.blood_group) params.set("blood_group", filters.blood_group);
    if (filters.component_type) params.set("component_type", filters.component_type);
    if (filters.blood_bank) params.set("blood_bank", filters.blood_bank);
    if (filters.date_from) params.set("date_from", filters.date_from);
    if (filters.date_to) params.set("date_to", filters.date_to);
    params.set("_ts", String(Date.now()));
    return params.toString();
  }, [filters, pagination.page]);

  const fetchHistory = async () => {
    setLoading(true);
    setError("");
    try {
      const response = await authFetch(`backend/api/get_donation_history.php?${queryString}`, { cache: "no-store" });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to load donation history.");
      }

      setRows(Array.isArray(data.data) ? data.data : []);
      setSummary(data.summary || summary);
      setOptions(data.options || options);
      setPagination(data.pagination || pagination);
    } catch (fetchError) {
      setError(fetchError.message || "Unable to load donation history.");
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!user?.token) return;
    fetchHistory();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.token, queryString]);

  const applyFilters = () => {
    setFilters({ ...draftFilters, per_page: Number(draftFilters.per_page) || 10 });
    setPagination((current) => ({ ...current, page: 1 }));
  };

  const clearFilters = () => {
    const empty = {
      search: "",
      blood_group: "",
      component_type: "",
      blood_bank: "",
      date_from: "",
      date_to: "",
      per_page: 10,
    };
    setDraftFilters(empty);
    setFilters(empty);
    setPagination((current) => ({ ...current, page: 1 }));
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const response = await authFetch(`backend/api/get_donation_history.php?${queryString}&per_page=100`, { cache: "no-store" });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to export donation history.");
      }

      const rowsToExport = [
        ["Date & Time", "Donor Name", "CID", "Blood Bank", "Component Type", "Units", "Staff Name", "Status"],
        ...(Array.isArray(data.data) ? data.data : []).map((row) => [row.donation_date_time, row.donor_name, row.cid_masked, row.blood_bank, row.component_type, row.units, row.staff_name, row.status]),
      ];

      const csv = rowsToExport.map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
      DownloadLinkBlob(new Blob([csv], { type: "text/csv;charset=utf-8;" }), `donation-history-${new Date().toISOString().slice(0, 10)}.csv`);
      toast.success("Donation history export downloaded.");
    } catch (exportError) {
      toast.error(exportError.message || "Failed to export donation history.");
    } finally {
      setExporting(false);
    }
  };

  return (
    <div className="donation-history-panel">
      <section className="donation-history-hero">
        <div>
          <div className="donation-history-kicker">Hospital Workflow</div>
          <h2 className="donation-history-title">Donation History</h2>
          <p className="donation-history-subtitle">Completed donation events only. Linked to donor, blood bank, and staff records.</p>
        </div>
      </section>

      <section className="donation-history-stats">
        <article className="donation-history-stat">
          <div className="donation-history-stat-icon" aria-hidden="true">🩸</div>
          <div className="donation-history-stat-body">
            <span className="donation-history-stat-label">Total Donations</span>
            <strong className="donation-history-stat-value">{summary.total_donations}</strong>
            <span className="donation-history-stat-caption">All completed donations</span>
          </div>
        </article>
        <article className="donation-history-stat">
          <div className="donation-history-stat-icon" aria-hidden="true">📅</div>
          <div className="donation-history-stat-body">
            <span className="donation-history-stat-label">This Month</span>
            <strong className="donation-history-stat-value">{summary.this_month}</strong>
            <span className="donation-history-stat-caption">Completed this month</span>
          </div>
        </article>
        <article className="donation-history-stat">
          <div className="donation-history-stat-icon" aria-hidden="true">🧪</div>
          <div className="donation-history-stat-body">
            <span className="donation-history-stat-label">Total Units</span>
            <strong className="donation-history-stat-value">{summary.total_units}</strong>
            <span className="donation-history-stat-caption">Bags collected</span>
          </div>
        </article>
        <article className="donation-history-stat">
          <div className="donation-history-stat-icon" aria-hidden="true">🏥</div>
          <div className="donation-history-stat-body">
            <span className="donation-history-stat-label">Active Blood Banks</span>
            <strong className="donation-history-stat-value">{summary.active_blood_banks}</strong>
            <span className="donation-history-stat-caption">Operational locations</span>
          </div>
        </article>
      </section>

      <section className="donation-history-toolbar">
        <div className="donation-history-toolbar-grid">
          <label className="donation-history-field donation-history-field-wide">
            <span>Search by Donor Name or CID</span>
            <div className="donation-history-input-wrap">
              <input
                type="text"
                placeholder="Search name or CID"
                value={draftFilters.search}
                onChange={(event) => setDraftFilters((current) => ({ ...current, search: event.target.value }))}
              />
            </div>
          </label>
          <label className="donation-history-field">
            <span>Blood Group</span>
            <select value={draftFilters.blood_group} onChange={(event) => setDraftFilters((current) => ({ ...current, blood_group: event.target.value }))}>
              <option value="">All Blood Groups</option>
              {(options.blood_groups || []).map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </label>
          <label className="donation-history-field">
            <span>Component Type</span>
            <select value={draftFilters.component_type} onChange={(event) => setDraftFilters((current) => ({ ...current, component_type: event.target.value }))}>
              <option value="">All Components</option>
              {(options.component_types || []).map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </label>
          <label className="donation-history-field">
            <span>Blood Bank</span>
            <select value={draftFilters.blood_bank} onChange={(event) => setDraftFilters((current) => ({ ...current, blood_bank: event.target.value }))}>
              <option value="">All Blood Banks</option>
              {(options.blood_banks || []).map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </label>
          <label className="donation-history-field">
            <span>Date From</span>
            <input type="date" value={draftFilters.date_from} onChange={(event) => setDraftFilters((current) => ({ ...current, date_from: event.target.value }))} />
          </label>
          <label className="donation-history-field">
            <span>Date To</span>
            <input type="date" value={draftFilters.date_to} onChange={(event) => setDraftFilters((current) => ({ ...current, date_to: event.target.value }))} />
          </label>
          <label className="donation-history-field donation-history-field-small">
            <span>Rows</span>
            <select value={draftFilters.per_page} onChange={(event) => setDraftFilters((current) => ({ ...current, per_page: Number(event.target.value) }))}>
              {PER_PAGE_OPTIONS.map((option) => <option key={option} value={option}>{option}</option>)}
            </select>
          </label>
        </div>

        <div className="donation-history-toolbar-actions">
          <button type="button" className="donation-history-button secondary" onClick={clearFilters}>Clear Filters</button>
          <button type="button" className="donation-history-button primary" onClick={applyFilters}>Apply Filters</button>
          <button type="button" className="donation-history-button ghost" onClick={exportCsv} disabled={exporting}>Export CSV</button>
        </div>
      </section>

      <section className="donation-history-card">
        <div className="donation-history-card-head">
          <div>
            <h3>Donation Records</h3>
            <p>Showing completed donation events only.</p>
          </div>
          <div className="donation-history-card-meta">
            <span>{pagination.total} records</span>
            <span>{pagination.total_pages} pages</span>
          </div>
        </div>

        <div className="donation-history-table-wrap">
          <table className="donation-history-table">
            <thead>
              <tr>
                <th>Date &amp; Time</th>
                <th>Donor Name</th>
                <th>CID</th>
                <th>Blood Bank</th>
                <th>Component Type</th>
                <th>Units (bags)</th>
                <th>Staff Name</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="9"><div className="donation-history-empty">Loading donation history...</div></td></tr>
              ) : error ? (
                <tr><td colSpan="9"><div className="donation-history-error">{error}</div></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan="9"><div className="donation-history-empty">No completed donation records match the current filters.</div></td></tr>
              ) : rows.map((row) => (
                <tr key={row.id}>
                  <td className="donation-history-date">{formatDateTime(row.donation_date_time)}</td>
                  <td className="donation-history-name">{row.donor_name}</td>
                  <td className="donation-history-cid">{row.cid_masked || "—"}</td>
                  <td className="donation-history-bank">{row.blood_bank || "—"}</td>
                  <td><span className="donation-history-chip">{row.component_type}</span></td>
                  <td className="donation-history-units">{row.units}</td>
                  <td className="donation-history-staff">{row.staff_name}</td>
                  <td><span className="donation-history-badge completed">🟢 Completed</span></td>
                  <td>
                    <button type="button" className="donation-history-action-btn" onClick={() => setViewRow(row)}>View details</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="donation-history-footer">
          <div className="donation-history-footer-left">Showing {rows.length} of {pagination.total} completed records</div>
          <div className="donation-history-pagination">
            <button type="button" className="donation-history-page-btn" disabled={pagination.page <= 1} onClick={() => setPagination((current) => ({ ...current, page: current.page - 1 }))}>Previous</button>
            <span className="donation-history-page-indicator">Page {pagination.page} of {pagination.total_pages}</span>
            <button type="button" className="donation-history-page-btn" disabled={pagination.page >= pagination.total_pages} onClick={() => setPagination((current) => ({ ...current, page: current.page + 1 }))}>Next</button>
          </div>
        </div>
      </section>

      {viewRow ? (
        <div className="donation-history-modal-overlay" onClick={() => setViewRow(null)}>
          <div className="donation-history-modal" onClick={(event) => event.stopPropagation()}>
            <div className="donation-history-modal-header">
              <div>
                <h3>Donation Details</h3>
                <p>{viewRow.donor_name} · {viewRow.donation_id}</p>
              </div>
              <button type="button" className="donation-history-modal-close" onClick={() => setViewRow(null)}>×</button>
            </div>
            <div className="donation-history-modal-grid">
              <div><span>Date &amp; Time</span><strong>{formatDateTime(viewRow.donation_date_time)}</strong></div>
              <div><span>Donor</span><strong>{viewRow.donor_name}</strong></div>
              <div><span>CID</span><strong>{viewRow.cid_masked || "—"}</strong></div>
              <div><span>Blood Bank</span><strong>{viewRow.blood_bank || "—"}</strong></div>
              <div><span>Component</span><strong>{viewRow.component_type}</strong></div>
              <div><span>Units</span><strong>{viewRow.units}</strong></div>
              <div><span>Staff</span><strong>{viewRow.staff_name}</strong></div>
              <div><span>Status</span><strong>Completed</strong></div>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}