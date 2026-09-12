import { useState, useEffect } from 'react'
import apiClient from '../../api/client'

const EMPTY = { vendorName: '', vendorLabel: '', email: '', telephone: '', status: 1 }

export default function PaymentVendorsPage() {
  const [items, setItems] = useState<any[]>([])
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)

  const load = () => { apiClient.get('/payment-vendors').then(r => setItems(r.data.data ?? [])).catch(() => {}) }
  useEffect(() => { load() }, [])

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await apiClient.put(`/payment-vendors/${editingId}`, form)
      else await apiClient.post('/payment-vendors', form)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Payment Vendors</h1>
        <button onClick={() => { setEditingId(null); setForm(EMPTY); setModalOpen(true) }} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Vendor</button>
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {items.map((v: any) => (
              <tr key={v.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{v.vendorName}</td>
                <td className="px-4 py-3 text-gray-500">{v.vendorLabel || '-'}</td>
                <td className="px-4 py-3 text-xs text-gray-500">{v.email} {v.telephone ? `| ${v.telephone}` : ''}</td>
                <td className="px-4 py-3 text-center"><span className={`inline-block w-3 h-3 rounded-full ${v.status ? 'bg-green-500' : 'bg-gray-300'}`} /></td>
                <td className="px-4 py-3 text-right"><button onClick={() => { setEditingId(v.id); setForm({ vendorName: v.vendorName, vendorLabel: v.vendorLabel || '', email: v.email || '', telephone: v.telephone || '', status: v.status }); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Vendor' : 'Add Vendor'}</h2>
            {[{k:'vendorName',l:'Name'},{k:'vendorLabel',l:'Label'},{k:'email',l:'Email'},{k:'telephone',l:'Phone'}].map(f => (
              <div key={f.k}><label className="block text-xs font-medium text-gray-500 mb-1">{f.l}</label><input value={(form as any)[f.k]} onChange={e => setForm({...form, [f.k]: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            ))}
            <div className="flex items-center gap-2"><input type="checkbox" checked={form.status === 1} onChange={e => setForm({...form, status: e.target.checked ? 1 : 0})} className="rounded" /><label className="text-sm">Active</label></div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
