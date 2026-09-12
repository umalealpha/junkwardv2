import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type ExtRow = {
  id: number
  s_CoverageName: string
  s_ScreenName: string
  s_CoverageCode: string
  rate: number | string | null
  n_DisplaySequence: number | null
  extention_type: string
  type: string
  s_ExtensionsGroupName: string | null
  s_ParentCoverageID: number
  s_SubCoverageID: number | null
  d_EffectiveDt: string | null
  d_ExpirationDt: string | null
  s_DISPLAYTOUSER: number | null
  parent_name?: string
  parent_code?: string
  sub_name?: string
}

type CoverageOpt = { id: number; name: string; code: string }

type FormState = {
  id?: number
  s_ParentCoverageID: string
  s_SubCoverageID: string
  s_CoverageName: string
  s_ScreenName: string
  rate: string
  n_DisplaySequence: string
  extention_type: string
  type: string
  s_ExtensionsGroupName: string
  s_CoverageDesc: string
  s_RatingMethod: string
  s_DISPLAYTOUSER: boolean
  d_EffectiveDt: string
  d_ExpirationDt: string
}

const EMPTY: FormState = {
  s_ParentCoverageID: '', s_SubCoverageID: '',
  s_CoverageName: '', s_ScreenName: '',
  rate: '', n_DisplaySequence: '',
  extention_type: 'NOEDIT', type: 'Extention',
  s_ExtensionsGroupName: 'Main',
  s_CoverageDesc: '', s_RatingMethod: 'FLAT',
  s_DISPLAYTOUSER: true, d_EffectiveDt: '', d_ExpirationDt: '',
}

const TYPE_OPTS = ['Extention', 'Perils', 'Excess', 'Misc']
const EXT_TYPE_OPTS = ['NOEDIT', 'NUMBER', 'RADIO', 'DROPDOWN']

