import DualScrollTable from '../../components/common/DualScrollTable'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  useReinsuranceTypes,
  useCreateReinsuranceType,
  useUpdateReinsuranceType,
  useDeleteReinsuranceType,
} from '../../hooks/useReinsurance'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        {label}{required && <span className="text-red-500 ml-1">*</span>}
      </label>
      {children}
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  )
}

const EMPTY_FORM = { type_code: '', type_name: '', type_description: '', status: 1 }

export default function ReinsuranceTypePage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...EMPTY_FORM })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [apiError, setApiError] = useState<string | null>(null)

  const filters = {
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useReinsuranceTypes(filters)
  const createMutation = useCreateReinsuranceType()
  const updateMutation = useUpdateReinsuranceType()
  const deleteMutation = useDeleteReinsuranceType()

  const saving = createMutation.isPending || updateMutation.isPending

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  const meta = data?.meta
  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers() {
    const pages: (number | '...')[] = []
    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) pages.push(i)
    } else {
      pages.push(1)
      if (currentPage > 3) pages.push('...')
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) pages.push(i)
      if (currentPage < lastPage - 2) pages.push('...')
      pages.push(lastPage)
    }
    return pages
  }

  function openAdd() {
    setEditId(null)
    setForm({ ...EMPTY_FORM })
    setErrors({})
    setApiError(null)
    setShowForm(true)
  }

  function openEdit(item: { id: number; typeCode: string; typeName: string; typeDescription: string | null; status: number }) {
    setEditId(item.id)
    setForm({
      type_code:        item.typeCode,
      type_name:        item.typeName,
      type_description: item.typeDescription ?? '',
      status:           item.status,
    })
    setErrors({})
    setApiError(null)
    setShowForm(true)
  }

  function closeForm() {
    setShowForm(false)
    setEditId(null)
    setApiError(null)
  }

  function validate() {
    const errs: Record<string, string> = {}
    if (!form.type_code.trim()) errs.type_code = 'Type code is required.'
    if (!form.type_name.trim()) errs.type_name = 'Type name is required.'
    if (!form.type_description.trim()) errs.type_description = 'Description is required.'
    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!validate()) return
    setApiError(null)
    try {
      if (editId) {
        await updateMutation.mutateAsync({ id: editId, payload: form })
      } else {
        await createMutation.mutateAsync(form)
      }
      closeForm()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      if (e?.response?.data?.errors) {
        const serverErrors: Record<string, string> = {}
        for (const [k, v] of Object.entries(e.response.data.errors)) {
          serverErrors[k] = Array.isArray(v) ? v[0] : String(v)
        }
        setErrors(serverErrors)
      } else {
        setApiError(e?.response?.data?.message ?? 'An error occurred. Please try again.')
      }
    }
  }

  async function handleDelete(id: number, name: string) {
    if (!window.confirm(`Delete reinsurance type "${name}"? This cannot be undone.`)) return
    try {
      await deleteMutation.mutateAsync(id)
    } catch {
      alert('Failed to delete. The record may be in use.')
    }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Reinsurance Types</h1>
        <button
          onClick={openAdd}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition"
        >
          + Add New
        </button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Type code, name..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {isFetching && !isLoading && (
          <div className="absolute top-0 left-0 right-0 h-0.5 bg-blue-500 animate-pulse z-10" />
        )}
        <DualScrollTable>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Type Code</th>
                <th className="px-4 py-3 text-left">Type Name</th>
                <th className="px-4 py-3 text-left">Description</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Date</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No reinsurance types found" description="No reinsurance types have been configured yet." /></td></tr>
              ) : (
                data?.data.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                    <td className="px-4 py-2 font-medium text-blue-600">{item.typeCode || '\u2014'}</td>
                    <td className="px-4 py-2">{item.typeName || '\u2014'}</td>
                    <td className="px-4 py-2 truncate max-w-[200px]" title={item.typeDescription ?? ''}>{item.typeDescription || '\u2014'}</td>
                    <td className="px-4 py-2">
                      {item.status === 1
                        ? <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                        : <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                      }
                    </td>
                    <td className="px-4 py-2 text-gray-500">
                      {fmtDate(item.createdAt)}
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => openEdit(item)}
                          className="text-blue-500 hover:text-blue-700 p-1 rounded hover:bg-blue-50"
                          title="Edit"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button
                          onClick={() => handleDelete(item.id, item.typeName)}
                          className="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50"
                          title="Delete"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </DualScrollTable>

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">Showing {meta.from}&ndash;{meta.to} of {meta.total}</span>
            <div className="flex items-center gap-1">
              <button disabled={currentPage === 1} onClick={() => goToPage(currentPage - 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                ) : (
                  <button key={p} onClick={() => goToPage(p as number)} className={`px-2.5 py-1 rounded border text-sm ${p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 hover:bg-gray-100'}`}>{p}</button>
                )
              )}
              <button disabled={currentPage === lastPage} onClick={() => goToPage(currentPage + 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
              <div className="flex items-center gap-1 ml-3 border-l pl-3">
                <span className="text-gray-500 text-xs">Go to</span>
                <input type="number" min={1} max={lastPage} value={jumpPage} onChange={e => setJumpPage(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { const p = Number(jumpPage); if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') } } }} className="w-14 px-2 py-1 border rounded text-sm text-center" placeholder="#" />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Slide-over */}
      {showForm && (
        <div className="fixed inset-0 z-50 flex justify-end">
          <div className="fixed inset-0 bg-black/30" onClick={closeForm} />
          <div className="relative w-full max-w-lg bg-white shadow-xl overflow-y-auto">
            <div className="px-5 py-3 border-b flex items-center justify-between">
              <h2 className="text-lg font-semibold">{editId ? 'Edit' : 'Add New'} Reinsurance Type</h2>
              <button onClick={closeForm} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form onSubmit={handleSubmit} className="p-5 space-y-3">
              {apiError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{apiError}</div>
              )}
              <Field label="Type Code" required error={errors.type_code}>
                <input
                  type="text"
                  value={form.type_code}
                  onChange={e => setForm(f => ({ ...f, type_code: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. QS, XL"
                />
              </Field>
              <Field label="Type Name" required error={errors.type_name}>
                <input
                  type="text"
                  value={form.type_name}
                  onChange={e => setForm(f => ({ ...f, type_name: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. Quota Share"
                />
              </Field>
              <Field label="Description" required error={errors.type_description}>
                <textarea
                  value={form.type_description}
                  onChange={e => setForm(f => ({ ...f, type_description: e.target.value }))}
                  rows={3}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Brief description of this reinsurance type"
                />
              </Field>
              <Field label="Status">
                <label className="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.status === 1}
                    onChange={e => setForm(f => ({ ...f, status: e.target.checked ? 1 : 0 }))}
                    className="w-4 h-4 text-blue-600 border-gray-300 rounded"
                  />
                  <span className="text-sm text-gray-600">{form.status === 1 ? 'Active' : 'Inactive'}</span>
                </label>
              </Field>
              <div className="flex gap-3 pt-2">
                <button
                  type="submit"
                  disabled={saving}
                  className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 transition"
                >
                  {saving ? 'Saving...' : editId ? 'Update' : 'Create'}
                </button>
                <button
                  type="button"
                  onClick={closeForm}
                  className="px-4 py-2 border border-gray-300 text-sm rounded-md hover:bg-gray-50 transition"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
