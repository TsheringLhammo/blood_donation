import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { authFetch } from "../utils/auth";
import "./NotificationBar.css";

const parseJsonResponse = async (response) => {
  const text = String((await response.text()) || "").trim();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
};

export default function NotificationBar({
  role = "staff",
  mode = "banner",
  notifications,
  unreadCount,
  onMarkAllRead,
  onOpenNotifications,
  apiUrl,
}) {
  const [remoteNotifications, setRemoteNotifications] = useState(Array.isArray(notifications) ? notifications : []);
  const [remoteUnread, setRemoteUnread] = useState(Number(unreadCount || 0));
  const [dismissed, setDismissed] = useState(false);
  const [open, setOpen] = useState(false);
  const [viewAll, setViewAll] = useState(false);
  const rootRef = useRef(null);

  useEffect(() => {
    if (Array.isArray(notifications)) {
      setRemoteNotifications(notifications);
    }
  }, [notifications]);

  useEffect(() => {
    setRemoteUnread(Number(unreadCount || 0));
  }, [unreadCount]);

  const loadNotifications = useCallback(async () => {
    if (!apiUrl) return;
    try {
      const url = apiUrl.includes("?") ? `${apiUrl}&_ts=${Date.now()}` : `${apiUrl}?_ts=${Date.now()}`;
      const response = await authFetch(url, { cache: "no-store" });
      if (!response.ok) return;
      const payload = await parseJsonResponse(response);
      if (!payload || !payload.success) return;
      const data = Array.isArray(payload.data) ? payload.data : [];
      setRemoteNotifications(data);
      setRemoteUnread(Number(payload.total_unread ?? payload.notificationsUnreadCount ?? data.filter((note) => Number(note?.is_read || 0) === 0).length));
    } catch {
      // Ignore fetch errors silently.
    }
  }, [apiUrl]);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications]);

  useEffect(() => {
    if (mode !== "compact") return undefined;
    const onDocClick = (event) => {
      if (rootRef.current && !rootRef.current.contains(event.target)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", onDocClick);
    return () => document.removeEventListener("mousedown", onDocClick);
  }, [mode]);

  const unreadTotal = remoteUnread;
  const latestNotification = useMemo(() => {
    const list = Array.isArray(remoteNotifications) ? remoteNotifications : [];
    if (!list.length) return null;
    return list.find((note) => Number(note?.is_read || 0) === 0) || list[0];
  }, [remoteNotifications]);

  // Banner mode hides itself when nothing to show. Compact (bell) stays visible.
  if (mode !== "compact" && (dismissed || unreadTotal <= 0)) return null;

  const title = latestNotification?.title || "New notification";
  const message = latestNotification?.message || "You have unread notifications.";

  const handleMarkAllRead = async () => {
    // Optimistic: clear unread + mark every locally-known note as read.
    setRemoteUnread(0);
    setRemoteNotifications((current) => current.map((n) => ({ ...n, is_read: 1 })));
    try {
      if (typeof onMarkAllRead === "function") {
        await onMarkAllRead();
      }
    } catch {
      // ignore
    }
    await loadNotifications();
  };

  const handleViewAll = () => {
    if (typeof onOpenNotifications === "function") {
      onOpenNotifications();
      setOpen(false);
      return;
    }
    setViewAll((v) => !v);
  };

  if (mode === "compact") {
    const visibleNotifications = viewAll ? remoteNotifications : remoteNotifications.slice(0, 5);
    return (
      <div ref={rootRef} className={`notification-compact notification-compact--${role}`}>
        <button
          type="button"
          className={`notification-compact__button${unreadTotal > 0 ? " unread" : ""}`}
          onClick={() => setOpen((value) => !value)}
          aria-label={`Notifications (${unreadTotal})`}
          aria-expanded={open}
          aria-haspopup="menu"
        >
          🔔
          {unreadTotal > 0 ? <span className="notification-compact__count">{unreadTotal}</span> : null}
        </button>

        {open ? (
          <section className="notification-compact__menu" role="menu" aria-label="Recent notifications">
            <div className="notification-compact__header">
              <strong>{viewAll ? "All Notifications" : "Recent Notifications"}</strong>
              <div className="notification-compact__actions">
                {remoteNotifications.length > 5 ? (
                  <button type="button" className="notification-bar__button" onClick={handleViewAll}>
                    {viewAll ? "Show recent" : "View all"}
                  </button>
                ) : typeof onOpenNotifications === "function" ? (
                  <button type="button" className="notification-bar__button" onClick={handleViewAll}>
                    View all
                  </button>
                ) : null}
                {unreadTotal > 0 ? (
                  <button
                    type="button"
                    className="notification-bar__button"
                    onClick={handleMarkAllRead}
                  >
                    Mark all read
                  </button>
                ) : null}
              </div>
            </div>
            {visibleNotifications.length === 0 ? (
              <div className="notification-compact__empty">No notifications yet.</div>
            ) : (
              <ul className="notification-compact__list">
                {visibleNotifications.map((note) => (
                  <li
                    key={note.id}
                    className={`severity-${String(note.severity || "info").toLowerCase()}${Number(note.is_read || 0) === 0 ? " unread" : ""}`}
                  >
                    <strong>{note.title}:</strong> {note.message}
                  </li>
                ))}
              </ul>
            )}
          </section>
        ) : null}
      </div>
    );
  }

  return (
    <section className={`notification-bar notification-bar--${role}`} role="status" aria-live="polite">
      <div className="notification-bar__body">
        <span className="notification-bar__icon">🔔</span>
        <div className="notification-bar__text">
          <div className="notification-bar__title">
            <span>{unreadTotal} new notification{unreadTotal === 1 ? "" : "s"}</span>
            <span className="notification-bar__badge">Live</span>
          </div>
          <div className="notification-bar__message"><strong>{title}:</strong> {message}</div>
        </div>
      </div>
      <div className="notification-bar__actions">
        {typeof onOpenNotifications === "function" ? (
          <button type="button" className="notification-bar__button" onClick={onOpenNotifications}>
            View all
          </button>
        ) : null}
        <button
          type="button"
          className="notification-bar__button"
          onClick={async () => {
            await handleMarkAllRead();
            setDismissed(true);
          }}
        >
          Mark all read
        </button>
        <button type="button" className="notification-bar__close" onClick={() => setDismissed(true)} aria-label="Dismiss notifications bar">
          ✕
        </button>
      </div>
    </section>
  );
}