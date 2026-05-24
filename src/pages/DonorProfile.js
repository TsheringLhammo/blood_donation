import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
/* eslint-disable no-unused-vars */
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import { authFetch, getStoredUser, saveAuthSession } from '../utils/auth';
import { maskCidNumber, titleCase } from '../utils/strings';
import './DonorProfile.css';

const bloodGroupOptions = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const _dzongkhags = ['Thimphu', 'Punakha', 'Wangdue Phodrang', 'Trongsa', 'Bumthang', 'Lhuentse', 'Mongar', 'Trashigang', 'Trashiyangtse', 'Samdrup Jongkhar', 'Sarpang', 'Chukha', 'Haa', 'Gasa', 'Paro', 'Zhemgang', 'Dagana', 'Tsirang'];

const initialForm = {
  full_name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  blood_group: '',
  gender: '',
  weight: '',
  address: '',
  city: '',
  dzongkhag: '',
  profile_picture: '',
  current_password: '',
  new_password: '',
  confirm_password: '',
};

export default function DonorProfile() {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [form, setForm] = useState(initialForm);
  const [_loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [imageError, setImageError] = useState('');
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [showPasswordSection, setShowPasswordSection] = useState(false);
  const fullNameRef = useRef(null);
  const emailRef = useRef(null);
  const phoneRef = useRef(null);
  const dobRef = useRef(null);

  useEffect(() => {
    const user = getStoredUser();
    if (!user?.token) {
      navigate('/login');
    }
  }, [navigate]);

  const normalizeProfile = useCallback((data) => {
    const donor = data || {};
    return {
      ...donor,
      blood_group: donor.blood_group || donor.blood_type || '',
      profile_picture: donor.profile_picture || '',
      cid_number_masked: donor.cid_number_masked || maskCidNumber(donor.cid_number || ''),
    };
  }, []);

  const fetchProfile = useCallback(async () => {
    setLoading(true);
    try {
      const res = await authFetch('get_donor_profile.php?_ts=' + Date.now(), { cache: 'no-store' });
      const data = await res.json();
      if (data.success) {
        const donor = normalizeProfile(data.data);
        setProfile(donor);
        setForm((prev) => ({
          ...prev,
          full_name: donor.full_name || '',
          email: donor.email || '',
          phone: donor.phone || '',
          date_of_birth: donor.date_of_birth || '',
          blood_group: donor.blood_group || '',
          gender: donor.gender || '',
          weight: donor.weight ?? '',
          address: donor.address || '',
          city: donor.city || '',
          dzongkhag: donor.dzongkhag || '',
          profile_picture: donor.profile_picture || '',
        }));
      } else {
        setError(data.message || 'Unable to load profile');
      }
    } catch (fetchError) {
      console.error(fetchError);
      setError('Unable to load profile');
    } finally {
      setLoading(false);
    }
  }, [normalizeProfile]);

  useEffect(() => {
    fetchProfile();
  }, [fetchProfile]);

  const calcAge = useCallback((dob) => {
    if (!dob) return '';
    const date = new Date(dob);
    if (Number.isNaN(date.getTime())) return '';
    return Math.max(0, new Date().getFullYear() - date.getFullYear() - (new Date(new Date().getFullYear(), date.getMonth(), date.getDate()) > new Date() ? 1 : 0));
  }, []);

  const age = useMemo(() => calcAge(form.date_of_birth), [calcAge, form.date_of_birth]);
  const avatarSrc = form.profile_picture || profile?.profile_picture || '';

  const handleChange = (event) => {
    const { name, value } = event.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

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
    reader.onload = () => setForm((prev) => ({ ...prev, profile_picture: String(reader.result || '') }));
    reader.readAsDataURL(file);
  };

  const saveProfile = async (event) => {
    event.preventDefault();
    setError('');
    setImageError('');

    if (showPasswordSection && (form.new_password || form.current_password || form.confirm_password)) {
      if (!form.current_password) {
        setError('Current password is required to change your password.');
        return;
      }
      if (!form.new_password) {
        setError('New password is required when changing your password.');
        return;
      }
      if (form.new_password !== form.confirm_password) {
        setError('New password and confirm password do not match.');
        return;
      }
    }

    setSaving(true);
    try {
      const response = await authFetch('backend/api/update_my_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          full_name: titleCase(form.full_name),
          email: form.email,
          phone: form.phone,
          date_of_birth: form.date_of_birth,
          blood_group: form.blood_group,
          gender: form.gender,
          weight: form.weight,
          address: form.address,
          city: form.city,
          dzongkhag: form.dzongkhag,
          profile_picture: form.profile_picture,
          current_password: showPasswordSection ? form.current_password : '',
          new_password: showPasswordSection ? form.new_password : '',
          confirm_password: showPasswordSection ? form.confirm_password : '',
        }),
      });

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.message || 'Failed to save profile');
      }

      // If backend returned updated user info, update stored session so topbar shows new name/email
      if (result.data) {
        try {
          const stored = getStoredUser() || {};
          const updated = { ...stored };
          if (result.data.full_name) updated.name = result.data.full_name;
          if (result.data.email) updated.email = result.data.email;
          saveAuthSession(updated);
        } catch (e) {
          // ignore
        }
      }

      toast.success('Profile updated successfully.');
      navigate('/dashboard');
    } catch (saveError) {
      console.error(saveError);
      setError(saveError.message || 'Failed to save profile');
    } finally {
      setSaving(false);
    }
  };

  const _profileCompleted = Boolean(form.full_name || form.email || form.phone);

  return (
    <div className="donor-profile-page">
      <div className="donor-profile-shell donor-profile-shell-compact">
        <div className="donor-profile-topbar">
          <button type="button" className="donor-profile-back" onClick={() => navigate('/dashboard')}>
            ← Back to Dashboard
          </button>
        </div>

        <div className="donor-profile-hero">
          <div className="donor-profile-hero-icon">◉</div>
          <div>
            <h1>Edit My Profile</h1>
            <p>Update your personal information</p>
          </div>
        </div>

        <form className="donor-profile-form" onSubmit={saveProfile}>
          <div className="donor-profile-grid">
            <aside className="donor-profile-avatar-card">
              <div className="donor-profile-avatar-ring">
                {avatarSrc ? <img src={avatarSrc} alt="Profile" className="donor-profile-avatar" /> : <div className="donor-profile-avatar-placeholder">👤</div>}
              </div>
              <label className="donor-profile-photo-button" htmlFor="donor_profile_photo">
                📷 Change Photo
              </label>
              <input id="donor_profile_photo" type="file" accept="image/*" onChange={handleImage} className="donor-profile-photo-input" />
              <div className="donor-profile-photo-hint">Click to upload<br />JPG, PNG (Max 2MB)</div>
              {imageError && <div className="donor-profile-inline-error">{imageError}</div>}
            </aside>

            <section className="donor-profile-fields-card">
              <div className="donor-profile-field-row">
                <div className="donor-profile-field-group">
                  <label>FULL NAME *</label>
                  <div className="donor-profile-field-with-action">
                    <input ref={fullNameRef} name="full_name" value={form.full_name} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-mini-button" onClick={() => fullNameRef.current?.focus()}>✎ Edit</button>
                  </div>
                </div>
                <div className="donor-profile-field-group">
                  <label>EMAIL *</label>
                  <div className="donor-profile-field-with-action">
                    <input ref={emailRef} name="email" type="email" value={form.email} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-mini-button" onClick={() => emailRef.current?.focus()}>✎ Edit</button>
                  </div>
                </div>
              </div>

              <div className="donor-profile-field-row">
                <div className="donor-profile-field-group">
                  <label>PHONE NUMBER *</label>
                  <div className="donor-profile-field-with-action">
                    <input ref={phoneRef} name="phone" value={form.phone} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-mini-button" onClick={() => phoneRef.current?.focus()}>✎ Edit</button>
                  </div>
                </div>
                <div className="donor-profile-field-group">
                  <label>DATE OF BIRTH</label>
                  <div className="donor-profile-field-with-action">
                    <input ref={dobRef} name="date_of_birth" type="date" value={form.date_of_birth} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-mini-button" onClick={() => dobRef.current?.focus()}>✎ Edit</button>
                  </div>
                </div>
              </div>

              <div className="donor-profile-field-row donor-profile-age-row">
                <div className="donor-profile-field-group">
                  <label>CID NUMBER</label>
                  <input readOnly value={profile?.cid_number_masked || 'Not available'} className="donor-profile-input donor-profile-input-readonly" />
                </div>
                <div className="donor-profile-field-group">
                  <label>AGE</label>
                  <input readOnly value={age === '' ? '' : `${age} years (auto-calculated)`} className="donor-profile-input donor-profile-input-readonly" />
                </div>
              </div>
            </section>
          </div>

          <section className="donor-profile-section donor-profile-section-soft">
            <div className="donor-profile-field-row three-up">
              <div className="donor-profile-field-group">
                <label>BLOOD GROUP *</label>
                <select name="blood_group" value={form.blood_group} onChange={handleChange} className="donor-profile-input donor-profile-select">
                  <option value="">Select</option>
                  {bloodGroupOptions.map((group) => <option key={group} value={group}>{group}</option>)}
                </select>
              </div>
              <div className="donor-profile-field-group">
                <label>GENDER *</label>
                <select name="gender" value={form.gender} onChange={handleChange} className="donor-profile-input donor-profile-select">
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div className="donor-profile-field-group donor-profile-weight-group">
                <label>WEIGHT *</label>
                <div className="donor-profile-weight-wrap">
                  <input name="weight" type="number" step="0.1" value={form.weight} onChange={handleChange} className="donor-profile-input" />
                  <span>kg</span>
                </div>
              </div>
            </div>
          </section>

          {/* Health cards removed as requested */}

          <section className="donor-profile-section donor-profile-password donor-profile-password-compact">
            <div className="donor-profile-section-title">
              <span>🔒</span>
              <div>
                <h2>CHANGE PASSWORD</h2>
                <p>(Optional)</p>
              </div>
            </div>

            {!showPasswordSection ? (
              <div className="donor-profile-password-toggle-row">
                <div className="donor-profile-password-note">Leave this closed if you do not want to change your password.</div>
                <button type="button" className="donor-profile-password-toggle" onClick={() => setShowPasswordSection(true)}>
                  Change Password
                </button>
              </div>
            ) : (
              <div className="donor-profile-password-grid">
                <div className="donor-profile-password-row">
                  <label>Current Password:</label>
                  <div className="donor-profile-password-input-wrap">
                    <input type={showCurrentPassword ? 'text' : 'password'} name="current_password" value={form.current_password} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-eye-button" onClick={() => setShowCurrentPassword((v) => !v)}>{showCurrentPassword ? 'Hide' : 'Show'}</button>
                  </div>
                </div>
                <div className="donor-profile-password-row">
                  <label>New Password:</label>
                  <div className="donor-profile-password-input-wrap">
                    <input type={showNewPassword ? 'text' : 'password'} name="new_password" value={form.new_password} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-eye-button" onClick={() => setShowNewPassword((v) => !v)}>{showNewPassword ? 'Hide' : 'Show'}</button>
                  </div>
                </div>
                <div className="donor-profile-password-row">
                  <label>Confirm Password:</label>
                  <div className="donor-profile-password-input-wrap">
                    <input type={showConfirmPassword ? 'text' : 'password'} name="confirm_password" value={form.confirm_password} onChange={handleChange} className="donor-profile-input" />
                    <button type="button" className="donor-profile-eye-button" onClick={() => setShowConfirmPassword((v) => !v)}>{showConfirmPassword ? 'Hide' : 'Show'}</button>
                  </div>
                </div>
                <div className="donor-profile-password-actions">
                  <button type="button" className="donor-profile-password-cancel" onClick={() => {
                    setShowPasswordSection(false);
                    setForm((prev) => ({ ...prev, current_password: '', new_password: '', confirm_password: '' }));
                  }}>
                    Cancel Password Change
                  </button>
                </div>
              </div>
            )}
          </section>

          {error && <div className="donor-profile-block-error">{error}</div>}
          <div className="donor-profile-actions">
            <button type="submit" className="donor-profile-save" disabled={saving}>{saving ? 'Saving...' : 'Save Changes'}</button>
            <button type="button" className="donor-profile-cancel" onClick={() => navigate('/dashboard')}>Cancel</button>
          </div>
        </form>
      </div>
    </div>
  );
}
