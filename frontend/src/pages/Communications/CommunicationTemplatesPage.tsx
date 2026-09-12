import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

interface Template {
  id: number
  type: string
  name: string
  channels: string[] | null
  email_subject: string | null
  email_body: string | null
  sms_body: string | null
  whatsapp_body: string | null
  variables: string[] | null
  active: boolean
  created_at: string
}

const CHANNEL_OPTIONS = ['in_app', 'email', 'sms', 'whatsapp']
const CHANNEL_COLORS: Record<string, string> = {
  in_app: 'bg-blue-100 text-blue-700',
  email: 'bg-green-100 text-green-700',
  sms: 'bg-yellow-100 text-yellow-700',
  whatsapp: 'bg-emerald-100 text-emerald-700',
}

const EMPTY_FORM = {
  type: '', name: '', channels: ['in_app', 'email'] as string[],
  email_subject: '', email_body: '', sms_body: '', whatsapp_body: '',
  variables: '' as string, active: true,
}

export default function CommunicationTemplatesPage() {
  const [templates, setTemplates] = useState<Template[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [saving, setSaving] = useState(false)

  const load = () => {
    setLoading(true)
    apiClient.get('/communications/templates')
      .then(r => {
        setTemplates((r.data.data ?? []).map((t: any) => ({
          ...t,
          channels: typeof t.channels === 'string' ? JSON.parse(t.channels) : t.channels,
          variables: typeof t.variables === 'string' ? JSON.parse(t.variables) : t.variables,
        })))
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  function openCreate() {
    setEditingId(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(t: Template) {
    setEditingId(t.id)
    setForm({
      type: t.type,
      name: t.name,
      channels: t.channels ?? ['in_app', 'email'],
      email_subject: t.email_subject ?? '',
      email_body: t.email_body ?? '',
      sms_body: t.sms_body ?? '',
      whatsapp_body: t.whatsapp_body ?? '',
      variables: (t.variables ?? []).join(', '),
      active: t.active,
    })
    setModalOpen(true)
  }

  async function handleSave() {
    setSaving(true)
    const payload = {
      ...form,
      variables: form.variables ? form.variables.split(',').map(v => v.trim()).filter(Boolean) : [],
    }
    try {
      if (editingId) {
        await apiClient.put(`/communications/templates/${editingId}`, payload)
      } else {
        await apiClient.post('/communications/templates', payload)
      }
      setModalOpen(false)
      load()
    } catch (e: any) {
      alert(e.response?.data?.message || 'Save failed.')
    } finally { setSaving(false) }
  }

  async function handleDelete(id: number) {
    if (!confirm('Delete this template?')) return
    await apiClient.delete(`/communications/templates/${id}`)
    load()
  }

  function toggleChannel(ch: string) {
    setForm(prev => ({
      ...prev,
      channels: prev.channels.includes(ch)
        ? prev.channels.filter(c => c !== ch)
        : [...prev.channels, ch],
    }))
  }

  if (loading) return <div className="p-6"><div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-12 bg-gray-200 rounded" />)}</div></div>

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Notification Templates</h1>
          <p className="text-sm text-gray-500 mt-1">Configure notification content and channels per event type. Used by NotificationDispatcher for all system events.</p>
        </div>
        <button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">+ Add Template</button>
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channels</th>
              <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {templates.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No templates" description="Add one to configure notification routing." /></td></tr>}
            {templates.map(t => (
              <tr key={t.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs text-blue-700 bg-blue-50/50">{t.type}</td>
                <td className="px-4 py-3 font-medium text-gray-800">{t.name}</td>
                <td className="px-4 py-3">
                  <div className="flex gap-1 flex-wrap">
                    {(t.channels ?? []).map(ch => (
                      <span key={ch} className={`px-2 py-0.5 text-xs rounded-full font-medium ${CHANNEL_COLORS[ch] ?? 'bg-gray-100'}`}>{ch}</span>
                    ))}
                  </div>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-block w-3 h-3 rounded-full ${t.active ? 'bg-green-500' : 'bg-gray-300'}`} />
                </td>
                <td className="px-4 py-3 text-right space-x-2">
                  <button onClick={() => openEdit(t)} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => handleDelete(t.id)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 px-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Template' : 'Add Template'}</h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Event Type</label>
                <input value={form.type} onChange={e => setForm({...form, type: e.target.value})} disabled={!!editingId}
                  placeholder="e.g. policy_activated" className="w-full px-3 py-2 border rounded-md text-sm disabled:bg-gray-100" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Name</label>
                <input value={form.name} onChange={e => setForm({...form, name: e.target.value})}
                  placeholder="e.g. Policy Activated" className="w-full px-3 py-2 border rounded-md text-sm" />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Channels</label>
              <div className="flex gap-3">
                {CHANNEL_OPTIONS.map(ch => (
                  <label key={ch} className="flex items-center gap-1.5 text-sm cursor-pointer">
                    <input type="checkbox" checked={form.channels.includes(ch)} onChange={() => toggleChannel(ch)}
                      className="rounded border-gray-300 text-blue-600" />
                    <span>{ch}</span>
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Email Subject</label>
              <input value={form.email_subject} onChange={e => setForm({...form, email_subject: e.target.value})}
                className="w-full px-3 py-2 border rounded-md text-sm" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Email Body (HTML, use {'{{variable}}'} placeholders)</label>
              <textarea value={form.email_body} onChange={e => setForm({...form, email_body: e.target.value})}
                rows={4} className="w-full px-3 py-2 border rounded-md text-sm font-mono" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">SMS Body</label>
              <textarea value={form.sms_body} onChange={e => setForm({...form, sms_body: e.target.value})}
                rows={2} className="w-full px-3 py-2 border rounded-md text-sm" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Variables (comma-separated)</label>
              <input value={form.variables} onChange={e => setForm({...form, variables: e.target.value})}
                placeholder="policy_number, customer_name, amount" className="w-full px-3 py-2 border rounded-md text-sm" />
            </div>
            <div className="flex items-center gap-2">
              <input type="checkbox" checked={form.active} onChange={e => setForm({...form, active: e.target.checked})}
                className="rounded border-gray-300 text-blue-600" />
              <label className="text-sm text-gray-600">Active</label>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.type || !form.name}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
