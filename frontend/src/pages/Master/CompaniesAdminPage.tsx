import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type Company = {
  id: number
  name: string
  parent_id: number | null
  parent_name?: string | null
  VAT_registration_number?: string | null
  company_registration_number?: string | null
  head_office_physical_address?: string | null
  postal_address?: string | null
  city?: string | null
  state?: string | null
  pincode?: string | null
  primary_email?: string | null
  secondary_email?: string | null
  broker_email?: string | null
  contact_person_number?: string | null
  status: number
}

type ParentOpt = { id: number; name: string }

type FormState = {
  id?: number
  name: string
  parent_id: string
  VAT_registration_number: string
  company_registration_number: string
  head_office_physical_address: string
  postal_address: string
  city: string
  state: string
  pincode: string
  primary_email: string
  secondary_email: string
  broker_email: string
  contact_person_number: string
  status: string
}

const EMPTY: FormState = {
  name: '', parent_id: '',
  VAT_registration_number: '', company_registration_number: '',
  head_office_physical_address: '', postal_address: '',
  city: '', state: '', pincode: '',
  primary_email: '', secondary_email: '', broker_email: '',
  contact_person_number: '', status: '1',
}

export default function CompaniesAdminPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const filters = {
    search: searchParams.get('search') || undefined,
    scope:  searchParams.get('scope')  || undefined, // '' | 'parent' | 'sub'
    parent_id: searchParams.get('parent_id') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['companies-admin', filters],
    queryFn: () => apiClient.get('/master/companies', { params: filters }).then(r => r.data),
  })

  // For the "Parent" dropdown inside the modal we want the list of top-level
  // companies. Policy-wizard picker is fine for this (limit 50, status=1).
  const parents = useQuery<{ data: ParentOpt[] }>({
    queryKey: ['companies-parents'],
    queryFn: () => apiClient.get('/lookups/companies').then(r => r.data),
    staleTime: 5 * 60_000,
  })

  const invalidate = () => { qc.invalidateQueries({ queryKey: ['companies-admin'] }); qc.invalidateQueries({ queryKey: ['companies-parents'] }) }

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload: any = {
        name: form.name.trim(),
        parent_id: form.parent_id ? Number(form.parent_id) : null,
        VAT_registration_number: form.VAT_registration_number || null,
        company_registration_number: form.company_registration_number || null,
        head_office_physical_address: form.head_office_physical_address || null,
        postal_address: form.postal_address || null,
        city: form.city || null,
        state: form.state || null,
        pincode: form.pincode || null,
        primary_email: form.primary_email || null,
        secondary_email: form.secondary_email || null,
        broker_email: form.broker_email || null,
        contact_person_number: form.contact_person_number || null,
        status: Number(form.status),
      }
      return form.id
        ? apiClient.put(`/master/companies/${form.id}`, payload)
        : apiClient.post('/master/companies', payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || err?.response?.data?.message || 'Save failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/master/companies/${id}`),
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

  function openEdit(c: Company) {
    setModal({
      open: true,
      form: {
        id: c.id,
        name: c.name ?? '',
        parent_id: c.parent_id ? String(c.parent_id) : '',
        VAT_registration_number: c.VAT_registration_number ?? '',
        company_registration_number: c.company_registration_number ?? '',
        head_office_physical_address: c.head_office_physical_address ?? '',
        postal_address: c.postal_address ?? '',
        city: c.city ?? '',
        state: c.state ?? '',
        pincode: c.pincode ?? '',
        primary_email: c.primary_email ?? '',
        secondary_email: c.secondary_email ?? '',
        broker_email: c.broker_email ?? '',
        contact_person_number: c.contact_person_number ?? '',
        status: String(c.status ?? 1),
      },
    })
  }

  const rows: Company[] = list.data?.data ?? []
  const meta = list.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Companies</h1>
          <p className="text-sm text-gray-500 mt-0.5">Corporate customers + their sub-companies. Used by organisation-entity policies.</p>
        </div>
        <button onClick={() => setModal({ open: true, form: { ...EMPTY } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Add Company</button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input type="text" placeholder="Name, VAT, reg#, email..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => setFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-80" />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Scope</label>
          <select value={filters.scope ?? ''} onChange={e => setFilter('scope', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-48 bg-white">
            <option value="">All</option>
            <option value="parent">Parent companies only</option>
            <option value="sub">Sub-companies only</option>
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
                <th className="px-4 py-3 text-left">Name</th>
                <th className="px-4 py-3 text-left">Parent</th>
                <th className="px-4 py-3 text-left">VAT</th>
                <th className="px-4 py-3 text-left">Reg #</th>
                <th className="px-4 py-3 text-left">City / State</th>
                <th className="px-4 py-3 text-left">Primary Email</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={9} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={9} className="p-0"><EmptyState compact title="No companies" description="No companies have been added yet." /></td></tr>
              ) : rows.map(c => (
                <tr key={c.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-500">{c.id}</td>
                  <td className="px-4 py-2 font-medium text-gray-800">{c.name}</td>
                  <td className="px-4 py-2 text-gray-600">{c.parent_name ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{c.VAT_registration_number ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{c.company_registration_number ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-600">{[c.city, c.state].filter(Boolean).join(', ') || '—'}</td>
                  <td className="px-4 py-2 text-gray-600">{c.primary_email || '—'}</td>
                  <td className="px-4 py-2">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${c.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {c.status === 1 ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => openEdit(c)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => { if (confirm(`Delete "${c.name}"?`)) deleteMut.mutate(c.id) }}
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
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900">{modal.form.id ? 'Edit' : 'Add'} Company</h2>
              <button onClick={() => setModal({ open: false, form: EMPTY })} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Name *</label>
                <input type="text" value={modal.form.name}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, name: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Parent Company (optional)</label>
                <select value={modal.form.parent_id}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, parent_id: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="">— top-level company —</option>
                  {(parents.data?.data ?? []).filter(p => p.id !== modal.form.id).map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Status</label>
                <select value={modal.form.status}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, status: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">VAT Registration Number</label>
                <input type="text" value={modal.form.VAT_registration_number}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, VAT_registration_number: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Company Registration Number</label>
                <input type="text" value={modal.form.company_registration_number}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, company_registration_number: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Head Office Physical Address</label>
                <input type="text" value={modal.form.head_office_physical_address}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, head_office_physical_address: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Postal Address</label>
                <input type="text" value={modal.form.postal_address}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, postal_address: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">City</label>
                <input type="text" value={modal.form.city}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, city: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">State</label>
                <input type="text" value={modal.form.state}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, state: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Pincode</label>
                <input type="text" value={modal.form.pincode}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, pincode: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Contact Number</label>
                <input type="text" value={modal.form.contact_person_number}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, contact_person_number: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Primary Email</label>
                <input type="email" value={modal.form.primary_email}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, primary_email: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Secondary Email</label>
                <input type="email" value={modal.form.secondary_email}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, secondary_email: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Broker Email</label>
                <input type="email" value={modal.form.broker_email}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, broker_email: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t">
              <button onClick={() => {
                if (!modal.form.name.trim()) { alert('Name is required'); return }
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
