import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula } from '../../utils/format'
import { reportErrorToTeam } from '../../utils/reportError'

// ── Types ────────────────────────────────────────────────────────────────────

interface Conditions {
  kyc_required?: boolean
  preinspection_required?: boolean
  min_active_months?: number | null
  min_successful_payments?: number | null
  max_failed_payments?: number | null
  cooling_period_days?: number | null
  clawback_days?: number | null
  renewal_only?: boolean
  new_business_only?: boolean
  min_premium?: number | null
  valid_from?: string | null
  valid_to?: string | null
}

interface CommissionRule {
  id: number
  product_id: number | null
  product_name: string | null
  rule_name: string
  commission_type: 'amount' | 'percentage'
  commission_value: number
  priority: number
  status: number // 1=active, 0=inactive
  conditions: Conditions
}

interface Product { id: number; name: string }

// ── Helpers ──────────────────────────────────────────────────────────────────

interface FormState {
  product_id: string
  rule_name: string
  commission_type: 'amount' | 'percentage'
  commission_value: string
  priority: string
  status: number
  kyc_required: boolean
  preinspection_required: boolean
  min_active_months: string
  min_successful_payments: string
  max_failed_payments: string
  cooling_period_days: string
  clawback_days: string
  renewal_only: boolean
  new_business_only: boolean
  min_premium: string
  valid_from: string
  valid_to: string
}

const BLANK = (): FormState => ({
  product_id: '',
  rule_name: '',
  commission_type: 'percentage',
  commission_value: '',
  priority: '1',
  status: 1,
  kyc_required: false,
  preinspection_required: false,
  min_active_months: '',
  min_successful_payments: '',
  max_failed_payments: '',
  cooling_period_days: '',
  clawback_days: '',
  renewal_only: false,
  new_business_only: false,
  min_premium: '',
  valid_from: '',
  valid_to: '',
})

/** Map backend camelCase response to our flat form model */
function apiToRule(r: any): CommissionRule {
  return {
    id: r.id,
    product_id: r.productId ?? r.product_id ?? null,
    product_name: r.productName ?? r.product_name ?? null,
    rule_name: r.ruleName ?? r.rule_name ?? '',
    commission_type: r.commissionType ?? r.commission_type ?? 'percentage',
    commission_value: Number(r.commissionValue ?? r.commission_value ?? 0),
    priority: r.priority ?? 0,
    status: typeof r.status === 'number' ? r.status : (r.status === 'active' ? 1 : 0),
    conditions: typeof r.conditions === 'string' ? JSON.parse(r.conditions || '{}') : (r.conditions ?? {}),
  }
}

/** Build the payload the backend expects (snake_case, JSON conditions) */
function formToPayload(f: FormState) {
  return {
    product_id: f.product_id ? Number(f.product_id) : null,
    rule_name: f.rule_name,
    commission_type: f.commission_type,
    commission_value: Number(f.commission_value) || 0,
    priority: Number(f.priority) || 0,
    status: f.status,
    conditions: JSON.stringify({
      kyc_required: f.kyc_required || undefined,
      preinspection_required: f.preinspection_required || undefined,
      min_active_months: f.min_active_months ? Number(f.min_active_months) : undefined,
      min_successful_payments: f.min_successful_payments ? Number(f.min_successful_payments) : undefined,
      max_failed_payments: f.max_failed_payments !== '' ? Number(f.max_failed_payments) : undefined,
      cooling_period_days: f.cooling_period_days ? Number(f.cooling_period_days) : undefined,
      clawback_days: f.clawback_days ? Number(f.clawback_days) : undefined,
      renewal_only: f.renewal_only || undefined,
      new_business_only: f.new_business_only || undefined,
      min_premium: f.min_premium ? Number(f.min_premium) : undefined,
      valid_from: f.valid_from || undefined,
      valid_to: f.valid_to || undefined,
    }),
  }
}

