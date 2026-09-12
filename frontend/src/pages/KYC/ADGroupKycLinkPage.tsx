import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useAdGroupKycLink, useResendAdGroupKycLink } from '../../hooks/useAdGroupKyc'
import type { AdGroupKycLinkActivity } from '../../api/adGroupKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fmtDateTime } from '../../utils/format'
import { LINK_STATUS_PILL, Modal, NotifyForm, StatusPill } from './adGroupKycShared'

// Link detail — V2 port of the V8 Blade link show screen: status timeline,
// link/customer info cards, activity log with a WORKING metadata viewer
// (the V8 one was a stub), resend modal.

export default function ADGroupKycLinkPage() {
  const { id } = useParams()
  const linkId = Number(id)

  const { data: link, isLoading } = useAdGroupKycLink(linkId)
  const resend = useResendAdGroupKycLink(link?.campaign?.id)

  const [showResend, setShowResend] = useState(false)
  const [metaActivity, setMetaActivity] = useState<AdGroupKycLinkActivity | null>(null)
  const [banner, setBanner] = useState<{ ok: boolean; text: string } | null>(null)
  const [copied, setCopied] = useState(false)

  if (isLoading) return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  if (!link) return <div className="p-6"><EmptyState title="Link not found" description="It may have been removed." /></div>

  const responseMinutes = link.sentAt && link.openedAt
    ? Math.max(0, Math.round((new Date(link.openedAt).getTime() - new Date(link.sentAt).getTime()) / 60000))
    : null

  const timeline: { label: string; at: string | null; detail?: string }[] = [
    { label: 'Link Created', at: link.createdAt },
    { label: 'Link Sent', at: link.sentAt, detail: link.deliveryMethod ? `via ${link.deliveryMethod}${link.deliveryReference ? ` (${link.deliveryReference})` : ''}` : undefined },
    { label: 'Link Opened', at: link.openedAt, detail: responseMinutes != null ? `${responseMinutes} min after send` : undefined },
    { label: 'OTP Verified', at: link.otpVerifiedAt },
    { label: 'Completed', at: link.completedAt },
  ]

  function copyToken() {
    navigator.clipboard?.writeText(link!.token).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    })
  }

  return (
    <div className="p-6 space-y-4">
      <div className="text-sm text-ink-muted">
        {link.campaign ? (
          <Link to={`/kyc/ad-group/campaigns/${link.campaign.id}`} className="text-primary hover:underline">← Back to {link.campaign.name}</Link>
        ) : (
          <Link to="/kyc/ad-group" className="text-primary hover:underline">← Back to AD Group KYC</Link>
        )}
      </div>

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-ink">KYC Link #{link.id}</h1>
            <StatusPill status={link.status} map={LINK_STATUS_PILL} />
          </div>
          {link.customer && (
            <div className="text-sm text-ink-muted mt-1">{link.customer.name} · {link.customer.email || 'no email'}</div>
          )}
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setShowResend(true)}
            className="px-3 py-1.5 text-sm font-medium border border-line rounded-md text-ink hover:bg-surface-2 transition"
          >
            Resend Notification
          </button>
          {link.kycUrl && (
            <a
              href={link.kycUrl}
              target="_blank"
              rel="noreferrer"
              className="px-3 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md"
            >
              Open Customer Link ↗
            </a>
          )}
        </div>
      </div>

      {banner && (
        <div className={`px-3 py-2 rounded-md text-sm flex justify-between items-center ${banner.ok ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>
          {banner.text}
          <button type="button" onClick={() => setBanner(null)} className="ml-3 font-bold" aria-label="Dismiss">×</button>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Timeline */}
        <div className="bg-surface rounded-lg shadow-sm border border-line">
          <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Status Timeline</div>
          <ol className="p-4 space-y-4">
            {timeline.map((t, i) => (
              <li key={i} className="flex gap-3">
                <span className={`mt-1 h-2.5 w-2.5 rounded-full shrink-0 ${t.at ? 'bg-status-success-fg' : 'bg-surface-2 border border-line'}`} />
                <div>
                  <div className={`text-sm font-medium ${t.at ? 'text-ink' : 'text-ink-faint'}`}>{t.label}</div>
                  {t.at && <div className="text-xs text-ink-muted">{fmtDateTime(t.at)}</div>}
                  {t.detail && <div className="text-xs text-ink-faint">{t.detail}</div>}
                </div>
              </li>
            ))}
            <li className="flex gap-3 pt-1 border-t border-line">
              <span className={`mt-1 h-2.5 w-2.5 rounded-full shrink-0 ${link.expiresAt && new Date(link.expiresAt) < new Date() ? 'bg-status-danger-fg' : 'bg-status-warning-fg'}`} />
              <div>
                <div className="text-sm font-medium text-ink">Expires</div>
                <div className="text-xs text-ink-muted">{link.expiresAt ? fmtDateTime(link.expiresAt) : '—'}</div>
              </div>
            </li>
          </ol>
        </div>

        {/* Link info */}
        <div className="bg-surface rounded-lg shadow-sm border border-line">
          <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Link Information</div>
          <dl className="p-4 space-y-2 text-sm">
            <InfoRow label="Token">
              <span className="font-mono text-xs">{link.token.slice(0, 20)}{link.token.length > 20 ? '…' : ''}</span>
              <button type="button" onClick={copyToken} className="ml-2 text-primary text-xs font-medium hover:underline">
                {copied ? 'Copied!' : 'Copy'}
              </button>
            </InfoRow>
            <InfoRow label="Delivery Method">{link.deliveryMethod || '—'}</InfoRow>
            <InfoRow label="Delivery Reference">{link.deliveryReference || '—'}</InfoRow>
            <InfoRow label="OTP Attempts">{link.otpAttempts ?? 0}</InfoRow>
            <InfoRow label="IP Address">{link.ipAddress || '—'}</InfoRow>
            <InfoRow label="User Agent">
              <span className="text-xs break-all">{link.userAgent ? `${link.userAgent.slice(0, 80)}${link.userAgent.length > 80 ? '…' : ''}` : '—'}</span>
            </InfoRow>
          </dl>
        </div>

        {/* Customer info */}
        <div className="bg-surface rounded-lg shadow-sm border border-line">
          <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Customer</div>
          <dl className="p-4 space-y-2 text-sm">
            <InfoRow label="Name">{link.customer?.name || '—'}</InfoRow>
            <InfoRow label="Email">{link.customer?.email || '—'}</InfoRow>
            <InfoRow label="Phone">{link.customer?.cellphone || '—'}</InfoRow>
            <InfoRow label="Customer ID">
              {link.customer ? (
                <Link to={`/customers/${link.customer.id}`} className="text-primary hover:underline">{link.customer.id}</Link>
              ) : '—'}
            </InfoRow>
            <InfoRow label="Policy #">{link.policyNumber || '—'}</InfoRow>
            <InfoRow label="Campaign">
              {link.campaign ? (
                <Link to={`/kyc/ad-group/campaigns/${link.campaign.id}`} className="text-primary hover:underline">{link.campaign.name}</Link>
              ) : '—'}
            </InfoRow>
          </dl>
        </div>
      </div>

      {/* Activities */}
      <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
        <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Activity Log</div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Time</th>
                <th className="px-4 py-3 text-left">Action</th>
                <th className="px-4 py-3 text-left">Description</th>
                <th className="px-4 py-3 text-left">IP</th>
                <th className="px-4 py-3 text-left">Metadata</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {link.activities.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-ink-faint">No activity recorded yet.</td></tr>
              ) : (
                link.activities.map(a => (
                  <tr key={a.id} className="hover:bg-surface-2 transition">
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{fmtDateTime(a.occurredAt)}</td>
                    <td className="px-4 py-2"><span className="capitalize text-ink font-medium">{a.activityType.replace(/_/g, ' ')}</span></td>
                    <td className="px-4 py-2 text-ink-muted">{a.description || '—'}</td>
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{a.ipAddress || '—'}</td>
                    <td className="px-4 py-2">
                      {a.metadata && Object.keys(a.metadata).length > 0 ? (
                        <button type="button" onClick={() => setMetaActivity(a)} className="text-primary hover:underline text-xs font-medium">View</button>
                      ) : (
                        <span className="text-ink-faint text-xs">—</span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {showResend && (
        <Modal title="Resend KYC Link" onClose={() => setShowResend(false)}>
          <NotifyForm
            submitLabel="Resend"
            busy={resend.isPending}
            onSubmit={(channels, message) =>
              resend.mutateAsync({ linkId: link.id, channels, message })
                .then(res => { setShowResend(false); setBanner({ ok: true, text: res.message }) })
                .catch((e: any) => setBanner({ ok: false, text: e?.response?.data?.message || 'Resend failed.' }))
            }
          />
        </Modal>
      )}

      {metaActivity && (
        <Modal title={`Metadata — ${metaActivity.activityType.replace(/_/g, ' ')}`} onClose={() => setMetaActivity(null)} wide>
          <pre className="text-xs bg-surface-2 text-ink rounded-md p-3 overflow-x-auto whitespace-pre-wrap">
            {JSON.stringify(metaActivity.metadata, null, 2)}
          </pre>
        </Modal>
      )}
    </div>
  )
}

function InfoRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-ink-faint shrink-0">{label}</dt>
      <dd className="text-ink text-right min-w-0">{children}</dd>
    </div>
  )
}
