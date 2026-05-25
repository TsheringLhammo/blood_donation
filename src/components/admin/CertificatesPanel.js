import React, { useEffect, useMemo, useState } from "react";
import { toast } from "react-toastify";
import { authFetch, clearAuthSession, getStoredUser } from "../../utils/auth";
import "./CertificatesPanel.css";

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

const formatDateTime = (value) => {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
};

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
};

const milestoneBadge = (key) => {
  if (key === "milestone_20") return "Lifetime Honor";
  if (key === "milestone_10") return "Recognition";
  return "Appreciation";
};

const milestoneCopy = (key) => {
  if (key === "milestone_20") return "OF LIFETIME HONOR";
  if (key === "milestone_10") return "OF RECOGNITION";
  return "OF APPRECIATION";
};

const buildQrUrl = (certificate) => {
  const parts = [
    `cert:${certificate.certificate_number || certificate.id || ""}`,
    `donor:${certificate.donor_display_name || certificate.donor_name || ""}`,
    `cid:${certificate.donor_display_cid || certificate.cid || ""}`,
    `donations:${certificate.total_completed_donations || 0}`,
  ];
  return `https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=0&data=${encodeURIComponent(parts.join("|"))}`;
};

const buildDonorIdLabel = (certificate) => {
  if (certificate.certificate_number) return certificate.certificate_number;
  if (certificate.donor_id) return `DONOR-${String(certificate.donor_id).padStart(5, "0")}`;
  return "DONOR-00000";
};

/* ---------- Certificate SVG bits ---------- */
const CertCorner = (props) => (
  <svg viewBox="0 0 90 90" width="78" height="78" fill="none" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" aria-hidden="true" {...props}>
    <path d="M2 2h32M2 2v32" />
    <path d="M8 8c10 0 18 8 18 18" />
    <path d="M14 14c3 0 8 1 12 6 4 5 5 10 5 14" opacity=".7" />
    <circle cx="6" cy="6" r="1.2" fill="currentColor" stroke="none" />
    <circle cx="22" cy="6" r="1" fill="currentColor" stroke="none" />
    <circle cx="6" cy="22" r="1" fill="currentColor" stroke="none" />
    <path d="M30 12c-2 4-2 8 0 12" opacity=".55" />
    <path d="M12 30c4-2 8-2 12 0" opacity=".55" />
  </svg>
);

const CertDropHeart = (props) => (
  <svg viewBox="0 0 48 56" width="44" height="52" fill="currentColor" aria-hidden="true" {...props}>
    <path d="M24 2c-6 8-16 19-16 30a16 16 0 0 0 32 0c0-11-10-22-16-30z" />
    <path d="M24 36c-2-3-7-4-7-9 0-2.5 2-4.5 4-4.5 1.5 0 2.5 1 3 2 .5-1 1.5-2 3-2 2 0 4 2 4 4.5 0 5-5 6-7 9z" fill="#ffffff" />
  </svg>
);

const CertSeal = (props) => (
  <svg viewBox="0 0 120 130" width="110" height="120" aria-hidden="true" {...props}>
    <g transform="translate(60 60)">
      <g fill="none" stroke="#7a0a14" strokeWidth="1.3" strokeLinecap="round">
        <path d="M-40 -10 c -6 6 -10 16 -8 28" />
        <path d="M-32 -16 c -3 2 -6 7 -7 12" opacity=".7" />
        <path d="M-36 0 c -2 4 -3 9 -2 14" opacity=".7" />
        <path d="M-30 10 c -2 4 -2 9 0 13" opacity=".7" />
        <path d="M40 -10 c 6 6 10 16 8 28" />
        <path d="M32 -16 c 3 2 6 7 7 12" opacity=".7" />
        <path d="M36 0 c 2 4 3 9 2 14" opacity=".7" />
        <path d="M30 10 c 2 4 2 9 0 13" opacity=".7" />
      </g>
      <circle r="32" fill="#fff5f5" stroke="#b1001a" strokeWidth="2.4" />
      <circle r="26" fill="none" stroke="#b1001a" strokeWidth="0.8" strokeDasharray="2 3" />
      <g transform="translate(0 -5)" fill="#b1001a">
        <path d="M0 -16c-5 7-10 12-10 18a10 10 0 0 0 20 0c0-6-5-11-10-18z" />
      </g>
    </g>
    <g transform="translate(60 92)" fill="#b1001a">
      <path d="M-14 0 l-6 16 12 -6 8 6 -6 -16z" opacity=".85" />
      <path d="M14 0 l6 16 -12 -6 -8 6 6 -16z" opacity=".85" />
    </g>
  </svg>
);

