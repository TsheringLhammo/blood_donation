import React, { useState, useEffect } from "react";
/* eslint-disable no-unused-vars */
import { Link, useLocation, useNavigate } from "react-router-dom";
import "./Donatingblood.css";
import { getStoredUser, authFetch } from "../utils/auth";

const APPOINTMENT_API_URL = process.env.REACT_APP_APPOINTMENT_API_URL;
const BLOOD_BANKS_API_URL = process.env.REACT_APP_BLOOD_BANKS_API_URL;

const DEFAULT_APPOINTMENT_API_URLS = [
  "http://localhost:3000/api/book_appointment.php",
];

const DEFAULT_BLOOD_BANK_API_URLS = [
  "http://localhost/blood_donation/api/get_blood_banks.php",
  "http://localhost/blood_donation/api/blood-banks.php",
  "http://localhost/blood_donation/api/get_confirmed_blood_banks.php",
];

const CANONICAL_BLOOD_BANK_OPTIONS = [
  "National Blood Bank",
  "Phuentsholing Blood Bank",
  "Paro District Blood Bank",
  "Punakha District Blood Bank",
  "Gasa District Blood Bank",
  "Haa District Blood Bank",
  "Wangdue Blood Bank",
  "Trongsa Blood Bank",
  "Bumthang Blood Bank",
  "Mongar Blood Bank",
  "Trashigang Blood Bank",
  "Trashiyangtse District Blood Bank",
  "Lhuntse Blood Bank",
  "Samdrup Jongkhar Blood Bank",
  "Gelephu Blood Bank",
  "Dagana District Blood Bank",
  "Zhemgang District Blood Bank",
  "Tsirang District Blood Bank",
  "Lingkortakha District Blood Bank",
  "Pemagatshel District Blood Bank",
];

const getCandidateAppointmentApiUrls = () => {
  const uniqueUrls = new Set();
  if (APPOINTMENT_API_URL && APPOINTMENT_API_URL.trim()) {
    uniqueUrls.add(APPOINTMENT_API_URL.trim());
  }
  DEFAULT_APPOINTMENT_API_URLS.forEach((url) => uniqueUrls.add(url));
  return Array.from(uniqueUrls);
};

const getCandidateBloodBankApiUrls = () => {
  const uniqueUrls = new Set();
  if (BLOOD_BANKS_API_URL && BLOOD_BANKS_API_URL.trim()) {
    uniqueUrls.add(BLOOD_BANKS_API_URL.trim());
  }
  DEFAULT_BLOOD_BANK_API_URLS.forEach((url) => uniqueUrls.add(url));
  return Array.from(uniqueUrls);
};

const normalizePhoneInput = (value) => value.replace(/\D/g, "").slice(0, 8);
const isValidBhutanPhone = (value) => /^(16|17|77)\d{6}$/.test(value);

const parseJsonFromPossiblyNoisyBody = (rawBody) => {
  const normalizedBody = rawBody.replace(/^\uFEFF/, "").trim();
  if (!normalizedBody) return {};
  try {
    return JSON.parse(normalizedBody);
  } catch (error) {
    const firstBrace = normalizedBody.indexOf("{");
    const lastBrace = normalizedBody.lastIndexOf("}");
    if (firstBrace !== -1 && lastBrace > firstBrace) {
      const candidate = normalizedBody.slice(firstBrace, lastBrace + 1);
      return JSON.parse(candidate);
    }
    throw error;
  }
};

const tabs = [
  { id: "why", icon: "❤️", label: "Why Donate?" },
  { id: "eligibility", icon: "✅", label: "Am I Eligible?" },
  { id: "process", icon: "🩸", label: "The Process" },
  { id: "book", icon: "📅", label: "Book Appointment" },
  { id: "aftercare", icon: "🌿", label: "After Donation" },
  { id: "faq", icon: "💬", label: "FAQs" },
];

const resolveTabFromUrl = (search, hash) => {
  const params = new URLSearchParams(search || "");
  const rawTab = (params.get("tab") || (hash || "").replace("#", "")).trim().toLowerCase();
  return tabs.some((t) => t.id === rawTab) ? rawTab : "why";
};

