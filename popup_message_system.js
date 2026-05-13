// Modern Popup Message System for Blood Bank Management
class PopupMessageSystem {
    constructor() {
        this.notifications = [];
        this.container = null;
        this.init();
    }

    init() {
        this.createContainer();
        this.addStyles();
        this.setupEventListeners();
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'popup-message-system';
        this.container.className = 'popup-message-system';
        document.body.appendChild(this.container);
    }

    addStyles() {
        const styles = `
            #popup-message-system {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                pointer-events: none;
            }

            .popup-message {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
                margin-bottom: 15px;
                min-width: 350px;
                max-width: 450px;
                opacity: 0;
                transform: translateX(100%) scale(0.8);
                transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                pointer-events: auto;
                overflow: hidden;
                position: relative;
            }

            .popup-message.show {
                opacity: 1;
                transform: translateX(0) scale(1);
            }

            .popup-message::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0));
                border-radius: 16px 16px 0 0;
                pointer-events: none;
            }

            .popup-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px 25px;
                border-radius: 16px 16px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
            }

            .popup-title {
                font-size: 18px;
                font-weight: 600;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .popup-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                background: rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(10px);
            }

            .popup-close {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                transition: all 0.3s ease;
                opacity: 0.8;
            }

            .popup-close:hover {
                opacity: 1;
                background: rgba(255, 255, 255, 0.3);
                transform: rotate(90deg);
            }

            .popup-body {
                padding: 25px;
            }

            .popup-content {
                display: flex;
                gap: 20px;
                align-items: flex-start;
            }

            .popup-message-text {
                flex: 1;
                color: #333;
                line-height: 1.6;
                font-size: 15px;
            }

            .popup-timestamp {
                font-size: 12px;
                color: #888;
                text-align: right;
                margin-top: 10px;
            }

            .popup-actions {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-top: 20px;
            }

            .popup-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                min-width: 100px;
            }

            .popup-btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .popup-btn-secondary {
                background: #6c757d;
                color: white;
            }

            .popup-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }

            .popup-btn:active {
                transform: translateY(0);
            }

            /* Success theme */
            .popup-message.success .popup-header {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            }

            .popup-message.success .popup-icon {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
            }

            /* Warning theme */
            .popup-message.warning .popup-header {
                background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            }

            .popup-message.warning .popup-icon {
                background: linear-gradient(135deg, #ffc107, #ff9800);
                color: white;
            }

            /* Error theme */
            .popup-message.error .popup-header {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            }

            .popup-message.error .popup-icon {
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
            }

            /* Info theme */
            .popup-message.info .popup-header {
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            }

            .popup-message.info .popup-icon {
                background: linear-gradient(135deg, #17a2b8, #138496);
                color: white;
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .popup-message {
                    min-width: 300px;
                    max-width: 90%;
                    margin: 10px;
                }

                .popup-content {
                    flex-direction: column;
                    gap: 15px;
                }

                .popup-actions {
                    flex-direction: column;
                }

                .popup-btn {
                    width: 100%;
                }
            }

            /* Accessibility */
            .popup-message:focus-within {
                outline: 2px solid #667eea;
                outline-offset: 2px;
            }

            /* Animation variants */
            .popup-message.slide-in {
                animation: slideInRight 0.5s ease-out;
            }

            .popup-message.fade-in {
                animation: fadeIn 0.4s ease-out;
            }

            .popup-message.bounce-in {
                animation: bounceIn 0.6s ease-out;
            }

            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            @keyframes bounceIn {
                0% {
                    opacity: 0;
                    transform: scale(0.3) translateY(-50px);
                }
                50% {
                    opacity: 1;
                    transform: scale(1.05) translateY(0);
                }
                100% {
                    transform: scale(1) translateY(0);
                }
            }
        `;

        const styleSheet = document.createElement('style');
        styleSheet.textContent = styles;
        document.head.appendChild(styleSheet);
    }

