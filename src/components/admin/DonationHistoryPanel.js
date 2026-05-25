import React, { useEffect, useMemo, useState } from "react";
import { toast } from "react-toastify";
import { authFetch, clearAuthSession, getStoredUser } from "../../utils/auth";
import "./DonationHistoryPanel.css";

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

const formatDateTime = (value) => {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return value;
  return {
    date: new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" }).format(date),
    time: new Intl.DateTimeFormat("en-GB", { hour: "2-digit", minute: "2-digit" }).format(date),
  };
};

const formatDateLabel = (value) => {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" }).format(date);
};

function downloadBlob(blob, filename) {
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(url);
}

function getPageNumbers(current, total) {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages = new Set([1, 2, total - 1, total, current - 1, current, current + 1]);
  const sorted = Array.from(pages).filter((n) => n >= 1 && n <= total).sort((a, b) => a - b);
  const out = [];
  for (let i = 0; i < sorted.length; i++) {
    out.push(sorted[i]);
    if (i < sorted.length - 1 && sorted[i + 1] - sorted[i] > 1) out.push("...");
  }
  return out;
}

const componentTone = (component) => {
  const c = String(component || "").toLowerCase();
  if (c.includes("platelet")) return "platelets";
  if (c.includes("plasma")) return "plasma";
  if (c.includes("prbc") || c.includes("packed")) return "prbc";
  return "whole";
};

