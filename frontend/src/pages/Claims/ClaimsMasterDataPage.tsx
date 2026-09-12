import { useState, useEffect, useCallback } from 'react'
import { createPortal } from 'react-dom'
import { getStoredRoles } from '../../api/auth'
import EmptyState from '../../components/common/EmptyState'
import {
  fetchMasterDataSummary, fetchClaimHandlers, fetchBrokers, fetchReinsurers,
  fetchMasterSuppliers, fetchSystemUsers, fetchClaimTypeMap,
  toggleSupplierApproval, fetchConfigEntries, createConfigEntry,
  updateConfigEntry, deleteConfigEntry,
  type MasterDataSummary, type MasterSupplier, type SystemUser,
  type ClaimTypeMap, type ClaimsConfigEntry, type ReinsurerTreatyFallback,
} from '../../api/claimsMasterData'
import {
  fetchAssessors, createAssessor, updateAssessor, deleteAssessor,
  type Assessor, type AssessorPayload,
} from '../../api/assessors'

const ADMIN_ROLES = ['Admin', 'admin', 'Super Admin']

/** Tile keys drive which detail manager renders. */
type TileKey =
  | 'handlers' | 'assessors_motor' | 'assessors_non_motor'
  | 'panel_beaters' | 'glass_suppliers' | 'reinsurers' | 'brokers'
  | 'system_users' | 'claim_type_map'
  | 'comment_priorities' | 'mention_domains' | 'notification_settings'
  | 'notification_pilot_numbers' | 'policy_library_branches'
  | 'policy_library_products' | 'policy_library_coverages' | 'fac_clients'

interface TileDef {
  key: TileKey
  title: string
  subtitle: string
  group: string
  /** How to read the count from the summary payload. */
  count?: (s: MasterDataSummary) => number | undefined
  /** claims_config category slug (for config-store tiles). */
  configCategory?: string
}

const TILES: TileDef[] = [
  { key: 'handlers', title: 'Claims Handlers', subtitle: 'users ∩ role Claim Handler', group: 'People (read-through)', count: s => s.handlers },
  { key: 'assessors_motor', title: 'Motor Assessors', subtitle: 'assessors · category motor', group: 'People (read-through)', count: s => s.assessorsMotor },
  { key: 'assessors_non_motor', title: 'Non-Motor Assessors', subtitle: 'assessors · category non-motor', group: 'People (read-through)', count: s => s.assessorsNonMotor },
  { key: 'brokers', title: 'Approved Brokers', subtitle: 'agencies (read-only)', group: 'People (read-through)', count: s => s.brokers },
  { key: 'system_users', title: 'System Users', subtitle: 'users + roles (read-only)', group: 'People (read-through)', count: s => s.systemUsers },

  { key: 'panel_beaters', title: 'Approved Panel Beaters', subtitle: 'suppliers · Motor Vehicle Accident', group: 'Suppliers (read-through)', count: s => s.panelBeaters },
  { key: 'glass_suppliers', title: 'Approved Glass Suppliers', subtitle: 'suppliers · Glass', group: 'Suppliers (read-through)', count: s => s.glassSuppliers },
  { key: 'reinsurers', title: 'FAC Reinsurers', subtitle: 'reinsurer (+ treaty fallback)', group: 'Suppliers (read-through)', count: s => s.reinsurers },

  { key: 'claim_type_map', title: 'Claim-Type Map', subtitle: 'derived from TYPE_MAP', group: 'Derived' },

  { key: 'comment_priorities', title: 'Comment Priorities', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'comment_priorities' },
  { key: 'mention_domains', title: 'Mention Allowed Domains', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'mention_domains' },
  { key: 'notification_settings', title: 'Notification Settings', subtitle: 'claims_config (key/value)', group: 'Tracker-only lists', configCategory: 'notification_settings' },
  { key: 'notification_pilot_numbers', title: 'Notification Pilot Numbers', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'notification_pilot_numbers' },
  { key: 'policy_library_branches', title: 'Policy Library — Branches', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'policy_library_branches' },
  { key: 'policy_library_products', title: 'Policy Library — Products', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'policy_library_products' },
  { key: 'policy_library_coverages', title: 'Policy Library — Coverages', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'policy_library_coverages' },
  { key: 'fac_clients', title: 'FAC Clients', subtitle: 'claims_config', group: 'Tracker-only lists', configCategory: 'fac_clients' },
]

