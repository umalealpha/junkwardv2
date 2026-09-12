import { useState, useEffect } from 'react'
import { createPortal } from 'react-dom'
import {
  fetchInflationRates, fetchInflationRateOptions, createInflationRate, updateInflationRate,
  deleteInflationRate, fetchInflationImpact, fetchInflationApplied,
  type InflationRate, type InflationRatePayload, type CoverageSectionOption,
  type InflationImpact, type InflationAppliedRow,
} from '../../api/inflationRates'
import EmptyState from '../../components/common/EmptyState'
import { getStoredPermissions, getStoredRoles } from '../../api/auth'

/**
 * Inflation Rates — the master UW edits to decide how much a sum insured moves
 * at renewal, per product / coverage / line / sum-insured band.
 *
 * The cron reads these rows:
 *   php artisan policy:inflate-buildings-si --master            (dry run)
 *   php artisan policy:inflate-buildings-si --master --apply    (apply)
 *
 * Every matcher left blank is a wildcard ("any"), and the MOST SPECIFIC rule
 * wins for a given line — line beats section beats product. Impact previews one
 * rule before it is trusted; Applied log reads back what actually moved.
 */

type FormState = {
  name: string
  product_id: string
  coverage_id: string
  coverage_name: string
  sub_coverage_id: string
  sub_coverage_name: string
  si_from: string
  si_to: string
  transaction_type: string
  effective_from: string
  effective_to: string
  pct: string
  priority: string
  is_active: boolean
  notes: string
}

const EMPTY: FormState = {
  name: '', product_id: '', coverage_id: '', coverage_name: '', sub_coverage_id: '', sub_coverage_name: '',
  si_from: '', si_to: '', transaction_type: 'ANNIVERSARY-RENEW', effective_from: '', effective_to: '',
  pct: '10', priority: '0', is_active: true, notes: '',
}

/**
 * Add / edit / delete belongs to ONE named owner — the "Inflation Rate Manager"
 * role — while reading is open to everyone, so anyone handling a renewal can
 * see why a sum insured moved.
 *
 * Mirrors the backend gate exactly (role_or_permission:Super Admin|Inflation
 * Rate Manager|inflation_rate_manage). Fail-closed: no match => no buttons.
 * Hiding the buttons is courtesy, not security — the API is the real gate.
 */
const MANAGE_ROLE = 'Inflation Rate Manager'
const MANAGE_PERMISSION = 'inflation_rate_manage'

function canManageRules(): boolean {
  const roles = getStoredRoles()
  return roles.includes(MANAGE_ROLE)
    || roles.includes('Super Admin')
    || getStoredPermissions().includes(MANAGE_PERMISSION)
}

const money = (v: number | null | undefined) =>
  v === null || v === undefined ? '—' : v.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const pctLabel = (v: number) => `${v > 0 ? '+' : ''}${Number(v.toFixed(4))}%`

