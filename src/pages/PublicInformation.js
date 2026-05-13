import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import "./Home.css";

const eligibilityChecklist = [
  "Age between 18 and 60 years",
  "Minimum weight of 45 kg",
  "Hemoglobin at or above 12.5 g/dL",
  "No blood donation in the last 3 months",
  "No fever, flu, or active infection today",
  "Final clearance by blood bank medical staff",
];

const donationSteps = [
  "Register and provide basic details",
  "Quick health screening by clinical staff",
  "Blood donation (usually 8 to 12 minutes)",
  "Rest and refreshments at the center",
  "Post-donation advice before leaving",
];

const beforeDonationTips = [
  "Sleep well the night before donation",
  "Eat a light meal 2 to 3 hours before",
  "Drink enough clean water",
  "Bring a valid ID card",
  "Avoid alcohol before donation",
];

const afterDonationTips = [
  "Rest at the center for 10 to 15 minutes",
  "Drink extra fluids for the day",
  "Keep bandage in place for at least 4 hours",
  "Avoid heavy lifting for 24 hours",
  "Contact the blood bank if dizziness continues",
];

const publications = [
  {
    title: "Donor Eligibility Guidelines (PDF)",
    fileName: "donor-eligibility-guidelines.pdf",
    url: "/publications/donor-eligibility-guidelines.pdf",
  },
  {
    title: "Blood Donation Process Leaflet (PDF)",
    fileName: "blood-donation-process-leaflet.pdf",
    url: "/publications/blood-donation-process-leaflet.pdf",
  },
  {
    title: "Annual Blood Services Report (PDF)",
    fileName: "annual-blood-services-report.pdf",
    url: "/publications/annual-blood-services-report.pdf",
  },
  {
    title: "Monthly Blood Demand Bulletin (PDF)",
    fileName: "monthly-blood-demand-bulletin.pdf",
    url: "/publications/monthly-blood-demand-bulletin.pdf",
  },
  {
    title: "Press Release and Campaign Updates (PDF)",
    fileName: "press-release-and-campaign-updates.pdf",
    url: "/publications/press-release-and-campaign-updates.pdf",
  },
  {
    title: "Blood Donation Camp Toolkit (PDF)",
    fileName: "blood-donation-camp-toolkit.pdf",
    url: "/publications/blood-donation-camp-toolkit.pdf",
  },
];

const faqItems = [
  {
    q: "Does donating blood hurt?",
    a: "You may feel a brief pin-prick during needle insertion, but most donors feel comfortable during the donation.",
  },
  {
    q: "How long does the full visit take?",
    a: "Most visits take around 45 to 60 minutes, including registration, screening, donation, and rest.",
  },
  {
    q: "How often can I donate blood?",
    a: "Whole blood donation is usually allowed every 3 months. Staff will confirm based on your health status.",
  },
  {
    q: "Is donated blood tested?",
    a: "Yes. All donated blood is screened according to national transfusion safety protocols before use.",
  },
];

