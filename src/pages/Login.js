import React, { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import "./Login.css";
import { saveAuthSession, getApiBase } from "../utils/auth";

const API_BASE = getApiBase();

export default function Login() {
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: "", password: "" });
  const [resetForm, setResetForm] = useState({ email: "", newPassword: "", confirmPassword: "" });
  const [showPassword, setShowPassword] = useState(false);
  const [showResetPassword, setShowResetPassword] = useState(false);
  const [error, setError] = useState("");
  const [resetMessage, setResetMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [resetLoading, setResetLoading] = useState(false);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
    setError("");
  };

  const handleResetChange = (e) => {
    setResetForm({ ...resetForm, [e.target.name]: e.target.value });
    setResetMessage("");
  };

  const handleLogin = async () => {
    if (!form.email || !form.password) {
      setError("Please enter both email and password.");
      return;
    }
    setLoading(true);
    setError("");
    try {
      const res = await fetch(`${API_BASE}/api/login.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: form.email, password: form.password }),
      });
      const data = await res.json();
      if (data.success) {
        saveAuthSession({
          id:    data.id,
          name:  data.name,
          email: data.email,
          role:  data.role,
          phone: data.phone || "",
          profile_picture: data.profile_picture || "",
          assigned_blood_bank: data.assigned_blood_bank || "",
          position: data.position || "",
          employee_id: data.employee_id || "",
          address: data.address || "",
          city: data.city || "",
          dzongkhag: data.dzongkhag || "",
          date_of_birth: data.date_of_birth || "",
          emergency_contact_name: data.emergency_contact_name || "",
          emergency_contact_phone: data.emergency_contact_phone || "",
          token: data.token,
        });
        if (data.role === "admin")  navigate("/admin");
        else if (data.role === "doctor") navigate("/doctor");
        else if (data.role === "staff")  navigate("/staff");
        else navigate("/dashboard");
      } else {
        setError(data.message || "Invalid email or password. Please try again.");
        setLoading(false);
      }
    } catch {
      setError("Could not connect to the server. Please try again.");
      setLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter") handleLogin();
  };

  const handleBack = (e) => {
    e.preventDefault();
    if (window.history.length > 1) {
      navigate(-1);
      return;
    }
    navigate("/");
  };

  const handleResetPassword = async () => {
    if (!resetForm.email || !resetForm.newPassword || !resetForm.confirmPassword) {
      setResetMessage("Please fill all reset fields.");
      return;
    }

    if (resetForm.newPassword.length < 6) {
      setResetMessage("New password must be at least 6 characters.");
      return;
    }

    if (resetForm.newPassword !== resetForm.confirmPassword) {
      setResetMessage("New password and confirm password must match.");
      return;
    }

    setResetLoading(true);
    setResetMessage("");

    try {
      const res = await fetch(`${API_BASE}/api/reset_password.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: resetForm.email, newPassword: resetForm.newPassword }),
      });
      const data = await res.json();

      if (data.success) {
        setResetMessage("Password reset successful. You can sign in now.");
        setForm({ email: resetForm.email, password: "" });
        setShowResetPassword(false);
      } else {
        setResetMessage(data.message || "Could not reset password.");
      }
    } catch {
      setResetMessage("Could not connect to the server. Please try again.");
    } finally {
      setResetLoading(false);
    }
  };

  return (
    <div className="login-page">
      {/* Left Panel */}
      <div className="login-left">
        <div className="login-left-content">
          <div className="login-logo">
            <div className="login-logo-drop">🩸</div>
            <div>
              <div className="login-logo-title">Blood Transfusion Services</div>
              <div className="login-logo-sub">Ministry of Health, Bhutan</div>
            </div>
          </div>
          <h1 className="login-headline">Every drop<br /><em>saves a life.</em></h1>
          <p className="login-tagline">Sign in to manage your donations, book appointments, and track your impact.</p>
          <div className="login-stats">
            <div className="login-stat">
              <span className="login-stat-num">3,200+</span>
              <span className="login-stat-label">Registered Donors</span>
            </div>
            <div className="login-stat-div" />
            <div className="login-stat">
              <span className="login-stat-num">18</span>
              <span className="login-stat-label">Blood Banks</span>
            </div>
            <div className="login-stat-div" />
            <div className="login-stat">
              <span className="login-stat-num">9,800+</span>
              <span className="login-stat-label">Lives Saved</span>
            </div>
          </div>
        </div>
        <div className="login-left-circles">
          <div className="lc lc1" />
          <div className="lc lc2" />
          <div className="lc lc3" />
        </div>
      </div>

      {/* Right Panel — Form */}
      <div className="login-right">
        <div className="login-form-box">
          <button type="button" className="login-back-link" onClick={handleBack}>
            ← Back
          </button>

          <div className="login-form-header">
            <h2>Welcome back</h2>
            <p>Sign in to your account to continue</p>
          </div>

          {error && <div className="login-error">⚠ {error}</div>}

          <div className="login-field">
            <label>Email Address</label>
            <input
              type="email"
              name="email"
              value={form.email}
              onChange={handleChange}
              onKeyDown={handleKeyDown}
              placeholder="you@example.com"
              autoComplete="email"
            />
          </div>

          <div className="login-field">
            <label>Password</label>
            <div className="login-password-wrap">
              <input
                type={showPassword ? "text" : "password"}
                name="password"
                value={form.password}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                placeholder="Enter your password"
                autoComplete="current-password"
              />
              <button
                className="login-eye"
                onClick={() => setShowPassword(!showPassword)}
                type="button"
              >
                {showPassword ? "🙈" : "👁"}
              </button>
            </div>
          </div>

          <button
            className={`login-btn ${loading ? "loading" : ""}`}
            onClick={handleLogin}
            disabled={loading}
          >
            {loading ? <span className="login-spinner" /> : "Login →"}
          </button>

          <button
            className="login-forgot-link"
            type="button"
            onClick={() => {
              setShowResetPassword((prev) => !prev);
              setResetMessage("");
              setResetForm((prev) => ({ ...prev, email: form.email || prev.email }));
            }}
          >
            Forgot Password?
          </button>

          {showResetPassword && (
            <div className="login-reset-box">
              <p className="login-reset-title">Reset your password</p>

              <div className="login-field">
                <label>Account Email</label>
                <input
                  type="email"
                  name="email"
                  value={resetForm.email}
                  onChange={handleResetChange}
                  placeholder="you@example.com"
                />
              </div>

              <div className="login-field">
                <label>New Password</label>
                <input
                  type="password"
                  name="newPassword"
                  value={resetForm.newPassword}
                  onChange={handleResetChange}
                  placeholder="At least 6 characters"
                />
              </div>

              <div className="login-field">
                <label>Confirm New Password</label>
                <input
                  type="password"
                  name="confirmPassword"
                  value={resetForm.confirmPassword}
                  onChange={handleResetChange}
                  placeholder="Re-enter new password"
                />
              </div>

              {resetMessage && <div className="login-reset-message">{resetMessage}</div>}

              <button
                className={`login-btn ${resetLoading ? "loading" : ""}`}
                onClick={handleResetPassword}
                disabled={resetLoading}
                type="button"
              >
                {resetLoading ? <span className="login-spinner" /> : "Reset Password"}
              </button>
            </div>
          )}

          <div className="login-divider"><span>or</span></div>

          {process.env.NODE_ENV === "development" && (
            <div className="login-demo">
              <p className="login-demo-title">🔑 Demo Credentials</p>
              <div className="login-demo-row">
                <span>Donor:</span>
                <code>donor@gmail.com</code>
                <code>donor123</code>
              </div>
              <div className="login-demo-row">
                <span>Admin:</span>
                <code>admin@bts.bt</code>
                <code>admin123</code>
              </div>
              <div className="login-demo-row">
                <span>Doctor:</span>
                <code>doctor@bts.bt</code>
                <code>doctor123</code>
              </div>
              <div className="login-demo-row">
                <span>Staff:</span>
                <code>staff@bts.bt</code>
                <code>staff123</code>
              </div>
            </div>
          )}

          <p className="login-register-link">
            Don't have an account?{" "}
            <Link to="/register">Register as a Donor</Link>
          </p>

          <p className="login-home-link">
            <Link to="/" onClick={handleBack}>← Back to Home</Link>
          </p>
        </div>
      </div>
    </div>
  );
}