import { useState, useEffect } from 'react'
import apiClient from '../api/client'

interface CommissionRule {
  id: number
  product_name: string
  product_id: number | null
  rule_name: string
  commission_type: 'amount' | 'percentage'
  value: number
  priority: number
  status: 'active' | 'inactive'
  conditions: {
    kyc_required?: boolean
    preinspection_required?: boolean
    min_payments?: number
    min_active_days?: number
  }
}

interface Product {
  id: number
  name: string
}

const EMPTY_FORM = {
  product_id: '' as string,
  rule_name: '',
  commission_type: 'percentage' as 'amount' | 'percentage',
  value: '',
  priority: '1',
  status: 'active' as 'active' | 'inactive',
  kyc_required: false,
  preinspection_required: false,
  min_payments: '',
  min_active_days: '',
}

const CONDITION_BADGES: Record<string, { label: string; color: string }> = {
  kyc_required: { label: 'KYC Required', color: 'bg-blue-100 text-blue-700' },
  preinspection_required: { label: 'Preinspection', color: 'bg-purple-100 text-purple-700' },
  min_payments: { label: 'Min Payments', color: 'bg-green-100 text-green-700' },
  min_active_days: { label: 'Min Active Days', color: 'bg-orange-100 text-orange-700' },
}

export default function Rules() {
  const [rules, setRules] = useState<CommissionRule[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)

  function loadRules() {
    setLoading(true)
    apiClient
      .get('/commission/rules')
      .then((r) => {
        setRules(r.data.rules ?? r.data.data ?? r.data)
        if (r.data.products) setProducts(r.data.products)
      })
      .catch(() => setError('Failed to load commission rules.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadRules()
  }, [])

  function openCreate() {
    setEditingId(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(rule: CommissionRule) {
    setEditingId(rule.id)
    setForm({
      product_id: rule.product_id ? String(rule.product_id) : '',
      rule_name: rule.rule_name,
      commission_type: rule.commission_type,
      value: String(rule.value),
      priority: String(rule.priority),
      status: rule.status,
      kyc_required: rule.conditions?.kyc_required ?? false,
      preinspection_required: rule.conditions?.preinspection_required ?? false,
      min_payments: rule.conditions?.min_payments ? String(rule.conditions.min_payments) : '',
      min_active_days: rule.conditions?.min_active_days ? String(rule.conditions.min_active_days) : '',
    })
    setModalOpen(true)
  }

  async function handleSave() {
    setSaving(true)
    const payload = {
      product_id: form.product_id ? Number(form.product_id) : null,
      rule_name: form.rule_name,
      commission_type: form.commission_type,
      value: Number(form.value),
      priority: Number(form.priority),
      status: form.status,
      conditions: {
        kyc_required: form.kyc_required,
        preinspection_required: form.preinspection_required,
        min_payments: form.min_payments ? Number(form.min_payments) : null,
        min_active_days: form.min_active_days ? Number(form.min_active_days) : null,
      },
    }
    try {
      if (editingId) {
        await apiClient.put(`/commission/rules/${editingId}`, payload)
      } else {
        await apiClient.post('/commission/rules', payload)
      }
      setModalOpen(false)
      loadRules()
    } catch {
      setError('Failed to save rule.')
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(id: number) {
    try {
      await apiClient.delete(`/commission/rules/${id}`)
      setDeleteConfirmId(null)
      loadRules()
    } catch {
      setError('Failed to delete rule.')
    }
  }

  function renderConditionBadges(conditions: CommissionRule['conditions']) {
    const badges: { label: string; color: string }[] = []
    if (conditions?.kyc_required) badges.push(CONDITION_BADGES.kyc_required)
    if (conditions?.preinspection_required) badges.push(CONDITION_BADGES.preinspection_required)
    if (conditions?.min_payments) badges.push({ ...CONDITION_BADGES.min_payments, label: `Min ${conditions.min_payments} Payments` })
    if (conditions?.min_active_days) badges.push({ ...CONDITION_BADGES.min_active_days, label: `Min ${conditions.min_active_days} Days` })
    if (badges.length === 0) return <span className="text-gray-300">--</span>
    return (
      <div className="flex flex-wrap gap-1">
        {badges.map((b, i) => (
          <span key={i} className={`px-2 py-0.5 rounded-full text-xs font-medium ${b.color}`}>
            {b.label}
          </span>
        ))}
      </div>
    )
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
        <h1 className="text-2xl font-bold text-gray-800">Commission Rules</h1>
        <button
          onClick={openCreate}
          className="px-4 py-2 bg-ace-primary text-white rounded-md text-sm font-medium hover:bg-ace-dark transition"
        >
          + Add Rule
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
            <h3 className="text-lg font-semibold text-gray-800 mb-2">Delete Rule</h3>
            <p className="text-sm text-gray-600 mb-4">Are you sure you want to delete this commission rule? This action cannot be undone.</p>
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
                {editingId ? 'Edit Commission Rule' : 'Add Commission Rule'}
              </h3>
              <button onClick={() => setModalOpen(false)} className="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div className="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Product</label>
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

              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Rule Name</label>
                <input
                  type="text"
                  value={form.rule_name}
                  onChange={(e) => setForm({ ...form, rule_name: e.target.value })}
                  className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  placeholder="e.g. Standard Motor Commission"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Commission Type</label>
                  <select
                    value={form.commission_type}
                    onChange={(e) => setForm({ ...form, commission_type: e.target.value as 'amount' | 'percentage' })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                  >
                    <option value="percentage">Percentage (%)</option>
                    <option value="amount">Fixed Amount (P)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Value</label>
                  <input
                    type="number"
                    value={form.value}
                    onChange={(e) => setForm({ ...form, value: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                    placeholder={form.commission_type === 'percentage' ? 'e.g. 10' : 'e.g. 500'}
                    step="0.01"
                    min="0"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Priority</label>
                  <input
                    type="number"
                    value={form.priority}
                    onChange={(e) => setForm({ ...form, priority: e.target.value })}
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                    min="1"
                  />
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

              {/* Conditions */}
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-2">Conditions</label>
                <div className="space-y-3 bg-gray-50 rounded-md p-3 border border-gray-200">
                  <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                      type="checkbox"
                      checked={form.kyc_required}
                      onChange={(e) => setForm({ ...form, kyc_required: e.target.checked })}
                      className="rounded border-gray-300 text-ace-primary focus:ring-ace-primary"
                    />
                    KYC Required
                  </label>
                  <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                      type="checkbox"
                      checked={form.preinspection_required}
                      onChange={(e) => setForm({ ...form, preinspection_required: e.target.checked })}
                      className="rounded border-gray-300 text-ace-primary focus:ring-ace-primary"
                    />
                    Preinspection Required
                  </label>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs text-gray-500 mb-1">Min Payments</label>
                      <input
                        type="number"
                        value={form.min_payments}
                        onChange={(e) => setForm({ ...form, min_payments: e.target.value })}
                        className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                        placeholder="e.g. 3"
                        min="0"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-gray-500 mb-1">Min Active Days</label>
                      <input
                        type="number"
                        value={form.min_active_days}
                        onChange={(e) => setForm({ ...form, min_active_days: e.target.value })}
                        className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-ace-primary focus:border-ace-primary"
                        placeholder="e.g. 30"
                        min="0"
                      />
                    </div>
                  </div>
                </div>
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
                disabled={saving || !form.rule_name || !form.value}
                className="px-4 py-2 text-sm bg-ace-primary text-white rounded-md hover:bg-ace-dark disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                {saving ? 'Saving...' : editingId ? 'Update Rule' : 'Create Rule'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Rules Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Product</th>
                <th className="px-4 py-3 text-left">Rule Name</th>
                <th className="px-4 py-3 text-left">Type</th>
                <th className="px-4 py-3 text-right">Value</th>
                <th className="px-4 py-3 text-left">Conditions</th>
                <th className="px-4 py-3 text-center">Priority</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {rules.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-14 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                      </div>
                      <p className="text-sm text-gray-400">No commission rules configured yet.</p>
                      <button
                        onClick={openCreate}
                        className="text-sm text-ace-primary hover:underline font-medium"
                      >
                        Add your first rule
                      </button>
                    </div>
                  </td>
                </tr>
              ) : (
                rules.map((rule) => (
                  <tr key={rule.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 text-gray-700">{rule.product_name || 'All'}</td>
                    <td className="px-4 py-2 font-medium text-gray-800">{rule.rule_name}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                        rule.commission_type === 'percentage'
                          ? 'bg-indigo-100 text-indigo-700'
                          : 'bg-teal-100 text-teal-700'
                      }`}>
                        {rule.commission_type === 'percentage' ? 'Percentage' : 'Amount'}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right font-mono font-medium text-gray-800">
                      {rule.commission_type === 'percentage' ? `${rule.value}%` : `P ${rule.value.toLocaleString('en-BW', { minimumFractionDigits: 2 })}`}
                    </td>
                    <td className="px-4 py-2">{renderConditionBadges(rule.conditions)}</td>
                    <td className="px-4 py-2 text-center text-gray-600">{rule.priority}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                        rule.status === 'active'
                          ? 'bg-green-100 text-green-700'
                          : 'bg-gray-100 text-gray-500'
                      }`}>
                        {rule.status === 'active' ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => openEdit(rule)}
                          className="px-2 py-1 text-xs bg-ace-primary/10 text-ace-primary rounded hover:bg-ace-primary/20"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => setDeleteConfirmId(rule.id)}
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
