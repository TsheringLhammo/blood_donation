import React from 'react';
import './ConfirmationModal.css';

const ConfirmationModal = ({ isOpen, title, message, onConfirm, onCancel, confirmText = "Confirm", cancelText = "Cancel", type = "warning", isLoading = false }) => {
  if (!isOpen) return null;

  const getIcon = () => {
    switch (type) {
      case 'danger':
        return '⚠️';
      case 'success':
        return '✓';
      case 'info':
        return 'ℹ';
      default:
        return '?';
    }
  };

  const getColorClass = () => {
    switch (type) {
      case 'danger':
        return 'danger';
      case 'success':
        return 'success';
      case 'info':
        return 'info';
      default:
        return 'warning';
    }
  };

  return (
    <div className="confirmation-overlay" onClick={onCancel}>
      <div className="confirmation-modal" onClick={(e) => e.stopPropagation()}>
        <div className={`confirmation-header ${getColorClass()}`}>
          <div className="confirmation-icon">{getIcon()}</div>
          <h2 className="confirmation-title">{title}</h2>
        </div>

        <div className="confirmation-body">
          <p className="confirmation-message">{message}</p>
        </div>

        <div className="confirmation-footer">
          <button
            className="btn-confirm-cancel"
            onClick={onCancel}
            disabled={isLoading}
          >
            {cancelText}
          </button>
          <button
            className={`btn-confirm-submit ${getColorClass()}`}
            onClick={onConfirm}
            disabled={isLoading}
          >
            {isLoading ? '⏳ Processing...' : confirmText}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ConfirmationModal;
