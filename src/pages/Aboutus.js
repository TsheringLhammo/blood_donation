import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import "./Aboutus.css";

export default function AboutUs() {
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

  const stats = [
    { num: "1986", label: "Established" },
    { num: "20+", label: "Blood Banks" },
    { num: "50K+", label: "Lives Saved" },
    { num: "1095", label: "Helpline" },
  ];

  const team = [
    { name: "Dr. Karma Wangchuk", role: "Director, NBTS", emoji: "👨‍⚕️" },
    { name: "Dr. Tshering Pemo", role: "Chief Medical Officer", emoji: "👩‍⚕️" },
    { name: "Mr. Dorji Namgyal", role: "Head of Operations", emoji: "👨‍💼" },
    { name: "Ms. Sonam Dema", role: "Public Health Specialist", emoji: "👩‍💼" },
  ];

  const values = [
    { icon: "❤️", title: "Compassion", desc: "We care deeply for every donor and recipient, treating each with dignity and respect." },
    { icon: "🔬", title: "Excellence", desc: "We maintain the highest medical standards in blood collection, testing and storage." },
    { icon: "🤝", title: "Community", desc: "We build strong partnerships with communities, organizations and volunteers across Bhutan." },
    { icon: "🛡️", title: "Safety", desc: "Every unit of blood is rigorously screened to ensure the safety of donors and recipients." },
    { icon: "🌱", title: "Sustainability", desc: "We work towards a self-sufficient blood supply that meets Bhutan's national needs." },
    { icon: "📢", title: "Awareness", desc: "We educate the public about the importance of voluntary blood donation year-round." },
  ];

  const milestones = [
    { year: "1986", event: "NBTS established under Ministry of Health" },
    { year: "1995", event: "First voluntary blood donation drive organized" },
    { year: "2003", event: "National blood screening program launched" },
    { year: "2010", event: "Expanded to all 20 Dzongkhags" },
    { year: "2016", event: "Achieved 100% voluntary blood donation" },
    { year: "2020", event: "Digital blood management system introduced" },
    { year: "2024", event: "50,000 lives saved milestone reached" },
  ];

  return (
    <div className="about-page">

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
            <li><Link to="/about" className="active" onClick={() => setIsNavOpen(false)}>About Us</Link></li>
            <li><Link to="/about-blood" onClick={() => setIsNavOpen(false)}>About Blood</Link></li>
            <li><Link to="/donating-blood" onClick={() => setIsNavOpen(false)}>Donating Blood</Link></li>
            <li><Link to="/public-information" onClick={() => setIsNavOpen(false)}>Information & Publications</Link></li>
          </ul>
        </div>
      </nav>

      {/* Hero */}
      <div className="about-hero">
        <div className="about-hero-inner">
          <div className="about-breadcrumb">
            <span className="about-breadcrumb-link" onClick={() => navigate("/")}>🏠 Home</span>
            <span> › </span>
            <span>About Us</span>
          </div>
          <h1>About NBTS Bhutan</h1>
          <p>Serving the nation with safe, quality blood since 1986</p>
        </div>
      </div>

      {/* Stats Bar */}
      <div className="about-stats-bar">
        {stats.map((s, i) => (
          <div className="about-stat-item" key={i}>
            <div className="about-stat-num">{s.num}</div>
            <div className="about-stat-label">{s.label}</div>
          </div>
        ))}
      </div>

      {/* Main Content */}
      <div className="about-container">

        {/* Mission & Vision */}
        <div className="about-mission-grid">
          <div className="about-mission-card red-mission">
            <div className="mission-icon">🎯</div>
            <h3>Our Mission</h3>
            <p>To ensure a safe, adequate and sustainable supply of blood and blood products for all patients in Bhutan, through the promotion of voluntary non-remunerated blood donation and the application of the highest standards of quality and safety.</p>
          </div>
          <div className="about-mission-card blue-mission">
            <div className="mission-icon">🔭</div>
            <h3>Our Vision</h3>
            <p>A Bhutan where every patient in need has access to safe and sufficient blood and blood products, supported by a strong culture of voluntary blood donation across all communities and dzongkhags.</p>
          </div>
        </div>

        {/* Who We Are */}
        <div className="about-section">
          <div className="about-section-label">Who We Are</div>
          <h2 className="about-section-title">Blood Transfusion Services</h2>
          <div className="about-text-grid">
            <div className="about-text-content">
              <p>The National Blood Transfusion Services (NBTS) of Bhutan is the official government body responsible for all aspects of blood transfusion services in the country. Operating under the Department of Medical Services, Ministry of Health, NBTS was established in 1986 to address the growing need for a safe, organized blood supply system.</p>
              <p>Over the decades, NBTS has grown from a single blood bank in Thimphu to a nationwide network covering all 20 Dzongkhags. We work tirelessly to collect, test, process and distribute blood to hospitals and health centers across Bhutan, ensuring that every patient who needs blood receives it safely and on time.</p>
              <p>Our work is made possible by the generosity of thousands of voluntary blood donors who selflessly give the gift of life to their fellow citizens. We are deeply grateful to every donor who has contributed to saving lives in Bhutan.</p>
            </div>
            <div className="about-image-card">
              <div className="about-image-placeholder">
                <div className="about-image-icon">🏥</div>
                <p>NBTS Headquarters</p>
                <span>Thimphu, Bhutan</span>
              </div>
            </div>
          </div>
        </div>

        {/* Core Values */}
        <div className="about-section">
          <div className="about-section-label">What We Stand For</div>
          <h2 className="about-section-title">Our Core Values</h2>
          <div className="about-values-grid">
            {values.map((v, i) => (
              <div className="about-value-card" key={i}>
                <div className="about-value-icon">{v.icon}</div>
                <h4>{v.title}</h4>
                <p>{v.desc}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Timeline */}
        <div className="about-section">
          <div className="about-section-label">Our Journey</div>
          <h2 className="about-section-title">Key Milestones</h2>
          <div className="about-timeline">
            {milestones.map((m, i) => (
              <div className={`about-timeline-item ${i % 2 === 0 ? "left" : "right"}`} key={i}>
                <div className="about-timeline-year">{m.year}</div>
                <div className="about-timeline-dot" />
                <div className="about-timeline-content">
                  <p>{m.event}</p>
                </div>
              </div>
            ))}
            <div className="about-timeline-line" />
          </div>
        </div>

        {/* Team */}
        <div className="about-section">
          <div className="about-section-label">Our People</div>
          <h2 className="about-section-title">Leadership Team</h2>
          <div className="about-team-grid">
            {team.map((t, i) => (
              <div className="about-team-card" key={i}>
                <div className="about-team-avatar">{t.emoji}</div>
                <h4>{t.name}</h4>
                <p>{t.role}</p>
              </div>
            ))}
          </div>
        </div>

        {/* CTA */}
        <div className="about-cta-box">
          <div className="about-cta-text">
            <h3>Join Us in Saving Lives</h3>
            <p>Become a blood donor or organize a camp in your community today.</p>
          </div>
          <div className="about-cta-buttons">
            <button className="about-btn-primary" onClick={() => navigate("/register")}>
              Register as Donor 🩸
            </button>
            <button className="about-btn-secondary" onClick={() => navigate("/camp")}>
              Organize a Camp 🏕️
            </button>
            <button className="about-btn-ghost" onClick={() => navigate("/")}>
              ← Back to Home
            </button>
          </div>
        </div>

      </div>

      {/* Footer */}
      <footer className="footer">
        <p>© 2026 <strong>Blood Transfusion Services</strong> · Ministry of Health, Bhutan</p>
        <p className="footer-hotline">Helpline: <span>1095</span> · Available 24/7</p>
      </footer>

    </div>
  );
}