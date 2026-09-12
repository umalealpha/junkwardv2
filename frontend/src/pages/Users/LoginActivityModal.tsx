import { useEffect, useState } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'

interface LoginLog {
  id: number
  event: string // login | logout | failed | sso_blocked
  ip_address: string | null
  user_agent: string | null
  created_at: string | null
}

const EVENT_BADGE: Record<string, { label: string; classes: string }> = {
  login:       { label: 'Login',       classes: 'bg-green-100 text-green-700' },
  logout:      { label: 'Logout',      classes: 'bg-gray-100 text-gray-600' },
  failed:      { label: 'Failed',      classes: 'bg-red-100 text-red-700' },
  sso_blocked: { label: 'SSO blocked', classes: 'bg-amber-100 text-amber-700' },
}

// Lightweight, dependency-free device hint from the user-agent string.
function deviceFromUA(ua: string | null): string {
  if (!ua) return '—'
  const browser = /Edg/.test(ua) ? 'Edge'
    : /Chrome/.test(ua) ? 'Chrome'
    : /Firefox/.test(ua) ? 'Firefox'
    : /Safari/.test(ua) ? 'Safari'
    : 'Other'
  const os = /Windows/.test(ua) ? 'Windows'
    : /Mac OS|Macintosh/.test(ua) ? 'macOS'
    : /Android/.test(ua) ? 'Android'
    : /iPhone|iPad|iOS/.test(ua) ? 'iOS'
    : /Linux/.test(ua) ? 'Linux'
    : ''
  return os ? `${browser} · ${os}` : browser
}

function fmt(ts: string | null): string {
  if (!ts) return '—'
  const d = new Date(ts)
  return isNaN(d.getTime()) ? ts : d.toLocaleString()
}

export default function LoginActivityModal({
  open, onClose, userId, userName,
}: { open: boolean; onClose: () => void; userId: number; userName: string }) {
  const [logs, setLogs] = useState<LoginLog[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open || !userId) return
    let cancelled = false
    setLoading(true)
    setError(null)
    apiClient.get(`/users/${userId}/login-activity`)
      .then(r => { if (!cancelled) setLogs(r.data?.data ?? []) })
      .catch((e: any) => { if (!cancelled) setError(e?.response?.data?.message || 'Could not load login activity.') })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [open, userId])

  if (!open) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Login activity"
      className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
      onClick={e => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
        <div className="flex items-center justify-between px-5 py-3 border-b shrink-0">
          <div>
            <h3 className="text-lg font-semibold text-gray-800">Login activity</h3>
            <p className="text-xs text-gray-500">{userName} · most recent 100 events</p>
          </div>
          <button
            onClick={onClose}
            aria-label="Close"
            className="text-gray-400 hover:text-gray-600 text-xl leading-none cursor-pointer"
          >
            &times;
          </button>
        </div>

        <div className="px-5 py-3 overflow-y-auto grow min-h-0">
          {loading ? (
            <div className="py-10 flex justify-center"><LoadingSpinner /></div>
          ) : error ? (
            <div className="bg-red-50 border border-red-200 text-red-700 rounded px-3 py-2 text-sm">{error}</div>
          ) : logs.length === 0 ? (
            <div className="py-10 text-center text-sm text-gray-500">
              No login activity recorded yet.
              <div className="text-xs text-gray-400 mt-1">Events appear once the user signs in, out, or a sign-in fails.</div>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                    <th className="py-2 pr-3 font-medium">Event</th>
                    <th className="py-2 pr-3 font-medium">IP address</th>
                    <th className="py-2 pr-3 font-medium">Device</th>
                    <th className="py-2 font-medium whitespace-nowrap">When</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.map(l => {
                    const b = EVENT_BADGE[l.event] ?? { label: l.event, classes: 'bg-gray-100 text-gray-600' }
                    return (
                      <tr key={l.id} className="border-b last:border-0">
                        <td className="py-2 pr-3">
                          <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${b.classes}`}>{b.label}</span>
                        </td>
                        <td className="py-2 pr-3 font-mono text-xs text-gray-700">{l.ip_address ?? '—'}</td>
                        <td className="py-2 pr-3 text-gray-600">{deviceFromUA(l.user_agent)}</td>
                        <td className="py-2 text-gray-600 whitespace-nowrap">{fmt(l.created_at)}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="flex justify-end px-5 py-3 border-t bg-gray-50 shrink-0">
          <button onClick={onClose} className="px-4 py-2 text-sm border rounded hover:bg-gray-100 cursor-pointer">Close</button>
        </div>
      </div>
    </div>
  )
}