export default function InflationRatesPage() {
  const [canManage] = useState(canManageRules)
  const [items, setItems] = useState<InflationRate[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [productFilter, setProductFilter] = useState('')
  const [products, setProducts] = useState<{ id: number; name: string }[]>([])

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<FormState>(EMPTY)
  const [sections, setSections] = useState<CoverageSectionOption[]>([])
  const [sectionsLoading, setSectionsLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [viewing, setViewing] = useState<InflationRate | null>(null)
  const [impact, setImpact] = useState<InflationImpact | null>(null)
  const [impactLoading, setImpactLoading] = useState(false)

  const [appliedOpen, setAppliedOpen] = useState(false)
  const [applied, setApplied] = useState<InflationAppliedRow[]>([])
  const [appliedTotal, setAppliedTotal] = useState(0)

  const load = () => {
    setLoading(true)
    fetchInflationRates({
      search: search || undefined,
      product_id: productFilter ? Number(productFilter) : undefined,
    })
      .then(setItems)
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [search, productFilter]) // eslint-disable-line react-hooks/exhaustive-deps

  // Product list once — the form and the filter share it.
  useEffect(() => {
    fetchInflationRateOptions().then(o => setProducts(o.products)).catch(() => setProducts([]))
  }, [])

  // Sections/lines follow the product chosen in the form.
  useEffect(() => {
    if (!modalOpen || !form.product_id) { setSections([]); return }
    setSectionsLoading(true)
    fetchInflationRateOptions(Number(form.product_id))
      .then(o => setSections(o.sections))
      .catch(() => setSections([]))
      .finally(() => setSectionsLoading(false))
  }, [modalOpen, form.product_id])

  // Dropdowns are keyed by NAME, not id: the coverage master repeats the same
  // Description across many rows, and a rule pinned to one of them would miss
  // the rest on live quotes. The id rides along only when the name is unique.
  const section = sections.find(s => s.name === form.coverage_name)
  const lines = section?.lines ?? []
  const line = lines.find(l => l.name === form.sub_coverage_name)

  // Editing a rule saved with an id but no readable name — backfill the name
  // once its product's sections have loaded, so the select can show it.
  useEffect(() => {
    if (!modalOpen || sections.length === 0) return
    if (form.coverage_name === '' && form.coverage_id) {
      const match = sections.find(s => s.ids.includes(Number(form.coverage_id)))
      if (match) setForm(f => ({ ...f, coverage_name: match.name }))
    }
    if (form.sub_coverage_name === '' && form.sub_coverage_id) {
      const owner = sections.find(s => s.lines.some(l => l.ids.includes(Number(form.sub_coverage_id))))
      const match = owner?.lines.find(l => l.ids.includes(Number(form.sub_coverage_id)))
      if (match) setForm(f => ({ ...f, coverage_name: f.coverage_name || owner!.name, sub_coverage_name: match.name }))
    }
  }, [modalOpen, sections]) // eslint-disable-line react-hooks/exhaustive-deps

  function openCreate() {
    setEditingId(null); setForm(EMPTY); setError(null); setModalOpen(true)
  }

  function openEdit(r: InflationRate) {
    setEditingId(r.id)
    setError(null)
    setForm({
      name: r.name ?? '',
      product_id: r.product_id ? String(r.product_id) : '',
      coverage_id: r.coverage_id ? String(r.coverage_id) : '',
      coverage_name: r.coverage_name ?? '',
      sub_coverage_id: r.sub_coverage_id ? String(r.sub_coverage_id) : '',
      sub_coverage_name: r.sub_coverage_name ?? '',
      si_from: r.si_from === null ? '' : String(r.si_from),
      si_to: r.si_to === null ? '' : String(r.si_to),
      transaction_type: r.transaction_type ?? '',
      effective_from: r.effective_from ?? '',
      effective_to: r.effective_to ?? '',
      pct: String(r.pct),
      priority: String(r.priority),
      is_active: r.is_active,
      notes: r.notes ?? '',
    })
    setModalOpen(true)
  }

  function payload(): InflationRatePayload {
    const num = (v: string) => (v.trim() === '' ? null : Number(v))
    return {
      name: form.name.trim() || null,
      product_id: num(form.product_id),
      coverage_id: num(form.coverage_id),
      // The id decides the match; the name is kept as the readable label of it,
      // and is the matcher on its own when no id was picked.
      coverage_name: form.coverage_name.trim() || null,
      sub_coverage_id: num(form.sub_coverage_id),
      sub_coverage_name: form.sub_coverage_name.trim() || null,
      si_from: num(form.si_from),
      si_to: num(form.si_to),
      transaction_type: form.transaction_type.trim() || null,
      effective_from: form.effective_from || null,
      effective_to: form.effective_to || null,
      pct: Number(form.pct),
      priority: Number(form.priority || 0),
      is_active: form.is_active,
      notes: form.notes.trim() || null,
    }
  }

  async function handleSave() {
    const pct = Number(form.pct)
    if (Number.isNaN(pct) || pct < -100 || pct > 100) {
      setError('The uplift percent must be between -100 and 100. Enter 10 for a 10% increase, not 1000.')
      return
    }
    setSaving(true); setError(null)
    try {
      if (editingId) await updateInflationRate(editingId, payload())
      else await createInflationRate(payload())
      setModalOpen(false); load()
    } catch (e: any) {
      const res = e.response?.data
      setError(res?.message || Object.values(res?.errors ?? {}).flat().join(' ') || 'Failed to save the rule.')
    } finally { setSaving(false) }
  }

  async function handleDelete(r: InflationRate) {
    if (!confirm(`Delete rule ${r.id}${r.name ? ` (${r.name})` : ''}? Rules that have already uplifted live quotes cannot be deleted — deactivate them instead.`)) return
    try { await deleteInflationRate(r.id); load() }
    catch (e: any) { alert(e.response?.data?.message || 'Failed to delete the rule.') }
  }

  async function openImpact(r: InflationRate) {
    setImpactLoading(true)
    try { setImpact(await fetchInflationImpact(r.id, 25)) }
    catch (e: any) { alert(e.response?.data?.message || 'Could not compute the impact.') }
    finally { setImpactLoading(false) }
  }

  async function openApplied(ruleId?: number) {
    setAppliedOpen(true)
    try {
      const r = await fetchInflationApplied({ rule_id: ruleId, limit: 100 })
      setApplied(r.data); setAppliedTotal(r.total)
    } catch { setApplied([]); setAppliedTotal(0) }
  }

  const productName = (id: number | null) =>
    id === null ? 'Any' : (products.find(p => p.id === id)?.name ?? `#${id}`)

  const band = (r: InflationRate) => {
    if (r.si_from === null && r.si_to === null) return 'Any'
    return `${r.si_from === null ? '0' : money(r.si_from)} → ${r.si_to === null ? '∞' : money(r.si_to)}`
  }

  const windowLabel = (r: InflationRate) => {
    if (!r.effective_from && !r.effective_to) return 'Always'
    return `${r.effective_from ?? '…'} → ${r.effective_to ?? 'open'}`
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-ink">Inflation Rates</h1>
          <p className="text-sm text-ink-muted mt-1 max-w-3xl">
            Sum-insured uplift applied at renewal, per product, coverage and sum-insured band. A blank
            field means <span className="font-medium">any</span>; where several rules match a line the
            most specific one wins (line beats section beats product). <span className="font-medium">0%</span> is
            a real decision — it holds that line at its current sum insured.
          </p>
        </div>
        <div className="flex gap-2 shrink-0">
          <select value={productFilter} onChange={e => setProductFilter(e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm">
            <option value="">All products</option>
            {products.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
          <input value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Search name, coverage, notes…" className="px-3 py-1.5 border border-line rounded-md text-sm w-56" />
          <button onClick={() => openApplied()} className="px-3 py-2 border border-line text-sm rounded-md hover:bg-surface-2">Applied log</button>
          {canManage && (
            <button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Rule</button>
          )}
        </div>
      </div>

      {!canManage && (
        <div className="px-3 py-2 bg-surface-2 border border-line text-ink-muted text-xs rounded-md">
          Read-only. Adding and editing inflation rules belongs to the
          <code className="mx-1 px-1.5 py-0.5 bg-surface rounded">{MANAGE_ROLE}</code>
          role — ask them for a change. You can still open Impact and the Applied log.
        </div>
      )}

      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface-2"><tr>
            {['Rule', 'Product', 'Section', 'Line', 'Sum insured band', 'Uplift', 'Effective', 'Priority', 'Status', 'Actions'].map((h, i) => (
              <th key={h} className={`px-4 py-3 text-xs font-medium text-ink-muted uppercase ${i === 9 ? 'text-right' : 'text-left'}`}>{h}</th>
            ))}
          </tr></thead>
          <tbody className="divide-y divide-line">
            {loading && <tr><td colSpan={10} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!loading && items.length === 0 && (
              <tr><td colSpan={10} className="p-0">
                <EmptyState compact title="No inflation rules yet"
                  description="Add a rule to tell the renewal cron how much to lift a sum insured." />
              </td></tr>
            )}
            {items.map(r => (
              <tr key={r.id} className={`hover:bg-surface-2 ${r.is_active ? '' : 'opacity-60'}`}>
                <td className="px-4 py-3">
                  <div className="font-medium">{r.name || `Rule #${r.id}`}</div>
                  <div className="text-xs text-ink-faint">#{r.id}</div>
                </td>
                <td className="px-4 py-3 text-ink-muted">{productName(r.product_id)}</td>
                <td className="px-4 py-3">{r.coverage_name || (r.coverage_id ? `#${r.coverage_id}` : <span className="text-ink-faint">Any</span>)}</td>
                <td className="px-4 py-3">{r.sub_coverage_name || (r.sub_coverage_id ? `#${r.sub_coverage_id}` : <span className="text-ink-faint">All lines</span>)}</td>
                <td className="px-4 py-3 text-xs text-ink-muted whitespace-nowrap">{band(r)}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${
                    r.pct === 0 ? 'bg-surface-2 text-ink-muted' : r.pct > 0 ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-700'
                  }`}>{r.pct === 0 ? 'Hold (0%)' : pctLabel(r.pct)}</span>
                </td>
                <td className="px-4 py-3 text-xs text-ink-muted whitespace-nowrap">
                  <div>{windowLabel(r)}</div>
                  <div className="text-ink-faint">{r.transaction_type || 'any transaction'}</div>
                </td>
                <td className="px-4 py-3 text-ink-muted">{r.priority}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 text-xs rounded-full ${r.is_active ? 'bg-green-100 text-green-700' : 'bg-surface-2 text-ink-muted'}`}>
                    {r.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td className="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                  <button onClick={() => openImpact(r)} disabled={impactLoading} className="text-sm text-ink-muted hover:underline disabled:opacity-40">Impact</button>
                  {canManage ? (
                    <>
                      <button onClick={() => openEdit(r)} className="text-sm text-blue-600 hover:underline">Edit</button>
                      <button onClick={() => handleDelete(r)} className="text-sm text-red-600 hover:underline">Delete</button>
                    </>
                  ) : (
                    <button onClick={() => setViewing(r)} className="text-sm text-blue-600 hover:underline">View</button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="text-xs text-ink-faint">
        Rules are applied by the cron, never by this screen:
        <code className="ml-1 px-1.5 py-0.5 bg-surface-2 rounded">php artisan policy:inflate-buildings-si --master</code>
        {' '}(dry run) then add <code className="px-1.5 py-0.5 bg-surface-2 rounded">--apply</code>.
      </div>

      {/* Create / Edit */}
      {modalOpen && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-2xl p-5 space-y-3 max-h-[88vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? `Edit Rule #${editingId}` : 'Add Inflation Rule'}</h2>

            {error && <div className="px-3 py-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-md">{error}</div>}

            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2">
                <label className="block text-xs font-medium text-ink-muted mb-1">Rule name</label>
                <input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })}
                  placeholder="e.g. 2026 Domestic buildings uplift"
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Product</label>
                <select value={form.product_id}
                  onChange={e => setForm({ ...form, product_id: e.target.value, coverage_id: '', coverage_name: '', sub_coverage_id: '', sub_coverage_name: '' })}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">Any product</option>
                  {products.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">
                  Section {sectionsLoading && <span className="text-ink-faint">(loading…)</span>}
                </label>
                <select value={form.coverage_name} disabled={!form.product_id}
                  onChange={e => {
                    const s = sections.find(x => x.name === e.target.value)
                    setForm({
                      ...form,
                      coverage_name: e.target.value,
                      coverage_id: s?.id ? String(s.id) : '',
                      sub_coverage_id: '', sub_coverage_name: '',
                    })
                  }}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm disabled:bg-surface-2">
                  <option value="">Any section</option>
                  {sections.map(s => <option key={s.name} value={s.name}>{s.name}</option>)}
                </select>
                {section && section.duplicates > 1 && (
                  <p className="text-[11px] text-ink-faint mt-1">
                    Matched by name — this section name covers {section.duplicates} coverage rows, and the rule applies to all of them.
                  </p>
                )}
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Line (Description)</label>
                <select value={form.sub_coverage_name} disabled={!form.coverage_name}
                  onChange={e => {
                    const l = lines.find(x => x.name === e.target.value)
                    setForm({ ...form, sub_coverage_name: e.target.value, sub_coverage_id: l?.id ? String(l.id) : '' })
                  }}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm disabled:bg-surface-2">
                  <option value="">All lines in the section</option>
                  {lines.map(l => <option key={l.name} value={l.name}>{l.name}</option>)}
                </select>
                {line && line.duplicates > 1 ? (
                  <p className="text-[11px] text-ink-faint mt-1">
                    Matched by name — “{line.name}” is carried by {line.duplicates} coverage rows, and the rule applies to all of them.
                  </p>
                ) : (
                  <p className="text-[11px] text-ink-faint mt-1">Leave on “All lines” only on purpose — it lifts every sum insured in the section.</p>
                )}
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Uplift % *</label>
                <input type="number" step="0.0001" min={-100} max={100} value={form.pct}
                  onChange={e => setForm({ ...form, pct: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                <p className="text-[11px] text-ink-faint mt-1">10 = +10%. 0 = hold this line. Negative reduces.</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Sum insured from</label>
                <input type="number" step="0.01" min={0} value={form.si_from}
                  onChange={e => setForm({ ...form, si_from: e.target.value })}
                  placeholder="no lower bound" className="w-full px-3 py-2 border border-line rounded-md text-sm" />
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Sum insured to</label>
                <input type="number" step="0.01" min={0} value={form.si_to}
                  onChange={e => setForm({ ...form, si_to: e.target.value })}
                  placeholder="no upper bound" className="w-full px-3 py-2 border border-line rounded-md text-sm" />
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction type</label>
                <select value={form.transaction_type} onChange={e => setForm({ ...form, transaction_type: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="ANNIVERSARY-RENEW">ANNIVERSARY-RENEW</option>
                  <option value="RENEW">RENEW</option>
                  <option value="">Any</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Priority</label>
                <input type="number" min={0} max={999} value={form.priority}
                  onChange={e => setForm({ ...form, priority: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                <p className="text-[11px] text-ink-faint mt-1">Higher wins over a more specific rule. Leave 0 unless you mean it.</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Effective from</label>
                <input type="date" value={form.effective_from} onChange={e => setForm({ ...form, effective_from: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
              </div>

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Effective to</label>
                <input type="date" value={form.effective_to} onChange={e => setForm({ ...form, effective_to: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                <p className="text-[11px] text-ink-faint mt-1">Matched on the renewal’s effective date, not today.</p>
              </div>

              <div className="col-span-2">
                <label className="block text-xs font-medium text-ink-muted mb-1">Notes</label>
                <textarea value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} rows={2}
                  placeholder="Who approved it, and why."
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
              </div>

              <div className="col-span-2">
                <label className="flex items-center gap-2 text-sm text-ink">
                  <input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} className="rounded" />
                  Active — an inactive rule never fires
                </label>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || form.pct.trim() === ''}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Read-only detail (users without the manage permission) */}
      {viewing && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setViewing(null) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{viewing.name || `Rule #${viewing.id}`}</h2>
            <p className="text-xs text-ink-muted">{viewing.summary}</p>
            <dl className="text-sm space-y-2">
              {([
                ['Product', productName(viewing.product_id)],
                ['Section', viewing.coverage_name || (viewing.coverage_id ? `#${viewing.coverage_id}` : 'Any')],
                ['Line', viewing.sub_coverage_name || (viewing.sub_coverage_id ? `#${viewing.sub_coverage_id}` : 'All lines')],
                ['Sum insured band', band(viewing)],
                ['Uplift', viewing.pct === 0 ? 'Hold (0%)' : pctLabel(viewing.pct)],
                ['Effective', windowLabel(viewing)],
                ['Transaction', viewing.transaction_type || 'Any'],
                ['Priority', String(viewing.priority)],
                ['Status', viewing.is_active ? 'Active' : 'Inactive'],
              ] as [string, string][]).map(([k, v]) => (
                <div key={k} className="flex justify-between gap-4">
                  <dt className="text-ink-muted">{k}</dt><dd className="text-ink text-right">{v}</dd>
                </div>
              ))}
              {viewing.notes && <div><dt className="text-ink-muted mb-1">Notes</dt><dd className="text-ink whitespace-pre-wrap">{viewing.notes}</dd></div>}
            </dl>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setViewing(null)} className="px-4 py-2 text-sm border border-line rounded-md">Close</button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Impact preview */}
      {impact && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setImpact(null) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-4xl p-5 space-y-3 max-h-[88vh] overflow-y-auto">
            <h2 className="text-lg font-bold">Impact — {impact.rule.name || `Rule #${impact.rule.id}`}</h2>
            <p className="text-xs text-ink-muted">{impact.rule.summary}</p>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              {[
                ['Policies', String(impact.summary.policies)],
                ['Quotes', String(impact.summary.actions)],
                ['Lines', String(impact.summary.lines)],
                ['Already applied', String(impact.summary.already_applied)],
                ['Sum insured before', money(impact.summary.si_before)],
                ['Sum insured after', money(impact.summary.si_after)],
                ['Premium before', money(impact.summary.premium_before)],
                ['Premium after', money(impact.summary.premium_after)],
              ].map(([label, value]) => (
                <div key={label} className="border border-line rounded-md p-3">
                  <div className="text-[11px] uppercase text-ink-faint">{label}</div>
                  <div className="text-base font-semibold text-ink">{value}</div>
                </div>
              ))}
            </div>

            <div className="px-3 py-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-md">
              {impact.note}
            </div>

            <div className="overflow-x-auto border border-line rounded-md">
              <table className="min-w-full divide-y divide-line text-xs">
                <thead className="bg-surface-2"><tr>
                  {['Policy', 'Effective', 'Section', 'Line', 'SI before', 'SI after', 'Prem before', 'Prem after'].map(h => (
                    <th key={h} className="px-3 py-2 text-left font-medium text-ink-muted uppercase">{h}</th>
                  ))}
                </tr></thead>
                <tbody className="divide-y divide-line">
                  {impact.sample.length === 0 && (
                    <tr><td colSpan={8} className="px-3 py-6 text-center text-ink-faint">No live quote lines match this rule yet.</td></tr>
                  )}
                  {impact.sample.map(row => (
                    <tr key={`${row.action_id}-${row.policy_number}-${row.line}-${row.si_before}`}>
                      <td className="px-3 py-2 font-medium">{row.policy_number}</td>
                      <td className="px-3 py-2 text-ink-muted">{row.effective_from}</td>
                      <td className="px-3 py-2 text-ink-muted">{row.section || '—'}</td>
                      <td className="px-3 py-2 text-ink-muted">{row.line || '—'}</td>
                      <td className="px-3 py-2 text-right">{money(row.si_before)}</td>
                      <td className="px-3 py-2 text-right font-medium">{money(row.si_after)}</td>
                      <td className="px-3 py-2 text-right">{money(row.premium_before)}</td>
                      <td className="px-3 py-2 text-right font-medium">{money(row.premium_after)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-between items-center pt-2">
              <button onClick={() => { openApplied(impact.rule.id); setImpact(null) }} className="text-sm text-blue-600 hover:underline">See what this rule already applied</button>
              <button onClick={() => setImpact(null)} className="px-4 py-2 text-sm border border-line rounded-md">Close</button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Applied log */}
      {appliedOpen && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setAppliedOpen(false) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-4xl p-5 space-y-3 max-h-[88vh] overflow-y-auto">
            <h2 className="text-lg font-bold">Applied log <span className="text-sm font-normal text-ink-muted">({appliedTotal} line(s) uplifted in total)</span></h2>
            <p className="text-xs text-ink-muted">
              One row per sum insured the cron actually moved. This is also the guard that stops a
              re-run compounding an uplift, so rows are never edited or removed.
            </p>
            <div className="overflow-x-auto border border-line rounded-md">
              <table className="min-w-full divide-y divide-line text-xs">
                <thead className="bg-surface-2"><tr>
                  {['Applied', 'Policy', 'Quote', 'Line', 'Rule', '%', 'SI before', 'SI after', 'Prem before', 'Prem after'].map(h => (
                    <th key={h} className="px-3 py-2 text-left font-medium text-ink-muted uppercase">{h}</th>
                  ))}
                </tr></thead>
                <tbody className="divide-y divide-line">
                  {applied.length === 0 && (
                    <tr><td colSpan={10} className="px-3 py-6 text-center text-ink-faint">Nothing has been uplifted yet.</td></tr>
                  )}
                  {applied.map(row => (
                    <tr key={row.id}>
                      <td className="px-3 py-2 text-ink-muted whitespace-nowrap">{row.applied_at?.slice(0, 16)}</td>
                      <td className="px-3 py-2 font-medium">{row.policy_number || '—'}</td>
                      <td className="px-3 py-2 text-ink-muted">{row.action_id}</td>
                      <td className="px-3 py-2 text-ink-muted">{row.line || '—'}</td>
                      <td className="px-3 py-2 text-ink-muted">#{row.rule_id}{row.rule_name ? ` ${row.rule_name}` : ''}</td>
                      <td className="px-3 py-2">{pctLabel(row.pct)}</td>
                      <td className="px-3 py-2 text-right">{money(row.si_before)}</td>
                      <td className="px-3 py-2 text-right font-medium">{money(row.si_after)}</td>
                      <td className="px-3 py-2 text-right">{money(row.premium_before)}</td>
                      <td className="px-3 py-2 text-right font-medium">{money(row.premium_after)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex justify-end pt-2">
              <button onClick={() => setAppliedOpen(false)} className="px-4 py-2 text-sm border border-line rounded-md">Close</button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  )
}
