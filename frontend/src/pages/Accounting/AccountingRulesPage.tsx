import { useState, useEffect } from 'react'
import apiClient from '../../api/client'

interface Rule { id: number; product_id: number; product_name: string | null; action: string; action_type: number; account_name: string; entry_type: string }
const EMPTY = { product_id: '', action: '', action_type: '', account_name: '', entry_type: 'Debit' }

export default function AccountingRulesPage() {
  const [rules, setRules] = useState<Rule[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)

  const load = () => { setLoading(true); apiClient.get('/accounting-rules').then(r => setRules(r.data.data ?? [])).catch(() => {}).finally(() => setLoading(false)) }
  useEffect(() => { load() }, [])

  async function handleSave() {
    setSaving(true)
    const payload = { ...form, product_id: Number(form.product_id), action_type: Number(form.action_type) }
    try {
      if (editingId) await apiClient.put(`/accounting-rules/${editingId}`, payload)
      else await apiClient.post('/accounting-rules', payload)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Accounting Rules</h1>
        <button onClick={() => { setEditingId(null); setForm(EMPTY); setModalOpen(true) }} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Rule</button>
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Entry Type</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {rules.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3">{r.product_name || `#${r.product_id}`}</td>
                <td className="px-4 py-3 font-medium">{r.action}</td>
                <td className="px-4 py-3">{r.account_name}</td>
                <td className="px-4 py-3 text-center">
                  <span className={`px-2 py-0.5 text-xs rounded-full font-medium ${r.entry_type === 'Debit' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`}>{r.entry_type}</span>
                </td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => { setEditingId(r.id); setForm({ product_id: String(r.product_id), action: r.action, action_type: String(r.action_type), account_name: r.account_name, entry_type: r.entry_type }); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Rule' : 'Add Rule'}</h2>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Product ID</label><input value={form.product_id} onChange={e => setForm({...form, product_id: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Action</label><input value={form.action} onChange={e => setForm({...form, action: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Action Type</label><input value={form.action_type} onChange={e => setForm({...form, action_type: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Account Name</label><input value={form.account_name} onChange={e => setForm({...form, account_name: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Entry Type</label>
              <select value={form.entry_type} onChange={e => setForm({...form, entry_type: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm">
                <option value="Debit">Debit</option><option value="Credit">Credit</option>
              </select></div>
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