function ruleToForm(r: CommissionRule): FormState {
  const c = r.conditions ?? {}
  return {
    product_id: r.product_id ? String(r.product_id) : '',
    rule_name: r.rule_name,
    commission_type: r.commission_type,
    commission_value: String(r.commission_value),
    priority: String(r.priority),
    status: r.status,
    kyc_required: !!c.kyc_required,
    preinspection_required: !!c.preinspection_required,
    min_active_months: c.min_active_months != null ? String(c.min_active_months) : '',
    min_successful_payments: c.min_successful_payments != null ? String(c.min_successful_payments) : '',
    max_failed_payments: c.max_failed_payments != null ? String(c.max_failed_payments) : '',
    cooling_period_days: c.cooling_period_days != null ? String(c.cooling_period_days) : '',
    clawback_days: c.clawback_days != null ? String(c.clawback_days) : '',
    renewal_only: !!c.renewal_only,
    new_business_only: !!c.new_business_only,
    min_premium: c.min_premium != null ? String(c.min_premium) : '',
    valid_from: c.valid_from ?? '',
    valid_to: c.valid_to ?? '',
  }
}

// ── Condition badges ─────────────────────────────────────────────────────────

function ConditionBadges({ c }: { c: Conditions }) {
  const badges: { label: string; cls: string }[] = []
  if (c.kyc_required) badges.push({ label: 'KYC', cls: 'bg-blue-100 text-blue-700' })
  if (c.preinspection_required) badges.push({ label: 'Preinspection', cls: 'bg-purple-100 text-purple-700' })
  if (c.min_active_months) badges.push({ label: `${c.min_active_months}mo active`, cls: 'bg-green-100 text-green-700' })
  if (c.min_successful_payments) badges.push({ label: `${c.min_successful_payments} payments`, cls: 'bg-teal-100 text-teal-700' })
  if (c.max_failed_payments != null) badges.push({ label: `max ${c.max_failed_payments} fail`, cls: 'bg-orange-100 text-orange-700' })
  if (c.cooling_period_days) badges.push({ label: `${c.cooling_period_days}d cooling`, cls: 'bg-amber-100 text-amber-700' })
  if (c.clawback_days) badges.push({ label: `${c.clawback_days}d clawback`, cls: 'bg-red-100 text-red-700' })
  if (c.renewal_only) badges.push({ label: 'Renewal', cls: 'bg-indigo-100 text-indigo-700' })
  if (c.new_business_only) badges.push({ label: 'New Biz', cls: 'bg-cyan-100 text-cyan-700' })
  if (c.min_premium) badges.push({ label: `P${c.min_premium}+ premium`, cls: 'bg-emerald-100 text-emerald-700' })
  if (c.valid_from || c.valid_to) badges.push({ label: `Campaign ${c.valid_from ?? ''}–${c.valid_to ?? ''}`, cls: 'bg-pink-100 text-pink-700' })
  if (badges.length === 0) return <span className="text-gray-300 text-xs">No conditions</span>
  return (
    <div className="flex flex-wrap gap-1">
      {badges.map((b, i) => <span key={i} className={`px-1.5 py-0.5 rounded-full text-[10px] font-medium ${b.cls}`}>{b.label}</span>)}
    </div>
  )
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function CommissionRulesPage() {
  const [rules, setRules] = useState<CommissionRule[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<FormState>(BLANK())
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)
  const [filterProduct, setFilterProduct] = useState('')
  const [filterStatus, setFilterStatus] = useState('')

  // ── Data loading ─────────────────────────────────

  function loadRules() {
    setLoading(true)
    setError('')
    apiClient.get('/commission/rules', { params: { per_page: 100 } })
      .then(r => setRules((r.data.data ?? []).map(apiToRule)))
      .catch((err: any) => {
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load commission rules.'
        setError(message)
        reportErrorToTeam({ error: message, stack: err?.stack, context: 'CommissionRulesPage:loadRules' })
      })
      .finally(() => setLoading(false))
  }

  function loadProducts() {
    apiClient.get('/products')
      .then(r => {
        const list = r.data.data ?? r.data.products ?? r.data ?? []
        setProducts(list.map((p: any) => ({ id: p.id, name: p.name ?? p.productName ?? `Product #${p.id}` })))
      })
      .catch(() => {})
  }

  useEffect(() => { loadRules(); loadProducts() }, [])

  // ── CRUD actions ─────────────────────────────────

  function openCreate() { setEditingId(null); setForm(BLANK()); setError(''); setModalOpen(true) }
  function openEdit(rule: CommissionRule) { setEditingId(rule.id); setForm(ruleToForm(rule)); setError(''); setModalOpen(true) }
  function openCopy(rule: CommissionRule) {
    setEditingId(null)
    const f = ruleToForm(rule)
    f.rule_name = `${rule.rule_name} (Copy)`
    setForm(f)
    setError('')
    setModalOpen(true)
  }

  async function handleSave() {
    if (!form.rule_name.trim()) { setError('Rule name is required.'); return }
    if (!form.commission_value) { setError('Commission value is required.'); return }

    setSaving(true); setError('')
    try {
      const payload = formToPayload(form)
      if (editingId) {
        await apiClient.put(`/commission/rules/${editingId}`, payload)
        setSuccess('Rule updated successfully.')
      } else {
        await apiClient.post('/commission/rules', payload)
        setSuccess('Rule created successfully.')
      }
      setModalOpen(false); loadRules()
      setTimeout(() => setSuccess(''), 3000)
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.response?.data?.error || 'Failed to save rule.'
      const errs = e?.response?.data?.errors
      if (errs) {
        setError(msg + ' ' + Object.values(errs).flat().join(', '))
      } else {
        setError(msg)
      }
    } finally { setSaving(false) }
  }

  async function handleDelete(id: number) {
    try {
      await apiClient.delete(`/commission/rules/${id}`)
      setDeleteConfirmId(null); loadRules()
      setSuccess('Rule deactivated.'); setTimeout(() => setSuccess(''), 3000)
    } catch { setError('Failed to delete rule.') }
  }

  // ── Filtering ────────────────────────────────────

  const filtered = rules.filter(r => {
    if (filterProduct && String(r.product_id) !== filterProduct) return false
    if (filterStatus === '1' && r.status !== 1) return false
    if (filterStatus === '0' && r.status !== 0) return false
    return true
  })

  // ── Render ───────────────────────────────────────

  if (loading) return <div className="p-6 flex items-center justify-center min-h-[400px]"><LoadingSpinner size="lg" /></div>

  const upd = (key: string, value: any) => setForm((p: FormState) => ({ ...p, [key]: value }))

  return (
    <div className="p-4 sm:p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <h1 className="text-xl sm:text-2xl font-bold text-gray-800">Commission Rules</h1>
        <button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">+ Add Rule</button>
      </div>

      {/* Alerts */}
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md text-sm flex items-center gap-3">
          <span className="flex-1">{error}</span>
          <button onClick={loadRules} className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">Try again</button>
          <button onClick={() => setError('')} className="text-red-500 hover:text-red-700 font-bold">&times;</button>
        </div>
      )}
      {success && <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded-md text-sm">{success}</div>}

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <select value={filterProduct} onChange={e => setFilterProduct(e.target.value)} className="px-3 py-1.5 border rounded text-sm">
          <option value="">All Products</option>
          {products.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
        <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)} className="px-3 py-1.5 border rounded text-sm">
          <option value="">All Statuses</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
        <span className="text-xs text-gray-400 self-center">{filtered.length} rule(s)</span>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-3 py-2.5 text-left">Product</th>
                <th className="px-3 py-2.5 text-left">Rule</th>
                <th className="px-3 py-2.5 text-left">Type</th>
                <th className="px-3 py-2.5 text-right">Value</th>
                <th className="px-3 py-2.5 text-left hidden lg:table-cell">Conditions</th>
                <th className="px-3 py-2.5 text-center">Priority</th>
                <th className="px-3 py-2.5 text-center">Status</th>
                <th className="px-3 py-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {filtered.length === 0 ? (
                <tr><td colSpan={8} className="px-4 py-14 text-center text-gray-400 text-sm">
                  No rules.{' '}
                  <button onClick={openCreate} className="text-blue-600 hover:underline font-medium">Add your first rule</button>
                </td></tr>
              ) : filtered.map(rule => (
                <tr key={rule.id} className="hover:bg-gray-50">
                  <td className="px-3 py-2 text-gray-700 max-w-[120px] truncate">{rule.product_name || 'All Products'}</td>
                  <td className="px-3 py-2 font-medium text-gray-800">{rule.rule_name}</td>
                  <td className="px-3 py-2">
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${rule.commission_type === 'percentage' ? 'bg-indigo-100 text-indigo-700' : 'bg-teal-100 text-teal-700'}`}>
                      {rule.commission_type === 'percentage' ? '%' : 'P'}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-right font-mono font-medium">
                    {rule.commission_type === 'percentage' ? `${rule.commission_value}%` : fmtPula(rule.commission_value)}
                  </td>
                  <td className="px-3 py-2 hidden lg:table-cell"><ConditionBadges c={rule.conditions} /></td>
                  <td className="px-3 py-2 text-center text-gray-600">{rule.priority}</td>
                  <td className="px-3 py-2 text-center">
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${rule.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {rule.status === 1 ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex items-center justify-end gap-1">
                      <button onClick={() => openEdit(rule)} className="px-2 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100">Edit</button>
                      <button onClick={() => openCopy(rule)} className="px-2 py-1 text-xs bg-amber-50 text-amber-600 rounded hover:bg-amber-100" title="Duplicate this rule">Copy</button>
                      <button onClick={() => setDeleteConfirmId(rule.id)} className="px-2 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-red-100">Del</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* ── Delete Confirm ──────────────────────────── */}
      {deleteConfirmId !== null && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setDeleteConfirmId(null) }}>
          <div className="bg-white rounded-lg shadow-xl p-5 w-full max-w-sm max-h-[85vh] overflow-y-auto">
            <h3 className="text-lg font-semibold text-gray-800 mb-2">Deactivate Rule</h3>
            <p className="text-sm text-gray-600 mb-4">This will set the rule to Inactive. It can be re-activated later.</p>
            <div className="flex justify-end gap-2">
              <button onClick={() => setDeleteConfirmId(null)} className="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">Cancel</button>
              <button onClick={() => handleDelete(deleteConfirmId)} className="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">Deactivate</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Create / Edit Modal — fully responsive ─── */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-2 sm:p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl my-4">
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-4 border-b">
              <h3 className="text-lg font-semibold text-gray-800">{editingId ? 'Edit Commission Rule' : 'New Commission Rule'}</h3>
              <button onClick={() => setModalOpen(false)} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>

            {/* Body */}
            <div className="px-5 py-4 space-y-5 max-h-[70vh] overflow-y-auto">
              {/* Core fields */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                  <label className="block text-xs font-medium text-gray-500 mb-1">Rule Name *</label>
                  <input value={form.rule_name} onChange={e => upd('rule_name', e.target.value)}
                    className="w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500"
                    placeholder="e.g. Motor Comp — Standard Agent Commission" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Product</label>
                  <select value={form.product_id} onChange={e => upd('product_id', e.target.value)}
                    className="w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500 bg-white">
                    <option value="">All Products</option>
                    {products.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Type</label>
                    <select value={form.commission_type} onChange={e => upd('commission_type', e.target.value)}
                      className="w-full px-3 py-2 border rounded-md text-sm bg-white">
                      <option value="percentage">Percentage (%)</option>
                      <option value="amount">Fixed Amount (P)</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Value *</label>
                    <input type="number" value={form.commission_value} onChange={e => upd('commission_value', e.target.value)}
                      className="w-full px-3 py-2 border rounded-md text-sm"
                      placeholder={form.commission_type === 'percentage' ? '10' : '500'} step="0.01" min="0" />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Priority</label>
                  <input type="number" value={form.priority} onChange={e => upd('priority', e.target.value)}
                    className="w-full px-3 py-2 border rounded-md text-sm" min="0" />
                  <span className="text-[10px] text-gray-400">Higher priority rules are evaluated first</span>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                  <select value={form.status} onChange={e => upd('status', Number(e.target.value))}
                    className="w-full px-3 py-2 border rounded-md text-sm bg-white">
                    <option value={1}>Active</option>
                    <option value={0}>Inactive</option>
                  </select>
                </div>
              </div>

              {/* ── Qualification Conditions ────────────── */}
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-gray-50 px-4 py-2 border-b">
                  <h4 className="text-sm font-semibold text-gray-700">Qualification Conditions</h4>
                  <p className="text-[10px] text-gray-400">Commission is earned only when ALL checked conditions are met</p>
                </div>
                <div className="px-4 py-3 space-y-4">
                  {/* Checkboxes */}
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                      { key: 'kyc_required', label: 'KYC Compliant' },
                      { key: 'preinspection_required', label: 'Preinspection Done' },
                      { key: 'renewal_only', label: 'Renewals Only' },
                      { key: 'new_business_only', label: 'New Business Only' },
                    ].map(({ key, label }) => (
                      <label key={key} className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={!!(form as any)[key]}
                          onChange={e => upd(key, e.target.checked)}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        {label}
                      </label>
                    ))}
                  </div>

                  {/* Payment & retention */}
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Min Active Months</label>
                      <input type="number" value={form.min_active_months} onChange={e => upd('min_active_months', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" placeholder="e.g. 2" min="0" />
                    </div>
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Min Successful Payments</label>
                      <input type="number" value={form.min_successful_payments} onChange={e => upd('min_successful_payments', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" placeholder="e.g. 3" min="0" />
                    </div>
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Max Failed Payments</label>
                      <input type="number" value={form.max_failed_payments} onChange={e => upd('max_failed_payments', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" placeholder="0 = no failures allowed" min="0" />
                    </div>
                  </div>

                  {/* Clawback & cooling */}
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Cooling Period (days)</label>
                      <input type="number" value={form.cooling_period_days} onChange={e => upd('cooling_period_days', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" placeholder="e.g. 30" min="0" />
                      <span className="text-[9px] text-gray-400">Hold before paying out</span>
                    </div>
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Clawback Period (days)</label>
                      <input type="number" value={form.clawback_days} onChange={e => upd('clawback_days', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" placeholder="e.g. 90" min="0" />
                      <span className="text-[9px] text-gray-400">Reverse if policy cancels within</span>
                    </div>
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Min Premium (P)</label>
                      <input type="number" value={form.min_premium} onChange={e => upd('min_premium', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" placeholder="e.g. 500" min="0" />
                    </div>
                  </div>

                  {/* Campaign dates */}
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Valid From (campaign)</label>
                      <input type="date" value={form.valid_from} onChange={e => upd('valid_from', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" />
                    </div>
                    <div>
                      <label className="block text-[10px] text-gray-500 mb-0.5">Valid To</label>
                      <input type="date" value={form.valid_to} onChange={e => upd('valid_to', e.target.value)}
                        className="w-full px-2 py-1.5 border rounded text-sm" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="flex justify-end gap-2 px-5 py-3 border-t bg-gray-50 rounded-b-xl">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md hover:bg-gray-100">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.rule_name || !form.commission_value}
                className="px-5 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 font-medium">
                {saving ? 'Saving...' : editingId ? 'Update Rule' : 'Create Rule'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
