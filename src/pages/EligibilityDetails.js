import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import "./EligibilityDetails.css";

export default function EligibilityDetails() {
  const navigate = useNavigate();
  const [isNavOpen, setIsNavOpen] = useState(false);

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 900) {
        setIsNavOpen(false);
      }
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const eligible = [
    { icon: "🎂", title: "Age", desc: "Between 18 and 65 years old" },
    { icon: "⚖️", title: "Weight", desc: "Minimum 45 kg (99 lbs)" },
    { icon: "❤️", title: "Health", desc: "Generally healthy and feeling well" },
    { icon: "🩸", title: "Hemoglobin", desc: "Min 12.5 g/dL for women, 13.0 g/dL for men" },
    { icon: "💓", title: "Blood Pressure", desc: "Between 90/60 and 160/100 mmHg" },
    { icon: "🌡️", title: "Temperature", desc: "Normal body temperature (below 37.5°C)" },
  ];

  const notEligible = [
    { icon: "🤒", title: "Recent Illness", desc: "Cold, flu or infection in last 2 weeks" },
    { icon: "💉", title: "Recent Tattoo/Piercing", desc: "Within the last 6 months" },
    { icon: "🤰", title: "Pregnancy", desc: "Currently pregnant or breastfeeding" },
    { icon: "💊", title: "Certain Medications", desc: "Blood thinners, antibiotics (consult staff)" },
    { icon: "🧬", title: "Certain Conditions", desc: "HIV, Hepatitis B/C, heart disease" },
    { icon: "🩹", title: "Recent Surgery", desc: "Major surgery within last 6 months" },
    { icon: "🍺", title: "Alcohol", desc: "Consumed alcohol within 24 hours" },
    { icon: "🩸", title: "Recent Donation", desc: "Donated blood within last 3 months" },
  ];

  const steps = [
    { num: "01", title: "Registration", desc: "Fill in your personal details and health information at the blood bank." },
    { num: "02", title: "Health Screening", desc: "A quick check of your blood pressure, hemoglobin, weight and temperature." },
    { num: "03", title: "Sample Testing", desc: "Staff collect a small sample and test for HIV, HBsAg, HCV, Syphilis, and Malaria before any full donation." },
    { num: "04", title: "Donation", desc: "Only after a negative sample result will you be called for the full donation. The donation itself takes about 8–10 minutes." },
    { num: "05", title: "Refreshments", desc: "Rest for 10–15 minutes and enjoy refreshments provided by the blood bank." },
  ];

  return (
    <div className="elig-page">

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
            <div className="logo-badge gov">🏛️</div>
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

      {/* Hero */}
      <div className="elig-hero">
        <div className="elig-hero-inner">
          <div className="elig-breadcrumb">
            <span className="elig-breadcrumb-link" onClick={() => navigate("/")}>🏠 Home</span>
            <span> › </span>
            <span>Eligibility Details</span>
          </div>
          <h1>Who Can Donate Blood?</h1>
          <p>Find out if you are eligible to become a blood donor and save lives</p>
        </div>
      </div>

      {/* Main Content */}
      <div className="elig-container">

        {/* Eligible Section */}
        <div className="elig-section-header">
          <div className="elig-badge green-badge">✓ Eligible Criteria</div>
          <h2>You CAN donate if you meet these criteria</h2>
        </div>
        <div className="elig-grid">
          {eligible.map((item, i) => (
            <div className="elig-card green-card" key={i}>
              <div className="elig-card-icon">{item.icon}</div>
              <div>
                <h4>{item.title}</h4>
                <p>{item.desc}</p>
              </div>
            </div>
          ))}
        </div>

        {/* Not Eligible Section */}
        <div className="elig-section-header" style={{ marginTop: "60px" }}>
          <div className="elig-badge red-badge">✕ Not Eligible Criteria</div>
          <h2>You CANNOT donate if any of these apply</h2>
        </div>
        <div className="elig-grid">
          {notEligible.map((item, i) => (
            <div className="elig-card red-card" key={i}>
              <div className="elig-card-icon">{item.icon}</div>
              <div>
                <h4>{item.title}</h4>
                <p>{item.desc}</p>
              </div>
            </div>
          ))}
        </div>

        {/* Donation Process */}
        <div className="elig-section-header" style={{ marginTop: "60px" }}>
          <div className="elig-badge blue-badge">📋 The Process</div>
          <h2>What happens when you donate?</h2>
        </div>
        <div className="elig-steps">
          {steps.map((step, i) => (
            <div className="elig-step" key={i}>
              <div className="elig-step-num">{step.num}</div>
              <div className="elig-step-content">
                <h4>{step.title}</h4>
                <p>{step.desc}</p>
              </div>
              {i < steps.length - 1 && <div className="elig-step-arrow">→</div>}
            </div>
          ))}
        </div>

        {/* CTA */}
        <div className="elig-cta-box">
          <div className="elig-cta-text">
            <h3>Ready to Save Lives?</h3>
            <p>If you meet the eligibility criteria, register as a donor today!</p>
          </div>
          <div className="elig-cta-buttons">
            <button className="elig-btn-primary" onClick={() => navigate("/register")}>
              Register as Donor 🩸
            </button>
            <button className="elig-btn-ghost" onClick={() => navigate("/")}>
              ← Back to Home
            </button>
          </div>
        </div>

      </div>

      {/* Footer */}
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
