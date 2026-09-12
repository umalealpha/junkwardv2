import { useState, type ReactNode } from 'react'
import type { CampaignStatus, LinkStatus, NotifyChannel } from '../../api/adGroupKyc'

// Shared UI bits for the AD Group KYC campaign screens.

export const CAMPAIGN_STATUS_PILL: Record<CampaignStatus, string> = {
  active:    'bg-status-success-bg text-status-success-fg',
  completed: 'bg-status-info-bg text-status-info-fg',
  paused:    'bg-status-warning-bg text-status-warning-fg',
  draft:     'bg-surface-2 text-ink-faint',
  cancelled: 'bg-status-danger-bg text-status-danger-fg',
}

export const LINK_STATUS_PILL: Record<LinkStatus, string> = {
  pending:       'bg-status-warning-bg text-status-warning-fg',
  sent:          'bg-status-info-bg text-status-info-fg',
  opened:        'bg-status-accent-bg text-status-accent-fg',
  otp_verified:  'bg-status-accent-bg text-status-accent-fg',
  completed:     'bg-status-success-bg text-status-success-fg',
  expired:       'bg-status-danger-bg text-status-danger-fg',
  failed:        'bg-status-danger-bg text-status-danger-fg',
  not_generated: 'bg-surface-2 text-ink-faint',
}

export function StatusPill({ status, map }: { status: string; map: Record<string, string> }) {
  return (
    <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize whitespace-nowrap ${map[status] ?? 'bg-surface-2 text-ink-faint'}`}>
      {status.replace(/_/g, ' ')}
    </span>
  )
}

export function Modal({ title, onClose, children, wide }: {
  title: string
  onClose: () => void
  children: ReactNode
  wide?: boolean
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-ink/40" onClick={onClose} />
      <div className={`relative bg-surface border border-line rounded-lg shadow-xl w-full ${wide ? 'max-w-2xl' : 'max-w-md'} max-h-[85vh] overflow-y-auto`}>
        <div className="flex items-center justify-between px-4 py-3 border-b border-line sticky top-0 bg-surface">
          <h2 className="text-base font-semibold text-ink">{title}</h2>
          <button type="button" onClick={onClose} className="text-ink-faint hover:text-ink text-lg leading-none" aria-label="Close">×</button>
        </div>
        <div className="p-4">{children}</div>
      </div>
    </div>
  )
}

const ALL_CHANNELS: NotifyChannel[] = ['email', 'sms', 'whatsapp']

export function ChannelChecks({ channels, onChange }: {
  channels: NotifyChannel[]
  onChange: (next: NotifyChannel[]) => void
}) {
  function toggle(c: NotifyChannel) {
    onChange(channels.includes(c) ? channels.filter(x => x !== c) : [...channels, c])
  }
  return (
    <div className="flex gap-4">
      {ALL_CHANNELS.map(c => (
        <label key={c} className="flex items-center gap-1.5 text-sm text-ink capitalize cursor-pointer">
          <input type="checkbox" checked={channels.includes(c)} onChange={() => toggle(c)} className="rounded border-line" />
          {c}
        </label>
      ))}
    </div>
  )
}

/** Channels + optional message form used by resend / bulk-notify modals. */
export function NotifyForm({ submitLabel, busy, onSubmit, showMessage = true }: {
  submitLabel: string
  busy: boolean
  onSubmit: (channels: NotifyChannel[], message?: string) => void
  showMessage?: boolean
}) {
  const [channels, setChannels] = useState<NotifyChannel[]>(['email'])
  const [message, setMessage] = useState('')
  return (
    <div className="space-y-3">
      <div>
        <label className="block text-xs font-medium text-ink-muted mb-1">Channels</label>
        <ChannelChecks channels={channels} onChange={setChannels} />
      </div>
      {showMessage && (
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Message (optional)</label>
          <textarea
            value={message}
            onChange={e => setMessage(e.target.value)}
            maxLength={500}
            rows={3}
            className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink"
            placeholder="Optional note — included in the notification and recorded on the activity log"
          />
        </div>
      )}
      <div className="flex justify-end">
        <button
          type="button"
          disabled={busy || channels.length === 0}
          onClick={() => onSubmit(channels, message || undefined)}
          className="px-4 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md disabled:opacity-50"
        >
          {busy ? 'Sending…' : submitLabel}
        </button>
      </div>
    </div>
  )
}
