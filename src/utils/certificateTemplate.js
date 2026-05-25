// Shared helpers for rendering donor milestone certificates.
// Used by CertificatesPanel (preview + print/download) and the
// "Generate Certificate" buttons in DonorDetailsPanel / DonorRecordsPanel.

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" }).format(date);
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

const milestoneKeyForCount = (count) => {
  const n = Number(count) || 0;
  if (n >= 20) return "milestone_20";
  if (n >= 10) return "milestone_10";
  return "milestone_5";
};

const buildDonorIdLabel = (certificate) => {
  if (certificate.certificate_number) return certificate.certificate_number;
  if (certificate.donor_id) return `DONOR-${String(certificate.donor_id).padStart(5, "0")}`;
  return "DONOR-00000";
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

// Build a certificate-shaped record from a donor object (used by the
// "Generate Certificate" buttons on donor detail/list views).
// `issuer` may carry { name, role } for the signature block; falls back to
// "Admin BTS" when omitted.
function certificateFromDonor(donor, issuer = null) {
  const total = Number(donor?.total_donations ?? donor?.total_completed_donations ?? donor?.summary?.total_donations ?? 0);
  const donorId = donor?.id ?? donor?.donor_id ?? donor?.basic?.id ?? 0;
  return {
    id: donorId,
    donor_id: donorId,
    donor_name: donor?.full_name || donor?.name || donor?.basic?.name || "Donor",
    donor_display_name: donor?.full_name || donor?.name || donor?.basic?.name || "Donor",
    cid: donor?.cid || donor?.cid_number || donor?.basic?.cid || "",
    donor_display_cid: donor?.cid || donor?.cid_number || donor?.basic?.cid || "",
    blood_type: donor?.blood_type || donor?.blood_group || donor?.basic?.blood_group || "N/A",
    total_completed_donations: total,
    milestone_key: milestoneKeyForCount(total),
    milestone_threshold: total >= 20 ? 20 : total >= 10 ? 10 : 5,
    certificate_type: "Certificate of Appreciation",
    certificate_number: donorId ? `DONOR-${String(donorId).padStart(5, "0")}` : null,
    issue_date: new Date().toISOString().slice(0, 10),
    signed_by_name: issuer?.name || "",
    signed_by_role: issuer?.role || "",
  };
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

  const cornerSvg = `<svg viewBox="0 0 90 90" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"><path d="M2 2h32M2 2v32"/><path d="M8 8c10 0 18 8 18 18"/><path d="M14 14c3 0 8 1 12 6 4 5 5 10 5 14" opacity=".7"/><circle cx="6" cy="6" r="1.2" fill="currentColor" stroke="none"/><path d="M30 12c-2 4-2 8 0 12" opacity=".55"/><path d="M12 30c4-2 8-2 12 0" opacity=".55"/></svg>`;

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
        <div class="corner c-tl">${cornerSvg}</div>
        <div class="corner c-tr">${cornerSvg}</div>
        <div class="corner c-bl">${cornerSvg}</div>
        <div class="corner c-br">${cornerSvg}</div>

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

function openCertificateWindow(certificate) {
  const html = buildCertificateHtml(certificate);
  const popup = window.open("", "_blank", "width=1100,height=860,scrollbars=yes,resizable=yes");
  if (!popup) return false;
  popup.document.open();
  popup.document.write(html);
  popup.document.close();
  popup.focus();
  return true;
}

export {
  formatDate,
  milestoneBadge,
  milestoneCopy,
  milestoneKeyForCount,
  buildDonorIdLabel,
  buildQrUrl,
  buildCertificateHtml,
  certificateFromDonor,
  openCertificateWindow,
};
