import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type Group = {
  n_PrValidationRuleGroupMasters_PK: number
  s_RuleCode: string
  s_RuleDesc: string | null
  n_Product_FK: number
  product_name?: string | null
  s_Status?: string | null
}

type ProductOpt = { id: number; name: string }

type FormState = {
  id?: number
  s_RuleCode: string
  s_RuleDesc: string
  n_Product_FK: string
  s_Status: string
}

const EMPTY: FormState = { s_RuleCode: '', s_RuleDesc: '', n_Product_FK: '', s_Status: 'ACTIVE' }

export default function ValidationGroupsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const filters = {
    search: searchParams.get('search') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['validation-groups', filters],
    queryFn: () => apiClient.get('/policy-validation/groups', { params: filters }).then(r => r.data),
  })

  const products = useQuery<{ data: ProductOpt[] }>({
    queryKey: ['products-list-for-groups'],
    queryFn: () => apiClient.get('/lookups/products').then(r => r.data).catch(() => ({ data: [] })),
    staleTime: 5 * 60_000,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['validation-groups'] })

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload = {
        s_RuleCode: form.s_RuleCode.trim(),
        s_RuleDesc: form.s_RuleDesc || null,
        n_Product_FK: Number(form.n_Product_FK),
        s_Status: form.s_Status,
      }
      return form.id
        ? apiClient.put(`/policy-validation/groups/${form.id}`, payload)
        : apiClient.post('/policy-validation/groups', payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || err?.response?.data?.message || 'Save failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/policy-validation/groups/${id}`),
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

  function openEdit(g: Group) {
    setModal({
      open: true,
      form: {
        id: g.n_PrValidationRuleGroupMasters_PK,
        s_RuleCode: g.s_RuleCode ?? '',
        s_RuleDesc: g.s_RuleDesc ?? '',
        n_Product_FK: String(g.n_Product_FK ?? ''),
        s_Status: g.s_Status ?? 'ACTIVE',
      },
    })
  }

  const rows: Group[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
  const productOpts = products.data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Validation Rule Groups</h1>
          <p className="text-sm text-gray-500 mt-0.5">Bundles of rules mapped to roles. A role's rule group decides which rules gate its Submit-to-Approval.</p>
        </div>
        <button onClick={() => setModal({ open: true, form: { ...EMPTY } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Group</button>
      </div>

      <div>
        <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
        <input type="text" placeholder="Group code or description..."
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
                <th className="px-4 py-3 text-left">Code</th>
                <th className="px-4 py-3 text-left">Description</th>
                <th className="px-4 py-3 text-left">Product</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={6} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={6} className="p-0"><EmptyState compact title="No groups" description="No validation groups have been configured yet." /></td></tr>
              ) : rows.map(g => (
                <tr key={g.n_PrValidationRuleGroupMasters_PK} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{g.n_PrValidationRuleGroupMasters_PK}</td>
                  <td className="px-4 py-2 font-mono text-xs text-blue-700">{g.s_RuleCode}</td>
                  <td className="px-4 py-2 text-gray-700">{g.s_RuleDesc || '—'}</td>
                  <td className="px-4 py-2 text-gray-600">{g.product_name ?? `#${g.n_Product_FK}`}</td>
                  <td className="px-4 py-2">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${g.s_Status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {g.s_Status ?? '—'}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => openEdit(g)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => { if (confirm(`Delete group "${g.s_RuleCode}"?`)) deleteMut.mutate(g.n_PrValidationRuleGroupMasters_PK) }}
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
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 space-y-3 max-h-[85vh] flex flex-col overflow-hidden">
            <div className="flex items-center justify-between shrink-0">
              <h2 className="text-lg font-bold text-gray-900">{modal.form.id ? 'Edit' : 'Add'} Rule Group</h2>
              <button onClick={() => setModal({ open: false, form: EMPTY })} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div className="space-y-3 overflow-y-auto grow min-h-0">
              <div>
                <label className="block text-xs text-gray-600 mb-1">Code *</label>
                <input type="text" value={modal.form.s_RuleCode}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_RuleCode: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm font-mono" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Description</label>
                <input type="text" value={modal.form.s_RuleDesc}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_RuleDesc: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Product *</label>
                <select value={modal.form.n_Product_FK}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, n_Product_FK: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="">-- Select --</option>
                  {productOpts.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Status</label>
                <select value={modal.form.s_Status}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_Status: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="ACTIVE">Active</option>
                  <option value="INACTIVE">Inactive</option>
                </select>
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t shrink-0">
              <button onClick={() => {
                if (!modal.form.s_RuleCode || !modal.form.n_Product_FK) {
                  alert('Code and product are required'); return
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
