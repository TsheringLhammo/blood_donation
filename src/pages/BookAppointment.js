/**
 * BookAppointment.js
 * 
 * PURPOSE:
 *   Allow donors to book appointments
 *   Checks eligibility (not deferred, status is Confirmed)
 *   Prevents deferred donors from booking
 *   Shows available dates and blood banks
 */

import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authFetch } from '../utils/auth';
import './BookAppointment.css';

const BookAppointment = () => {
  const navigate = useNavigate();
  
  const [eligible, setEligible] = useState(null);
  const [eligibilityMessage, setEligibilityMessage] = useState('');
  const [profile, setProfile] = useState(null);
  const [bloodBanks, setBloodBanks] = useState([]);
  const [selectedBloodBank, setSelectedBloodBank] = useState('');
  const [appointmentDate, setAppointmentDate] = useState('');
  const [appointmentTime, setAppointmentTime] = useState('');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [error, setError] = useState('');

  // Fetch donor profile
  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const res = await authFetch(`get_my_profile.php?_ts=${Date.now()}`, {
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('Failed to fetch profile');
        const data = await res.json();
        
        if (data.success && data.data) {
          setProfile(data.data);
        }
      } catch (err) {
        setError('Could not load profile: ' + err.message);
      }
    };

    fetchProfile();
  }, []);

  // Check donor eligibility
  useEffect(() => {
    if (!profile) return;

    const checkEligibility = async () => {
      try {
        const res = await authFetch(`check_donor_eligibility.php?donor_id=${profile.id}&_ts=${Date.now()}`, {
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('Failed to check eligibility');
        const data = await res.json();
        
        if (data.success) {
          setEligible(data.eligible);
          setEligibilityMessage(data.message);
        } else {
          setEligible(false);
          setEligibilityMessage(data.message || 'Could not verify eligibility');
        }
      } catch (err) {
        setEligible(false);
        setEligibilityMessage('Error checking eligibility: ' + err.message);
      }
    };

    checkEligibility();
  }, [profile]);

  // Fetch blood banks
  useEffect(() => {
    const fetchBloodBanks = async () => {
      try {
        const res = await authFetch(`get_blood_banks.php?_ts=${Date.now()}`, {
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('Failed to fetch blood banks');
        const data = await res.json();
        
        if (data.success && Array.isArray(data.data)) {
          setBloodBanks(data.data);
        }
      } catch (err) {
        console.error('Error fetching blood banks:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchBloodBanks();
  }, []);

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!selectedBloodBank || !appointmentDate || !appointmentTime) {
      setError('Please fill in all required fields');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const res = await authFetch('book_appointment.php', {
        method: 'POST',
        body: JSON.stringify({
          blood_bank_id: selectedBloodBank,
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
          notes: notes
        })
      });

      if (!res.ok) throw new Error('Failed to book appointment');
      const data = await res.json();

      if (data.success) {
        setSuccessMessage(
          `Appointment booked successfully!\n` +
          `Date: ${appointmentDate}\n` +
          `Time: ${appointmentTime}\n` +
          `A confirmation email has been sent to ${profile.email}`
        );
        
        // Reset form
        setSelectedBloodBank('');
        setAppointmentDate('');
        setAppointmentTime('');
        setNotes('');

        // Redirect to dashboard after 2 seconds
        setTimeout(() => navigate('/dashboard'), 2000);
      } else {
        setError(data.message || 'Could not book appointment');
      }
    } catch (err) {
      setError('Error booking appointment: ' + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div className="booking-loading">Loading...</div>;
  }

  // ========================================
  // IF DONOR NOT ELIGIBLE
  // ========================================
  if (!eligible) {
    return (
      <div className="book-appointment">
        <div className="booking-error">
          <h2>❌ Cannot Book Appointment</h2>
          <p className="error-message">{eligibilityMessage}</p>
          
          {profile?.deferred_until && (
            <div className="deferral-notice">
              <h3>Your Deferral Details</h3>
              <p><strong>Reason:</strong> {profile.deferral_reason}</p>
              <p><strong>Can reapply on:</strong> {new Date(profile.deferred_until).toLocaleDateString()}</p>
              <p className="deferral-explanation">
                We appreciate your willingness to donate! Once your deferral period ends, 
                you'll be eligible to book an appointment again. Thank you for your patience.
              </p>
            </div>
          )}

          <button 
            className="btn btn-primary"
            onClick={() => navigate('/dashboard')}
          >
            Return to Dashboard
          </button>
        </div>
      </div>
    );
  }

  // ========================================
  // BOOKING FORM (IF ELIGIBLE)
  // ========================================
  return (
    <div className="book-appointment">
      <div className="booking-container">
        <h2>📅 Book an Appointment</h2>
        <p className="greeting">Welcome, {profile?.full_name}! You're eligible to donate.</p>

        {successMessage && (
          <div className="success-message">
            <strong>✅ Success!</strong>
            <p>{successMessage}</p>
          </div>
        )}

        {error && (
          <div className="error-alert">
            <strong>❌ Error</strong>
            <p>{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="booking-form">
          {/* Blood Bank Selection */}
          <div className="form-group">
            <label htmlFor="blood-bank">
              Blood Bank <span className="required">*</span>
            </label>
            <select
              id="blood-bank"
              value={selectedBloodBank}
              onChange={(e) => setSelectedBloodBank(e.target.value)}
              required
              className="form-control"
            >
              <option value="">-- Select a Blood Bank --</option>
              {bloodBanks.map((bank) => (
                <option key={bank.id} value={bank.id}>
                  {bank.name} - {bank.location}
                </option>
              ))}
            </select>
          </div>

          {/* Appointment Date */}
          <div className="form-group">
            <label htmlFor="date">
              Appointment Date <span className="required">*</span>
            </label>
            <input
              id="date"
              type="date"
              value={appointmentDate}
              onChange={(e) => setAppointmentDate(e.target.value)}
              required
              min={new Date().toISOString().split('T')[0]}
              className="form-control"
            />
            <small>Select a date at least 1 day from today</small>
          </div>

          {/* Appointment Time */}
          <div className="form-group">
            <label htmlFor="time">
              Appointment Time <span className="required">*</span>
            </label>
            <select
              id="time"
              value={appointmentTime}
              onChange={(e) => setAppointmentTime(e.target.value)}
              required
              className="form-control"
            >
              <option value="">-- Select a Time --</option>
              <option value="09:00">09:00 AM</option>
              <option value="10:00">10:00 AM</option>
              <option value="11:00">11:00 AM</option>
              <option value="12:00">12:00 PM</option>
              <option value="14:00">02:00 PM</option>
              <option value="15:00">03:00 PM</option>
              <option value="16:00">04:00 PM</option>
            </select>
          </div>

          {/* Optional Notes */}
          <div className="form-group">
            <label htmlFor="notes">Additional Notes (Optional)</label>
            <textarea
              id="notes"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Any special requirements or health concerns?"
              rows="3"
              className="form-control"
            />
          </div>

          {/* Form Actions */}
          <div className="form-actions">
            <button
              type="submit"
              disabled={submitting}
              className="btn btn-primary"
            >
              {submitting ? 'Booking...' : 'Confirm Appointment'}
            </button>
            <button
              type="button"
              onClick={() => navigate('/dashboard')}
              className="btn btn-secondary"
            >
              Cancel
            </button>
          </div>
        </form>

        {/* Booking Info */}
        <div className="booking-info">
          <h3>What to Expect</h3>
          <ul>
            <li>Arrive 10-15 minutes early</li>
            <li>Bring a valid ID (Bhutanese Citizens ID or Passport)</li>
            <li>Ensure you're well-hydrated before donation</li>
            <li>Avoid alcohol 24 hours before donation</li>
            <li>Eat a light meal 2-3 hours before appointment</li>
          </ul>
        </div>
      </div>
    </div>
  );
};

export default BookAppointment;
