import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type Rule = {
  n_PrValidationRuleMaster_PK: number
  s_RuleCode: string
  s_Description: string | null
  s_ScreenErrorMsg: string | null
  n_Product_FK: number
  s_RuleApplyOn: string | null
  s_CanRate: string | null
  s_CanPrintQuote: string | null
  s_CanPrintApp: string | null
  s_CanBindApp: string | null
  s_CanUnBoundApp: string | null
  s_CanIssue: string | null
  d_EffectiveDateFrom: string | null
  d_EffectiveDateTo: string | null
  s_RuleStatus: string
}

type ProductOpt = { id: number; name: string }

type FormState = {
  id?: number
  s_RuleCode: string
  s_Description: string
  s_ScreenErrorMsg: string
  n_Product_FK: string
  s_RuleApplyOn: string
  s_CanRate: string
  s_CanPrintQuote: string
  s_CanPrintApp: string
  s_CanBindApp: string
  s_CanUnBoundApp: string
  s_CanIssue: string
  d_EffectiveDateFrom: string
  d_EffectiveDateTo: string
  s_RuleStatus: string
}

const EMPTY: FormState = {
  s_RuleCode: '', s_Description: '', s_ScreenErrorMsg: '',
  n_Product_FK: '', s_RuleApplyOn: 'TERMSTARTDATE',
  s_CanRate: 'YES', s_CanPrintQuote: 'YES', s_CanPrintApp: 'YES',
  s_CanBindApp: 'YES', s_CanUnBoundApp: 'YES', s_CanIssue: 'YES',
  d_EffectiveDateFrom: '', d_EffectiveDateTo: '', s_RuleStatus: 'ACTIVE',
}

const APPLY_ON = ['TERMSTARTDATE', 'TRANSACTIONSTARTDATE', 'BOOKINGDATE']