const IcPrinter = (props) => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
    <rect x="6" y="14" width="12" height="8" rx="1" />
  </svg>
);

const IcDownload = (props) => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
    <path d="M7 10l5 5 5-5M12 15V3" />
  </svg>
);

const IcShare = (props) => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
    <circle cx="18" cy="5" r="3" />
    <circle cx="6" cy="12" r="3" />
    <circle cx="18" cy="19" r="3" />
    <path d="m8.6 13.5 6.8 4M15.4 6.5 8.6 10.5" />
  </svg>
);

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

function buildCertificateHtml(certificate) {
  const donorName = certificate.donor_display_name || certificate.donor_name || "Donor";
  const certificateType = certificate.certificate_type || "Certificate of Appreciation";
  const totalDonations = certificate.total_completed_donations || 0;
  const cid = certificate.donor_display_cid || certificate.cid || "—";
  const issueDate = formatDate(certificate.issue_date);
  const bloodType = certificate.blood_type || "N/A";
  const donorIdLabel = buildDonorIdLabel(certificate);
  const qrUrl = buildQrUrl(certificate);
  const subtitleText = milestoneCopy(certificate.milestone_key);
  const safeFile = String(certificate.certificate_number || certificate.milestone_key || "certificate").replace(/[^a-z0-9_-]+/gi, "-").toLowerCase();
  const signerName = (certificate.signed_by_name || "Admin BTS").trim() || "Admin BTS";
  const signerRoleRaw = (certificate.signed_by_role || "").trim();
  const signerRole = signerRoleRaw ? signerRoleRaw.charAt(0).toUpperCase() + signerRoleRaw.slice(1) : "";
  const signerTagline = signerRole ? `Blood Transfusion Service — ${signerRole}` : "Blood Transfusion Service";

  return `<!doctype html>
  <html>
    <head>
      <meta charset="utf-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>${certificateType} - ${donorName}</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Great+Vibes&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
      <style>
        :root { --red: #b1001a; --red-deep: #7a0a14; --paper: #fdf2f2; --ink: #1f2937; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #efe4e4; font-family: 'Inter', Arial, sans-serif; color: var(--ink); }
        .page { max-width: 1000px; margin: 28px auto; background: var(--paper); border: 4px solid var(--red); border-radius: 10px; padding: 50px 60px 40px; position: relative; box-shadow: 0 24px 60px rgba(0,0,0,.18); }
        .page::before { content: ""; position: absolute; inset: 8px; border: 1px solid var(--red); border-radius: 6px; pointer-events: none; }
        .corner { position: absolute; width: 80px; height: 80px; color: var(--red); }
        .corner svg { width: 100%; height: 100%; }
        .c-tl { top: 14px; left: 14px; }
        .c-tr { top: 14px; right: 14px; transform: scaleX(-1); }
        .c-bl { bottom: 14px; left: 14px; transform: scaleY(-1); }
        .c-br { bottom: 14px; right: 14px; transform: scale(-1, -1); }
        .top-drop { display: flex; justify-content: center; color: var(--red); margin-bottom: 6px; }
        h1.title { margin: 4px 0 6px; text-align: center; font-family: 'Cinzel', 'Playfair Display', Georgia, serif; font-size: 58px; letter-spacing: .08em; color: var(--red); font-weight: 700; }
        .subtitle { display: flex; align-items: center; justify-content: center; gap: 14px; color: var(--red); font-family: 'Cinzel', Georgia, serif; letter-spacing: .26em; font-size: 16px; margin-bottom: 28px; }
        .subtitle .line { flex: 0 0 70px; height: 1.5px; background: var(--red); }
        .intro { text-align: center; color: #3a3a3a; font-size: 17px; margin: 22px 0 8px; }
        .name { text-align: center; margin: 0 0 18px; font-family: 'Great Vibes', 'Edwardian Script ITC', cursive; color: var(--red); font-size: 76px; line-height: 1; font-weight: 400; }
        .body-text { text-align: center; color: #3a3a3a; font-size: 16px; line-height: 1.7; max-width: 720px; margin: 0 auto 32px; }
        .stats { display: grid; grid-template-columns: 1fr auto 1fr; gap: 26px; align-items: center; margin: 8px 0 28px; }
        .stat { text-align: center; }
        .stat-icon { color: var(--red); display: inline-flex; }
        .stat-label { color: var(--red); font-weight: 700; letter-spacing: .12em; font-size: 12px; margin-top: 6px; }
        .stat-value { color: var(--red-deep); font-size: 36px; font-weight: 700; margin-top: 6px; }
        .stat-caption { color: #6b6b6b; font-size: 13px; margin-top: 2px; }
        .seal { display: flex; justify-content: center; }
        .footer-row { display: grid; grid-template-columns: 110px 1fr 1fr; gap: 24px; align-items: end; margin-top: 22px; }
        .qr img { width: 100px; height: 100px; display: block; }
        .info .info-row { display: grid; grid-template-columns: 80px 8px 1fr; gap: 4px; font-size: 13px; color: #4b5563; padding: 3px 0; }
        .info .info-row strong { color: var(--ink); font-weight: 600; }
        .info .info-id { color: var(--red); font-weight: 700; }
        .sign { text-align: right; }
        .sign-script { font-family: 'Great Vibes', cursive; color: var(--ink); font-size: 28px; line-height: 1; }
        .sign-line { width: 180px; border-top: 1.5px dotted #9ca3af; margin: 4px 0 0 auto; }
        .sign-name { color: var(--ink); font-weight: 700; font-size: 14px; margin-top: 8px; }
        .sign-org { color: #6b7280; font-size: 12px; margin-top: 2px; }
        .actions { display: flex; gap: 12px; justify-content: center; padding: 22px 0 6px; flex-wrap: wrap; }
        .actions button { min-width: 180px; height: 44px; border-radius: 10px; border: 1px solid #d1d5db; background: #fff; color: #111827; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .actions button.primary { background: var(--red); color: #fff; border-color: var(--red); }
        @media (max-width: 800px) {
          .page { padding: 36px 28px; }
          h1.title { font-size: 38px; }
          .name { font-size: 50px; }
          .stats { grid-template-columns: 1fr; }
          .seal { order: -1; }
          .footer-row { grid-template-columns: 1fr; text-align: center; }
          .sign { text-align: center; }
          .sign-line { margin: 4px auto 0; }
        }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; border-radius: 0; } .actions { display: none; } }
      </style>
      <script>
        window.downloadCertificate = function () {
          var html = document.documentElement.outerHTML;
          var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
          var anchor = document.createElement('a');
          anchor.href = URL.createObjectURL(blob);
          anchor.download = '${safeFile}.html';
          document.body.appendChild(anchor);
          anchor.click();
          anchor.remove();
          setTimeout(function () { URL.revokeObjectURL(anchor.href); }, 1000);
        };
      </script>
    </head>
    <body>
      <div class="page">
        <div class="corner c-tl"><svg viewBox="0 0 90 90" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"><path d="M2 2h32M2 2v32"/><path d="M8 8c10 0 18 8 18 18"/><path d="M14 14c3 0 8 1 12 6 4 5 5 10 5 14" opacity=".7"/><circle cx="6" cy="6" r="1.2" fill="currentColor" stroke="none"/><path d="M30 12c-2 4-2 8 0 12" opacity=".55"/><path d="M12 30c4-2 8-2 12 0" opacity=".55"/></svg></div>
        <div class="corner c-tr"><svg viewBox="0 0 90 90" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"><path d="M2 2h32M2 2v32"/><path d="M8 8c10 0 18 8 18 18"/><path d="M14 14c3 0 8 1 12 6 4 5 5 10 5 14" opacity=".7"/><circle cx="6" cy="6" r="1.2" fill="currentColor" stroke="none"/><path d="M30 12c-2 4-2 8 0 12" opacity=".55"/><path d="M12 30c4-2 8-2 12 0" opacity=".55"/></svg></div>
        <div class="corner c-bl"><svg viewBox="0 0 90 90" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"><path d="M2 2h32M2 2v32"/><path d="M8 8c10 0 18 8 18 18"/><path d="M14 14c3 0 8 1 12 6 4 5 5 10 5 14" opacity=".7"/><circle cx="6" cy="6" r="1.2" fill="currentColor" stroke="none"/><path d="M30 12c-2 4-2 8 0 12" opacity=".55"/><path d="M12 30c4-2 8-2 12 0" opacity=".55"/></svg></div>
        <div class="corner c-br"><svg viewBox="0 0 90 90" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"><path d="M2 2h32M2 2v32"/><path d="M8 8c10 0 18 8 18 18"/><path d="M14 14c3 0 8 1 12 6 4 5 5 10 5 14" opacity=".7"/><circle cx="6" cy="6" r="1.2" fill="currentColor" stroke="none"/><path d="M30 12c-2 4-2 8 0 12" opacity=".55"/><path d="M12 30c4-2 8-2 12 0" opacity=".55"/></svg></div>

        <div class="top-drop">
          <svg viewBox="0 0 48 56" width="46" height="54" fill="currentColor"><path d="M24 2c-6 8-16 19-16 30a16 16 0 0 0 32 0c0-11-10-22-16-30z"/><path d="M24 36c-2-3-7-4-7-9 0-2.5 2-4.5 4-4.5 1.5 0 2.5 1 3 2 .5-1 1.5-2 3-2 2 0 4 2 4 4.5 0 5-5 6-7 9z" fill="#ffffff"/></svg>
        </div>

        <h1 class="title">CERTIFICATE</h1>
        <div class="subtitle"><span class="line"></span><span>${subtitleText}</span><span class="line"></span></div>

        <p class="intro">This is to proudly certify that</p>
        <h2 class="name">${donorName}</h2>
        <p class="body-text">has generously donated blood and contributed to saving lives.<br/>Your selfless act of kindness and generosity<br/>is deeply appreciated.</p>

        <div class="stats">
          <div class="stat">
            <span class="stat-icon"><svg viewBox="0 0 48 56" width="34" height="40" fill="currentColor"><path d="M24 2c-6 8-16 19-16 30a16 16 0 0 0 32 0c0-11-10-22-16-30z"/></svg></span>
            <div class="stat-label">TOTAL DONATIONS</div>
            <div class="stat-value">${totalDonations}</div>
            <div class="stat-caption">Donations</div>
          </div>
          <div class="seal">
            <svg viewBox="0 0 120 130" width="120" height="130"><g transform="translate(60 60)"><circle r="32" fill="#fff5f5" stroke="#b1001a" stroke-width="2.4"/><circle r="26" fill="none" stroke="#b1001a" stroke-width=".8" stroke-dasharray="2 3"/><g fill="#b1001a" transform="translate(0 -5)"><path d="M0 -16c-5 7-10 12-10 18a10 10 0 0 0 20 0c0-6-5-11-10-18z"/></g></g><g transform="translate(60 92)" fill="#b1001a"><path d="M-14 0 l-6 16 12 -6 8 6 -6 -16z" opacity=".85"/><path d="M14 0 l6 16 -12 -6 -8 6 6 -16z" opacity=".85"/></g></svg>
          </div>
          <div class="stat">
            <div class="stat-label">BLOOD GROUP</div>
            <div class="stat-value">${bloodType}</div>
          </div>
        </div>

        <div class="footer-row">
          <div class="qr"><img src="${qrUrl}" alt="QR code"/></div>
          <div class="info">
            <div class="info-row"><span>Donor ID</span><i>:</i><strong class="info-id">${donorIdLabel}</strong></div>
            <div class="info-row"><span>CID</span><i>:</i><strong>${cid}</strong></div>
            <div class="info-row"><span>Issue Date</span><i>:</i><strong>${issueDate}</strong></div>
          </div>
          <div class="sign">
            <div class="sign-script">${signerName}</div>
            <div class="sign-line"></div>
            <div class="sign-name">${signerName}</div>
            <div class="sign-org">${signerTagline}</div>
            <div class="sign-org">Bhutan</div>
          </div>
        </div>
      </div>

      <div class="actions">
        <button onclick="window.print()">Print Certificate</button>
        <button onclick="window.downloadCertificate && window.downloadCertificate()">Download PDF</button>
        <button class="primary" onclick="window.close()">Close</button>
      </div>
    </body>
  </html>`;
}