const whyDonateCards = [
  { icon: "🏥", title: "Save Up to 3 Lives", desc: "One donation can save up to three lives. Your single act of kindness has a multiplying effect on the community." },
  { icon: "🔴", title: "Always in Demand", desc: "Blood cannot be manufactured. Every 2 seconds someone needs blood — only voluntary donors can meet this need." },
  { icon: "💪", title: "Health Benefits", desc: "Donating blood stimulates new blood cell production and may reduce the risk of cardiovascular disease." },
  { icon: "🇧🇹", title: "Serve Bhutan", desc: "Help maintain Bhutan's national blood supply and ensure no patient goes without blood during emergencies." },
];

const eligibilityItems = {
  can: [
    "Age 18–60 years old",
    "Weight at least 45 kg",
    "Haemoglobin ≥ 12.5 g/dL",
    "Feeling healthy and well on the day",
    "No donation in the past 3 months",
    "Blood pressure between 100/60 and 180/100",
  ],
  cannot: [
    "Currently pregnant or breastfeeding",
    "Had a tattoo or piercing in the last 6 months",
    "Suffered from cold, flu, or fever in the last 7 days",
    "Have HIV, Hepatitis B or C, or syphilis",
    "Recently travelled to a malaria-risk area",
    "Currently on antibiotics or blood-thinning medication",
  ],
};

const processSteps = [
  { num: "01", title: "Register", desc: "Fill in a short registration form with your personal details and basic health information at the blood bank.", time: "~5 min" },
  { num: "02", title: "Health Check", desc: "A nurse will check your blood pressure, pulse, temperature, and haemoglobin level to confirm you're eligible.", time: "~10 min" },
  { num: "03", title: "Donation", desc: "Relax in a comfortable chair while approximately 450 ml of blood is collected through a sterile needle.", time: "~10 min" },
  { num: "04", title: "Refreshments", desc: "Enjoy snacks and drinks provided by the blood bank. Rest for at least 15 minutes before leaving.", time: "~15 min" },
];

const aftercareItems = [
  { icon: "💧", tip: "Drink plenty of fluids — at least 4 extra glasses of water or juice today." },
  { icon: "🍽️", tip: "Eat iron-rich foods like red meat, leafy greens, beans, and fortified cereals." },
  { icon: "🚫", tip: "Avoid strenuous exercise, heavy lifting, or intense sport for 24 hours." },
  { icon: "🩹", tip: "Keep the bandage on for at least 4 hours. If bleeding occurs, apply pressure and raise your arm." },
  { icon: "🚬", tip: "Avoid smoking for at least 2 hours after donation." },
  { icon: "🍺", tip: "Do not consume alcohol for 24 hours after donation." },
];

const faqs = [
  { q: "Does donating blood hurt?", a: "You'll feel a quick pinch when the needle is inserted, but the donation itself is generally painless. Most donors describe it as a mild pressure." },
  { q: "How long does a donation take?", a: "The entire process — registration, health check, donation, and rest — takes about 45–60 minutes. The blood draw itself is only 8–10 minutes." },
  { q: "How often can I donate?", a: "Whole blood can be donated every 3 months (90 days). This gives your body enough time to replenish the donated blood." },
  { q: "Will I feel weak after donating?", a: "Most people feel completely fine. Some may feel slightly lightheaded. Resting and drinking fluids immediately after helps prevent this." },
  { q: "Is my blood tested after donation?", a: "Yes. All donated blood is screened for HIV, Hepatitis B & C, syphilis, and malaria before it is used." },
];

