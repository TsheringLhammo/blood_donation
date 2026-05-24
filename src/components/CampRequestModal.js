import React, { useState } from 'react';
import './CampRequestModal.css';
import { toast } from 'react-toastify';
import { authFetch } from '../utils/auth';

const CampRequestModal = ({ isOpen, onClose, camp, onAccept, onReject, isLoading, onRefresh, onSave }) => {
  const [isEditing, setIsEditing] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [formData, setFormData] = useState({
    organization: '',
    date: '',
    contact_person: '',
    phone: '',
    email: '',
    expected_participants: '',
  });

  // Update formData when camp changes
  React.useEffect(() => {
    if (camp) {
      const newFormData = {
        organization: camp.organization_name || '',
        date: camp.preferred_date || '',
        contact_person: camp.contact_person || '',
        phone: String(camp.phone_number || '').trim(),
        email: camp.email || '',
        expected_participants: String(camp.expected_donors || '').trim(),
      };
      
      setFormData(newFormData);
      setIsEditing(false);
    }
  }, [camp, isOpen]);

  if (!isOpen || !camp) return null;

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSave = async () => {
    if (isSaving) return;
    
    try {
      setIsSaving(true);
      
      // Prepare the data to send
      const updateData = {
        id: camp.id,
        organization: formData.organization,
        contact_person: formData.contact_person,
        phone: formData.phone,
        email: formData.email,
        date: formData.date,
        expected_participants: formData.expected_participants
      };
      
      console.log('Sending update data:', updateData);
      
      // Send to backend using authFetch for proper auth handling
      const response = await authFetch('backend/api/update_camp_request.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(updateData)
      });
      
      console.log('Response status:', response.status);
      const result = await response.json();
      console.log('Response data:', result);
      
      if (result.success) {
        toast.success('✓ Changes saved successfully!');
        setIsEditing(false);
        
        // Call onSave callback if provided to update parent component
        if (onSave) {
          onSave(updateData);
        }
      } else {
        toast.error(result.message || 'Failed to save changes');
        console.error('Save error response:', result);
      }
    } catch (error) {
      console.error('Error saving changes:', error);
      toast.error('Failed to save changes: ' + error.message);
    } finally {
      setIsSaving(false);
    }
  };

  const handleAcceptClick = () => {
    onAccept(camp);
  };

  const handleRejectClick = () => {
    onReject(camp);
  };

  const statusColor = camp.status === 'confirmed' ? '#1b7f4f' : 
                      camp.status === 'rejected' ? '#dc3545' : 
                      '#f39c12';

  const statusBgColor = camp.status === 'confirmed' ? '#e8f5ee' : 
                        camp.status === 'rejected' ? '#fdedec' : 
                        '#fef8e8';

  const statusBorderColor = camp.status === 'confirmed' ? '#b7e5c4' : 
                            camp.status === 'rejected' ? '#f5b7b1' : 
                            '#fce4b6';

  return (
    <div className="camp-modal-overlay" onClick={onClose}>
      <div className="camp-modal" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="camp-modal-header">
          <div className="header-title">
            <h2>Camp Request Details</h2>
            <span 
              className="camp-status-badge"
              style={{
                background: statusBgColor,
                color: statusColor,
                borderColor: statusBorderColor
              }}
            >
              {camp.status?.charAt(0).toUpperCase() + camp.status?.slice(1) || 'Pending'}
            </span>
          </div>
          <div className="header-actions">
            {onRefresh && (
              <button 
                className="camp-modal-refresh"
                onClick={onRefresh}
                title="Refresh data from database"
              >
                🔄
              </button>
            )}
            <button className="camp-modal-close" onClick={onClose}>×</button>
          </div>
        </div>

        {/* Body */}
        <div className="camp-modal-body">
          {isEditing ? (
            // Edit Form
            <div className="camp-edit-form">
              <div className="form-group">
                <label htmlFor="organization">Organization Name</label>
                <input
                  id="organization"
                  type="text"
                  name="organization"
                  value={formData.organization}
                  onChange={handleInputChange}
                  placeholder="Enter organization name"
                  className="form-input"
                />
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="date">Camp Date</label>
                  <input
                    id="date"
                    type="date"
                    name="date"
                    value={formData.date}
                    onChange={handleInputChange}
                    className="form-input"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="expected_participants">Expected Participants</label>
                  <input
                    id="expected_participants"
                    type="number"
                    name="expected_participants"
                    value={formData.expected_participants}
                    onChange={handleInputChange}
                    placeholder="0"
                    className="form-input"
                  />
                </div>
              </div>

              <div className="form-group">
                <label htmlFor="contact_person">Contact Person</label>
                <input
                  id="contact_person"
                  type="text"
                  name="contact_person"
                  value={formData.contact_person}
                  onChange={handleInputChange}
                  placeholder="Enter contact person name"
                  className="form-input"
                />
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="phone">Phone Number</label>
                  <input
                    id="phone"
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleInputChange}
                    placeholder="Enter phone number"
                    className="form-input"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="email">Email Address</label>
                  <input
                    id="email"
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    placeholder="Enter email address"
                    className="form-input"
                  />
                </div>
              </div>
            </div>
          ) : (
            // View Mode
            <div className="camp-details-grid">
              <div className="detail-item">
                <span className="detail-label">📍 Organization</span>
                <span className="detail-value">{formData.organization || 'Not provided'}</span>
              </div>

              <div className="detail-item">
                <span className="detail-label">📅 Camp Date</span>
                <span className="detail-value">
                  {formData.date ? new Date(formData.date).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                  }) : 'Not provided'}
                </span>
              </div>

              <div className="detail-item">
                <span className="detail-label">👤 Contact Person</span>
                <span className="detail-value">{formData.contact_person || 'Not provided'}</span>
              </div>

              <div className="detail-item">
                <span className="detail-label">📞 Phone Number</span>
                <span className="detail-value">{formData.phone || 'Not provided'}</span>
              </div>

              <div className="detail-item">
                <span className="detail-label">📧 Email Address</span>
                <span className="detail-value">{formData.email || 'Not provided'}</span>
              </div>

              <div className="detail-item">
                <span className="detail-label">👥 Expected Participants</span>
                <span className="detail-value">{formData.expected_participants || 'Not provided'}</span>
              </div>
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="camp-modal-footer">
          <div className="footer-left">
            {!isEditing && camp.status === 'pending' && (
              <button 
                className="btn-edit"
                onClick={() => setIsEditing(true)}
                disabled={isLoading}
              >
                ✏️ Edit
              </button>
            )}
            {isEditing && (
              <>
                <button 
                  className="btn-cancel-edit"
                  onClick={() => setIsEditing(false)}
                  disabled={isSaving}
                >
                  Cancel
                </button>
                <button 
                  className="btn-save"
                  onClick={handleSave}
                  disabled={isSaving}
                >
                  {isSaving ? '⏳ Saving...' : 'Save Changes'}
                </button>
              </>
            )}
          </div>

          <div className="footer-right">
            {camp.status === 'pending' && !isEditing && (
              <>
                <button 
                  className="btn-reject"
                  onClick={handleRejectClick}
                  disabled={isLoading}
                >
                  ✗ Reject
                </button>
                <button 
                  className="btn-accept"
                  onClick={handleAcceptClick}
                  disabled={isLoading}
                >
                  {isLoading ? '⏳ Processing...' : '✓ Accept'}
                </button>
              </>
            )}
            {camp.status !== 'pending' && (
              <button 
                className="btn-close"
                onClick={onClose}
              >
                Close
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default CampRequestModal;
