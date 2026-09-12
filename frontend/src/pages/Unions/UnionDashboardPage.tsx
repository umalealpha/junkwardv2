import { useState, useRef } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fetchUnion, fetchMembers, fetchLegalClaims, legalClaimPath, type UnionMember, type UnionLegalClaim } from '../../api/unions'

const money = (n: number) =>
  'P' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const genderLabel = (g: number | null) => (g === 1 ? 'Male' : g === 0 ? 'Female' : '—')

function Tile({ label, value, accent }: { label: string; value: string | number; accent?: string }) {
  return (
    <div className="bg-white rounded-lg shadow-sm border p-4">
      <div className="text-xs uppercase tracking-wide text-gray-500">{label}</div>
      <div className={`text-2xl font-bold mt-1 ${accent ?? 'text-gray-800'}`}>{value}</div>
    </div>
  )
}

export default function UnionDashboardPage() {
  const { id } = useParams<{ id: string }>()
  const [page, setPage] = useState(1)
  // Member search (BONU brief 2026-09-10): the roster here is thousands of
  // rows, so the dashboard needs the same name / ID search as the Members page.
  const [search, setSearch] = useState('')
  const searchRef = useRef<HTMLInputElement>(null)
  const runSearch = () => { setPage(1); setSearch(searchRef.current?.value.trim() ?? '') }
  const clearSearch = () => { if (searchRef.current) searchRef.current.value = ''; setPage(1); setSearch('') }

  const unionQ = useQuery({ queryKey: ['union', id], queryFn: () => fetchUnion(id!), enabled: !!id })
  const membersQ = useQuery({
    queryKey: ['union-members', id, { page, search }],
    queryFn: () => fetchMembers(id!, { page, per_page: 25, search }),
    enabled: !!id,
  })

  const union = unionQ.data
  const stats = union?.stats
  const members = membersQ.data?.data ?? []
  const meta = membersQ.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }

  const claimsQ = useQuery({
    queryKey: ['union-legal-claims', id],
    queryFn: () => fetchLegalClaims(id!, { per_page: 25 }),
    enabled: !!id,
  })
  const claims = claimsQ.data?.data ?? []

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <Link to="/unions" className="text-xs text-blue-600 hover:underline">← All Unions</Link>
          <h1 className="text-2xl font-bold text-gray-800 mt-1">
            {unionQ.isLoading ? 'Loading…' : (union?.union_name ?? 'Union')}
            {union?.union_code && <span className="ml-2 text-sm font-mono text-gray-400">{union.union_code}</span>}
            {union?.status === 0 && <span className="ml-2 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500 align-middle">Inactive</span>}
          </h1>
        </div>
        <div className="flex gap-2">
          <Link to={`/unions/${id}/payments`}
            className="px-4 py-2 border border-line text-ink text-sm font-medium rounded hover:bg-surface-2">Payments →</Link>
          <Link to={`/unions/members?union=${id}`}
            className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">Manage Members →</Link>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <Tile label="Total Members" value={stats?.total_members ?? '—'} />
        <Tile label="Active" value={stats?.active_members ?? '—'} accent="text-green-600" />
        <Tile label="Inactive" value={stats?.inactive_members ?? '—'} accent="text-gray-400" />
        <Tile label="Premium / member" value={stats ? money(stats.monthly_premium) : '—'} />
        <Tile label="Monthly Premium" value={stats ? money(stats.total_monthly_premium) : '—'} accent="text-blue-600" />
        <Tile label="Outstanding Claims" value={stats?.outstanding_claims ?? 0} />
      </div>

      <div className="bg-white rounded-lg shadow-sm border p-4">
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Group Policy</h2>
        {union?.policy ? (
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div><div className="text-xs text-gray-500">Policy Number</div><div className="font-mono text-gray-800">{union.policy.policy_number ?? '—'}</div></div>
            {/* The mapped Legal Insurance product, not just the product family —
                it is what sets this union's per-member premium. */}
            <div><div className="text-xs text-gray-500">Product</div><div className="text-gray-800">{union.plan_name ?? 'Legal Insurance'}</div></div>
            <div><div className="text-xs text-gray-500">Premium / member</div><div className="text-gray-800">{money(union.policy.premium)} / {union.policy.premium_freq ?? 'monthly'}</div></div>
            <div><div className="text-xs text-gray-500">Active Members</div><div className="text-gray-800">{stats?.active_members ?? 0}</div></div>
            <div><div className="text-xs text-gray-500">Total Monthly Premium</div><div className="font-semibold text-gray-800">{stats ? money(stats.total_monthly_premium) : '—'}</div></div>
          </div>
        ) : (
          <p className="text-sm text-gray-400">No group policy linked.</p>
        )}
      </div>

      <div>
        <div className="flex flex-wrap items-end justify-between gap-3 mb-3">
          <h2 className="text-sm font-semibold text-ink">Members{search && <span className="ml-2 text-xs font-normal text-ink-muted">matching “{search}”</span>}</h2>
          <div className="flex gap-1">
            <input ref={searchRef} type="text" placeholder="Search member by name, ID or contact…" defaultValue={search}
              onKeyDown={e => { if (e.key === 'Enter') runSearch() }}
              className="px-3 py-1.5 border border-line rounded-md text-sm w-72 bg-surface text-ink" />
            <button type="button" onClick={runSearch}
              className="px-3 py-1.5 border border-line rounded-md text-sm text-ink hover:bg-surface-2">Search</button>
            {search && (
              <button type="button" onClick={clearSearch} title="Clear search"
                className="px-2 py-1.5 text-sm text-ink-muted hover:text-ink">×</button>
            )}
          </div>
        </div>
        <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
          {membersQ.isFetching && !membersQ.isLoading && (
            <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center"><LoadingSpinner size="md" /></div>
          )}
          <DualScrollTable>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                  <th className="px-4 py-3 text-left">ID Number</th>
                  <th className="px-4 py-3 text-left">Name</th>
                  <th className="px-4 py-3 text-left">Type</th>
                  <th className="px-4 py-3 text-left">Date of Birth</th>
                  <th className="px-4 py-3 text-left">Gender</th>
                  <th className="px-4 py-3 text-left">Contact</th>
                  <th className="px-4 py-3 text-left">Nationality</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {membersQ.isLoading ? (
                  <tr><td colSpan={9} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
                ) : members.length === 0 ? (
                  <tr><td colSpan={9} className="p-0"><EmptyState compact title="No members" description={search ? `No members match “${search}”.` : 'No members registered for this union yet.'} /></td></tr>
                ) : members.map((m: UnionMember) => (
                  <tr key={m.id} className="hover:bg-gray-50">
                    <td className="px-4 py-2 text-gray-600 font-mono text-xs">{m.id_number}</td>
                    <td className="px-4 py-2 font-medium text-gray-800">{m.member_name}</td>
                    <td className="px-4 py-2 text-gray-600">{m.member_type ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{m.date_of_birth ? m.date_of_birth.slice(0, 10) : '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{genderLabel(m.gender)}</td>
                    <td className="px-4 py-2 text-gray-600">{m.contact_number ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{m.nationality ?? '—'}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs ${m.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                        {m.status === 1 ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right whitespace-nowrap">
                      <Link to={legalClaimPath(union?.union_code, id!, m.id)} state={{ member: m }}
                        className="text-blue-600 hover:underline text-xs">File Legal Claim</Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </DualScrollTable>
          {meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
              <span className="text-gray-500">Showing {meta.from}–{meta.to} of {meta.total}</span>
              <div className="flex items-center gap-1">
                <button disabled={meta.current_page === 1} onClick={() => setPage(p => p - 1)}
                  className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40">Prev</button>
                <span className="px-2 text-gray-600">{meta.current_page} / {meta.last_page}</span>
                <button disabled={meta.current_page === meta.last_page} onClick={() => setPage(p => p + 1)}
                  className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40">Next</button>
              </div>
            </div>
          )}
        </div>
      </div>

      <div>
        <h2 className="text-sm font-semibold text-ink mb-3">Legal Claims</h2>
        <div className="bg-surface rounded-lg shadow-sm border overflow-hidden relative">
          {claimsQ.isFetching && !claimsQ.isLoading && (
            <div className="absolute inset-0 bg-surface/50 z-10 flex items-center justify-center"><LoadingSpinner size="md" /></div>
          )}
          <DualScrollTable>
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                <tr>
                  <th className="px-4 py-3 text-left">Claim Number</th>
                  <th className="px-4 py-3 text-left">Form</th>
                  <th className="px-4 py-3 text-left">Member</th>
                  <th className="px-4 py-3 text-left">Omang / Passport</th>
                  <th className="px-4 py-3 text-left">Type of Matter</th>
                  <th className="px-4 py-3 text-left">Matter Arose</th>
                  <th className="px-4 py-3 text-left">Filed</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {claimsQ.isLoading ? (
                  <tr><td colSpan={9} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
                ) : claims.length === 0 ? (
                  <tr><td colSpan={9} className="p-0"><EmptyState compact title="No legal claims" description="No legal claims have been filed for this union yet." /></td></tr>
                ) : claims.map((c: UnionLegalClaim) => (
                  <tr key={c.id} className="hover:bg-surface-2">
                    <td className="px-4 py-2 font-mono text-xs">
                      {c.claim_id
                        ? <Link to={`/claims/${c.claim_id}`} className="text-blue-600 hover:underline">{c.claim_number ?? '—'}</Link>
                        : <span className="text-ink">{c.claim_number ?? '—'}</span>}
                    </td>
                    <td className="px-4 py-2 text-ink-muted text-xs">{c.form_name ?? '—'}</td>
                    <td className="px-4 py-2 text-ink">{c.insured_name ?? '—'}</td>
                    <td className="px-4 py-2 text-ink-muted font-mono text-xs">{c.omang_passport ?? '—'}</td>
                    <td className="px-4 py-2 text-ink-muted">{c.matter_type ?? '—'}</td>
                    <td className="px-4 py-2 text-ink-muted">{c.matter_arose_date ? c.matter_arose_date.slice(0, 10) : '—'}</td>
                    <td className="px-4 py-2 text-ink-muted">{c.created_at ? c.created_at.slice(0, 10) : '—'}</td>
                    <td className="px-4 py-2">
                      <span className="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{c.status}</span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      {c.claim_id && <Link to={`/claims/${c.claim_id}`} className="text-blue-600 hover:underline text-xs">View</Link>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </DualScrollTable>
        </div>
      </div>
    </div>
  )
}
