import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import { reportErrorToTeam } from '../../utils/reportError'
import EmptyState from '../../components/common/EmptyState'

interface KycField {
  id: number
  name: string
  slug: string
  customer_kyc_column: string
  created_at: string
}

const EMPTY_FORM = { name: '', customer_kyc_column: '' }

export default function KycFieldsPage() {
  const [fields, setFields] = useState<KycField[]>([])
  const [columns, setColumns] = useState<string[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [saving, setSaving] = useState(false)
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)

  function loadData() {
    setLoading(true)
    setError('')
    Promise.all([
      apiClient.get('/kyc-fields'),
      apiClient.get('/kyc-fields/columns'),
    ])
      .then(([fieldsRes, colsRes]) => {
        setFields(fieldsRes.data.data ?? [])
        setColumns(colsRes.data.data ?? [])
      })
      .catch((err: any) => {
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load KYC fields.'
        setError(message)
        reportErrorToTeam({ error: message, stack: err?.stack, context: 'KycFieldsPage:loadData' })
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { loadData() }, [])

  function openCreate() {
    setEditingId(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(field: KycField) {
    setEditingId(field.id)
    setForm({ name: field.name, customer_kyc_column: field.customer_kyc_column })
    setModalOpen(true)
  }

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) {
        await apiClient.put(`/kyc-fields/${editingId}`, form)
      } else {
        await apiClient.post('/kyc-fields', form)
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
      await apiClient.delete(`/kyc-fields/${id}`)
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
          {[1, 2, 3, 4].map((i) => <div key={i} className="h-10 bg-gray-200 rounded" />)}
        </div>
      </div>
    )
  }

  if (error) return (
    <div className="p-6">
      <div className="bg-red-50 border border-red-200 rounded-lg p-6 space-y-3">
        <p className="text-sm font-medium text-red-700">Failed to load KYC fields.</p>
        <p className="text-xs text-red-600">{error}</p>
        <button onClick={loadData} className="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Try again</button>
      </div>
    </div>
  )

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">KYC Fields</h1>
        <button
          onClick={openCreate}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
        >
          + Add Field
        </button>
      </div>

      <p className="text-sm text-gray-500">
        Define available KYC document types. Each field maps to a column in the customer_kyc table.
      </p>

      {/* Table */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DB Column</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {fields.length === 0 && (
              <tr><td colSpan={6} className="p-0"><EmptyState compact title="No KYC fields defined yet" description="Add a KYC field to get started." /></td></tr>
            )}
            {fields.map((f) => (
              <tr key={f.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 text-sm text-gray-600">{f.id}</td>
                <td className="px-4 py-3 text-sm font-medium text-gray-800">{f.name}</td>
                <td className="px-4 py-3 text-sm text-gray-500">{f.slug}</td>
                <td className="px-4 py-3 text-sm font-mono text-blue-700 bg-blue-50 rounded">{f.customer_kyc_column}</td>
                <td className="px-4 py-3 text-sm text-gray-400">{f.created_at?.split('T')[0] ?? '-'}</td>
                <td className="px-4 py-3 text-right space-x-2">
                  <button onClick={() => openEdit(f)} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => setDeleteConfirmId(f.id)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit KYC Field' : 'Add KYC Field'}</h2>

            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Field Name</label>
              <input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="e.g. Driving License"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">customer_kyc Column</label>
              <select
                value={form.customer_kyc_column}
                onChange={(e) => setForm({ ...form, customer_kyc_column: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
              >
                <option value="">-- Select Column --</option>
                {columns.map((col) => (
                  <option key={col} value={col}>{col}</option>
                ))}
              </select>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
              <button
                onClick={handleSave}
                disabled={saving || !form.name || !form.customer_kyc_column}
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
            <h2 className="text-lg font-bold text-red-600">Delete KYC Field?</h2>
            <p className="text-sm text-gray-600">This will permanently remove this field definition. Any compliance rules referencing it will no longer match.</p>
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
