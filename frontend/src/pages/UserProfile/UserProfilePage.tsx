import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getStoredUser, getStoredRoles, getStoredPermissions, logout } from '../../api/auth'
import { ALPHA_BRIDGE_BOARD_URL } from '../../utils/alphaBridge'
import { useMyProfile } from '../../hooks/useMyProfile'
import { fmtDate, fmtDateTime } from '../../utils/format'
import { openAlphaBridgeWidget } from '../../utils/alphaBridge'
import { useToast } from '../../components/common/Toast'
import AvatarUploadModal from './AvatarUploadModal'

// ── helpers ────────────────────────────────────────────────────────

// Build the initials avatar. "Pramod Bisen" -> "PB"; falls back to first letter
// of email, then "U". Used as a visual identity while real avatars are still
// a Phase 2 backend feature.
function initials(name: string | undefined, email: string | undefined): string {
  if (name && name.trim()) {
    const parts = name.trim().split(/\s+/)
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    return parts[0].slice(0, 2).toUpperCase()
  }
  if (email && email.length > 0) return email[0].toUpperCase()
  return 'U'
}

// Pick a stable colour from a small accessible palette based on the user's
// identity, so the same person always lands on the same avatar tint. All
// palette entries meet 4.5:1 contrast for white text per WCAG AA.
const AVATAR_PALETTE = [
  'bg-brand-navy',
  'bg-emerald-700',
  'bg-amber-700',
  'bg-rose-700',
  'bg-indigo-700',
  'bg-teal-700',
  'bg-fuchsia-700',
  'bg-cyan-800',
]
function avatarBg(seed: string): string {
  let h = 0
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) >>> 0
  return AVATAR_PALETTE[h % AVATAR_PALETTE.length]
}

// ── tiny inline components ─────────────────────────────────────────

function KpiTile({
  label, value, hint, accent,
}: { label: string; value: string | number; hint?: string; accent?: 'navy' | 'orange' | 'muted' }) {
  const accentClass =
    accent === 'orange' ? 'text-brand-orange'
    : accent === 'muted' ? 'text-gray-400'
    : 'text-brand-navy'
  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4">
      <div className="text-[10px] font-semibold tracking-[0.08em] uppercase text-gray-500">{label}</div>
      <div className={`mt-1 text-2xl font-bold tabular-nums ${accentClass}`}>{value}</div>
      {hint && <div className="text-[11px] text-gray-400 mt-0.5">{hint}</div>}
    </div>
  )
}

function Card({ title, action, children }: { title: string; action?: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="bg-white rounded-lg border border-gray-200 shadow-sm">
      <div className="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
        <h2 className="text-xs font-semibold tracking-[0.06em] uppercase text-gray-600">{title}</h2>
        {action}
      </div>
      <div className="p-4">{children}</div>
    </div>
  )
}

function ComingSoon({ label, hint }: { label: string; hint?: string }) {
  return (
    <div className="flex items-center justify-between px-3 py-2 rounded-md bg-gray-50 border border-dashed border-gray-200">
      <div className="min-w-0">
        <div className="text-sm font-medium text-gray-700">{label}</div>
        {hint && <div className="text-[11px] text-gray-400">{hint}</div>}
      </div>
      <span className="text-[10px] font-semibold tracking-wider uppercase text-brand-orange bg-brand-orange/10 px-2 py-0.5 rounded-full whitespace-nowrap">
        Coming soon
      </span>
    </div>
  )
}

// ── page ───────────────────────────────────────────────────────────

