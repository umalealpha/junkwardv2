import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

const EMPTY = { name: '', email: '', mobile: '', vat: '' }

export default function RepairCentersPage() {
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)

  const load = () => { setLoading(true); apiClient.get('/repair-centers').then(r => setItems(r.data.data ?? [])).catch(() => {}).finally(() => setLoading(false)) }
  useEffect(() => { load() }, [])

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await apiClient.put(`/repair-centers/${editingId}`, form)
      else await apiClient.post('/repair-centers', form)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Repair Centers</h1>
        <button onClick={() => { setEditingId(null); setForm(EMPTY); setModalOpen(true) }} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Center</button>
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mobile</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">VAT</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No repair centers" description="No repair centers have been added yet." /></td></tr>}
            {items.map((r: any) => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{r.name}</td>
                <td className="px-4 py-3 text-gray-500">{r.email || '-'}</td>
                <td className="px-4 py-3 text-gray-500">{r.mobile || '-'}</td>
                <td className="px-4 py-3 text-gray-500">{r.vat || '-'}</td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => { setEditingId(r.id); setForm({ name: r.name, email: r.email || '', mobile: r.mobile || '', vat: r.vat || '' }); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Repair Center' : 'Add Repair Center'}</h2>
            {[{k:'name',l:'Name'},{k:'email',l:'Email'},{k:'mobile',l:'Mobile'},{k:'vat',l:'VAT No'}].map(f => (
              <div key={f.k}><label className="block text-xs font-medium text-gray-500 mb-1">{f.l}</label>
              <input value={(form as any)[f.k]} onChange={e=>setForm({...form,[f.k]:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            ))}
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={()=>setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving||!form.name} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving?'Saving...':'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
