import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import "./Blooddonationcamp.css";

const CAMP_API_URL = process.env.REACT_APP_CAMP_API_URL;

const DEFAULT_CAMP_API_URLS = [
  "http://localhost/blood_donation/api/register_camp.php",
];

const getCandidateCampApiUrls = () => {
  const uniqueUrls = new Set();
  if (CAMP_API_URL && CAMP_API_URL.trim()) {
    uniqueUrls.add(CAMP_API_URL.trim());
  }
  DEFAULT_CAMP_API_URLS.forEach((url) => uniqueUrls.add(url));
  return Array.from(uniqueUrls);
};

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

export default function BloodDonationCamp() {
  const navigate = useNavigate();
  const [isNavOpen, setIsNavOpen] = useState(false);

  const [formData, setFormData] = useState({
    organizationName: "",
    contactPerson: "",
    phone: "",
    email: "",
    dzongkhag: "",
    venue: "",
    preferredDate: "",
    alternateDate: "",
    expectedDonors: "",
    campType: "",
    facilities: "",
    additionalInfo: "",
    agree: false,
  });

  const [submitted, setSubmitted] = useState(false);
  const [errors, setErrors] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 900) {
        setIsNavOpen(false);
      }
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const dzongkhags = [
    "Bumthang", "Chhukha", "Dagana", "Gasa", "Haa",
    "Lhuentse", "Mongar", "Paro", "Pemagatshel", "Punakha",
    "Samdrup Jongkhar", "Samtse", "Sarpang", "Thimphu",
    "Trashigang", "Trashi Yangtse", "Trongsa", "Tsirang",
    "Wangdue Phodrang", "Zhemgang"
  ];

  const validate = () => {
    const newErrors = {};
    if (!formData.organizationName.trim()) newErrors.organizationName = "Organization name is required";
    if (!formData.contactPerson.trim()) newErrors.contactPerson = "Contact person name is required";
    if (!formData.phone.trim()) newErrors.phone = "Phone number is required";
    else if (!/^(16|17|77)\d{6}$/.test(formData.phone)) newErrors.phone = "Enter a valid 8-digit phone number starting with 16, 17, or 77";
    if (formData.email.trim()) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(formData.email.trim())) {
        newErrors.email = "Enter a valid email address";
      }
    }
    if (!formData.dzongkhag) newErrors.dzongkhag = "Please select dzongkhag";
    if (!formData.venue.trim()) newErrors.venue = "Venue is required";
    if (!formData.preferredDate) newErrors.preferredDate = "Preferred date is required";
    if (!formData.expectedDonors.trim()) {
      newErrors.expectedDonors = "Expected donors is required";
    } else {
      const donorsNumber = Number(formData.expectedDonors);
      if (!Number.isFinite(donorsNumber) || donorsNumber < 20) {
        newErrors.expectedDonors = "Expected donors must be at least 20";
      }
    }
    if (!formData.campType) newErrors.campType = "Please select camp type";
    if (!formData.agree) newErrors.agree = "You must agree to the terms";
    return newErrors;
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData((prev) => ({ ...prev, [name]: type === "checkbox" ? checked : value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: "" }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitError("");

    const newErrors = validate();
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    const payload = {
      organizationName: formData.organizationName,
      contactPerson: formData.contactPerson,
      phone: formData.phone,
      email: formData.email || null,
      dzongkhag: formData.dzongkhag,
      campType: formData.campType,
      venue: formData.venue,
      preferredDate: formData.preferredDate,
      alternateDate: formData.alternateDate || null,
      expectedDonors: Number(formData.expectedDonors),
      facilities: formData.facilities || null,
      additionalInfo: formData.additionalInfo || null,
    };

    setIsSubmitting(true);
    try {
      const candidateUrls = getCandidateCampApiUrls();
      let lastError = null;
      let saved = false;

      for (const apiUrl of candidateUrls) {
        try {
          const response = await fetch(apiUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
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
            throw new Error(result?.message || "Could not save camp request.");
          }

          saved = true;
          break;
        } catch (requestError) {
          lastError = requestError;
          const shouldTryNextEndpoint =
            requestError?.name === "TypeError" ||
            requestError?.name === "ParseError";
          if (!shouldTryNextEndpoint) {
            throw requestError;
          }
        }
      }

      if (!saved) {
        throw (
          lastError ||
          new Error("Could not reach camp API. Start Apache/MySQL and verify your API URL.")
        );
      }

      setSubmitted(true);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      setSubmitError(error.message || "Could not save camp request.");
      window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      setIsSubmitting(false);
    }
  };

  if (submitted) {
    return (
      <div className="camp-page">
        <div className="camp-success-wrap">
          <div className="camp-success-card">
            <div className="camp-success-icon">🏕️</div>
            <h2>Request Submitted!</h2>
            <p>Thank you, <strong>{formData.organizationName}</strong>!</p>
            <p>Your blood donation camp request has been received. Our team will contact <strong>{formData.contactPerson}</strong> at <strong>{formData.phone}</strong> within 3–5 working days.</p>
            <div className="camp-success-buttons">
              <button className="camp-btn-primary" onClick={() => navigate("/register")}>
                Register as Donor 🩸
              </button>
              <button className="camp-btn-ghost" onClick={() => navigate("/")}>
                ← Back to Home
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="camp-page">

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
            <h1>National Blood Transfusion Services</h1>
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
            <li><Link to="/donating-blood" onClick={() => setIsNavOpen(false)}>Donating Blood</Link></li>
            <li><Link to="/public-information" onClick={() => setIsNavOpen(false)}>Information & Publications</Link></li>
          </ul>
        </div>
      </nav>

      {/* Page Hero */}
      <div className="camp-hero">
        <div className="camp-hero-inner">
          <div className="camp-breadcrumb">
            <span className="camp-breadcrumb-link" onClick={() => navigate("/")}>➕ Home</span>
            <span> › </span>
            <span>Organize Blood Donation Camp</span>
          </div>
          <h1>Organize a Blood Donation Camp</h1>
          <p>Partner with us to save lives in your community across Bhutan</p>
        </div>
      </div>

      {/* Info Bar */}
      <div className="camp-info-bar">
        <div className="camp-info-item">🏢 <span>Any organization can apply</span></div>
        <div className="camp-info-item">👥 <span>Min. 20 expected donors</span></div>
        <div className="camp-info-item">📅 <span>Apply 2 weeks in advance</span></div>
        <div className="camp-info-item">🆓 <span>Free NBTS support provided</span></div>
      </div>

      {/* Main Content */}
      <div className="camp-container">
        <div className="camp-layout">

          {/* Form */}
          <form className="camp-form" onSubmit={handleSubmit} noValidate>

            {/* Organization Info */}
            <div className="camp-section">
              <div className="camp-section-header">
                <span className="camp-section-icon">🏢</span>
                <h2>Organization Information</h2>
              </div>
              <div className="camp-grid-2">
                <div className="camp-field camp-field-full">
                  <label>Organization / Institution Name <span className="req">*</span></label>
                  <input
                    type="text"
                    name="organizationName"
                    placeholder="e.g. Thimphu Tech Park, RUB, Ministry of Finance"
                    value={formData.organizationName}
                    onChange={handleChange}
                    className={errors.organizationName ? "error" : ""}
                  />
                  {errors.organizationName && <span className="camp-error">{errors.organizationName}</span>}
                </div>
                <div className="camp-field">
                  <label>Contact Person <span className="req">*</span></label>
                  <input
                    type="text"
                    name="contactPerson"
                    placeholder="Full name of contact person"
                    value={formData.contactPerson}
                    onChange={handleChange}
                    className={errors.contactPerson ? "error" : ""}
                  />
                  {errors.contactPerson && <span className="camp-error">{errors.contactPerson}</span>}
                </div>
                <div className="camp-field">
                  <label>Phone Number <span className="req">*</span></label>
                  <input
                    type="tel"
                    name="phone"
                    placeholder="Starts with 16, 17, or 77"
                    value={formData.phone}
                    onChange={handleChange}
                    className={errors.phone ? "error" : ""}
                  />
                  {errors.phone && <span className="camp-error">{errors.phone}</span>}
                </div>
                <div className="camp-field">
                  <label>Email Address</label>
                  <input
                    type="email"
                    name="email"
                    placeholder="optional"
                    value={formData.email}
                    onChange={handleChange}
                    className={errors.email ? "error" : ""}
                  />
                  {errors.email && <span className="camp-error">{errors.email}</span>}
                </div>
              </div>
            </div>

            {/* Camp Details */}
            <div className="camp-section">
              <div className="camp-section-header">
                <span className="camp-section-icon">📍</span>
                <h2>Camp Details</h2>
              </div>
              <div className="camp-grid-2">
                <div className="camp-field">
                  <label>Dzongkhag <span className="req">*</span></label>
                  <select
                    name="dzongkhag"
                    value={formData.dzongkhag}
                    onChange={handleChange}
                    className={errors.dzongkhag ? "error" : ""}
                  >
                    <option value="">Select Dzongkhag</option>
                    {dzongkhags.map((d) => (
                      <option key={d} value={d}>{d}</option>
                    ))}
                  </select>
                  {errors.dzongkhag && <span className="camp-error">{errors.dzongkhag}</span>}
                </div>
                <div className="camp-field">
                  <label>Camp Type <span className="req">*</span></label>
                  <select
                    name="campType"
                    value={formData.campType}
                    onChange={handleChange}
                    className={errors.campType ? "error" : ""}
                  >
                    <option value="">Select camp type</option>
                    <option value="corporate">Corporate / Office</option>
                    <option value="school">School / College</option>
                    <option value="community">Community / Gewog</option>
                    <option value="religious">Religious / Monastery</option>
                    <option value="hospital">Hospital / Health Center</option>
                    <option value="other">Other</option>
                  </select>
                  {errors.campType && <span className="camp-error">{errors.campType}</span>}
                </div>
                <div className="camp-field camp-field-full">
                  <label>Venue / Address <span className="req">*</span></label>
                  <input
                    type="text"
                    name="venue"
                    placeholder="Full address of the camp venue"
                    value={formData.venue}
                    onChange={handleChange}
                    className={errors.venue ? "error" : ""}
                  />
                  {errors.venue && <span className="camp-error">{errors.venue}</span>}
                </div>
                <div className="camp-field">
                  <label>Preferred Date <span className="req">*</span></label>
                  <input
                    type="date"
                    name="preferredDate"
                    value={formData.preferredDate}
                    onChange={handleChange}
                    className={errors.preferredDate ? "error" : ""}
                  />
                  {errors.preferredDate && <span className="camp-error">{errors.preferredDate}</span>}
                </div>
                <div className="camp-field">
                  <label>Alternate Date</label>
                  <input
                    type="date"
                    name="alternateDate"
                    value={formData.alternateDate}
                    onChange={handleChange}
                  />
                </div>
                <div className="camp-field">
                  <label>Expected Number of Donors <span className="req">*</span></label>
                  <input
                    type="number"
                    name="expectedDonors"
                    placeholder="e.g. 50"
                    min="20"
                    value={formData.expectedDonors}
                    onChange={handleChange}
                    className={errors.expectedDonors ? "error" : ""}
                  />
                  {errors.expectedDonors && <span className="camp-error">{errors.expectedDonors}</span>}
                </div>
                <div className="camp-field">
                  <label>Facilities Available</label>
                  <input
                    type="text"
                    name="facilities"
                    placeholder="e.g. hall, chairs, tables, refreshments"
                    value={formData.facilities}
                    onChange={handleChange}
                  />
                </div>
                <div className="camp-field camp-field-full">
                  <label>Additional Information</label>
                  <textarea
                    name="additionalInfo"
                    placeholder="Any other details about the camp, special requirements, etc."
                    rows={3}
                    value={formData.additionalInfo}
                    onChange={handleChange}
                  />
                </div>
              </div>
            </div>

            {/* Agreement */}
            <div className="camp-section">
              <label className={`camp-checkbox ${errors.agree ? "error-check" : ""}`}>
                <input
                  type="checkbox"
                  name="agree"
                  checked={formData.agree}
                  onChange={handleChange}
                />
                <span>
                  I confirm that our organization is committed to organizing this blood donation camp
                  and will provide the necessary facilities. We agree to coordinate with the National
                  Blood Transfusion Services team for successful execution.
                </span>
              </label>
              {errors.agree && <span className="camp-error">{errors.agree}</span>}
            </div>

            {/* Buttons */}
            <div className="camp-actions">
              {submitError && <p className="camp-submit-error">{submitError}</p>}
              <button type="button" className="camp-cancel-btn" onClick={() => navigate("/")}>
                ✕ Cancel
              </button>
              <button type="submit" className="camp-submit-btn" disabled={isSubmitting}>
                {isSubmitting ? "Submitting..." : "Submit Request 🏕️"}
              </button>
            </div>

          </form>

          {/* Sidebar */}
          <div className="camp-sidebar">
            <div className="camp-side-card">
              <h3>📋 What We Provide</h3>
              <ul>
                <li>Trained medical staff & nurses</li>
                <li>All blood collection equipment</li>
                <li>Donor screening & health check</li>
                <li>Blood storage & transport</li>
                <li>Donor certificates & recognition</li>
                <li>Awareness materials & banners</li>
              </ul>
            </div>
            <div className="camp-side-card">
              <h3>✅ Your Responsibilities</h3>
              <ul>
                <li>Provide a suitable venue/hall</li>
                <li>Mobilize potential donors</li>
                <li>Arrange chairs & tables</li>
                <li>Provide refreshments for donors</li>
                <li>Assist with logistics on the day</li>
              </ul>
            </div>
            <div className="camp-helpline-card">
              <div className="camp-helpline-icon">📞</div>
              <div>
                <p>Need help planning?</p>
                <strong>Call 1095</strong>
                <p>Available 24/7</p>
              </div>
            </div>
          </div>

        </div>
      </div>

      {/* Footer */}
      <footer className="footer">
        <p>© 2026 <strong>Blood Transfusion Services</strong> · Ministry of Health, Bhutan</p>
        <p className="footer-contact">
          <span>📧 <a href="mailto:admin@bts.bt">admin@bts.bt</a></span>
          <span>📞 <a href="tel:17967631">17967631</a></span>
        </p>
        <p className="footer-hotline">Helpline: <span>1095</span> · Available 24/7</p>
      </footer>

    </div>
  );
}