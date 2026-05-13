import React, { useEffect, useState, useCallback, useRef } from "react";

import { Link, useNavigate } from "react-router-dom";

import { toast } from "react-toastify";

import "./AdminDashboard.css";

import "./AdminDashboard-DeferModal.css";

import { authFetch, clearAuthSession, getStoredUser } from "../utils/auth";

import AdminShell from "../components/admin/AdminShell";

import DonorDetailsPanel from "../components/admin/DonorDetailsPanel";

import ViewDetailsModal from "../components/ViewDetailsModal";



export default function AdminDashboard() {

  const navigate = useNavigate();

  const bankFormRef = useRef(null);

  const [user, setUser] = useState(null);

  const [activeTab, setActiveTab] = useState("dashboard");



  const [stats, setStats] = useState(null);

  const [appointments, setAppointments] = useState(null);

  const [camps, setCamps] = useState(null);

  const [donors, setDonors] = useState(null);

  const [stage2Donors, setStage2Donors] = useState(null);

  const [pendingDonors, setPendingDonors] = useState(null);

  const [bloodBanks, setBloodBanks] = useState(null);

  const [notifications, setNotifications] = useState([]);

  const [unreadNotificationCount, setUnreadNotificationCount] = useState(0);

  const [lowStockItems, setLowStockItems] = useState([]);



  const [loadingStats, setLoadingStats] = useState(true);

  const [loadingAppointments, setLoadingAppointments] = useState(true);

  const [loadingCamps, setLoadingCamps] = useState(true);

  const [loadingDonors, setLoadingDonors] = useState(true);

  const [loadingStage2Donors, setLoadingStage2Donors] = useState(true);

  const [loadingPendingDonors, setLoadingPendingDonors] = useState(true);

  const [loadingBloodBanks, setLoadingBloodBanks] = useState(true);

  const [loadingNotifications, setLoadingNotifications] = useState(true);

  const [loadingLowStock, setLoadingLowStock] = useState(true);



  const [errorAppointments, setErrorAppointments] = useState("");

  const [errorCamps, setErrorCamps] = useState("");

  const [errorDonors, setErrorDonors] = useState("");

  const [errorStage2Donors, setErrorStage2Donors] = useState("");

  const [errorPendingDonors, setErrorPendingDonors] = useState("");

  const [errorBloodBanks, setErrorBloodBanks] = useState("");

  const [errorNotifications, setErrorNotifications] = useState("");

  const [errorLowStock, setErrorLowStock] = useState("");

  const [actionError, setActionError] = useState("");

  const [approvingSampleIds, setApprovingSampleIds] = useState(new Set());

  const [bankMessage, setBankMessage] = useState("");

  const [bannerToast, setBannerToast] = useState(null);

  const [isToastShowing, setIsToastShowing] = useState(false);

  const [archiveTarget, setArchiveTarget] = useState(null);

  // Details modal state
  const [detailsModal, setDetailsModal] = useState({ isOpen: false, type: null, data: null });

  const [appointmentEdit, setAppointmentEdit] = useState(null);

  const [includeInactiveBanks, setIncludeInactiveBanks] = useState(false);

  const [bloodBankSearch, setBloodBankSearch] = useState("");

  const [bloodBankPage, setBloodBankPage] = useState(1);

  const [bloodBankPerPage] = useState(10);

  const [bloodBankPagination, setBloodBankPagination] = useState({ total: 0, totalPages: 1, page: 1, perPage: 10 });

  const [expandedDonorRows, setExpandedDonorRows] = useState(new Set());

  const [selectedDonorDetails, setSelectedDonorDetails] = useState(null);

  const [donorDetailsOpen, setDonorDetailsOpen] = useState(false);

  const [donorDetailsLoading, setDonorDetailsLoading] = useState(false);

  const [donorDetailsError, setDonorDetailsError] = useState("");

  const [referenceSearchQuery, setReferenceSearchQuery] = useState("");

  const [referenceBloodType, setReferenceBloodType] = useState("");

  const [referenceWorkflowStatus, setReferenceWorkflowStatus] = useState("");

  

  // Admin decision modal state

  const [approveModal, setApproveModal] = useState({ open: false, donorId: null, donorName: "", message: "" });

  const [deferTemporaryModal, setDeferTemporaryModal] = useState({ open: false, donorId: null, donorName: "", months: 6, message: "" });

  const [permanentDeferralModal, setPermanentDeferralModal] = useState({ open: false, donorId: null, donorName: "", message: "" });

  const [deferralMonths, setDeferralMonths] = useState(6); // default 6 months for malaria/temporary deferral

  // Edit donor modal state
  const [editDonorModal, setEditDonorModal] = useState({ 
    open: false, 
    donorId: null,
    full_name: "",
    email: "",
    phone: "",
    date_of_birth: "",
    gender: "",
    blood_type: "",
    status: "",
    deferred: 0,
    deferred_until: null,
  });

  const [editingDonor, setEditingDonor] = useState(null);
  const [savingDonorEdit, setSavingDonorEdit] = useState(false);

  const [processingDecision, setProcessingDecision] = useState(false);

  const [decisionMessage, setDecisionMessage] = useState("");



  const BLOOD_GROUP_OPTIONS = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];



  const parseApiError = useCallback(async (response, fallbackMessage = "Request failed.") => {

    let message = fallbackMessage;

    try {

      const data = await response.json();

      if (data && typeof data.message === "string" && data.message.trim()) {

        message = data.message;

      }

    } catch (_) {}

    return `${message} (HTTP ${response.status})`;

  }, []);



  const toggleExpandDonor = (donorId) => {

    const newSet = new Set(expandedDonorRows);

    if (newSet.has(donorId)) newSet.delete(donorId);

    else newSet.add(donorId);

    setExpandedDonorRows(newSet);

  };



  const openDonorDetails = useCallback(async (donorId) => {

    if (!donorId) return;

    setDonorDetailsOpen(true);

    setDonorDetailsLoading(true);

    setDonorDetailsError("");

    try {

      const res = await authFetch(`backend/api/get_donor_details.php?id=${encodeURIComponent(String(donorId))}&_ts=${Date.now()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load donor details."));

      const data = await res.json();

      if (!data.success || !data.data) throw new Error(data.message || "Donor details not found.");

      setSelectedDonorDetails(data.data);

    } catch (error) {

      setSelectedDonorDetails(null);

      setDonorDetailsError(error.message || "Could not load donor details.");

    } finally {

      setDonorDetailsLoading(false);

    }

  }, [parseApiError]);



  const closeDonorDetails = useCallback(() => {

    setDonorDetailsOpen(false);

    setDonorDetailsError("");

  }, []);



  const openEditDonorModal = useCallback(async (donorId) => {

    if (!donorId) return;

    try {

      const res = await authFetch(`backend/api/get_donor_details.php?id=${encodeURIComponent(String(donorId))}&_ts=${Date.now()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load donor details."));

      const data = await res.json();

      if (!data.success || !data.data) throw new Error(data.message || "Donor details not found.");

      const donor = data.data;

      setEditingDonor(donor);

      setEditDonorModal({

        open: true,

        donorId: donor.id,

        full_name: donor.full_name || "",

        email: donor.email || "",

        phone: donor.phone || "",

        date_of_birth: donor.date_of_birth || "",

        gender: donor.gender || "",

        blood_type: donor.blood_type || "",

        status: donor.status || "",

        deferred: donor.deferred || 0,

        deferred_until: donor.deferred_until || null,

      });

    } catch (error) {

      toast.error(error.message || "Could not load donor details.");

    }

  }, [parseApiError]);



  const closeEditDonorModal = useCallback(() => {

    setEditDonorModal({

      open: false,

      donorId: null,

      full_name: "",

      email: "",

      phone: "",

      date_of_birth: "",

      gender: "",

      blood_type: "",

      status: "",

      deferred: 0,

      deferred_until: null,

    });

    setEditingDonor(null);

  }, []);



  const handleSaveDonorEdit = useCallback(async () => {

    if (!editDonorModal.donorId) return;

    if (!editDonorModal.full_name.trim()) {

      toast.error("Full name is required.");

      return;

    }

    if (!editDonorModal.email.trim()) {

      toast.error("Email is required.");

      return;

    }

    if (!editDonorModal.phone.trim()) {

      toast.error("Phone is required.");

      return;

    }

    setSavingDonorEdit(true);

    try {

      const payload = {

        donor_id: editDonorModal.donorId,

        full_name: editDonorModal.full_name.trim(),

        email: editDonorModal.email.trim().toLowerCase(),

        phone: editDonorModal.phone.trim(),

        date_of_birth: editDonorModal.date_of_birth || null,

        gender: editDonorModal.gender || null,

        blood_type: editDonorModal.blood_type || null,

        status: editDonorModal.status || null,

        deferred: editDonorModal.deferred ? 1 : 0,

        deferred_until: editDonorModal.deferred_until || null,

      };

      const res = await authFetch("backend/api/update_donor.php", {

        method: "PUT",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify(payload),

      });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to save donor."));

      const data = await res.json();

      if (!data.success) throw new Error(data.message || "Failed to save donor.");

      toast.success(data.message || "Donor updated successfully!");

      closeEditDonorModal();

      // Refresh donor lists

      if (activeTab === "donors") {

        setLoadingDonors(true);

      } else if (activeTab === "stage2") {

        setLoadingStage2Donors(true);

      } else if (activeTab === "pending") {

        setLoadingPendingDonors(true);

      }

    } catch (error) {

      toast.error(error.message || "Could not save donor.");

    } finally {

      setSavingDonorEdit(false);

    }

  }, [editDonorModal, activeTab, parseApiError, closeEditDonorModal]);



  useEffect(() => {

    const parsed = getStoredUser();

    if (!parsed?.token) { navigate("/login"); return; }

    if (parsed.role !== "admin") { navigate("/dashboard"); return; }

    setUser(parsed);

  }, [navigate]);



  const fetchStats = useCallback(async () => {

    setLoadingStats(true);

    try {

      const res = await authFetch("backend/api/get_stats.php");

      const data = await res.json();

      if (data.success) setStats(data);

    } catch (_) {}

    finally { setLoadingStats(false); }

  }, []);



  const fetchAppointments = useCallback(async () => {

    setLoadingAppointments(true);

    setErrorAppointments("");

    try {

      const res = await authFetch("backend/api/get_appointments.php");

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load appointments."));

      const data = await res.json();

      if (data.success) setAppointments(data.data);

      else setErrorAppointments(data.message || "Failed to load appointments.");

    } catch (error) {

      setErrorAppointments(error.message || "Could not reach server.");

    } finally { setLoadingAppointments(false); }

  }, [parseApiError]);



  const fetchCamps = useCallback(async () => {

    setLoadingCamps(true);

    setErrorCamps("");

    try {

      const res = await authFetch("backend/api/get_camps.php");

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load camps."));

      const data = await res.json();

      if (data.success) setCamps(data.data);

      else setErrorCamps(data.message || "Failed to load camps.");

    } catch (error) {

      setErrorCamps(error.message || "Could not reach server.");

    } finally { setLoadingCamps(false); }

  }, [parseApiError]);



  const fetchDonors = useCallback(async () => {

    setLoadingDonors(true);

    setErrorDonors("");

    try {

      const res = await authFetch(`backend/api/get_donor_tables.php?_ts=${Date.now()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load donors."));

      const data = await res.json();

      if (data.success) {

        const allDonors = data.all_donors || [];

        const awaitingDonors = data.awaiting_decision || [];

        const awaitingIds = new Set(awaitingDonors.map(d => d.id));

        const mergedDonors = allDonors.map(d => ({

          ...d,

          full_name: d.name,

          donor_name: d.name,

          awaiting_test_decision: awaitingIds.has(d.id)

        }));

        try {
          const prevMap = new Map((donors || []).map(d => [String(d.id), d]));
          const finalStatuses = ['decision_made_rejected', 'permanently_deferred', 'perm_defer', 'decision_made_deferred', 'temporarily_deferred', 'deferred_until_date', 'approved_donor', 'approved_to_donate'];
          const changed = [];
          const safeMerged = mergedDonors.map((nd) => {
            const pid = String(nd.id);
            const prev = prevMap.get(pid);
            if (!prev) return nd;

            // AUTHORITATIVE UPDATE RULES
            // 1) If server provides `workflow_updated_at` (ISO) and prev has it, accept nd only if newer.
            // 2) If server provides `updated_by_admin` truthy, accept nd.
            // 3) Otherwise, preserve prev.workflow_status to avoid client-side flips.

            const ndUpdatedAt = nd.workflow_updated_at ? Date.parse(String(nd.workflow_updated_at)) : null;
            const prevUpdatedAt = prev.workflow_updated_at ? Date.parse(String(prev.workflow_updated_at)) : null;
            const ndUpdatedByAdmin = !!nd.updated_by_admin;

            if (ndUpdatedAt && prevUpdatedAt) {
              if (ndUpdatedAt > prevUpdatedAt) {
                // server update is newer -> accept
              } else {
                nd.workflow_status = prev.workflow_status;
              }
            } else if (ndUpdatedByAdmin) {
              // admin-marked update -> accept
            } else {
              // no authoritative metadata -> preserve previous admin-set final statuses
              if (finalStatuses.includes(String(prev.workflow_status || '').toLowerCase())) {
                nd.workflow_status = prev.workflow_status;
              } else {
                // also prefer preserving the prev value to avoid oscillation unless server is explicit
                nd.workflow_status = prev.workflow_status;
              }
            }

            // Preserve explicit positive/negative test labels from previous state to avoid flip-flopping
            const prevDisplay = String(prev.latest_test_result_display || '').trim();
            if (prevDisplay && /positive|negative|reactive|non-reactive/i.test(prevDisplay)) {
              nd.latest_test_result_display = nd.latest_test_result_display || prevDisplay;
              nd.latest_test_result = nd.latest_test_result || prev.latest_test_result;
            }

            if (String(prev.workflow_status || '') !== String(nd.workflow_status || '')) {
              changed.push({ id: nd.id, before: prev.workflow_status, after: nd.workflow_status });
            }

            return nd;
          });
          if (changed.length > 0) console.debug('[fetchDonors] workflow_status changes detected (after protection):', changed);
          setDonors(safeMerged);
        } catch (e) {
          console.debug('[fetchDonors] debug compare failed', e);
          setDonors(mergedDonors);
        }

      } else {

        setErrorDonors(data.error || data.message || "Failed to load donors.");

      }

    } catch (error) {

      setErrorDonors(error.message || "Could not reach server.");

    } finally { setLoadingDonors(false); }

  }, [parseApiError]);



  const fetchStage2Donors = useCallback(async () => {

    setLoadingStage2Donors(true);

    setErrorStage2Donors("");

    try {

      const res = await authFetch(`backend/api/get_stage2_donors.php?_ts=${Date.now()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load Stage 2 donors."));

      const data = await res.json();

      if (data.success) setStage2Donors(Array.isArray(data.data) ? data.data : []);

      else setErrorStage2Donors(data.error || data.message || "Failed to load Stage 2 donors.");

    } catch (error) {

      setErrorStage2Donors(error.message || "Could not reach server.");

    } finally { setLoadingStage2Donors(false); }

  }, [parseApiError]);



  const fetchPendingDonors = useCallback(async () => {

    setLoadingPendingDonors(true);

    setErrorPendingDonors("");

    try {

      const res = await authFetch(`backend/api/get_pending_donors.php?_ts=${Date.now()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load pending donor approvals."));

      const data = await res.json();

      if (data.success) setPendingDonors(Array.isArray(data.data) ? data.data : []);

      else setErrorPendingDonors(data.message || "Failed to load pending donor approvals.");

    } catch (error) {

      setErrorPendingDonors(error.message || "Could not reach server.");

    } finally { setLoadingPendingDonors(false); }

  }, [parseApiError]);



  const fetchBloodBanks = useCallback(async () => {

    setLoadingBloodBanks(true);

    setErrorBloodBanks("");

    try {

      const params = new URLSearchParams();

      if (includeInactiveBanks) params.set("include_inactive", "1");

      if (bloodBankSearch.trim()) params.set("search", bloodBankSearch.trim());

      params.set("page", String(bloodBankPage));

      params.set("per_page", String(bloodBankPerPage));

      const res = await authFetch(`backend/api/get_blood_banks_admin.php?${params.toString()}`);

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load blood banks."));

      const data = await res.json();

      if (!data.success) throw new Error(data.message || "Failed to load blood banks.");

      setBloodBanks(Array.isArray(data.data) ? data.data : []);

      setBloodBankPagination(data.pagination || { total: Array.isArray(data.data) ? data.data.length : 0, totalPages: 1, page: bloodBankPage, perPage: bloodBankPerPage });

    } catch (error) {

      setErrorBloodBanks(error.message || "Could not reach server.");

    } finally { setLoadingBloodBanks(false); }

  }, [includeInactiveBanks, bloodBankSearch, bloodBankPage, bloodBankPerPage, parseApiError]);



  const fetchLowStockAlerts = useCallback(async () => {

    setLoadingLowStock(true);

    setErrorLowStock("");

    try {

      const res = await authFetch("backend/api/get_staff_dashboard.php");

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "Failed to load low stock alerts.");

      setLowStockItems(Array.isArray(data.lowStockItems) ? data.lowStockItems : []);

    } catch (error) {

      setErrorLowStock(error.message || "Could not load low stock alerts.");

    } finally { setLoadingLowStock(false); }

  }, []);



  const fetchNotifications = useCallback(async () => {

    setLoadingNotifications(true);

    setErrorNotifications("");

    try {

      const res = await authFetch(`backend/api/get_admin_notifications.php?limit=50&_ts=${Date.now()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load notifications."));

      const data = await res.json();

      if (!data.success) throw new Error(data.message || "Failed to load notifications.");

      setNotifications(Array.isArray(data.data) ? data.data : []);

      setUnreadNotificationCount(Number(data.total_unread || 0));

    } catch (error) {

      setErrorNotifications(error.message || "Could not reach server.");

    } finally { setLoadingNotifications(false); }

  }, [parseApiError]);



  useEffect(() => {

    if (!user) return;

    fetchStats();

    fetchAppointments();

    fetchCamps();

    fetchDonors();

    fetchStage2Donors();

    fetchPendingDonors();

    fetchBloodBanks();

    fetchLowStockAlerts();

    fetchNotifications();

  }, [user]);



  useEffect(() => {

    if (!bannerToast || isToastShowing) return;

    setIsToastShowing(true);

    const timer = setTimeout(() => {

      setBannerToast(null);

      setIsToastShowing(false);

    }, 3000);

    return () => clearTimeout(timer);

  }, [bannerToast]);



  const handleLogout = () => {

    clearAuthSession();

    navigate("/login");

  };



  const handleRefresh = () => {

    fetchStats();

    fetchAppointments();

    fetchCamps();

    fetchDonors();

    fetchStage2Donors();

    fetchPendingDonors();

    fetchBloodBanks();

    fetchLowStockAlerts();

    fetchNotifications();

  };



  const handleDonorApproval = async (donorId, nextStatus) => {

    try {

      setActionError("");

      const normalizedStatus = String(nextStatus || "").toLowerCase();

      let reason = "";

      if (normalizedStatus === "rejected") {

        const promptedReason = window.prompt("Enter a rejection reason for the donor:", "");

        if (promptedReason === null) return;

        reason = promptedReason.trim();

      }

      const res = await authFetch("backend/api/update_donor_registration_status.php", {

        method: "POST",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify({ donorId, status: nextStatus, reason }),

      });

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "Failed to update donor registration.");

      setPendingDonors((prev) => {

        if (!Array.isArray(prev)) return prev;

        if (normalizedStatus === "confirmed" || normalizedStatus === "active" || normalizedStatus === "approved") {

          return prev.filter((row) => Number(row.id) !== Number(donorId));

        }

        return prev.map((row) => (Number(row.id) === Number(donorId) ? { ...row, status: normalizedStatus } : row));

      });

      fetchDonors();

      fetchPendingDonors();

      fetchStats();

      setBannerToast({ type: "success", message: normalizedStatus === "confirmed" || normalizedStatus === "active" || normalizedStatus === "approved" ? "Donor approved to donate." : "Donor rejected from the initial approval stage." });

    } catch (error) {

      setActionError(error.message || "Could not update donor registration.");

    }

  };



  const handleApproveSample = async (sampleId, donorName = "this donor") => {

    if (!sampleId) return;

    if (!window.confirm(`Approve sample and send message to ${donorName}?`)) return;

    setActionError("");

    setApprovingSampleIds((prev) => new Set(prev).add(sampleId));

    try {

      const res = await authFetch("backend/api/approve_sample_test.php", {

        method: "POST",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify({ sample_id: sampleId }),

      });

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "Approval failed.");

      setPendingDonors((prev) => {

        if (!Array.isArray(prev)) return prev;

        return prev.filter((row) => Number(row.sample_id) !== Number(sampleId));

      });

      setBannerToast({ type: "success", message: `Sample approved and message sent to ${donorName}.` });

      fetchDonors();

      fetchStage2Donors();

      fetchNotifications();

    } catch (error) {

      const message = error.message || "Could not approve the sample at this time.";

      setActionError(message);

      setBannerToast({ type: "error", message });

    } finally {

      setApprovingSampleIds((prev) => {

        const next = new Set(prev);

        next.delete(sampleId);

        return next;

      });

    }

  };



  const handleFinalizeDecision = async (sampleId, decision, donorName = "this donor") => {

    if (!sampleId) return;

    const normalizedDecision = String(decision || "").toLowerCase();

    let deferUntil = "";

    let decisionNotes = "";

    if (normalizedDecision === "temp_defer") {

      const sixMonthsFromNow = new Date();

      sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);

      const suggestedDate = sixMonthsFromNow.toISOString().split("T")[0];

      const chosenDate = window.prompt(`Enter deferral date for ${donorName} (YYYY-MM-DD):`, suggestedDate);

      if (chosenDate === null) return;

      deferUntil = (chosenDate || suggestedDate).trim();

      const promptedReason = window.prompt("Enter a temporary deferral reason (optional):", "");

      decisionNotes = promptedReason === null ? "" : promptedReason.trim();

    } else {

      const promptedNotes = window.prompt("Enter decision notes (optional):", "");

      decisionNotes = promptedNotes === null ? "" : promptedNotes.trim();

    }

    const confirmLabel = normalizedDecision === "accept" ? "Accept as donor" : normalizedDecision === "temp_defer" ? "Temporarily defer" : normalizedDecision === "perm_defer" ? "Permanently defer" : "Request retest";

    if (!window.confirm(`${confirmLabel} for ${donorName}?`)) return;

    try {

      setActionError("");

      const res = await authFetch("backend/api/finalize_test_decision.php", {

        method: "POST",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify({ sample_id: sampleId, decision: normalizedDecision, defer_until: deferUntil || undefined, decision_notes: decisionNotes, notify_donor: true }),

      });

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "We couldn't save that decision.");

      setBannerToast({ type: "success", message: `${donorName} has been updated.` });

      fetchDonors();

      fetchStage2Donors();

      fetchNotifications();

    } catch (error) {

      const message = error.message || "We couldn't save that decision right now.";

      setActionError(message);

      setBannerToast({ type: "error", message });

    }

  };



  // Admin Decision Handlers

  const openDeferTemporaryModal = (donorId, donorName, months = 6) => {

    const defaultUntil = new Date();
    defaultUntil.setMonth(defaultUntil.getMonth() + Number(months));

    setDeferTemporaryModal({

      open: true,

      donorId,

      donorName,

      months: Number(months),

      message: `Dear ${donorName}, thank you for your willingness to donate blood. Based on your screening results, you are temporarily deferred for ${months} months. Please return on or after ${defaultUntil.toLocaleDateString()} for re-evaluation.`,

    });

  };



  const closeDeferTemporaryModal = () => {

    setDeferTemporaryModal({ open: false, donorId: null, donorName: "", months: 6 });

  };



  const openPermanentDeferralModal = (donorId, donorName) => {

    setPermanentDeferralModal({

      open: true,

      donorId,

      donorName,

      message: `Dear ${donorName}, thank you for your willingness to donate blood. Based on your screening results, you are permanently deferred from donating blood for your health and the safety of recipients. Please consult a healthcare provider for confidential follow-up.`,

    });

  };



  const closePermanentDeferralModal = () => {

    setPermanentDeferralModal({ open: false, donorId: null, donorName: "" });

  };



  const openApproveModal = (donorId, donorName) => {

    setApproveModal({

      open: true,

      donorId,

      donorName,

      message: `Dear ${donorName}, your blood test results are negative. Thank you for your willingness to donate blood. You are now an Approved Donor and can book your next appointment after the medical review is completed.`,

    });

    setDecisionMessage("");

  };



  const closeApproveModal = () => {

    setApproveModal({ open: false, donorId: null, donorName: "", message: "" });

  };



  const handleApproveSubmit = async () => {

    const { donorId, donorName, message } = approveModal;

    if (!donorId) return;

    setProcessingDecision(true);

    setDecisionMessage("");

    try {

      // First get the samples for this donor to find the correct one

      const samplesRes = await authFetch(`backend/api/get_donor_samples.php`);

      const samplesData = await samplesRes.json();

      if (!samplesRes.ok || !samplesData.success || !samplesData.data) {

        throw new Error('Could not fetch donor samples');

      }

      // Find the most recent sample for this donor that hasn't been finalized
      const donorSample = samplesData.data.find(sample => 

        sample.donor_id === donorId && 

        !sample.admin_finalized

      );

      if (!donorSample || !donorSample.id) {

        throw new Error('Could not find donor sample record');

      }

      const nextEligibleDate = new Date();
      nextEligibleDate.setMonth(nextEligibleDate.getMonth() + 3);

      const res = await authFetch("backend/api/finalize_test_decision.php", {

        method: "POST",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify({ 

          sample_id: donorSample.id,

          decision: "approve",

          workflow_status: "approved_donor",

          message: message,

          next_eligible_date: nextEligibleDate.toISOString().split('T')[0]

        }),

      });

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "Failed to save decision.");

      setBannerToast({ type: "success", message: "Donor accepted and notified" });

      closeApproveModal();

      setTimeout(() => {

        fetchDonors();

        setDecisionMessage("");

      }, 2000);

    } catch (error) {

      setActionError(error.message || "Could not save decision.");

    } finally {

      setProcessingDecision(false);

    }

  };



  const handleDeferTemporarySubmit = async () => {

    const { donorId, donorName, months } = deferTemporaryModal;

    if (!donorId || !months) return;

    setProcessingDecision(true);

    setDecisionMessage("");

    try {

      const deferUntilDate = new Date();

      deferUntilDate.setMonth(deferUntilDate.getMonth() + Number(months));

      

      // First get the samples for this donor to find the correct one

      const samplesRes = await authFetch(`backend/api/get_donor_samples.php`);

      const samplesData = await samplesRes.json();

      

      if (!samplesRes.ok || !samplesData.success || !samplesData.data) {

        throw new Error('Could not fetch donor samples');

      }

      

      // Find the most recent sample for this donor that hasn't been finalized

      const donorSample = samplesData.data.find(sample => 

        sample.donor_id === donorId && 

        !sample.admin_finalized

      );

      

      if (!donorSample || !donorSample.id) {

        throw new Error('Could not find eligible donor sample record');

      }

      

      const res = await authFetch("backend/api/finalize_test_decision.php", {

        method: "POST",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify({ 

          sample_id: donorSample.id,

          decision: "defer_temporary",

          workflow_status: "temporarily_deferred",

          defer_until: deferUntilDate.toISOString().split('T')[0],

          defer_months: months,

          message: deferTemporaryModal.message,

          next_eligible_date: deferUntilDate.toISOString().split('T')[0]

        }),

      });

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "Failed to save decision.");

      setBannerToast({ type: "success", message: "Deferral saved & donor notified" });

      closeDeferTemporaryModal();

      setTimeout(() => {

        fetchDonors();

        setDecisionMessage("");

      }, 2000);

    } catch (error) {

      setActionError(error.message || "Could not save decision.");

    } finally {

      setProcessingDecision(false);

    }

  };



  const handleDeferPermanentDecision = async (donorId, donorName) => {

    openPermanentDeferralModal(donorId, donorName);

  };



  const handlePermanentDeferralSubmit = async () => {

    const { donorId, donorName } = permanentDeferralModal;

    if (!donorId) return;

    setProcessingDecision(true);

    setDecisionMessage("");

    try {

      // First get the samples for this donor to find the correct one

      const samplesRes = await authFetch(`backend/api/get_donor_samples.php`);

      const samplesData = await samplesRes.json();

      

      if (!samplesRes.ok || !samplesData.success || !samplesData.data) {

        throw new Error('Could not fetch donor samples');

      }

      

      // Find the most recent sample for this donor that hasn't been finalized

      const donorSample = samplesData.data.find(sample => 

        sample.donor_id === donorId && 

        !sample.admin_finalized

      );

      

      if (!donorSample || !donorSample.id) {

        throw new Error('Could not find eligible donor sample record');

      }

      

      const res = await authFetch("backend/api/finalize_test_decision.php", {

        method: "POST",

        headers: { "Content-Type": "application/json" },

        body: JSON.stringify({ 

          sample_id: donorSample.id,

          decision: "defer_permanent",

          workflow_status: "permanently_deferred",

          message: permanentDeferralModal.message,

          confidential: true

        }),

      });

      const data = await res.json();

      if (!res.ok || !data.success) throw new Error(data.message || "Failed to save decision.");

      setBannerToast({ type: "success", message: "Permanent deferral saved & donor notified confidentially" });

      closePermanentDeferralModal();

      setTimeout(() => {

        fetchDonors();

        setDecisionMessage("");

      }, 2000);

    } catch (error) {

      setActionError(error.message || "Could not save decision.");

    } finally {

      setProcessingDecision(false);

    }

  };



  // Handler for Malaria-specific deferral with flexible duration selection
  const handleDeferMalariaDecision = async (donorId, donorName, months) => {
    if (!donorId || !months) return;
    
    setProcessingDecision(true);
    setActionError('');

    try {
      const deferUntilDate = new Date();
      deferUntilDate.setMonth(deferUntilDate.getMonth() + Number(months));

      // First get the samples for this donor
      const samplesRes = await authFetch(`backend/api/get_donor_samples.php`);
      const samplesData = await samplesRes.json();

      if (samplesData.success && samplesData.data) {
        const donorSamples = samplesData.data.filter(sample => sample.donor_id === donorId);
        
        if (donorSamples.length > 0) {
          // Update all samples for this donor
          for (const sample of donorSamples) {
            await authFetch(`backend/api/update_sample_status.php`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                sample_id: sample.id,
                status: 'temporarily_deferred',
                defer_until_date: deferUntilDate.toISOString().split('T')[0],
                deferral_reason: 'Malaria - temporary deferral'
              })
            });
          }
        }
      }

      // Update donor status
      await authFetch(`backend/api/update_donor_status.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          donor_id: donorId,
          status: 'temporarily_deferred',
          defer_until_date: deferUntilDate.toISOString().split('T')[0],
          deferral_reason: 'Malaria - temporary deferral'
        })
      });

      toast.success(`${donorName} has been temporarily deferred for ${months} months due to malaria risk`);
      fetchDonors(); // Refresh donor list
      
    } catch (error) {
      setActionError('Failed to process malaria deferral');
      toast.error('Failed to process malaria deferral');
    } finally {
      setProcessingDecision(false);
    }
  };


  // Appointment action handlers
  const handleAcceptAppointment = async (appointment) => {
    console.log('Accept appointment clicked:', appointment);
    try {
      const res = await authFetch("backend/api/accept_appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: appointment.id })
      });
      console.log('API response status:', res.status);
      const data = await res.json();
      console.log('API response data:', data);
      if (data.success) {
        toast.success(`Appointment for ${appointment.full_name} confirmed successfully`);
        fetchAppointments(); // Refresh the appointments list
      } else {
        toast.error(data.message || "Failed to accept appointment");
      }
    } catch (error) {
      console.error('Error accepting appointment:', error);
      toast.error("Error accepting appointment");
    }
  };

  const handleRejectAppointment = async (appointment) => {
    console.log('Reject appointment clicked:', appointment);
    try {
      const res = await authFetch("backend/api/reject_appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: appointment.id })
      });
      console.log('Reject API response status:', res.status);
      const data = await res.json();
      console.log('Reject API response data:', data);
      if (data.success) {
        toast.success(`Appointment for ${appointment.full_name} rejected successfully`);
        fetchAppointments(); // Refresh the appointments list
      } else {
        toast.error(data.message || "Failed to reject appointment");
      }
    } catch (error) {
      console.error('Error rejecting appointment:', error);
      toast.error("Error rejecting appointment");
    }
  };

  const handleViewAppointment = (appointment) => {
    console.log('View appointment clicked:', appointment);
    setDetailsModal({
      isOpen: true,
      type: 'appointment',
      data: appointment
    });
  };

  // Camp request action handlers
  const handleAcceptCamp = async (camp) => {
    console.log('Accept camp clicked:', camp);
    try {
      const res = await authFetch("backend/api/accept_camp_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: camp.id })
      });
      console.log('Accept camp API response status:', res.status);
      const data = await res.json();
      console.log('Accept camp API response data:', data);
      if (data.success) {
        toast.success(`Camp request from ${camp.organization_name} accepted successfully`);
        fetchCamps(); // Refresh the camps list
      } else {
        toast.error(data.message || "Failed to accept camp request");
      }
    } catch (error) {
      console.error('Error accepting camp request:', error);
      toast.error("Error accepting camp request");
    }
  };

  const handleRejectCamp = async (camp) => {
    console.log('Reject camp clicked:', camp);
    try {
      const res = await authFetch("backend/api/reject_camp_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: camp.id })
      });
      console.log('Reject camp API response status:', res.status);
      const data = await res.json();
      console.log('Reject camp API response data:', data);
      if (data.success) {
        toast.success(`Camp request from ${camp.organization_name} rejected successfully`);
        fetchCamps(); // Refresh the camps list
      } else {
        toast.error(data.message || "Failed to reject camp request");
      }
    } catch (error) {
      console.error('Error rejecting camp request:', error);
      toast.error("Error rejecting camp request");
    }
  };

  const handleViewCamp = (camp) => {
    console.log('View camp clicked:', camp);
    setDetailsModal({
      isOpen: true,
      type: 'camp',
      data: camp
    });
  };

  // Helper function to get upcoming appointments
  const upcomingAppointments = (() => {
    const source = Array.isArray(appointments) ? appointments : [];

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return source.filter((row) => {
      if (!row.preferred_date) return false;
      const d = new Date(row.preferred_date);
      return !Number.isNaN(d.getTime()) && d >= today;
    }).sort((a, b) => new Date(a.preferred_date) - new Date(b.preferred_date)).slice(0, 5);
  })();



  if (!user) return null;



  const pendingReviewCount = Array.isArray(pendingDonors) ? pendingDonors.filter((row) => String(row.status || "Pending").toLowerCase() === "pending").length : 0;

  const stage2Queue = Array.isArray(stage2Donors) ? stage2Donors : [];
  // Also expose a full Stage-2 donor list derived from the main donors array
  // so the UI can show all donors that are in the "approved_for_blood_draw" state.
  const stage2AllDonors = Array.isArray(donors) ? donors.filter(d => String(d.workflow_status || '').toLowerCase() === 'approved_for_blood_draw') : [];

  const referenceDonors = Array.isArray(donors) ? donors.filter((row) => {

    const fullName = String(row.full_name || row.donor_name || "").toLowerCase();

    const bloodType = String(row.blood_type || "").toLowerCase();

    const search = referenceSearchQuery.trim().toLowerCase();

    const searchMatch = !search || [fullName, String(row.email || "").toLowerCase(), String(row.phone || "").toLowerCase()].some((value) => value.includes(search));

    const bloodTypeMatch = !referenceBloodType || bloodType === referenceBloodType.toLowerCase();

    return searchMatch && bloodTypeMatch;

  }) : [];



  const normalizeEmail = (value) => String(value || "").replace(/\s+/g, "").trim();



  const getPositiveDiseaseLabel = (row) => {

    if (!row) return '';
    const diseases = new Set();

    // canonical mapping for disease tokens we allow in the label
    const canonical = {
      hiv: 'HIV',
      'hepatitis b': 'Hepatitis B',
      hbsag: 'Hepatitis B',
      'hepatitis c': 'Hepatitis C',
      hcv: 'Hepatitis C',
      diabetes: 'Diabetes',
      tuberculosis: 'Tuberculosis',
      tubercolosis: 'Tuberculosis',
      syphilis: 'Syphilis',
      malaria: 'Malaria'
    };

    const isNoise = (s) => {
      if (!s) return true;
      const n = String(s).trim().toLowerCase();
      if (!n) return true;
      if (['negative','non-reactive','not_tested','not tested','not-tested','na','n/a','—','-'].includes(n)) return true;
      return false;
    };

    const normalizePart = (part) => {
      if (!part) return null;
      const p = String(part).trim().toLowerCase();
      if (isNoise(p)) return null;
      // try canonical match
      for (const key of Object.keys(canonical)) {
        if (p.includes(key)) return canonical[key];
      }
      // fallback: capitalize words if it looks like a disease name
      const cleaned = p.replace(/[^a-z0-9\s]/g, '').trim();
      if (cleaned.split(/\s+/).length <= 4) {
        return cleaned.split(/\s+/).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
      }
      return null;
    };

    const addText = (value) => {
      const text = String(value || '').trim();
      if (!text) return;

      // If text contains parentheses, look inside and split parts
      const bracketMatch = text.match(/\(([^)]+)\)/);
      if (bracketMatch && bracketMatch[1]) {
        bracketMatch[1].split(',').forEach((part) => {
          const norm = normalizePart(part);
          if (norm) diseases.add(norm);
        });
        return;
      }

      // If text looks like a plain 'Positive' or 'Reactive' without disease, ignore
      if (/^\s*(positive|reactive)\s*$/i.test(text)) return;

      // Split comma-separated values and normalize each
      text.split(',').forEach((part) => {
        const norm = normalizePart(part);
        if (norm) diseases.add(norm);
      });
    };

    if (Array.isArray(row.reactive_tests)) {
      row.reactive_tests.forEach((item) => addText(item));
    }

    addText(row.reactive_tests_label);
    addText(row.positive_diseases);
    addText(row.latest_test_result_display);
    addText(row.latest_test_result);

    // Also consider explicit sample fields which should be exact values like 'reactive'
    const diseaseFields = {
      hiv_result: 'HIV',
      hbsag_result: 'Hepatitis B',
      hcv_result: 'Hepatitis C',
      syphilis_result: 'Syphilis',
      malaria_result: 'Malaria',
    };

    Object.entries(diseaseFields).forEach(([field, label]) => {
      const value = String(row[field] || '').trim().toLowerCase();
      if (value === 'reactive' || value === 'positive') {
        diseases.add(label);
      }
    });

    return Array.from(diseases).join(', ');

  };



  const getWorkflowStatusLabel = (status, row = null) => {

    const labels = {

      'awaiting_review': 'Awaiting Review',

      'pending_approval': 'Awaiting Review',

      'approved_to_donate': 'Ready for Blood Draw',

      'approved_for_blood_draw': 'Ready for Blood Draw',

      'blood_donated': 'Awaiting Test Results',

      'blood_drawn_pending_test': 'Awaiting Test Results',

      'tested_negative': 'Tested - Negative',

      'test_result_pending_decision': 'Awaiting Admin Review',

      'approved_donor': 'Approved Donor',

      'decision_made_accepted': 'Approved Donor',

      'active_donor': 'Approved Donor',

      'decision_made_deferred': 'Temporarily Deferred',

      'temporarily_deferred': 'Temporarily Deferred',

      'deferred_until_date': 'Temporarily Deferred',

      'decision_made_rejected': 'Permanently Deferred',

      'permanently_deferred': 'Permanently Deferred',

      'perm_defer': 'Permanently Deferred',

    };

    const positiveDiseaseLabel = getPositiveDiseaseLabel(row);
    const sampleLabel = getSampleTestResultLabel(row);
    const trimmed = String(status || '').trim().toLowerCase();

    // NOTE: do not auto-derive deferral status from sample labels here —
    // final deferral decisions should only come from explicit admin workflow_status fields.
    return labels[trimmed] || 'Awaiting Review';

  };



  const getWorkflowStatusClass = (status, row = null) => {

    const positiveDiseaseLabel = getPositiveDiseaseLabel(row);
    const sampleLabel = getSampleTestResultLabel(row);
    const trimmed = String(status || '').trim().toLowerCase();

    // NOTE: keep class mapping strictly based on workflow_status; do not infer deferral from samples.

    if (['decision_made_accepted', 'approved_donor', 'active_donor'].includes(trimmed)) return 'admin-status-confirmed';

    if (['approved_to_donate', 'approved_for_blood_draw', 'tested_negative', 'blood_donated', 'blood_drawn_pending_test', 'test_result_pending_decision', 'decision_made_deferred', 'temporarily_deferred', 'deferred_until_date'].includes(trimmed)) return 'admin-status-pending';

    if (['decision_made_rejected', 'permanently_deferred', 'perm_defer'].includes(trimmed)) return 'admin-status-rejected';

    return 'admin-status-pending';

  };



  const getTestResultColor = (result) => {

    const normalized = String(result || '').trim().toLowerCase();

    if (normalized.startsWith('positive') || normalized.startsWith('reactive') || normalized.includes('positive') || normalized.includes('reactive')) return 'admin-status-rejected';

    if (normalized === 'negative' || normalized === 'non-reactive') return 'admin-status-confirmed';

    return 'admin-status-pending';

  };



  const getSampleTestResultLabel = (row) => {

    if (!row) return '—';

    // keep sample test result label stable — do not suppress it here

    const rawResult = String(row.latest_test_result || '').trim().toLowerCase();
    const rawDisplay = String(row.latest_test_result_display || '').trim().toLowerCase();

    if (!rawResult || rawResult === 'not_tested' || rawResult === 'pending' || rawDisplay === '—' || rawDisplay === '' || rawDisplay === 'not tested') {
      return '—';
    }

    const positiveLabel = getPositiveDiseaseLabel(row);

    // If status is Reactive or Positive, show it
    if (rawResult === 'reactive' || rawResult === 'positive') {
      if (positiveLabel && String(positiveLabel).trim() !== '') {
        return `Positive (${positiveLabel})`;
      }
      return 'Positive';
    }

    if (positiveLabel && String(positiveLabel).trim() !== '') {
      return `Positive (${positiveLabel})`;
    }

    return 'Negative';

  };



  const getDeferralType = (row) => {

    if (!row) return 'temporary';

    

    // Check for negative test results in multiple possible fields

    const testResult = String(row.latest_test_result || '').trim();

    const testResultDisplay = String(row.latest_test_result_display || '').trim();

    

    // Primary check: if test result is explicitly negative, return accept

    if (testResult === 'Negative' || testResult === 'Non-reactive' || 

        testResultDisplay === 'Negative' || testResultDisplay === 'Non-reactive') {

      return 'accept';

    }

    

    // If test result is positive or reactive, check for deferral type

    if (testResult === 'Positive' || testResult === 'Reactive' || 

        testResultDisplay === 'Positive' || testResultDisplay === 'Reactive') {

      const positiveDiseases = getPositiveDiseaseLabel(row).toLowerCase();
      const rawPositiveText = [
        positiveDiseases,
        row.positive_diseases,
        row.reactive_tests_label,
        row.latest_test_result_display,
        row.latest_test_result,
        row.hiv_result,
        row.hbsag_result,
        row.hcv_result,
      ]
        .map((value) => String(value || '').toLowerCase())
        .join(' ');

      // If no positive diseases are listed, default to temporary deferral
      if (!positiveDiseases || positiveDiseases.trim() === '') {
        return 'temporary';
      }

      // HIV, HBsAg, and HCV are permanent deferrals
      const permanentDiseases = ['hiv', 'hcv', 'hbsag', 'hepatitis b', 'hepatitis c'];
      const hasPermanentDisease = permanentDiseases.some((disease) => rawPositiveText.includes(disease.toLowerCase()));

      if (hasPermanentDisease) {
        return 'permanent';
      }

      // Syphilis and malaria remain temporary deferrals
      const temporaryDiseases = ['syphilis', 'malaria'];
      const hasTemporaryDisease = temporaryDiseases.some((disease) => positiveDiseases.includes(disease.toLowerCase()));

      if (hasTemporaryDisease) {
        return 'temporary';
      }

      // Default to temporary for other positive results
      return 'temporary';

    }

    

    // Default case: if we can't determine, treat as temporary for safety

    return 'temporary';

  };



  const formatHealthDeclaration = (value) => {

    if (!value) return "Not provided";

    let parsed = value;

    if (typeof value === "string") {

      try { parsed = JSON.parse(value); } catch { return String(value).trim() || "Not provided"; }

    }

    if (!parsed || typeof parsed !== "object") return String(value).trim() || "Not provided";

    const parts = [];

    Object.entries(parsed).forEach(([key, flag]) => {

      const label = key.replace(/_/g, " ").replace(/\b\w/g, (match) => match.toUpperCase());

      parts.push(`${label}: ${flag ? "Yes" : "No"}`);

    });

    return parts.length > 0 ? parts.join(" · ") : "Not provided";

  };



  const statCards = [

    { icon: "🩸", label: "Total Donors", value: loadingStats ? null : (stats ? String(stats.donors) : "—"), trend: "Registered donors" },

    { icon: "📅", label: "Upcoming (Next 5)", value: loadingAppointments ? null : String(upcomingAppointments.length), trend: "Scheduled soon" },

    { icon: "⚠️", label: "Low Stock Alerts", value: loadingLowStock ? null : String(lowStockItems.length), trend: "Needs replenishment" },

    { icon: "🏕️", label: "Camp Requests", value: loadingStats ? null : (stats ? String(stats.camps) : "—"), trend: "All time" },

  ];



  const links = [

    { to: "/blood-banks", icon: "🏥", label: "Blood Banks", desc: "Search and manage locations" },

    { to: "/donating-blood", icon: "📋", label: "Book Appointment", desc: "Create and manage donor appointments" },

    { to: "/camp", icon: "🏕️", label: "Camp Form", desc: "Register donation camp" },

    { to: "/register", icon: "👤", label: "Donor Register", desc: "Add new donor profile" },

    { to: "/", icon: "🏠", label: "Back to Home", desc: "Return to public site" },

  ];



  return (
    <AdminShell user={user} onLogout={handleLogout}>
      <div className="admin-dashboard">
      <div className="admin-page">
        {bannerToast ? <div className={`admin-toast ${bannerToast.type}`}>{bannerToast.message}</div> : null}



        {/* HERO */}

        <section className="admin-hero">

          <div className="admin-hero-circle" />

          <div className="admin-hero-circle2" />

          <div className="admin-hero-content">

            <span className="admin-hero-label">Admin Control Panel</span>

            <h1>Admin Dashboard</h1>

            <p>Welcome back, {user.name}. Manage blood bank operations and monitor donation activity across Bhutan.</p>

          </div>

          <div className="admin-hero-badge">🩸</div>

        </section>



        {/* STAT CARDS */}

        <section className="admin-cards">

          {statCards.map((stat, i) => (

            <article className="admin-card" key={i}>

              <span className="admin-card-icon">{stat.icon}</span>

              <h3>{stat.label}</h3>

              <p className="admin-value">{stat.value === null ? <span className="admin-skeleton" /> : stat.value}</p>

              <p className="admin-card-trend">{stat.trend}</p>

            </article>

          ))}

        </section>



        {/* TABS */}

        <section className="admin-data-section">

          {actionError && <div className="admin-table-msg error" style={{ margin: "0 0 12px" }}>{actionError}</div>}

          <div className="admin-tabs-bar">

            <button className={`admin-tab${activeTab === "dashboard" ? " active" : ""}`} onClick={() => setActiveTab("dashboard")}>📊 Dashboard</button>

            <button className={`admin-tab${activeTab === "donors" ? " active" : ""}`} onClick={() => setActiveTab("donors")}>

              🩸 Donors

              {donors && <span className="admin-tab-count">{donors.length}</span>}

              {pendingDonors && pendingDonors.length > 0 && <span className="admin-tab-count" style={{ marginLeft: 6 }}>{pendingReviewCount} pending</span>}

            </button>

            <button className={`admin-tab${activeTab === "appointments" ? " active" : ""}`} onClick={() => setActiveTab("appointments")}>📅 Appointments{appointments && <span className="admin-tab-count">{appointments.length}</span>}</button>

            <button className={`admin-tab${activeTab === "camps" ? " active" : ""}`} onClick={() => setActiveTab("camps")}>🏕️ Camp Requests{camps && <span className="admin-tab-count">{camps.length}</span>}</button>

            <button className={`admin-tab${activeTab === "bloodBanks" ? " active" : ""}`} onClick={() => setActiveTab("bloodBanks")}>🏥 Blood Banks{bloodBanks && <span className="admin-tab-count">{bloodBanks.length}</span>}</button>

            <button className={`admin-tab${activeTab === "notifications" ? " active" : ""}`} onClick={() => setActiveTab("notifications")}>🔔 Notifications{unreadNotificationCount > 0 && <span className="admin-tab-count">{unreadNotificationCount} new</span>}</button>

            <button className="admin-refresh-btn" onClick={handleRefresh}>↺ Refresh</button>

          </div>



          {/* DASHBOARD TAB - NO DONOR TABLE */}

          {activeTab === "dashboard" && (

            <div className="admin-dashboard-grid">

              <article className="admin-panel-card">

                <div className="admin-panel-head"><h3>Next 5 Upcoming Appointments</h3></div>

                {loadingAppointments ? <div className="admin-table-msg">Loading upcoming appointments...</div> : errorAppointments ? <div className="admin-table-msg error">{errorAppointments}</div> : upcomingAppointments.length === 0 ? <div className="admin-table-msg">No upcoming appointments.</div> : (

                  <ul className="admin-mini-list">

                    {upcomingAppointments.map((row) => (

                      <li key={row.id}><div><strong>{row.full_name}</strong><span>{row.blood_group || "—"} · {row.blood_bank || "—"}</span></div><time>{row.preferred_date}</time></li>

                    ))}

                  </ul>

                )}

              </article>



              <article className="admin-panel-card">

                <div className="admin-panel-head"><h3>Low Stock Alerts</h3></div>

                {loadingLowStock ? <div className="admin-table-msg">Loading low stock alerts...</div> : errorLowStock ? <div className="admin-table-msg error">{errorLowStock}</div> : lowStockItems.length === 0 ? <div className="admin-table-msg">No low stock alerts.</div> : (

                  <ul className="admin-mini-list">

                    {lowStockItems.slice(0, 5).map((row, idx) => (

                      <li key={`${row.blood_type}-${idx}`}><div><strong>{row.blood_type}</strong><span>{row.blood_bank_name || "All Banks"}</span></div><span className="admin-mini-pill">{row.total_units} units</span></li>

                    ))}

                  </ul>

                )}

              </article>



              <article className="admin-panel-card admin-panel-wide">

                <div className="admin-panel-head"><h3>Quick Actions</h3></div>

                <div className="admin-quick-grid">

                  {links.map((link, i) => (

                    <Link to={link.to} key={i} className="admin-quick-card">

                      <span className="admin-quick-icon">{link.icon}</span>

                      <div><h4>{link.label}</h4><p>{link.desc}</p></div>

                      <span className="admin-link-arrow">→</span>

                    </Link>

                  ))}

                </div>

              </article>

            </div>

          )}



          {/* DONORS TAB - ALL DONOR MANAGEMENT HERE */}

          {activeTab === "donors" && (

            <div className="admin-donor-page-stack">

              {/* Pending Initial Approval (Stage 1) */}

              <div className="admin-table-wrap">

                <div className="admin-panel-head" style={{ marginBottom: 12 }}><h3>Pending Initial Approval (Stage 1)</h3></div>

                {loadingPendingDonors ? <div className="admin-table-msg">Loading pending approvals…</div> : errorPendingDonors ? <div className="admin-table-msg error">{errorPendingDonors}</div> : !pendingDonors || pendingDonors.length === 0 ? <div className="admin-table-msg">No pending donors awaiting review.</div> : (

                  <table className="admin-table">

                    <thead><tr><th style={{ width: 30 }}></th><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Blood Type</th><th>Test Result</th><th>Health Declaration</th><th>Consent</th><th>Status</th><th>Actions</th></tr></thead>

                    <tbody>

                      {pendingDonors.map((row) => {

                        const donorStatus = String(row.status || "Pending").toLowerCase();

                        const isRejected = donorStatus === "rejected";

                        const isConfirmed = donorStatus === "confirmed" || donorStatus === "active";

                        const isExpanded = expandedDonorRows.has(row.id);

                        const age = row.age ?? (row.date_of_birth ? Math.max(0, new Date().getFullYear() - new Date(row.date_of_birth).getFullYear()) : null);

                        return (

                          <React.Fragment key={row.id}>

                            <tr>

                              <td style={{ width: 30, padding: "8px 4px", textAlign: "center" }}>

                                <button className={`admin-expand-btn ${isExpanded ? "open" : ""}`} onClick={() => toggleExpandDonor(row.id)}>{isExpanded ? "−" : "+"}</button>

                              </td>

                              <td className="admin-td-id">{row.id}</td>

                              <td className="admin-td-name">{row.full_name}</td>

                              <td className="admin-td-long">{normalizeEmail(row.email)}</td>

                              <td>{normalizeEmail(row.phone)}</td>

                              <td className="admin-td-blood">{row.blood_type ? <span className="admin-badge-blood">{row.blood_type}</span> : "—"}</td>

                              <td>{getSampleTestResultLabel(row)}</td>

                              <td className="admin-td-health">{formatHealthDeclaration(row.health_declaration_display ?? row.health_declaration_summary ?? row.health_declaration)}</td>

                              <td className="admin-td-consent">{Number(row.consent_medical ?? row.consent ?? 0) === 1 ? "Yes" : "No"}</td>

                              <td className="admin-td-status">{isRejected ? "Rejected" : isConfirmed ? "Confirmed" : "Pending"}</td>

                              <td className="admin-td-actions">

                                <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>

                                  <button className="admin-action-btn edit" onClick={() => openDonorDetails(row.id)}>View Details</button>

                                  <button className="admin-action-btn" onClick={() => openEditDonorModal(row.id)} title="Edit donor information">✏️ Edit</button>

                                  {row.workflow_status === "Pending Admin Approval" && row.sample_id ? (

                                    <button className="admin-action-btn confirm" disabled={approvingSampleIds.has(row.sample_id)} onClick={() => handleApproveSample(row.sample_id, row.full_name || row.name || "this donor")}>{approvingSampleIds.has(row.sample_id) ? "Approving…" : "Approve & Send Message"}</button>

                                  ) : null}

                                  <button className="admin-action-btn confirm" onClick={() => handleDonorApproval(row.id, "confirmed")}>Approve to Donate</button>

                                  <button className="admin-action-btn reject" onClick={() => handleDonorApproval(row.id, "rejected")} disabled={isRejected}>Reject (with reason)</button>

                                </div>

                              </td>

                            </tr>

                            {isExpanded && (

                              <tr className="admin-detail-row"><td colSpan="10"><div className="admin-detail-content">

                                <div className="admin-detail-field"><span className="admin-detail-label">Date of Birth</span><span className="admin-detail-value">{row.date_of_birth || "—"}</span></div>

                                <div className="admin-detail-field"><span className="admin-detail-label">Age</span><span className="admin-detail-value">{age ? `${age} years` : "—"}</span></div>

                                <div className="admin-detail-field"><span className="admin-detail-label">Weight</span><span className="admin-detail-value">{row.weight ? `${row.weight} kg` : "—"}</span></div>

                                <div className="admin-detail-field"><span className="admin-detail-label">Last Donation</span><span className="admin-detail-value">{row.last_donation_date || "Never"}</span></div>

                                <div className="admin-detail-field"><span className="admin-detail-label">Emergency Contact</span><span className="admin-detail-value">{row.emergency_contact_name ? `${row.emergency_contact_name} / ${row.emergency_contact_phone}` : "—"}</span></div>

                                <div className="admin-detail-field"><span className="admin-detail-label">Submitted</span><span className="admin-detail-value">{new Date(row.created_at).toLocaleDateString()}</span></div>

                              </div></td></tr>

                            )}

                          </React.Fragment>

                        );

                      })}

                    </tbody>

                  </table>

                )}

              </div>



              {/* Donors Ready for Blood Draw (Stage 2) */}

              <div className="admin-table-wrap">

                <div className="admin-panel-head" style={{ marginBottom: 12 }}><h3>Donors Ready for Blood Draw (Stage 2)</h3></div>

                {loadingStage2Donors ? <div className="admin-table-msg">Loading Stage 2 donors…</div> : errorStage2Donors ? <div className="admin-table-msg error">{errorStage2Donors}</div> : stage2Queue.length === 0 ? <div className="admin-table-msg">No donors are waiting for Stage 2 review right now.</div> : (

                  <table className="admin-table">

                    <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Blood Type</th><th>Test Status</th><th>Actions</th></tr></thead>

                    <tbody>

                      {stage2AllDonors.map((row) => {
                        return (
                          <tr key={`stage2-${row.id}`}>
                            <td className="admin-td-id">{row.id}</td>
                            <td className="admin-td-name">{row.full_name || row.donor_name || "—"}</td>
                            <td><div>{row.email || "—"}</div><div>{row.phone || "—"}</div></td>
                            <td>{row.blood_type ? <span className="admin-badge-blood">{row.blood_type}</span> : "—"}</td>
                            <td>—</td>
                            <td className="admin-td-actions">
                              <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                                <button className="admin-action-btn edit" onClick={() => openDonorDetails(row.id)}>View Details</button>
                                <button className="admin-action-btn" onClick={() => openEditDonorModal(row.id)} title="Edit donor information">✏️ Edit</button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}

                    </tbody>

                  </table>

                )}

              </div>



              {/* All Registered Donors (Reference) */}

              <div className="admin-table-wrap">

                <div className="admin-panel-head" style={{ marginBottom: 12 }}><h3>All Registered Donors (Reference)</h3></div>

                <div className="admin-search-container">

                  <div className="admin-search-input-group"><input className="admin-search-input" type="text" placeholder="Search donors by name, email, or phone" value={referenceSearchQuery} onChange={(e) => setReferenceSearchQuery(e.target.value)} /></div>

                  <div className="admin-filter-group">

                    <select className="admin-filter-select" value={referenceBloodType} onChange={(e) => setReferenceBloodType(e.target.value)}><option value="">All Blood Types</option>{BLOOD_GROUP_OPTIONS.map((type) => (<option key={type} value={type}>{type}</option>))}</select>

                    <button type="button" className="admin-filter-reset-btn" onClick={() => { setReferenceSearchQuery(""); setReferenceBloodType(""); }}>Clear Filters</button>

                  </div>

                </div>

                {loadingDonors ? <div className="admin-table-msg">Loading registered donors…</div> : errorDonors ? <div className="admin-table-msg error">{errorDonors}</div> : referenceDonors.length === 0 ? <div className="admin-table-msg">No donors match the selected reference filters.</div> : (

                  <table className="admin-table">

                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Blood Type</th><th>Test Result</th><th>Workflow Status</th><th>Admin Decision</th><th>Details</th></tr></thead>

                    <tbody>

                      {referenceDonors.map((row, index) => (

                        <tr key={`reference-${row.id}-${index}`}>

                          <td className="admin-td-id">{row.id}</td>

                          <td className="admin-td-name">{row.full_name || "—"}</td>

                          <td className="admin-td-long">{normalizeEmail(row.email)}</td>

                          <td>{normalizeEmail(row.phone)}</td>

                          <td>{row.blood_type ? <span className="admin-badge-blood">{row.blood_type}</span> : "—"}</td>

                          {(() => {
                            const displayValue = getSampleTestResultLabel(row);
                            const workflowLabel = getWorkflowStatusLabel(row.workflow_status, row);
                            const testValueShown = displayValue && displayValue !== '—' && displayValue.trim().toLowerCase() !== String(workflowLabel || '').trim().toLowerCase();
                            return (
                              <>
                                <td>{displayValue && displayValue !== '—' && testValueShown ? <span className={`admin-status-badge ${getTestResultColor(row.latest_test_result_display || row.latest_test_result)}`}>{displayValue}</span> : (displayValue && displayValue !== '—' && !testValueShown ? <span className={`admin-status-badge ${getTestResultColor(row.latest_test_result_display || row.latest_test_result)}`}>{displayValue}</span> : '—')}</td>
                                <td><span className={`admin-status-badge ${getWorkflowStatusClass(row.workflow_status, row)}`}>{workflowLabel}</span></td>
                              </>
                            );
                          })()}

                          <td className="admin-td-decision">
                            {(() => {
                              const workflow = String(row.workflow_status || '').trim().toLowerCase();
                              // Allow decision buttons for donors awaiting admin decision
                              const canDecide = ['test_result_pending_decision'].includes(workflow);

                              if (!canDecide) {
                                return <span style={{ color: '#999' }}>—</span>;
                              }

                              const deferralType = getDeferralType(row);

                              return (
                                <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                                  {deferralType === 'accept' && (
                                    <button className="admin-action-btn confirm" onClick={() => openApproveModal(row.id, row.full_name)} title="Accept this donor">Accept</button>
                                  )}

                                  {deferralType === 'temporary' && (
                                    <>
                                      <button className="admin-action-btn warning" onClick={() => openDeferTemporaryModal(row.id, row.full_name, 3)} title="Defer for 3 months">Defer 3 months</button>
                                      <button className="admin-action-btn warning" onClick={() => openDeferTemporaryModal(row.id, row.full_name, 6)} title="Defer for 6 months">Defer 6 months</button>
                                      <button className="admin-action-btn warning" onClick={() => openDeferTemporaryModal(row.id, row.full_name, 12)} title="Defer for 12 months">Defer 12 months</button>
                                    </>
                                  )}

                                  {deferralType === 'malaria' && (
                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
                                      <select
                                        value={deferralMonths}
                                        onChange={(e) => setDeferralMonths(parseInt(e.target.value))}
                                        style={{
                                          padding: '6px 12px',
                                          borderRadius: '4px',
                                          border: '1px solid #f0ad4e',
                                          backgroundColor: '#fff',
                                          fontSize: '12px',
                                          cursor: 'pointer',
                                          fontWeight: '500'
                                        }}
                                      >
                                        <option value={6}>6 months</option>
                                        <option value={9}>9 months</option>
                                        <option value={12}>12 months</option>
                                      </select>
                                      <button
                                        className="admin-action-btn warning"
                                        onClick={() => handleDeferMalariaDecision(row.id, row.full_name, deferralMonths)}
                                        disabled={processingDecision}
                                        title={`Defer for ${deferralMonths} months`}
                                      >
                                        {processingDecision ? 'Processing...' : 'Defer'}
                                      </button>
                                    </div>
                                  )}

                                  {deferralType === 'permanent' && (
                                    <button className="admin-action-btn reject" onClick={() => handleDeferPermanentDecision(row.id, row.full_name)} title="Permanent deferral">Permanent Deferral</button>
                                  )}
                                </div>
                              );
                            })()}
                          </td>

                          <td className="admin-td-actions">
                            <div style={{ display: "flex", gap: 6 }}>
                              <button className="admin-action-btn edit" onClick={() => openDonorDetails(row.id)}>View Details</button>
                              <button className="admin-action-btn" onClick={() => openEditDonorModal(row.id)} title="Edit donor information">✏️ Edit</button>
                            </div>
                          </td>

                        </tr>

                      ))}

                    </tbody>

                  </table>

                )}

              </div>

            </div>

          )}



          {/* Other tabs remain the same - appointments, camps, bloodBanks, notifications */}

          {activeTab === "appointments" && (

            <div className="admin-table-wrap">

              {loadingAppointments ? <div className="admin-table-msg">Loading appointments…</div> : errorAppointments ? <div className="admin-table-msg error">{errorAppointments}</div> : !appointments || appointments.length === 0 ? <div className="admin-table-msg">No appointments found.</div> : (

                <table className="admin-table"><thead><tr><th>#</th><th>Full Name</th><th>Date</th><th>Time</th><th>Blood Bank</th><th>Status</th><th>Actions</th></tr></thead><tbody>

                  {appointments.map((row) => (
                    <tr key={row.id}>
                      <td>{row.id}</td>
                      <td>{row.full_name}</td>
                      <td>{row.preferred_date}</td>
                      <td>{row.preferred_time}</td>
                      <td>{row.blood_bank}</td>
                      <td>
                        <span className={`admin-badge-status ${row.status}`}>
                          {row.status}
                        </span>
                      </td>
                      <td className="admin-td-actions">
                        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                          {row.status === 'pending' && (
                            <>
                              <button 
                                className="admin-action-btn accept" 
                                onClick={() => {
                                  alert('Accept button clicked for: ' + row.full_name);
                                  console.log('Accept button clicked:', row);
                                  handleAcceptAppointment(row);
                                }}
                                title="Accept appointment"
                              >
                                Accept
                              </button>
                              <button 
                                className="admin-action-btn reject" 
                                onClick={() => {
                                  alert('Reject button clicked for: ' + row.full_name);
                                  console.log('Reject button clicked:', row);
                                  handleRejectAppointment(row);
                                }}
                                title="Reject appointment"
                              >
                                Reject
                              </button>
                            </>
                          )}
                          <button 
                            className="admin-action-btn view" 
                            onClick={() => handleViewAppointment(row)}
                            title="View appointment details"
                          >
                            View
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}

                </tbody></table>

              )}

            </div>

          )}



          {activeTab === "camps" && (

            <div className="admin-table-wrap">

              {loadingCamps ? <div className="admin-table-msg">Loading camps…</div> : errorCamps ? <div className="admin-table-msg error">{errorCamps}</div> : !camps || camps.length === 0 ? <div className="admin-table-msg">No camps found.</div> : (

                <table className="admin-table"><thead><tr><th>#</th><th>Organization</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>

                  {camps.map((row) => (
                    <tr key={row.id}>
                      <td>{row.id}</td>
                      <td>{row.organization_name}</td>
                      <td>{row.preferred_date}</td>
                      <td>
                        <span className={`admin-badge-status ${row.status}`}>
                          {row.status}
                        </span>
                      </td>
                      <td className="admin-td-actions">
                        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                          {row.status === 'pending' && (
                            <>
                              <button 
                                className="admin-action-btn accept" 
                                onClick={() => handleAcceptCamp(row)}
                                title="Accept camp request"
                              >
                                Accept
                              </button>
                              <button 
                                className="admin-action-btn reject" 
                                onClick={() => handleRejectCamp(row)}
                                title="Reject camp request"
                              >
                                Reject
                              </button>
                            </>
                          )}
                          <button 
                            className="admin-action-btn view" 
                            onClick={() => handleViewCamp(row)}
                            title="View camp details"
                          >
                            View
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}

                </tbody></table>

              )}

            </div>

          )}

        </section>

      </div>



      <DonorDetailsPanel open={donorDetailsOpen} loading={donorDetailsLoading} error={donorDetailsError} donor={selectedDonorDetails} onClose={closeDonorDetails} />

      {/* View Details Modal */}
      <ViewDetailsModal 
        isOpen={detailsModal.isOpen}
        type={detailsModal.type}
        data={detailsModal.data}
        onClose={() => setDetailsModal({ isOpen: false, type: null, data: null })}
      />

      
      {/* Defer Temporary Modal */}

      {approveModal.open && (

        <div className="defer-modal-backdrop" onClick={closeApproveModal}>

          <div className="defer-modal" onClick={(e) => e.stopPropagation()}>

            <div className="defer-modal-header" style={{ background: 'linear-gradient(135deg, #1b7f4f 0%, #2fa866 100%)' }}>

              <div className="icon">✅</div>

              <h2>Approve Donor</h2>

              <button className="admin-modal-close" onClick={closeApproveModal}>×</button>

            </div>

            <div className="defer-modal-body">

              <div className="defer-donor-info" style={{ borderLeftColor: '#1b7f4f' }}>

                <div className="label">Donor Name</div>

                <div className="value">{approveModal.donorName}</div>

              </div>

              <div className="defer-info-box" style={{ background: '#e8f5ee', borderColor: '#b7e5c4', color: '#1b5e36' }}>

                <strong>Good news:</strong>

                This donor appears eligible to donate. Review the message below, then confirm to send it.

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="approveMessage">Message to donor</label>

                <textarea

                  id="approveMessage"

                  value={approveModal.message}

                  onChange={(e) => setApproveModal({ ...approveModal, message: e.target.value })}

                  rows={6}

                  style={{ width: '100%', padding: '12px', borderRadius: '8px', border: '1px solid #ddd', marginTop: '8px', resize: 'vertical' }}

                />

              </div>

              {decisionMessage && (

                <div style={{ color: '#1b5e36', marginTop: '12px', padding: '12px', background: '#e8f5ee', border: '1px solid #b7e5c4', borderRadius: '6px' }}>

                  {decisionMessage}

                </div>

              )}

            </div>

            <div className="defer-modal-footer">

              <button className="btn-cancel" onClick={closeApproveModal}>

                Cancel

              </button>

              <button 

                className="btn-submit" 

                onClick={handleApproveSubmit}

                disabled={processingDecision}

                style={{ background: '#1b7f4f' }}

              >

                {processingDecision ? 'Processing...' : 'Confirm Approval'}

              </button>

            </div>

          </div>

        </div>

      )}

      {/* Defer Temporary Modal */}

      {deferTemporaryModal.open && (

        <div className="defer-modal-backdrop" onClick={closeDeferTemporaryModal}>

          <div className="defer-modal" onClick={(e) => e.stopPropagation()}>

            <div className="defer-modal-header">

              <div className="icon">⏰</div>

              <h2>Temporary Deferral</h2>

              <button className="admin-modal-close" onClick={closeDeferTemporaryModal}>×</button>

            </div>

            <div className="defer-modal-body">

              <div className="defer-donor-info">

                <div className="label">Donor Name</div>

                <div className="value">{deferTemporaryModal.donorName}</div>

              </div>

              

              <div className="defer-form-group">

                <label>Select deferral period:</label>

                <div style={{ display: 'flex', gap: '12px', marginTop: '8px' }}>

                  <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>

                    <input

                      type="radio"

                      name="deferPeriod"

                      value="6"

                      checked={deferTemporaryModal.months === 6}

                      onChange={(e) => setDeferTemporaryModal({ ...deferTemporaryModal, months: 6 })}

                    />

                    <span>6 months</span>

                  </label>

                  <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>

                    <input

                      type="radio"

                      name="deferPeriod"

                      value="12"

                      checked={deferTemporaryModal.months === 12}

                      onChange={(e) => setDeferTemporaryModal({ ...deferTemporaryModal, months: 12 })}

                    />

                    <span>12 months</span>

                  </label>

                </div>

              </div>

              

              <div className="defer-info-box">

                <strong>Information:</strong>

                The donor will be notified automatically with their deferral period and next eligible donation date.

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="temporaryDeferralMessage">Message to donor</label>

                <textarea

                  id="temporaryDeferralMessage"

                  value={deferTemporaryModal.message}

                  onChange={(e) => setDeferTemporaryModal({ ...deferTemporaryModal, message: e.target.value })}

                  rows={6}

                  style={{ width: '100%', padding: '12px', borderRadius: '8px', border: '1px solid #ddd', marginTop: '8px', resize: 'vertical' }}

                />

              </div>

              

              {decisionMessage && (

                <div style={{ color: '#28a745', marginTop: '12px', padding: '12px', background: '#d4edda', border: '1px solid #c3e6cb', borderRadius: '6px' }}>

                  {decisionMessage}

                </div>

              )}

            </div>

            <div className="defer-modal-footer">

              <button className="btn-cancel" onClick={closeDeferTemporaryModal}>

                Cancel

              </button>

              <button 

                className="btn-submit" 

                onClick={handleDeferTemporarySubmit}

                disabled={processingDecision}

              >

                {processingDecision ? 'Processing...' : `Defer for ${deferTemporaryModal.months} months`}

              </button>

            </div>

          </div>

        </div>

      )}

      

      {/* Permanent Deferral Warning Modal */}

      {permanentDeferralModal.open && (

        <div className="defer-modal-backdrop" onClick={closePermanentDeferralModal}>

          <div className="defer-modal" onClick={(e) => e.stopPropagation()}>

            <div className="defer-modal-header">

              <div className="icon">⚠️</div>

              <h2>Permanent Deferral</h2>

              <button className="admin-modal-close" onClick={closePermanentDeferralModal}>×</button>

            </div>

            <div className="defer-modal-body">

              <div className="defer-donor-info">

                <div className="label">Donor Name</div>

                <div className="value">{permanentDeferralModal.donorName}</div>

              </div>

              

              <div className="defer-info-box" style={{ background: '#f8d7da', borderColor: '#f5c6cb', color: '#721c24' }}>

                <strong>⚠️ Warning:</strong>

                This donor will be permanently deferred. A confidential notification will be sent. Continue?

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="permanentDeferralMessage">Message to donor</label>

                <textarea

                  id="permanentDeferralMessage"

                  value={permanentDeferralModal.message}

                  onChange={(e) => setPermanentDeferralModal({ ...permanentDeferralModal, message: e.target.value })}

                  rows={6}

                  style={{ width: '100%', padding: '12px', borderRadius: '8px', border: '1px solid #ddd', marginTop: '8px', resize: 'vertical' }}

                />

              </div>

              

              {decisionMessage && (

                <div style={{ color: '#28a745', marginTop: '12px', padding: '12px', background: '#d4edda', border: '1px solid #c3e6cb', borderRadius: '6px' }}>

                  {decisionMessage}

                </div>

              )}

            </div>

            <div className="defer-modal-footer">

              <button className="btn-cancel" onClick={closePermanentDeferralModal}>

                Cancel

              </button>

              <button 

                className="btn-submit" 

                onClick={handlePermanentDeferralSubmit}

                disabled={processingDecision}

                style={{ background: '#dc3545' }}

              >

                {processingDecision ? 'Processing...' : 'Confirm Permanent Deferral'}

              </button>

            </div>

          </div>

        </div>

      )}

      

      {/* Edit Donor Modal */}

      {editDonorModal.open && (

        <div className="defer-modal-backdrop" onClick={closeEditDonorModal}>

          <div className="defer-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '500px' }}>

            <div className="defer-modal-header">

              <div className="icon">✏️</div>

              <h2>Edit Donor Information</h2>

              <button className="admin-modal-close" onClick={closeEditDonorModal}>×</button>

            </div>

            <div className="defer-modal-body" style={{ maxHeight: '600px', overflowY: 'auto' }}>

              <div className="defer-form-group">

                <label htmlFor="editFullName">Full Name *</label>

                <input

                  id="editFullName"

                  type="text"

                  value={editDonorModal.full_name}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, full_name: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                />

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="editEmail">Email *</label>

                <input

                  id="editEmail"

                  type="email"

                  value={editDonorModal.email}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, email: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                />

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="editPhone">Phone *</label>

                <input

                  id="editPhone"

                  type="tel"

                  value={editDonorModal.phone}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, phone: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                />

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="editDOB">Date of Birth</label>

                <input

                  id="editDOB"

                  type="date"

                  value={editDonorModal.date_of_birth}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, date_of_birth: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                />

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="editGender">Gender</label>

                <select

                  id="editGender"

                  value={editDonorModal.gender}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, gender: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                >

                  <option value="">-- Select Gender --</option>

                  <option value="Male">Male</option>

                  <option value="Female">Female</option>

                  <option value="Other">Other</option>

                </select>

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="editBloodType">Blood Type</label>

                <select

                  id="editBloodType"

                  value={editDonorModal.blood_type}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, blood_type: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                >

                  <option value="">-- Select Blood Type --</option>

                  <option value="O+">O+</option>

                  <option value="O-">O-</option>

                  <option value="A+">A+</option>

                  <option value="A-">A-</option>

                  <option value="B+">B+</option>

                  <option value="B-">B-</option>

                  <option value="AB+">AB+</option>

                  <option value="AB-">AB-</option>

                </select>

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label htmlFor="editStatus">Donor Status</label>

                <select

                  id="editStatus"

                  value={editDonorModal.status}

                  onChange={(e) => setEditDonorModal({ ...editDonorModal, status: e.target.value })}

                  style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                >

                  <option value="">-- Select Status --</option>

                  <option value="Pending">Pending</option>

                  <option value="Awaiting Review">Awaiting Review</option>

                  <option value="Ready for Blood Draw">Ready for Blood Draw</option>

                  <option value="Blood Donated">Blood Donated</option>

                  <option value="Tested - Negative">Tested - Negative</option>

                  <option value="Approved Donor">Approved Donor</option>

                  <option value="Temporarily Deferred">Temporarily Deferred</option>

                  <option value="Permanently Deferred">Permanently Deferred</option>

                </select>

              </div>

              <div className="defer-form-group" style={{ marginTop: '12px' }}>

                <label>

                  <input

                    type="checkbox"

                    checked={editDonorModal.deferred === 1}

                    onChange={(e) => setEditDonorModal({ ...editDonorModal, deferred: e.target.checked ? 1 : 0 })}

                  />

                  <span style={{ marginLeft: '8px' }}>Deferred</span>

                </label>

              </div>

              {editDonorModal.deferred === 1 && (

                <div className="defer-form-group" style={{ marginTop: '12px' }}>

                  <label htmlFor="editDeferredUntil">Deferred Until</label>

                  <input

                    id="editDeferredUntil"

                    type="date"

                    value={editDonorModal.deferred_until || ''}

                    onChange={(e) => setEditDonorModal({ ...editDonorModal, deferred_until: e.target.value })}

                    style={{ width: '100%', padding: '10px', marginTop: '6px', borderRadius: '6px', border: '1px solid #ddd', fontSize: '14px' }}

                  />

                </div>

              )}

            </div>

            <div className="defer-modal-footer">

              <button className="btn-cancel" onClick={closeEditDonorModal} disabled={savingDonorEdit}>

                Cancel

              </button>

              <button 

                className="btn-submit" 

                onClick={handleSaveDonorEdit}

                disabled={savingDonorEdit}

              >

                {savingDonorEdit ? 'Saving...' : 'Save Changes'}

              </button>

            </div>

          </div>

        </div>

      )}
      </div>
    </AdminShell>
  );
}