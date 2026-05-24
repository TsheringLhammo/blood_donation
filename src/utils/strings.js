export function titleCase(input) {
  if (!input && input !== "") return input;
  const s = String(input || "").trim().replace(/\s+/g, " ");
  if (!s) return s;
  return s
    .toLowerCase()
    .split(' ')
    .map((part) => {
      // Preserve internal punctuation like hyphens or apostrophes
      return part
        .split(/([-'])/)
        .map(p => p.length ? (p.charAt(0).toUpperCase() + p.slice(1)) : p)
        .join('');
    })
    .join(' ');
}

export function maskCidNumber(input) {
  const value = String(input || "").replace(/\D/g, "");
  if (!value) return "";
  const suffix = value.slice(-4);
  return `${"X".repeat(Math.max(0, value.length - 4))}${suffix}`;
}
