import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import EditProfile from '../components/EditProfile.js';
import { authFetch } from '../utils/auth';

export default function AdminProfile() {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchProfile = useCallback(async () => {
    setLoading(true);
    try {
      const res = await authFetch('backend/api/get_my_profile.php?_ts=' + Date.now(), { cache: 'no-store' });
      let data = null;
      try {
        data = await res.json();
      } catch (error) {
        data = null;
      }

      if (data && data.success) {
        setProfile(data.data || null);
      } else {
        const local = localStorage.getItem('dev_admin_profile');
        if (local) {
          try {
            const parsed = JSON.parse(local);
            setProfile(parsed);
            toast.warn('Running in offline mode - using local profile');
          } catch {
            toast.error(data?.message || 'Could not load profile');
          }
        } else {
          toast.error(data?.message || 'Could not load profile');
        }
      }
    } catch (error) {
      console.error(error);
      const local = localStorage.getItem('dev_admin_profile');
      if (local) {
        try {
          const parsed = JSON.parse(local);
          setProfile(parsed);
          toast.warn('Backend unreachable - using local profile (offline)');
        } catch {
          toast.error('Could not load profile');
        }
      } else {
        toast.error('Could not load profile');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProfile();
  }, [fetchProfile]);

  const handleSave = (updated) => {
    setProfile(updated);
    navigate('/admin');
  };

  if (loading) return null;

  return (
    <EditProfile profile={profile} onSave={handleSave} onCancel={() => navigate('/admin')} fullPage={true} />
  );
}
