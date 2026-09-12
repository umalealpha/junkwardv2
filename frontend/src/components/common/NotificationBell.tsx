import { useState, useEffect, useRef, useCallback } from 'react'
import { fetchNotifications, markNotificationRead, markNotificationUnread, markAllNotificationsRead, type Notification } from '../../api/notifications'
import { fetchUnseenReleaseNotes, markReleaseNotesSeen, type ReleaseNote } from '../../api/releaseNotes'
import { useNavigate } from 'react-router-dom'
import apiClient from '../../api/client'

const POLL_INTERVAL = 60000 // 60 seconds (reduced from 15s to avoid DB connection pile-up)
const TOAST_DURATION = 8000 // 8 seconds

// Notification sound (base64 encoded short beep)
// Sound is loaded from /notification.mp3 in the public folder

export default function NotificationBell() {
  const navigate = useNavigate()
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [unreadCount, setUnreadCount] = useState(0)
  // Unseen "What's New" release notes, surfaced in the bell alongside DB
  // notifications (the bell is the persistent entry point; the login panel
  // is the on-login announcement).
  const [releaseNotes, setReleaseNotes] = useState<ReleaseNote[]>([])
  const [isOpen, setIsOpen] = useState(false)
  // "All" vs "Unread only" filter for the dropdown list. Filtered client-side
  // from the already-polled set so switching is instant.
  const [showUnreadOnly, setShowUnreadOnly] = useState(false)
  const [toasts, setToasts] = useState<Notification[]>([])
  const dropdownRef = useRef<HTMLDivElement>(null)
  const prevUnreadRef = useRef(0)
  const audioRef = useRef<HTMLAudioElement | null>(null)

  // Play notification sound — tries /notification.mp3 first, falls back to Web Audio API beep
  const playSound = useCallback(() => {
    try {
      if (!audioRef.current) {
        audioRef.current = new Audio('/notification.mp3')
        audioRef.current.onerror = () => {
          // MP3 not found — generate a short beep via Web Audio API
          try {
            const ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
            const osc = ctx.createOscillator()
            const gain = ctx.createGain()
            osc.connect(gain)
            gain.connect(ctx.destination)
            osc.type = 'sine'
            osc.frequency.value = 880 // A5 note
            gain.gain.value = 0.3
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3)
            osc.start(ctx.currentTime)
            osc.stop(ctx.currentTime + 0.3)
          } catch {}
        }
      }
      audioRef.current.currentTime = 0
      audioRef.current.play().catch(() => {
        // Autoplay blocked or file missing — try Web Audio API
        try {
          const ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
          const osc = ctx.createOscillator()
          const gain = ctx.createGain()
          osc.connect(gain)
          gain.connect(ctx.destination)
          osc.type = 'sine'
          osc.frequency.value = 880
          gain.gain.value = 0.3
          gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3)
          osc.start(ctx.currentTime)
          osc.stop(ctx.currentTime + 0.3)
        } catch {}
      })
    } catch {}
  }, [])

  // Show browser notification
  const showBrowserNotification = useCallback((title: string, body: string) => {
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification(title, { body, icon: '/favicon.ico' })
    }
  }, [])

  // Poll for notifications
  const loadNotifications = useCallback(async () => {
    try {
      const result = await fetchNotifications()
      setNotifications(result.data)

      // Check for new notifications
      if (result.unread_count > prevUnreadRef.current && prevUnreadRef.current >= 0) {
        const newOnes = result.data.filter(n => !n.read_at).slice(0, result.unread_count - prevUnreadRef.current)
        if (newOnes.length > 0 && prevUnreadRef.current > 0) {
          // New notification arrived — show toast, play sound
          newOnes.forEach(n => {
            setToasts(prev => [...prev, n])
            setTimeout(() => setToasts(prev => prev.filter(t => t.id !== n.id)), TOAST_DURATION)
          })
          playSound()
          const first = newOnes[0]
          showBrowserNotification(first.data.title || 'Notification', first.data.message || '')
        }
      }

      prevUnreadRef.current = result.unread_count
      setUnreadCount(result.unread_count)
    } catch {}
  }, [playSound, showBrowserNotification])

  // Unseen release notes — cheap query, polled on the same cadence and
  // cleared immediately when the login panel dispatches `releasenotes:seen`.
  const loadReleaseNotes = useCallback(async () => {
    try {
      const r = await fetchUnseenReleaseNotes()
      setReleaseNotes(r.data ?? [])
    } catch {}
  }, [])

  // Request browser notification permission
  useEffect(() => {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission()
    }
  }, [])

  // Poll. Base interval is 60 s — enough to stay responsive without
  // piling up DB connections. When the app dispatches the
  // `notifications:accelerate-poll` window event (e.g. the user just
  // kicked off a background reinsurance compute), poll every 10 s for
  // up to 5 min so the toast arrives inside a plausible "is it done?"
  // window. Event consumers pass { ms: 300000 } to override the budget.
  useEffect(() => {
    loadNotifications()
    let interval = setInterval(loadNotifications, POLL_INTERVAL)
    let acceleratedUntil = 0

    const tick = () => {
      loadNotifications()
      if (Date.now() > acceleratedUntil) {
        clearInterval(interval)
        interval = setInterval(loadNotifications, POLL_INTERVAL)
      }
    }

    const onAccelerate = (e: Event) => {
      const detail = (e as CustomEvent<{ ms?: number }>).detail || {}
      const windowMs = Math.max(60000, detail.ms ?? 300000) // 1 – 5 min
      acceleratedUntil = Date.now() + windowMs
      clearInterval(interval)
      interval = setInterval(tick, 10000) // 10 s while in the window
    }

    window.addEventListener('notifications:accelerate-poll', onAccelerate)
    return () => {
      clearInterval(interval)
      window.removeEventListener('notifications:accelerate-poll', onAccelerate)
    }
  }, [loadNotifications])

  // Poll unseen release notes (separate from the DB-notification poll so a
  // failure in one never affects the other), and clear instantly when the
  // login panel marks them seen.
  useEffect(() => {
    loadReleaseNotes()
    const onSeen = () => setReleaseNotes([])
    window.addEventListener('releasenotes:seen', onSeen)
    const iv = setInterval(loadReleaseNotes, POLL_INTERVAL)
    return () => {
      window.removeEventListener('releasenotes:seen', onSeen)
      clearInterval(iv)
    }
  }, [loadReleaseNotes])

  const bellButtonRef = useRef<HTMLButtonElement>(null)

  // Close dropdown on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) setIsOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  // Escape closes the dropdown and returns focus to the bell (WCAG 2.1.2).
  useEffect(() => {
    if (!isOpen) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setIsOpen(false)
        bellButtonRef.current?.focus()
      }
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [isOpen])

  // Clicking a release-note item: mark all notes seen, clear, open the
  // full changelog. (markReleaseNotesSeen marks every note seen — there is
  // no per-note seen state — so /whats-new shows the full history.)
  const handleReleaseClick = async () => {
    setIsOpen(false)
    setReleaseNotes([])
    try { await markReleaseNotesSeen() } catch {}
    navigate('/whats-new')
  }

  const handleMarkRead = async (n: Notification) => {
    // Mark as read (don't block navigation on failure)
    if (!n.read_at) {
      try {
        await markNotificationRead(n.id)
        setNotifications(prev => prev.map(x => x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x))
        setUnreadCount(prev => Math.max(0, prev - 1))
      } catch {}
    }
    setIsOpen(false)

    // For pdf_ready notifications, download the PDF directly
    if (n.type === 'pdf_ready' && n.data.policy_id && n.data.job_id) {
      try {
        const r = await apiClient.get(`/policies/${n.data.policy_id}/download-quote-pdf/${n.data.job_id}`, { responseType: 'blob' })
        window.open(URL.createObjectURL(r.data), '_blank')
      } catch {
        // Fallback: navigate to the policy page
        if (n.action) navigate(n.action)
      }
      return
    }

    // For other notifications, navigate to action URL
    if (n.action) navigate(n.action)
  }

  // Flag an already-read notification back to unread so it stays as a
  // follow-up reminder (e.g. opened by mistake). Bump prevUnreadRef too so
  // the next poll doesn't misread the higher server count as a new arrival
  // and fire a spurious toast.
  const handleMarkUnread = async (n: Notification) => {
    if (!n.read_at) return
    try {
      await markNotificationUnread(n.id)
      setNotifications(prev => prev.map(x => x.id === n.id ? { ...x, read_at: null } : x))
      setUnreadCount(prev => prev + 1)
      prevUnreadRef.current = prevUnreadRef.current + 1
    } catch {}
  }

  const handleMarkAllRead = async () => {
    await markAllNotificationsRead()
    setNotifications(prev => prev.map(x => ({ ...x, read_at: x.read_at || new Date().toISOString() })))
    setUnreadCount(0)
    if (releaseNotes.length) {
      try { await markReleaseNotesSeen() } catch {}
      setReleaseNotes([])
    }
  }

  // Badge + "mark all" reflect DB notifications plus unseen release notes.
  const totalUnread = unreadCount + releaseNotes.length

  // List honours the All/Unread filter. Release notes are always unseen, so
  // they stay visible under "Unread" too.
  const visibleNotifications = showUnreadOnly
    ? notifications.filter(n => !n.read_at)
    : notifications

  const typeIcon = (type: string) => {
    switch (type) {
      case 'pdf_ready': return '📄'
      case 'approval_needed': return '📋'
      case 'policy_approved': return '✅'
      case 'policy_rejected': return '❌'
      case 'policy_issued': return '🎉'
      case 'task_assigned': return '📌'
      case 'claim_mention': return '💬'
      case 'reinsurance_computed': return '🛡️'
      case 'reinsurance_empty': return '⚠️'
      case 'reinsurance_failed': return '🚫'
      default: return '🔔'
    }
  }

  const typeBg = (type: string) => {
    switch (type) {
      case 'pdf_ready': return 'bg-blue-50 border-blue-200'
      case 'approval_needed': return 'bg-yellow-50 border-yellow-200'
      case 'policy_approved': return 'bg-green-50 border-green-200'
      case 'policy_rejected': return 'bg-red-50 border-red-200'
      case 'policy_issued': return 'bg-emerald-50 border-emerald-200'
      case 'claim_mention': return 'bg-status-info-bg border-primary'
      case 'reinsurance_computed': return 'bg-indigo-50 border-indigo-200'
      case 'reinsurance_empty': return 'bg-amber-50 border-amber-200'
      case 'reinsurance_failed': return 'bg-red-50 border-red-200'
      default: return 'bg-surface-2 border-line'
    }
  }

  return (
    <>
      {/* Bell Icon */}
      <div ref={dropdownRef} className="relative">
        <button
          ref={bellButtonRef}
          onClick={() => setIsOpen(!isOpen)}
          className="relative p-2 text-ink-muted hover:text-ink hover:bg-surface-2 rounded-full transition"
          title="Notifications"
          aria-label={totalUnread > 0 ? `Notifications, ${totalUnread} unread` : 'Notifications'}
          aria-haspopup="menu"
          aria-expanded={isOpen}
        >
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          {totalUnread > 0 && (
            <span className="absolute -top-1 -right-1 flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-red-500 rounded-full animate-pulse">
              {totalUnread > 99 ? '99+' : totalUnread}
            </span>
          )}
        </button>

        {/* Dropdown Panel */}
        {isOpen && (
          <div role="menu" className="absolute right-0 mt-2 w-96 bg-surface rounded-xl shadow-elev-lg border border-line z-drawer max-h-[70vh] flex flex-col">
            {/* Header */}
            <div className="border-b border-line">
              <div className="flex items-center justify-between px-4 py-3">
                <h3 className="font-semibold text-ink">Notifications</h3>
                {totalUnread > 0 && (
                  <button onClick={handleMarkAllRead} className="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    Mark all read
                  </button>
                )}
              </div>
              {/* All / Unread filter — lets a user focus on items still pending
                  follow-up (including any they marked back to unread). */}
              <div className="flex items-center gap-1 px-4 pb-2">
                <button
                  onClick={() => setShowUnreadOnly(false)}
                  className={`text-xs px-2.5 py-1 rounded-full font-medium transition ${!showUnreadOnly ? 'bg-blue-100 text-blue-700' : 'text-ink-muted hover:bg-surface-2'}`}
                >
                  All
                </button>
                <button
                  onClick={() => setShowUnreadOnly(true)}
                  className={`text-xs px-2.5 py-1 rounded-full font-medium transition ${showUnreadOnly ? 'bg-blue-100 text-blue-700' : 'text-ink-muted hover:bg-surface-2'}`}
                >
                  Unread{totalUnread > 0 ? ` (${totalUnread})` : ''}
                </button>
              </div>
            </div>

            {/* Notification List */}
            <div className="overflow-y-auto flex-1">
              {/* Release notes ("What's New") first — new features the user
                  hasn't seen yet. Clicking opens the full changelog. */}
              {releaseNotes.map(rn => (
                <button
                  key={`rn-${rn.id}`}
                  onClick={handleReleaseClick}
                  className="w-full text-left px-4 py-3 border-b border-line hover:bg-brand-orange/10 transition flex items-start gap-3 bg-brand-orange/5"
                >
                  <span className="text-lg mt-0.5">✨</span>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-ink truncate">{rn.title}</span>
                      <span className="shrink-0 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-brand-orange/10 text-brand-orange">New</span>
                    </div>
                    <p className="text-xs text-ink-muted mt-0.5 line-clamp-2">{rn.highlights?.[0]?.text ?? 'New update available'}</p>
                    <span className="text-[10px] text-ink-faint mt-1 block">What's New{rn.version ? ` · v${rn.version}` : ''}</span>
                  </div>
                </button>
              ))}

              {visibleNotifications.length === 0 && releaseNotes.length === 0 ? (
                <div className="py-8 text-center text-ink-faint text-sm">
                  {showUnreadOnly ? 'No unread notifications' : 'No notifications'}
                </div>
              ) : (
                visibleNotifications.map(n => (
                  <div
                    key={n.id}
                    className={`relative border-b border-line ${!n.read_at ? 'bg-status-info-bg/40' : ''}`}
                  >
                    <button
                      onClick={() => handleMarkRead(n)}
                      className="w-full text-left px-4 py-3 hover:bg-surface-2 transition flex items-start gap-3"
                    >
                      <span className="text-lg mt-0.5">{typeIcon(n.type)}</span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-medium text-ink truncate">{n.data.title || n.type}</span>
                          {!n.read_at && <span className="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0" />}
                        </div>
                        <p className="text-xs text-ink-muted mt-0.5 line-clamp-2">{n.data.message}</p>
                        {(n.data.customer_name || n.data.claim_type || n.data.policy_number || n.data.claim_number) && (
                          <p className="text-[11px] text-ink-faint mt-0.5 truncate">
                            {[n.data.customer_name, n.data.claim_type, n.data.policy_number, n.data.claim_number].filter(Boolean).join(' · ')}
                          </p>
                        )}
                        <span className="text-[10px] text-ink-faint mt-1 block">{n.time_ago}</span>
                      </div>
                    </button>
                    {/* Read notifications get a "Mark unread" control so a
                        mistakenly-opened item can be kept as a follow-up. */}
                    {n.read_at && (
                      <button
                        onClick={e => { e.stopPropagation(); handleMarkUnread(n) }}
                        className="absolute top-2 right-2 text-[10px] text-blue-600 hover:text-blue-800 font-medium px-1.5 py-0.5 rounded hover:bg-blue-50"
                        title="Mark as unread"
                      >
                        Mark unread
                      </button>
                    )}
                  </div>
                ))
              )}
            </div>
          </div>
        )}
      </div>

      {/* Toast Notifications (bottom-right) */}
      <div className="fixed bottom-4 right-4 z-toast flex flex-col gap-2 pointer-events-none">
        {toasts.map(n => (
          <div
            key={n.id}
            className={`pointer-events-auto max-w-sm rounded-lg border shadow-lg px-4 py-3 flex items-start gap-3 animate-slide-in ${typeBg(n.type)}`}
            onClick={async () => {
              setToasts(prev => prev.filter(t => t.id !== n.id))
              if (n.type === 'pdf_ready' && n.data.policy_id && n.data.job_id) {
                try {
                  const r = await apiClient.get(`/policies/${n.data.policy_id}/download-quote-pdf/${n.data.job_id}`, { responseType: 'blob' })
                  window.open(URL.createObjectURL(r.data), '_blank')
                } catch { if (n.action) navigate(n.action) }
                return
              }
              if (n.action) navigate(n.action)
            }}
            style={{ cursor: 'pointer' }}
          >
            <span className="text-xl">{typeIcon(n.type)}</span>
            <div className="flex-1">
              <p className="text-sm font-semibold text-ink">{n.data.title || 'Notification'}</p>
              <p className="text-xs text-ink-muted mt-0.5">{n.data.message}</p>
            </div>
            <button
              onClick={e => { e.stopPropagation(); setToasts(prev => prev.filter(t => t.id !== n.id)) }}
              aria-label="Dismiss notification"
              className="text-ink-faint hover:text-ink text-sm"
            >✕</button>
          </div>
        ))}
      </div>

      {/* CSS animation for toast slide-in */}
      <style>{`
        @keyframes slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .animate-slide-in { animation: slide-in 0.3s ease-out; }
      `}</style>
    </>
  )
}
