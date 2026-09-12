import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import ClaimsChrome from './_chrome/ClaimsChrome'
import { getStoredRoles } from '../../api/auth'
import { useClaimsApiAccess } from '../../hooks/useClaimsApiAccess'
import type { ApiAccessToken } from '../../api/claimsApiAccess'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fmtDate, fmtDateTime } from '../../utils/format'

/**
 * Claims → Admin → API Access — a concept-level port of the Claims Tracker's
 * "API Access Monitor". The Tracker screen showed a per-request /api/* access
 * log plus an IP whitelist ("Trusted Sources"); Graphite has neither table.
 * Its genuine "who can call the API" source is the Sanctum personal-access-token
 * registry, so this renders that (read-only, token metadata only — never the
 * secret) with usage stats. Admin / Super Admin only.
 */

const ADMIN_ROLES = ['Admin', 'admin', 'Super Admin']

function StatTile({ label, value, accent }: { label: string; value: number | string; accent: string }) {
  return (
    <div className={`bg-surface rounded-lg border border-line border-l-4 ${accent} shadow-elev-sm px-4 py-3`}>
      <div className="text-[11px] font-medium text-ink-muted uppercase tracking-wide">{label}</div>
      <div className="text-2xl font-bold text-ink mt-0.5">{value}</div>
    </div>
  )
}

function StatusPill({ token }: { token: ApiAccessToken }) {
  const cls = token.expired
    ? 'bg-status-danger-bg text-status-danger-fg'
    : 'bg-status-success-bg text-status-success-fg'
  return (
    <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-medium ${cls}`}>
      {token.expired ? 'Expired' : 'Active'}
    </span>
  )
}

function Abilities({ abilities }: { abilities: string[] }) {
  if (!abilities.length) return <span className="text-ink-faint">—</span>
  const shown = abilities.slice(0, 4)
  const extra = abilities.length - shown.length
  return (
    <div className="flex flex-wrap gap-1">
      {shown.map((a, i) => (
        <span key={i} className="inline-block px-1.5 py-0.5 rounded bg-surface-2 text-ink-muted font-mono text-[10px]">
          {a === '*' ? 'all (*)' : a}
        </span>
      ))}
      {extra > 0 && <span className="text-[10px] text-ink-faint">+{extra} more</span>}
    </div>
  )
}

export default function ClaimsApiAccessPage() {
  const navigate = useNavigate()
  const isAdmin = getStoredRoles().some((r) => ADMIN_ROLES.includes(r))
  const query = useClaimsApiAccess(200, isAdmin)
  const [filter, setFilter] = useState('')

  const tokens = query.data?.tokens ?? []
  const filtered = useMemo(() => {
    const f = filter.toLowerCase().trim()
    if (!f) return tokens
    return tokens.filter((t) =>
      (t.name ?? '').toLowerCase().includes(f) ||
      (t.owner ?? '').toLowerCase().includes(f) ||
      t.abilities.some((a) => a.toLowerCase().includes(f)),
    )
  }, [tokens, filter])

  if (!isAdmin) {
    return (
      <div className="p-6">
        <ClaimsChrome />
        <EmptyState
          title="No access"
          description="API Access is available to administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  const stats = query.data?.stats
  const unavailable = query.data && query.data.available === false

  return (
    <div className="p-6">
      <ClaimsChrome onRefresh={() => query.refetch()} refreshing={query.isFetching} />

      <div className="mb-4">
        <h1 className="font-heading text-2xl font-bold text-ink">API Access</h1>
        <p className="text-xs text-ink-muted mt-0.5">
          API tokens that can authenticate against the Graphite API (Sanctum bearer tokens), with usage. Token metadata only — secrets are never shown.
        </p>
      </div>

      {/* Explanatory note — the Tracker's exact model has no Graphite equivalent */}
      <div className="rounded-lg border border-status-info-fg/30 bg-status-info-bg px-4 py-3 text-sm text-status-info-fg mb-5">
        <strong>Source: Sanctum personal access tokens.</strong> The Claims Tracker's per-request access
        log and IP whitelist ("Trusted Sources") have no Graphite equivalent, so this shows the genuine
        token registry that governs API access instead.
      </div>

      {query.isLoading ? (
        <div className="py-16 flex justify-center"><LoadingSpinner /></div>
      ) : query.isError ? (
        <EmptyState title="Could not load API access" description="The API-access registry could not be loaded. Try refreshing." />
      ) : unavailable ? (
        <EmptyState title="Not available" description={query.data?.note || 'The Sanctum token table is not present on this environment.'} />
      ) : (
        <>
          {/* Stats */}
          {stats && (
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-5">
              <StatTile label="Total tokens" value={stats.total_tokens} accent="border-l-brand-navy" />
              <StatTile label="Active" value={stats.active_tokens} accent="border-l-status-success-fg" />
              <StatTile label="Expired" value={stats.expired_tokens} accent="border-l-status-danger-fg" />
              <StatTile label="Used (24h)" value={stats.used_24h} accent="border-l-brand-orange" />
              <StatTile label="Used (7d)" value={stats.used_7d} accent="border-l-status-info-fg" />
              <StatTile label="Never used" value={stats.never_used} accent="border-l-ink-faint" />
            </div>
          )}

          {/* Token registry */}
          <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
            <div className="flex items-start justify-between gap-3 mb-4 flex-wrap">
              <div>
                <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Access Tokens</h2>
                <p className="text-xs text-ink-faint mt-0.5">Most recently used first. {tokens.length} token{tokens.length === 1 ? '' : 's'}.</p>
              </div>
              <input
                type="text"
                placeholder="Filter by name, owner or ability…"
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
                className="w-full sm:w-64 min-h-[44px] sm:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>

            {filtered.length === 0 ? (
              <EmptyState compact title="No tokens" description={filter ? 'No tokens match your filter.' : 'No API tokens have been issued.'} />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-xs text-ink-muted uppercase">
                    <tr>
                      <th className="text-left py-1.5 pr-3">Name</th>
                      <th className="text-left pr-3">Owner</th>
                      <th className="text-left pr-3">Abilities</th>
                      <th className="text-left pr-3 whitespace-nowrap">Last used</th>
                      <th className="text-left pr-3 whitespace-nowrap">Expires</th>
                      <th className="text-left pr-3 whitespace-nowrap">Created</th>
                      <th className="text-left">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-line">
                    {filtered.map((t) => (
                      <tr key={t.id}>
                        <td className="py-2 pr-3 text-ink font-medium">{t.name || <span className="text-ink-faint">(unnamed)</span>}</td>
                        <td className="pr-3 text-ink-muted">
                          {t.owner ? (
                            <>{t.owner}</>
                          ) : (
                            <span className="text-ink-faint">—</span>
                          )}
                        </td>
                        <td className="pr-3"><Abilities abilities={t.abilities} /></td>
                        <td className="pr-3 text-ink-muted whitespace-nowrap">{t.last_used_at ? fmtDateTime(t.last_used_at) : <span className="text-ink-faint">never</span>}</td>
                        <td className="pr-3 text-ink-muted whitespace-nowrap">{t.expires_at ? fmtDateTime(t.expires_at) : <span className="text-ink-faint">—</span>}</td>
                        <td className="pr-3 text-ink-muted whitespace-nowrap">{t.created_at ? fmtDate(t.created_at) : <span className="text-ink-faint">—</span>}</td>
                        <td><StatusPill token={t} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </div>
  )
}
