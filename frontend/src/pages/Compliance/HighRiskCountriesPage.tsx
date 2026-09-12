import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type HighRiskCountry = {
  id: number
  country_id: number
  country_name: string | null
  country_code: string | null
  created_at: string | null
}

type CountryOption = { id: number; name: string; code?: string | null }

export default function HighRiskCountriesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)
  const [countryId, setCountryId] = useState('')

  const filters = {
    search: searchParams.get('search') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['high-risk-countries', filters],
    queryFn: () => apiClient.get('/master/high-risk-countries', { params: filters }).then(r => r.data),
  })

  // Countries not yet on the list — drives the Add dropdown so no duplicates.
  const options = useQuery<{ data: CountryOption[] }>({
    queryKey: ['high-risk-country-options'],
    queryFn: () => apiClient.get('/master/high-risk-countries/options').then(r => r.data),
    enabled: modalOpen,
  })

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['high-risk-countries'] })
    qc.invalidateQueries({ queryKey: ['high-risk-country-options'] })
  }

  const addMut = useMutation({
    mutationFn: (id: number) => apiClient.post('/master/high-risk-countries', { country_id: id }),
    onSuccess: () => { invalidate(); setModalOpen(false); setCountryId('') },
    onError: (err: any) => alert(err?.response?.data?.error || err?.response?.data?.message || 'Add failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/master/high-risk-countries/${id}`),
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

  const rows: HighRiskCountry[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">High Risk Countries</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            AML watch-list. Customers whose country is on this list are flagged
            "High Risk Customer" on the policy view.
          </p>
        </div>
        <button onClick={() => { setModalOpen(true); setCountryId('') }}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Country</button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input type="text" placeholder="Country name..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => setFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-80" />
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
                <th className="px-4 py-3 text-left">Country</th>
                <th className="px-4 py-3 text-left">Code</th>
                <th className="px-4 py-3 text-left">Added On</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={5} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={5} className="p-0"><EmptyState compact title="No high-risk countries" description="No countries have been added to the watch-list yet." /></td></tr>
              ) : rows.map(c => (
                <tr key={c.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{c.id}</td>
                  <td className="px-4 py-2 font-medium text-gray-800">{c.country_name ?? `#${c.country_id}`}</td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{c.country_code ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-600">{c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'}</td>
                  <td className="px-4 py-2 text-right">
                    <button onClick={() => { if (confirm(`Remove "${c.country_name ?? c.country_id}" from the high-risk list?`)) deleteMut.mutate(c.id) }}
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

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 p-4 pt-10"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900">Add High Risk Country</h2>
              <button onClick={() => setModalOpen(false)} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div>
              <label className="block text-xs text-gray-600 mb-1">Country *</label>
              <select value={countryId} onChange={e => setCountryId(e.target.value)}
                className="w-full px-3 py-2 border rounded text-sm bg-white">
                <option value="">{options.isLoading ? 'Loading…' : '— select a country —'}</option>
                {(options.data?.data ?? []).map(o => (
                  <option key={o.id} value={o.id}>{o.name}{o.code ? ` (${o.code})` : ''}</option>
                ))}
              </select>
            </div>
            <div className="flex gap-2 pt-2 border-t">
              <button onClick={() => {
                if (!countryId) { alert('Please select a country'); return }
                addMut.mutate(Number(countryId))
              }} disabled={addMut.isPending}
                className="flex-1 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                {addMut.isPending ? 'Adding…' : 'Add'}
              </button>
              <button onClick={() => setModalOpen(false)}
                className="px-4 py-2 border rounded text-sm hover:bg-gray-50">Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
