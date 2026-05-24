import React, { useEffect, useState, useCallback, useRef } from "react";
/* eslint-disable no-unused-vars, react-hooks/exhaustive-deps */

import { Link, useNavigate } from "react-router-dom";

import { toast } from "react-toastify";

import "./AdminDashboard.css";

import "./AdminDashboard-DeferModal.css";

import { authFetch, clearAuthSession, getStoredUser } from "../utils/auth";
import { titleCase } from "../utils/strings";

import AdminShell from "../components/admin/AdminShell";

import DonorDetailsPanel from "../components/admin/DonorDetailsPanel";

import ViewDetailsModal from "../components/ViewDetailsModal";

import CampRequestModal from "../components/CampRequestModal";

import ConfirmationModal from "../components/ConfirmationModal";

import DonorRecordsPanel from "../components/admin/DonorRecordsPanel";
import DonationHistoryPanel from "../components/admin/DonationHistoryPanel";



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

  const [_notifications, setNotifications] = useState([]);

  const [unreadNotificationCount, setUnreadNotificationCount] = useState(0);

  const [lowStockItems, setLowStockItems] = useState([]);



  const [loadingStats, setLoadingStats] = useState(true);

  const [loadingAppointments, setLoadingAppointments] = useState(true);

  const [loadingCamps, setLoadingCamps] = useState(true);

  const [loadingDonors, setLoadingDonors] = useState(true);

  const [loadingStage2Donors, setLoadingStage2Donors] = useState(true);

  const [loadingPendingDonors, setLoadingPendingDonors] = useState(true);

  const [loadingBloodBanks, setLoadingBloodBanks] = useState(true);

  const [_loadingNotifications, setLoadingNotifications] = useState(true);

  const [loadingLowStock, setLoadingLowStock] = useState(true);



  const [errorAppointments, setErrorAppointments] = useState("");

  const [errorCamps, setErrorCamps] = useState("");

  const [errorDonors, setErrorDonors] = useState("");

  const [errorStage2Donors, setErrorStage2Donors] = useState("");

  const [errorPendingDonors, setErrorPendingDonors] = useState("");

  const [errorBloodBanks, setErrorBloodBanks] = useState("");

  const [_errorNotifications, setErrorNotifications] = useState("");

  const [errorLowStock, setErrorLowStock] = useState("");

  const [actionError, setActionError] = useState("");

  const [approvingSampleIds, setApprovingSampleIds] = useState(new Set());

  const [bankMessage, setBankMessage] = useState("");

  const [bannerToast, setBannerToast] = useState(null);

  const [isToastShowing, setIsToastShowing] = useState(false);

  const [archiveTarget, setArchiveTarget] = useState(null);

  // Helper to trigger downloads from other windows
  function DownloadLinkBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
  }

  useEffect(() => {
    const handleMessage = (event) => {
      const data = event.data || {};
      if (data.type !== 'donor-card-download' || !data.html || !data.filename) return;
      DownloadLinkBlob(new Blob([data.html], { type: 'text/html;charset=utf-8' }), data.filename);
      toast.success('Card downloaded.');
    };

    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, []);

  // Details modal state
  const [detailsModal, setDetailsModal] = useState({ isOpen: false, type: null, data: null });

  const [_appointmentEdit, _setAppointmentEdit] = useState(null);

  const [includeInactiveBanks, setIncludeInactiveBanks] = useState(false);

  const [bloodBankSearch, setBloodBankSearch] = useState("");

  const [bloodBankPage, setBloodBankPage] = useState(1);

  const [bloodBankPerPage] = useState(10);

  const [bloodBankPagination, setBloodBankPagination] = useState({ total: 0, totalPages: 1, page: 1, perPage: 10 });

  const [bankForm, setBankForm] = useState({
    id: null,
    name: "",
    address: "",
    dzongkhag: "",
    phone: "",
    email: "",
    hours: "Mon-Fri: 9:00 AM - 5:00 PM",
    latitude: "",
    longitude: "",
    hospital: "",
    emergencyPhone: "",
    emergency: "Emergency on call",
    status: "active",
    availabilityStatus: "open",
    servicesText: "Blood Donation, Testing",
    types: ["A+", "A-", "B+", "B-", "AB+", "AB-"],
    // inventory will be initialized when needed
    inventory: {},
  });

  const [expandedDonorRows, setExpandedDonorRows] = useState(new Set());

  const [selectedDonorDetails, setSelectedDonorDetails] = useState(null);

  const [donorDetailsOpen, setDonorDetailsOpen] = useState(false);

  const [donorDetailsLoading, setDonorDetailsLoading] = useState(false);

  const [donorDetailsError, setDonorDetailsError] = useState("");

  const [referenceSearchQuery, setReferenceSearchQuery] = useState("");

  const [referenceBloodType, setReferenceBloodType] = useState("");

  const [_referenceWorkflowStatus, _setReferenceWorkflowStatus] = useState("");

  

  // Admin decision modal state

  const [approveModal, setApproveModal] = useState({ open: false, donorId: null, donorName: "", message: "" });

  const [deferTemporaryModal, setDeferTemporaryModal] = useState({ open: false, donorId: null, donorName: "", months: 6, message: "" });

  const [permanentDeferralModal, setPermanentDeferralModal] = useState({ open: false, donorId: null, donorName: "", message: "" });

  const [appointmentModal, setAppointmentModal] = useState({
    open: false,
    appointment: null,
    preferred_date: "",
    preferred_time: "",
    blood_bank: "",
    notes: "",
    status: "confirmed",
  });

  const [rejectAppointmentModal, setRejectAppointmentModal] = useState({ open: false, appointment: null });

  const [campModal, setCampModal] = useState({ open: false, camp: null, isLoading: false });

  const [confirmationModal, setConfirmationModal] = useState({ isOpen: false, title: '', message: '', onConfirm: null, type: 'warning', isLoading: false });

  const [savingAppointment, setSavingAppointment] = useState(false);

  const formatTimeForInput = (value) => {
    if (!value) return "";
    const trimmed = String(value).trim();
    if (/^\d{1,2}:\d{2}$/.test(trimmed)) {
      return trimmed.padStart(5, '0');
    }
    if (/^\d{1,2}:\d{2}\s*(AM|PM)$/i.test(trimmed)) {
      const [time, period] = trimmed.split(/\s+/);
      let [hours, minutes] = time.split(':').map(Number);
      const upper = period.toUpperCase();
      if (upper === 'AM' && hours === 12) hours = 0;
      if (upper === 'PM' && hours < 12) hours += 12;
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }
    if (/^\d{1,2}:\d{2}:\d{2}$/.test(trimmed)) {
      const [hours, minutes] = trimmed.split(':');
      return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
    }
    return "";
  };

  const normalizeAppointmentStatus = (value) => {
    const status = String(value || "").trim().toLowerCase();
    if (['completed', 'complete', 'done'].includes(status)) return 'completed';
    if (['deferred', 'defer'].includes(status)) return 'deferred';
    if (['cancelled', 'canceled', 'rejected', 'reject'].includes(status)) return 'cancelled';
    if (['confirmed', 'confirm'].includes(status)) return 'confirmed';
    if (!status || status === 'pending') return 'pending';
    return status;
  };

  const getAppointmentStatusLabel = (value) => {
    switch (normalizeAppointmentStatus(value)) {
      case 'completed':
        return 'Completed';
      case 'deferred':
        return 'Deferred';
      case 'cancelled':
        return 'Cancelled';
      case 'confirmed':
        return 'Confirmed';
      case 'pending':
        return 'Pending';
      default:
        return value ? String(value) : 'Pending';
    }
  };

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

  const [_editingDonor, _setEditingDonor] = useState(null);
  const [savingDonorEdit, setSavingDonorEdit] = useState(false);

  const [processingDecision, setProcessingDecision] = useState(false);

  const [decisionMessage, setDecisionMessage] = useState("");

  const [donationHistoryFilters, setDonationHistoryFilters] = useState({
    donorName: "",
    cid: "",
    bloodGroup: "",
    componentType: "",
    bloodBank: "",
    dateFrom: "",
    dateTo: "",
  });

  const [appliedDonationHistoryFilters, setAppliedDonationHistoryFilters] = useState({
    donorName: "",
    cid: "",
    bloodGroup: "",
    componentType: "",
    bloodBank: "",
    dateFrom: "",
    dateTo: "",
  });

  const donationHistoryRows = [
    {
      id: "DH-001",
      date: "2026-05-23",
      donorName: "Yangdon",
      cid: "11225543210",
      bloodGroup: "AB+",
      bloodBank: "JDWNRH Thimphu",
      componentType: "Whole Blood",
      units: 1,
      staffName: "Pema Dorji",
      status: "Completed",
    },
    {
      id: "DH-002",
      date: "2026-05-18",
      donorName: "Tshering Lhamo",
      cid: "11531006200",
      bloodGroup: "O+",
      bloodBank: "Paro District Blood Bank",
      componentType: "PRBC",
      units: 1,
      staffName: "Tshering Wangmo",
      status: "Completed",
    },
    {
      id: "DH-003",
      date: "2026-04-30",
      donorName: "Kinley Zangmo",
      cid: "11770654321",
      bloodGroup: "A+",
      bloodBank: "Mongar Blood Bank",
      componentType: "Platelets",
      units: 1,
      staffName: "Dawa",
      status: "Pending",
    },
    {
      id: "DH-004",
      date: "2026-03-12",
      donorName: "Pelden Lhamo",
      cid: "12445678901",
      bloodGroup: "B+",
      bloodBank: "Thimphu Blood Bank",
      componentType: "Plasma",
      units: 1,
      staffName: "Dorji Wangchuk",
      status: "Rejected",
    },
  ];

  const donationHistoryBloodBanks = [...new Set(donationHistoryRows.map((row) => row.bloodBank))];
  const donationHistoryBloodGroups = [...new Set(donationHistoryRows.map((row) => row.bloodGroup))];
  const donationHistoryComponentTypes = [...new Set(donationHistoryRows.map((row) => row.componentType))];

  const maskCid = (cid) => {
    const digits = String(cid || "").replace(/\D+/g, "");
    if (digits.length <= 4) return digits || "—";
    return `${digits.slice(0, 4)}****`;
  };

  const formatDonationDate = (value) => {
    if (!value) return "—";
    try {
      return new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(value));
    } catch {
      return value;
    }
  };

  const getDonationHistoryStatusClass = (status) => {
    const normalized = String(status || "").toLowerCase();
    if (normalized === "completed") return "completed";
    if (normalized === "pending") return "pending";
    if (normalized === "rejected") return "rejected";
    return "pending";
  };

  const filteredDonationHistoryRows = donationHistoryRows.filter((row) => {
    const completedOnly = String(row.status || "").toLowerCase() === "completed";
    if (!completedOnly) return false;

    const filters = appliedDonationHistoryFilters;
    if (filters.donorName && !String(row.donorName || "").toLowerCase().includes(filters.donorName.toLowerCase())) return false;
    if (filters.cid && !String(row.cid || "").includes(filters.cid.replace(/\D+/g, ""))) return false;
    if (filters.bloodGroup && String(row.bloodGroup || "").toLowerCase() !== filters.bloodGroup.toLowerCase()) return false;
    if (filters.componentType && String(row.componentType || "").toLowerCase() !== filters.componentType.toLowerCase()) return false;
    if (filters.bloodBank && String(row.bloodBank || "").toLowerCase() !== filters.bloodBank.toLowerCase()) return false;
    if (filters.dateFrom && row.date < filters.dateFrom) return false;
    if (filters.dateTo && row.date > filters.dateTo) return false;
    return true;
  });

  const donationHistoryStats = {
    totalDonations: donationHistoryRows.filter((row) => String(row.status || "").toLowerCase() === "completed").length,
    thisMonth: donationHistoryRows.filter((row) => String(row.status || "").toLowerCase() === "completed" && row.date.startsWith("2026-05")).length,
    totalUnits: donationHistoryRows
      .filter((row) => String(row.status || "").toLowerCase() === "completed")
      .reduce((sum, row) => sum + Number(row.units || 0), 0),
    activeBloodBanks: donationHistoryBloodBanks.length,
  };

  const updateDonationHistoryFilter = (field, value) => {
    setDonationHistoryFilters((prev) => ({ ...prev, [field]: value }));
  };

  const applyDonationHistoryFilters = () => {
    setAppliedDonationHistoryFilters({ ...donationHistoryFilters });
  };

  const clearDonationHistoryFilters = () => {
    const empty = {
      donorName: "",
      cid: "",
      bloodGroup: "",
      componentType: "",
      bloodBank: "",
      dateFrom: "",
      dateTo: "",
    };
    setDonationHistoryFilters(empty);
    setAppliedDonationHistoryFilters(empty);
  };



  const BLOOD_GROUP_OPTIONS = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

  const BANK_TYPES_FOR_FORM = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

  const SAMPLE_BLOOD_BANKS = [
    { id: 9, name: "Bumthang Blood Bank", dzongkhag: "Bumthang", phone: "17967631", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Bumthang" },
    { id: 2, name: "Phuentsholing Blood Bank", dzongkhag: "Chukha", phone: "77895643", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Chukha" },
    { id: 16, name: "Dagana District Blood Bank", dzongkhag: "Dagana", phone: "17896543", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Dagana" },
    { id: 5, name: "Gasa District Blood Bank", dzongkhag: "Gasa", phone: "77895643", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Gasa" },
    { id: 6, name: "Haa District Blood Bank", dzongkhag: "Haa", phone: "17656543", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Haa" },
    { id: 13, name: "Lhuntse Blood Bank", dzongkhag: "Lhuntse", phone: "17665544", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Lhuntse" },
    { id: 19, name: "Lingkorthakha District Blood Bank", dzongkhag: "Lingkorthakha", phone: "16789054", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Lingkorthakha" },
    { id: 10, name: "Mongar Blood Bank", dzongkhag: "Mongar", phone: "17689054", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Mongar" },
    { id: 3, name: "Paro District Blood Bank", dzongkhag: "Paro", phone: "77098765", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Paro" },
    { id: 20, name: "Pemagatshel District Blood Bank", dzongkhag: "Pemagatshel", phone: "1753116", latitude: null, longitude: null, status: "active", availabilityStatus: "open", address: "Pemagatshel" },
  ];



  const parseApiError = useCallback(async (response, fallbackMessage = "Request failed.") => {

    if (response.status === 401 || response.status === 403) {

      clearAuthSession();

      navigate("/login", { replace: true });

      return "Your admin session expired. Please sign in again.";

    }

    let message = fallbackMessage;

    try {

      const data = await response.json();

      if (data && typeof data.message === "string" && data.message.trim()) {

        message = data.message;

      }

    } catch (_) {}

    return `${message} (HTTP ${response.status})`;

  }, [navigate]);

  // Shared localStorage key for syncing admin -> public list without backend changes
  const SHARED_BANKS_KEY = "blood_banks_shared_v1";
  const genLocalId = () => Date.now() + Math.floor(Math.random() * 999);
  const readSharedBanks = useCallback(() => {
    try {
      const raw = window.localStorage.getItem(SHARED_BANKS_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : null;
    } catch (e) {
      return null;
    }
  }, []);
  const writeSharedBanks = useCallback((list) => {
    try {
      window.localStorage.setItem(SHARED_BANKS_KEY, JSON.stringify(list || []));
      // Notify other tabs via storage event
      window.dispatchEvent(new Event('storage'));
      // Notify the current tab (same window) via custom event
      window.dispatchEvent(new CustomEvent('banks-updated', { detail: { banks: list } }));
    } catch (e) {
      // ignore write failures
    }
  }, []);

  // Ensure shared storage seeded with sample banks so public view has initial data
  // eslint-disable-next-line react-hooks/exhaustive-deps
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    try {
      const curr = readSharedBanks();
      if (!curr || !Array.isArray(curr) || curr.length === 0) {
        const mapped = SAMPLE_BLOOD_BANKS.map((b) => ({
          id: b.id || genLocalId(),
          name: b.name || "",
          hospital: b.hospital || b.name || "",
          dzongkhag: b.dzongkhag || "",
          address: b.address || "",
          phone: b.phone || "",
          email: b.email || "",
          hours: b.hours || "",
          emergency_phone: b.emergencyPhone || b.emergency_phone || b.phone || "",
          services: Array.isArray(b.services) ? b.services : (b.servicesText ? String(b.servicesText).split(",").map(s=>s.trim()).filter(Boolean) : []),
          types: Array.isArray(b.types) ? b.types : ["A+","A-","B+","B-","AB+","AB-"],
          status: b.status || "active",
          availabilityStatus: b.availabilityStatus || b.availability_status || "open",
          latitude: b.latitude ?? null,
          longitude: b.longitude ?? null,
          is_open_now: (b.availabilityStatus || b.availability_status || b.is_open_now) === "open" || !!b.is_open_now,
        }));
        writeSharedBanks(mapped);
      }
    } catch (e) {}
  }, [readSharedBanks, writeSharedBanks]);

  // Fetch all blood banks without pagination for localStorage syncing
  const fetchAllBloodBanksForSync = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      params.set("include_inactive", "1");
      params.set("per_page", "1000"); // Fetch all banks
      params.set("_t", Date.now().toString());

      const res = await authFetch(`backend/api/get_blood_banks_admin.php?${params.toString()}`, { cache: "no-store" });

      if (!res.ok) return [];

      const data = await res.json();

      if (!data.success) return [];

      return Array.isArray(data.data) ? data.data : [];
    } catch (error) {
      console.error("Error fetching all blood banks for sync:", error);
      return [];
    }
  }, [authFetch]);

  // Sync fetched blood banks from API to localStorage so public page gets fresh data with emails
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    const syncAllBanks = async () => {
      const allBanks = await fetchAllBloodBanksForSync();
      if (Array.isArray(allBanks) && allBanks.length > 0) {
        try {
          const mapped = allBanks.map((b) => ({
            id: b.id,
            name: b.name || "",
            hospital: b.hospital || b.name || "",
            dzongkhag: b.dzongkhag || "",
            address: b.address || "",
            phone: b.phone || "",
            email: b.email || "",
            hours: b.hours || "",
            emergency_phone: b.emergency_phone || b.phone || "",
            services: Array.isArray(b.services) ? b.services : (b.services_json ? (() => { try { return JSON.parse(b.services_json); } catch { return []; } })() : []),
            types: Array.isArray(b.types) ? b.types : (b.types_csv ? b.types_csv.split(',').map(t => t.trim()).filter(Boolean) : ["A+","A-","B+","B-","AB+","AB-"]),
            status: b.status || b.directory_status || "active",
            availabilityStatus: b.availability_status || b.availabilityStatus || "open",
            latitude: b.latitude ?? null,
            longitude: b.longitude ?? null,
            is_open_now: (b.availability_status || b.availabilityStatus || "open") === "open",
          }));
          writeSharedBanks(mapped);
        } catch (e) {
          // non-fatal: if mapping fails, keep existing localStorage data
        }
      }
    };

    syncAllBanks();
  }, [fetchAllBloodBanksForSync, writeSharedBanks]);

  const normalizeBankFormFromRow = useCallback((bank) => {
    if (!bank) return null;

    return {
      id: bank.id ?? null,
      name: String(bank.name || ""),
      address: String(bank.address || ""),
      dzongkhag: String(bank.dzongkhag || ""),
      phone: String(bank.phone || ""),
      email: String(bank.email || ""),
      hours: String(bank.hours || "Mon-Fri: 9:00 AM - 5:00 PM"),
      latitude: bank.latitude ?? "",
      longitude: bank.longitude ?? "",
      hospital: String(bank.hospital || bank.name || ""),
      emergencyPhone: String(bank.emergencyPhone || bank.emergency_phone || bank.phone || ""),
      emergency: String(bank.emergency || "Emergency on call"),
      status: String(bank.status || "active"),
      availabilityStatus: String(bank.availabilityStatus || bank.availability_status || "open"),
      servicesText: Array.isArray(bank.services) ? bank.services.join(", ") : "Blood Donation, Testing",
      types: Array.isArray(bank.types) && bank.types.length > 0 ? bank.types : ["A+", "A-", "B+", "B-", "AB+", "AB-"],
      inventory: (() => {
        try {
          if (bank.inventory && typeof bank.inventory === 'object') return bank.inventory;
          if (bank.inventory_json) return JSON.parse(bank.inventory_json);
          return defaultInventoryForBank();
        } catch (e) { return defaultInventoryForBank(); }
      })(),
    };
  }, []);

  const toNullableNumber = (value) => {
    const text = String(value ?? "").trim();
    if (!text) return null;
    const n = Number(text);
    return Number.isFinite(n) ? n : null;
  };

  const defaultInventoryForBank = () => ({
    PRBC: Object.fromEntries(BLOOD_GROUP_OPTIONS.map(bt => [bt, { units: 0, min: 5 }])),
    Platelets: Object.fromEntries(BLOOD_GROUP_OPTIONS.map(bt => [bt, { units: 0, min: 5 }])),
    Plasma: Object.fromEntries(BLOOD_GROUP_OPTIONS.map(bt => [bt, { units: 0, min: 5 }])),
    Wholeblood: Object.fromEntries(BLOOD_GROUP_OPTIONS.map(bt => [bt, { units: 0, min: 5 }]))
  });

  const getInventoryTotalUnits = (inventory) => {
    if (!inventory || typeof inventory !== 'object') return 0;
    return Object.values(inventory).reduce((componentTotal, componentRows) => {
      if (!componentRows || typeof componentRows !== 'object') return componentTotal;
      return componentTotal + Object.values(componentRows).reduce((bloodTypeTotal, row) => {
        const units = Number(row?.units || 0);
        return bloodTypeTotal + (Number.isFinite(units) ? units : 0);
      }, 0);
    }, 0);
  };

  const updateInventoryField = (component, bloodType, field, value) => {
    setBankForm((prev) => {
      const inv = prev.inventory ? { ...prev.inventory } : defaultInventoryForBank();
      inv[component] = { ...inv[component] };
      const btRow = inv[component][bloodType] ? { ...inv[component][bloodType] } : { units: 0, min: 5 };
      btRow[field] = value;
      inv[component][bloodType] = btRow;
      return { ...prev, inventory: inv };
    });
  };

  const resetBankForm = useCallback(() => {
    setBankForm({
      id: null,
      name: "",
      address: "",
      dzongkhag: "",
      phone: "",
      email: "",
      hours: "Mon-Fri: 9:00 AM - 5:00 PM",
      latitude: "",
      longitude: "",
      hospital: "",
      emergencyPhone: "",
      emergency: "Emergency on call",
      status: "active",
      availabilityStatus: "open",
      servicesText: "Blood Donation, Testing",
      types: ["A+", "A-", "B+", "B-", "AB+", "AB-"],
      inventory: defaultInventoryForBank(),
    });
  }, []);

  const editBankFromRow = useCallback((bank) => {
    const normalized = normalizeBankFormFromRow(bank);
    if (!normalized) return;
    setBankForm(normalized);
    setBankMessage("Editing blood bank. Update fields and click Save Blood Bank.");
    requestAnimationFrame(() => {
      if (bankFormRef.current) {
        bankFormRef.current.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  }, [normalizeBankFormFromRow]);

  const saveBloodBank = useCallback(async (overrideForm = null) => {
    const source = overrideForm || bankForm;
    if (!source.name.trim() || !source.address.trim() || !source.phone.trim() || !source.dzongkhag.trim()) {
      setBankMessage("Please fill Name, Address, Dzongkhag, and Phone before saving.");
      return;
    }

    // Validate phone number format
    const phone = source.phone.trim();
    if (!/^\d{8}$/.test(phone)) {
      setBankMessage("Phone must be exactly 8 digits.");
      toast.error("Phone must be exactly 8 digits.");
      return;
    }
    if (!/^(16|17|77)/.test(phone)) {
      setBankMessage("Phone must start with 16, 17, or 77.");
      toast.error("Phone must start with 16, 17, or 77.");
      return;
    }

    // Validate emergency phone if provided
    if (source.emergencyPhone.trim()) {
      const emergencyPhone = source.emergencyPhone.trim();
      if (!/^\d{8}$/.test(emergencyPhone)) {
        setBankMessage("Emergency phone must be exactly 8 digits.");
        toast.error("Emergency phone must be exactly 8 digits.");
        return;
      }
      if (!/^(16|17|77)/.test(emergencyPhone)) {
        setBankMessage("Emergency phone must start with 16, 17, or 77.");
        toast.error("Emergency phone must start with 16, 17, or 77.");
        return;
      }
    }

    try {
      const payload = {
        id: source.id || undefined,
        name: source.name.trim(),
        hospital: (source.hospital || source.name).trim(),
        dzongkhag: source.dzongkhag.trim(),
        address: source.address.trim(),
        phone: source.phone.trim(),
        emergencyPhone: source.emergencyPhone?.trim() || source.phone.trim(),
        email: source.email?.trim() || "",
        hours: source.hours?.trim() || "Mon-Fri: 9:00 AM - 5:00 PM",
        emergency: source.emergency?.trim() || "Emergency on call",
        latitude: toNullableNumber(source.latitude),
        longitude: toNullableNumber(source.longitude),
        services: String(source.servicesText || "")
          .split(",")
          .map((v) => v.trim())
          .filter(Boolean),
        types: Array.isArray(source.types) && source.types.length > 0 ? source.types : ["A+", "A-", "B+", "B-", "AB+", "AB-"],
        status: source.status || "active",
        availabilityStatus: source.availabilityStatus || "open",
        inventory: source.inventory || null,
      };

      const res = await authFetch("backend/api/save_blood_bank.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to save blood bank.");

      setBankMessage(data.message || "Blood bank saved successfully.");
      toast.success(data.message || "Blood bank saved successfully.");
      resetBankForm();
      setBloodBankPage(1);
      
      // Update localStorage immediately with the saved bank data
      try {
        const savedBank = {
          id: payload.id || (data.data?.id),
          name: payload.name,
          hospital: payload.hospital || payload.name,
          dzongkhag: payload.dzongkhag,
          address: payload.address,
          phone: payload.phone,
          email: payload.email,
          hours: payload.hours,
          emergency_phone: payload.emergencyPhone || payload.phone,
          emergency: payload.emergency,
          services: Array.isArray(payload.services) ? payload.services : [],
          types: Array.isArray(payload.types) ? payload.types : [],
          status: payload.status || "active",
          availabilityStatus: payload.availabilityStatus || "open",
          latitude: payload.latitude ?? null,
          longitude: payload.longitude ?? null,
          is_open_now: (payload.availabilityStatus || "open") === "open",
        };
        
        const current = readSharedBanks() || [];
        const idx = current.findIndex((b) => String(b.id) === String(savedBank.id));
        if (idx >= 0) {
          // Update existing bank
          current[idx] = { ...current[idx], ...savedBank };
        } else {
          // Add new bank
          current.unshift(savedBank);
        }
        writeSharedBanks(current);
      } catch (e) { 
        console.error('Error updating localStorage after save:', e);
      }
      
      // Also fetch fresh from database to ensure synchronization
      fetchBloodBanks();
    } catch (error) {
      setBankMessage(error.message || "Could not save blood bank.");
      toast.error(error.message || "Could not save blood bank.");
    }
  }, [bankForm, resetBankForm]);

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const archiveBloodBank = useCallback(async (bank) => {
    if (!bank?.id) return;
    if (!window.confirm(`Archive ${bank.name}?`)) return;

    try {
      const res = await authFetch("backend/api/delete_blood_bank.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: bank.id }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to archive blood bank.");

      setArchiveTarget(bank.id);
      setBankMessage(data.message || "Blood bank archived successfully.");
      toast.success(data.message || "Blood bank archived successfully.");
      
      // Update localStorage immediately to reflect archive
      try {
        const current = readSharedBanks() || [];
        const idx = current.findIndex((b) => String(b.id) === String(bank.id));
        if (idx >= 0) {
          current[idx] = { ...current[idx], status: "archived" };
        }
        writeSharedBanks(current);
      } catch (e) {
        console.error('Error updating localStorage after archive:', e);
      }
      
      // Also fetch fresh from database
      fetchBloodBanks();
    } catch (error) {
      setBankMessage(error.message || "Could not archive blood bank.");
      toast.error(error.message || "Could not archive blood bank.");
    }
  }, []);

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const setBankLocation = useCallback(async (bank) => {
    if (!bank) return;

    const currentLat = bank.latitude ?? "";
    const currentLng = bank.longitude ?? "";

    const nextLat = window.prompt(`Latitude for ${bank.name}:`, currentLat === null ? "" : String(currentLat));
    if (nextLat === null) return;
    const nextLng = window.prompt(`Longitude for ${bank.name}:`, currentLng === null ? "" : String(currentLng));
    if (nextLng === null) return;

    const merged = normalizeBankFormFromRow(bank);
    if (!merged) return;
    merged.latitude = nextLat.trim();
    merged.longitude = nextLng.trim();
    await saveBloodBank(merged);
  }, [normalizeBankFormFromRow, saveBloodBank]);

  const openMapPicker = useCallback(() => {
    const lat = toNullableNumber(bankForm.latitude);
    const lng = toNullableNumber(bankForm.longitude);
    const target = lat !== null && lng !== null
      ? `https://www.google.com/maps?q=${lat},${lng}`
      : "https://www.google.com/maps/place/Bhutan";
    window.open(target, "_blank", "noopener,noreferrer");
  }, [bankForm.latitude, bankForm.longitude]);



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

      _setEditingDonor(donor);

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

    _setEditingDonor(null);

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

        full_name: titleCase(editDonorModal.full_name || ''),

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



  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {

    const parsed = getStoredUser();

    if (!parsed?.token) { clearAuthSession(); navigate("/login", { replace: true }); return; }

    if (parsed.role !== "admin") { clearAuthSession(); navigate("/", { replace: true }); return; }

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

      if (data.success) {
        const normalizedAppointments = Array.isArray(data.data)
          ? data.data.map((row) => {
              const statusSource = row.status ?? row.appointment_status ?? row.status_label ?? row.current_status ?? '';
              const status_key = normalizeAppointmentStatus(statusSource);
              return {
                ...row,
                status_key,
                status_label: getAppointmentStatusLabel(statusSource),
                status: getAppointmentStatusLabel(statusSource),
              };
            })
          : [];

        setAppointments(normalizedAppointments);
      }

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

      // Add cache-busting timestamp
      params.set("_t", Date.now().toString());

      const res = await authFetch(`backend/api/get_blood_banks_admin.php?${params.toString()}`, { cache: "no-store" });

      if (!res.ok) throw new Error(await parseApiError(res, "Failed to load blood banks."));

      const data = await res.json();

      if (!data.success) throw new Error(data.message || "Failed to load blood banks.");

      const banksList = Array.isArray(data.data) ? data.data : [];

      setBloodBanks(banksList);

      setBloodBankPagination(data.pagination || { total: banksList.length, totalPages: 1, page: bloodBankPage, perPage: bloodBankPerPage });

      // Return the fetched banks for immediate use
      return banksList;

    } catch (error) {

      setErrorBloodBanks(error.message || "Could not reach server.");

      return [];

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

    fetchLowStockAlerts();

    fetchNotifications();

  }, [user]);

  useEffect(() => {
    if (!user) return;
    const timer = setTimeout(() => {
      fetchBloodBanks();
    }, 220);

    return () => clearTimeout(timer);
  }, [user, fetchBloodBanks]);



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



  const _handleFinalizeDecision = async (sampleId, decision, donorName = "this donor") => {

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

  const buildTemporaryDeferralContent = useCallback((donorName, monthsValue) => {
    const safeMonths = Number(monthsValue) || 3;
    const nextEligibleDate = new Date();
    nextEligibleDate.setMonth(nextEligibleDate.getMonth() + safeMonths);

    return {
      months: safeMonths,
      nextEligibleIso: nextEligibleDate.toISOString().split('T')[0],
      message: `Dear ${donorName}, thank you for your willingness to donate blood. Based on your screening results, you are temporarily deferred for ${safeMonths} months. Please return on or after ${nextEligibleDate.toLocaleDateString()} for re-evaluation.`,
    };
  }, []);

  const openDeferTemporaryModal = (donorId, donorName, months = 6) => {
    const content = buildTemporaryDeferralContent(donorName, months);

    setDeferTemporaryModal({

      open: true,

      donorId,

      donorName,

      months: content.months,

      message: content.message,

    });

  };

  const handleTemporaryDeferralMonthsChange = useCallback((monthsValue) => {
    setDeferTemporaryModal((prev) => {
      const content = buildTemporaryDeferralContent(prev.donorName, monthsValue);
      return {
        ...prev,
        months: content.months,
        message: content.message,
      };
    });
  }, [buildTemporaryDeferralContent]);



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

    const { donorId, message } = approveModal;

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

    const { donorId, months } = deferTemporaryModal;

    const selectedMonths = Number(months) || 0;

    if (!donorId || !selectedMonths) return;

    setProcessingDecision(true);

    setDecisionMessage("");

    try {

      const content = buildTemporaryDeferralContent(deferTemporaryModal.donorName, selectedMonths);

      

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

          defer_until: content.nextEligibleIso,

          defer_months: selectedMonths,

          decision_notes: `Temporary deferral for ${selectedMonths} months`,

          message: deferTemporaryModal.message,

          next_eligible_date: content.nextEligibleIso

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

    const { donorId } = permanentDeferralModal;

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
  const openAppointmentModal = (appointment) => {
    setAppointmentModal({
      open: true,
      appointment,
      preferred_date: appointment.preferred_date || "",
      preferred_time: formatTimeForInput(appointment.preferred_time || ""),
      blood_bank: appointment.blood_bank || "",
      notes: appointment.notes || "",
      status: "confirmed",
    });
  };

  const closeAppointmentModal = () => {
    setAppointmentModal({
      open: false,
      appointment: null,
      preferred_date: "",
      preferred_time: "",
      blood_bank: "",
      notes: "",
      status: "confirmed",
    });
  };

  const handleSaveAndAcceptAppointment = async () => {
    if (!appointmentModal.appointment || !appointmentModal.preferred_date || !appointmentModal.preferred_time) {
      toast.error('Please provide the appointment date and time before accepting.');
      return;
    }

    setSavingAppointment(true);
    try {
      const res = await authFetch("backend/api/update_appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: appointmentModal.appointment.id,
          preferred_date: appointmentModal.preferred_date,
          preferred_time: appointmentModal.preferred_time,
          blood_bank: appointmentModal.blood_bank,
          notes: appointmentModal.notes,
          status: 'confirmed',
        }),
      });

      const data = await res.json();
      if (data.success) {
        toast.success(`Appointment for ${appointmentModal.appointment.full_name} confirmed successfully`);
        fetchAppointments();
        closeAppointmentModal();
      } else {
        toast.error(data.message || 'Failed to save and accept appointment');
      }
    } catch (error) {
      console.error('Error saving appointment:', error);
      toast.error('Error saving and accepting appointment');
    } finally {
      setSavingAppointment(false);
    }
  };

  const openRejectAppointmentModal = (appointment) => {
    setRejectAppointmentModal({ open: true, appointment });
  };

  const closeRejectAppointmentModal = () => {
    setRejectAppointmentModal({ open: false, appointment: null });
  };

  const handleConfirmRejectAppointment = async () => {
    if (!rejectAppointmentModal.appointment) {
      toast.error('No appointment selected to reject.');
      return;
    }

    setSavingAppointment(true);
    try {
      const res = await authFetch("backend/api/update_appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: rejectAppointmentModal.appointment.id,
          status: 'rejected',
        }),
      });

      const data = await res.json();
      if (data.success) {
        toast.success(`Appointment for ${rejectAppointmentModal.appointment.full_name} rejected successfully`);
        fetchAppointments();
        closeRejectAppointmentModal();
      } else {
        toast.error(data.message || 'Failed to reject appointment');
      }
    } catch (error) {
      console.error('Error rejecting appointment:', error);
      toast.error('Error rejecting appointment');
    } finally {
      setSavingAppointment(false);
    }
  };

  const handleAppointmentDetailsAction = async (action, appointment) => {
    if (!appointment || !appointment.id) {
      toast.error('No appointment selected.');
      return { success: false };
    }

    try {
      const res = await authFetch('backend/api/update_appointment_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          appointmentId: appointment.id,
          action,
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Failed to update appointment.');
      }

      const nextStatus = data?.data?.status || (action === 'completed' ? 'Completed' : action === 'deferred' ? 'Deferred' : 'Cancelled');
      toast.success(`Appointment for ${appointment.full_name} ${nextStatus.toLowerCase()}.`);
      fetchAppointments();
      setDetailsModal((prev) => prev.isOpen && prev.type === 'appointment' && prev.data?.id === appointment.id
        ? { ...prev, data: { ...prev.data, status: nextStatus } }
        : prev
      );

      return { success: true, status: nextStatus };
    } catch (error) {
      toast.error(error.message || 'Could not update appointment.');
      return { success: false };
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
  const openCampModal = (camp) => {
    setCampModal({ open: true, camp, isLoading: false });
  };

  const closeCampModal = () => {
    setCampModal({ open: false, camp: null, isLoading: false });
  };

  const refreshCampModal = async () => {
    if (!campModal.camp) return;
    
    try {
      // Fetch fresh camp data from the API
      const res = await authFetch("backend/api/get_camps.php?_ts=" + Date.now(), { 
        cache: "no-store" 
      });
      
      if (!res.ok) throw new Error("Failed to refresh");
      
      const data = await res.json();
      if (data.success && data.data) {
        // Find the current camp in the fresh data
        const updatedCamp = data.data.find(c => c.id === campModal.camp.id);
        if (updatedCamp) {
          setCampModal({ open: true, camp: updatedCamp, isLoading: false });
          toast.success("Data refreshed from database");
        }
      }
    } catch (error) {
      console.error('Error refreshing camp data:', error);
      toast.error("Failed to refresh data");
    }
  };

  const handleSaveCampChanges = async (updatedData) => {
    // Update the camps list with the new data
    const updatedCamps = camps.map(camp => 
      camp.id === updatedData.id 
        ? {
            ...camp,
            organization_name: updatedData.organization,
            contact_person: updatedData.contact_person,
            phone_number: updatedData.phone,
            email: updatedData.email,
            preferred_date: updatedData.date,
            expected_donors: updatedData.expected_participants
          }
        : camp
    );
    
    setCamps(updatedCamps);
    
    // Also update the modal with the new camp data
    const updatedCamp = updatedCamps.find(c => c.id === updatedData.id);
    if (updatedCamp) {
      setCampModal({ open: true, camp: updatedCamp, isLoading: false });
    }
  };

  const handleAcceptCamp = async (camp) => {
    console.log('Accept camp clicked:', camp);
    setCampModal(prev => ({ ...prev, isLoading: true }));
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
        closeCampModal();
        fetchCamps(); // Refresh the camps list
      } else {
        toast.error(data.message || "Failed to accept camp request");
      }
    } catch (error) {
      console.error('Error accepting camp request:', error);
      toast.error("Error accepting camp request");
    } finally {
      setCampModal(prev => ({ ...prev, isLoading: false }));
    }
  };

  const handleRejectCamp = async (camp) => {
    console.log('Reject camp clicked:', camp);
    setConfirmationModal(prev => ({ ...prev, isLoading: true }));
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
        setConfirmationModal({ isOpen: false, title: '', message: '', onConfirm: null, type: 'warning', isLoading: false });
        closeCampModal();
        fetchCamps(); // Refresh the camps list
      } else {
        toast.error(data.message || "Failed to reject camp request");
        setConfirmationModal({ isOpen: false, title: '', message: '', onConfirm: null, type: 'warning', isLoading: false });
      }
    } catch (error) {
      console.error('Error rejecting camp request:', error);
      toast.error("Error rejecting camp request");
      setConfirmationModal({ isOpen: false, title: '', message: '', onConfirm: null, type: 'warning', isLoading: false });
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

    const _positiveDiseaseLabel = getPositiveDiseaseLabel(row);
    const _sampleLabel = getSampleTestResultLabel(row);
    const trimmed = String(status || '').trim().toLowerCase();

    // NOTE: do not auto-derive deferral status from sample labels here —
    // final deferral decisions should only come from explicit admin workflow_status fields.
    return labels[trimmed] || 'Awaiting Review';

  };



  const getWorkflowStatusClass = (status, row = null) => {

    const _positiveDiseaseLabel = getPositiveDiseaseLabel(row);
    const _sampleLabel = getSampleTestResultLabel(row);
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

    const positiveSignalText = [testResult, testResultDisplay]
      .map((value) => String(value || '').toLowerCase())
      .join(' ');

    if (positiveSignalText.includes('positive') || positiveSignalText.includes('reactive')) {

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

  const fallbackFilteredBanks = SAMPLE_BLOOD_BANKS.filter((bank) => {
    if (!includeInactiveBanks && String(bank.status || "active").toLowerCase() !== "active") {
      return false;
    }

    const query = bloodBankSearch.trim().toLowerCase();
    if (!query) return true;
    return String(bank.name || "").toLowerCase().includes(query) || String(bank.dzongkhag || "").toLowerCase().includes(query);
  });

  const bankRows = Array.isArray(bloodBanks) && bloodBanks.length > 0 ? bloodBanks : fallbackFilteredBanks;
  const isFeatureView = ["donorRecords", "donationHistory", "certificates", "donorCards"].includes(activeTab);

  const featureViews = {
    donorRecords: {
      title: "Donor Records",
      subtitle: "Complete donor list with search, filters, export, and profile preview.",
      stats: [
        { label: "Visible columns", value: "CID, Name, Group, Phone" },
        { label: "Rows per page", value: "10 / 25 / 50 / 100" },
        { label: "Export", value: "CSV" },
      ],
      tableHead: ["#", "CID", "Name", "Blood Group", "Phone", "Email", "Last Donation", "Total", "Next Eligible", "Status"],
      tableRows: [
        ["1", "1122****", "Yangdon", "AB+", "17655432", "yangdon@example.com", "15 May 2026", "7", "13 Aug 2026", "Active"],
        ["2", "1153****", "Tshering Lhamo", "O+", "17654321", "tshering@example.com", "Never", "0", "N/A", "Pending"],
      ],
    },
    donationHistory: {
      title: "Donation History",
      subtitle: "Every donation event across donors, blood banks, components, and staff.",
      stats: [
        { label: "Total Donations", value: "156" },
        { label: "This Month", value: "18" },
        { label: "Total Units", value: "245" },
      ],
      tableHead: ["#", "Date", "Donor", "CID", "Blood Bank", "Component", "Units", "Staff", "Status"],
      tableRows: [
        ["1", "15 May 2026", "Yangdon", "1122****", "JDWNRH Thimphu", "Whole Blood", "1", "Pema Dorji", "Completed"],
        ["2", "10 Feb 2026", "Yangdon", "1122****", "Paro", "PRBC", "1", "Tshering Wangmo", "Completed"],
      ],
    },
    certificates: {
      title: "Certificates",
      subtitle: "Generate appreciation letters and certificates based on donation milestones.",
      stats: [
        { label: "Eligibility", value: "1 / 3 / 5 / 10 / 20+" },
        { label: "Formats", value: "Preview, PDF, Print" },
        { label: "History", value: "Saved per donor" },
      ],
      tableHead: ["Issue Date", "Donor", "CID", "Certificate Type", "Donations", "Status"],
      tableRows: [
        ["22 May 2026", "Yangdon", "1122****", "Certificate of Appreciation", "7", "Issued"],
        ["10 Jan 2026", "Yangdon", "1122****", "Appreciation Letter", "3", "Issued"],
      ],
    },
    donorCards: {
      title: "Donor Cards",
      subtitle: "Digital donor ID cards with QR, masked CID, print and PDF download.",
      stats: [
        { label: "Validity", value: "1 year" },
        { label: "QR Code", value: "Full CID encoded" },
        { label: "Formats", value: "Print, PDF, View Full Size" },
      ],
      tableHead: ["Issue Date", "Donor", "CID", "Card Number", "Status"],
      tableRows: [
        ["22 May 2026", "Yangdon", "1122****", "CARD-00014", "Active"],
        ["10 Jan 2026", "Yangdon", "1122****", "CARD-00008", "Expired"],
      ],
    },
  };

  const renderFeatureView = (key) => {
    const feature = featureViews[key];
    if (!feature) return null;

    if (key === "donorRecords") {
      return <DonorRecordsPanel embedded />;
    }

    if (key === "donationHistory") {
      return <DonationHistoryPanel embedded />;
    }

    return (
      <div className="admin-dashboard-grid">
        <article className="admin-panel-card admin-panel-wide">
          <div className="admin-panel-head"><h3>{feature.title}</h3></div>
          <p style={{ marginTop: 0, color: "#64748b" }}>{feature.subtitle}</p>
          <div className="admin-mini-list" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 12 }}>
            {feature.stats.map((item) => (
              <div key={item.label} className="admin-card" style={{ margin: 0, minHeight: 120 }}>
                <h3 style={{ marginTop: 0 }}>{item.label}</h3>
                <p className="admin-value" style={{ marginBottom: 0 }}>{item.value}</p>
              </div>
            ))}
          </div>
        </article>
        {key === 'donorCards' ? (
          <article className="admin-panel-card admin-panel-wide">
            <div style={{ display: 'flex', justifyContent: 'flex-end', padding: 12 }}>
              <button
                className="admin-action-btn"
                onClick={() => {
                  const donorName = 'Tshering Lhamo';
                  const donorId = 'DONOR-00014';
                  const fullCid = '17656565';
                  const bloodGroup = 'O+';
                  const issueDate = '22 May 2026';
                  const initials = donorName.split(/\s+/).map(p => p.charAt(0).toUpperCase()).slice(0,2).join('') || 'D';
                  const qrValue = encodeURIComponent(`donor:${donorId}|cid:${fullCid}|name:${donorName}|group:${bloodGroup}`);
                  const html = `<!doctype html><html><head><meta charset="utf-8"><title>Donor Card - ${donorName}</title><meta name="viewport" content="width=device-width,initial-scale=1"/><style>body{font-family:Arial,sans-serif;margin:0;background:#f5f0eb;color:#111827} .card-wrap{max-width:980px;margin:28px auto;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.12);overflow:hidden} .top{background:linear-gradient(90deg,#bf131e,#9a111c);color:#fff;padding:18px 26px;display:flex;justify-content:space-between;align-items:center} .brand{font-weight:800} .pill{background:#fff;color:#b31622;padding:8px 16px;border-radius:999px;font-weight:800} .body{padding:22px} .main{display:grid;grid-template-columns:160px 1fr 170px;gap:18px;align-items:start} .avatar{width:138px;height:138px;border-radius:50%;border:4px solid #bf131e;display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:800;color:#2f3a4a;background:radial-gradient(circle at 30% 20%,#fff,#f0f3f8)} .details h2{margin:0;font-size:40px;color:#b31622} .row{display:grid;grid-template-columns:132px 12px 1fr;gap:8px;padding:6px 0;font-size:20px} .label{color:#4b5563;font-weight:600} .value{font-weight:800} .qr{border:1px solid #e5e7eb;border-radius:14px;padding:10px;display:flex;align-items:center;justify-content:center} .footer{background:linear-gradient(90deg,#b0111c,#8e0f18);color:#fff;padding:14px 20px;font-weight:700;display:flex;align-items:center;gap:10px} .actions{display:flex;gap:16px;justify-content:center;padding:18px}</style></head><body><div class="card-wrap"><div class="top"><div class="brand">BLOOD TRANSFUSION SERVICE<br/>ROYAL GOVERNMENT OF BHUTAN</div><div class="pill">DONOR CARD</div></div><div class="body"><div class="main"><div class="avatar">${initials}</div><div class="details"><h2>${donorName}</h2><div style="font-weight:700;margin-bottom:12px">Donor ID: ${donorId}</div><div class="row"><div class="label">CID</div><div>:</div><div class="value">${fullCid}</div></div><div class="row"><div class="label">Blood Group</div><div>:</div><div class="value">${bloodGroup}</div></div><div class="row"><div class="label">Issue Date</div><div>:</div><div class="value">${issueDate}</div></div><div class="row"><div class="label">Blood Type</div><div>:</div><div class="value">Whole Blood</div></div></div><div class="qr"><img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${qrValue}" alt="qr"/></div></div></div><div class="footer">❤ Every Drop Counts, Every Donor Matters</div></div><div class="actions"><button onclick="window.print()" style="padding:10px 16px;border-radius:8px;border:1px solid #ddd;background:#fff;font-weight:700;">Print Card</button><button onclick="(function(){if(window.opener){window.opener.postMessage({type:'donor-card-download',filename:'${donorId.toLowerCase()}-card.html',html:document.documentElement.outerHTML}, '*');}else{var a=document.createElement('a');var blob=new Blob([document.documentElement.outerHTML],{type:'text/html'});a.href=URL.createObjectURL(blob);a.download='${donorId.toLowerCase()}-card.html';document.body.appendChild(a);a.click();a.remove();}})()" style="padding:10px 16px;border-radius:8px;border:1px solid #ddd;background:#fff;font-weight:700;margin-left:8px;">Download</button><button onclick="(function(){var w=window.open('','_blank','width=1400,height=980,scrollbars=yes,resizable=yes');if(!w) return;w.document.open();w.document.write(document.documentElement.outerHTML);w.document.close();w.focus();})()" style="padding:10px 16px;border-radius:8px;border:0;background:#b31622;color:#fff;font-weight:700;margin-left:8px;">View Full Size</button></div></body></html>`;
                  const popup = window.open('', '_blank', 'width=1100,height=760,scrollbars=yes,resizable=yes');
                  if (!popup) { toast.error('Popup blocked. Please allow popups for this site.'); return; }
                  popup.document.write(html);
                  popup.document.close();
                }}
              >
                View in Action
              </button>
            </div>
          </article>
        ) : null}

        <article className="admin-panel-card admin-panel-wide">
          <div className="admin-panel-head"><h3>Design Preview</h3></div>
          <div style={{ overflowX: "auto" }}>
            <table className="admin-table">
              <thead>
                <tr>{feature.tableHead.map((head) => (<th key={head}>{head}</th>))}</tr>
              </thead>
              <tbody>
                {feature.tableRows.map((row, index) => (
                  <tr key={`${key}-${index}`}>
                    {row.map((cell, cellIndex) => <td key={`${key}-${index}-${cellIndex}`}>{cell}</td>)}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </article>
      </div>
    );
  };



  return (
    <AdminShell user={user} onLogout={handleLogout} activeView={activeTab} onChangeView={setActiveTab}>
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

          {activeTab === "donorRecords" && renderFeatureView("donorRecords")}
          {activeTab === "donationHistory" && renderFeatureView("donationHistory")}
          {activeTab === "certificates" && renderFeatureView("certificates")}
          {activeTab === "donorCards" && renderFeatureView("donorCards")}

          {!isFeatureView && <div className="admin-tabs-bar">

            <button className={`admin-tab${activeTab === "dashboard" ? " active" : ""}`} onClick={() => setActiveTab("dashboard")}>📊 Dashboard</button>

            <button className={`admin-tab${activeTab === "donors" ? " active" : ""}`} onClick={() => setActiveTab("donors")}>

              🩸 Donors

              {donors && <span className="admin-tab-count">{donors.length}</span>}

              {pendingDonors && pendingDonors.length > 0 && <span className="admin-tab-count" style={{ marginLeft: 6 }}>{pendingReviewCount} pending</span>}

            </button>

            <button className={`admin-tab${activeTab === "appointments" ? " active" : ""}`} onClick={() => setActiveTab("appointments")}>📅 Appointments{appointments && <span className="admin-tab-count">{appointments.length}</span>}</button>

            <button className={`admin-tab${activeTab === "camps" ? " active" : ""}`} onClick={() => setActiveTab("camps")}>🏕️ Camp Requests{camps && <span className="admin-tab-count">{camps.length}</span>}</button>

            <button className={`admin-tab${activeTab === "bloodBanks" ? " active" : ""}`} onClick={() => setActiveTab("bloodBanks")}>🏥 Blood Banks{bloodBanks && <span className="admin-tab-count">{bloodBanks.length}</span>}</button>

            <button className="admin-refresh-btn" onClick={handleRefresh}>↺ Refresh</button>

          </div>}



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

                                  <button className="admin-action-btn confirm" onClick={() => handleDonorApproval(row.id, "confirmed")}>Approve for blood draw</button>

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

                                  <div className="admin-detail-field"><span className="admin-detail-label">Staff Decision</span><span className="admin-detail-value">{row.staff_deferral_summary || "—"}</span></div>

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
                        <span className={`admin-badge-status ${row.status_key || normalizeAppointmentStatus(row.status)}`}>
                          {row.status_label || getAppointmentStatusLabel(row.status)}
                        </span>
                      </td>
                      <td className="admin-td-actions">
                        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                          {(row.status_key || normalizeAppointmentStatus(row.status)) === 'pending' && (
                            <>
                              <button 
                                className="admin-action-btn accept" 
                                onClick={() => openAppointmentModal(row)}
                                title="Accept appointment"
                              >
                                Accept
                              </button>
                              <button 
                                className="admin-action-btn reject" 
                                onClick={() => openRejectAppointmentModal(row)}
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
                      <td>{row.organization_name || 'N/A'}</td>
                      <td>{row.preferred_date || 'N/A'}</td>
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
                                onClick={() => openCampModal(row)}
                                title="Review and accept camp request"
                              >
                                Accept
                              </button>
                              <button 
                                className="admin-action-btn reject" 
                                onClick={() => {
                                  setConfirmationModal({
                                    isOpen: true,
                                    title: 'Reject Camp Request',
                                    message: `Are you sure you want to reject the camp request from ${row.organization_name}? This action cannot be undone.`,
                                    onConfirm: () => handleRejectCamp(row),
                                    type: 'danger',
                                    isLoading: false
                                  });
                                }}
                                title="Reject camp request"
                              >
                                Reject
                              </button>
                            </>
                          )}
                          <button 
                            className="admin-action-btn view" 
                            onClick={() => openCampModal(row)}
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



          {activeTab === "bloodBanks" && (

            <div className="admin-bb-wrap">

              <div className="admin-bb-form" ref={bankFormRef}>

                <h3>Add Blood Bank</h3>

                <div className="admin-bb-grid">

                  <input value={bankForm.name} onChange={(e) => setBankForm((prev) => ({ ...prev, name: e.target.value }))} placeholder="Name *" />

                  <input value={bankForm.hospital} onChange={(e) => setBankForm((prev) => ({ ...prev, hospital: e.target.value }))} placeholder="Hospital" />

                  <input value={bankForm.dzongkhag} onChange={(e) => setBankForm((prev) => ({ ...prev, dzongkhag: e.target.value }))} placeholder="Dzongkhag *" />

                  <input value={bankForm.address} onChange={(e) => setBankForm((prev) => ({ ...prev, address: e.target.value }))} placeholder="Address *" />

                  <input 
                    type="tel"
                    value={bankForm.phone} 
                    onChange={(e) => {
                      let val = e.target.value.replace(/\D/g, '');
                      setBankForm((prev) => ({ ...prev, phone: val }));
                    }} 
                    placeholder="Phone * (8 digits, starts with 16, 17, or 77)" 
                    maxLength="8"
                  />

                  <input 
                    type="tel"
                    value={bankForm.emergencyPhone} 
                    onChange={(e) => {
                      let val = e.target.value.replace(/\D/g, '');
                      setBankForm((prev) => ({ ...prev, emergencyPhone: val }));
                    }} 
                    placeholder="Emergency phone (8 digits, starts with 16, 17, or 77)"
                    maxLength="8"
                  />

                  <input value={bankForm.email} onChange={(e) => setBankForm((prev) => ({ ...prev, email: e.target.value }))} placeholder="Email" />

                  <input value={bankForm.hours} onChange={(e) => setBankForm((prev) => ({ ...prev, hours: e.target.value }))} placeholder="Mon-Fri: 9:00 AM - 5:00 PM" />

                  <input value={bankForm.emergency} onChange={(e) => setBankForm((prev) => ({ ...prev, emergency: e.target.value }))} placeholder="Emergency on call" />

                  <input value={bankForm.latitude} onChange={(e) => setBankForm((prev) => ({ ...prev, latitude: e.target.value }))} placeholder="Latitude" />

                  <input value={bankForm.longitude} onChange={(e) => setBankForm((prev) => ({ ...prev, longitude: e.target.value }))} placeholder="Longitude" />

                  <input value={bankForm.servicesText} onChange={(e) => setBankForm((prev) => ({ ...prev, servicesText: e.target.value }))} placeholder="Blood Donation, Testing" />

                  <div className="admin-bb-inventory" style={{ gridColumn: '1 / -1', marginTop: 8 }}>
                    <label>🩸 Blood Inventory</label>
                    <div className="admin-inventory-components">
                      {['Whole Blood', 'PRBC', 'Platelets', 'Plasma'].map((component) => (
                        <div key={component} className="admin-inventory-component">
                          <h5>{component}</h5>
                          <table className="admin-inventory-table">
                            <thead>
                              <tr>
                                <th>Blood Type</th>
                                <th>Current Units</th>
                                <th>Min Threshold</th>
                                <th>Status</th>
                              </tr>
                            </thead>
                            <tbody>
                              {BLOOD_GROUP_OPTIONS.map((bt) => {
                                const row = bankForm.inventory && bankForm.inventory[component] && bankForm.inventory[component][bt]
                                  ? bankForm.inventory[component][bt]
                                  : { units: 0, min: 5 };
                                const units = Number(row.units || 0);
                                const min = Number(row.min || 0);
                                const status = units < min ? 'LOW 🔴' : (units >= Math.ceil(min * 1.5) ? 'Healthy' : 'Watch');
                                return (
                                  <tr key={bt}>
                                    <td>{bt}</td>
                                    <td>
                                      <input
                                        type="number"
                                        min="0"
                                        value={units}
                                        onChange={(e) => updateInventoryField(component, bt, 'units', Number(e.target.value || 0))}
                                      />
                                    </td>
                                    <td>
                                      <input
                                        type="number"
                                        min="0"
                                        value={min}
                                        onChange={(e) => updateInventoryField(component, bt, 'min', Number(e.target.value || 0))}
                                      />
                                    </td>
                                    <td className={`admin-inventory-status ${status.startsWith('LOW') ? 'low' : (status === 'Healthy' ? 'healthy' : 'watch')}`}>
                                      {status}
                                    </td>
                                  </tr>
                                );
                              })}
                            </tbody>
                          </table>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="admin-bb-types">

                    <label>Blood groups</label>

                    <div className="admin-bb-type-list">

                      {BANK_TYPES_FOR_FORM.map((type) => {
                        const selected = bankForm.types.includes(type);
                        return (
                          <button
                            key={type}
                            type="button"
                            className={`admin-bb-type-pill${selected ? " selected" : ""}`}
                            onClick={() => setBankForm((prev) => ({
                              ...prev,
                              types: prev.types.includes(type)
                                ? prev.types.filter((v) => v !== type)
                                : [...prev.types, type],
                            }))}
                          >
                            {type}
                          </button>
                        );
                      })}

                    </div>

                  </div>

                  <select value={bankForm.status} onChange={(e) => setBankForm((prev) => ({ ...prev, status: e.target.value }))}>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>

                  <select value={bankForm.availabilityStatus} onChange={(e) => setBankForm((prev) => ({ ...prev, availabilityStatus: e.target.value }))}>
                    <option value="open">Open</option>
                    <option value="limited">Limited</option>
                  </select>

                </div>

                <div className="admin-bb-actions">

                  <button type="button" className="admin-action-btn confirm" onClick={() => saveBloodBank()}>
                    Save Blood Bank
                  </button>

                  <button type="button" className="admin-action-btn view" onClick={openMapPicker}>
                    Open Map Picker
                  </button>

                  <button type="button" className="admin-action-btn" onClick={resetBankForm}>
                    Reset
                  </button>

                </div>

                {bankMessage ? <div className="admin-bb-message">{bankMessage}</div> : null}

              </div>



              <div className="admin-banks-list-head">

                <input
                  className="admin-banks-search"
                  placeholder="Search by name or dzongkhag"
                  value={bloodBankSearch}
                  onChange={(e) => {
                    setBloodBankSearch(e.target.value);
                    setBloodBankPage(1);
                  }}
                />

                <label className="admin-banks-check">
                  <input
                    type="checkbox"
                    checked={includeInactiveBanks}
                    onChange={(e) => {
                      setIncludeInactiveBanks(e.target.checked);
                      setBloodBankPage(1);
                    }}
                  />
                  Include inactive blood banks
                </label>

              </div>



              <div className="admin-table-wrap">
                {loadingBloodBanks ? (
                  <div className="admin-table-msg">Loading blood banks...</div>
                ) : errorBloodBanks && bankRows.length === 0 ? (
                  <div className="admin-table-msg error">{errorBloodBanks}</div>
                ) : (
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Dzongkhag</th>
                        <th>Inventory</th>
                        <th>Phone</th>
                        <th>Coordinates</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {bankRows.map((bank) => {
                        const status = String(bank.status || "active").toLowerCase();
                        const isActive = status === "active";
                        const coordText = bank.latitude != null && bank.longitude != null ? `${bank.latitude}, ${bank.longitude}` : "Not set";
                        const inventoryTotal = getInventoryTotalUnits(bank.inventory);
                        return (
                          <tr key={bank.id} className={archiveTarget === bank.id ? "admin-row-archived" : ""}>
                            <td className="admin-td-id">{bank.id}</td>
                            <td className="admin-td-name">{bank.name}</td>
                            <td>{bank.dzongkhag || "—"}</td>
                            <td>{inventoryTotal} units</td>
                            <td>{bank.phone || "—"}</td>
                            <td>{coordText}</td>
                            <td>
                              <span className={`admin-status-badge ${isActive ? "admin-status-confirmed" : "admin-status-rejected"}`}>
                                {isActive ? "Active" : "Inactive"}
                              </span>
                            </td>
                            <td className="admin-td-actions">
                              <button type="button" className="admin-action-btn edit" onClick={() => editBankFromRow(bank)}>Edit</button>
                              <button type="button" className="admin-action-btn view" onClick={() => setBankLocation(bank)}>Set Location</button>
                              <button type="button" className="admin-action-btn reject" onClick={() => archiveBloodBank(bank)}>Archive</button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                )}
              </div>



              <div className="admin-pagination">
                <button
                  type="button"
                  className="admin-action-btn"
                  disabled={bloodBankPagination.page <= 1 || loadingBloodBanks}
                  onClick={() => setBloodBankPage((prev) => Math.max(1, prev - 1))}
                >
                  Previous
                </button>
                <span>
                  Page {bloodBankPagination.page || 1} of {bloodBankPagination.totalPages || 1}
                </span>
                <button
                  type="button"
                  className="admin-action-btn"
                  disabled={(bloodBankPagination.page || 1) >= (bloodBankPagination.totalPages || 1) || loadingBloodBanks}
                  onClick={() => setBloodBankPage((prev) => Math.min(bloodBankPagination.totalPages || 1, prev + 1))}
                >
                  Next
                </button>
              </div>

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
        onAppointmentAction={handleAppointmentDetailsAction}
        onClose={() => setDetailsModal({ isOpen: false, type: null, data: null })}
      />

      {/* Accept Appointment Modal */}
      {appointmentModal.open && (
        <div className="admin-modal-backdrop" onClick={closeAppointmentModal}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '520px' }}>
            <div className="admin-modal-header">
              <h2>Save & Accept Appointment</h2>
              <button className="admin-modal-close" onClick={closeAppointmentModal}>×</button>
            </div>
            <div className="admin-modal-body">
              <div className="modal-form-group">
                <label htmlFor="appointment-date">Preferred Date</label>
                <input
                  id="appointment-date"
                  type="date"
                  className="admin-input"
                  value={appointmentModal.preferred_date}
                  onChange={(e) => setAppointmentModal({ ...appointmentModal, preferred_date: e.target.value })}
                />
              </div>
              <div className="modal-form-group">
                <label htmlFor="appointment-time">Preferred Time</label>
                <input
                  id="appointment-time"
                  type="time"
                  className="admin-input"
                  value={appointmentModal.preferred_time}
                  onChange={(e) => setAppointmentModal({ ...appointmentModal, preferred_time: e.target.value })}
                />
              </div>
              <div className="modal-form-group">
                <label htmlFor="appointment-bank">Blood Bank (optional)</label>
                <select
                  id="appointment-bank"
                  className="admin-select"
                  value={appointmentModal.blood_bank}
                  onChange={(e) => setAppointmentModal({ ...appointmentModal, blood_bank: e.target.value })}
                >
                  <option value="">Select blood bank</option>
                  {appointmentModal.blood_bank && Array.isArray(bloodBanks) && !bloodBanks.some((bank) => bank.name === appointmentModal.blood_bank) && (
                    <option value={appointmentModal.blood_bank}>Current: {appointmentModal.blood_bank}</option>
                  )}
                  {Array.isArray(bloodBanks) && bloodBanks.map((bank) => (
                    <option key={bank.id} value={bank.name}>{bank.name}</option>
                  ))}
                </select>
              </div>
              <div className="modal-form-group">
                <label htmlFor="appointment-notes">Notes (optional)</label>
                <textarea
                  id="appointment-notes"
                  className="admin-textarea"
                  rows={4}
                  value={appointmentModal.notes}
                  onChange={(e) => setAppointmentModal({ ...appointmentModal, notes: e.target.value })}
                  placeholder="Any special instructions or donor notes"
                />
              </div>
            </div>
            <div className="admin-modal-footer">
              <button className="btn-cancel" onClick={closeAppointmentModal} disabled={savingAppointment}>Cancel</button>
              <button className="btn-submit" onClick={handleSaveAndAcceptAppointment} disabled={savingAppointment}>
                {savingAppointment ? 'Saving...' : 'Save & Accept'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Appointment Modal */}
      {rejectAppointmentModal.open && (
        <div className="admin-modal-backdrop" onClick={closeRejectAppointmentModal}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '480px' }}>
            <div className="admin-modal-header">
              <h2>Reject Appointment</h2>
              <button className="admin-modal-close" onClick={closeRejectAppointmentModal}>×</button>
            </div>
            <div className="admin-modal-body">
              <p>Are you sure you want to reject the appointment for <strong>{rejectAppointmentModal.appointment?.full_name}</strong>?</p>
              <p>This will update the appointment status to rejected and notify the donor.</p>
            </div>
            <div className="admin-modal-footer">
              <button className="btn-cancel" onClick={closeRejectAppointmentModal} disabled={savingAppointment}>Cancel</button>
              <button className="btn-submit" onClick={handleConfirmRejectAppointment} disabled={savingAppointment}>
                {savingAppointment ? 'Processing...' : 'Confirm Reject'}
              </button>
            </div>
          </div>
        </div>
      )}

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

                      value="3"

                      checked={deferTemporaryModal.months === 3}

                      onChange={() => handleTemporaryDeferralMonthsChange(3)}

                    />

                    <span>3 months</span>

                  </label>

                  <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>

                    <input

                      type="radio"

                      name="deferPeriod"

                      value="6"

                      checked={deferTemporaryModal.months === 6}

                      onChange={() => handleTemporaryDeferralMonthsChange(6)}

                    />

                    <span>6 months</span>

                  </label>

                  <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>

                    <input

                      type="radio"

                      name="deferPeriod"

                      value="12"

                      checked={deferTemporaryModal.months === 12}

                      onChange={() => handleTemporaryDeferralMonthsChange(12)}

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

      {/* Camp Request Modal */}
      <CampRequestModal 
        isOpen={campModal.open}
        onClose={closeCampModal}
        camp={campModal.camp}
        onAccept={handleAcceptCamp}
        onReject={handleRejectCamp}
        isLoading={campModal.isLoading}
        onRefresh={refreshCampModal}
        onSave={handleSaveCampChanges}
      />

      {/* Confirmation Modal */}
      <ConfirmationModal
        isOpen={confirmationModal.isOpen}
        title={confirmationModal.title}
        message={confirmationModal.message}
        onConfirm={confirmationModal.onConfirm}
        onCancel={() => setConfirmationModal({ isOpen: false, title: '', message: '', onConfirm: null, type: 'warning', isLoading: false })}
        confirmText="Reject"
        cancelText="Cancel"
        type={confirmationModal.type}
        isLoading={confirmationModal.isLoading}
      />
      </div>
    </AdminShell>
  );
}