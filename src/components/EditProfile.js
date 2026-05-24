import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { authFetch, getStoredUser, saveAuthSession } from '../utils/auth';
import { toast } from 'react-toastify';
import ConfirmDialog from './ConfirmDialog';
import './EditProfile.css';
import { titleCase } from '../utils/strings';

const EditProfile = ({ profile, onSave, onCancel, fullPage = true }) => {
  const currentUser = getStoredUser() || {};
  const currentUserSnapshot = JSON.stringify({
    email: currentUser.email || '',
    phone: currentUser.phone || '',
    profile_picture: currentUser.profile_picture || '',
    date_of_birth: currentUser.date_of_birth || '',
    address: currentUser.address || '',
    city: currentUser.city || '',
    dzongkhag: currentUser.dzongkhag || '',
    emergency_contact_name: currentUser.emergency_contact_name || '',
    emergency_contact_phone: currentUser.emergency_contact_phone || '',
    assigned_blood_bank: currentUser.assigned_blood_bank || '',
    position: currentUser.position || '',
    employee_id: currentUser.employee_id || '',
  });
  const localProfileKey = currentUser.role === 'admin' ? 'dev_admin_profile' : 'dev_staff_profile';
  const normalizeBloodBank = (value) => {
    const trimmed = String(value || '').trim();
    if (trimmed === 'Thimphu Blood Bank') return 'JDWNRH';
    return trimmed;
  };

  const [formData, setFormData] = useState({
    full_name: '',
    email: '',
    phone: '',
    profile_picture: '',
    date_of_birth: '',
    address: '',
    city: '',
    dzongkhag: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    assigned_blood_bank: '',
    position: '',
    employee_id: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [imageError, setImageError] = useState('');
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    if (profile) {
      setFormData({
        full_name: profile.full_name || '',
        email: profile.email || currentUser.email || '',
        phone: profile.phone || currentUser.phone || '',
        profile_picture: profile.profile_picture || currentUser.profile_picture || '',
        date_of_birth: profile.date_of_birth || currentUser.date_of_birth || '',
        address: profile.address || currentUser.address || '',
        city: profile.city || currentUser.city || '',
        dzongkhag: profile.dzongkhag || currentUser.dzongkhag || '',
        emergency_contact_name: profile.emergency_contact_name || currentUser.emergency_contact_name || '',
        emergency_contact_phone: profile.emergency_contact_phone || currentUser.emergency_contact_phone || '',
        assigned_blood_bank: normalizeBloodBank(profile.assigned_blood_bank || currentUser.assigned_blood_bank || ''),
        position: profile.position || currentUser.position || '',
        employee_id: profile.employee_id || currentUser.employee_id || ''
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [profile, currentUserSnapshot]);

  const avatarSrc = formData.profile_picture || profile?.profile_picture || '';

  const handleImage = (event) => {
    setImageError('');
    const file = event.target.files?.[0];
    if (!file) return;

    if (!/^image\/(png|jpe?g|gif)$/.test(file.type)) {
      setImageError('Only JPG, PNG, or GIF images are allowed.');
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      setImageError('Image must be 2MB or smaller.');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => setFormData((prev) => ({ ...prev, profile_picture: String(reader.result || '') }));
    reader.readAsDataURL(file);
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    // If we have a local offline profile (dev mode) prefer immediate save
    const hasLocal = !!localStorage.getItem(localProfileKey);
    if (hasLocal) {
      await handleConfirmSave();
      return;
    }
    setShowConfirmDialog(true);
  };

  const handleConfirmSave = async () => {
    setLoading(true);
    setError('');
    setShowConfirmDialog(false);
    try {
      // client-side phone validation: must be 8 digits and start with 16,17 or 77
      const phoneCandidate = (document.getElementById('phone')?.value || formData.phone || '').toString().trim();
      if (phoneCandidate !== '') {
        if (!/^(16|17|77)\d{6}$/.test(phoneCandidate)) {
          throw new Error('Phone must be 8 digits and start with 16, 17, or 77');
        }
      }
      // read current input values from DOM to ensure we capture user's edits
      const getVal = (id) => (document.getElementById(id)?.value ?? '');
      const payload = {
        full_name: titleCase(getVal('full_name') || formData.full_name || ''),
        email: getVal('email') || formData.email || '',
        phone: getVal('phone') || formData.phone || '',
        profile_picture: formData.profile_picture || '',
        date_of_birth: getVal('date_of_birth') || formData.date_of_birth || '',
        address: getVal('address') || formData.address || '',
        city: getVal('city') || formData.city || '',
        dzongkhag: getVal('dzongkhag') || formData.dzongkhag || '',
        emergency_contact_name: getVal('emergency_contact_name') || formData.emergency_contact_name || '',
        emergency_contact_phone: getVal('emergency_contact_phone') || formData.emergency_contact_phone || '',
        current_password: getVal('current_password') || '',
        new_password: getVal('new_password') || '',
        confirm_password: getVal('confirm_password') || ''
      };
      payload.assigned_blood_bank = normalizeBloodBank(getVal('assigned_blood_bank') || formData.assigned_blood_bank || '');
      payload.position = getVal('position') || formData.position || '';
      payload.employee_id = getVal('employee_id') || formData.employee_id || '';
      const res = await authFetch('backend/api/update_my_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res) throw new Error('No response from server');
      let data = null;
      try {
        data = await res.json();
      } catch (e) {
        data = null;
      }

      if (data && data.success) {
        const saved = data.data || payload;
        try { localStorage.setItem(localProfileKey, JSON.stringify(saved)); } catch {}
        // update stored authenticated user so header/profile icon reflects new picture/name
        try {
          const currentUser = getStoredUser() || {};
          const merged = {
            ...currentUser,
            name: saved.full_name || currentUser.name,
            email: saved.email || currentUser.email,
            phone: saved.phone || currentUser.phone,
            profile_picture: saved.profile_picture || currentUser.profile_picture,
            date_of_birth: saved.date_of_birth || currentUser.date_of_birth,
            address: saved.address || currentUser.address,
            city: saved.city || currentUser.city,
            dzongkhag: saved.dzongkhag || currentUser.dzongkhag,
            emergency_contact_name: saved.emergency_contact_name || currentUser.emergency_contact_name,
            emergency_contact_phone: saved.emergency_contact_phone || currentUser.emergency_contact_phone,
            assigned_blood_bank: normalizeBloodBank(saved.assigned_blood_bank || currentUser.assigned_blood_bank || ''),
            position: saved.position || currentUser.position,
            employee_id: saved.employee_id || currentUser.employee_id,
          };
          saveAuthSession(merged);
        } catch {}
        // update local form state and notify parent
        setFormData(prev => ({ ...prev, ...saved }));
        if (typeof onSave === 'function') onSave(saved);
        toast.success('Profile updated successfully');
      } else {
        // fallback: save locally for offline development
        try {
          localStorage.setItem(localProfileKey, JSON.stringify(payload));
          try {
            const currentUser = getStoredUser() || {};
            const merged = {
              ...currentUser,
              name: payload.full_name || currentUser.name,
              email: payload.email || currentUser.email,
              phone: payload.phone || currentUser.phone,
              profile_picture: payload.profile_picture || currentUser.profile_picture,
              date_of_birth: payload.date_of_birth || currentUser.date_of_birth,
              address: payload.address || currentUser.address,
              city: payload.city || currentUser.city,
              dzongkhag: payload.dzongkhag || currentUser.dzongkhag,
              emergency_contact_name: payload.emergency_contact_name || currentUser.emergency_contact_name,
              emergency_contact_phone: payload.emergency_contact_phone || currentUser.emergency_contact_phone,
              assigned_blood_bank: normalizeBloodBank(payload.assigned_blood_bank || currentUser.assigned_blood_bank || ''),
              position: payload.position || currentUser.position,
              employee_id: payload.employee_id || currentUser.employee_id,
            };
            saveAuthSession(merged);
          } catch {}
          setFormData(prev => ({ ...prev, ...payload }));
          if (typeof onSave === 'function') onSave(payload);
          toast.warn('Saved locally (offline mode) — backend unreachable');
        } catch (e) {
          throw new Error(data?.message || 'Failed to update profile');
        }
      }
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

  const bloodBanks = [
    'JDWNRH',
    'Punakha Blood Bank',
    'Wangdue Phodrang Blood Bank',
    'Trongsa Blood Bank',
    'Bumthang Blood Bank',
    'Mongar Blood Bank'
  ];
  const positions = [
    'Blood Bank Technician',
    'Laboratory Technician',
    'Staff Nurse',
    'Supervisor'
  ];

  const handleCancel = () => {
    if (typeof onCancel === 'function') return onCancel();
    navigate('/dashboard');
  };

  const FormFields = (
    <>
      <div className="edit-profile-grid">
        <aside className="edit-profile-avatar-card">
          <div className="edit-profile-avatar-ring">
            {avatarSrc ? <img src={avatarSrc} alt="Profile" className="edit-profile-avatar" /> : <div className="edit-profile-avatar-placeholder">👤</div>}
          </div>
          <label className="edit-profile-photo-button" htmlFor="edit_profile_photo">📷 Change Photo</label>
          <input id="edit_profile_photo" type="file" accept="image/*" onChange={handleImage} className="edit-profile-photo-input" />
          <div className="edit-profile-photo-hint">Click to upload\nJPG, PNG (Max 2MB)</div>
          {imageError && <div className="edit-profile-inline-error">{imageError}</div>}
        </aside>

        <div className="edit-profile-content">
          <div className="form-row">
            <div className="form-group">
              <label htmlFor="full_name">Full Name *</label>
              <input type="text" id="full_name" name="full_name" value={formData.full_name} onChange={handleChange} required className="form-control" />
            </div>
            <div className="form-group">
              <label htmlFor="email">Email *</label>
              <input type="email" id="email" name="email" value={formData.email} onChange={handleChange} required className="form-control" />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="phone">Phone Number *</label>
              <input type="tel" id="phone" name="phone" value={formData.phone} onChange={handleChange} required className="form-control" placeholder="e.g. 17654321" />
            </div>
            <div className="form-group">
              <label htmlFor="date_of_birth">Date of Birth</label>
              <input type="date" id="date_of_birth" name="date_of_birth" value={formData.date_of_birth} onChange={handleChange} className="form-control" />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="address">Address</label>
              <input type="text" id="address" name="address" value={formData.address} onChange={handleChange} className="form-control" />
            </div>
            <div className="form-group">
              <label htmlFor="city">City</label>
              <input type="text" id="city" name="city" value={formData.city} onChange={handleChange} className="form-control" />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="dzongkhag">Dzongkhag</label>
              <select id="dzongkhag" name="dzongkhag" value={formData.dzongkhag} onChange={handleChange} className="form-control">
                <option value="">Select Dzongkhag</option>
                {dzongkhags.map(dzongkhag => <option key={dzongkhag} value={dzongkhag}>{dzongkhag}</option>)}
              </select>
            </div>
          </div>

          <div className="profile-section-title">🏥 Work Information</div>
          <div className="form-row compact-row">
            <div className="form-group">
              <label htmlFor="assigned_blood_bank">Assigned Blood Bank *</label>
              <select id="assigned_blood_bank" name="assigned_blood_bank" value={formData.assigned_blood_bank} onChange={handleChange} className="form-control">
                <option value="">Select blood bank</option>
                {bloodBanks.map((bank) => <option key={bank} value={bank}>{bank}</option>)}
              </select>
            </div>
            <div className="form-group">
              <label htmlFor="position">Position / Role *</label>
              <select id="position" name="position" value={formData.position} onChange={handleChange} className="form-control">
                <option value="">Select position</option>
                {positions.map((position) => <option key={position} value={position}>{position}</option>)}
              </select>
            </div>
            <div className="form-group">
              <label htmlFor="employee_id">Employee ID (Optional)</label>
              <input type="text" id="employee_id" name="employee_id" value={formData.employee_id} onChange={handleChange} className="form-control" placeholder="EMP-2024-089" />
            </div>
          </div>

          <hr />
          <h3>Change Password (optional)</h3>
          <div className="form-row compact-row password-row">
            <div className="form-group">
              <label htmlFor="current_password">Current Password</label>
              <input type="password" id="current_password" name="current_password" className="form-control" />
            </div>
            <div className="form-group">
              <label htmlFor="new_password">New Password</label>
              <input type="password" id="new_password" name="new_password" className="form-control" />
            </div>
            <div className="form-group">
              <label htmlFor="confirm_password">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password" className="form-control" />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="emergency_contact_name">Emergency Contact Name</label>
              <input type="text" id="emergency_contact_name" name="emergency_contact_name" value={formData.emergency_contact_name} onChange={handleChange} className="form-control" />
            </div>
            <div className="form-group">
              <label htmlFor="emergency_contact_phone">Emergency Contact Phone</label>
              <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value={formData.emergency_contact_phone} onChange={handleChange} className="form-control" placeholder="e.g. 17123456" />
            </div>
          </div>
        </div>
      </div>
    </>
  );

  return (
    <>
      {fullPage ? (
        <div className="edit-profile-page">
          <div className="edit-profile-shell">
            <div className="edit-profile-hero">
              <div className="edit-profile-hero-icon">👤</div>
              <div>
                <h1>Edit Your Profile</h1>
                <p>Update your personal information</p>
              </div>
              <div className="edit-profile-hero-actions">
                <button type="button" className="edit-profile-back" onClick={handleCancel}>← Back to Dashboard</button>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="edit-profile-form edit-profile-form-full">
              {FormFields}

              {error && <div className="alert alert-danger">{error}</div>}

              <div className="form-actions">
                <button type="submit" className="btn btn-primary" disabled={loading}>{loading ? 'Saving...' : 'Save Changes'}</button>
                <button type="button" className="btn btn-secondary" onClick={handleCancel} disabled={loading}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      ) : (
        <div className="edit-profile-overlay">
          <div className="edit-profile-modal">
            <div className="edit-profile-header">
              <h2>Edit Your Profile</h2>
              <button className="close-btn" onClick={onCancel}>×</button>
            </div>

            <form onSubmit={handleSubmit} className="edit-profile-form">
              {FormFields}

              {error && <div className="alert alert-danger">{error}</div>}

              <div className="form-actions">
                <button type="submit" className="btn btn-primary" disabled={loading}>{loading ? 'Saving...' : 'Save Changes'}</button>
                <button type="button" className="btn btn-secondary" onClick={onCancel} disabled={loading}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

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
