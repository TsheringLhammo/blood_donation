const API_BASE = "/blood_donation";

export const getApiBase = () => API_BASE;

export const getStoredUser = () => {
  try {
    const raw = localStorage.getItem("bts_user");
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== "object") return null;
    return parsed;
  } catch {
    return null;
  }
};

export const saveAuthSession = (user) => {
  localStorage.setItem("bts_user", JSON.stringify(user));
};

export const clearAuthSession = () => {
  localStorage.removeItem("bts_user");
};

export const isUserAuthorized = (roles = []) => {
  const user = getStoredUser();
  if (!user || !user.token) return false;
  if (!Array.isArray(roles) || roles.length === 0) return true;
  return roles.includes(user.role);
};

export const authFetch = async (path, options = {}) => {
  const user = getStoredUser();
  const headers = {
    ...(options.headers || {}),
  };

  if (user?.token) {
    headers.Authorization = `Bearer ${user.token}`;
  }

  const normalizedPath = String(path || "").replace(/^\//, "");

  if (normalizedPath.startsWith("http://") || normalizedPath.startsWith("https://")) {
    const response = await fetch(path, { ...options, headers });
    return response;
  }

  const candidates = [];
  const addCandidate = (value) => {
    if (value && !candidates.includes(value)) candidates.push(value);
  };

  const fileName = normalizedPath.split("/").pop();
  if (!normalizedPath.startsWith("api/") && !normalizedPath.startsWith("backend/api/")) {
    addCandidate(`${API_BASE}/backend/api/${normalizedPath}`);
    addCandidate(`${API_BASE}/api/${normalizedPath}`);
    addCandidate(`${API_BASE}/${normalizedPath}`);

    if (fileName) {
      addCandidate(`${API_BASE}/backend/api/${fileName}`);
      addCandidate(`${API_BASE}/api/${fileName}`);
    }
  } else {
    addCandidate(`${API_BASE}/${normalizedPath}`);
  }

  // Walk the candidate URLs in order. Only fall through to the next
  // candidate when the endpoint truly isn't there (404 / network error /
  // Apache returned an HTML page). Real responses (401, 403, 5xx, JSON
  // payloads) belong to the first matching endpoint — return them
  // immediately so we don't fire noisy 404s for the alternates.
  let lastResponse = null;
  let notFoundError = null;

  for (const url of candidates) {
    let response;
    try {
      response = await fetch(url, {
        ...options,
        headers,
        mode: "cors",
      });
    } catch {
      continue;
    }

    lastResponse = response;

    if (response.status === 404) {
      notFoundError = response;
      continue;
    }

    const contentType = response.headers.get("content-type") || "";
    const isJson = contentType.includes("application/json");

    if (!isJson && response.ok) {
      // 200 OK but not JSON — could be Apache directory listing or the
      // SPA fallback page. Try the next candidate.
      const preview = (await response.clone().text()).trimStart();
      if (preview.startsWith("<")) {
        continue;
      }
    }

    return response;
  }

  if (notFoundError) {
    return notFoundError;
  }

  return lastResponse || fetch(`${API_BASE}/${normalizedPath}`, { ...options, headers, mode: 'cors' });
};
