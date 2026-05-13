import React from "react";

const statusClassMap = {
  pending: "bg-amber-100 text-amber-800",
  confirmed: "bg-emerald-100 text-emerald-800",
  active: "bg-emerald-100 text-emerald-800",
  rejected: "bg-rose-100 text-rose-800",
  deferred: "bg-orange-100 text-orange-800",
};

const statusLabelMap = {
  pending: "Pending",
  confirmed: "Confirmed",
  active: "Confirmed",
  rejected: "Rejected",
  deferred: "Deferred",
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
      border: "1px solid #e2e8f0",
      borderRadius: 12,
      background: "#f8fafc",
      padding: "10px 12px",
    }}
  >
    <p
      className="text-xs font-semibold uppercase tracking-wide text-slate-500"
      style={{
        margin: 0,
        fontSize: 11,
        fontWeight: 700,
        letterSpacing: "0.06em",
        textTransform: "uppercase",
        color: "#64748b",
      }}
    >
      {label}
    </p>
    <p
      className="mt-1 text-sm text-slate-800"
      style={{ margin: "6px 0 0", fontSize: 14, fontWeight: 500, color: "#0f172a" }}
    >
      {value || "-"}
    </p>
  </div>
);

export default function DonorDetailsPanel({ open, loading, error, donor, onClose }) {
  if (!open) return null;

  const statusKey = String(donor?.status || "pending").toLowerCase();
  const statusLabel = statusLabelMap[statusKey] || donor?.status || "Pending";
  const statusClass = statusClassMap[statusKey] || "bg-slate-100 text-slate-700";

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
          className="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur"
          style={{
            position: "sticky",
            top: 0,
            zIndex: 10,
            borderBottom: "1px solid #e2e8f0",
            background: "linear-gradient(180deg, #fff 0%, #fff7ed 100%)",
            padding: "18px 20px",
          }}
        >
          <div className="flex items-start justify-between gap-4" style={{ display: "flex", justifyContent: "space-between", gap: 16 }}>
            <div>
              <h2 id="donor-details-title" className="text-lg font-semibold text-slate-900" style={{ margin: 0, fontSize: 24, lineHeight: "30px", color: "#7f1d1d" }}>Donor Details</h2>
              <p className="mt-1 text-sm text-slate-600" style={{ margin: "6px 0 0", fontSize: 13, color: "#6b7280" }}>Review complete profile and current eligibility status.</p>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
              style={{
                border: "1px solid #d1d5db",
                borderRadius: 10,
                background: "#fff",
                color: "#374151",
                fontWeight: 600,
                fontSize: 13,
                padding: "8px 14px",
                cursor: "pointer",
              }}
            >
              Close
            </button>
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
              <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", border: "1px solid #e2e8f0", borderRadius: 14, background: "#fffaf5", padding: "12px 14px" }}>
                <div>
                  <p className="text-xs uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 11, letterSpacing: "0.06em", textTransform: "uppercase", color: "#78716c" }}>Current Status</p>
                  <span className={`mt-1 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusClass}`} style={{ marginTop: 6, display: "inline-flex", borderRadius: 9999, padding: "5px 10px", fontSize: 12, fontWeight: 700 }}>
                    {statusLabel}
                  </span>
                </div>
                {statusKey === "deferred" && (
                  <div className="text-right text-sm text-slate-700" style={{ textAlign: "right", color: "#334155", fontSize: 13 }}>
                    <p className="font-medium" style={{ margin: 0, fontWeight: 700 }}>Deferred Until</p>
                    <p style={{ margin: "4px 0 0" }}>{toDisplayDate(donor.deferred_until)}</p>
                  </div>
                )}
              </div>

              <section className="space-y-3" style={{ display: "grid", gap: 10 }}>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "#64748b" }}>Basic Profile</h3>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 10 }}>
                  <Field label="Full Name" value={donor.full_name} />
                  <Field label="Email" value={donor.email} />
                  <Field label="Phone" value={donor.phone} />
                  <Field label="Date of Birth" value={toDisplayDate(donor.date_of_birth)} />
                  <Field label="Gender" value={donor.gender} />
                </div>
              </section>

              <section className="space-y-3" style={{ display: "grid", gap: 10 }}>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "#64748b" }}>Donation Details</h3>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 10 }}>
                  <Field label="Blood Type" value={donor.blood_type} />
                  <Field label="Weight" value={donor.weight ? `${donor.weight} kg` : "-"} />
                  <Field label="City" value={donor.city} />
                  <Field label="Dzongkhag" value={donor.dzongkhag} />
                  <Field label="Last Donation Date" value={toDisplayDate(donor.last_donation_date)} />
                </div>
              </section>

              {statusKey === "deferred" && (
                <section className="space-y-3" style={{ display: "grid", gap: 10 }}>
                  <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "#9a3412" }}>Deferral</h3>
                  <div className="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900" style={{ border: "1px solid #fed7aa", borderRadius: 12, background: "#fff7ed", padding: "12px 14px", color: "#9a3412", fontSize: 14 }}>
                    <p style={{ margin: 0 }}><span className="font-semibold" style={{ fontWeight: 700 }}>Reason:</span> {donor.deferral_reason || "-"}</p>
                    <p className="mt-1" style={{ margin: "8px 0 0" }}><span className="font-semibold" style={{ fontWeight: 700 }}>Deferred Until:</span> {toDisplayDate(donor.deferred_until)}</p>
                  </div>
                </section>
              )}

              <section className="space-y-3" style={{ display: "grid", gap: 10 }}>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500" style={{ margin: 0, fontSize: 12, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "#64748b" }}>Health Declaration Summary</h3>
                <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 whitespace-pre-wrap" style={{ border: "1px solid #e2e8f0", borderRadius: 12, background: "#f8fafc", padding: "12px 14px", fontSize: 14, color: "#1f2937", whiteSpace: "pre-wrap", lineHeight: 1.5 }}>
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
