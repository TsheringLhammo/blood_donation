import React, { useEffect, useState, useCallback, useRef, useMemo } from "react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import "./StaffDashboard.css";
import { authFetch, clearAuthSession, getStoredUser } from "../utils/auth";

const DEFAULT_STATS = {
  incomingRequests: 0,
  crossMatchQueue: 0,
  unitsIssuedToday: 0,
  lowStockAlerts: 0,
};

const toSafeUnits = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

const getInventoryTotal = (row) => (
  toSafeUnits(row?.whole_units)
  + toSafeUnits(row?.prbc_units)
  + toSafeUnits(row?.platelets_units)
  + toSafeUnits(row?.ffp_units)
);

const normalizeRequestStatus = (status) => {
  const normalized = String(status || "").trim().toLowerCase();
  if (normalized === "cross-match" || normalized === "crossmatch") return "cross-matching";
  if (normalized === "cross-match complete" || normalized === "crossmatch complete" || normalized === "ready to issue") return "matched";
  if (normalized === "cross-match failed") return "rejected";
  return normalized;
};

const getDisplayStatusLabel = (status) => {
  const normalized = normalizeRequestStatus(status);
  if (normalized === "matched") return "Ready to Issue";
  if (normalized === "cross-matching") return "Cross-Matching";
  if (normalized === "rejected") return "Rejected";
  if (normalized === "issued") return "Issued";
  if (normalized === "approved") return "Approved";
  if (normalized === "pending") return "Pending";
  return status;
};

const shouldShowAvailableUnitsForStatus = (status) => {
  const normalized = normalizeRequestStatus(status);
  // Don't show unit picker for matched, issued, or rejected - they use Issue Blood button instead
  return normalized !== "issued" && normalized !== "rejected" && normalized !== "matched";
};

const parseAvailableUnitIds = (value) => {
  if (!value) return [];
  return String(value)
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);
};

const parseApiJson = async (response) => {
  const raw = String(await response.text() || "").replace(/^\uFEFF/, "").trim();
  if (!raw) {
    return {
      success: false,
      message: "Empty response from server. Check backend logs and ensure API returns JSON.",
    };
  }

  try {
    return JSON.parse(raw);
  } catch {
    const firstBrace = raw.indexOf("{");
    const lastBrace = raw.lastIndexOf("}");
    if (firstBrace !== -1 && lastBrace > firstBrace) {
      try {
        return JSON.parse(raw.slice(firstBrace, lastBrace + 1));
      } catch {
        // Fall through to formatted parse error below.
      }
    }

    return {
      success: false,
      message: `Server returned non-JSON response: ${raw.slice(0, 180)}`,
    };
  }
};

