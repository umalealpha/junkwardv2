import { useState, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type SpecifiedItem = {
  id: number
  specified_code: string
  specified_name: string
  coverage_id: number
  rate: number | string | null
  effective_from: string | null
  effective_to: string | null
  coverage_name?: string
  coverage_code?: string
}

type CoverageOpt = { id: number; name: string; code: string }

type FormState = {
  id?: number
  coverage_id: string
  specified_name: string
  rate: string
  effective_from: string
  effective_to: string
}

const EMPTY: FormState = {
  coverage_id: '', specified_name: '', rate: '', effective_from: '', effective_to: '',
}

export default function SpecifiedCoverageItemsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const filters = {
    search: searchParams.get('search') || undefined,
    coverage_id: searchParams.get('coverage_id') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['specified-coverage-items', filters],
    queryFn: () => apiClient.get('/master/specified-coverage-items', { params: filters }).then(r => r.data),
  })

  const coverages = useQuery<{ data: CoverageOpt[] }>({
    queryKey: ['coverage-master-list'],
    queryFn: () => apiClient.get('/master/coverages/all').then(r => r.data),
    staleTime: 5 * 60_000,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['specified-coverage-items'] })

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload = {
        coverage_id: Number(form.coverage_id),
        specified_name: form.specified_name.trim(),
        rate: Number(form.rate),
        effective_from: form.effective_from || null,
        effective_to: form.effective_to || null,
      }
      return form.id
        ? apiClient.put(`/master/specified-coverage-items/${form.id}`, payload)
        : apiClient.post('/master/specified-coverage-items', payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || 'Save failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/master/specified-coverage-items/${id}`),
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

  const rows: SpecifiedItem[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
  const coverageOpts = coverages.data?.data ?? []

  const coverageName = useMemo(() => (id: number) =>
    coverageOpts.find(c => c.id === id)?.name ?? `#${id}`, [coverageOpts])

  function openCreate() { setModal({ open: true, form: { ...EMPTY } }) }
  function openEdit(row: SpecifiedItem) {
    setModal({
      open: true,
      form: {
        id: row.id,
        coverage_id: String(row.coverage_id),
        specified_name: row.specified_name,
        rate: String(row.rate ?? ''),
        effective_from: row.effective_from?.slice(0, 10) ?? '',
        effective_to: row.effective_to?.slice(0, 10) ?? '',
      },
    })
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Specified Coverage Items</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Master catalogue of items available under each coverage (rate is applied when policy operators pick from this list).
          </p>
        </div>
        <button onClick={openCreate}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Item</button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input type="text" placeholder="Name, code, or coverage..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => setFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-72" />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Filter by Coverage</label>
          <select value={filters.coverage_id ?? ''}
            onChange={e => setFilter('coverage_id', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-72 bg-white">
            <option value="">All coverages</option>
            {coverageOpts.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>
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
                <th className="px-4 py-3 text-left">Coverage</th>
                <th className="px-4 py-3 text-left">Code</th>
                <th className="px-4 py-3 text-left">Specified Name</th>
                <th className="px-4 py-3 text-left">Rate %</th>
                <th className="px-4 py-3 text-left">Effective From</th>
                <th className="px-4 py-3 text-left">Effective To</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={8} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={8} className="p-0"><EmptyState compact title="No items" description="No specified coverage items have been added yet." /></td></tr>
              ) : rows.map(r => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{r.id}</td>
                  <td className="px-4 py-2 text-gray-700">{r.coverage_name ?? coverageName(r.coverage_id)}</td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{r.coverage_code ?? '—'}</td>
                  <td className="px-4 py-2 font-medium text-gray-800">{r.specified_name}</td>
                  <td className="px-4 py-2">{r.rate ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-500">{r.effective_from?.slice(0, 10) ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-500">{r.effective_to?.slice(0, 10) ?? '—'}</td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => openEdit(r)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => { if (confirm(`Delete "${r.specified_name}"?`)) deleteMut.mutate(r.id) }}
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
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, form: EMPTY }) }}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900">{modal.form.id ? 'Edit' : 'Add'} Specified Item</h2>
              <button onClick={() => setModal({ open: false, form: EMPTY })} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="block text-xs text-gray-600 mb-1">Coverage *</label>
                <select value={modal.form.coverage_id}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, coverage_id: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="">-- Select coverage --</option>
                  {coverageOpts.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Specified Name *</label>
                <input type="text" value={modal.form.specified_name}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, specified_name: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" placeholder="e.g. Canopy" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Rate % *</label>
                <input type="number" step="0.0001" value={modal.form.rate}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, rate: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" placeholder="0.00" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Effective From</label>
                  <input type="date" value={modal.form.effective_from}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, effective_from: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Effective To</label>
                  <input type="date" value={modal.form.effective_to}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, effective_to: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t">
              <button onClick={() => {
                if (!modal.form.coverage_id || !modal.form.specified_name || !modal.form.rate) {
                  alert('Coverage, name and rate are required'); return
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
