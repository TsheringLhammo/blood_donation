import { authFetch } from "../utils/auth";

export const getAllDonors = async () => {
  const response = await authFetch("admin/api/donors.php?status=all");
  if (!response.ok) {
    throw new Error("Failed to fetch donors.");
  }
  const data = await response.json();
  return data.data || [];
};

export const approveDonor = async (donorId) => {
  const response = await authFetch("admin/api/approve-donor.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ donor_id: donorId }),
  });
  if (!response.ok) {
    const body = await response.text();
    throw new Error(body || "Failed to approve donor.");
  }
  const data = await response.json();
  return data;
};

export const approveSampleTest = async (sampleId) => {
  const response = await authFetch("backend/api/approve_sample_test.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ sample_id: sampleId }),
  });
  if (!response.ok) {
    const body = await response.text();
    throw new Error(body || "Failed to approve sample.");
  }
  const data = await response.json();
  return data;
};

export const rejectDonor = async (donorId, reason) => {
  const response = await authFetch("admin/api/reject-donor.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ donor_id: donorId, reason }),
  });
  if (!response.ok) {
    const body = await response.text();
    throw new Error(body || "Failed to reject donor.");
  }
  const data = await response.json();
  return data;
};