export default function ClaimsMasterDataPage() {
  const roles = getStoredRoles()
  const isAdmin = roles.some(r => ADMIN_ROLES.includes(r))

  const [summary, setSummary] = useState<MasterDataSummary | null>(null)
  const [active, setActive] = useState<TileKey | null>(null)

  useEffect(() => {
    if (!isAdmin) return
    fetchMasterDataSummary().then(setSummary).catch(() => setSummary(null))
  }, [isAdmin])

  if (!isAdmin) {
    return (
      <div className="p-6">
        <EmptyState title="Access restricted" description="Claims Master Data is available to Admin and Super Admin only." />
      </div>
    )
  }

  const activeTile = TILES.find(t => t.key === active) || null

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center gap-3">
        {active && (
          <button onClick={() => setActive(null)} className="text-sm text-gray-600 hover:underline">← Back</button>
        )}
        <div>
          <h1 className="text-2xl font-bold text-gray-800">{activeTile ? activeTile.title : 'Claims Master Data'}</h1>
          <p className="text-sm text-gray-500">{activeTile ? activeTile.subtitle : 'Reference lists for the Claims module. Read-through tiles reflect Graphite’s live tables; the tracker-only lists are stored in claims_config.'}</p>
        </div>
      </div>

      {!activeTile && <TileGrid summary={summary} onOpen={setActive} />}

      {activeTile?.key === 'handlers' && <HandlersManager />}
      {activeTile?.key === 'assessors_motor' && <AssessorManager category="motor" />}
      {activeTile?.key === 'assessors_non_motor' && <AssessorManager category="non_motor" />}
      {activeTile?.key === 'panel_beaters' && <SupplierManager type="panel_beater" />}
      {activeTile?.key === 'glass_suppliers' && <SupplierManager type="glass" />}
      {activeTile?.key === 'reinsurers' && <ReinsurerManager />}
      {activeTile?.key === 'brokers' && <BrokerManager />}
      {activeTile?.key === 'system_users' && <SystemUsersManager />}
      {activeTile?.key === 'claim_type_map' && <ClaimTypeMapView />}
      {activeTile?.configCategory && <ConfigListManager category={activeTile.configCategory} title={activeTile.title} keyValue={activeTile.key === 'notification_settings'} />}
    </div>
  )
}

// ─── Landing grid ─────────────────────────────────────────────────────────

