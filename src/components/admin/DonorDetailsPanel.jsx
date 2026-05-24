import React from "react";

// Map numeric status codes and string variations to normalized values
const normalizeStatus = (status) => {
  if (!status && status !== 0) return "pending";
  
  const statusStr = String(status).toLowerCase().trim();
  
  // Numeric status codes
  const numericMap = {
    "0": "approved",      // 0 in database means approved
    "1": "confirmed",
    "2": "active",
    "3": "rejected",
    "4": "deferred",
  };
  
  if (numericMap[statusStr]) {
    return numericMap[statusStr];
  }
  
  // String status normalization - matches exact database values
  if (statusStr === "approved donor" || statusStr.includes("approved")) return "approved";
  if (statusStr === "ready for blood draw") return "ready";
  if (statusStr === "eligible") return "eligible";
  if (statusStr.includes("confirmed")) return "confirmed";
  if (statusStr.includes("active")) return "active";
  if (statusStr === "pending" || statusStr.includes("pending")) return "pending";
  if (statusStr.includes("permanently deferred")) return "permanently_deferred";
  if (statusStr.includes("temporarily deferred") || statusStr.includes("deferred")) return "deferred";
  if (statusStr.includes("reject")) return "rejected";
  
  return statusStr; // Return as-is if not matched
};

const statusClassMap = {
  pending: "bg-amber-100 text-amber-800",
  approved: "bg-green-100 text-green-800",
  confirmed: "bg-emerald-100 text-emerald-800",
  active: "bg-emerald-100 text-emerald-800",
  ready: "bg-blue-100 text-blue-800",
  eligible: "bg-green-100 text-green-800",
  rejected: "bg-rose-100 text-rose-800",
  deferred: "bg-orange-100 text-orange-800",
  permanently_deferred: "bg-rose-200 text-rose-900",
};

const statusLabelMap = {
  pending: "⏳ Pending Approval",
  approved: "✅ Approved",
  confirmed: "✅ Confirmed",
  active: "⭐ Active",
  ready: "🩸 Ready for Blood Draw",
  eligible: "✅ Eligible",
  rejected: "❌ Rejected",
  deferred: "⏸️ Temporarily Deferred",
  permanently_deferred: "🚫 Permanently Deferred",
};

const getStatusLabel = (status) => {
  const normalized = normalizeStatus(status);
  return statusLabelMap[normalized] || normalized;
};

const getStatusClass = (status) => {
  const normalized = normalizeStatus(status);
  return statusClassMap[normalized] || "bg-slate-100 text-slate-700";
};

const getStatusKey = (status) => {
  return normalizeStatus(status);
};

const toDisplayDate = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString();
};

const Field = ({ label, value }) => (
  <div
    className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
    style={{
      border: "1.5px solid #d5dce7",
      borderRadius: 12,
      background: "#f9fbfd",
      padding: "14px 16px",
      transition: "all 0.2s ease",
    }}
    onMouseEnter={(e) => {
      e.currentTarget.style.borderColor = "#1a5fa8";
      e.currentTarget.style.background = "#f0f7ff";
      e.currentTarget.style.boxShadow = "0 2px 8px rgba(26, 95, 168, 0.08)";
    }}
    onMouseLeave={(e) => {
      e.currentTarget.style.borderColor = "#d5dce7";
      e.currentTarget.style.background = "#f9fbfd";
      e.currentTarget.style.boxShadow = "none";
    }}
  >
    <p
      className="text-xs font-semibold uppercase tracking-wide text-slate-500"
      style={{
        margin: 0,
        fontSize: 10,
        fontWeight: 800,
        letterSpacing: "0.08em",
        textTransform: "uppercase",
        color: "#0d3d7a",
      }}
    >
      {label}
    </p>
    <p
      className="mt-1 text-sm text-slate-800"
      style={{ margin: "8px 0 0", fontSize: 15, fontWeight: 500, color: "#0f172a" }}
    >
      {value || "-"}
    </p>
  </div>
);