    setupEventListeners() {
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAll();
            }
        });

        // Close on background click
        document.addEventListener('click', (e) => {
            if (e.target === this.container) {
                this.closeAll();
            }
        });
    }

    show(options) {
        const {
            title = 'Notification',
            message = '',
            type = 'info',
            duration = 5000,
            persistent = false,
            actions = [],
            animation = 'slide-in'
        } = options;

        const notification = this.createNotification(title, message, type, actions);
        this.container.appendChild(notification);

        // Show with animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Auto close if not persistent
        if (!persistent && duration > 0) {
            setTimeout(() => {
                this.close(notification);
            }, duration);
        }

        // Add to notifications array
        this.notifications.push({
            id: Date.now(),
            title,
            message,
            type,
            timestamp: new Date(),
            persistent
        });

        return notification;
    }

    createNotification(title, message, type, actions) {
        const notification = document.createElement('div');
        notification.className = `popup-message ${type} ${this.getAnimationClass()}`;
        
        const iconMap = {
            success: '✅',
            warning: '⚠️',
            error: '❌',
            info: 'ℹ️'
        };

        notification.innerHTML = `
            <div class="popup-header">
                <div class="popup-title">
                    <div class="popup-icon">${iconMap[type] || iconMap.info}</div>
                    ${title}
                </div>
                <button class="popup-close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</button>
            </div>
            <div class="popup-body">
                <div class="popup-content">
                    <div class="popup-message-text">${message}</div>
                    <div class="popup-timestamp">${new Date().toLocaleString()}</div>
                </div>
                ${actions.length > 0 ? `
                    <div class="popup-actions">
                        ${actions.map(action => 
                            `<button class="popup-btn popup-btn-primary" onclick="${action.onclick}">${action.text}</button>`
                        ).join('')}
                    </div>
                ` : ''}
            </div>
        `;

        return notification;
    }

    close(notification) {
        if (notification) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    closeAll() {
        const notifications = this.container.querySelectorAll('.popup-message');
        notifications.forEach(notification => {
            this.close(notification);
        });
    }

    getAnimationClass() {
        const animations = ['slide-in', 'fade-in', 'bounce-in'];
        return animations[Math.floor(Math.random() * animations.length)];
    }

    // Predefined notification types
    showSuccess(title, message, options = {}) {
        return this.show({
            title,
            message,
            type: 'success',
            duration: options.duration || 4000,
            animation: options.animation || 'slide-in',
            ...options
        });
    }

    showWarning(title, message, options = {}) {
        return this.show({
            title,
            message,
            type: 'warning',
            duration: options.duration || 5000,
            animation: options.animation || 'bounce-in',
            ...options
        });
    }

    showError(title, message, options = {}) {
        return this.show({
            title,
            message,
            type: 'error',
            duration: options.duration || 6000,
            animation: options.animation || 'fade-in',
            persistent: options.persistent || false,
            ...options
        });
    }

    showInfo(title, message, options = {}) {
        return this.show({
            title,
            message,
            type: 'info',
            duration: options.duration || 4000,
            animation: options.animation || 'slide-in',
            ...options
        });
    }

    // Specialized notifications
    showDonorStatusUpdate(donorName, oldStatus, newStatus) {
        return this.show({
            title: '👤 Donor Status Updated',
            message: `<strong>${donorName}</strong><br><br>Status changed from <em>${oldStatus}</em> to <strong>${newStatus}</strong>`,
            type: 'info',
            actions: [
                {
                    text: 'View Details',
                    onclick: `viewDonorDetails('${donorName}')`
                }
            ]
        });
    }

    showAppointmentReminder(donorName, appointmentDate, appointmentTime) {
        return this.show({
            title: '📅 Appointment Reminder',
            message: `<strong>${donorName}</strong> has an appointment scheduled:<br><br><strong>Date:</strong> ${appointmentDate}<br><strong>Time:</strong> ${appointmentTime}`,
            type: 'warning',
            actions: [
                {
                    text: 'Confirm Appointment',
                    onclick: `confirmAppointment('${donorName}')`
                },
                {
                    text: 'Reschedule',
                    onclick: `rescheduleAppointment('${donorName}')`
                }
            ]
        });
    }

    showTestResults(donorName, results) {
        const resultText = Object.entries(results)
            .map(([key, value]) => `${key}: ${value}`)
            .join('<br>');
        
        return this.show({
            title: '🧪 Test Results Available',
            message: `<strong>${donorName}</strong><br><br>Test Results:<br><br>${resultText}`,
            type: 'info',
            duration: 8000
        });
    }

    showDeferralUpdate(donorName, deferralType, deferralPeriod) {
        return this.show({
            title: '⏸️ Deferral Status Update',
            message: `<strong>${donorName}</strong> has been deferred for <strong>${deferralPeriod}</strong><br><br>Type: <strong>${deferralType}</strong>`,
            type: 'warning',
            actions: [
                {
                    text: 'View Details',
                    onclick: `viewDeferralDetails('${donorName}')`
                },
                {
                    text: 'Contact Support',
                    onclick: `contactSupport('${donorName}')`
                }
            ]
        });
    }

    // Utility functions
    getNotificationHistory() {
        return this.notifications;
    }

    clearAll() {
        this.closeAll();
        this.notifications = [];
    }

    // Global functions for onclick handlers
    window.viewDonorDetails = (donorName) => {
        console.log(`Viewing details for ${donorName}`);
        // Implementation would open donor details modal
    };

    window.confirmAppointment = (donorName) => {
        this.showSuccess('Appointment Confirmed', `Appointment for ${donorName} has been confirmed`);
    };

    window.rescheduleAppointment = (donorName) => {
        this.showInfo('Reschedule Appointment', `Opening reschedule options for ${donorName}`);
    };

    window.viewDeferralDetails = (donorName) => {
        console.log(`Viewing deferral details for ${donorName}`);
        // Implementation would show deferral details modal
    };

    window.contactSupport = (donorName) => {
        this.showInfo('Contact Support', `Opening contact options for ${donorName}`);
    };
}

// Initialize the popup system
document.addEventListener('DOMContentLoaded', () => {
    window.popupSystem = new PopupMessageSystem();
    console.log('Popup Message System initialized');
});
