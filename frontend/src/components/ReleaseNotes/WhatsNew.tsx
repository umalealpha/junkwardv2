import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { fetchUnseenReleaseNotes, markReleaseNotesSeen, type ReleaseNote } from '../../api/releaseNotes'

// Tag → label + style. Uses brand tokens so it inherits the design system.
const TAG: Record<string, { label: string; cls: string }> = {
  feature:     { label: 'New',      cls: 'bg-brand-orange/10 text-brand-orange' },
  improvement: { label: 'Improved', cls: 'bg-brand-navy/10 text-brand-navy' },
  fix:         { label: 'Fixed',    cls: 'bg-green-100 text-green-700' },
}

/**
 * "What's New" login panel. On mount it fetches unseen release notes and
 * auto-opens once per browser session when there's something new; dismissing
 * marks them seen. There is no top-bar icon — release notes also surface in
 * the notification bell (NotificationBell), so the bell is the persistent
 * entry point and this is purely the on-login announcement. Dismissing fires
 * a `releasenotes:seen` event so the bell clears its unseen items instantly.
 * Failures never block the app.
 */
export default function WhatsNew() {
  const navigate = useNavigate()
  const [notes, setNotes] = useState<ReleaseNote[]>([])
  const [open, setOpen] = useState(false)

  useEffect(() => {
    let cancelled = false
    fetchUnseenReleaseNotes()
      .then(r => {
        if (cancelled) return
        setNotes(r.data ?? [])
        if ((r.count ?? 0) > 0 && !sessionStorage.getItem('whatsnew_autoshown')) {
          sessionStorage.setItem('whatsnew_autoshown', '1')
          setOpen(true)
        }
      })
      .catch(() => { /* never block the app on this */ })
    return () => { cancelled = true }
  }, [])

  async function dismiss() {
    setOpen(false)
    try { await markReleaseNotesSeen() } catch { /* ignore */ }
    // Tell the notification bell to drop its unseen release-note items now,
    // rather than waiting for its next poll.
    window.dispatchEvent(new Event('releasenotes:seen'))
  }

  if (!open) return null

  return (
    <div
      role="dialog" aria-modal="true" aria-label="What's new"
      className="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
      onClick={e => { if (e.target === e.currentTarget) dismiss() }}
    >
      <div className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div className="flex items-center justify-between px-5 py-3 border-b shrink-0">
          <div className="flex items-center gap-2">
            <span className="text-brand-orange">
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.2 4.9L19.5 8l-3.7 3.5.9 5.1L12 14.4 7.3 16.6l.9-5.1L4.5 8l5.3-1.1z"/></svg>
            </span>
            <h3 className="text-lg font-semibold text-gray-800">What's New</h3>
          </div>
          <button onClick={dismiss} aria-label="Close" className="text-gray-400 hover:text-gray-600 text-2xl leading-none cursor-pointer">&times;</button>
        </div>

        <div className="px-5 py-3 overflow-y-auto grow min-h-0 space-y-5">
          {notes.length === 0 ? (
            <p className="py-8 text-center text-sm text-gray-500">You're all caught up.</p>
          ) : notes.map(n => (
            <div key={n.id}>
              <div className="flex items-baseline gap-2 mb-2">
                <h4 className="text-sm font-semibold text-gray-800">{n.title}</h4>
                {n.version && <span className="text-xs text-gray-400">v{n.version}</span>}
              </div>
              <ul className="space-y-2">
                {(n.highlights ?? []).map((h, i) => {
                  const t = TAG[h.tag ?? ''] ?? { label: 'Update', cls: 'bg-gray-100 text-gray-600' }
                  return (
                    <li key={i} className="flex gap-2.5 text-sm text-gray-700">
                      <span className={`self-start shrink-0 mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold ${t.cls}`}>{t.label}</span>
                      <span>{h.text}</span>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}
        </div>

        <div className="flex items-center justify-between px-5 py-3 border-t bg-gray-50 shrink-0">
          <button onClick={() => { dismiss(); navigate('/whats-new') }} className="text-sm text-brand-navy font-medium hover:underline cursor-pointer">View all updates →</button>
          <button onClick={dismiss} className="px-4 py-2 bg-brand-navy text-white text-sm font-medium rounded-md hover:bg-brand-navy/90 transition cursor-pointer">Got it</button>
        </div>
      </div>
    </div>
  )
}
