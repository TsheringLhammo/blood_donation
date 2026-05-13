import React from 'react';
import './ViewDetailsModal.css';

const ViewDetailsModal = ({ isOpen, onClose, type, data }) => {
  if (!isOpen || !data) return null;

  const renderAppointmentDetails = () => (
    <div className="details-content">
      <div className="details-header">
        <h3>Appointment Details</h3>
      </div>
      <div className="details-body">
        <div className="detail-row">
          <span className="detail-label">Full Name:</span>
          <span className="detail-value">{data.full_name}</span>
        </div>
        <div className="detail-row">
          <span className="detail-label">Date:</span>
          <span className="detail-value">{data.preferred_date}</span>
        </div>
        <div className="detail-row">
          <span className="detail-label">Time:</span>
          <span className="detail-value">{data.preferred_time}</span>
        </div>
        <div className="detail-row">
          <span className="detail-label">Blood Bank:</span>
          <span className="detail-value">{data.blood_bank}</span>
        </div>
        <div className="detail-row">
          <span className="detail-label">Status:</span>
          <span className={`detail-value status-badge ${data.status}`}>
            {data.status}
          </span>
        </div>
        {data.email && (
          <div className="detail-row">
            <span className="detail-label">Email:</span>
            <span className="detail-value">{data.email}</span>
          </div>
        )}
        {data.phone && (
          <div className="detail-row">
            <span className="detail-label">Phone:</span>
            <span className="detail-value">{data.phone}</span>
          </div>
        )}
      </div>
    </div>
  );

  const renderCampDetails = () => (
    <div className="details-content">
      <div className="details-header">
        <h3>Camp Request Details</h3>
      </div>
      <div className="details-body">
        <div className="detail-row">
          <span className="detail-label">Organization:</span>
          <span className="detail-value">{data.organization_name}</span>
        </div>
        <div className="detail-row">
          <span className="detail-label">Date:</span>
          <span className="detail-value">{data.preferred_date}</span>
        </div>
        <div className="detail-row">
          <span className="detail-label">Status:</span>
          <span className={`detail-value status-badge ${data.status}`}>
            {data.status}
          </span>
        </div>
        {data.contact_person && (
          <div className="detail-row">
            <span className="detail-label">Contact Person:</span>
            <span className="detail-value">{data.contact_person}</span>
          </div>
        )}
        {data.phone && (
          <div className="detail-row">
            <span className="detail-label">Phone:</span>
            <span className="detail-value">{data.phone}</span>
          </div>
        )}
        {data.email && (
          <div className="detail-row">
            <span className="detail-label">Email:</span>
            <span className="detail-value">{data.email}</span>
          </div>
        )}
        {data.expected_participants && (
          <div className="detail-row">
            <span className="detail-label">Expected Participants:</span>
            <span className="detail-value">{data.expected_participants}</span>
          </div>
        )}
      </div>
    </div>
  );

  return (
    <div className="view-details-overlay" onClick={onClose}>
      <div className="view-details-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{type === 'appointment' ? 'Appointment Details' : 'Camp Request Details'}</h2>
          <button className="close-btn" onClick={onClose}>×</button>
        </div>
        <div className="modal-content">
          {type === 'appointment' ? renderAppointmentDetails() : renderCampDetails()}
        </div>
      </div>
    </div>
  );
};

export default ViewDetailsModal;