export default function ValidationRulesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const filters = {
    search: searchParams.get('search') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['validation-rules', filters],
    queryFn: () => apiClient.get('/policy-validation/rules', { params: filters }).then(r => r.data),
  })

  const products = useQuery<{ data: ProductOpt[] }>({
    queryKey: ['products-list-for-rules'],
    queryFn: () => apiClient.get('/lookups/products').then(r => r.data).catch(() => ({ data: [] })),
    staleTime: 5 * 60_000,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['validation-rules'] })

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload = {
        s_RuleCode: form.s_RuleCode.trim(),
        s_Description: form.s_Description || null,
        s_ScreenErrorMsg: form.s_ScreenErrorMsg || null,
        n_Product_FK: Number(form.n_Product_FK),
        s_RuleApplyOn: form.s_RuleApplyOn || null,
        s_CanRate: form.s_CanRate, s_CanPrintQuote: form.s_CanPrintQuote,
        s_CanPrintApp: form.s_CanPrintApp, s_CanBindApp: form.s_CanBindApp,
        s_CanUnBoundApp: form.s_CanUnBoundApp, s_CanIssue: form.s_CanIssue,
        d_EffectiveDateFrom: form.d_EffectiveDateFrom,
        d_EffectiveDateTo: form.d_EffectiveDateTo,
        s_RuleStatus: form.s_RuleStatus,
      }
      return form.id
        ? apiClient.put(`/policy-validation/rules/${form.id}`, payload)
        : apiClient.post('/policy-validation/rules', payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || err?.response?.data?.message || 'Save failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/policy-validation/rules/${id}`),
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

  function openEdit(r: Rule) {
    setModal({
      open: true,
      form: {
        id: r.n_PrValidationRuleMaster_PK,
        s_RuleCode: r.s_RuleCode ?? '',
        s_Description: r.s_Description ?? '',
        s_ScreenErrorMsg: r.s_ScreenErrorMsg ?? '',
        n_Product_FK: String(r.n_Product_FK ?? ''),
        s_RuleApplyOn: r.s_RuleApplyOn ?? 'TERMSTARTDATE',
        s_CanRate: r.s_CanRate ?? 'YES',
        s_CanPrintQuote: r.s_CanPrintQuote ?? 'YES',
        s_CanPrintApp: r.s_CanPrintApp ?? 'YES',
        s_CanBindApp: r.s_CanBindApp ?? 'YES',
        s_CanUnBoundApp: r.s_CanUnBoundApp ?? 'YES',
        s_CanIssue: r.s_CanIssue ?? 'YES',
        d_EffectiveDateFrom: r.d_EffectiveDateFrom?.slice(0, 10) ?? '',
        d_EffectiveDateTo: r.d_EffectiveDateTo?.slice(0, 10) ?? '',
        s_RuleStatus: r.s_RuleStatus ?? 'ACTIVE',
      },
    })
  }

  const rows: Rule[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
  const productOpts = products.data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Policy Validation Rules</h1>
          <p className="text-sm text-gray-500 mt-0.5">Rules evaluated at Submit-to-Approval to gate lifecycle transitions.</p>
        </div>
        <button onClick={() => setModal({ open: true, form: { ...EMPTY } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Rule</button>
      </div>

      <div>
        <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
        <input type="text" placeholder="Rule code or description..."
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
                <th className="px-4 py-3 text-left">Apply On</th>
                <th className="px-4 py-3 text-left">Effective</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No rules" description="No validation rules have been configured yet." /></td></tr>
              ) : rows.map(r => (
                <tr key={r.n_PrValidationRuleMaster_PK} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{r.n_PrValidationRuleMaster_PK}</td>
                  <td className="px-4 py-2 font-mono text-xs text-blue-700">{r.s_RuleCode}</td>
                  <td className="px-4 py-2 text-gray-700">{r.s_Description || '—'}</td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{r.s_RuleApplyOn || '—'}</td>
                  <td className="px-4 py-2 text-gray-500 text-xs">
                    {r.d_EffectiveDateFrom?.slice(0, 10) ?? '—'} → {r.d_EffectiveDateTo?.slice(0, 10) ?? '—'}
                  </td>
                  <td className="px-4 py-2">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${r.s_RuleStatus === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {r.s_RuleStatus}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => openEdit(r)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => { if (confirm(`Delete rule "${r.s_RuleCode}"?`)) deleteMut.mutate(r.n_PrValidationRuleMaster_PK) }}
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
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 p-4 pt-10"
          onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, form: EMPTY }) }}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-5 space-y-3 max-h-[85vh] flex flex-col overflow-hidden">
            <div className="flex items-center justify-between shrink-0">
              <h2 className="text-lg font-bold text-gray-900">{modal.form.id ? 'Edit' : 'Add'} Validation Rule</h2>
              <button onClick={() => setModal({ open: false, form: EMPTY })} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div className="grid grid-cols-2 gap-3 overflow-y-auto grow min-h-0">
              <div>
                <label className="block text-xs text-gray-600 mb-1">Rule Code *</label>
                <input type="text" value={modal.form.s_RuleCode}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_RuleCode: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm font-mono" />
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
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Description</label>
                <input type="text" value={modal.form.s_Description}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_Description: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Screen Error Message</label>
                <input type="text" value={modal.form.s_ScreenErrorMsg}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_ScreenErrorMsg: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm"
                  placeholder="Shown to the user when this rule fails" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Apply On</label>
                <select value={modal.form.s_RuleApplyOn}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_RuleApplyOn: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  {APPLY_ON.map(o => <option key={o} value={o}>{o}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Status</label>
                <select value={modal.form.s_RuleStatus}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_RuleStatus: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="ACTIVE">Active</option>
                  <option value="INACTIVE">Inactive</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Effective From *</label>
                <input type="date" value={modal.form.d_EffectiveDateFrom}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, d_EffectiveDateFrom: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Effective To *</label>
                <input type="date" value={modal.form.d_EffectiveDateTo}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, d_EffectiveDateTo: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs font-semibold text-gray-700 mb-2">Gate lifecycle transitions</label>
                <div className="grid grid-cols-3 gap-2">
                  {([
                    ['s_CanRate', 'Rate'],
                    ['s_CanPrintQuote', 'Print Quote'],
                    ['s_CanPrintApp', 'Print App'],
                    ['s_CanBindApp', 'Bind App'],
                    ['s_CanUnBoundApp', 'UnBound App'],
                    ['s_CanIssue', 'Issue'],
                  ] as const).map(([k, l]) => (
                    <label key={k} className="flex items-center gap-2 text-xs">
                      <input type="checkbox" checked={modal.form[k] === 'YES'}
                        onChange={e => setModal(m => ({ ...m, form: { ...m.form, [k]: e.target.checked ? 'YES' : 'NO' } }))} />
                      {l}
                    </label>
                  ))}
                </div>
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t shrink-0">
              <button onClick={() => {
                if (!modal.form.s_RuleCode || !modal.form.n_Product_FK || !modal.form.d_EffectiveDateFrom || !modal.form.d_EffectiveDateTo) {
                  alert('Code, product, effective-from and effective-to are required'); return
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
