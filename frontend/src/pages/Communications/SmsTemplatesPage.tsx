import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

const EMPTY = { name: '', content: '', status: 1 }

export default function SmsTemplatesPage() {
  const [tab, setTab] = useState<'sms' | 'email'>('sms')
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<any>(EMPTY)
  const [saving, setSaving] = useState(false)

  const endpoint = tab === 'sms' ? '/sms-templates' : '/email-templates'

  const load = () => {
    setLoading(true)
    apiClient.get(endpoint).then(r => setItems(r.data.data ?? [])).catch(() => setItems([])).finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [tab])

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await apiClient.put(`${endpoint}/${editingId}`, form)
      else await apiClient.post(endpoint, form)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">{tab === 'sms' ? 'SMS' : 'Email'} Templates</h1>
        <button onClick={() => { setEditingId(null); setForm(tab === 'sms' ? EMPTY : { name: '', subject: '', body: '', status: 1 }); setModalOpen(true) }}
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Template</button>
      </div>
      <div className="flex gap-2">
        {(['sms', 'email'] as const).map(t => (
          <button key={t} onClick={() => setTab(t)} className={`px-4 py-1.5 text-sm rounded-md font-medium uppercase ${tab === t ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{t}</button>
        ))}
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{tab === 'sms' ? 'Content' : 'Subject'}</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={4} className="p-0"><EmptyState compact title="No templates" description="No SMS templates have been configured yet." /></td></tr>}
            {items.map((t: any) => (
              <tr key={t.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{t.name}</td>
                <td className="px-4 py-3 text-xs text-gray-500 max-w-[400px] truncate">{tab === 'sms' ? t.content : t.subject}</td>
                <td className="px-4 py-3 text-center"><span className={`inline-block w-3 h-3 rounded-full ${t.status ? 'bg-green-500' : 'bg-gray-300'}`} /></td>
                <td className="px-4 py-3 text-right space-x-2">
                  <button onClick={() => {
                    setEditingId(t.id)
                    setForm(tab === 'sms' ? { name: t.name, content: t.content, status: t.status } : { name: t.name, subject: t.subject || '', body: t.body || '', status: t.status })
                    setModalOpen(true)
                  }} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={async () => { if (confirm('Delete?')) { await apiClient.delete(`${endpoint}/${t.id}`); load() } }} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit' : 'Add'} {tab.toUpperCase()} Template</h2>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Name</label><input value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            {tab === 'email' && <div><label className="block text-xs font-medium text-gray-500 mb-1">Subject</label><input value={form.subject || ''} onChange={e => setForm({...form, subject: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>}
            <div><label className="block text-xs font-medium text-gray-500 mb-1">{tab === 'sms' ? 'Content' : 'Body (HTML)'}</label>
              <textarea value={tab === 'sms' ? form.content : form.body} onChange={e => setForm({...form, [tab === 'sms' ? 'content' : 'body']: e.target.value})} rows={6} className="w-full px-3 py-2 border rounded-md text-sm font-mono" /></div>
            <div className="flex items-center gap-2"><input type="checkbox" checked={form.status === 1} onChange={e => setForm({...form, status: e.target.checked ? 1 : 0})} className="rounded" /><label className="text-sm">Active</label></div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.name} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