export default function DonatingBlood() {
  const navigate = useNavigate();
  const location = useLocation();
  const [isNavOpen, setIsNavOpen] = useState(false);
  const [activeTab, setActiveTab] = useState(() => resolveTabFromUrl(location.search, location.hash));
  const [openFaq, setOpenFaq] = useState(null);
  const [visible, setVisible] = useState(true);
  const [form, setForm] = useState({
    name: "", email: "", age: "", gender: "", bloodGroup: "", phone: "", date: "", time: "", bank: ""
  });
  const [submitted, setSubmitted] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [availableBanks, setAvailableBanks] = useState([]);
  const [popupMessage, setPopupMessage] = useState("");
  const [popupRequiresLogin, setPopupRequiresLogin] = useState(false);
  const [_donorProfile, setDonorProfile] = useState(null);
  const [isEligibleToBook, setIsEligibleToBook] = useState(false);
  const [bookingIneligibleMessage, setBookingIneligibleMessage] = useState("");

  useEffect(() => {
    setVisible(false);
    const t = setTimeout(() => setVisible(true), 50);
    return () => clearTimeout(t);
  }, [activeTab]);

  useEffect(() => {
    const tabFromUrl = resolveTabFromUrl(location.search, location.hash);
    setActiveTab((currentTab) => (currentTab === tabFromUrl ? currentTab : tabFromUrl));
  }, [location.search, location.hash]);

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 900) {
        setIsNavOpen(false);
      }
    };
    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  useEffect(() => {
    const loadBloodBanks = async () => {
      const mergedBanks = new Set();
      for (const apiUrl of getCandidateBloodBankApiUrls()) {
        try {
          const res = await fetch(apiUrl);
          const rawBody = await res.text();
          const data = parseJsonFromPossiblyNoisyBody(rawBody);
          if (!res.ok || !data?.success || !Array.isArray(data.data)) {
            continue;
          }
          const rows = data.data.filter((bank) => bank && typeof bank === "object");
          const eligibleBanks = rows
            .filter((bank) => {
              const status = String(bank.status || "").toLowerCase();
              return status === "" || status === "active" || status === "confirmed";
            })
            .map((bank) => (bank.name || bank.hospital || "").trim())
            .filter(Boolean);
          eligibleBanks.forEach((name) => mergedBanks.add(name));
        } catch {
          // Try next endpoint
        }
      }
      if (mergedBanks.size < 10) {
        CANONICAL_BLOOD_BANK_OPTIONS.forEach((name) => mergedBanks.add(name));
      }
      const finalBanks = Array.from(mergedBanks).sort((a, b) => a.localeCompare(b));
      if (finalBanks.length > 0) {
        setAvailableBanks(finalBanks);
      } else {
        setAvailableBanks([]);
      }
    };
    loadBloodBanks();
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(location.search || "");
    const bankParam = (params.get("bank") || "").trim();
    if (!bankParam) return;
    setForm((prev) => {
      if (prev.bank === bankParam) return prev;
      return { ...prev, bank: bankParam };
    });
    setActiveTab("book");
  }, [location.search]);

  // Fetch donor profile and prefill form + check eligibility
  useEffect(() => {
    const fetchDonorProfile = async () => {
      const currentUser = getStoredUser();
      if (!currentUser?.token) return;

      try {
        const res = await authFetch("get_donor_profile.php?_ts=" + Date.now(), {
          cache: "no-store",
        });
        const data = await res.json();
        if (data.success && data.data) {
          const profile = data.data;
          setDonorProfile(profile);

          // Check eligibility: only decision_made_accepted can book
          const workflowStatus = String(profile.workflow_status || "").toLowerCase().trim();
          const isEligible = workflowStatus === "decision_made_accepted";

          if (isEligible) {
            setIsEligibleToBook(true);
            setBookingIneligibleMessage("");
          } else {
            setIsEligibleToBook(false);
            // Set message based on status
            if (workflowStatus === "pending" || workflowStatus === "pending_approval") {
              setBookingIneligibleMessage("⏳ Your registration is pending admin approval. Once approved, you'll be able to book an appointment.");
            } else if (workflowStatus === "approved_for_blood_draw") {
              setBookingIneligibleMessage("🧪 Your registration is approved. Please visit the blood bank for a blood sample test before booking a donation appointment.");
            } else if (workflowStatus === "blood_drawn_pending_test") {
              setBookingIneligibleMessage("🔬 Your blood sample is being tested. We'll notify you once results are ready.");
            } else if (workflowStatus === "decision_made_deferred") {
              setBookingIneligibleMessage("⏸️ You are temporarily deferred. Please contact your blood bank for more information.");
            } else if (workflowStatus === "decision_made_rejected") {
              setBookingIneligibleMessage("⛔ Based on your test results, you cannot donate blood at this time. Please contact your blood bank for support.");
            } else {
              setBookingIneligibleMessage("⏳ Please complete your registration and testing before booking an appointment.");
            }
          }

          // Prefill form with donor data
          setForm((prev) => ({
            ...prev,
            name: profile.full_name || currentUser.name || prev.name,
            email: profile.email || currentUser.email || prev.email,
            age: profile.age || prev.age,
            bloodGroup: profile.blood_type || prev.bloodGroup,
            gender: profile.gender || prev.gender,
            phone: profile.phone || prev.phone,
          }));
        }
      } catch (error) {
        console.error("Error fetching donor profile:", error);
      }
    };

    fetchDonorProfile();
  }, []);

  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name === "phone") {
      setForm({ ...form, phone: normalizePhoneInput(value) });
      return;
    }
    setForm({ ...form, [name]: value });
  };

  const handleSubmit = async () => {
    const currentUser = getStoredUser();
    if (!currentUser?.token) {
      setPopupRequiresLogin(true);
      setPopupMessage("Please sign in with a donor account to book an appointment.");
      return;
    }
    if (currentUser?.role !== "donor") {
      setPopupRequiresLogin(false);
      setPopupMessage("Only donor accounts can book appointments. Please sign in as a donor.");
      return;
    }

    // Check eligibility
    if (!isEligibleToBook) {
      setPopupRequiresLogin(false);
      setPopupMessage(bookingIneligibleMessage || "You are not eligible to book an appointment at this time. Please complete your registration and blood testing first.");
      return;
    }

    setPopupRequiresLogin(false);
    if (!form.name || !form.email || !form.date || !form.bank || !form.gender) {
      setPopupMessage("Please fill Name, Email, Gender, Preferred Date, and Blood Bank.");
      return;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(form.email)) {
      const emailMessage = "Please enter a valid email address.";
      setSubmitError(emailMessage);
      setPopupMessage(emailMessage);
      return;
    }

    if (form.phone && !isValidBhutanPhone(form.phone)) {
      const phoneMessage = "Enter a valid Bhutan phone number (8 digits, starts with 16, 17, or 77).";
      setSubmitError(phoneMessage);
      setPopupMessage(phoneMessage);
      return;
    }
    const appointmentPayload = {
      fullName: form.name,
      email: form.email,
      age: form.age ? Number(form.age) : null,
      gender: form.gender || null,
      bloodGroup: form.bloodGroup || null,
      phone: form.phone || null,
      preferredDate: form.date,
      preferredTime: form.time || null,
      bloodBank: form.bank,
    };
    setIsSubmitting(true);
    setSubmitError("");
    try {
      const candidateUrls = getCandidateAppointmentApiUrls();
      let lastError = null;
      let saved = false;
      const apiUrl = candidateUrls[0] || candidateUrls[1];
      try {
        const response = await fetch(apiUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
          },
          body: JSON.stringify(appointmentPayload),
        });
        const rawBody = await response.text();
        let result = null;
        try {
          result = parseJsonFromPossiblyNoisyBody(rawBody);
        } catch (parseError) {
          const bodyPreview = rawBody.replace(/\s+/g, " ").trim().slice(0, 180);
          const parseFailure = new Error(
            `Server at ${apiUrl} returned an invalid response. Preview: ${bodyPreview || "<empty>"}`
          );
          parseFailure.name = "ParseError";
          throw parseFailure;
        }
        if (!response.ok || !result?.success) {
          throw new Error(result?.message || "Could not save appointment.");
        }
        saved = true;
      } catch (requestError) {
        lastError = requestError;
        const shouldTryNextEndpoint =
          requestError?.name === "TypeError" ||
          requestError?.name === "ParseError";
        if (!shouldTryNextEndpoint) {
          throw requestError;
        }
      }
      if (!saved) {
        throw (
          lastError ||
          new Error("Could not reach appointment API. Start Apache/MySQL and verify your API URL.")
        );
      }
      setSubmitted(true);
    } catch (error) {
      setSubmitError(error.message || "Could not save appointment.");
    } finally {
      setIsSubmitting(false);
    }
  };

  const activeTabIndex = tabs.findIndex((t) => t.id === activeTab);
  const goToPrevTab = () => {
    if (activeTabIndex <= 0) return;
    setActiveTab(tabs[activeTabIndex - 1].id);
  };
  const goToNextTab = () => {
    if (activeTabIndex >= tabs.length - 1) return;
    setActiveTab(tabs[activeTabIndex + 1].id);
  };

  return (
    <div className="db-page">
      {/* Top Strip */}
      <div className="top-strip">
        <span>📞 For queries, contact your nearest blood bank</span>
        <Link to="/blood-banks">List of Blood Banks</Link>
        <button onClick={() => navigate("/login")}>Sign In</button>
      </div>

      {/* Header */}
      <header className="logo-header">
        <div className="logo-container">
          <div className="logo-wrap">
            <div className="logo-badge">🩸</div>
            <div className="logo-meta">
              <span className="logo-tag red-tag">Official</span>
              <span className="logo-sub">Blood Transfusion Services</span>
            </div>
          </div>
          <div className="header-title">
            <h1>Blood Transfusion Services</h1>
            <p className="subtitle">
              Department of Medical Services
              <span className="header-divider" />
              Ministry of Health, Bhutan
            </p>
          </div>
          <div className="logo-wrap logo-wrap-right">
            <div className="logo-meta logo-meta-right">
              <span className="logo-tag blue-tag">Ministry</span>
              <span className="logo-sub">Government of Bhutan</span>
            </div>
            <div className="logo-badge gov">
              <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="60" height="60" rx="8" fill="#E8F3EF"/>
                <rect x="26" y="10" width="8" height="40" rx="4" fill="#2E7D4F"/>
                <rect x="10" y="26" width="40" height="8" rx="4" fill="#2E7D4F"/>
              </svg>
            </div>
          </div>
        </div>
      </header>

      {/* Navbar */}
      <nav className={`navbar ${isNavOpen ? "navbar-open" : ""}`}>
        <div className="nav-inner">
          <button
            className="nav-toggle"
            type="button"
            aria-label="Toggle navigation menu"
            aria-expanded={isNavOpen}
            onClick={() => setIsNavOpen((prev) => !prev)}
          >
            <span />
            <span />
            <span />
          </button>
          <ul className={`nav-links ${isNavOpen ? "nav-links-open" : ""}`}>
            <li><Link to="/" onClick={() => setIsNavOpen(false)}>Home</Link></li>
            <li><Link to="/about" onClick={() => setIsNavOpen(false)}>About Us</Link></li>
            <li><Link to="/about-blood" onClick={() => setIsNavOpen(false)}>About Blood</Link></li>
            <li><Link to="/donating-blood" className="active" onClick={() => setIsNavOpen(false)}>Donating Blood</Link></li>
            <li><Link to="/public-information" onClick={() => setIsNavOpen(false)}>Information & Publications</Link></li>
          </ul>
        </div>
      </nav>

      {/* Hero Banner */}
      <div className="db-hero">
        <div className="db-hero-content">
          <nav className="db-breadcrumb">
            <span className="db-breadcrumb-link" onClick={() => navigate("/")}>🏠 Home</span> › Donating Blood
          </nav>
          <h1 className="db-hero-title">Donating Blood</h1>
          <p className="db-hero-sub">A simple act. A life saved. Be a hero today.</p>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="db-tabs-wrapper">
        <div className="db-tabs">
          {tabs.map((t) => (
            <button
              key={t.id}
              className={`db-tab ${activeTab === t.id ? "active" : ""}`}
              onClick={() => setActiveTab(t.id)}
            >
              <span className="db-tab-icon">{t.icon}</span>
              <span className="db-tab-label">{t.label}</span>
              {activeTab === t.id && <span className="db-tab-arrow">▼</span>}
            </button>
          ))}
        </div>
      </div>

      {/* Tab Content */}
      <div className={`db-content ${visible ? "fade-in" : ""}`}>
        {/* WHY DONATE */}
        {activeTab === "why" && (
          <section className="db-section">
            <div className="db-section-label">THE REASON</div>
            <h2 className="db-section-title">Why Your Donation Matters</h2>
            <p className="db-section-desc">Every drop counts. Blood cannot be manufactured — it can only come from generous volunteers like you.</p>
            <div className="db-why-grid">
              {whyDonateCards.map((c, i) => (
                <div className="db-why-card" key={i} style={{ animationDelay: `${i * 0.1}s` }}>
                  <div className="db-why-icon">{c.icon}</div>
                  <h3>{c.title}</h3>
                  <p>{c.desc}</p>
                </div>
              ))}
            </div>
            <div className="db-stat-banner">
              <div className="db-stat">
                <span className="db-stat-num">450<span>ml</span></span>
                <span className="db-stat-label">Per Donation</span>
              </div>
              <div className="db-stat-divider" />
              <div className="db-stat">
                <span className="db-stat-num">3</span>
                <span className="db-stat-label">Lives Saved</span>
              </div>
              <div className="db-stat-divider" />
              <div className="db-stat">
                <span className="db-stat-num">90<span>days</span></span>
                <span className="db-stat-label">Between Donations</span>
              </div>
            </div>
          </section>
        )}

        {/* ELIGIBILITY */}
        {activeTab === "eligibility" && (
          <section className="db-section">
            <div className="db-section-label">REQUIREMENTS</div>
            <h2 className="db-section-title">Am I Eligible to Donate?</h2>
            <p className="db-section-desc">Check these criteria to see if you can donate blood today.</p>
            <div className="db-elig-grid">
              <div className="db-elig-card can">
                <span className="db-elig-badge green">✔ You CAN donate if you...</span>
                <ul>
                  {eligibilityItems.can.map((item, i) => (
                    <li key={i}><span className="check">✓</span>{item}</li>
                  ))}
                </ul>
              </div>
              <div className="db-elig-card cannot">
                <span className="db-elig-badge red">✘ You CANNOT donate if you...</span>
                <ul>
                  {eligibilityItems.cannot.map((item, i) => (
                    <li key={i}><span className="cross">✗</span>{item}</li>
                  ))}
                </ul>
              </div>
            </div>
            <div className="db-cta-box">
              <p>Not sure if you're eligible? Speak to a nurse at your nearest blood bank — they'll guide you.</p>
              <button className="db-btn-primary" onClick={() => setActiveTab("book")}>Book Appointment</button>
            </div>
          </section>
        )}

        {/* PROCESS */}
        {activeTab === "process" && (
          <section className="db-section">
            <div className="db-section-label">STEP BY STEP</div>
            <h2 className="db-section-title">What Happens When You Donate?</h2>
            <p className="db-section-desc">The whole process is simple, safe, and takes less than an hour.</p>
            <div className="db-process-steps">
              {processSteps.map((s, i) => (
                <div className="db-step" key={i} style={{ animationDelay: `${i * 0.12}s` }}>
                  <div className="db-step-num">{s.num}</div>
                  <div className="db-step-body">
                    <div className="db-step-time">⏱ {s.time}</div>
                    <h3>{s.title}</h3>
                    <p>{s.desc}</p>
                  </div>
                </div>
              ))}
            </div>
            <div className="db-total-time">
              🕐 Total visit time: approximately <strong>45–60 minutes</strong>
            </div>
          </section>
        )}

        {/* BOOK APPOINTMENT */}
        {activeTab === "book" && (
          <section className="db-section">
            <div className="db-section-label">SCHEDULE A VISIT</div>
            <h2 className="db-section-title">Book an Appointment</h2>
            <p className="db-section-desc">Reserve your slot at a blood bank near you. Walk-ins are welcome, but booking ahead saves you time.</p>
            
            {!isEligibleToBook && bookingIneligibleMessage && (
              <div className="db-ineligible-box">
                <p>{bookingIneligibleMessage}</p>
                <button className="db-btn-primary" onClick={() => navigate("/dashboard")}>Go to Dashboard</button>
              </div>
            )}

            {isEligibleToBook && submitted ? (
              <div className="db-success-box">
                <div className="db-success-icon">✅</div>
                <h3>Appointment Requested!</h3>
                <p>
                  Thank you, <strong>{form.name}</strong>. Your appointment at{" "}
                  <strong>{form.bank}</strong> on <strong>{form.date}</strong>
                  {form.time && <> at <strong>{form.time}</strong></>} has been received.
                  A confirmation email has been sent to <strong>{form.email}</strong>.
                  The blood bank will confirm your slot shortly.
                </p>
                <button
                  className="db-form-submit"
                  style={{ marginTop: "24px", maxWidth: "220px" }}
                  onClick={() => {
                    setSubmitted(false);
                    setSubmitError("");
                    setForm({ name: "", email: "", age: "", gender: "", bloodGroup: "", phone: "", date: "", time: "", bank: "" });
                  }}
                >
                  Book Another
                </button>
              </div>
            ) : isEligibleToBook ? (
              <div className="db-form-card">
                <div className="db-form-grid">
                  <div className="db-form-group">
                    <label>Full Name <span className="db-required">*</span></label>
                    <input name="name" value={form.name} onChange={handleChange} placeholder="e.g. Tshering Wangchuk" />
                  </div>
                  <div className="db-form-group">
                    <label>Email Address <span className="db-required">*</span></label>
                    <input name="email" type="email" value={form.email} onChange={handleChange} placeholder="e.g. donor@example.com" />
                  </div>
                  <div className="db-form-group">
                    <label>Age</label>
                    <input name="age" value={form.age} onChange={handleChange} placeholder="e.g. 25" type="number" min="18" max="60" />
                  </div>
                  <div className="db-form-group">
                    <label>Blood Group</label>
                    <select name="bloodGroup" value={form.bloodGroup} onChange={handleChange}>
                      <option value="">-- Select --</option>
                      {["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"].map((bg) => (
                        <option key={bg} value={bg}>{bg}</option>
                      ))}
                    </select>
                  </div>
                  <div className="db-form-group">
                    <label>Gender <span className="db-required">*</span></label>
                    <select name="gender" value={form.gender} onChange={handleChange} required>
                      <option value="">-- Select --</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  <div className="db-form-group">
                    <label>Phone Number</label>
                    <input name="phone" value={form.phone} onChange={handleChange} placeholder="e.g. 16XXXXXX" inputMode="numeric" maxLength={8} />
                  </div>
                  <div className="db-form-group">
                    <label>Preferred Date <span className="db-required">*</span></label>
                    <input name="date" value={form.date} onChange={handleChange} type="date" min={new Date().toISOString().split("T")[0]} />
                  </div>
                  <div className="db-form-group">
                    <label>Preferred Time</label>
                    <select name="time" value={form.time} onChange={handleChange}>
                      <option value="">-- Select --</option>
                      <option>9:00 AM</option>
                      <option>10:00 AM</option>
                      <option>11:00 AM</option>
                      <option>1:00 PM</option>
                      <option>2:00 PM</option>
                      <option>3:00 PM</option>
                    </select>
                  </div>
                  <div className="db-form-group db-form-full">
                    <label>Select Blood Bank <span className="db-required">*</span></label>
                    <select name="bank" value={form.bank} onChange={handleChange}>
                      <option value="">-- Select a blood bank --</option>
                      {availableBanks.length > 0 ? (
                        availableBanks.map((bankName) => (
                          <option key={bankName} value={bankName}>{bankName}</option>
                        ))
                      ) : (
                        <>
                          <option>Jigme Dorji Wangchuck National Referral Hospital, Thimphu</option>
                          <option>Phuentsholing General Hospital, Phuentsholing</option>
                          <option>Mongar Regional Referral Hospital, Mongar</option>
                          <option>Gelephu Regional Referral Hospital, Gelephu</option>
                          <option>Wangdue District Hospital, Wangdue</option>
                        </>
                      )}
                    </select>
                  </div>
                </div>
                <div className="db-form-notice">
                  <span>📋</span>
                  <p>Please arrive 10 minutes early. Bring your CID card. Eat a light meal before donating and stay hydrated.</p>
                </div>
                {submitError && <p className="db-form-error">{submitError}</p>}
                <button className="db-form-submit" onClick={handleSubmit} disabled={isSubmitting}>
                  {isSubmitting ? "Saving Appointment..." : "Confirm Appointment →"}
                </button>
              </div>
            ) : null}
          </section>
        )}

        {/* AFTERCARE */}
        {activeTab === "aftercare" && (
          <section className="db-section">
            <div className="db-section-label">RECOVERY</div>
            <h2 className="db-section-title">After Your Donation</h2>
            <p className="db-section-desc">Follow these guidelines to feel your best and recover quickly.</p>
            <div className="db-aftercare-grid">
              {aftercareItems.map((item, i) => (
                <div className="db-aftercare-card" key={i} style={{ animationDelay: `${i * 0.08}s` }}>
                  <span className="db-aftercare-icon">{item.icon}</span>
                  <p>{item.tip}</p>
                </div>
              ))}
            </div>
            <div className="db-alert-box">
              <strong>⚠ When to seek help:</strong> If you feel faint, dizzy, have prolonged bleeding, or feel unwell after leaving, contact your nearest blood bank or health facility immediately.
            </div>
          </section>
        )}

        {/* FAQS */}
        {activeTab === "faq" && (
          <section className="db-section">
            <div className="db-section-label">COMMON QUESTIONS</div>
            <h2 className="db-section-title">Frequently Asked Questions</h2>
            <p className="db-section-desc">Everything you wanted to know about blood donation.</p>
            <div className="db-faq-list">
              {faqs.map((faq, i) => (
                <div className={`db-faq-item ${openFaq === i ? "open" : ""}`} key={i} onClick={() => setOpenFaq(openFaq === i ? null : i)}>
                  <div className="db-faq-q">
                    <span>{faq.q}</span>
                    <span className="db-faq-arrow">{openFaq === i ? "▲" : "▼"}</span>
                  </div>
                  {openFaq === i && <div className="db-faq-a">{faq.a}</div>}
                </div>
              ))}
            </div>
          </section>
        )}

        <div className="db-nav-arrows">
          <button className="db-nav-btn" onClick={goToPrevTab} disabled={activeTabIndex === 0}>← Previous</button>
          <div className="db-nav-dots">
            {tabs.map((tab, index) => (
              <span key={tab.id} className={`db-nav-dot ${activeTabIndex === index ? "dot-active" : ""}`} onClick={() => setActiveTab(tab.id)} />
            ))}
          </div>
          <button className="db-nav-btn" onClick={goToNextTab} disabled={activeTabIndex === tabs.length - 1}>Next →</button>
        </div>
      </div>

      {popupMessage && (
        <div className="db-modal-overlay" role="dialog" aria-modal="true" aria-label="Form message">
          <div className="db-modal-card">
            <h3>Please Check</h3>
            <p>{popupMessage}</p>
            <div className="db-modal-actions">
              {popupRequiresLogin && (
                <button type="button" onClick={() => { setPopupMessage(""); setPopupRequiresLogin(false); navigate("/login"); }}>Sign In</button>
              )}
              <button type="button" onClick={() => { setPopupMessage(""); setPopupRequiresLogin(false); }}>OK</button>
            </div>
          </div>
        </div>
      )}

      {/* Bottom CTA */}
      <div className="db-bottom-cta">
        <div className="db-bottom-cta-inner">
          <div className="db-bottom-copy">
            <span className="db-bottom-kicker">Next step</span>
            <h3>Ready to Save a Life?</h3>
            <p>Find your nearest blood donation centre and book an appointment today.</p>
          </div>
          <div className="db-bottom-cta-btns">
            <button className="db-btn-primary" onClick={() => setActiveTab("book")}>Book Appointment</button>
            <button className="db-btn-outline" onClick={() => setActiveTab("eligibility")}>Check Eligibility</button>
          </div>
        </div>
      </div>

      <footer className="footer">
        <p>© 2026 <strong>National Blood Transfusion Services</strong> · Ministry of Health, Bhutan</p>
        <p className="footer-contact">
          <span>📧 <a href="mailto:admin@bts.bt">admin@bts.bt</a></span>
          <span>📞 <a href="tel:17967631">17967631</a></span>
        </p>
        <p className="footer-hotline">Helpline: <span>1095</span> · Available 24/7</p>
      </footer>
    </div>
  );
}