export default function ExtensionsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const filters = {
    search: searchParams.get('search') || undefined,
    coverage_id: searchParams.get('coverage_id') || undefined,
    type: searchParams.get('type') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['extensions-master', filters],
    queryFn: () => apiClient.get('/master/extensions', { params: filters }).then(r => r.data),
  })

  const coverages = useQuery<{ data: CoverageOpt[] }>({
    queryKey: ['coverage-master-list'],
    queryFn: () => apiClient.get('/master/coverages/all').then(r => r.data),
    staleTime: 5 * 60_000,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['extensions-master'] })

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload: any = {
        s_ParentCoverageID: Number(form.s_ParentCoverageID),
        s_SubCoverageID: form.s_SubCoverageID ? Number(form.s_SubCoverageID) : null,
        s_CoverageName: form.s_CoverageName.trim(),
        s_ScreenName: form.s_ScreenName.trim(),
        rate: Number(form.rate),
        n_DisplaySequence: form.n_DisplaySequence ? Number(form.n_DisplaySequence) : null,
        extention_type: form.extention_type,
        type: form.type,
        s_ExtensionsGroupName: form.s_ExtensionsGroupName || null,
        s_CoverageDesc: form.s_CoverageDesc || null,
        s_RatingMethod: form.s_RatingMethod || null,
        s_DISPLAYTOUSER: form.s_DISPLAYTOUSER,
        d_EffectiveDt: form.d_EffectiveDt || null,
        d_ExpirationDt: form.d_ExpirationDt || null,
      }
      return form.id
        ? apiClient.put(`/master/extensions/${form.id}`, payload)
        : apiClient.post('/master/extensions', payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || 'Save failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/master/extensions/${id}`),
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

  const rows: ExtRow[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
  const coverageOpts = coverages.data?.data ?? []

  function openEdit(r: ExtRow) {
    setModal({
      open: true,
      form: {
        id: r.id,
        s_ParentCoverageID: String(r.s_ParentCoverageID),
        s_SubCoverageID: r.s_SubCoverageID ? String(r.s_SubCoverageID) : '',
        s_CoverageName: r.s_CoverageName ?? '',
        s_ScreenName: r.s_ScreenName ?? '',
        rate: String(r.rate ?? ''),
        n_DisplaySequence: String(r.n_DisplaySequence ?? ''),
        extention_type: r.extention_type ?? 'NOEDIT',
        type: r.type ?? 'Extention',
        s_ExtensionsGroupName: r.s_ExtensionsGroupName ?? '',
        s_CoverageDesc: '', s_RatingMethod: 'FLAT',
        s_DISPLAYTOUSER: !!r.s_DISPLAYTOUSER,
        d_EffectiveDt: r.d_EffectiveDt?.slice(0, 10) ?? '',
        d_ExpirationDt: r.d_ExpirationDt?.slice(0, 10) ?? '',
      },
    })
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Extensions, Excesses &amp; Misc</h1>
          <p className="text-sm text-gray-500 mt-0.5">Master rows in <code>extentions</code> — drives the Extensions / Perils / Excess rows on the policy coverage form.</p>
        </div>
        <button onClick={() => setModal({ open: true, form: { ...EMPTY } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Entry</button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input type="text" placeholder="Name, screen name, group..." defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => setFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-72" />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Parent Coverage</label>
          <select value={filters.coverage_id ?? ''}
            onChange={e => setFilter('coverage_id', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-72 bg-white">
            <option value="">All</option>
            {coverageOpts.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Type</label>
          <select value={filters.type ?? ''}
            onChange={e => setFilter('type', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-48 bg-white">
            <option value="">All types</option>
            {TYPE_OPTS.map(t => <option key={t} value={t}>{t}</option>)}
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
                <th className="px-4 py-3 text-left">Parent</th>
                <th className="px-4 py-3 text-left">Sub (opt)</th>
                <th className="px-4 py-3 text-left">Screen Name</th>
                <th className="px-4 py-3 text-left">Type</th>
                <th className="px-4 py-3 text-left">Input</th>
                <th className="px-4 py-3 text-left">Group</th>
                <th className="px-4 py-3 text-left">Rate %</th>
                <th className="px-4 py-3 text-left">Seq</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={10} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={10} className="p-0"><EmptyState compact title="No entries" description="No extensions have been configured yet." /></td></tr>
              ) : rows.map(r => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{r.id}</td>
                  <td className="px-4 py-2 text-gray-700">{r.parent_name ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-600">{r.sub_name ?? '—'}</td>
                  <td className="px-4 py-2 font-medium text-gray-800">{r.s_ScreenName}</td>
                  <td className="px-4 py-2"><span className="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">{r.type}</span></td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{r.extention_type}</td>
                  <td className="px-4 py-2 text-gray-600">{r.s_ExtensionsGroupName ?? '—'}</td>
                  <td className="px-4 py-2">{r.rate ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-500">{r.n_DisplaySequence ?? '—'}</td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => openEdit(r)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => { if (confirm(`Delete "${r.s_ScreenName}"?`)) deleteMut.mutate(r.id) }}
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
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900">{modal.form.id ? 'Edit' : 'Add'} Entry</h2>
              <button onClick={() => setModal({ open: false, form: EMPTY })} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Parent Coverage *</label>
                  <select value={modal.form.s_ParentCoverageID}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_ParentCoverageID: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm bg-white">
                    <option value="">-- Select --</option>
                    {coverageOpts.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Type *</label>
                  <select value={modal.form.type}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, type: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm bg-white">
                    {TYPE_OPTS.map(t => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Coverage Name *</label>
                  <input type="text" value={modal.form.s_CoverageName}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_CoverageName: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Screen Name *</label>
                  <input type="text" value={modal.form.s_ScreenName}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_ScreenName: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Input Type</label>
                  <select value={modal.form.extention_type}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, extention_type: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm bg-white">
                    {EXT_TYPE_OPTS.map(t => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Extensions Group</label>
                  <input type="text" value={modal.form.s_ExtensionsGroupName}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_ExtensionsGroupName: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" placeholder="Main" />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Rate % *</label>
                  <input type="number" step="0.0001" value={modal.form.rate}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, rate: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Display Seq</label>
                  <input type="number" value={modal.form.n_DisplaySequence}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, n_DisplaySequence: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
                <div className="flex items-end">
                  <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={modal.form.s_DISPLAYTOUSER}
                      onChange={e => setModal(m => ({ ...m, form: { ...m.form, s_DISPLAYTOUSER: e.target.checked } }))} />
                    Visible to user
                  </label>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Effective From</label>
                  <input type="date" value={modal.form.d_EffectiveDt}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, d_EffectiveDt: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs text-gray-600 mb-1">Expiration</label>
                  <input type="date" value={modal.form.d_ExpirationDt}
                    onChange={e => setModal(m => ({ ...m, form: { ...m.form, d_ExpirationDt: e.target.value } }))}
                    className="w-full px-3 py-2 border rounded text-sm" />
                </div>
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t">
              <button onClick={() => {
                if (!modal.form.s_ParentCoverageID || !modal.form.s_CoverageName || !modal.form.s_ScreenName || !modal.form.rate) {
                  alert('Parent, name, screen name and rate are required'); return
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
