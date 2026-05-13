import React, { useState, useEffect } from 'react';
import { authFetch } from '../utils/auth';
import './AddBloodUnitModal.css';

const AddBloodUnitModal = ({ isOpen, onClose, appointmentId, onSuccess }) => {
  const [formData, setFormData] = useState({
    blood_type: '',
    units: 1,
    expiration_date: '',
    storage_location: '',
    notes: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Blood type options
  const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

  useEffect(() => {
    if (isOpen) {
      alert('Blood Unit Modal is Open!');
      setFormData({
        blood_type: '',
        units: 1,
        expiration_date: '',
        storage_location: '',
        notes: ''
      });
      setError('');
      setLoading(false);
    }
  }, [isOpen]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.blood_type || !formData.expiration_date) {
      setError('Please fill in all required fields');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await authFetch('backend/api/add_appointment_blood_unit.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          appointment_id: appointmentId,
          ...formData
        })
      });

      const result = await response.json();
      
      if (result.success) {
        onSuccess && onSuccess();
        onClose();
        setFormData({
          blood_type: '',
          units: 1,
          expiration_date: '',
          storage_location: '',
          notes: ''
        });
      } else {
        setError(result.message || 'Failed to add blood unit');
      }
    } catch (err) {
      setError('An error occurred while adding blood unit');
      console.error('Error adding blood unit:', err);
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="add-blood-unit-overlay" onClick={onClose}>
      <div className="add-blood-unit-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>Add Blood Unit</h2>
          <button className="close-btn" onClick={onClose}>×</button>
        </div>
        
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="form-row">
              <div className="form-group">
                <label htmlFor="blood_type">Blood Type *</label>
                <select
                  id="blood_type"
                  name="blood_type"
                  value={formData.blood_type}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select Blood Type</option>
                  {bloodTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
              
              <div className="form-group">
                <label htmlFor="units">Number of Units *</label>
                <input
                  type="number"
                  id="units"
                  name="units"
                  value={formData.units}
                  onChange={handleChange}
                  min="1"
                  required
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="expiration_date">Expiration Date *</label>
                <input
                  type="date"
                  id="expiration_date"
                  name="expiration_date"
                  value={formData.expiration_date}
                  onChange={handleChange}
                  min={new Date().toISOString().split('T')[0]}
                  required
                />
              </div>
              
              <div className="form-group">
                <label htmlFor="storage_location">Storage Location *</label>
                <input
                  type="text"
                  id="storage_location"
                  name="storage_location"
                  value={formData.storage_location}
                  onChange={handleChange}
                  placeholder="e.g., Main Blood Bank Fridge"
                  required
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group full-width">
                <label htmlFor="notes">Notes</label>
                <textarea
                  id="notes"
                  name="notes"
                  value={formData.notes}
                  onChange={handleChange}
                  rows="3"
                  placeholder="Additional notes about this blood unit..."
                />
              </div>
            </div>

            {error && (
              <div className="error-message">
                {error}
              </div>
            )}

            <div className="form-actions">
              <button type="button" className="btn-cancel" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="btn-submit" disabled={loading}>
                {loading ? 'Adding...' : 'Add Blood Unit'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default AddBloodUnitModal;
