import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import "./DonorRegister.css";
import { saveAuthSession } from "../utils/auth";

const API_URL = process.env.REACT_APP_API_URL;

const DEFAULT_API_URLS = [
  "http://localhost/blood_donation/api/register_donor.php",
];

const getCandidateApiUrls = () => {
  const uniqueUrls = new Set();
  if (API_URL && API_URL.trim()) uniqueUrls.add(API_URL.trim());
  DEFAULT_API_URLS.forEach((url) => uniqueUrls.add(url));
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

const getFriendlyServerErrorFromRawBody = (rawBody) => {
  const normalized = rawBody.replace(/\s+/g, " ").trim();
  if (/Duplicate entry .* for key 'email'/i.test(normalized))
    return "This email is already registered.";
  return null;
};

const isConfirmedInsert = (response, result) =>
  response.ok && result?.success === true;

export default function DonorRegister() {
  const navigate = useNavigate();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [popup, setPopup] = useState({ open: false, title: "", message: "", kind: "error" });
  const [formData, setFormData] = useState({
    fullName: "",
    email: "",
    password: "",
    confirmPassword: "",
    phone: "",
    dateOfBirth: "",
    gender: "",
    bloodType: "",
    address: "",
    city: "",
    dzongkhag: "",
    weight: "",
    lastDonationDate: "",
    healthDeclaration: {
      no_tattoo_piercing_acupuncture_last_6_months: false,
      not_taking_antibiotics_or_blood_thinners: false,
      no_surgery_last_6_months: false,
      not_pregnant_or_breastfeeding: false,
      no_cold_flu_or_fever_today: false,
    },
    consent: false,
    emergencyContactName: "",
    emergencyContactPhone: "",
  });

  const showPopup = (title, message, kind = "error") =>
    setPopup({ open: true, title, message, kind });

  const closePopup = () =>
    setPopup({ open: false, title: "", message: "", kind: "error" });

  const isValidBhutanPhone = (value) => /^(16|17|77)\d{6}$/.test(value);

  const resetForm = () =>
    setFormData({
      fullName: "", email: "", password: "", confirmPassword: "", phone: "",
      dateOfBirth: "", gender: "", bloodType: "", address: "", city: "",
      dzongkhag: "", weight: "", lastDonationDate: "",
      healthDeclaration: {
        no_tattoo_piercing_acupuncture_last_6_months: false,
        not_taking_antibiotics_or_blood_thinners: false,
        no_surgery_last_6_months: false,
        not_pregnant_or_breastfeeding: false,
        no_cold_flu_or_fever_today: false,
      },
      consent: false,
      emergencyContactName: "",
      emergencyContactPhone: "",
    });

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    if (type === "checkbox" && name.startsWith("healthDeclaration.")) {
      const key = name.split(".")[1];
      setFormData({ ...formData, healthDeclaration: { ...formData.healthDeclaration, [key]: checked } });
      return;
    }
    if (type === "checkbox" && name === "consent") {
      setFormData({ ...formData, consent: checked });
      return;
    }
    if (name === "phone") {
      setFormData({ ...formData, phone: value.replace(/\D/g, "").slice(0, 8) });
      return;
    }
    if (name === "emergencyContactPhone") {
      setFormData({ ...formData, emergencyContactPhone: value.replace(/\D/g, "").slice(0, 8) });
      return;
    }
    setFormData({ ...formData, [name]: value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!formData.fullName || !formData.email || !formData.password || !formData.confirmPassword ||
      !formData.phone || !formData.dateOfBirth || !formData.gender || !formData.bloodType ||
      !formData.address || !formData.city || !formData.dzongkhag || !formData.weight ||
      !formData.emergencyContactName || !formData.emergencyContactPhone) {
      showPopup("Please Check", "Please fill all required fields.");
      return;
    }
    if (formData.password.length < 6) {
      showPopup("Weak Password", "Password must be at least 6 characters.");
      return;
    }
    /* eslint-disable-next-line no-useless-escape */
    if (!/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>\/?]/.test(formData.password)) {
      showPopup("Weak Password", "Password must contain at least 1 special character (!@#$%^&* etc).");
      return;
    }
    if (formData.password !== formData.confirmPassword) {
      showPopup("Password Mismatch", "Password and confirm password must match.");
      return;
    }
    if (!isValidBhutanPhone(formData.phone)) {
      showPopup("Invalid Phone Number", "Use 8 digits starting with 16, 17, or 77.");
      return;
    }
    if (Number(formData.weight) < 45) {
      showPopup("Invalid Weight", "Weight must be at least 45 kg.");
      return;
    }
    if (!isValidBhutanPhone(formData.emergencyContactPhone)) {
      showPopup("Invalid Emergency Contact", "Emergency contact phone must use 8 digits starting with 16, 17, or 77.");
      return;
    }
    const healthDeclarationChecks = { ...formData.healthDeclaration };
    if (formData.gender !== "Female") {
      delete healthDeclarationChecks.not_pregnant_or_breastfeeding;
    }

    const missingHealthCheck = Object.entries(healthDeclarationChecks).some(([, v]) => !v);
    if (missingHealthCheck) {
      showPopup("Health Declaration", "Please confirm all health declaration items.");
      return;
    }
    if (!formData.consent) {
      showPopup("Consent Required", "You must consent to the blood testing terms.");
      return;
    }

    try {
      setIsSubmitting(true);
      const candidateUrls = getCandidateApiUrls();
      let lastError = null, completed = false, savedByUrl = "", savedId = null, result = null;

      for (const apiUrl of candidateUrls) {
        try {
          const response = await fetch(apiUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ...formData, weight: Number(formData.weight) }),
          });
          const rawBody = await response.text();
          try {
            result = parseJsonFromPossiblyNoisyBody(rawBody);
          } catch (parseError) {
            const friendlyServerError = getFriendlyServerErrorFromRawBody(rawBody);
            if (friendlyServerError) throw new Error(friendlyServerError);
            const bodyPreview = rawBody.replace(/\s+/g, " ").trim().slice(0, 180);
            const parseFailure = new Error(`Server at ${apiUrl} returned an invalid response. Preview: ${bodyPreview || "<empty>"}`);
            parseFailure.name = "ParseError";
            throw parseFailure;
          }
          if (!response.ok || !result.success) throw new Error(result.message || "Registration failed.");
          if (!isConfirmedInsert(response, result)) throw new Error("Server replied with an unexpected success response.");
          savedByUrl = apiUrl;
          savedId = result?.id ?? null;
          completed = true;
          break;
        } catch (requestError) {
          lastError = requestError;
          const shouldTryNextEndpoint = requestError?.name === "TypeError" || requestError?.name === "ParseError";
          if (!shouldTryNextEndpoint) throw requestError;
        }
      }

      if (!completed) throw lastError || new Error("Could not reach donor registration API.");
      if (savedId || savedByUrl) console.log("Donor registration saved", { id: savedId, endpoint: savedByUrl });

      if (result?.token) {
        saveAuthSession({ id: result.id, name: result.name, email: result.email, role: result.role, token: result.token });
        navigate("/dashboard");
        return;
      }
      // Navigate to success screen with registration details
      navigate("/registration-success", {
        state: {
          donorId: result?.id || savedId,
          email: formData.email,
          status: result?.status || "Pending",
        },
      });
      resetForm();
    } catch (error) {
      showPopup("Could Not Submit", error.message || "Could not submit registration.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="register-page">

      <header className="register-header">
        <button className="back-btn" onClick={() => navigate("/")}>← Back to Home</button>
        <div className="header-badge">🩸</div>
        <h1>Create New Account</h1>
        <p>Register as a blood donor and help save lives.</p>
      </header>

      <section className="register-section">
        <div className="register-container">

          <form className="register-form" onSubmit={handleSubmit} noValidate>

            {/* SECTION 1 – Personal Information */}
            <div className="form-section">
              <div className="form-section-title">
                <span className="section-num">1</span>
                Personal Information
              </div>

              <div className="form-group">
                <label>Full Name *</label>
                <input type="text" name="fullName" value={formData.fullName}
                  onChange={handleChange} placeholder="Enter your full name" required />
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>Email *</label>
                  <input type="email" name="email" value={formData.email}
                    onChange={handleChange} placeholder="your.email@example.com" required />
                </div>
                <div className="form-group">
                  <label>Phone Number *</label>
                  <input type="tel" name="phone" value={formData.phone}
                    onChange={handleChange} placeholder="16XXXXXX / 17XXXXXX / 77XXXXXX"
                    inputMode="numeric" maxLength={8} required />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>Date of Birth *</label>
                  <input type="date" name="dateOfBirth" value={formData.dateOfBirth}
                    onChange={handleChange} required />
                </div>
                <div className="form-group">
                  <label>Gender *</label>
                  <select name="gender" value={formData.gender} onChange={handleChange} required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>Blood Type *</label>
                  <select name="bloodType" value={formData.bloodType} onChange={handleChange} required>
                    <option value="">Select Blood Type</option>
                    <option value="O+">O+</option><option value="O-">O-</option>
                    <option value="A+">A+</option><option value="A-">A-</option>
                    <option value="B+">B+</option><option value="B-">B-</option>
                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>Weight (kg) *</label>
                  <input type="number" name="weight" value={formData.weight}
                    onChange={handleChange} min="45" step="0.1" placeholder="Minimum 45 kg" required />
                </div>
              </div>

              <div className="form-group">
                <label>Address *</label>
                <input type="text" name="address" value={formData.address}
                  onChange={handleChange} placeholder="Street address" required />
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>City *</label>
                  <input type="text" name="city" value={formData.city}
                    onChange={handleChange} placeholder="City" required />
                </div>
                <div className="form-group">
                  <label>Dzongkhag *</label>
                  <select name="dzongkhag" value={formData.dzongkhag} onChange={handleChange} required>
                    <option value="">Select Dzongkhag</option>
                    <option value="Bumthang">Bumthang</option>
                    <option value="Chhukha">Chhukha</option>
                    <option value="Dagana">Dagana</option>
                    <option value="Gasa">Gasa</option>
                    <option value="Haa">Haa</option>
                    <option value="Lhuentse">Lhuentse</option>
                    <option value="Mongar">Mongar</option>
                    <option value="Paro">Paro</option>
                    <option value="Pemagatshel">Pemagatshel</option>
                    <option value="Punakha">Punakha</option>
                    <option value="Samdrup Jongkhar">Samdrup Jongkhar</option>
                    <option value="Samtse">Samtse</option>
                    <option value="Sarpang">Sarpang</option>
                    <option value="Thimphu">Thimphu</option>
                    <option value="Trashigang">Trashigang</option>
                    <option value="Trashiyangtse">Trashiyangtse</option>
                    <option value="Trongsa">Trongsa</option>
                    <option value="Tsirang">Tsirang</option>
                    <option value="Wangdue Phodrang">Wangdue Phodrang</option>
                    <option value="Zhemgang">Zhemgang</option>
                  </select>
                </div>
              </div>

              <div className="form-group">
                <label>Last Donation Date</label>
                <input type="date" name="lastDonationDate" value={formData.lastDonationDate}
                  onChange={handleChange} />
              </div>
            </div>

            {/* SECTION 2 – Account Security */}
            <div className="form-section">
              <div className="form-section-title">
                <span className="section-num">2</span>
                Account Security
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>Create Password *</label>
                  <input type="password" name="password" value={formData.password}
                    onChange={handleChange} placeholder="Min 6 chars + 1 special char (!@#$%^&*)" minLength={6} required />
                </div>
                <div className="form-group">
                  <label>Confirm Password *</label>
                  <input type="password" name="confirmPassword" value={formData.confirmPassword}
                    onChange={handleChange} placeholder="Re-enter your password" minLength={6} required />
                </div>
              </div>
            </div>

            {/* SECTION 3 – Health + Emergency + Consent */}
            <div className="form-section declaration-box">
              <div className="form-section-title">
                <span className="section-num">3</span>
                Health Declaration &amp; Consent
              </div>

              <p className="declaration-intro">
                Please read and check all items below before submitting your registration.
              </p>

              <div className="health-declaration-list">
                <label className="checkbox-row">
                  <input type="checkbox"
                    name="healthDeclaration.no_tattoo_piercing_acupuncture_last_6_months"
                    checked={formData.healthDeclaration.no_tattoo_piercing_acupuncture_last_6_months}
                    onChange={handleChange} />
                  <span><strong>No tattoo, piercing or acupuncture</strong> in the last 6 months.</span>
                </label>

                <label className="checkbox-row">
                  <input type="checkbox"
                    name="healthDeclaration.not_taking_antibiotics_or_blood_thinners"
                    checked={formData.healthDeclaration.not_taking_antibiotics_or_blood_thinners}
                    onChange={handleChange} />
                  <span><strong>Not currently taking</strong> antibiotics or blood thinners.</span>
                </label>

                <label className="checkbox-row">
                  <input type="checkbox"
                    name="healthDeclaration.no_surgery_last_6_months"
                    checked={formData.healthDeclaration.no_surgery_last_6_months}
                    onChange={handleChange} />
                  <span><strong>No surgery</strong> in the last 6 months.</span>
                </label>

                {formData.gender === "Female" && (
                  <label className="checkbox-row">
                    <input type="checkbox"
                      name="healthDeclaration.not_pregnant_or_breastfeeding"
                      checked={formData.healthDeclaration.not_pregnant_or_breastfeeding}
                      onChange={handleChange} />
                    <span><strong>Not pregnant</strong> or breastfeeding.</span>
                  </label>
                )}

                <label className="checkbox-row">
                  <input type="checkbox"
                    name="healthDeclaration.no_cold_flu_or_fever_today"
                    checked={formData.healthDeclaration.no_cold_flu_or_fever_today}
                    onChange={handleChange} />
                  <span><strong>No cold, flu, or fever</strong> today.</span>
                </label>
              </div>

              <div className="declaration-divider"><span>Emergency Contact</span></div>

              <div className="form-row">
                <div className="form-group">
                  <label>Emergency Contact Name *</label>
                  <input type="text" name="emergencyContactName"
                    value={formData.emergencyContactName} onChange={handleChange}
                    placeholder="Full name" required />
                </div>
                <div className="form-group">
                  <label>Emergency Contact Phone *</label>
                  <input type="tel" name="emergencyContactPhone"
                    value={formData.emergencyContactPhone} onChange={handleChange}
                    placeholder="16XXXXXX / 17XXXXXX / 77XXXXXX"
                    inputMode="numeric" maxLength={8} required />
                </div>
              </div>

              <div className="declaration-divider"><span>Medical Consent</span></div>

              <label className="consent-checkbox-row">
                <input type="checkbox" name="consent"
                  checked={formData.consent} onChange={handleChange} />
                <span>
                  I consent to have my blood tested for <strong>HIV, Hepatitis B/C, Syphilis,
                  Malaria</strong>, and other infectious diseases. I understand that positive results
                  will be confidentially communicated to me.
                </span>
              </label>
            </div>

            <div className="form-actions">
              <button type="button" className="cancel-btn" onClick={() => navigate("/")}>
                ✕ Cancel
              </button>
              <button type="submit" className="submit-btn" disabled={isSubmitting}>
                {isSubmitting ? "Submitting…" : "Create Account"}
              </button>
            </div>
          </form>

          <aside className="register-info">
            <div className="info-drop">🩸</div>
            <h3>Why Donate Blood?</h3>
            <ul>
              <li>Save up to 3 lives with a single donation</li>
              <li>Receive free health screening</li>
              <li>Get recognition as a blood donor</li>
              <li>Help emergency patients in need</li>
              <li>Be part of a caring community</li>
            </ul>
            <div className="helpline">
              <strong>Need help?</strong>
              <span>1095</span>
              <small>Available 24 / 7</small>
            </div>
          </aside>

        </div>
      </section>

      {popup.open && (
        <div className="register-modal-overlay" role="dialog" aria-modal="true">
          <div className={`register-modal-card ${popup.kind}`}>
            <div className="modal-icon">{popup.kind === "success" ? "🎉" : "⚠️"}</div>
            <h3>{popup.title}</h3>
            <p>{popup.message}</p>
            <div className="register-modal-actions">
              {popup.kind === "success" && (
                <button type="button" onClick={() => { closePopup(); navigate("/login"); }}>
                  Sign In Now
                </button>
              )}
              <button type="button" className="modal-ok" onClick={closePopup}>OK</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}