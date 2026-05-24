import React, { useState, useEffect, useCallback } from "react";
import { authFetch } from "../utils/auth";
import "./NotificationDropdown.css";

const NotificationDropdown = () => {
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifications, setNotifications] = useState([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const fetchUnreadCount = useCallback(async () => {
    try {
      setError("");
      const response = await authFetch("donor_notifications_count.php", {
        cache: "no-store",
      });
      const data = await response.json();
      if (data.success) {
        setUnreadCount(Number(data.unread_count || 0));
      } else {
        setError(data.message || "Unable to load notification count.");
      }
    } catch (requestError) {
      console.error("Error fetching unread count:", requestError);
      setError("Unable to load notification count.");
    }
  }, []);

  const fetchNotifications = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await authFetch("donor_notifications_list.php", {
        cache: "no-store",
      });
      const data = await response.json();
      if (data.success) {
        setNotifications(Array.isArray(data.data) ? data.data : []);
      } else {
        setError(data.message || "Unable to load notifications.");
        setNotifications([]);
      }
    } catch (requestError) {
      console.error("Error fetching notifications:", requestError);
      setError("Unable to load notifications.");
      setNotifications([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const formatDateTime = (value) => {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    });
  };

  // Fetch unread count on mount and periodically
  useEffect(() => {
    fetchUnreadCount();

    // Poll every 30 seconds for new notifications
    const interval = setInterval(fetchUnreadCount, 30000);
    return () => clearInterval(interval);
  }, [fetchUnreadCount]);

  useEffect(() => {
    if (!showDropdown) return undefined;

    fetchNotifications();

    const interval = setInterval(() => {
      fetchNotifications();
      fetchUnreadCount();
    }, 15000);

    const onFocus = () => {
      fetchNotifications();
      fetchUnreadCount();
    };

    window.addEventListener("focus", onFocus);

    return () => {
      clearInterval(interval);
      window.removeEventListener("focus", onFocus);
    };
  }, [showDropdown, fetchNotifications, fetchUnreadCount]);

  // Fetch all notifications when dropdown is opened
  const handleBellClick = async () => {
    const willOpen = !showDropdown;
    setShowDropdown(willOpen);

    if (willOpen) {
      fetchNotifications();
      fetchUnreadCount();
    }
  };

  // Mark notification as read
  const handleMarkAsRead = async (notificationId) => {
    try {
      const response = await authFetch(
        `donor_notification_mark_read.php`,
        {
          method: "POST",
          body: JSON.stringify({ notification_id: notificationId }),
        }
      );
      const data = await response.json();
      if (data.success) {
        // Update local state
        setNotifications(
          notifications.map((n) =>
            n.id === notificationId ? { ...n, isRead: 1 } : n
          )
        );
        // Refresh unread count
        await fetchUnreadCount();
      }
    } catch (error) {
      console.error("Error marking notification as read:", error);
    }
  };

  // Mark all as read
  const handleMarkAllAsRead = async () => {
    const unreadNotifications = notifications.filter((n) => n.isRead === 0);
    for (const notification of unreadNotifications) {
      await handleMarkAsRead(notification.id);
    }
  };

  return (
    <div className="notification-container">
      {/* Bell Icon Button */}
      <button className="notification-bell" onClick={handleBellClick}>
        🔔
        {unreadCount > 0 && (
          <span className="notification-badge">{unreadCount}</span>
        )}
      </button>

      {/* Dropdown Menu */}
      {showDropdown && (
        <div className="notification-dropdown">
          <div className="notification-header">
            <h3>Notifications</h3>
            <button
              className="close-btn"
              onClick={() => setShowDropdown(false)}
            >
              ✕
            </button>
          </div>

          {loading ? (
            <div className="notification-loading">Loading...</div>
          ) : error ? (
            <div className="notification-error">{error}</div>
          ) : notifications.length === 0 ? (
            <div className="notification-empty">No notifications yet</div>
          ) : (
            <>
              <div className="notification-list">
                {notifications.map((notification) => (
                  <div
                    key={notification.id}
                    className={`notification-item ${
                      notification.isRead === 0 ? "unread" : "read"
                    }`}
                  >
                    {notification.isRead === 0 && (
                      <div className="notification-dot">●</div>
                    )}
                    <div className="notification-content">
                      {notification.title ? (
                        <p className="notification-title">{notification.title}</p>
                      ) : null}
                      <p className="notification-message">
                        {notification.message}
                      </p>
                      <span className="notification-time">
                        {notification.timeAgo}
                        {notification.createdAt
                          ? ` • ${formatDateTime(notification.createdAt)}`
                          : ""}
                      </span>
                    </div>
                    {notification.isRead === 0 && (
                      <button
                        className="mark-read-btn"
                        onClick={() => handleMarkAsRead(notification.id)}
                        title="Mark as read"
                      >
                        ✓
                      </button>
                    )}
                  </div>
                ))}
              </div>

              {notifications.some((n) => n.isRead === 0) && (
                <div className="notification-footer">
                  <button
                    className="mark-all-read-btn"
                    onClick={handleMarkAllAsRead}
                  >
                    Mark all as read
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
};

export default NotificationDropdown;
