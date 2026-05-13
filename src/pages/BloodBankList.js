import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Link } from "react-router-dom";
import "./BloodBankList.css";

const API_URL = process.env.REACT_APP_BLOOD_BANKS_API_URL || "";
const RAW_GOOGLE_MAPS_API_KEY = process.env.REACT_APP_GOOGLE_MAPS_API_KEY || process.env.REACT_APP_GOOGLE_MAPS_KEY || "";
const GOOGLE_MAPS_API_KEY = String(RAW_GOOGLE_MAPS_API_KEY).replace(/^['"]|['"]$/g, "").trim();
const HAS_GOOGLE_MAPS_KEY = Boolean(
  GOOGLE_MAPS_API_KEY &&
    !/^YOUR_GOOGLE_MAPS_API_KEY$/i.test(GOOGLE_MAPS_API_KEY) &&
    !/^PASTE_YOUR_REAL_KEY_HERE$/i.test(GOOGLE_MAPS_API_KEY)
);
const BLOOD_TYPES = ["", "A+", "A-", "B+", "B-", "O+", "O-", "AB+", "AB-"];
const PAGE_SIZE = 8;

const FALLBACK_BANKS = [
  {
    id: 1,
    name: "National Blood Bank",
    hospital: "Jigme Dorji Wangchuck National Referral Hospital",
    dzongkhag: "Thimphu",
    address: "Gongphel Lam, Thimphu",
    phone: "02-322496",
    hours: "Mon-Sat: 9:00 AM - 5:00 PM",
    emergency_phone: "02-322496",
    services: ["Blood Donation", "Screening", "Cross-match"],
    inventory: { "A+": 8, "B+": 6, "O+": 10, "AB+": 4, "O-": 2 },
    latitude: 27.4727924,
    longitude: 89.6392862,
    is_open_now: true,
  },
  {
    id: 2,
    name: "Phuentsholing Blood Bank",
    hospital: "Phuentsholing General Hospital",
    dzongkhag: "Chukha",
    address: "Hospital Road, Phuentsholing",
    phone: "05-252431",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "05-252431",
    services: ["Blood Donation", "Emergency Issue"],
    inventory: { "A+": 4, "O+": 5, "B+": 2 },
    latitude: 26.8588058,
    longitude: 89.3886278,
    is_open_now: true,
  },
  {
    id: 3,
    name: "Mongar Blood Bank",
    hospital: "Mongar Regional Referral Hospital",
    dzongkhag: "Mongar",
    address: "Mongar Town",
    phone: "04-641114",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "04-641114",
    services: ["Blood Donation", "Testing"],
    inventory: { "A+": 3, "B+": 3, "O+": 4 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 4,
    name: "Paro District Blood Bank",
    hospital: "Paro General Hospital",
    dzongkhag: "Paro",
    address: "Paro Town",
    phone: "08-271116",
    hours: "Mon-Fri: 8:00 AM - 4:00 PM",
    emergency_phone: "08-271116",
    services: ["Blood Donation", "Emergency Issue"],
    inventory: { "A+": 2, "O+": 3 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 5,
    name: "Punakha District Blood Bank",
    hospital: "Punakha District Hospital",
    dzongkhag: "Punakha",
    address: "Punakha Town",
    phone: "02-581116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "02-581116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 2 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 6,
    name: "Wangdue Blood Bank",
    hospital: "Wangdue District Hospital",
    dzongkhag: "Wangdue Phodrang",
    address: "Wangdue Phodrang Town",
    phone: "02-481116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "02-481116",
    services: ["Blood Donation", "Testing"],
    inventory: { "A+": 2, "B+": 1, "O+": 2 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 7,
    name: "Trongsa Blood Bank",
    hospital: "Trongsa District Hospital",
    dzongkhag: "Trongsa",
    address: "Trongsa Town",
    phone: "03-521116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "03-521116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 8,
    name: "Bumthang Blood Bank",
    hospital: "Bumthang District Hospital",
    dzongkhag: "Bumthang",
    address: "Jakar, Bumthang",
    phone: "03-631116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "03-631116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 2 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 9,
    name: "Trashigang Blood Bank",
    hospital: "Trashigang Regional Referral Hospital",
    dzongkhag: "Trashigang",
    address: "Trashigang Town",
    phone: "04-721116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "04-721116",
    services: ["Blood Donation", "Emergency Issue"],
    inventory: { "A+": 2, "B+": 2, "O+": 3 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 10,
    name: "Trashiyangtse District Blood Bank",
    hospital: "Trashiyangtse District Hospital",
    dzongkhag: "Trashiyangtse",
    address: "Trashiyangtse Town",
    phone: "04-951116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "04-951116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "B+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 11,
    name: "Lhuntse Blood Bank",
    hospital: "Lhuntse District Hospital",
    dzongkhag: "Lhuntse",
    address: "Lhuntse Town",
    phone: "04-331116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "04-331116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 12,
    name: "Samdrup Jongkhar Blood Bank",
    hospital: "Samdrup Jongkhar District Hospital",
    dzongkhag: "Samdrup Jongkhar",
    address: "Samdrup Jongkhar Town",
    phone: "07-251116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "07-251116",
    services: ["Blood Donation", "Testing"],
    inventory: { "A+": 2, "B+": 1, "O+": 2 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 13,
    name: "Gelephu Blood Bank",
    hospital: "Gelephu Regional Referral Hospital",
    dzongkhag: "Sarpang",
    address: "Gelephu Town, Sarpang",
    phone: "06-251116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "06-251116",
    services: ["Blood Donation", "Emergency Issue"],
    inventory: { "A+": 2, "B+": 2, "O+": 3 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 14,
    name: "Dagana District Blood Bank",
    hospital: "Dagana District Hospital",
    dzongkhag: "Dagana",
    address: "Dagana Town",
    phone: "05-351116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "05-351116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 15,
    name: "Zhemgang District Blood Bank",
    hospital: "Zhemgang District Hospital",
    dzongkhag: "Zhemgang",
    address: "Zhemgang Town",
    phone: "06-351116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "06-351116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "B+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 16,
    name: "Tsirang District Blood Bank",
    hospital: "Tsirang District Hospital",
    dzongkhag: "Tsirang",
    address: "Tsirang Town",
    phone: "06-151116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "06-151116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 17,
    name: "Lingkortakha District Blood Bank",
    hospital: "Lingkortakha District Hospital",
    dzongkhag: "Lingkortakha",
    address: "Lingkortakha Town",
    phone: "03-411116",
    hours: "Mon-Fri: 9:00 AM - 3:00 PM",
    emergency_phone: "03-411116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 18,
    name: "Pemagatshel District Blood Bank",
    hospital: "Pemagatshel District Hospital",
    dzongkhag: "Pemagatshel",
    address: "Pemagatshel Town",
    phone: "07-531116",
    hours: "Mon-Fri: 9:00 AM - 4:00 PM",
    emergency_phone: "07-531116",
    services: ["Blood Donation", "Testing"],
    inventory: { "A+": 1, "B+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 19,
    name: "Gasa District Blood Bank",
    hospital: "Gasa District Hospital",
    dzongkhag: "Gasa",
    address: "Gasa Town",
    phone: "02-681116",
    hours: "Mon-Fri: 10:00 AM - 3:00 PM",
    emergency_phone: "02-681116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
  {
    id: 20,
    name: "Haa District Blood Bank",
    hospital: "Haa District Hospital",
    dzongkhag: "Haa",
    address: "Haa Town",
    phone: "02-841116",
    hours: "Mon-Fri: 9:00 AM - 3:30 PM",
    emergency_phone: "02-841116",
    services: ["Blood Donation"],
    inventory: { "A+": 1, "B+": 1, "O+": 1 },
    latitude: null,
    longitude: null,
    is_open_now: true,
  },
];

const bloodTypeClass = {
  "A+": "type-ap",
  "A-": "type-an",
  "B+": "type-bp",
  "B-": "type-bn",
  "O+": "type-op",
  "O-": "type-on",
  "AB+": "type-abp",
  "AB-": "type-abn",
};

function formatHours(hours, hoursJson) {
  if (!hoursJson || typeof hoursJson !== "object" || Array.isArray(hoursJson)) {
    return hours || "Hours not available";
  }

  const orderedDays = ["mon", "tue", "wed", "thu", "fri", "sat", "sun"];
  const labels = {
    mon: "Mon",
    tue: "Tue",
    wed: "Wed",
    thu: "Thu",
    fri: "Fri",
    sat: "Sat",
    sun: "Sun",
  };

  const segments = [];
  orderedDays.forEach((day) => {
    const d = hoursJson[day];
    if (!d || d.open === false || !d.start || !d.end) return;
    segments.push(`${labels[day]} ${d.start}-${d.end}`);
  });

  return segments.length > 0 ? segments.join(" | ") : (hours || "Hours not available");
}

function mapsDirectionsUrl(bank) {
  if (bank.latitude != null && bank.longitude != null) {
    return `https://www.google.com/maps/dir/?api=1&destination=${bank.latitude},${bank.longitude}`;
  }
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${bank.name} ${bank.address}`)}`;
}

function loadGoogleMaps(apiKey) {
  if (window.google?.maps) return Promise.resolve(window.google.maps);
  if (!apiKey) return Promise.reject(new Error("Google Maps API key missing."));

  const existing = document.getElementById("google-maps-script");
  if (existing) {
    return new Promise((resolve, reject) => {
      existing.addEventListener("load", () => resolve(window.google?.maps));
      existing.addEventListener("error", () => reject(new Error("Failed to load Google Maps.")));
    });
  }

  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.id = "google-maps-script";
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}`;
    script.async = true;
    script.defer = true;
    script.onload = () => resolve(window.google?.maps);
    script.onerror = () => reject(new Error("Failed to load Google Maps."));
    document.body.appendChild(script);
  });
}

function parseJsonFromPossiblyNoisyBody(rawBody) {
  const normalizedBody = String(rawBody || "").replace(/^\uFEFF/, "").trim();
  if (!normalizedBody) return {};

  try {
    return JSON.parse(normalizedBody);
  } catch {
    const firstBrace = normalizedBody.indexOf("{");
    const lastBrace = normalizedBody.lastIndexOf("}");
    if (firstBrace !== -1 && lastBrace > firstBrace) {
      const candidate = normalizedBody.slice(firstBrace, lastBrace + 1);
      return JSON.parse(candidate);
    }
    throw new Error("Server returned a non-JSON response.");
  }
}

function getCandidateApiUrls() {
  const urls = new Set();
  if (API_URL) {
    urls.add(API_URL);
  }

  urls.add("http://localhost/blood_donation/api/blood-banks.php");
  urls.add("http://localhost/blood_donation/api/get_blood_banks.php");
  return Array.from(urls);
}

function applyClientFilters(list, search, selectedDzongkhag, selectedType, openNow) {
  const q = search.trim().toLowerCase();
  return list.filter((bank) => {
    const matchesSearch =
      !q ||
      String(bank.name || "").toLowerCase().includes(q) ||
      String(bank.address || "").toLowerCase().includes(q) ||
      String(bank.dzongkhag || "").toLowerCase().includes(q);

    const matchesDzongkhag = !selectedDzongkhag || bank.dzongkhag === selectedDzongkhag;
    const matchesType = !selectedType || Number((bank.inventory || {})[selectedType] || 0) > 0;
    const matchesOpenNow = !openNow || Boolean(bank.is_open_now);

    return matchesSearch && matchesDzongkhag && matchesType && matchesOpenNow;
  });
}

export default function BloodBanks() {
  const [banks, setBanks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [search, setSearch] = useState("");
  const [selectedDzongkhag, setSelectedDzongkhag] = useState("");
  const [selectedType, setSelectedType] = useState("");
  const [openNow, setOpenNow] = useState(false);
  const [viewMode] = useState("list");
  const [page, setPage] = useState(1);

  const [mapError, setMapError] = useState("");
  const mapContainerRef = useRef(null);
  const mapRef = useRef(null);
  const markersRef = useRef([]);
  const infoWindowRef = useRef(null);

  const dzongkhags = useMemo(() => {
    const all = banks.map((b) => b.dzongkhag).filter(Boolean);
    return Array.from(new Set(all)).sort((a, b) => a.localeCompare(b));
  }, [banks]);

  const fetchBanks = useCallback(async (signal) => {
    // Don't attempt fetch if signal is already aborted
    if (signal?.aborted) return;

    setError("");
    const params = new URLSearchParams();
    if (search.trim()) params.set("q", search.trim());
    if (selectedDzongkhag) params.set("dzongkhag", selectedDzongkhag);
    if (selectedType) params.set("blood_type", selectedType);
    if (openNow) params.set("open_now", "1");

    let lastError = null;
    for (const baseUrl of getCandidateApiUrls()) {
      // Check if signal was aborted during loop
      if (signal?.aborted) break;

      try {
        const endpoint = `${baseUrl}${baseUrl.includes("?") ? "&" : "?"}${params.toString()}`;
        const res = await fetch(endpoint, { signal });
        const rawBody = await res.text();
        const data = parseJsonFromPossiblyNoisyBody(rawBody);

        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Could not load blood banks.");
        }

        let list = Array.isArray(data.data) ? data.data : [];

        // Recovery path: if server-side filtering returns no rows, refetch once
        // without filters and apply client-side filtering to avoid false-empty states.
        if (list.length === 0 && (search.trim() || selectedDzongkhag || selectedType || openNow)) {
          const retryParams = new URLSearchParams();
          const retryEndpoint = `${baseUrl}${baseUrl.includes("?") ? "&" : "?"}${retryParams.toString()}`;
          const retryRes = await fetch(retryEndpoint, { signal });
          const retryRawBody = await retryRes.text();
          const retryData = parseJsonFromPossiblyNoisyBody(retryRawBody);

          if (retryRes.ok && retryData?.success) {
            const retryList = Array.isArray(retryData.data) ? retryData.data : [];
            const recovered = applyClientFilters(retryList, search, selectedDzongkhag, selectedType, openNow);
            if (recovered.length > 0) {
              list = recovered;
            }
          }
        }

        if (list.length === 0 && !search.trim() && !selectedDzongkhag && !selectedType && !openNow) {
          const backupAll = applyClientFilters(FALLBACK_BANKS, search, selectedDzongkhag, selectedType, openNow);
          setBanks(backupAll);
          setPage((prev) => {
            const totalPages = Math.max(1, Math.ceil(backupAll.length / PAGE_SIZE));
            return Math.min(prev, totalPages);
          });
          setError("Live blood bank API returned an empty directory. Showing backup directory data.");
          return;
        }

        setBanks(list);
        setPage((prev) => {
          const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
          return Math.min(prev, totalPages);
        });
        return;
      } catch (err) {
        // Don't capture AbortError as a network failure
        if (err?.name === "AbortError") break;
        lastError = err;
      }
    }

    // Only show error if not aborted
    if (signal?.aborted) return;

    const fallback = applyClientFilters(FALLBACK_BANKS, search, selectedDzongkhag, selectedType, openNow);
    setBanks(fallback);
    setPage(1);
    setError(
      `Could not reach live blood bank API. Showing backup directory data.${
        lastError?.message ? ` ${lastError.message}` : ""
      }`
    );
  }, [search, selectedDzongkhag, selectedType, openNow]);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);

    fetchBanks(controller.signal)
      .catch((err) => {
        if (err.name !== "AbortError") setError(err.message || "Could not load blood banks.");
      })
      .finally(() => setLoading(false));

    const poll = setInterval(() => {
      fetchBanks(controller.signal).catch(() => {});
    }, 30000);

    return () => {
      controller.abort();
      clearInterval(poll);
    };
  }, [fetchBanks]);

  useEffect(() => {
    setPage(1);
  }, [search, selectedDzongkhag, selectedType, openNow]);

  const totalPages = Math.max(1, Math.ceil(banks.length / PAGE_SIZE));
  const pagedBanks = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return banks.slice(start, start + PAGE_SIZE);
  }, [banks, page]);

  useEffect(() => {
    if (viewMode !== "map") return;
    if (!mapContainerRef.current) return;
    if (!HAS_GOOGLE_MAPS_KEY) {
      setMapError("");
      return;
    }

    let cancelled = false;

    const renderMap = async () => {
      try {
        setMapError("");
        const maps = await loadGoogleMaps(GOOGLE_MAPS_API_KEY);
        if (cancelled || !maps) return;

        const first = banks.find((b) => b.latitude != null && b.longitude != null);
        const center = first
          ? { lat: Number(first.latitude), lng: Number(first.longitude) }
          : { lat: 27.5142, lng: 90.4336 };

        if (!mapRef.current) {
          mapRef.current = new maps.Map(mapContainerRef.current, {
            center,
            zoom: first ? 9 : 7,
            mapTypeControl: false,
            fullscreenControl: false,
            streetViewControl: false,
          });
          infoWindowRef.current = new maps.InfoWindow();
        } else {
          mapRef.current.setCenter(center);
        }

        markersRef.current.forEach((marker) => marker.setMap(null));
        markersRef.current = [];

        const bounds = new maps.LatLngBounds();
        let markerCount = 0;

        banks.forEach((bank) => {
          if (bank.latitude == null || bank.longitude == null) return;
          const position = { lat: Number(bank.latitude), lng: Number(bank.longitude) };
          const marker = new maps.Marker({
            position,
            map: mapRef.current,
            title: bank.name,
          });

          marker.addListener("click", () => {
            const appointmentUrl = `/donating-blood?tab=book&bank=${encodeURIComponent(bank.name)}`;
            const html = `
              <div class="bb-map-popup">
                <h4>${bank.name}</h4>
                <p>${bank.address}</p>
                <p>Phone: ${bank.phone || "N/A"}</p>
                <div class="bb-map-popup-links">
                  <a href="tel:${(bank.phone || "").replace(/\s+/g, "")}" target="_self">Call</a>
                  <a href="${mapsDirectionsUrl(bank)}" target="_blank" rel="noreferrer">Directions</a>
                  <a href="${appointmentUrl}" target="_self">Book</a>
                </div>
              </div>
            `;
            infoWindowRef.current.setContent(html);
            infoWindowRef.current.open({ map: mapRef.current, anchor: marker });
          });

          markersRef.current.push(marker);
          bounds.extend(position);
          markerCount += 1;
        });

        if (markerCount > 1) {
          mapRef.current.fitBounds(bounds, 50);
        } else if (markerCount === 1) {
          mapRef.current.setZoom(11);
        }
      } catch (err) {
        if (!cancelled) {
          setMapError(err.message || "Unable to load Google Map.");
        }
      }
    };

    renderMap();

    return () => {
      cancelled = true;
    };
  }, [viewMode, banks]);

  const clearFilters = () => {
    setSearch("");
    setSelectedDzongkhag("");
    setSelectedType("");
    setOpenNow(false);
  };

  return (
    <div className="bb-page">
      <div className="bb-hero">
        <div className="bb-hero-content">
          <nav className="bb-breadcrumb">
            <Link className="bb-breadcrumb-link" to="/">Home</Link> / Blood Banks
          </nav>
          <h1 className="bb-hero-title">Blood Bank Directory</h1>
          <p className="bb-hero-sub">Live inventory and quick actions for every blood bank.</p>
        </div>
      </div>

      <section className="bb-filter-bar">
        <div className="bb-filter-inner">
          <div className="bb-search-wrap">
            <span className="bb-search-icon">Search</span>
            <input
              className="bb-search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Name, address, or dzongkhag"
            />
          </div>

          <div className="bb-filter-group">
            <label>Dzongkhag</label>
            <select value={selectedDzongkhag} onChange={(e) => setSelectedDzongkhag(e.target.value)}>
              <option value="">All</option>
              {dzongkhags.map((d) => (
                <option key={d} value={d}>{d}</option>
              ))}
            </select>
          </div>

          <div className="bb-filter-group">
            <label>Blood Type</label>
            <select value={selectedType} onChange={(e) => setSelectedType(e.target.value)}>
              {BLOOD_TYPES.map((t) => (
                <option key={t || "all"} value={t}>{t || "All"}</option>
              ))}
            </select>
          </div>

          <label className="bb-open-toggle">
            <input type="checkbox" checked={openNow} onChange={(e) => setOpenNow(e.target.checked)} />
            Open now
          </label>
        </div>
      </section>

      <section className="bb-results-bar">
        <div className="bb-results-inner">
          <span>
            {loading ? "Loading..." : `Showing ${banks.length} blood banks`}
          </span>
          <button type="button" className="bb-reset" onClick={clearFilters}>Clear filters</button>
        </div>
      </section>

      {error && <div className="bb-alert error">{error}</div>}

      {viewMode === "map" ? (
        <section className="bb-map-wrap">
          {!HAS_GOOGLE_MAPS_KEY && (
            <div className="bb-alert warning">
              Set REACT_APP_GOOGLE_MAPS_API_KEY to enable Google Maps view.
            </div>
          )}
          {mapError && <div className="bb-alert error">{mapError}</div>}
          {HAS_GOOGLE_MAPS_KEY ? (
            <div ref={mapContainerRef} className="bb-map" />
          ) : (
            <div className="bb-empty">Map is unavailable without a Google Maps API key. You can still use Get Directions on each bank card.</div>
          )}
        </section>
      ) : (
        <section className="bb-grid-wrap">
          <div className="bb-grid">
            {!loading && pagedBanks.length === 0 && (
              <div className="bb-empty">No blood banks match the selected filters.</div>
            )}

            {pagedBanks.map((b) => {
              const inventory = b.inventory || {};
              const availableTypes = Object.entries(inventory)
                .filter(([, count]) => Number(count) > 0)
                .sort((a, b2) => a[0].localeCompare(b2[0]));

              const appointmentUrl = `/donating-blood?tab=book&bank=${encodeURIComponent(b.name)}`;

              return (
                <article className="bb-card" key={b.id}>
                  <div className="bb-card-top">
                    <div>
                      <div className="bb-card-dzong">{b.dzongkhag}</div>
                      <h3 className="bb-card-name">{b.name}</h3>
                      <p className="bb-card-hospital">{b.hospital}</p>
                    </div>
                    <span className={`bb-status ${b.is_open_now ? "open" : "closed"}`}>
                      {b.is_open_now ? "Open Now" : "Closed"}
                    </span>
                  </div>

                  <div className="bb-card-details">
                    <div className="bb-detail"><strong>Address:</strong> {b.address}</div>
                    <div className="bb-detail"><strong>Phone:</strong> {b.phone || "N/A"}</div>
                    <div className="bb-detail"><strong>Hours:</strong> {formatHours(b.hours, b.hours_json)}</div>
                    <div className="bb-detail"><strong>Emergency:</strong> {b.emergency_phone || b.emergency || "N/A"}</div>
                  </div>

                  <div className="bb-card-services">
                    {(Array.isArray(b.services) ? b.services : []).map((service) => (
                      <span className="bb-service-chip" key={service}>{service}</span>
                    ))}
                  </div>

                  <div className="bb-card-types">
                    <span className="bb-types-label">Available Blood Types</span>
                    <div className="bb-types">
                      {availableTypes.length === 0 ? (
                        <span className="bb-empty-inventory">No available units right now</span>
                      ) : (
                        availableTypes.map(([type, count]) => (
                          <span key={type} className={`bb-type ${bloodTypeClass[type] || ""}`}>
                            {type}: {count}
                          </span>
                        ))
                      )}
                    </div>
                  </div>

                  <div className="bb-actions">
                    <a className="bb-btn" href={`tel:${(b.phone || "").replace(/\s+/g, "")}`}>Call Now</a>
                    <a className="bb-btn secondary" href={mapsDirectionsUrl(b)} target="_blank" rel="noreferrer">Get Directions</a>
                    <Link className="bb-btn ghost" to={appointmentUrl}>Book Appointment</Link>
                  </div>
                </article>
              );
            })}
          </div>

          {banks.length > PAGE_SIZE && (
            <div className="bb-pagination">
              <button type="button" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1}>Previous</button>
              <span>Page {page} of {totalPages}</span>
              <button type="button" onClick={() => setPage((p) => Math.min(totalPages, p + 1))} disabled={page === totalPages}>Next</button>
            </div>
          )}
        </section>
      )}
    </div>
  );
}
