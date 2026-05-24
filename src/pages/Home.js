import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import "./Home.css";
import { getStoredUser } from "../utils/auth";

export default function Home() {
  const navigate = useNavigate();
  const [isNavOpen, setIsNavOpen] = useState(false);
  const [user, setUser] = useState(null);

  // Fetch blood banks from shared localStorage
  const fetchBanksFromStorage = () => {
    const sharedKey = 'blood_banks_shared_v1';
    const storedData = localStorage.getItem(sharedKey);

    if (storedData) {
      try {
        const allBanks = JSON.parse(storedData);
        const activeBanks = allBanks.filter(b => String(b.status || 'active').toLowerCase() === 'active');
        console.log('Home.js: Loaded banks from localStorage:', activeBanks);
        return;
      } catch(e) {
        console.error('Home.js: Error parsing stored banks:', e);
      }
    }

    console.log('Home.js: No shared banks found in localStorage');
  };

  useEffect(() => {
    const storedUser = getStoredUser();
    setUser(storedUser);
  }, []);

  // Load banks and setup event listeners
  useEffect(() => {
    fetchBanksFromStorage();

    const onSharedChange = () => {
      console.log('Home.js: Storage event fired, reloading banks');
      fetchBanksFromStorage();
    };

    window.addEventListener('storage', onSharedChange);
    window.addEventListener('banks-updated', onSharedChange);

    return () => {
      window.removeEventListener('storage', onSharedChange);
      window.removeEventListener('banks-updated', onSharedChange);
    };
  }, []);

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 900) {
        setIsNavOpen(false);
      }
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  return (
    <div className="home">

      <div className="top-strip">
        <span>📞 For queries, contact your nearest blood bank</span>
        <Link to="/blood-banks">List of Blood Banks</Link>
        {user ? (
          <>
            <span>👤 {user.name}</span>
            <button onClick={() => {
              if (user.role === "admin") navigate("/admin");
              else if (user.role === "doctor") navigate("/doctor");
              else if (user.role === "staff") navigate("/staff");
              else navigate("/dashboard");
            }}>
              {user.role === "admin" ? "Admin Dashboard" : user.role === "doctor" ? "Doctor Dashboard" : user.role === "staff" ? "Staff Dashboard" : "Dashboard"}
            </button>
          </>
        ) : (
          <button onClick={() => navigate("/login")}>Sign In</button>
        )}
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
              <>
                <li><Link to="/about" onClick={() => setIsNavOpen(false)}>About Us</Link></li>
                <li><Link to="/about-blood" onClick={() => setIsNavOpen(false)}>About Blood</Link></li>
                <li><Link to="/donating-blood" onClick={() => setIsNavOpen(false)}>Donating Blood</Link></li>
                <li><Link to="/public-information" onClick={() => setIsNavOpen(false)}>Information & Publications</Link></li>
              </>
            {user && user.role === "donor" && (
              <>
                <li><Link to="/dashboard" onClick={() => setIsNavOpen(false)}>My Dashboard</Link></li>
                <li><Link to="/blood-banks" onClick={() => setIsNavOpen(false)}>Find Blood Banks</Link></li>
              </>
            )}
            {user && user.role === "doctor" && (
              <>
                <li><Link to="/doctor" onClick={() => setIsNavOpen(false)}>Submit Request</Link></li>
              </>
            )}
            {user && user.role === "staff" && (
              <>
                <li><Link to="/staff" onClick={() => setIsNavOpen(false)}>Process Requests</Link></li>
              </>
            )}
            {user && user.role === "admin" && (
              <>
                <li><Link to="/admin" onClick={() => setIsNavOpen(false)}>Admin Console</Link></li>
              </>
            )}
            {!user && (
              <li><Link to="/blood-banks" onClick={() => setIsNavOpen(false)}>Blood Banks</Link></li>
            )}
          </ul>
        </div>
      </nav>

      <section className="hero">
        <div className="hero-bg-pattern" />
        <div className="hero-glow" />
        <div className="hero-content">
          <div className="hero-pill">🌍 World Blood Donor Day — June 14</div>
          <h1 className="hero-title">
            Donate Blood.<br /><em>Save Lives.</em>
          </h1>
          <p className="hero-sub">Every drop counts. Be someone's reason to live today.</p>
          <div className="hero-cta">
            <button className="btn-hero-primary" onClick={() => navigate("/register")}>
              🩸 Register as Donor
            </button>
            <button className="btn-hero-ghost" onClick={() => navigate("/donating-blood?tab=book")}>
              📅 Book Appointment <span className="btn-arrow">→</span>
            </button>
          </div>
        </div>
        <div className="hero-stat-bar">
          <div className="hero-stats">
            <div className="stat">
              <div className="stat-num">20+</div>
              <div className="stat-label">Blood Banks Nationwide</div>
            </div>
            <div className="stat">
              <div className="stat-num">3 Months</div>
              <div className="stat-label">Donation Interval</div>
            </div>
            <div className="stat">
              <div className="stat-num">1095</div>
              <div className="stat-label">Helpline Number</div>
            </div>
            <div className="stat">
              <div className="stat-num">4</div>
              <div className="stat-label">Blood Types Needed</div>
            </div>
          </div>
        </div>
      </section>

      {/* About Us Section */}
      <section className="about-section">
        <div className="about-content">
          <div className="about-text">
            <div className="section-label">About Us</div>
            <h2 className="section-title">National Blood Transfusion Services</h2>
            <p className="about-description">
              Since 1986, the National Blood Transfusion Services (NBTS) has been at the forefront of ensuring safe, adequate, and accessible blood supply across Bhutan. We operate 20+ blood banks nationwide and have saved over 50,000 lives through voluntary blood donation programs.
            </p>
            <div className="about-stats-grid">
              <div className="about-stat">
                <div className="stat-num">1986</div>
                <div className="stat-label">Established</div>
              </div>
              <div className="about-stat">
                <div className="stat-num">20+</div>
                <div className="stat-label">Blood Banks</div>
              </div>
              <div className="about-stat">
                <div className="stat-num">50K+</div>
                <div className="stat-label">Lives Saved</div>
              </div>
              <div className="about-stat">
                <div className="stat-num">100%</div>
                <div className="stat-label">Voluntary Donation</div>
              </div>
            </div>
            <button className="read-more-btn" onClick={() => navigate("/about") }>
              Learn More About Us <span className="btn-arrow">→</span>
            </button>
          </div>
          <div className="about-values">
            <div className="value-item">
              <span className="value-icon">❤️</span>
              <div className="value-content">
                <h4>Compassion</h4>
                <p>We care deeply for every donor and recipient</p>
              </div>
            </div>
            <div className="value-item">
              <span className="value-icon">🔬</span>
              <div className="value-content">
                <h4>Excellence</h4>
                <p>Highest standards in blood services</p>
              </div>
            </div>
            <div className="value-item">
              <span className="value-icon">🤝</span>
              <div className="value-content">
                <h4>Community</h4>
                <p>Strong partnerships across Bhutan</p>
              </div>
            </div>
            <div className="value-item">
              <span className="value-icon">🛡️</span>
              <div className="value-content">
                <h4>Safety</h4>
                <p>Rigorous screening for donors and recipients</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Blood Banks Section intentionally removed */}

      {/* About Blood Section */}
      <section className="blood-info-section">
        <div className="blood-info-header">
          <div className="section-label">Learn About Blood</div>
          <h2 className="section-title">Understanding Blood & Blood Groups</h2>
          <p className="section-subtitle">Knowledge is power. Learn about blood types, components, and why donors are essential.</p>
        </div>

        <div className="blood-info-content">
          <div className="blood-components">
            <h3>Blood Components</h3>
            <div className="components-grid">
              <div className="component-card">
                <div className="component-icon">🔴</div>
                <h4>Red Blood Cells</h4>
                <p>Carry oxygen to your cells and return carbon dioxide. Live about 120 days.</p>
              </div>
              <div className="component-card">
                <div className="component-icon">⚪</div>
                <h4>White Blood Cells</h4>
                <p>Your body's defense system. Identify and destroy bacteria and viruses.</p>
              </div>
              <div className="component-card">
                <div className="component-icon">🟡</div>
                <h4>Platelets</h4>
                <p>Essential for wound healing. Form clots to stop bleeding. Live 8-10 days.</p>
              </div>
              <div className="component-card">
                <div className="component-icon">🟠</div>
                <h4>Plasma</h4>
                <p>Yellowish liquid carrying nutrients, hormones, and waste. 92% water.</p>
              </div>
            </div>
          </div>

          <div className="blood-groups">
            <h3>Blood Groups</h3>
            <p className="blood-groups-intro">There are 8 blood types. Know your type and who you can help!</p>
            <div className="blood-groups-grid">
              <div className="blood-group-card universal-donor">
                <div className="blood-type">O-</div>
                <div className="blood-group-label">Universal Donor</div>
                <div className="blood-group-desc">Can donate to all blood types</div>
              </div>
              <div className="blood-group-card universal-recipient">
                <div className="blood-type">AB+</div>
                <div className="blood-group-label">Universal Recipient</div>
                <div className="blood-group-desc">Can receive from all blood types</div>
              </div>
              <div className="blood-group-card">
                <div className="blood-type">A+/B+</div>
                <div className="blood-group-label">Most Common</div>
                <div className="blood-group-desc">More donors available</div>
              </div>
              <div className="blood-group-card">
                <div className="blood-type">A-/B-</div>
                <div className="blood-group-label">Rare Types</div>
                <div className="blood-group-desc">High demand, few donors</div>
              </div>
            </div>
          </div>
        </div>

        <button className="read-more-btn" onClick={() => navigate("/about-blood") }>
          View Complete Blood Information <span className="btn-arrow">→</span>
        </button>
      </section>

      <section className="cta-section">
        <div className="section-label">Get Involved</div>
        <div className="section-title">How Can We Help You?</div>
        <div className="cards-grid">

          <div className="cta-card card-green">
            <div className="card-icon">🤝</div>
            <h3>Want to become a blood donor?</h3>
            <button className="card-btn" onClick={() => navigate("/register") }>
              Register Now <span className="card-arrow">→</span>
            </button>
          </div>

          <div className="cta-card card-green">
            <div className="card-icon">📅</div>
            <h3>Ready to donate blood today?</h3>
            <button className="card-btn" onClick={() => navigate("/donating-blood?tab=book") }>
              Book Appointment <span className="card-arrow">→</span>
            </button>
          </div>

          <div className="cta-card card-blue">
            <div className="card-icon">❓</div>
            <h3>Who is eligible to donate blood?</h3>
            <button className="card-btn" onClick={() => navigate("/eligibility") }>
              View Eligibility <span className="card-arrow">→</span>
            </button>
          </div>

          <div className="cta-card card-blue">
            <div className="card-icon">🏕️</div>
            <h3>Want to organize a blood donation camp?</h3>
            <button className="card-btn" onClick={() => navigate("/camp") }>
              Submit Request <span className="card-arrow">→</span>
            </button>
          </div>

        </div>
      </section>

      <section className="info-section">
        <div className="info-grid">
          <div className="date-card">
            <div className="date-card-month">JUNE</div>
            <div className="date-card-day">14</div>
            <div className="date-card-label">World Blood Donor Day</div>
          </div>
          <div className="info-content">
            <div className="section-label">Annual Observance</div>
            <h2>World Blood<br />Donor Day 2026</h2>
            <div className="info-theme">Theme: "Blood Donation For All"</div>
            <p>
              Every year on June 14, Bhutan joins the global community in celebrating World Blood Donor Day.
              The Ministry of Health, in collaboration with the World Health Organization and Bank of Bhutan,
              leads nationwide advocacy and awareness activities.
            </p>
            <p>
              This year's theme — <strong>"Blood Donation For All"</strong> — drives activities across all
              dzongkhags, promoting voluntary donation, welcoming new donors, and fostering a spirit of
              community service that saves lives.
            </p>
          </div>
        </div>
      </section>

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
