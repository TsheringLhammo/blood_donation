import React from 'react';
import './ConfirmDialog.css';

const ConfirmDialog = ({ 
  isOpen, 
  title, 
  message, 
  confirmText = 'Confirm', 
  cancelText = 'Cancel',
  onConfirm, 
  onCancel,
  type = 'primary'
}) => {
  if (!isOpen) return null;

  return (
    <div className="confirm-overlay">
      <div className="confirm-dialog">
        <div className="confirm-header">
          <h3>{title}</h3>
          <button className="confirm-close" onClick={onCancel}>×</button>
        </div>
        
        <div className="confirm-body">
          <p>{message}</p>
        </div>
        
        <div className="confirm-actions">
          <button 
            className={`btn btn-${type}`}
            onClick={onConfirm}
          >
            {confirmText}
          </button>
          <button 
            className="btn btn-secondary"
            onClick={onCancel}
          >
            {cancelText}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ConfirmDialog;
