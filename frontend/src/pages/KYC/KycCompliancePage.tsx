import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import { reportErrorToTeam } from '../../utils/reportError'

interface KycFieldDef {
  id: number
  name: string
  slug: string
  customer_kyc_column: string
}

interface FieldRule {
  field: string
  check: number   // 1=Mandatory, 2=Mandatory with Other, 3=Optional
  other: string
}

interface ComplianceRule {
  id: number
  name: string
  fields: FieldRule[]
  flow_id: string | null
  products: { id: number; name: string }[]
  createdAt: string
}

interface ProductOption {
  id: number
  name: string
  kyc_compliance: number | null
}

const CHECK_LABELS: Record<number, string> = { 1: 'Mandatory', 2: 'Mandatory + Alt', 3: 'Optional' }
const CHECK_COLORS: Record<number, string> = {
  1: 'bg-red-100 text-red-700',
  2: 'bg-yellow-100 text-yellow-700',
  3: 'bg-gray-100 text-gray-500',
}

export default function KycCompliancePage() {
  const [rules, setRules] = useState<ComplianceRule[]>([])
  const [kycFields, setKycFields] = useState<KycFieldDef[]>([])
  const [allProducts, setAllProducts] = useState<ProductOption[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)

  // Form state
  const [formName, setFormName] = useState('')
  const [formFlowId, setFormFlowId] = useState('')
  const [formFieldRules, setFormFieldRules] = useState<FieldRule[]>([])
  const [formProductIds, setFormProductIds] = useState<number[]>([])

  // V8-parity global Mati toggle. Stored in the `config` table keyed
  // by 'enable_mati'. Mirrors the V8 admin/KycCompliance page header.
  const [matiEnabled, setMatiEnabled] = useState<boolean>(true)
  const [matiSaving, setMatiSaving] = useState(false)

  function loadData() {
    setLoading(true)
    setError('')
    Promise.all([
      apiClient.get('/kyc-compliance'),
      apiClient.get('/kyc-compliance/products'),
      apiClient.get('/kyc-compliance/mati'),
    ])
      .then(([rulesRes, productsRes, matiRes]) => {
        setRules(rulesRes.data.data ?? [])
        setKycFields(rulesRes.data.kycFields ?? [])
        setAllProducts(productsRes.data.data ?? [])
        setMatiEnabled(Boolean(matiRes.data.enabled))
      })
      .catch((err: any) => {
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load KYC compliance rules.'
        setError(message)
        reportErrorToTeam({ error: message, stack: err?.stack, context: 'KycCompliancePage:loadData' })
      })
      .finally(() => setLoading(false))
  }

  async function toggleMati(next: boolean) {
    setMatiSaving(true)
    const previous = matiEnabled
    setMatiEnabled(next) // optimistic
    try {
      await apiClient.post('/kyc-compliance/mati', { enabled: next })
    } catch {
      setMatiEnabled(previous)
      setError('Failed to update Mati setting.')
    } finally {
      setMatiSaving(false)
    }
  }

  useEffect(() => { loadData() }, [])

  function openCreate() {
    setEditingId(null)
    setFormName('')
    setFormFlowId('')
    setFormProductIds([])
    // Initialize all fields as Optional (check=3)
    setFormFieldRules(kycFields.map((f) => ({ field: f.name, check: 3, other: '' })))
    setModalOpen(true)
  }

  function openEdit(rule: ComplianceRule) {
    setEditingId(rule.id)
    setFormName(rule.name)
    setFormFlowId(rule.flow_id ?? '')
    setFormProductIds(rule.products.map((p) => p.id))
    // Merge existing rule fields with all available fields
    const existingMap = new Map(rule.fields.map((f) => [f.field, f]))
    setFormFieldRules(
      kycFields.map((f) => existingMap.get(f.name) ?? { field: f.name, check: 3, other: '' })
    )
    setModalOpen(true)
  }

  function updateFieldRule(index: number, key: keyof FieldRule, value: any) {
    setFormFieldRules((prev) => prev.map((r, i) => (i === index ? { ...r, [key]: value } : r)))
  }

  function toggleProduct(id: number) {
    setFormProductIds((prev) => (prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]))
  }

  async function handleSave() {
    if (!formName.trim()) return alert('Rule name is required.')
    setSaving(true)
    const payload = {
      name: formName.trim(),
      flow_id: formFlowId.trim() || null,
      fields: formFieldRules,
      product_ids: formProductIds,
    }
    try {
      if (editingId) {
        await apiClient.put(`/kyc-compliance/${editingId}`, payload)
      } else {
        await apiClient.post('/kyc-compliance', payload)
      }
      setModalOpen(false)
      loadData()
    } catch (e: any) {
      alert(e.response?.data?.message || 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(id: number) {
    try {
      await apiClient.delete(`/kyc-compliance/${id}`)
      setDeleteConfirmId(null)
      loadData()
    } catch {
      alert('Delete failed.')
    }
  }

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-3">
          {[1, 2, 3].map((i) => <div key={i} className="h-16 bg-gray-200 rounded" />)}
        </div>
      </div>
    )
  }

  if (error) return (
    <div className="p-6">
      <div className="bg-red-50 border border-red-200 rounded-lg p-6 space-y-3">
        <p className="text-sm font-medium text-red-700">Failed to load KYC compliance rules.</p>
        <p className="text-xs text-red-600">{error}</p>
        <button onClick={loadData} className="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Try again</button>
      </div>
    </div>
  )

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <h1 className="text-2xl font-bold text-gray-800">KYC Compliance Rules</h1>
        <div className="flex items-center gap-4">
          {/* V8-parity global Mati toggle. Stored in config.enable_mati;
              read by downstream Mati-integration code paths to decide
              whether to surface Mati upload links in compliance emails. */}
          <div className="flex items-center gap-2 text-sm">
            <span className="text-gray-600 font-medium">Enable Mati</span>
            <button
              type="button"
              role="switch"
              aria-checked={matiEnabled}
              disabled={matiSaving}
              onClick={() => toggleMati(!matiEnabled)}
              className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                matiEnabled ? 'bg-blue-600' : 'bg-gray-300'
              } ${matiSaving ? 'opacity-50' : ''}`}
              title={matiEnabled ? 'Click to disable' : 'Click to enable'}
            >
              <span
                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                  matiEnabled ? 'translate-x-6' : 'translate-x-1'
                }`}
              />
            </button>
            <span className={`text-xs font-medium ${matiEnabled ? 'text-green-700' : 'text-gray-500'}`}>
              {matiEnabled ? 'Yes' : 'No'}
            </span>
          </div>
          <button
            onClick={openCreate}
            className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
          >
            + Add Rule
          </button>
        </div>
      </div>

      <p className="text-sm text-gray-500">
        Each rule defines which KYC documents are required for assigned products.
        The KYC cron uses these rules to determine compliance per product.
        Scope: <span className="font-medium">MIS products only</span> (1-6, 9, 10).
      </p>

      {/* Rules list */}
      <div className="space-y-4">
        {rules.length === 0 && (
          <div className="bg-white rounded-lg p-8 text-center text-gray-400 shadow">No compliance rules defined.</div>
        )}
        {rules.map((rule) => (
          <div key={rule.id} className="bg-white shadow rounded-lg p-4 space-y-3">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="text-lg font-semibold text-gray-800">{rule.name}</h3>
                {rule.flow_id && <p className="text-xs text-gray-400">Flow ID: {rule.flow_id}</p>}
              </div>
              <div className="flex gap-2">
                <button onClick={() => openEdit(rule)} className="text-sm text-blue-600 hover:underline">Edit</button>
                <button onClick={() => setDeleteConfirmId(rule.id)} className="text-sm text-red-600 hover:underline">Delete</button>
              </div>
            </div>

            {/* Products assigned */}
            <div className="flex flex-wrap gap-1.5">
              {rule.products.length > 0
                ? rule.products.map((p) => (
                    <span key={p.id} className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 font-medium">
                      {p.name}
                    </span>
                  ))
                : <span className="text-xs text-gray-400 italic">No products assigned</span>}
            </div>

            {/* Field requirements */}
            <div className="flex flex-wrap gap-2">
              {rule.fields
                .filter((f) => f.check !== 3) // Only show mandatory fields
                .map((f, i) => (
                  <span key={i} className={`px-2 py-0.5 text-xs rounded-full font-medium ${CHECK_COLORS[f.check]}`}>
                    {f.field}
                    {f.check === 2 && f.other ? ` / ${f.other}` : ''}
                    {' '}{CHECK_LABELS[f.check] === 'Mandatory' ? '' : `(${CHECK_LABELS[f.check]})`}
                  </span>
                ))}
              {rule.fields.filter((f) => f.check !== 3).length === 0 && (
                <span className="text-xs text-gray-400 italic">No mandatory fields</span>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Create/Edit Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Compliance Rule' : 'Add Compliance Rule'}</h2>

            {/* Name */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Rule Name</label>
                <input
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  placeholder="e.g. Motor Comprehensive KYC"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Mati Flow ID (optional)</label>
                <input
                  value={formFlowId}
                  onChange={(e) => setFormFlowId(e.target.value)}
                  placeholder="e.g. 5f2a..."
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </div>

            {/* Products */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Assign to Products</label>
              <div className="flex flex-wrap gap-2 max-h-24 overflow-y-auto border border-gray-200 rounded-md p-2">
                {allProducts.map((p) => (
                  <label key={p.id} className="flex items-center gap-1.5 text-sm cursor-pointer">
                    <input
                      type="checkbox"
                      checked={formProductIds.includes(p.id)}
                      onChange={() => toggleProduct(p.id)}
                      className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span className={formProductIds.includes(p.id) ? 'font-medium text-blue-700' : 'text-gray-600'}>
                      {p.name}
                    </span>
                    {p.kyc_compliance !== null && p.kyc_compliance > 0 && p.kyc_compliance !== editingId && (
                      <span className="text-xs text-orange-500">(Rule #{p.kyc_compliance})</span>
                    )}
                  </label>
                ))}
              </div>
            </div>

            {/* Field rules table */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Document Requirements</label>
              <div className="border border-gray-200 rounded-md overflow-hidden max-h-64 overflow-y-auto">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                  <thead className="bg-gray-50 sticky top-0">
                    <tr>
                      <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Field</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Requirement</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Alternative Field</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {formFieldRules.map((fr, i) => (
                      <tr key={i} className={fr.check !== 3 ? 'bg-blue-50/30' : ''}>
                        <td className="px-3 py-2 font-medium text-gray-700">{fr.field}</td>
                        <td className="px-3 py-2">
                          <select
                            value={fr.check}
                            onChange={(e) => updateFieldRule(i, 'check', Number(e.target.value))}
                            className={`px-2 py-1 text-xs rounded border ${fr.check === 1 ? 'border-red-300 bg-red-50' : fr.check === 2 ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200'}`}
                          >
                            <option value={1}>Mandatory</option>
                            <option value={2}>Mandatory + Alternative</option>
                            <option value={3}>Optional</option>
                          </select>
                        </td>
                        <td className="px-3 py-2">
                          {fr.check === 2 ? (
                            <select
                              value={fr.other}
                              onChange={(e) => updateFieldRule(i, 'other', e.target.value)}
                              className="px-2 py-1 text-xs border border-gray-200 rounded w-full"
                            >
                              <option value="">-- Select Alternative --</option>
                              {kycFields
                                .filter((f) => f.name !== fr.field)
                                .map((f) => (
                                  <option key={f.id} value={f.name}>{f.name}</option>
                                ))}
                            </select>
                          ) : (
                            <span className="text-gray-300">-</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
              <button
                onClick={handleSave}
                disabled={saving || !formName.trim()}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                {saving ? 'Saving...' : 'Save'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirm */}
      {deleteConfirmId !== null && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setDeleteConfirmId(null) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-sm p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold text-red-600">Delete Compliance Rule?</h2>
            <p className="text-sm text-gray-600">Products linked to this rule will be unassigned. KYC cron will no longer check those products.</p>
            <div className="flex justify-end gap-2">
              <button onClick={() => setDeleteConfirmId(null)} className="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
              <button onClick={() => handleDelete(deleteConfirmId)} className="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
