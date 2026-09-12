import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

const TYPES = ['Assessor', 'Repair Shop', 'Salvage', 'Legal', 'Medical', 'Other']
const EMPTY = { supplierName: '', supplierType: '', email: '', telephone: '', supplierLocation: '', vat_no: '', account_no: '', address: '' }

export default function SupplierListPage() {
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  const load = () => {
    setLoading(true)
    const params: any = { page, per_page: 25 }
    if (search) params.search = search
    apiClient.get('/suppliers', { params })
      .then(r => { setItems(r.data.data ?? []); setHasMore(r.data.meta?.has_more ?? false) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [search, page])

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await apiClient.put(`/suppliers/${editingId}`, form)
      else await apiClient.post('/suppliers', form)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Suppliers</h1>
        <div className="flex gap-2">
          <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} placeholder="Search..." className="px-3 py-1.5 border rounded-md text-sm w-48" />
          <button onClick={() => { setEditingId(null); setForm(EMPTY); setModalOpen(true) }} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Supplier</button>
        </div>
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No suppliers" description="No suppliers have been added yet." /></td></tr>}
            {items.map((s: any) => (
              <tr key={s.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3">{s.type ? <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">{s.type}</span> : '-'}</td>
                <td className="px-4 py-3 text-xs text-gray-500"><div>{s.email}</div><div>{s.phone}</div></td>
                <td className="px-4 py-3 text-xs text-gray-500">{s.location || '-'}</td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => { setEditingId(s.id); setForm({ supplierName: s.name, supplierType: s.type || '', email: s.email || '', telephone: s.phone || '', supplierLocation: s.location || '', vat_no: s.vatNo || '', account_no: s.accountNo || '', address: s.address || '' }); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-between"><button disabled={page<=1} onClick={()=>setPage(p=>p-1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button><span className="text-sm text-gray-500">Page {page}</span><button disabled={!hasMore} onClick={()=>setPage(p=>p+1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button></div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Supplier' : 'Add Supplier'}</h2>
            <div className="grid grid-cols-2 gap-3">
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Name</label><input value={form.supplierName} onChange={e=>setForm({...form,supplierName:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Type</label><select value={form.supplierType} onChange={e=>setForm({...form,supplierType:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm"><option value="">--</option>{TYPES.map(t=><option key={t} value={t}>{t}</option>)}</select></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Email</label><input value={form.email} onChange={e=>setForm({...form,email:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Phone</label><input value={form.telephone} onChange={e=>setForm({...form,telephone:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Location</label><input value={form.supplierLocation} onChange={e=>setForm({...form,supplierLocation:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">VAT No</label><input value={form.vat_no} onChange={e=>setForm({...form,vat_no:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={()=>setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving||!form.supplierName} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving?'Saving...':'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