export default function DonorDetailsPanel({ open, loading, error, donor, onClose }) {
  if (!open) return null;

  const statusKey = getStatusKey(donor?.status);
  const statusLabel = getStatusLabel(donor?.status);
  const statusClass = getStatusClass(donor?.status);

  const buildCardHtml = (d) => {
    const donorName = d?.full_name || 'Donor';
    const donorId = `DONOR-${String(d?.id || 0).padStart(5, '0')}`;
    const fullCid = String(d?.cid || d?.cid_number || '').trim() || 'N/A';
    const bloodGroup = d?.blood_type || d?.blood_group || 'N/A';
    const issueDate = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const initials = donorName.split(/\s+/).filter(Boolean).slice(0,2).map(p => p.charAt(0).toUpperCase()).join('') || 'D';
    const qrValue = encodeURIComponent(`donor:${donorId}|cid:${fullCid}|name:${donorName}|group:${bloodGroup}`);

    return `<!doctype html><html><head><meta charset="utf-8"><title>Donor Card - ${donorName}</title><meta name="viewport" content="width=device-width,initial-scale=1"/><style>body{font-family:Arial,sans-serif;margin:0;background:#f5f0eb;color:#111827} .card-wrap{max-width:980px;margin:28px auto;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.12);overflow:hidden} .top{background:linear-gradient(90deg,#bf131e,#9a111c);color:#fff;padding:18px 26px;display:flex;justify-content:space-between;align-items:center} .brand{font-weight:800} .pill{background:#fff;color:#b31622;padding:8px 16px;border-radius:999px;font-weight:800} .body{padding:22px} .main{display:grid;grid-template-columns:160px 1fr 170px;gap:18px;align-items:start} .avatar{width:138px;height:138px;border-radius:50%;border:4px solid #bf131e;display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:800;color:#2f3a4a;background:radial-gradient(circle at 30% 20%,#fff,#f0f3f8)} .details h2{margin:0;font-size:40px;color:#b31622} .row{display:grid;grid-template-columns:132px 12px 1fr;gap:8px;padding:6px 0;font-size:20px} .label{color:#4b5563;font-weight:600} .value{font-weight:800} .qr{border:1px solid #e5e7eb;border-radius:14px;padding:10px;display:flex;align-items:center;justify-content:center} .footer{background:linear-gradient(90deg,#b0111c,#8e0f18);color:#fff;padding:14px 20px;font-weight:700;display:flex;align-items:center;gap:10px} .actions{display:flex;gap:16px;justify-content:center;padding:18px}</style></head><body><div class="card-wrap"><div class="top"><div class="brand">BLOOD TRANSFUSION SERVICE<br/>ROYAL GOVERNMENT OF BHUTAN</div><div class="pill">DONOR CARD</div></div><div class="body"><div class="main"><div class="avatar">${initials}</div><div class="details"><h2>${donorName}</h2><div style="font-weight:700;margin-bottom:12px">Donor ID: ${donorId}</div><div class="row"><div class="label">CID</div><div>:</div><div class="value">${fullCid}</div></div><div class="row"><div class="label">Blood Group</div><div>:</div><div class="value">${bloodGroup}</div></div><div class="row"><div class="label">Issue Date</div><div>:</div><div class="value">${issueDate}</div></div><div class="row"><div class="label">Blood Type</div><div>:</div><div class="value">Whole Blood</div></div></div><div class="qr"><img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${qrValue}" alt="qr"/></div></div></div><div class="footer">❤ Every Drop Counts, Every Donor Matters</div></div><div class="actions"><button onclick="window.print()" style="padding:10px 16px;border-radius:8px;border:1px solid #ddd;background:#fff;font-weight:700;">Print Card</button><button onclick="(function(){if(window.opener){window.opener.postMessage({type:'donor-card-download',filename:'${donorId.toLowerCase()}-card.html',html:document.documentElement.outerHTML}, '*');}else{var a=document.createElement('a');var blob=new Blob([document.documentElement.outerHTML],{type:'text/html'});a.href=URL.createObjectURL(blob);a.download='${donorId.toLowerCase()}-card.html';document.body.appendChild(a);a.click();a.remove();}})()" style="padding:10px 16px;border-radius:8px;border:1px solid #ddd;background:#fff;font-weight:700;margin-left:8px;">Download</button><button onclick="(function(){var w=window.open('','_blank','width=1400,height=980,scrollbars=yes,resizable=yes');if(!w) return;w.document.open();w.document.write(document.documentElement.outerHTML);w.document.close();w.focus();})()" style="padding:10px 16px;border-radius:8px;border:0;background:#b31622;color:#fff;font-weight:700;margin-left:8px;">View Full Size</button></div></body></html>`;
  };

  const openCardPreview = () => {
    const html = buildCardHtml(donor || {});
    const popup = window.open('', '_blank', 'width=1100,height=760,scrollbars=yes,resizable=yes');
    if (!popup) {
      alert('Popup blocked. Allow popups and try again.');
      return;
    }
    try { popup.document.write(html); popup.document.close(); } catch (e) { console.error(e); }
  };

  return (
    <div
      className="fixed inset-0 z-50"
      role="dialog"
      aria-modal="true"
      aria-labelledby="donor-details-title"
      style={{ position: "fixed", inset: 0, zIndex: 9999 }}
    >
      <div
        className="absolute inset-0 bg-slate-900/40"
        onClick={onClose}
        style={{ position: "absolute", inset: 0, background: "rgba(15, 23, 42, 0.45)" }}
      />
      <aside
        className="absolute right-0 top-0 h-full w-full max-w-xl overflow-y-auto bg-white shadow-2xl"
        style={{
          position: "absolute",
          right: 0,
          top: 0,
          height: "100%",
          width: "100%",
          maxWidth: 560,
          overflowY: "auto",
          background: "#fff",
          boxShadow: "0 20px 45px rgba(2, 6, 23, 0.35)",
        }}
      >
        <div
          className="sticky top-0 z-10"
          style={{ position: "sticky", top: 0, zIndex: 10 }}
        >
          {/* Card-like header matching the donor card design */}
          <div style={{ background: "linear-gradient(90deg, #b3151b, #8f0f17)", color: "#fff", padding: "20px", borderRadius: "8px 8px 0 0" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 18, maxWidth: "100%" }}>
              <div style={{ flex: "0 0 auto" }}>
                <div style={{ width: 88, height: 88, borderRadius: 88, border: "4px solid rgba(255,255,255,0.2)", background: "radial-gradient(circle at 30% 20%, #fff,#f0f3f8)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 30, fontWeight: 800, color: "#2f3a4a" }}>
                  {(() => {
                    const name = donor?.full_name || donor?.name || "D";
                    const parts = String(name).split(/\s+/).filter(Boolean).slice(0,2);
                    return parts.map(p => p.charAt(0).toUpperCase()).join("") || "D";
                  })()}
                </div>
              </div>

              <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                <h2 style={{ margin: 0, fontSize: 30, fontWeight: 900, color: "#fff", lineHeight: 1 }}>{donor?.full_name || donor?.name || "Donor"}</h2>
                <div style={{ marginTop: 8, display: "flex", gap: 12, alignItems: "center", color: "rgba(255,255,255,0.95)", fontWeight: 700 }}>
                  <div>CID {String(donor?.cid || donor?.cid_number || "").trim() || "N/A"}</div>
                  <div>·</div>
                  <div>{donor?.blood_type || donor?.blood_group || "N/A"}</div>
                  <div>·</div>
                  <div style={{ background: "#fff", color: "#b31622", padding: "6px 10px", borderRadius: 20, fontWeight: 800, fontSize: 13 }}>{statusLabel}</div>
                </div>
              </div>

              <div style={{ flex: "0 0 auto" }}>
                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Close"
                  style={{ width: 44, height: 44, borderRadius: 22, border: 0, background: "rgba(255,255,255,0.12)", color: "#fff", fontSize: 20, cursor: "pointer" }}
                >
                  ×
                </button>
              </div>
            </div>
          </div>
        </div>

        <div className="p-5" style={{ padding: 20 }}>
          {loading && (
            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700" style={{ border: "1px solid #e2e8f0", borderRadius: 12, background: "#f8fafc", padding: 14, color: "#334155" }}>
              Loading donor details...
            </div>
          )}

          {!loading && error && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" style={{ border: "1px solid #fecaca", borderRadius: 12, background: "#fff1f2", padding: 14, color: "#be123c" }}>
              {error}
            </div>
          )}

          {!loading && !error && donor && (
            <div className="space-y-5" style={{ display: "grid", gap: 14 }}>
              <div className="relative overflow-hidden rounded-xl border-2 border-slate-200 bg-gradient-to-br from-slate-50 to-slate-100 p-5" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", border: "2px solid #d1d5db", borderRadius: 14, background: "linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%)", padding: "18px 20px", position: "relative", overflow: "hidden" }}>
                <div style={{ position: "relative", zIndex: 2 }}>
                  <p className="text-xs uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", color: "#64748b", fontWeight: 700 }}>Current Status</p>
                  <div style={{ marginTop: 10, display: "flex", gap: 10, alignItems: "center" }}>
                    <span className={`inline-flex rounded-full px-4 py-2 text-sm font-bold ${statusClass}`} style={{ display: "inline-flex", borderRadius: 9999, padding: "8px 16px", fontSize: 13, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.05em" }}>
                      {statusLabel}
                    </span>
                  </div>
                </div>
                {statusKey === "deferred" && (
                  <div className="text-right text-sm text-slate-700" style={{ textAlign: "right", color: "#334155", fontSize: 13, position: "relative", zIndex: 2 }}>
                    <p className="font-medium" style={{ margin: 0, fontWeight: 700 }}>Deferred Until</p>
                    <p style={{ margin: "6px 0 0", fontSize: 12, color: "#6b7280" }}>{toDisplayDate(donor.deferred_until)}</p>
                  </div>
                )}
                <div style={{ position: "absolute", top: "-40px", right: "-40px", width: 200, height: 200, background: "radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%)", borderRadius: "50%" }}></div>
              </div>

              <section className="space-y-3" style={{ display: "grid", gap: 12 }}>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.08em", color: "#0d3d7a", paddingBottom: 8, borderBottom: "2px solid #3b82f6" }}>👤 Basic Profile</h3>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12 }}>
                  <Field label="Full Name" value={donor.full_name} />
                  <Field label="Email" value={donor.email} />
                  <Field label="Phone" value={donor.phone} />
                  <Field label="Date of Birth" value={toDisplayDate(donor.date_of_birth)} />
                  <Field label="Gender" value={donor.gender} />
                </div>
              </section>
              {/* Footer actions: Generate Certificate, View ID Card, Export History */}
              <div style={{ display: 'flex', gap: 12, justifyContent: 'flex-start', alignItems: 'center', paddingTop: 8 }}>
                <button className="inline-flex items-center rounded-md px-4 py-2" style={{ background: '#b31622', color: '#fff', borderRadius: 10, padding: '10px 16px', fontWeight: 800 }} onClick={() => { /* certificate logic may already exist elsewhere */ window.print(); }}>
                  Generate Certificate
                </button>
                <button className="inline-flex items-center rounded-md px-4 py-2" style={{ background: '#fff', border: '1px solid #e5e7eb', color: '#7f1d1d', fontWeight: 800, padding: '10px 16px', borderRadius: 10 }} onClick={openCardPreview}>
                  View ID Card
                </button>
                <button className="inline-flex items-center rounded-md px-4 py-2" style={{ background: '#fff', border: '1px solid #e5e7eb', color: '#7f1d1d', fontWeight: 700, padding: '10px 16px', borderRadius: 10 }} onClick={() => { /* export history fallback */ alert('Exporting history...'); }}>
                  Export History
                </button>
              </div>

              <section className="space-y-3" style={{ display: "grid", gap: 12 }}>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.08em", color: "#c0001c", paddingBottom: 8, borderBottom: "2px solid #c0001c" }}>🩸 Donation Details</h3>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12 }}>
                  <Field label="Blood Type" value={donor.blood_type} />
                  <Field label="Weight" value={donor.weight ? `${donor.weight} kg` : "-"} />
                  <Field label="City" value={donor.city} />
                  <Field label="Dzongkhag" value={donor.dzongkhag} />
                  <Field label="Last Donation Date" value={toDisplayDate(donor.last_donation_date)} />
                </div>
              </section>

              {statusKey === "deferred" && (
                <section className="space-y-3" style={{ display: "grid", gap: 12 }}>
                  <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.08em", color: "#9a3412", paddingBottom: 8, borderBottom: "2px solid #f97316" }}>⏸️ Deferral Information</h3>
                  <div className="rounded-lg border-2 border-orange-200 bg-gradient-to-r from-orange-50 to-orange-100 px-5 py-4 text-sm text-orange-900" style={{ border: "2px solid #fed7aa", borderRadius: 12, background: "linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)", padding: "16px 18px", color: "#92400e", fontSize: 14, lineHeight: 1.6 }}>
                    <p style={{ margin: "0 0 10px 0", fontWeight: 700 }}>⚠️ Reason for Deferral:</p>
                    <p style={{ margin: 0 }}>{donor.deferral_reason || "-"}</p>
                    <p style={{ margin: "12px 0 0 0", fontWeight: 700 }}>📅 Deferred Until:</p>
                    <p style={{ margin: 0 }}>{toDisplayDate(donor.deferred_until)}</p>
                  </div>
                </section>
              )}

              <section className="space-y-3" style={{ display: "grid", gap: 12 }}>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.08em", color: "#0d3d7a", paddingBottom: 8, borderBottom: "2px solid #3b82f6" }}>📋 Health Declaration Summary</h3>
                <div className="rounded-lg border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-800 whitespace-pre-wrap font-mono text-xs" style={{ border: "1px solid #e2e8f0", borderRadius: 12, background: "#f8fafc", padding: "16px 18px", fontSize: 13, color: "#1f2937", whiteSpace: "pre-wrap", lineHeight: 1.6, fontFamily: "monospace" }}>
                  {donor.health_declaration_summary || "-"}
                </div>
              </section>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}
