import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import "./Aboutblood.css";

export default function Aboutblood() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState(0);
  const [activeBloodGroup, setActiveBloodGroup] = useState(null);
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

  const tabs = [
    { icon: "🩸", label: "What is Blood?" },
    { icon: "🧬", label: "Components" },
    { icon: "🔵", label: "Blood Groups" },
    { icon: "❤️", label: "Why Donate?" },
    { icon: "📅", label: "How Often?" },
    { icon: "💡", label: "Fun Facts" },
  ];

  const components = [
    {
      icon: "🔴",
      name: "Red Blood Cells",
      short: "RBCs",
      color: "#E53935",
      percent: "45%",
      desc: "Red blood cells carry oxygen from your lungs to every cell in your body and return carbon dioxide back to your lungs to be exhaled. They contain hemoglobin, the protein that gives blood its red colour.",
      facts: ["Live about 120 days", "Most numerous blood cell", "Made in bone marrow"],
    },
    {
      icon: "⚪",
      name: "White Blood Cells",
      short: "WBCs",
      color: "#1A5FA8",
      percent: "1%",
      desc: "White blood cells are your body's defence system. They identify and destroy bacteria, viruses and other foreign invaders, protecting you from infection and disease.",
      facts: ["Live days to weeks", "Less than 1% of blood", "5 different types"],
    },
    {
      icon: "🟡",
      name: "Platelets",
      short: "Thrombocytes",
      color: "#F9A825",
      percent: "1%",
      desc: "Platelets are tiny cell fragments that rush to the site of a wound and clump together to form a plug, stopping bleeding. They are essential for wound healing.",
      facts: ["Live 8–10 days", "Vital for clotting", "Used in cancer treatment"],
    },
    {
      icon: "🟠",
      name: "Plasma",
      short: "Liquid Fraction",
      color: "#E65100",
      percent: "54%",
      desc: "Plasma is the yellowish liquid part of blood. It carries all the other blood cells, as well as nutrients, hormones, proteins and waste products throughout your body.",
      facts: ["92% water", "Contains antibodies", "Largest component of blood"],
    },
  ];

  const bloodGroups = [
    { type: "A+", canDonateTo: ["A+", "AB+"], canReceiveFrom: ["A+", "A-", "O+", "O-"], freq: "Common" },
    { type: "A-", canDonateTo: ["A+", "A-", "AB+", "AB-"], canReceiveFrom: ["A-", "O-"], freq: "Rare" },
    { type: "B+", canDonateTo: ["B+", "AB+"], canReceiveFrom: ["B+", "B-", "O+", "O-"], freq: "Common" },
    { type: "B-", canDonateTo: ["B+", "B-", "AB+", "AB-"], canReceiveFrom: ["B-", "O-"], freq: "Rare" },
    { type: "AB+", canDonateTo: ["AB+"], canReceiveFrom: ["All Types"], freq: "Universal Recipient", special: true },
    { type: "AB-", canDonateTo: ["AB+", "AB-"], canReceiveFrom: ["A-", "B-", "AB-", "O-"], freq: "Rarest" },
    { type: "O+", canDonateTo: ["A+", "B+", "AB+", "O+"], canReceiveFrom: ["O+", "O-"], freq: "Most Common" },
    { type: "O-", canDonateTo: ["All Types"], canReceiveFrom: ["O-"], freq: "Universal Donor", special: true },
  ];

  const whyDonate = [
    { icon: "🔪", title: "Surgeries", desc: "Surgeries of all types require large amounts of blood and blood products." },
    { icon: "🚑", title: "Accident Injuries", desc: "Trauma patients often need emergency blood transfusions to survive." },
    { icon: "🤱", title: "Childbirth", desc: "Complications during delivery can cause life-threatening blood loss." },
    { icon: "🎗️", title: "Cancer Treatment", desc: "Chemotherapy patients regularly need platelets and red blood cells." },
    { icon: "💊", title: "Severe Anaemia", desc: "Patients with anaemia depend on transfusions to maintain health." },
    { icon: "🧒", title: "Children", desc: "Children with blood disorders like thalassaemia need regular transfusions." },
  ];

  const facts = [
    { num: "5L", label: "Average blood in adult body" },
    { num: "7-8%", label: "Of total body weight is blood" },
    { num: "120", label: "Days a red blood cell lives" },
    { num: "3", label: "Lives saved per donation" },
    { num: "2M", label: "Blood cells made per second" },
    { num: "60K", label: "Miles of blood vessels in body" },
  ];

  return (
    <div className="blood-page">

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
            <li><Link to="/about-blood" className="active" onClick={() => setIsNavOpen(false)}>About Blood</Link></li>
            <li><Link to="/donating-blood" onClick={() => setIsNavOpen(false)}>Donating Blood</Link></li>
            <li><Link to="/public-information" onClick={() => setIsNavOpen(false)}>Information & Publications</Link></li>
          </ul>
        </div>
      </nav>

      {/* Hero */}
      <div className="blood-hero">
        <div className="blood-hero-inner">
          <div className="blood-breadcrumb">
            <span className="blood-breadcrumb-link" onClick={() => navigate("/")}>🏠 Home</span>
            <span> › </span>
            <span>About Blood</span>
          </div>
          <h1>About Blood</h1>
          <p>Understand the life-giving fluid that connects us all</p>
        </div>
      </div>

      {/* ── ARROW TAB NAVIGATION ── */}
      <div className="blood-tab-bar">
        <div className="blood-tabs">
          {tabs.map((tab, i) => (
            <button
              key={i}
              className={`blood-tab ${activeTab === i ? "blood-tab-active" : ""}`}
              onClick={() => setActiveTab(i)}
            >
              <span className="blood-tab-icon">{tab.icon}</span>
              <span className="blood-tab-label">{tab.label}</span>
              {activeTab === i && <span className="blood-tab-arrow">▼</span>}
            </button>
          ))}
        </div>
      </div>

      {/* ── TAB CONTENT ── */}
      <div className="blood-content">

        {/* TAB 0: What is Blood */}
        {activeTab === 0 && (
          <div className="blood-panel fade-in">
            <div className="blood-what-grid">
              <div className="blood-what-text">
                <div className="blood-panel-label">The Basics</div>
                <h2>What is Blood?</h2>
                <p>Blood is a vital fluid that flows through your body's network of arteries, veins and capillaries. It acts as the body's transport and communication system — carrying oxygen, nutrients, hormones and waste products to and from every cell.</p>
                <p>Without blood, organs fail within minutes. It is the reason the heart beats, the reason wounds heal, and the reason we stay alive. No artificial substitute can fully replace human blood — making voluntary donors irreplaceable.</p>
                <div className="blood-highlight-box">
                  <span className="blood-highlight-icon">💡</span>
                  <p>Blood makes up about <strong>7–8%</strong> of your total body weight. An average adult has approximately <strong>5 litres</strong> of blood.</p>
                </div>
              </div>
              <div className="blood-what-visual">
                <div className="blood-drop-visual">
                  <div className="blood-drop">🩸</div>
                  <div className="blood-drop-labels">
                    <div className="bdl bdl-1">Oxygen Transport</div>
                    <div className="bdl bdl-2">Immune Defence</div>
                    <div className="bdl bdl-3">Wound Healing</div>
                    <div className="bdl bdl-4">Nutrient Delivery</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* TAB 1: Components */}
        {activeTab === 1 && (
          <div className="blood-panel fade-in">
            <div className="blood-panel-label">Inside Your Blood</div>
            <h2>Components of Blood</h2>
            <p className="blood-panel-sub">Click on any component to learn more</p>
            <div className="blood-components-grid">
              {components.map((c, i) => (
                <div className="blood-component-card" key={i} style={{ "--accent": c.color }}>
                  <div className="bcc-icon">{c.icon}</div>
                  <div className="bcc-percent" style={{ color: c.color }}>{c.percent}</div>
                  <h3>{c.name}</h3>
                  <div className="bcc-short" style={{ background: c.color }}>{c.short}</div>
                  <p>{c.desc}</p>
                  <div className="bcc-facts">
                    {c.facts.map((f, j) => (
                      <span className="bcc-fact" key={j} style={{ borderColor: c.color, color: c.color }}>✓ {f}</span>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* TAB 2: Blood Groups */}
        {activeTab === 2 && (
          <div className="blood-panel fade-in">
            <div className="blood-panel-label">Blood Typing</div>
            <h2>Blood Groups</h2>
            <p className="blood-panel-sub">Click on a blood group to see donation compatibility</p>
            <div className="blood-groups-grid">
              {bloodGroups.map((bg, i) => (
                <div
                  key={i}
                  className={`blood-group-card ${bg.special ? "blood-group-special" : ""} ${activeBloodGroup === i ? "blood-group-selected" : ""}`}
                  onClick={() => setActiveBloodGroup(activeBloodGroup === i ? null : i)}
                >
                  <div className="bgc-type">{bg.type}</div>
                  <div className="bgc-freq">{bg.freq}</div>
                  <div className="bgc-arrow">{activeBloodGroup === i ? "▲" : "▼"}</div>
                </div>
              ))}
            </div>

            {activeBloodGroup !== null && (
              <div className="blood-compat-box fade-in">
                <h3>Blood Group <strong>{bloodGroups[activeBloodGroup].type}</strong> Compatibility</h3>
                <div className="blood-compat-grid">
                  <div className="blood-compat-item green-compat">
                    <div className="bc-title">✅ Can Donate To</div>
                    <div className="bc-types">
                      {bloodGroups[activeBloodGroup].canDonateTo.map((t, j) => (
                        <span className="bc-type-badge green-badge-bg" key={j}>{t}</span>
                      ))}
                    </div>
                  </div>
                  <div className="blood-compat-item blue-compat">
                    <div className="bc-title">💉 Can Receive From</div>
                    <div className="bc-types">
                      {bloodGroups[activeBloodGroup].canReceiveFrom.map((t, j) => (
                        <span className="bc-type-badge blue-badge-bg" key={j}>{t}</span>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* TAB 3: Why Donate */}
        {activeTab === 3 && (
          <div className="blood-panel fade-in">
            <div className="blood-panel-label">The Need</div>
            <h2>Why Blood Donation is Important</h2>
            <p className="blood-panel-sub">One donation can save up to 3 lives</p>
            <div className="blood-why-grid">
              {whyDonate.map((w, i) => (
                <div className="blood-why-card" key={i}>
                  <div className="bwc-icon">{w.icon}</div>
                  <h4>{w.title}</h4>
                  <p>{w.desc}</p>
                </div>
              ))}
            </div>
            <div className="blood-impact-box">
              <div className="blood-impact-stat">
                <span className="bis-num">3</span>
                <span className="bis-label">Lives saved per donation</span>
              </div>
              <div className="blood-impact-divider" />
              <div className="blood-impact-stat">
                <span className="bis-num">1 unit</span>
                <span className="bis-label">Donated every 2 seconds needed</span>
              </div>
              <div className="blood-impact-divider" />
              <div className="blood-impact-stat">
                <span className="bis-num">100%</span>
                <span className="bis-label">Bhutan's voluntary donation rate</span>
              </div>
            </div>
          </div>
        )}

        {/* TAB 4: How Often */}
        {activeTab === 4 && (
          <div className="blood-panel fade-in">
            <div className="blood-panel-label">Donation Frequency</div>
            <h2>How Often Can You Donate?</h2>
            <div className="blood-frequency-grid">
              <div className="blood-freq-card male-card">
                <div className="bfc-icon">👨</div>
                <h3>Men</h3>
                <div className="bfc-interval">Every <strong>3 Months</strong></div>
                <p>Men can donate whole blood every 3 months (12 weeks), as their iron stores replenish faster.</p>
              </div>
              <div className="blood-freq-card female-card">
                <div className="bfc-icon">👩</div>
                <h3>Women</h3>
                <div className="bfc-interval">Every <strong>4 Months</strong></div>
                <p>Women are advised to wait at least 4 months (16 weeks) between donations due to menstrual iron loss.</p>
              </div>
            </div>
            <div className="blood-types-freq">
              <h3>Frequency by Donation Type</h3>
              <div className="btf-grid">
                <div className="btf-item">
                  <div className="btf-type">Whole Blood</div>
                  <div className="btf-arrow">→</div>
                  <div className="btf-freq">Every 3–4 months</div>
                </div>
                <div className="btf-item">
                  <div className="btf-type">Platelets</div>
                  <div className="btf-arrow">→</div>
                  <div className="btf-freq">Every 2 weeks (max 24/year)</div>
                </div>
                <div className="btf-item">
                  <div className="btf-type">Plasma</div>
                  <div className="btf-arrow">→</div>
                  <div className="btf-freq">Every 4 weeks</div>
                </div>
                <div className="btf-item">
                  <div className="btf-type">Double Red Cells</div>
                  <div className="btf-arrow">→</div>
                  <div className="btf-freq">Every 6 months</div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* TAB 5: Fun Facts */}
        {activeTab === 5 && (
          <div className="blood-panel fade-in">
            <div className="blood-panel-label">Did You Know?</div>
            <h2>Amazing Facts About Blood</h2>
            <div className="blood-facts-grid">
              {facts.map((f, i) => (
                <div className="blood-fact-card" key={i}>
                  <div className="bfc-num">{f.num}</div>
                  <div className="bfc-label">{f.label}</div>
                </div>
              ))}
            </div>
            <div className="blood-facts-list">
              <div className="bfl-item">🩸 Blood is the only tissue that is liquid at body temperature.</div>
              <div className="bfl-item">🔬 A single drop of blood contains about 5 million red blood cells.</div>
              <div className="bfl-item">💉 The heart pumps about 2,000 gallons of blood per day.</div>
              <div className="bfl-item">🧬 O negative blood is given to newborns and trauma patients in emergencies.</div>
              <div className="bfl-item">🌡️ Blood maintains a constant temperature of about 38°C (100.4°F).</div>
              <div className="bfl-item">⏱️ It takes only 60 seconds for blood to travel around the entire body.</div>
            </div>
          </div>
        )}

        {/* Tab Navigation Arrows */}
        <div className="blood-nav-arrows">
          <button
            className="blood-nav-btn"
            onClick={() => setActiveTab((prev) => Math.max(0, prev - 1))}
            disabled={activeTab === 0}
          >
            ← Previous
          </button>
          <div className="blood-nav-dots">
            {tabs.map((_, i) => (
              <span
                key={i}
                className={`blood-nav-dot ${activeTab === i ? "dot-active" : ""}`}
                onClick={() => setActiveTab(i)}
              />
            ))}
          </div>
          <button
            className="blood-nav-btn"
            onClick={() => setActiveTab((prev) => Math.min(tabs.length - 1, prev + 1))}
            disabled={activeTab === tabs.length - 1}
          >
            Next →
          </button>
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