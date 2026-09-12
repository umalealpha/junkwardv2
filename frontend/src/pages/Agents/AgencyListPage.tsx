import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

interface Agency { id: number; name: string; status: number; agentCount: number; totalPremium: string; createdAt: string }

// Mirror the backend Agency::normalizeName() so the live duplicate check below
// matches exactly what the server (and DB collation) will treat as the same
// broker: trim, collapse inner whitespace, case-insensitive.
const normalizeName = (s: string) => s.trim().replace(/\s+/g, ' ').toLowerCase()

export default function AgencyListPage() {
  const [agencies, setAgencies] = useState<Agency[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState({ name: '', status: 1 })
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})

  const load = () => {
    setLoading(true)
    apiClient.get('/agencies').then(r => setAgencies(r.data.data ?? [])).catch(() => {}).finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [])

  function getFieldError(fieldName: string): string {
    const errors = fieldErrors[fieldName]
    return errors ? errors[0] : ''
  }

  function openModal(agency: Agency | null) {
    setFormError(''); setFieldErrors({})
    if (agency) { setEditingId(agency.id); setForm({ name: agency.name, status: agency.status }) }
    else { setEditingId(null); setForm({ name: '', status: 1 }) }
    setModalOpen(true)
  }

  // Proactive control: flag a name that already exists (normalised, excluding
  // the row being edited) before the user even submits — the create-broker
  // guard the CFO asked for. The server + DB unique index remain the backstop.
  const trimmedName = form.name.trim()
  const duplicateAgency = trimmedName
    ? agencies.find(a => a.id !== editingId && normalizeName(a.name) === normalizeName(form.name))
    : undefined
  const nameError = getFieldError('name') || (duplicateAgency ? 'A broker with this name already exists.' : '')

  async function handleSave() {
    setSaving(true); setFormError(''); setFieldErrors({})
    try {
      if (editingId) await apiClient.put(`/agencies/${editingId}`, form)
      else await apiClient.post('/agencies', form)
      setModalOpen(false); load()
    } catch (err: any) {
      if (err?.response?.status === 422 && err?.response?.data?.errors) {
        setFieldErrors(err.response.data.errors)
        setFormError('Please fix the errors below')
      } else {
        setFormError(err?.response?.data?.message || 'Failed to save agency')
      }
    } finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Agencies</h1>
        <button onClick={() => openModal(null)}
          className="px-4 py-2 min-h-[44px] bg-primary text-primary-contrast text-sm font-medium rounded-md hover:opacity-90">+ Add Agency</button>
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agency Name</th>
              <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Agents</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Premium</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && agencies.length === 0 && <tr><td colSpan={6} className="p-0"><EmptyState compact title="No agencies" description="No agencies have been added yet." /></td></tr>}
            {agencies.map(a => (
              <tr key={a.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 text-gray-500">{a.id}</td>
                <td className="px-4 py-3 font-medium text-gray-800">{a.name}</td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-block w-3 h-3 rounded-full ${a.status ? 'bg-green-500' : 'bg-gray-300'}`} />
                </td>
                <td className="px-4 py-3 text-right font-medium">{a.agentCount}</td>
                <td className="px-4 py-3 text-right font-medium text-green-700">P {a.totalPremium}</td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => openModal(a)}
                    className="text-sm text-primary hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Agency' : 'Add Agency'}</h2>
            {formError && <div className="p-3 text-sm text-red-700 bg-red-50 rounded-md" role="alert">{formError}</div>}
            <div>
              <label htmlFor="agency-name" className="block text-xs font-medium text-ink-muted mb-1">Name</label>
              <input id="agency-name" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })}
                aria-invalid={!!nameError} aria-describedby={nameError ? 'agency-name-error' : undefined}
                className={`w-full px-3 py-2 min-h-[44px] border rounded-md text-sm ${nameError ? 'border-red-500' : ''}`} />
              {nameError && <p id="agency-name-error" className="text-xs text-red-600 mt-1" role="alert">{nameError}</p>}
            </div>
            <div className="flex items-center gap-2">
              <input id="agency-active" type="checkbox" checked={form.status === 1} onChange={e => setForm({ ...form, status: e.target.checked ? 1 : 0 })}
                className="rounded border-line text-primary" />
              <label htmlFor="agency-active" className="text-sm text-ink-muted">Active</label>
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 min-h-[44px] text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !trimmedName || !!duplicateAgency}
                className="px-4 py-2 min-h-[44px] text-sm bg-primary text-primary-contrast rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
