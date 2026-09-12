import { useState, useEffect } from 'react'
import apiClient from '../api/client'

interface CommissionTarget {
  id: number
  name: string
  target_type: 'policy_count' | 'premium_amount'
  target_value: number
  period_type: string
  period_days: number | null
  bonus_type: 'amount' | 'percentage'
  bonus_value: number
  product_name: string | null
  product_id: number | null
  agent_name: string | null
  agent_id: number | null
  agency_name: string | null
  agency_id: number | null
  effective_from: string
  effective_to: string | null
  status: 'active' | 'inactive'
}

interface Product {
  id: number
  name: string
}

interface Agency {
  id: number
  name: string
}

const EMPTY_FORM = {
  name: '',
  target_type: 'policy_count' as 'policy_count' | 'premium_amount',
  target_value: '',
  period_type: 'monthly',
  period_days: '',
  bonus_type: 'amount' as 'amount' | 'percentage',
  bonus_value: '',
  product_id: '',
  agent_search: '',
  agent_id: '' as string,
  agent_name: '',
  agency_id: '',
  effective_from: '',
  effective_to: '',
  status: 'active' as 'active' | 'inactive',
}

function fmtDate(s: string | null): string {
  if (!s) return '--'
  return new Date(s).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

export default function Targets() {
  const [targets, setTargets] = useState<CommissionTarget[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [agencies, setAgencies] = useState<Agency[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)
  const [agentResults, setAgentResults] = useState<{ id: number; name: string }[]>([])
  const [showAgentDropdown, setShowAgentDropdown] = useState(false)

  function loadTargets() {
    setLoading(true)
    apiClient
      .get('/commission/targets')
      .then((r) => {
        setTargets(r.data.targets ?? r.data.data ?? r.data)
        if (r.data.products) setProducts(r.data.products)
        if (r.data.agencies) setAgencies(r.data.agencies)
      })
      .catch(() => setError('Failed to load commission targets.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadTargets()
  }, [])

  // Debounced agent search
  useEffect(() => {
    if (!form.agent_search || form.agent_search.length < 2) {
      setAgentResults([])
      return
    }
    const timer = setTimeout(() => {
      apiClient
        .get('/commission/targets/search-agents', { params: { q: form.agent_search } })
        .then((r) => {
          setAgentResults(r.data.agents ?? r.data.data ?? r.data)
          setShowAgentDropdown(true)
        })
        .catch(() => setAgentResults([]))
    }, 300)
    return () => clearTimeout(timer)
  }, [form.agent_search])

  function openCreate() {
    setEditingId(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(target: CommissionTarget) {
    setEditingId(target.id)
    setForm({
      name: target.name,
      target_type: target.target_type,
      target_value: String(target.target_value),
      period_type: target.period_type,
      period_days: target.period_days ? String(target.period_days) : '',
      bonus_type: target.bonus_type,
      bonus_value: String(target.bonus_value),
      product_id: target.product_id ? String(target.product_id) : '',
      agent_search: target.agent_name ?? '',
      agent_id: target.agent_id ? String(target.agent_id) : '',
      agent_name: target.agent_name ?? '',
      agency_id: target.agency_id ? String(target.agency_id) : '',
      effective_from: target.effective_from ? target.effective_from.slice(0, 10) : '',
      effective_to: target.effective_to ? target.effective_to.slice(0, 10) : '',
      status: target.status,
    })
    setModalOpen(true)
  }

  async function handleSave() {
    setSaving(true)
    const payload = {
      name: form.name,
      target_type: form.target_type,
      target_value: Number(form.target_value),
      period_type: form.period_type,
      period_days: form.period_days ? Number(form.period_days) : null,
      bonus_type: form.bonus_type,
      bonus_value: Number(form.bonus_value),
      product_id: form.product_id ? Number(form.product_id) : null,
      agent_id: form.agent_id ? Number(form.agent_id) : null,
      agency_id: form.agency_id ? Number(form.agency_id) : null,
      effective_from: form.effective_from,
      effective_to: form.effective_to || null,
      status: form.status,
    }
    try {
      if (editingId) {
        await apiClient.put(`/commission/targets/${editingId}`, payload)
      } else {
        await apiClient.post('/commission/targets', payload)
      }
      setModalOpen(false)
      loadTargets()
    } catch {
      setError('Failed to save target.')
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(id: number) {
    try {
      await apiClient.delete(`/commission/targets/${id}`)
      setDeleteConfirmId(null)
      loadTargets()
    } catch {
      setError('Failed to delete target.')
    }
  }

  if (loading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[400px]">
        <div className="animate-spin w-8 h-8 border-4 border-ace-primary border-t-transparent rounded-full" />
      </div>
    )
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Commission Targets</h1>
        <button
          onClick={openCreate}
          className="px-4 py-2 bg-ace-primary text-white rounded-md text-sm font-medium hover:bg-ace-dark transition"
        >
          + Add Target
        </button>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md text-sm">
          {error}
          <button onClick={() => setError('')} className="ml-2 text-red-500 hover:text-red-700">&times;</button>
        </div>
      )}

      {/* Delete Confirmation */}
      {deleteConfirmId !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
            <h3 className="text-lg font-semibold text-gray-800 mb-2">Delete Target</h3>
            <p className="text-sm text-gray-600 mb-4">Are you sure you want to delete this commission target? This action cannot be undone.</p>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setDeleteConfirmId(null)}
                className="px-4 py-2 text-sm border rounded-md text-gray-600 hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={() => handleDelete(deleteConfirmId)}
                className="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Create / Edit Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
            <div className="flex items-center justify-between px-6 py-4 border-b">
              <h3 className="text-lg font-semibold text-gray-800">
                {editingId ? 'Edit Commission Target' : 'Add Commission Target'}
              </h3>
              <button onClick={() => setModalOpen(false)} className="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div className="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Name</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  placeholder="e.g. Q1 Motor Sales Bonus"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Target Type</label>
                  <select
                    value={form.target_type}
                    onChange={(e) => setForm({ ...form, target_type: e.target.value as 'policy_count' | 'premium_amount' })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  >
                    <option value="policy_count">Policy Count</option>
                    <option value="premium_amount">Premium Amount</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Target Value</label>
                  <input
                    type="number"
                    value={form.target_value}
                    onChange={(e) => setForm({ ...form, target_value: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                    placeholder={form.target_type === 'policy_count' ? 'e.g. 50' : 'e.g. 100000'}
                    min="0"
                    step="0.01"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Period Type</label>
                  <select
                    value={form.period_type}
                    onChange={(e) => setForm({ ...form, period_type: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  >
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                    <option value="custom">Custom</option>
                  </select>
                </div>
                {form.period_type === 'custom' && (
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Period Days</label>
                    <input
                      type="number"
                      value={form.period_days}
                      onChange={(e) => setForm({ ...form, period_days: e.target.value })}
                      className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                      placeholder="e.g. 45"
                      min="1"
                    />
                  </div>
                )}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Bonus Type</label>
                  <select
                    value={form.bonus_type}
                    onChange={(e) => setForm({ ...form, bonus_type: e.target.value as 'amount' | 'percentage' })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  >
                    <option value="amount">Fixed Amount (P)</option>
                    <option value="percentage">Percentage (%)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Bonus Value</label>
                  <input
                    type="number"
                    value={form.bonus_value}
                    onChange={(e) => setForm({ ...form, bonus_value: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                    placeholder={form.bonus_type === 'percentage' ? 'e.g. 5' : 'e.g. 2000'}
                    step="0.01"
                    min="0"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Product (optional)</label>
                <select
                  value={form.product_id}
                  onChange={(e) => setForm({ ...form, product_id: e.target.value })}
                  className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                >
                  <option value="">All Products</option>
                  {products.map((p) => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </div>

              <div className="relative">
                <label className="block text-xs font-medium text-gray-500 mb-1">Agent (optional)</label>
                <input
                  type="text"
                  value={form.agent_search}
                  onChange={(e) => {
                    setForm({ ...form, agent_search: e.target.value, agent_id: '', agent_name: '' })
                    if (e.target.value.length < 2) setShowAgentDropdown(false)
                  }}
                  onFocus={() => { if (agentResults.length > 0) setShowAgentDropdown(true) }}
                  onBlur={() => setTimeout(() => setShowAgentDropdown(false), 200)}
                  className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  placeholder="Search agent by name..."
                />
                {showAgentDropdown && agentResults.length > 0 && (
                  <div className="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                    {agentResults.map((a) => (
                      <button
                        key={a.id}
                        type="button"
                        onMouseDown={() => {
                          setForm({ ...form, agent_id: String(a.id), agent_search: a.name, agent_name: a.name })
                          setShowAgentDropdown(false)
                        }}
                        className="w-full text-left px-3 py-2 text-sm hover:bg-purple-50 text-gray-700"
                      >
                        {a.name}
                      </button>
                    ))}
                  </div>
                )}
                {form.agent_id && (
                  <p className="text-xs text-green-600 mt-1">Selected: {form.agent_name} (ID: {form.agent_id})</p>
                )}
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Agency (optional)</label>
                <select
                  value={form.agency_id}
                  onChange={(e) => setForm({ ...form, agency_id: e.target.value })}
                  className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                >
                  <option value="">All Agencies</option>
                  {agencies.map((a) => (
                    <option key={a.id} value={a.id}>{a.name}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Effective From</label>
                  <input
                    type="date"
                    value={form.effective_from}
                    onChange={(e) => setForm({ ...form, effective_from: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Effective To</label>
                  <input
                    type="date"
                    value={form.effective_to}
                    onChange={(e) => setForm({ ...form, effective_to: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select
                  value={form.status}
                  onChange={(e) => setForm({ ...form, status: e.target.value as 'active' | 'inactive' })}
                  className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 rounded-b-lg">
              <button
                onClick={() => setModalOpen(false)}
                className="px-4 py-2 text-sm border rounded-md text-gray-600 hover:bg-gray-100"
              >
                Cancel
              </button>
              <button
                onClick={handleSave}
                disabled={saving || !form.name || !form.target_value || !form.bonus_value}
                className="px-4 py-2 text-sm bg-ace-primary text-white rounded-md hover:bg-ace-dark disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                {saving ? 'Saving...' : editingId ? 'Update Target' : 'Create Target'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Targets Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Name</th>
                <th className="px-4 py-3 text-left">Type</th>
                <th className="px-4 py-3 text-right">Target</th>
                <th className="px-4 py-3 text-left">Period</th>
                <th className="px-4 py-3 text-right">Bonus</th>
                <th className="px-4 py-3 text-left">Product</th>
                <th className="px-4 py-3 text-left">Agent</th>
                <th className="px-4 py-3 text-left">Dates</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {targets.length === 0 ? (
                <tr>
                  <td colSpan={10} className="px-4 py-14 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                      </div>
                      <p className="text-sm text-gray-400">No commission targets configured yet.</p>
                      <button
                        onClick={openCreate}
                        className="text-sm text-ace-primary hover:underline font-medium"
                      >
                        Add your first target
                      </button>
                    </div>
                  </td>
                </tr>
              ) : (
                targets.map((target) => (
                  <tr key={target.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-800">{target.name}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                        target.target_type === 'policy_count'
                          ? 'bg-blue-100 text-blue-700'
                          : 'bg-purple-100 text-purple-700'
                      }`}>
                        {target.target_type === 'policy_count' ? 'Policy Count' : 'Premium Amount'}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right font-mono text-gray-800">
                      {target.target_type === 'premium_amount'
                        ? `P ${target.target_value.toLocaleString('en-BW', { minimumFractionDigits: 2 })}`
                        : target.target_value.toLocaleString()
                      }
                    </td>
                    <td className="px-4 py-2 text-gray-600 capitalize">
                      {target.period_type}
                      {target.period_days ? ` (${target.period_days}d)` : ''}
                    </td>
                    <td className="px-4 py-2 text-right font-mono text-gray-800">
                      {target.bonus_type === 'percentage'
                        ? `${target.bonus_value}%`
                        : `P ${target.bonus_value.toLocaleString('en-BW', { minimumFractionDigits: 2 })}`
                      }
                    </td>
                    <td className="px-4 py-2 text-gray-600">{target.product_name || 'All'}</td>
                    <td className="px-4 py-2 text-gray-600">{target.agent_name || 'All'}</td>
                    <td className="px-4 py-2 text-xs text-gray-500">
                      {fmtDate(target.effective_from)}
                      {target.effective_to ? ` - ${fmtDate(target.effective_to)}` : ' - ongoing'}
                    </td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                        target.status === 'active'
                          ? 'bg-green-100 text-green-700'
                          : 'bg-gray-100 text-gray-500'
                      }`}>
                        {target.status === 'active' ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => openEdit(target)}
                          className="px-2 py-1 text-xs bg-ace-primary/10 text-ace-primary rounded hover:bg-ace-primary/20"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => setDeleteConfirmId(target.id)}
                          className="px-2 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-red-100"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