export default function CertificatesPanel({ embedded = false }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState({ total: 0, appreciation: 0, recognition: 0, honor: 0 });
  const [pagination, setPagination] = useState({ page: 1, per_page: 10, total: 0, total_pages: 1 });
  const [draftFilters, setDraftFilters] = useState({ search: "", milestone: "", status: "", per_page: 10 });
  const [filters, setFilters] = useState(draftFilters);
  const [previewRow, setPreviewRow] = useState(null);
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
    if (filters.milestone) params.set("milestone", filters.milestone);
    if (filters.status) params.set("status", filters.status);
    params.set("sync", "1");
    params.set("_ts", String(Date.now()));
    return params.toString();
  }, [filters, pagination.page]);

  const fetchCertificates = async () => {
    setLoading(true);
    setError("");
    try {
      const response = await authFetch(`backend/api/get_certificates.php?${queryString}`, { cache: "no-store" });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to load certificates.");
      }

      setRows(Array.isArray(data.data) ? data.data : []);
      setSummary(data.summary || summary);
      setPagination(data.pagination || pagination);
    } catch (fetchError) {
      setError(fetchError.message || "Unable to load certificates.");
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!user?.token) return;
    fetchCertificates();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.token, queryString]);

  const applyFilters = () => {
    setFilters({ ...draftFilters, per_page: Number(draftFilters.per_page) || 10 });
    setPagination((current) => ({ ...current, page: 1 }));
  };

  const clearFilters = () => {
    const empty = { search: "", milestone: "", status: "", per_page: 10 };
    setDraftFilters(empty);
    setFilters(empty);
    setPagination((current) => ({ ...current, page: 1 }));
  };

  const openPreview = (certificate) => {
    setPreviewRow(certificate);
  };

  const withSigner = (certificate) => ({
    ...certificate,
    signed_by_name: certificate.signed_by_name || user?.name || "Admin BTS",
    signed_by_role: certificate.signed_by_role || user?.role || "",
  });

  const printCertificate = (certificate) => {
    const popup = window.open("", "_blank", "width=1180,height=860,scrollbars=yes,resizable=yes");
    if (!popup) {
      toast.error("Popup blocked by browser. Please allow popups for this site.");
      return;
    }

    const html = buildCertificateHtml(withSigner(certificate));
    popup.document.write(html);
    popup.document.close();
    popup.downloadCertificate = () => {};
    popup.focus();
  };

  const downloadCertificate = (certificate) => {
    const html = buildCertificateHtml(withSigner(certificate));
    const filename = `${String(certificate.certificate_number || certificate.milestone_key || "certificate").replace(/[^a-z0-9_-]+/gi, "-").toLowerCase()}.html`;
    DownloadLinkBlob(new Blob([html], { type: "text/html;charset=utf-8" }), filename);
    toast.success("Certificate downloaded.");
  };

  return (
    <div className="certificates-panel">
      <section className="certificates-hero">
        <div>
          <div className="certificates-kicker">Recognition Workflow</div>
          <h2 className="certificates-title">Certificates</h2>
          <p className="certificates-subtitle">Milestone certificates are issued automatically at 5, 10, and 20 completed donations.</p>
        </div>
      </section>

      <section className="certificates-stats">
        <article className="certificates-stat"><span>Total Issued</span><strong>{summary.total}</strong><small>All certificate records</small></article>
        <article className="certificates-stat"><span>Appreciation</span><strong>{summary.appreciation}</strong><small>5 completed donations</small></article>
        <article className="certificates-stat"><span>Recognition</span><strong>{summary.recognition}</strong><small>10 completed donations</small></article>
        <article className="certificates-stat"><span>Lifetime Honor</span><strong>{summary.honor}</strong><small>20+ completed donations</small></article>
      </section>

      <section className="certificates-toolbar">
        <div className="certificates-toolbar-grid">
          <label className="certificates-field certificates-field-wide">
            <span>Search by Donor or CID</span>
            <input
              type="text"
              placeholder="Search donor name, CID, or certificate"
              value={draftFilters.search}
              onChange={(event) => setDraftFilters((current) => ({ ...current, search: event.target.value }))}
            />
          </label>
          <label className="certificates-field">
            <span>Milestone</span>
            <select value={draftFilters.milestone} onChange={(event) => setDraftFilters((current) => ({ ...current, milestone: event.target.value }))}>
              <option value="">All milestones</option>
              <option value="milestone_5">5 Donations</option>
              <option value="milestone_10">10 Donations</option>
              <option value="milestone_20">20+ Donations</option>
            </select>
          </label>
          <label className="certificates-field">
            <span>Status</span>
            <select value={draftFilters.status} onChange={(event) => setDraftFilters((current) => ({ ...current, status: event.target.value }))}>
              <option value="">All Statuses</option>
              <option value="Issued">Issued</option>
            </select>
          </label>
          <label className="certificates-field certificates-field-small">
            <span>Rows</span>
            <select value={draftFilters.per_page} onChange={(event) => setDraftFilters((current) => ({ ...current, per_page: Number(event.target.value) }))}>
              {PER_PAGE_OPTIONS.map((option) => <option key={option} value={option}>{option}</option>)}
            </select>
          </label>
        </div>
        <div className="certificates-toolbar-actions">
          <button type="button" className="certificates-button secondary" onClick={clearFilters}>Clear Filters</button>
          <button type="button" className="certificates-button primary" onClick={applyFilters}>Apply Filters</button>
          <button
            type="button"
            className="certificates-button ghost"
            disabled={exporting}
            onClick={async () => {
              setExporting(true);
              try {
                const response = await authFetch(`backend/api/get_certificates.php?${queryString}&per_page=100`, { cache: "no-store" });
                const data = await response.json();
                if (!response.ok || !data.success) {
                  throw new Error(data.message || "Failed to export certificates.");
                }

                const rowsToExport = [
                  ["Issue Date", "Donor", "CID", "Certificate Type", "Donations", "Certificate Number"],
                  ...(Array.isArray(data.data) ? data.data : []).map((row) => [row.issue_date, row.donor_display_name, row.donor_display_cid, row.certificate_type, row.total_completed_donations, row.certificate_number]),
                ];

                const csv = rowsToExport.map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
                DownloadLinkBlob(new Blob([csv], { type: "text/csv;charset=utf-8;" }), `certificates-${new Date().toISOString().slice(0, 10)}.csv`);
                toast.success("Certificate export downloaded.");
              } catch (exportError) {
                toast.error(exportError.message || "Failed to export certificates.");
              } finally {
                setExporting(false);
              }
            }}
          >
            Export CSV
          </button>
        </div>
      </section>

      <section className="certificates-card">
        <div className="certificates-card-head">
          <div>
            <h3>Certificate History</h3>
            <p>Each milestone is issued only once and stored per donor.</p>
          </div>
          <div className="certificates-card-meta">
            <span>{pagination.total} records</span>
            <span>{pagination.total_pages} pages</span>
          </div>
        </div>

        <div className="certificates-table-wrap">
          <table className="certificates-table">
            <thead>
              <tr>
                <th>Issue Date</th>
                <th>Donor</th>
                <th>CID</th>
                <th>Certificate Type</th>
                <th>Donations</th>
                <th>Number</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="8"><div className="certificates-empty">Loading certificates...</div></td></tr>
              ) : error ? (
                <tr><td colSpan="8"><div className="certificates-error">{error}</div></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan="8"><div className="certificates-empty">No certificates have been issued yet.</div></td></tr>
              ) : rows.map((row) => (
                <tr key={row.id}>
                  <td className="certificates-date">{formatDateTime(row.issue_date)}</td>
                  <td className="certificates-name">{row.donor_display_name || row.donor_name || "—"}</td>
                  <td className="certificates-cid">{row.donor_display_cid || row.cid || "—"}</td>
                  <td><span className="certificates-chip">{row.certificate_type}</span></td>
                  <td className="certificates-donations">{row.total_completed_donations}</td>
                  <td className="certificates-number">{row.certificate_number || "—"}</td>
                  <td><span className="certificates-badge issued">Issued</span></td>
                  <td>
                    <div className="certificates-actions">
                      <button type="button" className="certificates-action-btn" onClick={() => openPreview(row)}>Preview</button>
                      <button type="button" className="certificates-action-btn" onClick={() => printCertificate(row)}>Print</button>
                      <button type="button" className="certificates-action-btn" onClick={() => downloadCertificate(row)}>Download</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="certificates-footer">
          <div className="certificates-footer-left">Showing {rows.length} of {pagination.total} issued certificates</div>
          <div className="certificates-pagination">
            <button type="button" className="certificates-page-btn" disabled={pagination.page <= 1} onClick={() => setPagination((current) => ({ ...current, page: current.page - 1 }))}>Previous</button>
            <span className="certificates-page-indicator">Page {pagination.page} of {pagination.total_pages}</span>
            <button type="button" className="certificates-page-btn" disabled={pagination.page >= pagination.total_pages} onClick={() => setPagination((current) => ({ ...current, page: current.page + 1 }))}>Next</button>
          </div>
        </div>
      </section>

      {previewRow ? (() => {
        const donorName = previewRow.donor_display_name || previewRow.donor_name || "Donor";
        const totalDonations = previewRow.total_completed_donations || 0;
        const cid = previewRow.donor_display_cid || previewRow.cid || "—";
        const issueDate = formatDate(previewRow.issue_date);
        const bloodType = previewRow.blood_type || "N/A";
        const donorIdLabel = buildDonorIdLabel(previewRow);
        const qrUrl = buildQrUrl(previewRow);
        const subtitleText = milestoneCopy(previewRow.milestone_key);

        const shareCertificate = async () => {
          const text = `${previewRow.certificate_type || "Certificate"} — ${donorName} · ${totalDonations} donation${totalDonations === 1 ? "" : "s"} · ${donorIdLabel}`;
          if (typeof navigator !== "undefined" && navigator.share) {
            try {
              await navigator.share({ title: previewRow.certificate_type || "Donor Certificate", text });
              return;
            } catch {
              /* user cancelled */
            }
          }
          if (typeof navigator !== "undefined" && navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            toast.success("Certificate details copied to clipboard.");
          } else {
            toast.info("Sharing not supported on this device.");
          }
        };

        return (
          <div className="certificates-modal-overlay" onClick={() => setPreviewRow(null)}>
            <div className="certificates-modal cert-modal" onClick={(event) => event.stopPropagation()}>
              <div className="certificates-modal-header cert-modal-header">
                <h3>Certificate Preview</h3>
                <button type="button" className="certificates-modal-close" onClick={() => setPreviewRow(null)}>×</button>
              </div>

              <div className="cert-preview">
                <div className="cert-frame">
                  <span className="cert-corner cert-corner-tl"><CertCorner /></span>
                  <span className="cert-corner cert-corner-tr"><CertCorner /></span>
                  <span className="cert-corner cert-corner-bl"><CertCorner /></span>
                  <span className="cert-corner cert-corner-br"><CertCorner /></span>

                  <div className="cert-top-drop"><CertDropHeart /></div>

                  <h1 className="cert-title">CERTIFICATE</h1>
                  <div className="cert-subtitle">
                    <span className="cert-subtitle-line" />
                    <span className="cert-subtitle-text">{subtitleText}</span>
                    <span className="cert-subtitle-line" />
                  </div>

                  <p className="cert-intro">This is to proudly certify that</p>
                  <h2 className="cert-name">{donorName}</h2>
                  <p className="cert-body">
                    has generously donated blood and contributed to saving lives.<br />
                    Your selfless act of kindness and generosity<br />
                    is deeply appreciated.
                  </p>

                  <div className="cert-stats">
                    <div className="cert-stat cert-stat-left">
                      <span className="cert-stat-icon"><CertDropHeart /></span>
                      <div className="cert-stat-label">TOTAL DONATIONS</div>
                      <div className="cert-stat-value">{totalDonations}</div>
                      <div className="cert-stat-caption">Donations</div>
                    </div>
                    <div className="cert-seal-wrap"><CertSeal /></div>
                    <div className="cert-stat cert-stat-right">
                      <div className="cert-stat-label">BLOOD GROUP</div>
                      <div className="cert-stat-value">{bloodType}</div>
                    </div>
                  </div>

                  <div className="cert-footer-row">
                    <div className="cert-qr">
                      <img src={qrUrl} alt="QR code" />
                    </div>
                    <div className="cert-info">
                      <div className="cert-info-row"><span>Donor ID</span><i>:</i><strong className="cert-info-id">{donorIdLabel}</strong></div>
                      <div className="cert-info-row"><span>CID</span><i>:</i><strong>{cid}</strong></div>
                      <div className="cert-info-row"><span>Issue Date</span><i>:</i><strong>{issueDate}</strong></div>
                    </div>
                    <div className="cert-sign">
                      <div className="cert-sign-script">{user?.name || "Admin BTS"}</div>
                      <div className="cert-sign-line" />
                      <div className="cert-sign-name">{user?.name || "Admin BTS"}</div>
                      <div className="cert-sign-org">
                        {user?.role
                          ? `Blood Transfusion Service — ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}`
                          : "Blood Transfusion Service"}
                      </div>
                      <div className="cert-sign-org">Bhutan</div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="cert-modal-actions">
                <button type="button" className="cert-btn cert-btn-ghost" onClick={() => printCertificate(previewRow)}>
                  <IcPrinter /> Print Certificate
                </button>
                <button type="button" className="cert-btn cert-btn-ghost" onClick={() => downloadCertificate(previewRow)}>
                  <IcDownload /> Download PDF
                </button>
                <button type="button" className="cert-btn cert-btn-primary" onClick={shareCertificate}>
                  <IcShare /> Share Certificate
                </button>
              </div>
            </div>
          </div>
        );
      })() : null}
    </div>
  );
}