export default function StaffDashboard() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [stats, setStats] = useState(DEFAULT_STATS);
  const [incomingRequests, setIncomingRequests] = useState([]);
  const [inventoryRows, setInventoryRows] = useState([]);
  const [labUseLogs, setLabUseLogs] = useState([]);
  const [issueLogs, setIssueLogs] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [popupNotice, setPopupNotice] = useState("");
  const [actionError, setActionError] = useState("");
  const [busyKey, setBusyKey] = useState("");
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState("requests");
  const [isNotificationOpen, setIsNotificationOpen] = useState(false);
  const notificationPanelRef = useRef(null);
  const notificationButtonRef = useRef(null);
  const [labResultFilter, setLabResultFilter] = useState("all");
  const [selectedLabLog, setSelectedLabLog] = useState(null);
  const [selectedUnitsByRequest, setSelectedUnitsByRequest] = useState({});
  const [addInventoryModal, setAddInventoryModal] = useState({
    open: false,
    donationId: "",
    donorId: 0,
    bloodType: "",
    component: "Packed Red Cells",
    expiryDate: "",
    status: "Available",
  });
  const [confirmedDonors, setConfirmedDonors] = useState([]);
  const [confirmedDonorsLoading, setConfirmedDonorsLoading] = useState(false);
  const [confirmedDonorsError, setConfirmedDonorsError] = useState("");
  const [donorSamples, setDonorSamples] = useState([]);
  const [donorSamplesLoading, setDonorSamplesLoading] = useState(false);
  const [donorSamplesError, setDonorSamplesError] = useState("");
  const [sampleCollectionForm, setSampleCollectionForm] = useState({
    donorId: 0,
    collectionDate: new Date().toISOString().slice(0, 10),
    collectionTime: "10:00",
    technician: "",
  });
  const [sampleTestForm, setSampleTestForm] = useState({
    sampleId: 0,
    technician: "",
    hivResult: "Non-reactive",
    hbsagResult: "Non-reactive",
    hcvResult: "Non-reactive",
    syphilisResult: "Non-reactive",
    malariaResult: "Non-reactive",
    notes: "",
  });
  const [healthCheckForm, setHealthCheckForm] = useState({
    hemoglobin: "",
    bloodPressure: "",
    pulse: "",
    temperature: "",
    weight: "",
  });
  const [healthCheckStatus, setHealthCheckStatus] = useState({
    hemoglobin: "",
    bloodPressure: "",
    pulse: "",
    temperature: "",
    weight: "",
  });
  const [deferralDecision, setDeferralDecision] = useState("approve");
  const [deferralPeriod, setDeferralPeriod] = useState("3 months");
  const [deferralReason, setDeferralReason] = useState("");
  const [extraDiseaseRows, setExtraDiseaseRows] = useState([
    { name: "", result: "Negative" },
  ]);
  const [crossMatchModal, setCrossMatchModal] = useState({
    open: false,
    requestId: null,
    result: "Compatible",
    donorUnitRefs: "",
    availableUnits: [],
    loadingUnits: false,
    testParameters: "",
    notes: "",
  });
  const [issueBloodModal, setIssueBloodModal] = useState({
    open: false,
    requestId: null,
    request: null,
    compatibleUnitId: "",
    isEmergency: false,
    staffConfirmedBy: "",
    comment: "",
  });
  const [untestedDonations, setUntestedDonations] = useState([]);
  const [testForm, setTestForm] = useState({
    donationId: "",
    hivResult: "Non-reactive",
    hbsagResult: "Non-reactive",
    hcvResult: "Non-reactive",
    syphilisResult: "Non-reactive",
    malariaResult: "Non-reactive",
    remarks: "",
  });

  const getAvailableUnitsForRequest = useCallback((request) => {
    if (!request) return [];
    return parseAvailableUnitIds(request.available_unit_ids);
  }, []);

  const getSelectedUnitForRequest = useCallback((request) => {
    const units = getAvailableUnitsForRequest(request);
    const selected = selectedUnitsByRequest[request?.id];
    if (selected && units.includes(selected)) {
      return selected;
    }
    return units[0] || "";
  }, [getAvailableUnitsForRequest, selectedUnitsByRequest]);

  const handleAvailableUnitChange = useCallback((requestId, unitId) => {
    setSelectedUnitsByRequest((prev) => ({
      ...prev,
      [requestId]: unitId,
    }));
  }, []);

  const copyUnitIdToClipboard = useCallback(async (unitId) => {
    const value = String(unitId || "").trim();
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      setPopupNotice(`Copied donor unit ID: ${value}`);
      setActionError("");
    } catch {
      setActionError("Could not copy donor unit ID. Please copy manually.");
    }
  }, []);

  const normalizeDonorSampleState = useCallback((value) => String(value || "Pending").trim().toLowerCase(), []);
  const confirmedDonorOptions = confirmedDonors;
  const sampleCollectionDonors = useMemo(() => {
    const seen = new Set();
    // Filter to only include donors that don't already have samples
    // The API now handles this by checking tbldonor_samples, so we just need to remove duplicates by name
    return confirmedDonorOptions.filter((donor) => {
      const nameKey = String(donor.full_name || "").trim().toLowerCase();
      if (seen.has(nameKey)) return false;
      seen.add(nameKey);
      return true;
    });
  }, [confirmedDonorOptions]);
  const inventoryDonorsForSelectedBloodType = useMemo(() => {
    const selectedBloodType = String(addInventoryModal.bloodType || "").trim();
    if (!selectedBloodType) return [];

    return confirmedDonorOptions.filter((donor) => {
      const donorBloodType = String(donor.blood_type || donor.bloodType || "").trim();
      return donorBloodType === selectedBloodType;
    });
  }, [addInventoryModal.bloodType, confirmedDonorOptions]);

  useEffect(() => {
    const parsed = getStoredUser();
    if (!parsed?.token) { navigate("/login"); return; }
    if (parsed.role !== "staff" && parsed.role !== "admin") { navigate("/"); return; }
    setUser(parsed);
  }, [navigate]);

  const loadDashboard = useCallback(async (showRefreshNotice = false) => {
    setIsRefreshing(true);
    try {
      const res = await authFetch(`backend/api/get_staff_dashboard.php?_ts=${Date.now()}`, {
        cache: "no-store",
      });
      const data = await parseApiJson(res);
      if (data.success) {
        setStats(data.stats || DEFAULT_STATS);
        setIncomingRequests(Array.isArray(data.requests) ? data.requests : []);
        setInventoryRows(Array.isArray(data.inventory) ? data.inventory : []);
        setLabUseLogs(Array.isArray(data.labLogs) ? data.labLogs : []);
        setIssueLogs(Array.isArray(data.issueLogs) ? data.issueLogs : []);
        setNotifications(Array.isArray(data.notifications) ? data.notifications : []);

        const urgentCount = Array.isArray(data.urgentRequests) ? data.urgentRequests.length : 0;
        const lowStockCount = Array.isArray(data.lowStockItems) ? data.lowStockItems.length : 0;
        const expiredUnits = Number(data?.stats?.expiredUnits || 0);
        const expiringSoonUnits = Number(data?.stats?.expiringSoonUnits || 0);
        const expiryWindowDays = Number(data?.expiryAlertWindowDays || 7);
        if (urgentCount > 0) {
          setPopupNotice(`Urgent alert: ${urgentCount} urgent/critical request(s) need attention.`);
        } else {
          const alertParts = [];
          
          // Add expiry alerts
          if (expiredUnits > 0 || expiringSoonUnits > 0) {
            const expiryParts = [];
            if (expiredUnits > 0) {
              expiryParts.push(`${expiredUnits} expired unit(s)`);
            }
            if (expiringSoonUnits > 0) {
              expiryParts.push(`${expiringSoonUnits} unit(s) expiring within ${expiryWindowDays} day(s)`);
            }
            alertParts.push(`Expiry alert: ${expiryParts.join(' and ')}`);
          }
          
          // Add low stock alerts (always show if present)
          if (lowStockCount > 0) {
            alertParts.push(`Low stock alert: ${lowStockCount} blood type(s) below minimum threshold`);
          }
          
          if (alertParts.length > 0) {
            setPopupNotice(alertParts.join(". ") + ".");
          } else if (showRefreshNotice) {
            setPopupNotice("Dashboard refreshed.");
          }
        }
        setActionError("");
      } else {
        setActionError(data.message || "Could not load dashboard data.");
      }
    } catch {
      setActionError("Could not load dashboard data.");
    } finally {
      setIsRefreshing(false);
    }
  }, []);

  useEffect(() => { if (user) loadDashboard(); }, [user, loadDashboard]);

  const loadSampleRecords = useCallback(async () => {
    setDonorSamplesLoading(true);
    setDonorSamplesError("");
    try {
      const res = await authFetch(`backend/api/get_donor_samples.php?_ts=${Date.now()}`, { cache: "no-store" });
      const data = await parseApiJson(res);
      console.log("[DEBUG] get_donor_samples response:", data);
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Could not load donor samples.");
      }
      const samples = Array.isArray(data.data) ? data.data : [];
      console.log("[DEBUG] Setting donorSamples:", samples.length, "samples");
      setDonorSamples(samples);
    } catch (error) {
      console.error("[ERROR] loadSampleRecords:", error.message);
      setDonorSamples([]);
      setDonorSamplesError(error.message || "Could not load donor samples.");
    } finally {
      setDonorSamplesLoading(false);
    }
  }, []);

  useEffect(() => {
    if (user) {
      loadSampleRecords();
    }
  }, [user, loadSampleRecords]);

  const loadUntestedDonations = useCallback(async () => {
    try {
      const res = await authFetch(`backend/api/get_untested_donations.php?_ts=${Date.now()}`, { cache: "no-store" });
      const data = await parseApiJson(res);
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Could not load pending donations.");
      }
      const rows = Array.isArray(data.data) ? data.data : [];
      const mapped = rows.map((r) => ({
        donation_id: r.donation_id || r.donationId || r.unit_id || '',
        blood_type: r.unit_blood_type || r.blood_type || '',
        component: r.component || '',
        unit_count: 1,
        donorId: r.donor_id || r.donorId || 0,
        donorName: r.donor_name || r.donorName || 'Unknown donor',
        donorBloodType: r.donor_blood_type || r.donorBloodType || '',
      }));
      setUntestedDonations(mapped);
      setTestForm((prev) => ({
        ...prev,
        donationId: prev.donationId || (mapped[0]?.donation_id ? String(mapped[0].donation_id) : ""),
      }));
    } catch (error) {
      setActionError(error.message || "Could not load untested donations.");
    }
  }, []);

  useEffect(() => {
    if (user) {
      loadUntestedDonations();
    }
  }, [user, loadUntestedDonations]);

  useEffect(() => {
    if (!user) return;
    const intervalId = window.setInterval(() => {
      loadDashboard();
    }, 15000);
    return () => window.clearInterval(intervalId);
  }, [user, loadDashboard]);

  useEffect(() => {
    if (!popupNotice) return;
    const timer = window.setTimeout(() => setPopupNotice(""), 5000);
    return () => window.clearTimeout(timer);
  }, [popupNotice]);

  const selectedDonation = untestedDonations.find((row) => String(row.donation_id) === String(testForm.donationId)) || null;

  const toggleNotifications = useCallback(() => {
    setIsNotificationOpen((prev) => !prev);
  }, []);

  const closeNotifications = useCallback(() => {
    setIsNotificationOpen(false);
  }, []);

  const getUnreadNotificationCount = () => notifications.filter((note) => !note?.is_read || note.is_read === 0 || note.is_read === "0").length;

  const handleLogout = () => { clearAuthSession(); navigate("/login"); };

  useEffect(() => {
    const handlePointerDown = (event) => {
      if (!isNotificationOpen) return;
      const panelNode = notificationPanelRef.current;
      const buttonNode = notificationButtonRef.current;
      if (panelNode?.contains(event.target) || buttonNode?.contains(event.target)) return;
      closeNotifications();
    };

    const handleKeyDown = (event) => {
      if (event.key === "Escape") {
        closeNotifications();
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    document.addEventListener("touchstart", handlePointerDown);
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      document.removeEventListener("touchstart", handlePointerDown);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [closeNotifications, isNotificationOpen]);

  const runAction = useCallback(async (key, endpoint, payload, fallbackMessage) => {
    setBusyKey(key);
    try {
      const res = await authFetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      
      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      const data = await parseApiJson(res);
      if (!res.ok || !data.success) throw new Error(data.message || fallbackMessage);
      setActionError("");
      await loadDashboard();
    } catch (error) {
      setActionError(error.message || fallbackMessage);
    } finally {
      setBusyKey("");
    }
  }, [loadDashboard]);

  const handleProcessRequest = useCallback(async (requestId, action, fallbackMessage) => {
    if (!requestId) return;
    await runAction(`${action}-${requestId}`, "backend/api/process_blood_request.php",
      { requestId: Number(requestId), action }, fallbackMessage);
  }, [runAction]);

  const handleApproveRequest = useCallback(async (requestId) => {
    await handleProcessRequest(requestId, "approve", "Failed to approve request.");
  }, [handleProcessRequest]);

  const handleRejectRequest = useCallback(async (requestId) => {
    await handleProcessRequest(requestId, "reject", "Failed to reject request.");
  }, [handleProcessRequest]);

  const handleStartCrossMatch = useCallback(async (requestId) => {
    await handleProcessRequest(requestId, "start_crossmatch", "Failed to start cross-matching.");
  }, [handleProcessRequest]);

  const fetchCompatibleUnitsForCrossMatch = useCallback(async (requestId) => {
    try {
      const res = await authFetch(`backend/api/get_compatible_units.php?requestId=${encodeURIComponent(String(requestId))}`);
      
      // Check for auth errors (401/403) and don't continue if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      const data = await parseApiJson(res);
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to load compatible donor unit IDs.");
      }

      const unitItems = Array.isArray(data.units)
        ? data.units
            .map((row) => ({
              unitId: String(row?.unit_id || row?.unitId || "").trim(),
              bloodType: String(row?.blood_type || row?.bloodType || "").trim(),
            }))
            .filter((row) => row.unitId)
        : [];

      setCrossMatchModal((prev) => {
        if (!prev.open || Number(prev.requestId) !== Number(requestId)) return prev;
        const currentInput = String(prev.donorUnitRefs || "").trim();
        const nextInput = prev.result === "Compatible" && !currentInput ? (unitItems[0]?.unitId || "") : currentInput;
        return {
          ...prev,
          availableUnits: unitItems,
          donorUnitRefs: nextInput,
          loadingUnits: false,
        };
      });
    } catch (error) {
      setCrossMatchModal((prev) => {
        if (!prev.open || Number(prev.requestId) !== Number(requestId)) return prev;
        return {
          ...prev,
          loadingUnits: false,
        };
      });
      setActionError(error.message || "Failed to load compatible donor unit IDs.");
    }
  }, []);

  const openCrossMatchModal = useCallback(async (requestId, result = "Compatible") => {
    if (!requestId) return;
    
    // Find the request to verify its current status
    const request = incomingRequests.find((r) => r.id === requestId);
    if (!request) {
      setActionError("Request not found. Please refresh the dashboard.");
      return;
    }
    
    // Verify status is "cross-matching" (or acceptable variant)
    const status = normalizeRequestStatus(request.status);
    if (status !== "cross-matching") {
      // If request is approved, try to start cross-match automatically then open modal
      if (status === "approved") {
        try {
          await handleStartCrossMatch(requestId);
          // Open modal and fetch compatible units after starting cross-match
          setCrossMatchModal({
            open: true,
            requestId: Number(requestId),
            result,
            donorUnitRefs: "",
            availableUnits: [],
            loadingUnits: true,
            testParameters: "",
            notes: "",
          });
          setActionError("");
          await fetchCompatibleUnitsForCrossMatch(Number(requestId));
        } catch (err) {
          setActionError(err.message || `Failed to start cross-match for request ${requestId}.`);
        }
        return;
      }

      setActionError(
        `Cannot record cross-match result. Request status is "${request.status}". ` +
        `Please click "Start Cross-Match" first if not already done.`
      );
      return;
    }

    const latestResult = String(request.latest_crossmatch_result || "").trim().toLowerCase();
    if (latestResult === "compatible" || latestResult === "incompatible") {
      setActionError(
        `Cross-match is already finalized as "${request.latest_crossmatch_result}"` +
        `${request.latest_crossmatch_tested_at ? ` at ${request.latest_crossmatch_tested_at}` : ""}. Duplicate testing is blocked.`
      );
      return;
    }
    
    const availableUnits = getAvailableUnitsForRequest(request);
    const selectedUnit = getSelectedUnitForRequest(request);
    const prefilledDonorUnits = result === "Compatible" ? (selectedUnit || availableUnits[0] || "") : "";

    setCrossMatchModal({
      open: true,
      requestId: Number(requestId),
      result,
      donorUnitRefs: prefilledDonorUnits,
      availableUnits,
      loadingUnits: true,
      testParameters: "",
      notes: "",
    });
    setActionError("");
    fetchCompatibleUnitsForCrossMatch(Number(requestId));
  }, [incomingRequests, getAvailableUnitsForRequest, getSelectedUnitForRequest, fetchCompatibleUnitsForCrossMatch, handleStartCrossMatch]);

  const closeCrossMatchModal = useCallback(() => {
    setCrossMatchModal({
      open: false,
      requestId: null,
      result: "Compatible",
      donorUnitRefs: "",
      availableUnits: [],
      loadingUnits: false,
      testParameters: "",
      notes: "",
    });
  }, []);

  const handleCrossMatchModalChange = useCallback((field, value) => {
    setCrossMatchModal((prev) => ({ ...prev, [field]: value }));
  }, []);

  const handleCrossMatchSubmit = useCallback(async (event) => {
    event.preventDefault();
    const requestId = Number(crossMatchModal.requestId);
    if (!requestId) return;

    if (crossMatchModal.result === "Compatible" && !String(crossMatchModal.donorUnitRefs || "").trim()) {
      setActionError("Please enter the compatible donor unit ID before saving a compatible result.");
      return;
    }

    const key = `cross-${requestId}-${crossMatchModal.result}`;
    setBusyKey(key);
    let successfullySubmitted = false;
    
    try {
      console.log(`[CrossMatch] Submitting: request=${requestId}, result=${crossMatchModal.result}`);
      
      const res = await authFetch("backend/api/record_crossmatch.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          requestId,
          result: crossMatchModal.result,
          donorUnitRefs: crossMatchModal.donorUnitRefs,
          testParameters: crossMatchModal.testParameters,
          notes: crossMatchModal.notes,
        }),
      });
      
      console.log(`[CrossMatch] Response status: ${res.status}`);
      
      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      const data = await parseApiJson(res);
      console.log(`[CrossMatch] Response data:`, data);
      
      if (!res.ok || !data.success) {
        // Show detailed error with current status if available
        const errorMsg = data.message || "Failed to record cross-match.";
        const statusInfo = data.currentStatus ? ` (Current status in database: "${data.currentStatus}")` : "";
        const fullError = errorMsg + statusInfo;
        console.error(`[CrossMatch] Error:`, fullError);
        setActionError(fullError);
        return;
      }
      
      console.log(`[CrossMatch] Success! Closing modal and refreshing...`);
      successfullySubmitted = true;
      setActionError("");
      closeCrossMatchModal();
      
      console.log(`[CrossMatch] Refreshing dashboard...`);
      await loadDashboard();
      console.log(`[CrossMatch] Dashboard refreshed successfully`);
      
    } catch (error) {
      console.error(`[CrossMatch] Exception:`, error);
      setActionError(error.message || "Failed to record cross-match.");
    } finally {
      // Only clear busy key if submission succeeded
      // If failed, keep button showing error state briefly then clear
      if (successfullySubmitted) {
        setBusyKey("");
      } else {
        // After 2 seconds, allow retry
        setTimeout(() => setBusyKey(""), 2000);
      }
    }
  }, [closeCrossMatchModal, crossMatchModal, loadDashboard]);

  const handleIssueBlood = useCallback((requestId) => {
    if (!requestId) return;
    const request = incomingRequests.find((r) => r.id === requestId);
    if (!request) return;
    const selectedUnit = getSelectedUnitForRequest(request);
    const linkedUnit = String(request.compatible_unit_id || "").trim();
    // Close other modals/backdrops first, then open issue modal after a short delay
    // This avoids modal/backdrop transition race-conditions that block pointer events.
    closeCrossMatchModal();
    setActionError("");
    setTimeout(() => {
      setIssueBloodModal({
        open: true,
        requestId: Number(requestId),
        request,
        compatibleUnitId: selectedUnit || linkedUnit,
        isEmergency: false,
        staffConfirmedBy: String(user?.name || "").trim(),
        comment: "",
      });
    }, 140);
  }, [incomingRequests, getSelectedUnitForRequest, user, closeCrossMatchModal]);

  const closeIssueBloodModal = useCallback(() => {
    setIssueBloodModal({
      open: false,
      requestId: null,
      request: null,
      compatibleUnitId: "",
      isEmergency: false,
      staffConfirmedBy: "",
      comment: "",
    });
  }, []);

  const handleIssueBloodModalChange = useCallback((field, value) => {
    setIssueBloodModal((prev) => ({ ...prev, [field]: value }));
  }, []);

  const handleIssueBloodSubmit = useCallback(async (event) => {
    event.preventDefault();
    const requestId = Number(issueBloodModal.requestId);
    if (!requestId) return;

    const key = `issue-${requestId}-${issueBloodModal.isEmergency ? "emergency" : "normal"}`;
    setBusyKey(key);
    try {
      if (!issueBloodModal.isEmergency && !String(issueBloodModal.compatibleUnitId || "").trim()) {
        throw new Error("No compatible unit is linked to this request. Record a compatible cross-match with a unit ID first.");
      }
      if (!String(issueBloodModal.staffConfirmedBy || "").trim()) {
        throw new Error("Please enter issuing staff confirmation before issuing blood.");
      }

      const res = await authFetch("backend/api/issue_blood_unit.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          requestId,
          compatibleUnitId: issueBloodModal.compatibleUnitId,
          isEmergency: issueBloodModal.isEmergency,
          staffConfirmedBy: issueBloodModal.staffConfirmedBy,
          staffComment: issueBloodModal.comment,
        }),
      });
      
      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      const data = await parseApiJson(res);
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to issue blood.");
      }
      setActionError("");
      setPopupNotice(`✓ Blood unit issued successfully for request ${requestId}.`);
      closeIssueBloodModal();
      await loadDashboard();
    } catch (error) {
      setActionError(error.message || "Failed to issue blood.");
    } finally {
      setBusyKey("");
    }
  }, [closeIssueBloodModal, issueBloodModal, loadDashboard]);

  const getRequestStatus = useCallback((request) => normalizeRequestStatus(request?.status), []);

  const canIssueMatchedRequest = useCallback((request) => {
    const status = getRequestStatus(request);
    if (status === "issued" || status === "rejected") return false;

    const latestResult = String(request?.latest_crossmatch_result || "").trim().toLowerCase();
    const hasCompatibleResult = latestResult === "compatible";
    const isIssueStage = status === "matched" || hasCompatibleResult;
    if (!isIssueStage) return false;

    const availableUnits = getAvailableUnitsForRequest(request);
    const selectedUnit = getSelectedUnitForRequest(request);
    if (availableUnits.length > 0 && selectedUnit) {
      return true;
    }

    const compatibleUnitId = String(request?.compatible_unit_id || "").trim();
    if (!compatibleUnitId) return false;

    const compatibleUnitStatus = String(request?.compatible_unit_status || "").trim().toLowerCase();
    if (!compatibleUnitStatus) return true;
    if (compatibleUnitStatus === "issued") return false;

    const donationId = String(request?.compatible_donation_id || "").trim();
    const testFinal = String(request?.compatible_test_final_result || "").trim().toLowerCase();
    const testEligible = testFinal === "safe" || testFinal === "eligible";
    if (!donationId || !testEligible) {
      return false;
    }

    return compatibleUnitStatus === "available" || compatibleUnitStatus === "reserved";
  }, [getRequestStatus, getAvailableUnitsForRequest, getSelectedUnitForRequest]);

  const getRequestActionHint = useCallback((request) => {
    const status = getRequestStatus(request);
    if (status === "issued") return "Request already issued.";
    if (status === "rejected") return "Request rejected.";

    const latestResult = String(request?.latest_crossmatch_result || "").trim().toLowerCase();
    const compatibleUnitId = String(request?.compatible_unit_id || "").trim();
    const compatibleUnitStatus = String(request?.compatible_unit_status || "").trim().toLowerCase();
    const isIssueStage = status === "matched" || latestResult === "compatible";
    const availableUnits = getAvailableUnitsForRequest(request);
    const selectedUnit = getSelectedUnitForRequest(request);

    if (!isIssueStage) return "";
    if (availableUnits.length > 0 && selectedUnit) return "Selected unit is ready. Click Issue Blood to continue.";
    if (!compatibleUnitId) return "No compatible unit is linked yet.";
    if (compatibleUnitStatus === "issued") return "Compatible unit has already been issued.";
    if (compatibleUnitStatus === "pending" || compatibleUnitStatus === "quarantined") return "Compatible unit is not ready for issuance yet.";
    if (compatibleUnitStatus === "") return "Compatible unit status is unknown.";
    const donationId = String(request?.compatible_donation_id || "").trim();
    const testFinal = String(request?.compatible_test_final_result || "").trim().toLowerCase();
    const testEligible = testFinal === "safe" || testFinal === "eligible";
    if (!donationId || !testEligible) return "Cannot issue - donation test results missing or reactive.";
    return `Compatible unit status: ${compatibleUnitStatus}.`;
  }, [getRequestStatus, getAvailableUnitsForRequest, getSelectedUnitForRequest]);

  const isUrgentRequest = useCallback((request) => {
    const urgency = String(request?.urgency || "").toLowerCase();
    return urgency === "urgent" || urgency === "critical";
  }, []);

  const getRequestActions = useCallback((request) => {
    const status = getRequestStatus(request);

    if (canIssueMatchedRequest(request)) {
      return [
        { key: "issue", label: "Issue Blood", className: "confirm", onClick: () => handleIssueBlood(request.id) },
      ];
    }

    if (status === "pending") {
      return [
        { key: "approve", label: "Approve", className: "primary", onClick: () => handleApproveRequest(request.id) },
        { key: "reject", label: "Reject", className: "danger", onClick: () => handleRejectRequest(request.id) },
      ];
    }

    if (status === "approved") {
      return [
        { key: "start-crossmatch", label: "Start Cross-Match", className: "default", onClick: () => handleStartCrossMatch(request.id) },
        { key: "reject", label: "Reject", className: "danger", onClick: () => handleRejectRequest(request.id) },
      ];
    }

    if (status === "cross-matching") {
      const latestResult = String(request?.latest_crossmatch_result || "").trim().toLowerCase();
      if (latestResult === "compatible" || latestResult === "incompatible") {
        return [];
      }

      return [
        { key: "open-crossmatch", label: "Open Cross-Match", className: "primary", onClick: () => openCrossMatchModal(request.id, "Compatible") },
      ];
    }

    return [];
  }, [canIssueMatchedRequest, getRequestStatus, handleApproveRequest, handleIssueBlood, handleRejectRequest, handleStartCrossMatch, openCrossMatchModal]);

  const openAddInventoryModal = useCallback((bloodType) => {
    if (!bloodType) return;
    setAddInventoryModal({
      open: true,
      donationId: "",
      donorId: 0,
      bloodType: String(bloodType).trim(),
      component: "Packed Red Cells",
      expiryDate: "",
      status: "Available",
    });
    setActionError("");
  }, []);

  const closeAddInventoryModal = useCallback(() => {
    setAddInventoryModal({
      open: false,
      donationId: "",
      donorId: 0,
      bloodType: "",
      component: "Packed Red Cells",
      expiryDate: "",
      status: "Available",
    });
  }, []);

  const handleInventoryModalChange = useCallback((field, value) => {
    setAddInventoryModal((prev) => {
      if (field === "donorId") {
        const selectedDonor = confirmedDonors.find((donor) => Number(donor.id) === Number(value)) || null;
        return {
          ...prev,
          donorId: Number(value) || 0,
          bloodType: selectedDonor?.blood_type ? String(selectedDonor.blood_type).trim() : prev.bloodType,
        };
      }

      if (field === "bloodType") {
        return prev;
      }

      return { ...prev, [field]: value };
    });
  }, [confirmedDonors]);

  const handleSampleCollectionChange = useCallback((field, value) => {
    setSampleCollectionForm((prev) => ({
      ...prev,
      [field]: field === "donorId" ? Number(value) || 0 : value,
    }));
  }, []);

  const handleSampleTestChange = useCallback((field, value) => {
    setSampleTestForm((prev) => ({
      ...prev,
      [field]: field === "sampleId" ? Number(value) || 0 : value,
    }));
  }, []);

  const handleHealthCheckChange = useCallback((field, value) => {
    setHealthCheckForm((prev) => ({
      ...prev,
      [field]: value,
    }));
  }, []);

  const handleHealthCheckStatusChange = useCallback((field, value) => {
    setHealthCheckStatus((prev) => ({
      ...prev,
      [field]: value,
    }));
  }, []);

  const handleAddDiseaseRow = useCallback(() => {
    setExtraDiseaseRows((prev) => [...prev, { name: "", result: "Negative" }]);
  }, []);

  const handleExtraDiseaseChange = useCallback((index, field, value) => {
    setExtraDiseaseRows((prev) => prev.map((row, i) => (
      i === index ? { ...row, [field]: value } : row
    )));
  }, []);

  const handleRemoveDiseaseRow = useCallback((index) => {
    setExtraDiseaseRows((prev) => prev.filter((_, i) => i !== index));
  }, []);

  useEffect(() => {
    if (!addInventoryModal.open) return;
    const selectedDonor = confirmedDonors.find((donor) => Number(donor.id) === Number(addInventoryModal.donorId)) || null;
    const nextBloodType = selectedDonor?.blood_type ? String(selectedDonor.blood_type).trim() : "";
    if (nextBloodType) {
      setAddInventoryModal((prev) => (prev.bloodType === nextBloodType ? prev : { ...prev, bloodType: nextBloodType }));
    }
  }, [addInventoryModal.open, addInventoryModal.donorId, confirmedDonors]);

  const loadConfirmedDonors = useCallback(async () => {
    setConfirmedDonorsLoading(true);
    setConfirmedDonorsError("");
    try {
      const res = await authFetch('backend/api/get_confirmed_donors.php?_ts=' + Date.now(), { cache: 'no-store' });
      const json = await parseApiJson(res);
      if (json.success && Array.isArray(json.data)) {
        setConfirmedDonors(json.data);
      } else {
        setConfirmedDonors([]);
        setConfirmedDonorsError(json.message || 'Failed to load confirmed donors.');
      }
    } catch {
      setConfirmedDonors([]);
      setConfirmedDonorsError('Failed to load confirmed donors.');
    } finally {
      setConfirmedDonorsLoading(false);
    }
  }, []);

  // Load confirmed donors for both sample workflow and unit creation.
  useEffect(() => {
    if (user) {
      loadConfirmedDonors();
    }
  }, [user, loadConfirmedDonors]);

  // Refresh donor options whenever the add-unit modal opens.
  useEffect(() => {
    if (!addInventoryModal.open || !user) return;
    loadConfirmedDonors();
  }, [addInventoryModal.open, loadConfirmedDonors, user]);

  const handleAddInventorySubmit = useCallback(async (event) => {
    event.preventDefault();
    const bloodType = addInventoryModal.bloodType;
    const donationId = String(addInventoryModal.donationId || "").trim();
    const component = String(addInventoryModal.component || "Packed Red Cells").trim();
    const expiryDate = String(addInventoryModal.expiryDate || "").trim();

    if (!addInventoryModal.donorId) {
      setActionError("Please select a confirmed donor before adding a unit.");
      return;
    }
    if (!bloodType || !expiryDate) {
      setActionError("Please fill all required fields: Expiry Date.");
      return;
    }

    const key = `unit-${donationId}`;
    setBusyKey(key);
    try {
      const res = await authFetch("backend/api/add_blood_unit.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          donationId,
          donorId: addInventoryModal.donorId,
          bloodType: bloodType.trim(),
          component,
          expiryDate,
          status: "Available",
        }),
      });
      const data = await parseApiJson(res);
      
      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to add unit.");
      }

      setActionError("");
      setPopupNotice(`Blood unit added for ${bloodType}.`);
      closeAddInventoryModal();
      await loadDashboard();
    } catch (error) {
      setActionError(error.message || "Failed to add unit.");
    } finally {
      setBusyKey("");
    }
  }, [addInventoryModal, closeAddInventoryModal, loadDashboard]);

  const handleSampleCollectionSubmit = useCallback(async (event) => {
    event.preventDefault();
    const donorId = Number(sampleCollectionForm.donorId || 0);
    const collectionDate = String(sampleCollectionForm.collectionDate || "").trim();
    const technician = String(sampleCollectionForm.technician || "").trim();

    if (!donorId || !collectionDate || !sampleCollectionForm.collectionTime || !technician) {
      setActionError("Please choose a donor, collection date, collection time, and technician.");
      return;
    }

    const key = `sample-collect-${donorId}`;
    setBusyKey(key);
    try {
      const res = await authFetch("backend/api/collect_donor_sample.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ donorId, collectionDate, collectionTime: sampleCollectionForm.collectionTime, technician }),
      });
      const data = await parseApiJson(res);
      
      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to collect sample.");
      }

      const donorName = data?.data?.donorName || "donor";
      const formattedDate = collectionDate;
      const formattedTime = sampleCollectionForm.collectionTime;
      const notificationMessage = `Dear ${donorName}, your SAMPLE COLLECTION appointment has been confirmed. ⚠️ This is a SMALL blood sample (about 5ml) for testing. We will test for HIV, Hepatitis, Malaria, etc. 📍 Blood Bank: National Blood Bank, Thimphu. 📅 Date: ${formattedDate}. ⏰ Time: ${formattedTime}. Results will be ready in 2-3 days. You will be notified when results are available.`;

      setActionError("");
      setPopupNotice(`Sample collection appointment confirmed for ${donorName}.`);
      setNotifications((prev) => [{
        id: `notification-sample-${Date.now()}`,
        title: "SAMPLE COLLECTION APPOINTMENT CONFIRMED",
        message: notificationMessage,
        severity: "info",
        is_read: 0,
      }, ...prev]);
      setSampleCollectionForm((prev) => ({
        ...prev,
        donorId: 0,
        technician: "",
        collectionDate: new Date().toISOString().slice(0, 10),
      }));
      await loadSampleRecords();
      await loadConfirmedDonors();
      await loadDashboard();
    } catch (error) {
      setActionError(error.message || "Failed to collect sample.");
    } finally {
      setBusyKey("");
    }
  }, [loadConfirmedDonors, loadDashboard, loadSampleRecords, sampleCollectionForm]);

  const handleSampleTestSubmit = useCallback(async (event) => {
    event.preventDefault();

    const sampleId = Number(sampleTestForm.sampleId || 0);
    const technician = String(sampleTestForm.technician || "").trim();
    const requiredChecks = [
      "hemoglobin",
      "bloodPressure",
      "pulse",
      "temperature",
      "weight",
    ];

    if (!sampleId || !technician) {
      const msg = "Please choose a sample and enter the technician name.";
      setActionError(msg);
      toast.error(msg);
      return;
    }

    if (requiredChecks.some((field) => !healthCheckForm[field].trim() || !healthCheckStatus[field].trim())) {
      const msg = "Please complete all mandatory health checks before saving.";
      setActionError(msg);
      toast.error(msg);
      return;
    }

    const key = `sample-test-${sampleId}`;
    setBusyKey(key);

    try {
      const res = await authFetch("backend/api/save_sample_test.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          sampleId,
          technician,
          hiv_result: sampleTestForm.hivResult,
          hbsag_result: sampleTestForm.hbsagResult,
          hcv_result: sampleTestForm.hcvResult,
          syphilis_result: sampleTestForm.syphilisResult,
          malaria_result: sampleTestForm.malariaResult,
          notes: sampleTestForm.notes,
          mandatory_health_checks: healthCheckForm,
          mandatory_health_status: healthCheckStatus,
          deferral_decision: deferralDecision,
          deferral_period: deferralDecision === "temporary" ? deferralPeriod : null,
          deferral_reason: deferralDecision === "permanent" ? deferralReason : null,
          extra_diseases: extraDiseaseRows.filter((row) => String(row.name || "").trim()).map((row) => ({
            name: row.name,
            result: row.result,
          })),
        }),
      });
      const data = await parseApiJson(res);

      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }

      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to save sample test results.");
      }

      setActionError("");

      // Show toast notification based on result type
      const hasReactive = data?.data?.has_reactive_results;
      const donorName = data?.data?.donor_name || `Donor #${data?.data?.donor_id}`;

      if (hasReactive) {
        toast.error(`⚠️ Reactive results detected for ${donorName}. Admin notified for review.`, {
          autoClose: 8000,
        });
      } else {
        toast.success(`✅ All results non-reactive for ${donorName}. Awaiting admin confirmation.`, {
          autoClose: 5000,
        });
      }

      setPopupNotice(`Sample results saved for ${donorName}.`);
      setSampleTestForm({
        sampleId: 0,
        technician: "",
        hivResult: "Non-reactive",
        hbsagResult: "Non-reactive",
        hcvResult: "Non-reactive",
        syphilisResult: "Non-reactive",
        malariaResult: "Non-reactive",
        notes: "",
      });
      await loadSampleRecords();
      await loadDashboard();
    } catch (error) {
      console.error("[DEBUG] Error:", error);
      setActionError(error.message || "Failed to save sample test results.");
      toast.error(error.message || "Failed to save sample test results.");
    } finally {
      setBusyKey("");
    }
  }, [deferralDecision, deferralPeriod, deferralReason, extraDiseaseRows, healthCheckForm, healthCheckStatus, loadDashboard, loadSampleRecords, sampleTestForm]);

  const handleTestFormChange = useCallback((field, value) => {
    setTestForm((prev) => ({ ...prev, [field]: value }));
  }, []);

  const handleDonationTestSubmit = useCallback(async (event) => {
    event.preventDefault();
    const donationId = String(testForm.donationId || "").trim();
    if (!donationId) {
      setActionError("Please select a donation ID to record lab screening.");
      return;
    }

    const key = `donation-test-${donationId}`;
    setBusyKey(key);
    try {
      const res = await authFetch("backend/api/save_test_results.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          donation_id: donationId,
          donor_id: selectedDonation?.donorId || null,
          hiv: testForm.hivResult,
          hbsag: testForm.hbsagResult,
          hcv: testForm.hcvResult,
          syphilis: testForm.syphilisResult,
          malaria: testForm.malariaResult,
          remarks: testForm.remarks,
          technicianName: user?.name || "",
        }),
      });
      const data = await parseApiJson(res);
      
      // Check for auth errors (401/403) and don't try to refresh if auth failed
      if (res.status === 401 || res.status === 403) {
        setActionError("Your session has expired. Please log in again.");
        return;
      }
      
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to record donation test results.");
      }

      setActionError("");
      const donorName = data?.data?.donor_name || selectedDonation?.donorName || "Unknown donor";
      const donorStatus = data?.data?.donor_status || "Updated";
      const deferralText = data?.data?.deferral_reason ? ` Reason: ${data.data.deferral_reason}.` : "";
      setPopupNotice(donorStatus === "Deferred"
        ? `Donor ${donorName} has been deferred.${deferralText}`
        : `Donor ${donorName} confirmed.`
      );
      setTestForm({
        donationId: "",
        hivResult: "Non-reactive",
        hbsagResult: "Non-reactive",
        hcvResult: "Non-reactive",
        syphilisResult: "Non-reactive",
        malariaResult: "Non-reactive",
        remarks: "",
      });

      await Promise.all([loadDashboard(), loadUntestedDonations()]);
    } catch (error) {
      setActionError(error.message || "Failed to record donation test results.");
    } finally {
      setBusyKey("");
    }
  }, [loadDashboard, loadUntestedDonations, selectedDonation, testForm, user?.name]);

  const inventoryTotalUnits = inventoryRows.reduce((sum, row) => sum + getInventoryTotal(row), 0);
  const filteredLabLogs = labUseLogs.filter((log) => {
    if (labResultFilter === "all") return true;
    return String(log?.result || "").toLowerCase() === labResultFilter;
  });

  const activeCrossMatchRequest = incomingRequests.find(
    (request) => Number(request.id) === Number(crossMatchModal.requestId)
  ) || null;
  const modalAvailableUnits = crossMatchModal.availableUnits.length > 0
    ? crossMatchModal.availableUnits
    : getAvailableUnitsForRequest(activeCrossMatchRequest);
  // Filter samples that are ready for testing (only pending/collected, not yet tested)
  const pendingSampleRows = donorSamples.filter((sample) => {
    const status = normalizeDonorSampleState(sample.status);
    const isFinalized = Number(sample.admin_finalized || 0) === 1;
    // Only include samples that are pending or collected (not yet tested/approved)
    // Exclude: rejected, negative, reactive, tested, finalized
    const readyForTesting = ["pending", "collected"].includes(status);
    return readyForTesting && !isFinalized;
  });

  const pendingSampleOptions = useMemo(() => pendingSampleRows, [pendingSampleRows]);

  const initials = user?.name
    ? user.name.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase()
    : "?";

  if (!user) return null;

  return (
    <div className="staff-page">

      <header className="staff-topbar">
        <div className="staff-topbar-inner">
          <div className="staff-brand">
            <span className="brand-dot" />
            🩸 Blood Transfusion Services — Staff
          </div>
          <div className="staff-user-actions">
            <div className="staff-notification-wrap">
              <button
                ref={notificationButtonRef}
                type="button"
                className={`staff-notification-badge ${getUnreadNotificationCount() > 0 ? "unread" : ""}`}
                onClick={toggleNotifications}
                aria-label={`Notifications (${notifications.length})`}
                aria-expanded={isNotificationOpen}
                aria-haspopup="menu"
              >
                🔔
                <span className="staff-notification-count">{notifications.length}</span>
              </button>

              {isNotificationOpen && notifications.length > 0 && (
                <section className="staff-notification-dropdown" ref={notificationPanelRef} role="menu" aria-label="Recent notifications">
                  <h3>Recent Notifications</h3>
                  <ul>
                    {notifications.slice(0, 5).map((note) => (
                      <li key={note.id} className={`severity-${String(note.severity || "info").toLowerCase()}`}>
                        <strong>{note.title}:</strong> {note.message}
                      </li>
                    ))}
                  </ul>
                </section>
              )}
            </div>
            <div className="staff-user-pill">
              <div className="avatar">{initials}</div>
              {user.name}
            </div>
            <button className="staff-logout-btn" onClick={handleLogout}>Logout</button>
          </div>
        </div>
      </header>

      <main className="staff-main">

        <section className="staff-hero">
          <h1>Blood Bank Staff Dashboard</h1>
          <p>Process hospital requests, update inventory, manage cross-match results, and issue blood units safely.</p>
        </section>

        <section className="staff-stats">
          <article className="staff-stat red">
            <span className="staff-stat-icon">🩸</span>
            <div className="staff-stat-label">Incoming Requests</div>
            <div className="staff-stat-value">{stats.incomingRequests}</div>
            <div className="staff-stat-sub">Pending review</div>
          </article>
          <article className="staff-stat blue">
            <span className="staff-stat-icon">🔬</span>
            <div className="staff-stat-label">Cross-Match Queue</div>
            <div className="staff-stat-value">{stats.crossMatchQueue}</div>
            <div className="staff-stat-sub">Awaiting results</div>
          </article>
          <article className="staff-stat green">
            <span className="staff-stat-icon">✅</span>
            <div className="staff-stat-label">Units Issued Today</div>
            <div className="staff-stat-value">{stats.unitsIssuedToday}</div>
            <div className="staff-stat-sub">Issued today</div>
          </article>
          <article className="staff-stat amber">
            <span className="staff-stat-icon">⚠️</span>
            <div className="staff-stat-label">Low Stock Alerts</div>
            <div className="staff-stat-value">{stats.lowStockAlerts}</div>
            <div className="staff-stat-sub">Needs restocking</div>
          </article>
        </section>

        {actionError && (
          <div className="staff-error" role="alert">⚠️ {actionError}</div>
        )}

        {popupNotice && (
          <div className="staff-popup-alert" role="alert">{popupNotice}</div>
        )}

        <div className="staff-tab-card">
          <div className="staff-tabs">
            <button className={`staff-tab${activeTab === "requests" ? " active" : ""}`} onClick={() => setActiveTab("requests")}>
              🩸 Requests <span className="staff-tab-count">{incomingRequests.length}</span>
            </button>
            <button className={`staff-tab${activeTab === "inventory" ? " active" : ""}`} onClick={() => setActiveTab("inventory")}>
              📦 Inventory <span className="staff-tab-count">{inventoryRows.length}</span>
            </button>
            <button className={`staff-tab${activeTab === "lab" ? " active" : ""}`} onClick={() => setActiveTab("lab")}>
              🧪 Lab Logs <span className="staff-tab-count">{labUseLogs.length}</span>
            </button>
            <button className={`staff-tab${activeTab === "samples" ? " active" : ""}`} onClick={() => setActiveTab("samples") }>
              🧫 Samples <span className="staff-tab-count">{donorSamples.length}</span>
            </button>
            <button className={`staff-tab${activeTab === "issues" ? " active" : ""}`} onClick={() => setActiveTab("issues")}>
              📄 Issue Logs <span className="staff-tab-count">{issueLogs.length}</span>
            </button>
            <button className="staff-tab-refresh" onClick={() => loadDashboard(true)} disabled={isRefreshing}>
              {isRefreshing ? "↻ Refreshing..." : "↻ Refresh"}
            </button>
          </div>

          <div className={`staff-tab-panel${activeTab === "requests" ? " active" : ""}`}>
            <div className="staff-table-wrap">
              <table className="staff-table">
                <thead>
                  <tr>
                    <th>#</th><th>Patient</th><th>Diagnosis</th><th>Component</th><th>Blood Type</th>
                    <th>Units</th><th>Priority</th><th>Status</th><th>Available Unit IDs</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {incomingRequests.length === 0 ? (
                    <tr><td colSpan="10" className="empty-row">No incoming requests at this time.</td></tr>
                  ) : incomingRequests.map((r, i) => (
                    <tr key={r.id} className={isUrgentRequest(r) ? "request-row-urgent" : ""}>
                      <td>{i + 1}</td>
                      <td><strong>{r.patient_name}</strong></td>
                      <td><span className="diagnosis-badge">{r.diagnosis || r.reason_for_transfusion || "—"}</span></td>
                      <td>{r.component}</td>
                      <td>{r.blood_type ? <span className="blood-badge">{r.blood_type}</span> : "—"}</td>
                      <td>{r.units_requested}</td>
                      <td><span className={`tag ${String(r.urgency || "routine").toLowerCase()}`}>{r.urgency}</span></td>
                      <td><span className={`tag ${getRequestStatus(r).replace(/\s+/g, "-")}`}>{getDisplayStatusLabel(r.status)}</span></td>
                      <td>
                        {(() => {
                          if (!shouldShowAvailableUnitsForStatus(r.status)) {
                            const linkedUnit = String(r.compatible_unit_id || "").trim();
                            if (linkedUnit) {
                              return (
                                <div className="staff-unit-closed">
                                  <span className="tag note">Linked unit: {linkedUnit}</span>
                                  <button
                                    type="button"
                                    className="staff-table-btn default"
                                    onClick={() => copyUnitIdToClipboard(linkedUnit)}
                                  >
                                    Copy
                                  </button>
                                </div>
                              );
                            }
                            return <span className="tag note">Request closed</span>;
                          }

                          const availableUnits = getAvailableUnitsForRequest(r);
                          const selectedUnit = getSelectedUnitForRequest(r);
                          if (availableUnits.length === 0) {
                            return <span className="tag note">No available units</span>;
                          }

                          return (
                            <div className="staff-unit-picker">
                              <select
                                value={selectedUnit}
                                onChange={(event) => handleAvailableUnitChange(r.id, event.target.value)}
                              >
                                {availableUnits.map((unitId) => (
                                  <option key={`${r.id}-${unitId}`} value={unitId}>{unitId}</option>
                                ))}
                              </select>
                              <div className="staff-unit-picker-actions">
                                <button
                                  type="button"
                                  className="staff-table-btn default"
                                  onClick={() => copyUnitIdToClipboard(selectedUnit)}
                                  disabled={!selectedUnit}
                                >
                                  Copy
                                </button>
                                <button
                                  type="button"
                                  className="staff-table-btn primary"
                                  onClick={() => openCrossMatchModal(r.id, "Compatible")}
                                  disabled={!["cross-matching", "matched", "approved"].includes(normalizeRequestStatus(r.status)) || busyKey !== ""}
                                >
                                  Use
                                </button>
                              </div>
                            </div>
                          );
                        })()}
                      </td>
                      <td>
                        <div className="staff-row-actions">
                          {(() => {
                            const requestActions = getRequestActions(r);
                            const actionHint = getRequestActionHint(r);
                            if (requestActions.length === 0) {
                              return actionHint ? (
                                <span className="tag note">{actionHint}</span>
                              ) : (
                                <span className="tag">No actions</span>
                              );
                            }
                            return requestActions.map((action) => (
                              <button
                                key={`${r.id}-${action.key}`}
                                type="button"
                                className={`staff-table-btn ${action.className}`}
                                onClick={action.onClick}
                                disabled={busyKey !== ""}
                              >
                                {busyKey && busyKey.includes(`-${r.id}`)
                                  ? "Processing…"
                                  : action.label}
                              </button>
                            ));
                          })()}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className={`staff-tab-panel${activeTab === "inventory" ? " active" : ""}`}>
            <div className="staff-inventory-total">
              Total Inventory Units: <strong>{inventoryTotalUnits}</strong>
            </div>
            <div className="staff-table-wrap">
              <table className="staff-table">
                <thead>
                  <tr>
                    <th>Blood Type</th><th>Whole</th><th>PRBC</th>
                    <th>Platelets</th><th>Plasma (FFP)</th><th>Total Units</th><th>Stock Level</th><th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {inventoryRows.length === 0 ? (
                    <tr><td colSpan="8" className="empty-row">No inventory rows available.</td></tr>
                  ) : inventoryRows.map((row) => (
                    <tr key={row.blood_type}>
                      <td><strong>{row.blood_type}</strong></td>
                      <td>{row.whole_units}</td>
                      <td>{row.prbc_units}</td>
                      <td>{row.platelets_units}</td>
                      <td>{row.ffp_units}</td>
                      <td>{getInventoryTotal(row)}</td>
                      <td><span className={`tag ${String(row.stock_level || "healthy").toLowerCase()}`}>{row.stock_level}</span></td>
                      <td>
                        <button type="button" className="staff-table-btn confirm" onClick={() => openAddInventoryModal(row.blood_type)} disabled={busyKey !== ""}>
                          {busyKey === `inv-${row.blood_type}` ? "Updating…" : "Add Units"}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className={`staff-tab-panel${activeTab === "lab" ? " active" : ""}`}>
            <section className="staff-test-form-card">
              <h3>Record Donation Test Results</h3>
              <p>Capture HIV, HBsAg, HCV, Syphilis, and Malaria screening before blood can be issued.</p>
              <form className="staff-test-form" onSubmit={handleDonationTestSubmit}>
                <label htmlFor="test-donation-id">Donation ID <span className="required">*</span></label>
                <select
                  id="test-donation-id"
                  value={testForm.donationId}
                  onChange={(e) => handleTestFormChange("donationId", e.target.value)}
                  required
                >
                  <option value="">-- Select donation --</option>
                  {untestedDonations.map((row) => (
                    <option key={`untested-${row.donation_id}`} value={row.donation_id}>
                      {row.donation_id} | {row.donorName || "Unknown donor"} | {row.blood_type || "Unknown"}{row.donorBloodType ? ` / ${row.donorBloodType}` : ''} | {row.component || "Component"} | {row.unit_count} unit(s)
                    </option>
                  ))}
                </select>

                {selectedDonation && (
                  <div className="staff-test-note">
                    Donor: <strong>{selectedDonation.donorName || "Unknown donor"}</strong>{selectedDonation.donorId ? ` (ID ${selectedDonation.donorId})` : ""}
                    {selectedDonation.donorStatus && (
                      <span>
                        {' '}
                        · Status: <strong>{selectedDonation.donorStatus}</strong>
                      </span>
                    )}
                    {selectedDonation.donorStatus === "Deferred" && selectedDonation.deferred_until && (
                      <span>
                        {' '}
                        · Deferred until <strong>{new Date(selectedDonation.deferred_until).toLocaleDateString()}</strong>
                      </span>
                    )}
                    {selectedDonation.deferral_reason && (
                      <span>
                        {' '}
                        · Reason: <strong>{selectedDonation.deferral_reason}</strong>
                      </span>
                    )}

                    <div style={{ marginTop: 8 }}>
                      Unit blood group: <strong>{selectedDonation.blood_type || '—'}</strong>
                      {' '}
                      · Donor blood group: <strong>{selectedDonation.donorBloodType || '—'}</strong>
                    </div>

                    {selectedDonation.blood_type && selectedDonation.donorBloodType && selectedDonation.blood_type !== selectedDonation.donorBloodType && (
                      <div style={{
                        marginTop: 10,
                        background: '#fff4e5',
                        border: '1px solid #ffd7a8',
                        padding: '8px 10px',
                        borderRadius: 6,
                        color: '#7a4f00'
                      }}>
                        ⚠️ Blood type mismatch: unit ({selectedDonation.blood_type}) differs from donor record ({selectedDonation.donorBloodType}). Verify donor and unit before proceeding.
                      </div>
                    )}
                  </div>
                )}

                <div className="staff-test-grid">
                  <div>
                    <label htmlFor="test-hiv">HIV</label>
                    <select id="test-hiv" value={testForm.hivResult} onChange={(e) => handleTestFormChange("hivResult", e.target.value)}>
                      <option value="Non-reactive">Non-reactive</option>
                      <option value="Reactive">Reactive</option>
                    </select>
                  </div>
                  <div>
                    <label htmlFor="test-hbsag">HBsAg</label>
                    <select id="test-hbsag" value={testForm.hbsagResult} onChange={(e) => handleTestFormChange("hbsagResult", e.target.value)}>
                      <option value="Non-reactive">Non-reactive</option>
                      <option value="Reactive">Reactive</option>
                    </select>
                  </div>
                  <div>
                    <label htmlFor="test-hcv">HCV</label>
                    <select id="test-hcv" value={testForm.hcvResult} onChange={(e) => handleTestFormChange("hcvResult", e.target.value)}>
                      <option value="Non-reactive">Non-reactive</option>
                      <option value="Reactive">Reactive</option>
                    </select>
                  </div>
                  <div>
                    <label htmlFor="test-syphilis">Syphilis</label>
                    <select id="test-syphilis" value={testForm.syphilisResult} onChange={(e) => handleTestFormChange("syphilisResult", e.target.value)}>
                      <option value="Non-reactive">Non-reactive</option>
                      <option value="Reactive">Reactive</option>
                    </select>
                  </div>
                  <div>
                    <label htmlFor="test-malaria">Malaria</label>
                    <select id="test-malaria" value={testForm.malariaResult} onChange={(e) => handleTestFormChange("malariaResult", e.target.value)}>
                      <option value="Non-reactive">Non-reactive</option>
                      <option value="Reactive">Reactive</option>
                    </select>
                  </div>
                </div>

                <label htmlFor="test-remarks">Remarks (optional)</label>
                <textarea
                  id="test-remarks"
                  rows="2"
                  value={testForm.remarks}
                  onChange={(e) => handleTestFormChange("remarks", e.target.value)}
                  placeholder="Any additional observations"
                />

                <div className="staff-test-actions">
                  <button type="button" className="staff-table-btn default" onClick={loadUntestedDonations} disabled={busyKey !== ""}>
                    Refresh Donation List
                  </button>
                  <button type="submit" className="staff-table-btn confirm" disabled={busyKey !== "" || !testForm.donationId}>
                    {busyKey.startsWith("donation-test-") ? "Saving..." : "Save Test Results"}
                  </button>
                </div>
                {!testForm.donationId && (
                  <div className="staff-test-note">No pending donation selected. Add/link unit donation IDs first or refresh the list.</div>
                )}
                {selectedDonation && (
                  <div className="staff-test-note">
                    On save, the donor status will be updated and shown in the success banner.
                  </div>
                )}
              </form>
            </section>

            <div className="staff-table-toolbar">
              <label htmlFor="lab-result-filter">Result Filter</label>
              <select
                id="lab-result-filter"
                value={labResultFilter}
                onChange={(e) => setLabResultFilter(e.target.value)}
              >
                <option value="all">All</option>
                <option value="pending">Pending</option>
                <option value="compatible">Compatible</option>
                <option value="incompatible">Incompatible</option>
              </select>
            </div>
            <div className="staff-table-wrap">
              <table className="staff-table">
                <thead>
                  <tr>
                    <th>Timestamp</th><th>Test</th><th>Request</th><th>Patient</th><th>Blood Group</th><th>Result</th><th>Technician</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredLabLogs.length === 0 ? (
                    <tr><td colSpan="8" className="empty-row">No lab logs available for the selected filter.</td></tr>
                  ) : filteredLabLogs.map((log, i) => (
                    <tr key={log.id || `${log.sample_reference}-${i}`}>
                      <td>{log.logged_at || log.date}</td>
                      <td>{log.test_name}</td>
                      <td>{log.request_code || log.sample_reference}</td>
                      <td>{log.patient_name || "—"}</td>
                      <td>{log.blood_type || "—"}</td>
                      <td><span className={`tag ${String(log.result || "pending").toLowerCase()}`}>{log.result}</span></td>
                      <td>{log.technician_name}</td>
                      <td>
                        <button type="button" className="staff-table-btn default" onClick={() => setSelectedLabLog(log)}>
                          View Details
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className={`staff-tab-panel${activeTab === "samples" ? " active" : ""}`}>
            <section className="staff-test-form-card">
              <h3>Collect Donor Sample</h3>
              <p>Draw a small sample first. Only donors with a negative sample can later receive a full unit.</p>
              <form className="staff-test-form" onSubmit={handleSampleCollectionSubmit}>
                <div style={{ display: 'grid', gap: 8 }}>
                  <label htmlFor="sample-donor">Pending Sample <span className="required">*</span></label>
                  <select id="sample-donor" value={sampleCollectionForm.donorId || ""} onChange={(e) => handleSampleCollectionChange("donorId", e.target.value)} required>
                    <option value="">-- Select donor --</option>
                    {sampleCollectionDonors.map((donor) => (
                      <option key={`sample-donor-${donor.id}`} value={donor.id}>
                        {donor.full_name} ({donor.blood_type || "—"}) · Sample: {donor.sample_tested || "Pending"} · ID {donor.id}
                      </option>
                    ))}
                  </select>
                  {sampleCollectionDonors.length === 0 && (
                    <div className="staff-modal-hint" style={{ marginTop: 8 }}>
                      No pending donor samples were found. Refresh samples or collect a new sample first.
                    </div>
                  )}
                </div>

                <div className="staff-test-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 12 }}>
                  <div>
                    <label htmlFor="sample-collection-date">Collection Date <span className="required">*</span></label>
                    <input id="sample-collection-date" type="date" value={sampleCollectionForm.collectionDate} onChange={(e) => handleSampleCollectionChange("collectionDate", e.target.value)} required />
                  </div>
                  <div>
                    <label htmlFor="sample-collection-time">Collection Time <span className="required">*</span></label>
                    <input id="sample-collection-time" type="time" value={sampleCollectionForm.collectionTime} onChange={(e) => handleSampleCollectionChange("collectionTime", e.target.value)} required />
                  </div>
                </div>

                <div>
                  <label htmlFor="sample-technician">Technician <span className="required">*</span></label>
                  <input id="sample-technician" type="text" value={sampleCollectionForm.technician} onChange={(e) => handleSampleCollectionChange("technician", e.target.value)} placeholder="Enter technician name" required />
                </div>

                <div className="staff-test-actions">
                  <button type="button" className="staff-table-btn default" onClick={loadSampleRecords} disabled={busyKey !== ""}>
                    Refresh Samples
                  </button>
                  <button type="submit" className="staff-table-btn confirm" disabled={busyKey !== "" || !sampleCollectionForm.donorId}>
                    {busyKey.startsWith("sample-collect-") ? "Collecting..." : "Collect Sample"}
                  </button>
                </div>
              </form>
            </section>

            <section className="staff-test-form-card" style={{ marginTop: 18 }}>
              <h3>Record Sample Test Results</h3>
              <p>Enter HIV, HBsAg, HCV, Syphilis, and Malaria results for the collected sample.</p>
              <form className="staff-test-form" onSubmit={handleSampleTestSubmit}>
                <div style={{ display: 'grid', gap: 8 }}>
                  <label htmlFor="sample-record">Pending Sample <span className="required">*</span></label>
                  <select id="sample-record" value={sampleTestForm.sampleId || ""} onChange={(e) => handleSampleTestChange("sampleId", e.target.value)} required>
                    <option value="">-- Select pending sample --</option>
                    {pendingSampleOptions.map((sample) => (
                      <option key={`sample-record-${sample.id}`} value={sample.id}>
                        #{sample.id} · {sample.donor_name} · {sample.collection_date} · {sample.technician}
                      </option>
                    ))}
                  </select>
                  {donorSamplesError && (
                    <div className="staff-modal-hint" style={{ color: '#b45309', marginTop: 8 }}>
                      {donorSamplesError}
                    </div>
                  )}
                  {!donorSamplesLoading && pendingSampleRows.length === 0 && !donorSamplesError && (
                    <div className="staff-modal-hint" style={{ marginTop: 8 }}>
                      No pending donor samples were found. Refresh samples or collect a new sample first.
                    </div>
                  )}
                </div>

                <div>
                  <label htmlFor="sample-test-technician">Testing Technician <span className="required">*</span></label>
                  <input id="sample-test-technician" type="text" value={sampleTestForm.technician} onChange={(e) => handleSampleTestChange("technician", e.target.value)} placeholder="Enter technician name" required />
                </div>

                <div className="staff-test-form-card" style={{ marginTop: 18 }}>
                  <h4 style={{ fontSize: 14, marginBottom: 10 }}>🩺 MANDATORY HEALTH CHECKS (Required before donation)</h4>
                  <table className="staff-table" style={{ marginTop: 8 }}>
                    <thead>
                      <tr>
                        <th>Check</th>
                        <th>Value</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {[
                        { id: 'hemoglobin', label: 'Hemoglobin (g/dL)' },
                        { id: 'bloodPressure', label: 'Blood Pressure (mmHg)' },
                        { id: 'pulse', label: 'Pulse (bpm)' },
                        { id: 'temperature', label: 'Temperature (°C)' },
                        { id: 'weight', label: 'Weight (kg)' },
                      ].map((check) => (
                        <tr key={check.id}>
                          <td>{check.label}</td>
                          <td>
                            <input
                              id={`health-${check.id}`}
                              value={healthCheckForm[check.id]}
                              onChange={(e) => handleHealthCheckChange(check.id, e.target.value)}
                              placeholder={check.id === 'bloodPressure' ? 'e.g. 120/80' : ''}
                              style={{ width: '100%', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm)', padding: '8px 10px', fontSize: 12 }}
                            />
                          </td>
                          <td>
                            <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
                              <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <input
                                  type="radio"
                                  name={`health-status-${check.id}`}
                                  value="pass"
                                  checked={healthCheckStatus[check.id] === 'pass'}
                                  onChange={(e) => handleHealthCheckStatusChange(check.id, e.target.value)}
                                />
                                Pass
                              </label>
                              <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <input
                                  type="radio"
                                  name={`health-status-${check.id}`}
                                  value="fail"
                                  checked={healthCheckStatus[check.id] === 'fail'}
                                  onChange={(e) => handleHealthCheckStatusChange(check.id, e.target.value)}
                                />
                                Fail
                              </label>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="staff-test-form-card" style={{ marginTop: 18 }}>
                  <h4 style={{ fontSize: 14, marginBottom: 10 }}>🧪 LAB TEST RESULTS (Enter each disease and result)</h4>
                  <div style={{ display: 'grid', gap: 16, gridTemplateColumns: '1.2fr 0.8fr' }}>
                    <div style={{ display: 'grid', gap: 10 }}>
                      <table className="staff-table" style={{ width: '100%', marginTop: 8 }}>
                        <thead>
                          <tr>
                            <th>Disease / Condition</th>
                            <th>Result</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>HIV</td>
                            <td>
                              <select id="sample-hiv" value={sampleTestForm.hivResult} onChange={(e) => handleSampleTestChange("hivResult", e.target.value)}>
                                <option value="Non-reactive">Non-reactive</option>
                                <option value="Reactive">Reactive</option>
                                <option value="Inconclusive">Inconclusive</option>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td>HBsAg</td>
                            <td>
                              <select id="sample-hbsag" value={sampleTestForm.hbsagResult} onChange={(e) => handleSampleTestChange("hbsagResult", e.target.value)}>
                                <option value="Non-reactive">Non-reactive</option>
                                <option value="Reactive">Reactive</option>
                                <option value="Inconclusive">Inconclusive</option>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td>HCV</td>
                            <td>
                              <select id="sample-hcv" value={sampleTestForm.hcvResult} onChange={(e) => handleSampleTestChange("hcvResult", e.target.value)}>
                                <option value="Non-reactive">Non-reactive</option>
                                <option value="Reactive">Reactive</option>
                                <option value="Inconclusive">Inconclusive</option>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td>Syphilis</td>
                            <td>
                              <select id="sample-syphilis" value={sampleTestForm.syphilisResult} onChange={(e) => handleSampleTestChange("syphilisResult", e.target.value)}>
                                <option value="Non-reactive">Non-reactive</option>
                                <option value="Reactive">Reactive</option>
                                <option value="Inconclusive">Inconclusive</option>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td>Malaria</td>
                            <td>
                              <select id="sample-malaria" value={sampleTestForm.malariaResult} onChange={(e) => handleSampleTestChange("malariaResult", e.target.value)}>
                                <option value="Non-reactive">Non-reactive</option>
                                <option value="Reactive">Reactive</option>
                                <option value="Inconclusive">Inconclusive</option>
                              </select>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div style={{ display: 'grid', gap: 10 }}>
                      {extraDiseaseRows.map((row, index) => (
                        <div key={`extra-disease-${index}`} style={{ display: 'grid', gap: 8 }}>
                          <label htmlFor={`extra-disease-name-${index}`}>Disease / Condition</label>
                          <input
                            id={`extra-disease-name-${index}`}
                            value={row.name}
                            onChange={(e) => handleExtraDiseaseChange(index, 'name', e.target.value)}
                            placeholder="Enter disease name"
                          />
                          <label htmlFor={`extra-disease-result-${index}`}>Result</label>
                          <select
                            id={`extra-disease-result-${index}`}
                            value={row.result}
                            onChange={(e) => handleExtraDiseaseChange(index, 'result', e.target.value)}
                          >
                            <option value="Negative">Negative</option>
                            <option value="Positive">Positive</option>
                            <option value="Reactive">Reactive</option>
                          </select>
                          {extraDiseaseRows.length > 1 && (
                            <button
                              type="button"
                              className="staff-table-btn default"
                              style={{ padding: '8px 10px', justifySelf: 'start' }}
                              onClick={() => handleRemoveDiseaseRow(index)}
                            >
                              Remove
                            </button>
                          )}
                        </div>
                      ))}
                      <button
                        type="button"
                        className="staff-table-btn default"
                        style={{ width: 'fit-content', marginTop: 2 }}
                        onClick={handleAddDiseaseRow}
                      >
                        + Add More Disease
                      </button>
                    </div>
                  </div>
                </div>

                <label htmlFor="sample-notes">Staff Notes</label>
                <textarea id="sample-notes" rows="2" value={sampleTestForm.notes} onChange={(e) => handleSampleTestChange("notes", e.target.value)} placeholder="Optional observations" />

                <div className="staff-test-form-card" style={{ marginTop: 18 }}>
                  <h4 style={{ fontSize: 14, marginBottom: 10 }}>✅ DEFERRAL DECISION (Based on all above checks)</h4>
                  <div className="staff-deferral-options">
                    <div className="staff-deferral-option staff-deferral-approve">
                      <div className="staff-deferral-control">
                        <input
                          type="radio"
                          name="deferral-decision"
                          value="approve"
                          checked={deferralDecision === 'approve'}
                          onChange={(e) => setDeferralDecision(e.target.value)}
                        />
                      </div>
                      <div className="staff-deferral-meta">
                        <span>APPROVE - Eligible to donate</span>
                      </div>
                    </div>

                    <div className="staff-deferral-option staff-deferral-temporary">
                      <div className="staff-deferral-control">
                        <input
                          type="radio"
                          name="deferral-decision"
                          value="temporary"
                          checked={deferralDecision === 'temporary'}
                          onChange={(e) => setDeferralDecision(e.target.value)}
                        />
                      </div>
                      <div className="staff-deferral-meta">
                        <span>TEMPORARY DEFERRAL - Select period:</span>
                        <select
                          value={deferralPeriod}
                          onChange={(e) => setDeferralPeriod(e.target.value)}
                          disabled={deferralDecision !== 'temporary'}
                        >
                          <option value="3 months">3 months</option>
                          <option value="6 months">6 months</option>
                          <option value="12 months">12 months</option>
                        </select>
                      </div>
                    </div>

                    <div className="staff-deferral-option staff-deferral-permanent">
                      <div className="staff-deferral-control">
                        <input
                          type="radio"
                          name="deferral-decision"
                          value="permanent"
                          checked={deferralDecision === 'permanent'}
                          onChange={(e) => setDeferralDecision(e.target.value)}
                        />
                      </div>
                      <div className="staff-deferral-meta">
                        <span>PERMANENT DEFERRAL - Reason:</span>
                        <input
                          type="text"
                          value={deferralReason}
                          onChange={(e) => setDeferralReason(e.target.value)}
                          placeholder="Enter reason for permanent deferral..."
                          disabled={deferralDecision !== 'permanent'}
                        />
                      </div>
                    </div>
                  </div>
                </div>
in 
                <div className="staff-test-actions" style={{ justifyContent: 'space-between', marginTop: 12 }}>
                  <button type="button" className="staff-table-btn default">
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="staff-table-btn confirm"
                    disabled={busyKey !== "" || !sampleTestForm.sampleId || !sampleTestForm.technician.trim()}
                    style={busyKey !== "" || !sampleTestForm.sampleId || !sampleTestForm.technician.trim() ? { opacity: 0.6, cursor: 'not-allowed' } : {}}
                  >
                    {busyKey.startsWith("sample-test-") ? "Saving..." : "Save Sample Results"}
                  </button>
                </div>
                {(!sampleTestForm.sampleId || !sampleTestForm.technician.trim()) && (
                  <div style={{ color: '#b45309', fontSize: '12px', marginTop: '8px' }}>
                    ⚠️ Please select a sample and enter technician name before saving.
                  </div>
                )}
              </form>
            </section>

            {donorSamplesError && <div className="staff-modal-hint" style={{ color: '#b45309', marginTop: 12 }}>{donorSamplesError}</div>}
            <div className="staff-table-wrap" style={{ marginTop: 16 }}>
              <table className="staff-table">
                <thead>
                  <tr>
                    <th>Sample</th><th>Donor</th><th>Collection Date</th><th>Technician</th><th>Status</th><th>HIV</th><th>HBsAg</th><th>HCV</th><th>Syphilis</th><th>Malaria</th>
                  </tr>
                </thead>
                <tbody>
                  {donorSamplesLoading ? (
                    <tr><td colSpan="10" className="empty-row">Loading samples...</td></tr>
                  ) : donorSamples.length === 0 ? (
                    <tr><td colSpan="10" className="empty-row">No donor samples found.</td></tr>
                  ) : donorSamples.map((sample) => (
                    <tr key={sample.id}>
                      <td>#{sample.id}</td>
                      <td>{sample.donor_name}<div className="staff-modal-hint">{sample.blood_type || "—"} · {sample.donor_status || "—"}</div></td>
                      <td>{sample.collection_date}</td>
                      <td>{sample.technician}</td>
                      <td><span className={`tag ${String(sample.status || "pending").toLowerCase()}`}>{sample.status}</span></td>
                      <td>{sample.hiv_result || "—"}</td>
                      <td>{sample.hbsag_result || "—"}</td>
                      <td>{sample.hcv_result || "—"}</td>
                      <td>{sample.syphilis_result || "—"}</td>
                      <td>{sample.malaria_result || "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className={`staff-tab-panel${activeTab === "issues" ? " active" : ""}`}>
            <div className="staff-table-wrap">
              <table className="staff-table">
                <thead>
                  <tr>
                    <th>Date</th><th>Request Code</th><th>Patient</th><th>Blood Type</th><th>Component</th><th>Unit ID</th><th>Units</th><th>Issued By</th>
                  </tr>
                </thead>
                <tbody>
                  {issueLogs.length === 0 ? (
                    <tr><td colSpan="8" className="empty-row">No issue log entries yet.</td></tr>
                  ) : issueLogs.map((log, i) => (
                    <tr key={`${log.request_code}-${i}`}>
                      <td>{log.issued_at}</td>
                      <td>{log.request_code}</td>
                      <td>{log.patient_name}</td>
                      <td>{log.blood_type || "—"}</td>
                      <td>{log.component}</td>
                      <td>{log.issued_unit_id || "—"}</td>
                      <td>{log.units_issued}</td>
                      <td>{log.staff_name}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {addInventoryModal.open && (
          <div className="staff-modal-backdrop" role="presentation" onClick={closeAddInventoryModal}>
            <div className="staff-modal" role="dialog" aria-modal="true" aria-labelledby="add-inventory-title" onClick={(e) => e.stopPropagation()}>
              <h3 id="add-inventory-title">Add Blood Unit</h3>
              <p>
                Blood Group: <strong>{addInventoryModal.bloodType}</strong>
              </p>
              <form onSubmit={handleAddInventorySubmit}>
                <p className="staff-modal-hint">Record individual blood unit (bag) with donation ID, component type, and expiry date.</p>

                {actionError && (
                  <div style={{
                    background: '#fee',
                    border: '1px solid #f99',
                    borderRadius: '6px',
                    padding: '10px 12px',
                    marginBottom: '12px',
                    fontSize: '13px',
                    color: '#c00',
                    fontWeight: '500'
                  }}>
                    {actionError}
                  </div>
                )}

                <label htmlFor="inventory-donation-id">Donation ID (optional)</label>
                <input
                  id="inventory-donation-id"
                  value={addInventoryModal.donationId}
                  onChange={(e) => handleInventoryModalChange("donationId", e.target.value)}
                  type="text"
                  inputMode="numeric"
                  pattern="[0-9]*"
                  placeholder="Leave blank to add without linking a donation"
                />

                <label htmlFor="inventory-donor">Select Donor <span className="required">*</span></label>
                <select
                  id="inventory-donor"
                  value={addInventoryModal.donorId || ""}
                  onChange={(e) => handleInventoryModalChange('donorId', Number(e.target.value))}
                  required
                  disabled={confirmedDonorsLoading}
                >
                  <option value="">-- Select confirmed donor --</option>
                  {inventoryDonorsForSelectedBloodType.map((d) => (
                    <option key={`donor-${d.id}`} value={d.id}>{d.full_name || d.name || `Donor ${d.id}`} [{d.blood_type || '—'}]</option>
                  ))}
                </select>
                {confirmedDonorsLoading && <div className="staff-modal-hint">Loading sample-cleared donors...</div>}
                {!confirmedDonorsLoading && addInventoryModal.bloodType && inventoryDonorsForSelectedBloodType.length === 0 && (
                  <div className="staff-modal-hint" style={{ color: '#b45309' }}>
                    No confirmed donors found for {addInventoryModal.bloodType}.
                  </div>
                )}
                {confirmedDonorsError && <div className="staff-modal-hint" style={{ color: '#b45309' }}>{confirmedDonorsError}</div>}

                <label htmlFor="inventory-component">Component Type</label>
                <select
                  id="inventory-component"
                  value={addInventoryModal.component}
                  onChange={(e) => handleInventoryModalChange("component", e.target.value)}
                >
                  <option value="Whole Blood">Whole Blood</option>
                  <option value="Packed Red Cells">Packed Red Cells (PRBC)</option>
                  <option value="Platelets">Platelets</option>
                  <option value="Plasma">Plasma</option>
                  <option value="FFP">Fresh Frozen Plasma (FFP)</option>
                  <option value="Cryoprecipitate">Cryoprecipitate</option>
                </select>

                <label htmlFor="inventory-expiry">Expiry Date <span className="required">*</span></label>
                <input
                  id="inventory-expiry"
                  type="date"
                  value={addInventoryModal.expiryDate}
                  onChange={(e) => handleInventoryModalChange("expiryDate", e.target.value)}
                  required
                />

                <label htmlFor="inventory-blood-type">Blood Group (Pre-filled)</label>
                <input
                  id="inventory-blood-type"
                  type="text"
                  value={addInventoryModal.bloodType}
                  readOnly
                  aria-readonly="true"
                  style={{ opacity: 0.7 }}
                />

                <label htmlFor="inventory-status">Status</label>
                <select
                  id="inventory-status"
                  value={addInventoryModal.status}
                  onChange={(e) => handleInventoryModalChange("status", e.target.value)}
                  disabled
                >
                  <option value="Available">Available</option>
                </select>

                <div className="staff-modal-actions">
                  <button type="button" className="staff-table-btn default" onClick={closeAddInventoryModal} disabled={busyKey !== ""}>
                    Cancel
                  </button>
                  <button type="submit" className="staff-table-btn confirm" disabled={busyKey !== ""}>
                    {busyKey && busyKey.startsWith("unit-") ? "Adding…" : "Add Unit"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {crossMatchModal.open && (
          <div className="staff-modal-backdrop" role="presentation" onClick={closeCrossMatchModal}>
            <div className="staff-modal staff-modal-crossmatch" role="dialog" aria-modal="true" aria-labelledby="crossmatch-modal-title" onClick={(e) => e.stopPropagation()}>
              <h3 id="crossmatch-modal-title">Record Cross-Match Result</h3>
              <p>
                Request ID: <strong>{crossMatchModal.requestId}</strong>
              </p>
              
              {actionError && (
                <div style={{
                  background: '#fee',
                  border: '1px solid #f99',
                  borderRadius: '6px',
                  padding: '10px 12px',
                  marginBottom: '12px',
                  fontSize: '13px',
                  color: '#c00',
                  fontWeight: '500'
                }}>
                  ✗ {actionError}
                </div>
              )}
              
              <form onSubmit={handleCrossMatchSubmit}>
                <label htmlFor="crossmatch-result">Result</label>
                <select
                  id="crossmatch-result"
                  value={crossMatchModal.result}
                  onChange={(e) => handleCrossMatchModalChange("result", e.target.value)}
                >
                  <option value="Pending">Pending</option>
                  <option value="Compatible">Compatible</option>
                  <option value="Incompatible">Incompatible</option>
                </select>

                {crossMatchModal.loadingUnits && (
                  <p className="staff-modal-hint">Loading compatible donor units...</p>
                )}

                {!crossMatchModal.loadingUnits && modalAvailableUnits.length === 0 && (
                  <p className="staff-modal-hint">No available compatible donor units found for this request.</p>
                )}

                {modalAvailableUnits.length > 0 && (
                  <>
                    <label htmlFor="crossmatch-available-units">Available Compatible Units</label>
                    <select
                      id="crossmatch-available-units"
                      value={crossMatchModal.donorUnitRefs || (typeof modalAvailableUnits[0] === "string" ? modalAvailableUnits[0] : modalAvailableUnits[0]?.unitId || "")}
                      onChange={(e) => handleCrossMatchModalChange("donorUnitRefs", e.target.value)}
                    >
                      {modalAvailableUnits.map((item) => {
                        const unitId = typeof item === "string" ? item : String(item?.unitId || "").trim();
                        const bloodType = typeof item === "string" ? "" : String(item?.bloodType || "").trim();
                        if (!unitId) return null;
                        return (
                          <option key={`crossmatch-${crossMatchModal.requestId}-${unitId}`} value={unitId}>
                            {bloodType ? `${unitId} - ${bloodType}` : unitId}
                          </option>
                        );
                      })}
                    </select>
                    <div className="staff-unit-list">
                      {modalAvailableUnits.map((item) => {
                        const unitId = typeof item === "string" ? item : String(item?.unitId || "").trim();
                        const bloodType = typeof item === "string" ? "" : String(item?.bloodType || "").trim();
                        if (!unitId) return null;
                        return (
                          <div className="staff-unit-item" key={`unit-copy-${crossMatchModal.requestId}-${unitId}`}>
                            <input
                              type="text"
                              value={bloodType ? `${unitId} (${bloodType})` : unitId}
                              readOnly
                              aria-label={`Donor unit ${unitId}${bloodType ? ` blood type ${bloodType}` : ""}`}
                            />
                          <button
                            type="button"
                            className="staff-table-btn default"
                            onClick={() => copyUnitIdToClipboard(unitId)}
                          >
                            Copy ID
                          </button>
                          <button
                            type="button"
                            className="staff-table-btn primary"
                            onClick={() => handleCrossMatchModalChange("donorUnitRefs", unitId)}
                          >
                            Use
                          </button>
                          </div>
                        );
                      })}
                    </div>
                  </>
                )}

                <label htmlFor="crossmatch-units">Donor Unit ID(s)</label>
                <input
                  id="crossmatch-units"
                  type="text"
                  value={crossMatchModal.donorUnitRefs}
                  onChange={(e) => handleCrossMatchModalChange("donorUnitRefs", e.target.value)}
                  placeholder="e.g. U-2026-00012"
                />

                <label htmlFor="crossmatch-params">Test Parameters</label>
                <textarea
                  id="crossmatch-params"
                  value={crossMatchModal.testParameters}
                  onChange={(e) => handleCrossMatchModalChange("testParameters", e.target.value)}
                  placeholder="e.g. AHG phase, saline phase, incubation notes"
                  rows="3"
                />

                <label htmlFor="crossmatch-notes">Notes</label>
                <textarea
                  id="crossmatch-notes"
                  value={crossMatchModal.notes}
                  onChange={(e) => handleCrossMatchModalChange("notes", e.target.value)}
                  placeholder="Optional remarks"
                  rows="2"
                />

                <div className="staff-modal-actions">
                  <button type="button" className="staff-table-btn default" onClick={closeCrossMatchModal} disabled={busyKey !== ""}>
                    Cancel
                  </button>
                  <button type="submit" className="staff-table-btn primary" disabled={busyKey !== ""}>
                    {busyKey === `cross-${crossMatchModal.requestId}-${crossMatchModal.result}` ? "Saving…" : "Save Result"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {selectedLabLog && (
          <div className="staff-modal-backdrop" role="presentation" onClick={() => setSelectedLabLog(null)}>
            <div className="staff-modal staff-modal-wide" role="dialog" aria-modal="true" aria-labelledby="lab-log-details-title" onClick={(e) => e.stopPropagation()}>
              <h3 id="lab-log-details-title">Cross-Match Details</h3>
              <div className="staff-detail-grid">
                <div><strong>Logged At:</strong> {selectedLabLog.logged_at || selectedLabLog.date || "—"}</div>
                <div><strong>Request ID:</strong> {selectedLabLog.request_id ?? "—"}</div>
                <div><strong>Technician:</strong> {selectedLabLog.technician_name || "—"}</div>
                <div><strong>Request:</strong> {selectedLabLog.request_code || selectedLabLog.sample_reference || "—"}</div>
                <div><strong>Doctor:</strong> {selectedLabLog.doctor_name || "—"}</div>
                <div><strong>Result:</strong> {selectedLabLog.result || "—"}</div>
                <div><strong>Request Status:</strong> {selectedLabLog.request_status || "—"}</div>
                <div><strong>Patient:</strong> {selectedLabLog.patient_name || "—"}</div>
                <div><strong>Blood Type:</strong> {selectedLabLog.blood_type || "—"}</div>
                <div><strong>Component:</strong> {selectedLabLog.component || "—"}</div>
                <div><strong>Units Requested:</strong> {selectedLabLog.units_requested ?? "—"}</div>
                <div><strong>Donor Unit ID(s):</strong> {selectedLabLog.donor_unit_refs || selectedLabLog.donor_unit_id || "—"}</div>
                <div><strong>Donor Unit Status:</strong> {selectedLabLog.donor_unit_status || "—"}</div>
                <div><strong>Donor Blood Type:</strong> {selectedLabLog.donor_blood_type || "—"}</div>
                <div><strong>Donor Component:</strong> {selectedLabLog.donor_component || "—"}</div>
                <div><strong>Test Parameters:</strong> {selectedLabLog.test_parameters || "—"}</div>
                <div><strong>Notes:</strong> {selectedLabLog.notes || "—"}</div>
              </div>
              <div className="staff-modal-actions">
                <button type="button" className="staff-table-btn default" onClick={() => setSelectedLabLog(null)}>
                  Close
                </button>
              </div>
            </div>
          </div>
        )}

        {issueBloodModal.open && (
          <div className="staff-modal-backdrop" role="presentation" onClick={closeIssueBloodModal}>
            <div className="staff-modal staff-modal-issue" role="dialog" aria-modal="true" aria-labelledby="issue-blood-title" onClick={(e) => e.stopPropagation()}>
              <h3 id="issue-blood-title">Confirm Blood Issuance</h3>
              {issueBloodModal.request && (
                <div className="issue-blood-details">
                  <div className={`issue-detail-row${isUrgentRequest(issueBloodModal.request) ? " urgent" : ""}`}>
                    <span className="detail-label">Request ID:</span>
                    <span className="detail-value">{issueBloodModal.request.request_code}</span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Patient Name:</span>
                    <span className="detail-value">{issueBloodModal.request.patient_name}</span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Doctor:</span>
                    <span className="detail-value">{issueBloodModal.request.doctor_name}</span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Blood Type:</span>
                    <span className="detail-value"><strong>{issueBloodModal.request.blood_type}</strong></span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Component:</span>
                    <span className="detail-value">{issueBloodModal.request.component || "Whole Blood"}</span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Units Requested:</span>
                    <span className="detail-value"><strong>{issueBloodModal.request.units_requested}</strong></span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Urgency:</span>
                    <span className={`detail-value urgency-${String(issueBloodModal.request.urgency || "routine").toLowerCase()}`}>
                      {issueBloodModal.request.urgency || "Routine"}
                    </span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Status:</span>
                    <span className="detail-value">{getDisplayStatusLabel(issueBloodModal.request.status)}</span>
                  </div>
                  <div className="issue-detail-row">
                    <span className="detail-label">Compatible Unit ID:</span>
                    <span className="detail-value"><strong>{issueBloodModal.compatibleUnitId || "Not linked"}</strong></span>
                  </div>
                </div>
              )}
              <form onSubmit={handleIssueBloodSubmit}>
                <p className="staff-modal-hint">
                  ✓ This request has been cross-matched and is ready for issuance. Review details above and confirm to proceed.
                </p>

                <label htmlFor="issue-staff-confirm">Issuing Staff Confirmation <span className="required">*</span></label>
                <input
                  id="issue-staff-confirm"
                  type="text"
                  value={issueBloodModal.staffConfirmedBy}
                  onChange={(e) => handleIssueBloodModalChange("staffConfirmedBy", e.target.value)}
                  placeholder="Enter your name"
                  required
                />

                <label htmlFor="issue-blood-comment">Staff Comment (Optional)</label>
                <textarea
                  id="issue-blood-comment"
                  value={issueBloodModal.comment}
                  onChange={(e) => handleIssueBloodModalChange("comment", e.target.value)}
                  placeholder="e.g. Special handling, prioritized due to critical surgery, etc."
                  rows="2"
                />

                <div className="issue-blood-emergency">
                  <label htmlFor="issue-blood-emergency-check">
                    <input
                      id="issue-blood-emergency-check"
                      type="checkbox"
                      checked={issueBloodModal.isEmergency}
                      onChange={(e) => handleIssueBloodModalChange("isEmergency", e.target.checked)}
                    />
                    <span className="check-label">🚨 Emergency Issuance (Skip non-critical checks)</span>
                  </label>
                  {issueBloodModal.isEmergency && (
                    <div className="emergency-warning">
                      <strong>⚠️ Warning:</strong> Emergency mode will bypass certain standard validations and create an audit flag. Use only in critical life-threatening situations. This action will be logged separately.
                    </div>
                  )}
                </div>

                <div className="staff-modal-actions">
                  <button type="button" className="staff-table-btn default" onClick={closeIssueBloodModal} disabled={busyKey !== ""}>
                    Cancel
                  </button>
                  <button 
                    type="submit" 
                    className={`staff-table-btn ${issueBloodModal.isEmergency ? "danger" : "confirm"}`}
                    disabled={busyKey !== ""}
                  >
                    {busyKey && busyKey.startsWith("issue-") 
                      ? (issueBloodModal.isEmergency ? "Issuing (Emergency)…" : "Issuing…")
                      : (issueBloodModal.isEmergency ? "Issue Blood (Emergency)" : "Confirm & Issue Blood")
                    }
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        <div className="staff-quick-wrap">
          <div className="staff-quick-title">Quick Actions</div>
          <div className="staff-links">
            <Link to="/doctor">
              <span className="link-left"><span className="link-icon">🩺</span> Doctor Dashboard</span>
              <span className="arrow">→</span>
            </Link>
            <Link to="/admin">
              <span className="link-left"><span className="link-icon">🛡️</span> Admin Dashboard</span>
              <span className="arrow">→</span>
            </Link>
            <Link to="/dashboard">
              <span className="link-left"><span className="link-icon">💉</span> Donor Dashboard</span>
              <span className="arrow">→</span>
            </Link>
            <Link to="/">
              <span className="link-left"><span className="link-icon">🏠</span> Back to Home</span>
              <span className="arrow">→</span>
            </Link>
          </div>
        </div>

      </main>
    </div>
  );
}