export default function UserProfilePage() {
  const navigate = useNavigate()
  const { toast } = useToast()

  // Live profile from /me/profile is the source of truth; localStorage
  // (login payload) is the placeholder until the query resolves so the
  // page never blanks out on slow networks.
  const { data: profile } = useMyProfile()
  const storedUser = useMemo(() => getStoredUser(), [])
  const storedRoles = useMemo(() => getStoredRoles(), [])
  const storedPerms = useMemo(() => getStoredPermissions(), [])

  const roles       = profile?.roles ?? storedRoles
  const permissions = profile?.permissions ?? storedPerms
  const fullName    = profile?.name  || storedUser?.name  || 'Unknown user'
  const email       = profile?.email || storedUser?.email || '—'
  const roleLabel   = profile?.role  || storedUser?.role  || roles[0] || 'No role assigned'

  const pendingApprovals  = profile?.stats.pending_approvals ?? 0
  const uwRules           = profile?.uw_rules ?? []

  const [showAllPerms, setShowAllPerms] = useState(false)
  const visiblePerms = showAllPerms ? permissions : permissions.slice(0, 10)

  const [avatarModalOpen, setAvatarModalOpen] = useState(false)
  const avatarUrl = profile?.avatar_url ?? null

  function handleLogout() {
    try { logout() } catch { /* ignore */ }
    navigate('/login', { replace: true })
  }

  return (
    <div className="p-6 space-y-4 max-w-6xl">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Profile</h1>
          <p className="text-sm text-gray-500">Your account, workload, and quick links.</p>
        </div>
      </div>

      {/* Identity card — avatar (uploaded or initials) + name + email + roles + edit */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
        <div className="flex items-center gap-4 flex-wrap">
          <button
            type="button"
            onClick={() => setAvatarModalOpen(true)}
            aria-label={avatarUrl ? 'Change avatar' : 'Upload avatar'}
            className="relative group flex-none rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy focus-visible:ring-offset-2 cursor-pointer"
          >
            {avatarUrl ? (
              <img
                src={avatarUrl}
                alt={`Avatar for ${fullName}`}
                className="w-16 h-16 rounded-full object-cover border border-gray-200"
              />
            ) : (
              <div
                className={`w-16 h-16 rounded-full ${avatarBg(email)} text-white flex items-center justify-center text-xl font-bold select-none`}
                aria-hidden="true"
              >
                {initials(fullName, email)}
              </div>
            )}
            <span className="absolute inset-0 rounded-full bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center">
              <svg className="w-5 h-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
          </button>
          <div className="min-w-0 flex-1">
            <div className="flex items-baseline gap-2 flex-wrap">
              <h2 className="text-xl font-bold text-gray-800">{fullName}</h2>
              <span className="text-sm text-gray-500">· {email}</span>
            </div>
            <div className="mt-1.5 flex items-center gap-1.5 flex-wrap">
              <span className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-brand-navy/10 text-brand-navy">
                {roleLabel}
              </span>
              {roles.slice(1, 4).map(r => (
                <span key={r} className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                  {r}
                </span>
              ))}
              {roles.length > 4 && (
                <span className="text-[11px] text-gray-400">+{roles.length - 4} more</span>
              )}
            </div>
          </div>
          <button
            type="button"
            onClick={() => setAvatarModalOpen(true)}
            className="px-3 py-1.5 text-xs font-medium text-brand-navy border border-brand-navy/30 rounded-md hover:bg-brand-navy/5 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy"
          >
            {avatarUrl ? 'Change avatar' : 'Upload avatar'}
          </button>
        </div>
      </div>

      <AvatarUploadModal
        open={avatarModalOpen}
        onClose={() => setAvatarModalOpen(false)}
        hasExistingAvatar={!!avatarUrl}
      />

      {/* KPI strip — the two native ticket-count tiles were removed with the
          2026-07 Alpha Bridge cutover (they counted frozen pre-migration
          data); live ticket stats are on the Bridge board. */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        <KpiTile
          label="Last login"
          value={profile?.account.last_login_at ? fmtDateTime(profile.account.last_login_at) : '—'}
          hint={profile?.account.last_login_at ? 'Updated on every sign-in' : 'No prior session on file'}
        />
        <KpiTile
          label="Pending approvals"
          value={pendingApprovals}
          hint={pendingApprovals > 0 ? 'Items awaiting UW decision' : 'UW queue is clear'}
          accent={pendingApprovals > 0 ? 'orange' : 'navy'}
        />
      </div>

      {/* Two-column content */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {/* My tickets — the native list was retired with the 2026-07 Alpha
            Bridge cutover (it showed pre-migration snapshots). Tickets are
            raised via the Report an Issue widget and tracked in Bridge. */}
        <Card
          title="My Tickets"
          action={
            <a
              href={ALPHA_BRIDGE_BOARD_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="text-xs font-medium text-brand-navy hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy rounded"
            >
              Open Alpha Bridge →
            </a>
          }
        >
          <div className="py-4 text-center space-y-2">
            <p className="text-sm text-gray-500">
              Tickets now live in <span className="font-medium text-ink">Alpha Bridge</span> — raise them with
              the Report an Issue button and track their status on the Bridge board.
            </p>
            <a
              href={ALPHA_BRIDGE_BOARD_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-navy text-white text-sm font-medium rounded-md hover:bg-brand-navy/90 transition"
            >
              View my tickets in Alpha Bridge
              <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
        </Card>

        {/* Quick links */}
        <Card title="Quick Links">
          <div className="space-y-1.5">
            <QuickLink
              label="Audit trail"
              hint="Your actions across the system"
              onClick={() => navigate('/audit-trail')}
            />
            <QuickLink
              label="Help center"
              hint="Module guides & how-to articles"
              onClick={() => navigate('/help')}
            />
            <QuickLink
              label="Report a new issue"
              hint="Raise a ticket in Alpha Bridge"
              onClick={() => {
                void openAlphaBridgeWidget().catch(() =>
                  toast.error('Could not open the issue reporter — Alpha Bridge is unreachable. Please try again.')
                )
              }}
            />
            <button
              type="button"
              onClick={handleLogout}
              className="w-full flex items-center justify-between px-3 py-2 rounded-md border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 cursor-pointer"
            >
              <span>Sign out</span>
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </Card>

        {/* Account & Reporting — live data from /me/profile */}
        <Card title="Account & Reporting">
          <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt className="text-gray-500">Account created</dt>
            <dd className="text-gray-800 font-medium text-right">
              {profile?.account.created_at ? fmtDate(profile.account.created_at) : '—'}
            </dd>
            <dt className="text-gray-500">Last login</dt>
            <dd className="text-gray-800 font-medium text-right">
              {profile?.account.last_login_at ? fmtDateTime(profile.account.last_login_at) : '—'}
            </dd>
            <dt className="text-gray-500">Password changed</dt>
            <dd className="text-gray-800 font-medium text-right">
              {profile?.account.password_changed_at ? fmtDateTime(profile.account.password_changed_at) : '—'}
            </dd>
            <dt className="text-gray-500">Status</dt>
            <dd className={`font-medium text-right ${profile?.active === false ? 'text-red-600' : 'text-emerald-700'}`}>
              {profile?.active === false ? 'Inactive' : 'Active'}
            </dd>
            <dt className="text-gray-500">Reporting manager</dt>
            <dd className="text-gray-800 font-medium text-right truncate" title={profile?.manager?.email ?? ''}>
              {profile?.manager
                ? <span>{profile.manager.name} <span className="text-gray-400 font-normal">· {profile.manager.email}</span></span>
                : <span className="text-gray-400 italic font-normal">Not assigned</span>}
            </dd>
          </dl>
        </Card>

        {/* UW Rules & Authorization — each role can be bound to a Validation
            Rule Group; that mapping decides which rule set gates Submit-to-
            Approval transitions for this user. */}
        <Card
          title="UW Rules & Authorization"
          action={
            <button
              onClick={() => navigate('/underwriting')}
              className="text-xs font-medium text-brand-navy hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy rounded"
            >
              {pendingApprovals > 0 ? `Open UW queue (${pendingApprovals}) →` : 'Open UW queue →'}
            </button>
          }
        >
          {uwRules.length === 0 ? (
            <div className="text-sm text-gray-400 italic py-2">
              No roles mapped to a UW rule group yet.
            </div>
          ) : (
            <ul className="divide-y divide-gray-100 -my-2">
              {uwRules.map(r => (
                <li key={r.role_id} className="py-2.5 flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <div className="text-sm font-medium text-gray-800 truncate">{r.role_name}</div>
                    {r.rule_group_desc && (
                      <div className="text-[11px] text-gray-500 truncate" title={r.rule_group_desc}>
                        {r.rule_group_desc}
                      </div>
                    )}
                  </div>
                  {r.rule_group != null ? (
                    <span
                      className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-brand-navy/10 text-brand-navy whitespace-nowrap"
                      title={r.rule_group_code ?? `Group #${r.rule_group}`}
                    >
                      {r.rule_group_code ?? `Group #${r.rule_group}`}
                    </span>
                  ) : (
                    <span className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 whitespace-nowrap">
                      No rule group
                    </span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>

        {/* Permissions */}
        <Card
          title={`My Permissions (${permissions.length})`}
          action={
            permissions.length > 10 && (
              <button
                onClick={() => setShowAllPerms(v => !v)}
                className="text-xs font-medium text-brand-navy hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy rounded"
              >
                {showAllPerms ? 'Show less' : `Show all (${permissions.length})`}
              </button>
            )
          }
        >
          {permissions.length === 0 ? (
            <div className="text-sm text-gray-400 italic py-2">No permissions assigned.</div>
          ) : (
            <div className="flex flex-wrap gap-1.5">
              {visiblePerms.map(p => (
                <span
                  key={p}
                  className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-700"
                  title={p}
                >
                  {p}
                </span>
              ))}
            </div>
          )}
        </Card>

        {/* What's still ahead — avatar / UW rules / pending approvals have
            shipped; the remaining items move to Phase 3. */}
        <Card title="Coming Next">
          <div className="space-y-2">
            <ComingSoon label="Work-session duration" hint="Active time per day" />
            <ComingSoon label="Notification preferences" hint="Email / in-app / SMS per event type" />
            <ComingSoon label="Language preference" hint="Setswana / English toggle" />
            <ComingSoon label="Per-user timezone" hint="Display dates in your local TZ" />
            <ComingSoon label="API tokens" hint="Personal automation tokens" />
          </div>
        </Card>
      </div>
    </div>
  )
}

// ── QuickLink helper (declared after the page to keep the page body the
//    first thing a reader sees in this file) ─────────────────────────
function QuickLink({ label, hint, onClick }: { label: string; hint: string; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="w-full flex items-center justify-between px-3 py-2 rounded-md border border-gray-200 hover:border-brand-navy hover:text-brand-navy text-sm text-gray-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy cursor-pointer text-left"
    >
      <div className="min-w-0">
        <div className="font-medium">{label}</div>
        <div className="text-[11px] text-gray-400">{hint}</div>
      </div>
      <svg className="w-4 h-4 text-gray-400 flex-none" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
      </svg>
    </button>
  )
}