function TileGrid({ summary, onOpen }: { summary: MasterDataSummary | null; onOpen: (k: TileKey) => void }) {
  const groups = Array.from(new Set(TILES.map(t => t.group)))
  return (
    <div className="space-y-6">
      {summary && !summary.flagsAvailable && (
        <div className="rounded-md bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-800">
          Supplier incentive-approval columns are not present on this environment yet. Panel-beater / glass approval toggles are hidden until the incentive-flags migration is deployed.
        </div>
      )}
      {groups.map(group => (
        <div key={group}>
          <h2 className="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">{group}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            {TILES.filter(t => t.group === group).map(t => {
              const count = summary && t.count ? t.count(summary) : (summary && t.configCategory ? summary.config?.[t.configCategory] : undefined)
              return (
                <button key={t.key} onClick={() => onOpen(t.key)}
                  className="text-left bg-white shadow rounded-lg p-4 hover:shadow-md hover:ring-1 hover:ring-blue-200 transition">
                  <div className="flex items-start justify-between gap-2">
                    <span className="font-semibold text-gray-800">{t.title}</span>
                    {count !== undefined && <span className="text-lg font-bold text-blue-600">{count}</span>}
                  </div>
                  <p className="text-xs text-gray-400 mt-1">{t.subtitle}</p>
                </button>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  )
}

// ─── Small shared building blocks ───────────────────────────────────────────

function Panel({ children }: { children: React.ReactNode }) {
  return <div className="bg-white shadow rounded-lg overflow-hidden">{children}</div>
}

function SearchBar({ value, onChange, placeholder, right }: { value: string; onChange: (v: string) => void; placeholder: string; right?: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-2 mb-3">
      <input value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
        className="px-3 py-1.5 border rounded-md text-sm w-72" />
      {right}
    </div>
  )
}

function StatusPill({ active }: { active: boolean }) {
  return (
    <span className={`px-2 py-0.5 text-xs rounded-full ${active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
      {active ? 'Active' : 'Inactive'}
    </span>
  )
}

// ─── Claims Handlers (read-only) ────────────────────────────────────────────

function HandlersManager() {
  const [search, setSearch] = useState('')
  const [rows, setRows] = useState<{ id: number; name: string; email: string | null; isActive: boolean }[]>([])
  const [loading, setLoading] = useState(true)
  useEffect(() => {
    setLoading(true)
    fetchClaimHandlers(search).then(setRows).catch(() => setRows([])).finally(() => setLoading(false))
  }, [search])
  return (
    <div>
      <SearchBar value={search} onChange={setSearch} placeholder="Search name or email…" />
      <Panel>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={3} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={3} className="p-0"><EmptyState compact title="No claim handlers" description="No users hold the Claim Handler role." /></td></tr>}
            {rows.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{r.name || '—'}</td>
                <td className="px-4 py-3 text-gray-600">{r.email || '—'}</td>
                <td className="px-4 py-3"><StatusPill active={r.isActive} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </Panel>
      <p className="text-xs text-gray-400 mt-2">Read-through: users with the Spatie “Claim Handler” role. Manage role membership in Roles &amp; Permissions.</p>
    </div>
  )
}

// ─── Assessors (CRUD, category-scoped) ──────────────────────────────────────

function AssessorManager({ category }: { category: 'motor' | 'non_motor' }) {
  const EMPTY: AssessorPayload = { name: '', email: '', phone: '', company: '', category, is_active: true, notes: '' }
  const [rows, setRows] = useState<Assessor[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<AssessorPayload>(EMPTY)
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    setLoading(true)
    fetchAssessors({ category, search: search || undefined, per_page: 200 })
      .then(r => setRows(r.data)).catch(() => setRows([])).finally(() => setLoading(false))
  }, [category, search])
  useEffect(() => { load() }, [load])

  function openCreate() { setEditingId(null); setForm({ ...EMPTY, category }); setModalOpen(true) }
  function openEdit(a: Assessor) {
    setEditingId(a.id)
    setForm({ name: a.name, email: a.email ?? '', phone: a.phone ?? '', company: a.company ?? '', category: (a.category ?? category) as AssessorPayload['category'], is_active: a.is_active, notes: a.notes ?? '' })
    setModalOpen(true)
  }
  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await updateAssessor(editingId, form); else await createAssessor(form)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to save assessor.') }
    finally { setSaving(false) }
  }
  async function handleDelete(a: Assessor) {
    if (!confirm(`Delete assessor "${a.name}"?`)) return
    try { await deleteAssessor(a.id); load() } catch (e: any) { alert(e.response?.data?.message || 'Failed to delete.') }
  }

  return (
    <div>
      <SearchBar value={search} onChange={setSearch} placeholder="Search name, email, company…"
        right={<button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Assessor</button>} />
      <Panel>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No assessors yet" description="Add one, or run the seeding command." /></td></tr>}
            {rows.map(a => (
              <tr key={a.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{a.name}</td>
                <td className="px-4 py-3 text-xs text-gray-500"><div>{a.email || '—'}</div><div>{a.phone || ''}</div></td>
                <td className="px-4 py-3 text-gray-600">{a.company || '—'}</td>
                <td className="px-4 py-3"><StatusPill active={a.is_active} /></td>
                <td className="px-4 py-3 text-right space-x-3">
                  <button onClick={() => openEdit(a)} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => handleDelete(a)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Panel>

      {modalOpen && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Assessor' : 'Add Assessor'}</h2>
            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2"><label className="block text-xs font-medium text-gray-500 mb-1">Name *</label><input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Email</label><input value={form.email ?? ''} onChange={e => setForm({ ...form, email: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Phone</label><input value={form.phone ?? ''} onChange={e => setForm({ ...form, phone: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div className="col-span-2"><label className="block text-xs font-medium text-gray-500 mb-1">Company</label><input value={form.company ?? ''} onChange={e => setForm({ ...form, company: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div className="col-span-2"><label className="block text-xs font-medium text-gray-500 mb-1">Notes</label><textarea value={form.notes ?? ''} onChange={e => setForm({ ...form, notes: e.target.value })} rows={3} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div className="col-span-2"><label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!!form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} className="rounded" /> Active</label></div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.name.trim()} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>, document.body)}
      <p className="text-xs text-gray-400 mt-2">Read-through of the shared <code>assessors</code> table, filtered to this category.</p>
    </div>
  )
}

// ─── Suppliers (approval toggle) ────────────────────────────────────────────

function SupplierManager({ type }: { type: 'panel_beater' | 'glass' }) {
  const [rows, setRows] = useState<MasterSupplier[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [flagsAvailable, setFlagsAvailable] = useState(true)
  const [busyId, setBusyId] = useState<number | null>(null)

  const load = useCallback(() => {
    setLoading(true)
    fetchMasterSuppliers({ type, search: search || undefined, page, per_page: 25 })
      .then(r => { setRows(r.data); setHasMore(r.meta.has_more); setFlagsAvailable(r.meta.flagsAvailable) })
      .catch(() => setRows([])).finally(() => setLoading(false))
  }, [type, search, page])
  useEffect(() => { load() }, [load])

  async function toggle(s: MasterSupplier) {
    const isGlass = type === 'glass'
    const current = isGlass ? s.isApprovedGlassSupplier : s.isApprovedPanelBeater
    setBusyId(s.id)
    try {
      await toggleSupplierApproval(s.id, isGlass
        ? { is_approved_glass_supplier: !current }
        : { is_approved_panel_beater: !current })
      load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to update approval.') }
    finally { setBusyId(null) }
  }

  const approvedOf = (s: MasterSupplier) => type === 'glass' ? s.isApprovedGlassSupplier : s.isApprovedPanelBeater

  return (
    <div>
      {!flagsAvailable && (
        <div className="rounded-md bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-800 mb-3">
          Approval columns not present on this environment — showing the supplier list read-only. Deploy the incentive-flags migration to enable toggling.
        </div>
      )}
      <SearchBar value={search} onChange={v => { setSearch(v); setPage(1) }} placeholder="Search supplier name or email…" />
      <Panel>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
            {flagsAvailable && <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>}
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No suppliers" description="Nothing matches this filter." /></td></tr>}
            {rows.map(s => (
              <tr key={s.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3 text-xs text-gray-500"><div>{s.email || '—'}</div><div>{s.phone || ''}</div></td>
                <td className="px-4 py-3 text-gray-600">{s.location || '—'}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 text-xs rounded-full ${approvedOf(s) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                    {approvedOf(s) ? 'Approved' : 'Not approved'}
                  </span>
                </td>
                {flagsAvailable && (
                  <td className="px-4 py-3 text-right">
                    <button disabled={busyId === s.id} onClick={() => toggle(s)}
                      className="text-sm text-blue-600 hover:underline disabled:opacity-40">
                      {approvedOf(s) ? 'Revoke' : 'Approve'}
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </Panel>
      <div className="flex justify-between mt-3">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button>
        <span className="text-sm text-gray-500">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button>
      </div>
      <p className="text-xs text-gray-400 mt-2">Read-through of <code>suppliers</code>. Approving sets the incentive flag on the existing row (no new record).</p>
    </div>
  )
}

// ─── FAC Reinsurers (read + treaty fallback) ────────────────────────────────

function ReinsurerManager() {
  const [rows, setRows] = useState<{ id: number; companyName: string | null; email: string | null; cellphone: string | null }[]>([])
  const [fallback, setFallback] = useState<ReinsurerTreatyFallback[]>([])
  const [usingFallback, setUsingFallback] = useState(false)
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  useEffect(() => {
    setLoading(true)
    fetchReinsurers(search)
      .then(r => { setRows(r.data); setFallback(r.meta.fallbackTreaties); setUsingFallback(r.meta.usingFallback) })
      .catch(() => setRows([])).finally(() => setLoading(false))
  }, [search])
  return (
    <div>
      <SearchBar value={search} onChange={setSearch} placeholder="Search reinsurer or email…" />
      {usingFallback && (
        <div className="rounded-md bg-blue-50 border border-blue-200 px-4 py-2 text-sm text-blue-800 mb-3">
          The <code>reinsurer</code> table is empty — showing existing <code>reinsurance_treaty</code> rows as fallback context. Add reinsurers via Reinsurance → Reinsurers.
        </div>
      )}
      <Panel>
        {!usingFallback ? (
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cellphone</th>
            </tr></thead>
            <tbody className="divide-y divide-gray-200">
              {loading && <tr><td colSpan={3} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
              {!loading && rows.length === 0 && <tr><td colSpan={3} className="p-0"><EmptyState compact title="No reinsurers" description="Add reinsurers via Reinsurance → Reinsurers." /></td></tr>}
              {rows.map(r => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium">{r.companyName || '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{r.email || '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{r.cellphone || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Treaty</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
            </tr></thead>
            <tbody className="divide-y divide-gray-200">
              {loading && <tr><td colSpan={2} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
              {!loading && fallback.length === 0 && <tr><td colSpan={2} className="p-0"><EmptyState compact title="No reinsurers or treaties" description="Nothing to show yet." /></td></tr>}
              {fallback.map(t => (
                <tr key={t.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium">{t.treatyName || '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{t.treatyNumber || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>
    </div>
  )
}

// ─── Approved Brokers (read-only agencies) ──────────────────────────────────

function BrokerManager() {
  const [rows, setRows] = useState<{ id: number; name: string; isActive: boolean }[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  useEffect(() => {
    setLoading(true)
    fetchBrokers(search).then(setRows).catch(() => setRows([])).finally(() => setLoading(false))
  }, [search])
  return (
    <div>
      <SearchBar value={search} onChange={setSearch} placeholder="Search broker…" />
      <Panel>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Broker / Agency</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={2} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={2} className="p-0"><EmptyState compact title="No brokers" description="Nothing matches this search." /></td></tr>}
            {rows.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{r.name}</td>
                <td className="px-4 py-3"><StatusPill active={r.isActive} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </Panel>
      <p className="text-xs text-gray-400 mt-2">Read-through of the <code>agencies</code> table (Approved Brokers == agencies). Manage agencies in their own admin surface.</p>
    </div>
  )
}

// ─── System Users (read-only, paginated) ────────────────────────────────────

function SystemUsersManager() {
  const [rows, setRows] = useState<SystemUser[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  useEffect(() => {
    setLoading(true)
    fetchSystemUsers({ search: search || undefined, page, per_page: 25 })
      .then(r => { setRows(r.data); setHasMore(r.meta.has_more) })
      .catch(() => setRows([])).finally(() => setLoading(false))
  }, [search, page])
  return (
    <div>
      <SearchBar value={search} onChange={v => { setSearch(v); setPage(1) }} placeholder="Search name or email…" />
      <Panel>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Roles</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={4} className="p-0"><EmptyState compact title="No users" description="Nothing matches this search." /></td></tr>}
            {rows.map(u => (
              <tr key={u.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{u.name || '—'}</td>
                <td className="px-4 py-3 text-gray-600">{u.email || '—'}</td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-1">
                    {u.roles.length === 0 && <span className="text-gray-400 text-xs">—</span>}
                    {u.roles.map(r => <span key={r} className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">{r}</span>)}
                  </div>
                </td>
                <td className="px-4 py-3"><StatusPill active={u.isActive} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </Panel>
      <div className="flex justify-between mt-3">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button>
        <span className="text-sm text-gray-500">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button>
      </div>
      <p className="text-xs text-gray-400 mt-2">Read-only view. Full tracker-user → Spatie role sync is a separate task.</p>
    </div>
  )
}

// ─── Claim-Type Map (derived) ───────────────────────────────────────────────

function ClaimTypeMapView() {
  const [map, setMap] = useState<ClaimTypeMap | null>(null)
  const [loading, setLoading] = useState(true)
  useEffect(() => { fetchClaimTypeMap().then(setMap).catch(() => setMap(null)).finally(() => setLoading(false)) }, [])
  if (loading) return <div className="px-4 py-8 text-center text-gray-400">Loading…</div>
  if (!map) return <EmptyState title="Unavailable" description="Could not load the claim-type map." />
  return (
    <div className="space-y-5">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Panel>
          <div className="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase text-gray-500">Motor claim types ({map.motorTypes.length})</div>
          <ul className="divide-y divide-gray-100">{map.motorTypes.map(t => <li key={t} className="px-4 py-2 text-sm">{t}</li>)}</ul>
        </Panel>
        <Panel>
          <div className="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase text-gray-500">Non-motor claim types ({map.nonMotorTypes.length})</div>
          <ul className="divide-y divide-gray-100 max-h-96 overflow-y-auto">{map.nonMotorTypes.map(t => <li key={t} className="px-4 py-2 text-sm">{t}</li>)}</ul>
        </Panel>
      </div>
      <Panel>
        <div className="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase text-gray-500">Alias → Canonical ({map.aliases.length})</div>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Alias (input)</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Canonical</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Motor?</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-100">
            {map.aliases.map(a => (
              <tr key={a.alias}><td className="px-4 py-2 font-mono text-xs">{a.alias}</td><td className="px-4 py-2">{a.canonical}</td><td className="px-4 py-2">{a.isMotor ? 'Yes' : ''}</td></tr>
            ))}
          </tbody>
        </table>
      </Panel>
      <p className="text-xs text-gray-400">Derived from <code>ClaimsTrackerController::TYPE_MAP</code> — the single source of truth. Read-only (not stored).</p>
    </div>
  )
}

// ─── Generic claims_config list manager (CRUD) ──────────────────────────────

function ConfigListManager({ category, title, keyValue }: { category: string; title: string; keyValue: boolean }) {
  const [rows, setRows] = useState<ClaimsConfigEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [label, setLabel] = useState('')
  const [value, setValue] = useState('')
  const [active, setActive] = useState(true)
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    setLoading(true)
    fetchConfigEntries(category, search || undefined).then(setRows).catch(() => setRows([])).finally(() => setLoading(false))
  }, [category, search])
  useEffect(() => { load() }, [load])

  function openCreate() { setEditingId(null); setLabel(''); setValue(''); setActive(true); setModalOpen(true) }
  function openEdit(r: ClaimsConfigEntry) { setEditingId(r.id); setLabel(r.label); setValue(r.value ?? ''); setActive(r.is_active); setModalOpen(true) }
  async function handleSave() {
    setSaving(true)
    try {
      const payload = { label, value: keyValue ? value : undefined, is_active: active }
      if (editingId) await updateConfigEntry(category, editingId, payload)
      else await createConfigEntry(category, payload)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to save entry.') }
    finally { setSaving(false) }
  }
  async function handleDelete(r: ClaimsConfigEntry) {
    if (!confirm(`Delete "${r.label}"?`)) return
    try { await deleteConfigEntry(category, r.id); load() } catch (e: any) { alert(e.response?.data?.message || 'Failed to delete.') }
  }

  return (
    <div>
      <SearchBar value={search} onChange={setSearch} placeholder="Search…"
        right={<button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add</button>} />
      <Panel>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{keyValue ? 'Setting' : 'Value'}</th>
            {keyValue && <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>}
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={keyValue ? 4 : 3} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={keyValue ? 4 : 3} className="p-0"><EmptyState compact title={`No ${title.toLowerCase()} yet`} description="Add an entry to get started." /></td></tr>}
            {rows.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{r.label}</td>
                {keyValue && <td className="px-4 py-3 text-gray-600">{r.value || '—'}</td>}
                <td className="px-4 py-3"><StatusPill active={r.is_active} /></td>
                <td className="px-4 py-3 text-right space-x-3">
                  <button onClick={() => openEdit(r)} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => handleDelete(r)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Panel>

      {modalOpen && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5">
            <h2 className="text-lg font-bold">{editingId ? 'Edit' : 'Add'} — {title}</h2>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">{keyValue ? 'Setting key *' : 'Value *'}</label>
              <input value={label} onChange={e => setLabel(e.target.value)} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            {keyValue && (
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Value</label>
                <input value={value} onChange={e => setValue(e.target.value)} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            )}
            <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={active} onChange={e => setActive(e.target.checked)} className="rounded" /> Active</label>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !label.trim()} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>, document.body)}
      <p className="text-xs text-gray-400 mt-2">Stored in <code>claims_config</code> (category <code>{category}</code>) — the Graphite home for this tracker-only list.</p>
    </div>
  )
}
