import React, { useEffect, useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import "./RegistrationSuccess.css";

export default function RegistrationSuccess() {
  const navigate = useNavigate();
  const location = useLocation();
  const [registration, setRegistration] = useState(null);

  useEffect(() => {
    const state = location.state;
    if (!state || !state.donorId) {
      navigate("/");
      return;
    }
    setRegistration(state);
  }, [location.state, navigate]);

  if (!registration) return null;

  return (
    <div className="reg-success-page">
      <div className="reg-success-container">
        {/* Success Header */}
        <div className="reg-success-header">
          <div className="reg-success-icon">✓</div>
          <h1>Registration Confirmed!</h1>
          <p className="reg-success-subtitle">Thank you for joining our donor community</p>
        </div>

        {/* Status Card */}
        <div className="reg-success-card">
          <h2>Your Current Status</h2>
          
          <div className="status-badge pending">
            ⏳ Pending Approval
          </div>

          <div className="status-details">
            <p className="status-label">What happens next:</p>
            <ul className="status-timeline">
              <li>
                <span className="timeline-number">1</span>
                <div>
                  <strong>We review your registration</strong>
                  <span>Usually completed within 24–48 hours</span>
                </div>
              </li>
              <li>
                <span className="timeline-number">2</span>
                <div>
                  <strong>You'll receive an email confirmation</strong>
                  <span>Check your inbox and spam folder</span>
                </div>
              </li>
              <li>
                <span className="timeline-number">3</span>
                <div>
                  <strong>Book a donation appointment</strong>
                  <span>Once approved, visit your dashboard to schedule</span>
                </div>
              </li>
            </ul>
          </div>

          <div className="reg-success-info-box">
            <p>
              <strong>Your Donor ID:</strong> {registration.donorId}
            </p>
            <p>
              <strong>Email on file:</strong> {registration.email}
            </p>
            <p>
              <strong>Estimated wait:</strong> 24–48 hours from now
            </p>
          </div>
        </div>

        {/* FAQ */}
        <div className="reg-success-faq">
          <h3>Frequently Asked Questions</h3>
          <div className="faq-item">
            <p className="faq-question">What does "Pending" mean?</p>
            <p className="faq-answer">
              Your registration is awaiting admin review. Once approved, you'll move to "Confirmed" status and can book appointments.
            </p>
          </div>
          <div className="faq-item">
            <p className="faq-question">How do I track my status?</p>
            <p className="faq-answer">
              Sign in to your dashboard anytime. Your status will update automatically, and we'll send you an email notification.
            </p>
          </div>
          <div className="faq-item">
            <p className="faq-question">What if I don't hear back soon?</p>
            <p className="faq-answer">
              If more than 48 hours have passed, call our helpline at <strong>1095</strong> for updates.
            </p>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="reg-success-actions">
          <button
            className="reg-btn primary"
            onClick={() => navigate("/dashboard")}
          >
            Go to My Dashboard
          </button>
          <button
            className="reg-btn secondary"
            onClick={() => navigate("/")}
          >
            Back to Home
          </button>
        </div>

        {/* Contact Info */}
        <div className="reg-success-footer">
          <p>Questions? Contact us at <strong>1095</strong> (24/7 helpline)</p>
          <p>Or email: <strong>donors@bloodbank.bt</strong></p>
        </div>
      </div>
    </div>
  );
}