export default function PublicInformation() {
  const navigate = useNavigate();
  const [isNavOpen, setIsNavOpen] = useState(false);
  const [publicationError, setPublicationError] = useState("");

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 900) {
        setIsNavOpen(false);
      }
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const handlePublicationDownload = async (item) => {
    setPublicationError("");

    try {
      const head = await fetch(item.url, { method: "HEAD" });
      if (!head.ok) {
        setPublicationError(`File not uploaded yet: ${item.fileName}. Add it to public/publications.`);
        return;
      }

      const link = document.createElement("a");
      link.href = item.url;
      link.setAttribute("download", item.fileName);
      link.setAttribute("target", "_blank");
      link.setAttribute("rel", "noreferrer");
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch {
      setPublicationError("Could not verify publication file. Please try again.");
    }
  };

  return (
    <div className="home">
      <div className="top-strip">
        <span>📞 For queries, contact your nearest blood bank</span>
        <Link to="/blood-banks">List of Blood Banks</Link>
        <button onClick={() => navigate("/login")}>Sign In</button>
      </div>

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
            <li><Link to="/public-information" className="active" onClick={() => setIsNavOpen(false)}>Information & Publications</Link></li>
          </ul>
        </div>
      </nav>

      <section className="publications-section">
        <div className="publications-container">
          <div className="section-label">Information & Publications</div>
          <h2 className="section-title">Help Center for Donors and Families</h2>

          <div className="quick-help-grid">
            <article className="quick-help-card emergency">
              <h3>Need Blood Urgently?</h3>
              <p>Contact your nearest blood bank immediately for emergency availability and support.</p>
              <p className="quick-help-strong">Helpline: 1095</p>
            </article>
            <article className="quick-help-card guidance">
              <h3>Need Donation Guidance?</h3>
              <p>Our team can help with eligibility checks, appointment booking, and center locations.</p>
              <button className="quick-help-btn" onClick={() => navigate("/donating-blood?tab=book")}>Get Donation Help</button>
            </article>
            <article className="quick-help-card location">
              <h3>Find a Blood Bank</h3>
              <p>View official blood bank locations and contact details across Bhutan.</p>
              <button className="quick-help-btn" onClick={() => navigate("/blood-banks")}>View Blood Banks</button>
            </article>
          </div>

          <div className="pub-grid pub-grid-two">
            <article className="pub-card">
              <h3>Donor Eligibility</h3>
              <ul>
                {eligibilityChecklist.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </article>
            <article className="pub-card">
              <h3>Donation Process</h3>
              <ol>
                {donationSteps.map((step) => (
                  <li key={step}>{step}</li>
                ))}
              </ol>
            </article>
          </div>

          <div className="pub-grid pub-grid-two">
            <article className="pub-card">
              <h3>Before You Donate</h3>
              <ul>
                {beforeDonationTips.map((tip) => (
                  <li key={tip}>{tip}</li>
                ))}
              </ul>
            </article>
            <article className="pub-card">
              <h3>After Donation Care</h3>
              <ul>
                {afterDonationTips.map((tip) => (
                  <li key={tip}>{tip}</li>
                ))}
              </ul>
            </article>
          </div>

          <article className="pub-card publication-list-card">
            <h3>Official Publications</h3>
            <p>Download approved PDFs below. Place the files in <strong>public/publications</strong> with matching names.</p>
            {publicationError && <p className="publication-error">{publicationError}</p>}
            <div className="publication-list">
              {publications.map((item) => (
                <button
                  key={item.title}
                  className="publication-item"
                  type="button"
                  onClick={() => handlePublicationDownload(item)}
                >
                  {item.title}
                  <span>Download</span>
                </button>
              ))}
            </div>
          </article>

          <article className="pub-card faq-card">
            <h3>Frequently Asked Questions</h3>
            <div className="faq-simple-list">
              {faqItems.map((faq) => (
                <div className="faq-simple-item" key={faq.q}>
                  <h4>{faq.q}</h4>
                  <p>{faq.a}</p>
                </div>
              ))}
            </div>
          </article>

          <div className="pub-bottom-cta">
            <div className="pub-bottom-copy">
              <span className="pub-bottom-kicker">Next step</span>
              <h3>Ready to Take the Next Step?</h3>
              <p>Register as a donor or book your appointment in a few minutes.</p>
            </div>
            <div className="pub-bottom-actions">
              <button onClick={() => navigate("/register")}>Register as Donor</button>
              <button className="ghost" onClick={() => navigate("/donating-blood?tab=book")}>Book Appointment</button>
            </div>
          </div>
        </div>
      </section>

      <footer className="footer">
        <p>© 2026 <strong>National Blood Transfusion Services</strong> · Ministry of Health, Bhutan</p>
        <p className="footer-hotline">Helpline: <span>1095</span> · Available 24/7</p>
      </footer>
    </div>
  );
}
