import React, { useState, useEffect } from 'react';
import { authFetch } from '../utils/auth';
import ConfirmDialog from './ConfirmDialog';
import './EditProfile.css';

const EditProfile = ({ profile, onSave, onCancel }) => {
  const [formData, setFormData] = useState({
    full_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    address: '',
    city: '',
    dzongkhag: '',
    emergency_contact_name: '',
    emergency_contact_phone: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);

  // Initialize form data when profile changes
  useEffect(() => {
    if (profile) {
      setFormData({
        full_name: profile.full_name || '',
        email: profile.email || '',
        phone: profile.phone || '',
        date_of_birth: profile.date_of_birth || '',
        address: profile.address || '',
        city: profile.city || '',
        dzongkhag: profile.dzongkhag || '',
        emergency_contact_name: profile.emergency_contact_name || '',
        emergency_contact_phone: profile.emergency_contact_phone || ''
      });
    }
  }, [profile]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setShowConfirmDialog(true);
  };

  const handleConfirmSave = async () => {
    setLoading(true);
    setError('');
    setShowConfirmDialog(false);
    try {
      // Use authFetch so Authorization header is attached
      const res = await authFetch('backend/api/update_my_profile.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });

      if (!res) throw new Error('No response from server');

      const data = await res.json();
      if (!data.success) {
        throw new Error(data.message || 'Failed to update profile');
      }

      // Notify parent with updated profile
      onSave(data.data || formData);
    } catch (err) {
      console.error('Profile update error:', err);
      setError(err.message || 'Could not save profile');
    } finally {
      setLoading(false);
    }
  };

  const dzongkhags = [
    'Thimphu', 'Punakha', 'Wangdue Phodrang', 'Trongsa',
    'Bumthang', 'Lhuentse', 'Mongar', 'Trashigang',
    'Trashiyangtse', 'Samdrup Jongkhar', 'Sarpang',
    'Chukha', 'Haa', 'Gasa', 'Paro', 'Zhemgang', 'Dagana', 'Tsirang'
  ];

  return (
    <>
      <div className="edit-profile-overlay">
        <div className="edit-profile-modal">
          <div className="edit-profile-header">
            <h2>Edit Your Profile</h2>
            <button className="close-btn" onClick={onCancel}>×</button>
          </div>

          <form onSubmit={handleSubmit} className="edit-profile-form">
          <div className="form-row">
            <div className="form-group">
              <label htmlFor="full_name">Full Name *</label>
              <input
                type="text"
                id="full_name"
                name="full_name"
                value={formData.full_name}
                onChange={handleChange}
                required
                className="form-control"
              />
            </div>
            <div className="form-group">
              <label htmlFor="email">Email *</label>
              <input
                type="email"
                id="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                required
                className="form-control"
              />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="phone">Phone Number *</label>
              <input
                type="tel"
                id="phone"
                name="phone"
                value={formData.phone}
                onChange={handleChange}
                required
                className="form-control"
                placeholder="e.g. 17654321"
              />
            </div>
            <div className="form-group">
              <label htmlFor="date_of_birth">Date of Birth</label>
              <input
                type="date"
                id="date_of_birth"
                name="date_of_birth"
                value={formData.date_of_birth}
                onChange={handleChange}
                className="form-control"
              />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="address">Address</label>
              <input
                type="text"
                id="address"
                name="address"
                value={formData.address}
                onChange={handleChange}
                className="form-control"
              />
            </div>
            <div className="form-group">
              <label htmlFor="city">City</label>
              <input
                type="text"
                id="city"
                name="city"
                value={formData.city}
                onChange={handleChange}
                className="form-control"
              />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="dzongkhag">Dzongkhag</label>
              <select
                id="dzongkhag"
                name="dzongkhag"
                value={formData.dzongkhag}
                onChange={handleChange}
                className="form-control"
              >
                <option value="">Select Dzongkhag</option>
                {dzongkhags.map(dzongkhag => (
                  <option key={dzongkhag} value={dzongkhag}>
                    {dzongkhag}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="emergency_contact_name">Emergency Contact Name</label>
              <input
                type="text"
                id="emergency_contact_name"
                name="emergency_contact_name"
                value={formData.emergency_contact_name}
                onChange={handleChange}
                className="form-control"
              />
            </div>
            <div className="form-group">
              <label htmlFor="emergency_contact_phone">Emergency Contact Phone</label>
              <input
                type="tel"
                id="emergency_contact_phone"
                name="emergency_contact_phone"
                value={formData.emergency_contact_phone}
                onChange={handleChange}
                className="form-control"
                placeholder="e.g. 17123456"
              />
            </div>
          </div>

              {error && (
                <div className="alert alert-danger">
                  {error}
                </div>
              )}

          <div className="form-actions">
            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading}
            >
              {loading ? 'Saving...' : 'Save Changes'}
            </button>
            <button
              type="button"
              className="btn btn-secondary"
              onClick={onCancel}
              disabled={loading}
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

      {/* Test element to check if dialog should be showing */}
      {/* Confirm dialog handled below */}
      
      <ConfirmDialog
        isOpen={showConfirmDialog}
        title="Confirm Profile Update"
        message="Are you sure you want to update your profile information? This will save your changes to the system."
        confirmText="Save Changes"
        cancelText="Cancel"
        onConfirm={handleConfirmSave}
        onCancel={() => setShowConfirmDialog(false)}
        type="primary"
      />
    </>
  );
};

export default EditProfile;
