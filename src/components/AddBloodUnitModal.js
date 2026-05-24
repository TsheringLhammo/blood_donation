import React, { useState, useEffect } from 'react';
import { authFetch } from '../utils/auth';
import './AddBloodUnitModal.css';

const AddBloodUnitModal = ({ isOpen, onClose, appointmentId, onSuccess }) => {
  const [formData, setFormData] = useState({
    blood_type: '',
    // components: array of { component_type, units, expiration_date }
    components: [
      { id: Date.now(), component_type: 'Whole Blood', units: 1, expiration_date: '' }
    ],
    storage_location: '',
    notes: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Blood type options
  const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

  // Component type options and suggested expiry (days)
  const componentOptions = [
    { value: 'Whole Blood', label: 'Whole Blood', suggestedDays: 35 },
    { value: 'Packed Red Cells (PRBC)', label: 'Packed Red Cells (PRBC)', suggestedDays: 42 },
    { value: 'Platelets', label: 'Platelets', suggestedDays: 5 },
    { value: 'Plasma', label: 'Plasma', suggestedDays: 365 }
  ];

  const totalUnits = formData.components.reduce((sum, r) => sum + (Number(r.units) || 0), 0);

  useEffect(() => {
    if (isOpen) {
      setFormData({
        blood_type: '',
        components: [
          { id: Date.now(), component_type: 'Whole Blood', units: 1, expiration_date: '' }
        ],
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

  // Component rows handlers
  const updateComponentRow = (id, field, value) => {
    setFormData(prev => ({
      ...prev,
      components: prev.components.map(r => r.id === id ? { ...r, [field]: value } : r)
    }));
  };

  const addComponentRow = () => {
    setFormData(prev => ({
      ...prev,
      components: [...prev.components, { id: Date.now(), component_type: 'Whole Blood', units: 1, expiration_date: '' }]
    }));
  };

  const removeComponentRow = (id) => {
    setFormData(prev => ({
      ...prev,
      components: prev.components.filter(r => r.id !== id)
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.blood_type) {
      setError('Please choose a blood type');
      return;
    }

    // Validate component rows
    for (const row of formData.components) {
      if (!row.component_type || !row.expiration_date || !row.units || Number(row.units) < 1) {
        setError('Each component row requires a component type, expiry date, and quantity >= 1');
        return;
      }
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
          blood_type: formData.blood_type,
          storage_location: formData.storage_location,
          notes: formData.notes,
          components: formData.components.map(r => ({ component_type: r.component_type, units: Number(r.units), expiration_date: r.expiration_date }))
        })
      });

      const result = await response.json();
      
      if (result.success) {
        onSuccess && onSuccess();
        onClose();
        setFormData({
          blood_type: '',
          components: [ { id: Date.now(), component_type: 'Whole Blood', units: 1, expiration_date: '' } ],
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

              <div className="form-group full-width">
                <label>Totals</label>
                <div className="totals-box">Total Units: <strong>{totalUnits} bag{totalUnits === 1 ? '' : 's'}</strong></div>
              </div>
            </div>

            <div className="components-table">
              <div className="components-header">
                <div>Component Type</div>
                <div>Qty (bags)</div>
                <div>Expiry Date</div>
                <div>Action</div>
              </div>

              {formData.components.map(row => (
                <div className="components-row" key={row.id}>
                  <div>
                    <select value={row.component_type} onChange={(e) => updateComponentRow(row.id, 'component_type', e.target.value)}>
                      {componentOptions.map(opt => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <input type="number" min="1" value={row.units} onChange={(e) => updateComponentRow(row.id, 'units', e.target.value)} />
                  </div>
                  <div>
                    <input type="date" value={row.expiration_date} onChange={(e) => updateComponentRow(row.id, 'expiration_date', e.target.value)} min={new Date().toISOString().split('T')[0]} />
                    <div className="suggested-expiry">Suggested: {/* optional suggestion could go here */}</div>
                  </div>
                  <div>
                    <button type="button" className="btn-remove-row" onClick={() => removeComponentRow(row.id)}>Remove Row</button>
                  </div>
                </div>
              ))}

              <div className="components-footer">
                <button type="button" className="btn-add-row" onClick={addComponentRow}>+ Add Component Row</button>
              </div>
            </div>

            <div className="form-row">
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

              <div className="form-group full-width">
                <label htmlFor="notes">Notes</label>
                <textarea id="notes" name="notes" value={formData.notes} onChange={handleChange} rows="3" placeholder="Additional notes..." />
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
                {loading ? 'Adding...' : `Add ${totalUnits} Unit${totalUnits === 1 ? '' : 's'}`}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default AddBloodUnitModal;
