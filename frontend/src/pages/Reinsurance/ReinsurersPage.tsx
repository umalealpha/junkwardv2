import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type Reinsurer = {
  id: number
  company_name: string
  email: string
  cellphone: string
}

type FormState = {
  id?: number
  company_name: string
  email: string
  cellphone: string
}

const EMPTY: FormState = { company_name: '', email: '', cellphone: '' }

export default function ReinsurersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const filters = {
    search: searchParams.get('search') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['reinsurers', filters],
    queryFn: () => apiClient.get('/reinsurance/reinsurers', { params: filters }).then(r => r.data),
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['reinsurers'] })

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload = {
        company_name: form.company_name.trim(),
        email:        form.email.trim(),
        cellphone:    form.cellphone.trim(),
      }
      return form.id
        ? apiClient.put(`/reinsurance/reinsurers/${form.id}`, payload)
        : apiClient.post('/reinsurance/reinsurers', payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || 'Save failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/reinsurance/reinsurers/${id}`),
    onSuccess: invalidate,
    onError: (err: any) => alert(err?.response?.data?.error || 'Delete failed'),
  })

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    value ? next.set(key, value) : next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }
  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  function openEdit(r: Reinsurer) {
    setModal({
      open: true,
      form: {
        id: r.id,
        company_name: r.company_name ?? '',
        email: r.email ?? '',
        cellphone: r.cellphone ?? '',
      },
    })
  }

  const rows: Reinsurer[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Reinsurers</h1>
          <p className="text-sm text-gray-500 mt-0.5">Admin catalogue of reinsurance companies (linked from treaty configuration).</p>
        </div>
        <button onClick={() => setModal({ open: true, form: { ...EMPTY } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Reinsurer</button>
      </div>

      <div>
        <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
        <input type="text" placeholder="Name, email, or phone..."
          defaultValue={filters.search}
          onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }}
          onBlur={e => setFilter('search', e.target.value)}
          className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-72" />
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {list.isFetching && !list.isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center"><LoadingSpinner size="md" /></div>
        )}
        <DualScrollTable>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Company Name</th>
                <th className="px-4 py-3 text-left">Email</th>
                <th className="px-4 py-3 text-left">Cellphone</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={5} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={5} className="p-0"><EmptyState compact title="No reinsurers" description="No reinsurers have been added yet." /></td></tr>
              ) : rows.map(r => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{r.id}</td>
                  <td className="px-4 py-2 font-medium text-gray-800">{r.company_name}</td>
                  <td className="px-4 py-2 text-gray-600">{r.email || '—'}</td>
                  <td className="px-4 py-2 text-gray-600 font-mono text-xs">{r.cellphone || '—'}</td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => openEdit(r)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => { if (confirm(`Delete "${r.company_name}"?`)) deleteMut.mutate(r.id) }}
                      className="text-red-600 hover:underline text-xs">Delete</button>
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
              <button disabled={meta.current_page === 1} onClick={() => goToPage(meta.current_page - 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40">Prev</button>
              <span className="px-2 text-gray-600">{meta.current_page} / {meta.last_page}</span>
              <button disabled={meta.current_page === meta.last_page} onClick={() => goToPage(meta.current_page + 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40">Next</button>
            </div>
          </div>
        )}
      </div>

      {modal.open && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, form: EMPTY }) }}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 space-y-3 max-h-[85vh] flex flex-col overflow-hidden">
            <div className="flex items-center justify-between shrink-0">
              <h2 className="text-lg font-bold text-gray-900">{modal.form.id ? 'Edit' : 'Add'} Reinsurer</h2>
              <button onClick={() => setModal({ open: false, form: EMPTY })} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div className="space-y-3 overflow-y-auto grow min-h-0">
              <div>
                <label className="block text-xs text-gray-600 mb-1">Company Name *</label>
                <input type="text" value={modal.form.company_name}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, company_name: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Email *</label>
                <input type="email" value={modal.form.email}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, email: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Cellphone *</label>
                <input type="text" value={modal.form.cellphone}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, cellphone: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t shrink-0">
              <button onClick={() => {
                if (!modal.form.company_name || !modal.form.email || !modal.form.cellphone) {
                  alert('All fields are required'); return
                }
                saveMut.mutate(modal.form)
              }} disabled={saveMut.isPending}
                className="flex-1 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                {saveMut.isPending ? 'Saving…' : (modal.form.id ? 'Update' : 'Create')}
              </button>
              <button onClick={() => setModal({ open: false, form: EMPTY })}
                className="px-4 py-2 border rounded text-sm hover:bg-gray-50">Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