const SvgDrop = (props) => (
  <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" {...props}>
    <path d="M12 2.5c-2.6 3.6-6.8 8.2-6.8 12.2A6.8 6.8 0 0 0 12 21.5a6.8 6.8 0 0 0 6.8-6.8c0-4-4.2-8.6-6.8-12.2z" />
  </svg>
);
const SvgCalendar = (props) => (
  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <rect x="3" y="5" width="18" height="16" rx="2" />
    <path d="M8 3v4M16 3v4M3 10h18" />
  </svg>
);
const SvgBag = (props) => (
  <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" {...props}>
    <path d="M9 2.5h6a1 1 0 0 1 1 1V7H8V3.5a1 1 0 0 1 1-1zM6.5 8h11l-.7 11.6A2 2 0 0 1 14.8 21.5H9.2a2 2 0 0 1-2-1.9L6.5 8z" />
  </svg>
);
const SvgBank = (props) => (
  <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" {...props}>
    <path d="M4 9h4v11H4zM10 9h4v11h-4zM16 9h4v11h-4zM2 21h20v1.5H2zM12 2 2 7v1.5h20V7L12 2z" />
  </svg>
);
const SvgSearch = (props) => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true" {...props}>
    <circle cx="11" cy="11" r="7" />
    <path d="m20.5 20.5-3.8-3.8" />
  </svg>
);
const SvgRefresh = (props) => (
  <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <path d="M21 12a9 9 0 1 1-3-6.7" />
    <path d="M21 3v5h-5" />
  </svg>
);
const SvgFilter = (props) => (
  <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" {...props}>
    <path d="M3 5h18l-7 9v5l-4 2v-7L3 5z" />
  </svg>
);
const SvgSort = (props) => (
  <svg viewBox="0 0 12 16" width="10" height="14" fill="currentColor" aria-hidden="true" {...props}>
    <path d="M6 1 1.5 6h9L6 1z" opacity=".35" />
    <path d="M6 15l-4.5-5h9L6 15z" opacity=".35" />
  </svg>
);
const SvgCheck = (props) => (
  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <path d="m5 12 5 5L20 7" />
  </svg>
);
const SvgEye = (props) => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
    <circle cx="12" cy="12" r="3" />
  </svg>
);
const SvgDownload = (props) => (
  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
    <path d="M7 10l5 5 5-5M12 15V3" />
  </svg>
);
const SvgGear = (props) => (
  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <circle cx="12" cy="12" r="3" />
    <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h0a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h0a1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v0a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
  </svg>
);

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
      per_page: filters.per_page,
    };
    setDraftFilters(empty);
    setFilters(empty);
    setPagination((current) => ({ ...current, page: 1 }));
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const response = await authFetch(`backend/api/get_donation_history.php?${queryString}&per_page=1000`, { cache: "no-store" });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to export donation history.");
      }
      const rowsToExport = [
        ["Date & Time", "Donor Name", "CID", "Blood Bank", "Component Type", "Units", "Staff Name", "Status"],
        ...(Array.isArray(data.data) ? data.data : []).map((row) => [row.donation_date_time, row.donor_name, row.cid_masked, row.blood_bank, row.component_type, row.units, row.staff_name, row.status]),
      ];
      const csv = rowsToExport.map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
      downloadBlob(new Blob([csv], { type: "text/csv;charset=utf-8;" }), `donation-history-${new Date().toISOString().slice(0, 10)}.csv`);
      toast.success("Donation history export downloaded.");
    } catch (exportError) {
      toast.error(exportError.message || "Failed to export donation history.");
    } finally {
      setExporting(false);
    }
  };

  const changePage = (page) => {
    if (page === "..." || page === pagination.page) return;
    if (page < 1 || page > pagination.total_pages) return;
    setPagination((current) => ({ ...current, page }));
  };

  const changePerPage = (value) => {
    const per_page = Number(value) || 10;
    setDraftFilters((current) => ({ ...current, per_page }));
    setFilters((current) => ({ ...current, per_page }));
    setPagination((current) => ({ ...current, page: 1, per_page }));
  };

  const dateRangeLabel = (filters.date_from || filters.date_to)
    ? `${formatDateLabel(filters.date_from) || "Start"} - ${formatDateLabel(filters.date_to) || "End"}`
    : "";

  const startRow = pagination.total === 0 ? 0 : (pagination.page - 1) * pagination.per_page + 1;
  const endRow = Math.min(pagination.page * pagination.per_page, pagination.total);
  const pageNumbers = getPageNumbers(pagination.page, Math.max(pagination.total_pages, 1));

  return (
    <div className="donation-history-panel">
      <section className="dh-stats">
        <article className="dh-stat">
          <div className="dh-stat-icon"><SvgDrop /></div>
          <div className="dh-stat-body">
            <span className="dh-stat-label">Total Donations</span>
            <strong className="dh-stat-value">{summary.total_donations}</strong>
            <span className="dh-stat-caption">All time completed donations</span>
          </div>
        </article>
        <article className="dh-stat">
          <div className="dh-stat-icon"><SvgCalendar /></div>
          <div className="dh-stat-body">
            <span className="dh-stat-label">This Month</span>
            <strong className="dh-stat-value">{summary.this_month}</strong>
            <span className="dh-stat-caption">Completed this month</span>
          </div>
        </article>
        <article className="dh-stat">
          <div className="dh-stat-icon"><SvgBag /></div>
          <div className="dh-stat-body">
            <span className="dh-stat-label">Total Units</span>
            <strong className="dh-stat-value">{summary.total_units}</strong>
            <span className="dh-stat-caption">Units donated</span>
          </div>
        </article>
        <article className="dh-stat">
          <div className="dh-stat-icon"><SvgBank /></div>
          <div className="dh-stat-body">
            <span className="dh-stat-label">Active Blood Banks</span>
            <strong className="dh-stat-value">{summary.active_blood_banks}</strong>
            <span className="dh-stat-caption">Currently active</span>
          </div>
        </article>
      </section>

      <section className="dh-filters">
        <div className="dh-filters-grid">
          <label className="dh-field">
            <span>Search Donor by Name or CID</span>
            <div className="dh-input dh-input-icon">
              <SvgSearch className="dh-input-leading" />
              <input
                type="text"
                placeholder="Search name or CID..."
                value={draftFilters.search}
                onChange={(event) => setDraftFilters((c) => ({ ...c, search: event.target.value }))}
              />
              <button
                type="button"
                className="dh-input-action"
                onClick={applyFilters}
                aria-label="Search"
              >
                <SvgSearch />
              </button>
            </div>
          </label>
          <label className="dh-field">
            <span>Blood Group</span>
            <div className="dh-input dh-input-select">
              <SvgDrop className="dh-input-leading dh-icon-red" />
              <select value={draftFilters.blood_group} onChange={(event) => setDraftFilters((c) => ({ ...c, blood_group: event.target.value }))}>
                <option value="">All Blood Groups</option>
                {(options.blood_groups || []).map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </div>
          </label>
          <label className="dh-field">
            <span>Component Type</span>
            <div className="dh-input dh-input-select">
              <span className="dh-input-leading dh-icon-grid" aria-hidden="true">
                <i /><i /><i /><i />
              </span>
              <select value={draftFilters.component_type} onChange={(event) => setDraftFilters((c) => ({ ...c, component_type: event.target.value }))}>
                <option value="">All Components</option>
                {(options.component_types || []).map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </div>
          </label>
          <label className="dh-field">
            <span>Blood Bank</span>
            <div className="dh-input dh-input-select">
              <SvgBank className="dh-input-leading dh-icon-red" />
              <select value={draftFilters.blood_bank} onChange={(event) => setDraftFilters((c) => ({ ...c, blood_bank: event.target.value }))}>
                <option value="">All Blood Banks</option>
                {(options.blood_banks || []).map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </div>
          </label>
        </div>

        <div className="dh-filters-row2">
          <label className="dh-field dh-field-range">
            <span>Date Range</span>
            <div className="dh-input dh-input-range">
              <SvgCalendar className="dh-input-leading dh-icon-red" />
              <input
                type="date"
                value={draftFilters.date_from}
                onChange={(event) => setDraftFilters((c) => ({ ...c, date_from: event.target.value }))}
                aria-label="Date from"
              />
              <span className="dh-range-sep">–</span>
              <input
                type="date"
                value={draftFilters.date_to}
                onChange={(event) => setDraftFilters((c) => ({ ...c, date_to: event.target.value }))}
                aria-label="Date to"
              />
              {dateRangeLabel ? <span className="dh-range-preview">{dateRangeLabel}</span> : null}
            </div>
          </label>

          <div className="dh-filters-actions">
            <button type="button" className="dh-btn dh-btn-ghost" onClick={clearFilters}>
              <SvgRefresh /> Clear Filters
            </button>
            <button type="button" className="dh-btn dh-btn-primary" onClick={applyFilters}>
              <SvgFilter /> Apply Filters
            </button>
          </div>
        </div>
      </section>

      <section className="dh-card">
        <div className="dh-card-head">
          <div className="dh-card-title">
            <span className="dh-card-title-icon"><SvgDrop /></span>
            <div>
              <h3>Donation Records</h3>
              <p>Showing completed donations by default</p>
            </div>
          </div>
          <div className="dh-card-actions">
            <button type="button" className="dh-btn dh-btn-ghost" onClick={exportCsv} disabled={exporting}>
              <SvgDownload /> {exporting ? "Exporting..." : "Export CSV"}
            </button>
            <button type="button" className="dh-btn dh-btn-ghost dh-btn-icon" aria-label="Table options">
              <SvgGear />
            </button>
          </div>
        </div>

        <div className="dh-table-wrap">
          <table className="dh-table">
            <thead>
              <tr>
                <th className="dh-col-num">#</th>
                <th>
                  <button type="button" className="dh-th-sort">Date <SvgSort /></button>
                </th>
                <th>Donor Name</th>
                <th>
                  <button type="button" className="dh-th-sort">CID <SvgSort /></button>
                </th>
                <th>Blood Bank</th>
                <th>Component Type</th>
                <th>Units</th>
                <th>Staff Name</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="10"><div className="dh-empty">Loading donation history...</div></td></tr>
              ) : error ? (
                <tr><td colSpan="10"><div className="dh-error">{error}</div></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan="10"><div className="dh-empty">No completed donation records match the current filters.</div></td></tr>
              ) : rows.map((row, index) => {
                const dt = formatDateTime(row.donation_date_time);
                const tone = componentTone(row.component_type);
                const ComponentIcon = tone === "whole" ? SvgDrop : SvgBag;
                return (
                  <tr key={row.id ?? `${row.donation_id}-${index}`}>
                    <td className="dh-col-num">{startRow + index}</td>
                    <td>
                      <div className="dh-date">{dt.date || "—"}</div>
                      <div className="dh-time">{dt.time}</div>
                    </td>
                    <td className="dh-name">{row.donor_name || "—"}</td>
                    <td className="dh-cid">{row.cid_masked || "—"}</td>
                    <td className="dh-bank">{row.blood_bank || "—"}</td>
                    <td>
                      <span className={`dh-chip dh-chip-${tone}`}>
                        <ComponentIcon /> {row.component_type || "—"}
                      </span>
                    </td>
                    <td className="dh-units">{row.units}</td>
                    <td className="dh-staff">{row.staff_name || "—"}</td>
                    <td>
                      <span className="dh-badge dh-badge-completed">
                        <SvgCheck /> Completed
                      </span>
                    </td>
                    <td>
                      <button type="button" className="dh-icon-btn" aria-label="View details" onClick={() => setViewRow(row)}>
                        <SvgEye />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="dh-footer">
          <div className="dh-footer-left">
            <div className="dh-input dh-input-select dh-perpage">
              <select value={filters.per_page} onChange={(event) => changePerPage(event.target.value)}>
                {PER_PAGE_OPTIONS.map((option) => <option key={option} value={option}>{option}</option>)}
              </select>
            </div>
            <span className="dh-footer-label">rows per page</span>
          </div>

          <div className="dh-footer-center">
            Showing {startRow} to {endRow} of {pagination.total} records
          </div>

          <div className="dh-footer-right">
            <button
              type="button"
              className="dh-page-nav"
              disabled={pagination.page <= 1}
              onClick={() => changePage(pagination.page - 1)}
            >
              Previous
            </button>
            <div className="dh-page-list">
              {pageNumbers.map((p, i) => p === "..." ? (
                <span key={`gap-${i}`} className="dh-page-gap">…</span>
              ) : (
                <button
                  key={p}
                  type="button"
                  className={`dh-page-num${p === pagination.page ? " active" : ""}`}
                  onClick={() => changePage(p)}
                >
                  {p}
                </button>
              ))}
            </div>
            <button
              type="button"
              className="dh-page-nav"
              disabled={pagination.page >= pagination.total_pages}
              onClick={() => changePage(pagination.page + 1)}
            >
              Next
            </button>
          </div>
        </div>
      </section>

      {viewRow ? (
        <div className="dh-modal-overlay" onClick={() => setViewRow(null)}>
          <div className="dh-modal" onClick={(event) => event.stopPropagation()}>
            <div className="dh-modal-head">
              <div>
                <h3>Donation Details</h3>
                <p>{viewRow.donor_name} · {viewRow.donation_id}</p>
              </div>
              <button type="button" className="dh-modal-close" onClick={() => setViewRow(null)}>×</button>
            </div>
            <div className="dh-modal-grid">
              <div><span>Date &amp; Time</span><strong>{(() => { const dt = formatDateTime(viewRow.donation_date_time); return dt.date ? `${dt.date} ${dt.time}` : viewRow.donation_date_time; })()}</strong></div>
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
