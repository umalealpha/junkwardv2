import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

type WaTemplate = {
  id?: string
  name: string
  status?: string
  category?: string
  language?: string
  components?: Array<{ type: string; text?: string; example?: any }>
  rejected_reason?: string
  quality_score?: { score?: string }
}

const STATUS_COLORS: Record<string, string> = {
  APPROVED: 'bg-green-100 text-green-700',
  PENDING:  'bg-yellow-100 text-yellow-700',
  REJECTED: 'bg-red-100 text-red-700',
  DISABLED: 'bg-gray-200 text-gray-600',
  PAUSED:   'bg-orange-100 text-orange-700',
}

const EMPTY_FORM = {
  name: '',
  category: 'UTILITY',
  language: 'en',
  body: '',
}

const TEST_FORM_EMPTY = { to: '', body_params: '' }

export default function WhatsAppTemplatesPage() {
  const [items, setItems] = useState<WaTemplate[]>([])
  const [loading, setLoading] = useState(true)
  const [configured, setConfigured] = useState(true)
  const [serverMessage, setServerMessage] = useState<string>('')
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState(EMPTY_FORM)
  const [saving, setSaving] = useState(false)
  const [testOpenFor, setTestOpenFor] = useState<WaTemplate | null>(null)
  const [testForm, setTestForm] = useState(TEST_FORM_EMPTY)
  const [testing, setTesting] = useState(false)

  const load = () => {
    setLoading(true)
    apiClient.get('/whatsapp-templates')
      .then(r => {
        setItems(r.data.data ?? [])
        setConfigured(r.data.configured !== false)
        setServerMessage(r.data.message ?? '')
      })
      .catch(e => {
        setItems([])
        setServerMessage(e.response?.data?.message ?? 'Failed to load')
      })
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [])

  async function handleCreate() {
    setSaving(true)
    try {
      const components = [{ type: 'BODY', text: form.body }]
      await apiClient.post('/whatsapp-templates', {
        name: form.name.trim().toLowerCase(),
        category: form.category,
        language: form.language,
        components,
      })
      setModalOpen(false); setForm(EMPTY_FORM); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to create template') }
    finally { setSaving(false) }
  }

  async function handleDelete(name: string) {
    if (!confirm(`Delete template "${name}"? This affects all language variants.`)) return
    try {
      await apiClient.delete(`/whatsapp-templates/${encodeURIComponent(name)}`)
      load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to delete') }
  }

  async function handleTest() {
    if (!testOpenFor) return
    setTesting(true)
    try {
      const params = testForm.body_params.split('|').map(s => s.trim()).filter(Boolean)
      const r = await apiClient.post('/whatsapp-templates/test', {
        to: testForm.to.replace(/\D/g, ''),
        name: testOpenFor.name,
        language: testOpenFor.language ?? 'en',
        body_params: params,
      })
      alert(`Sent. Message id: ${r.data.data?.messages?.[0]?.id ?? '(none)'}`)
      setTestOpenFor(null); setTestForm(TEST_FORM_EMPTY)
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to send test') }
    finally { setTesting(false) }
  }

  function bodyText(t: WaTemplate): string {
    return (t.components ?? []).find(c => c.type?.toUpperCase() === 'BODY')?.text ?? ''
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">WhatsApp Templates</h1>
          <p className="text-sm text-gray-500 mt-1">
            Approved templates bypass the 24-hour customer-service window — needed for unsolicited alerts (anomaly notifications, daily briefs).
          </p>
        </div>
        <button
          onClick={() => { setForm(EMPTY_FORM); setModalOpen(true) }}
          disabled={!configured}
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:bg-gray-300"
        >+ Submit New Template</button>
      </div>

      {!configured && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4 text-sm text-yellow-800">
          <div className="font-medium mb-1">Service not configured</div>
          <div>{serverMessage || 'Set WHATSAPP_BUSINESS_ACCOUNT_ID env on the backend task definition. Find your WABA ID in Meta Business Manager → WhatsApp Accounts → Account ID.'}</div>
        </div>
      )}

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lang</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Body</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">Loading from Meta…</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={6} className="p-0"><EmptyState compact title="No templates registered" description="No WhatsApp templates have been registered yet." /></td></tr>}
            {items.map((t) => (
              <tr key={`${t.name}-${t.language}`} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium font-mono text-xs">{t.name}</td>
                <td className="px-4 py-3 text-xs text-gray-600">{t.category ?? '—'}</td>
                <td className="px-4 py-3 text-xs text-gray-600">{t.language ?? '—'}</td>
                <td className="px-4 py-3 text-xs text-gray-700 max-w-[420px] truncate" title={bodyText(t)}>{bodyText(t)}</td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[t.status ?? ''] ?? 'bg-gray-100 text-gray-600'}`}>
                    {t.status ?? '—'}
                  </span>
                  {t.rejected_reason && <div className="text-[10px] text-red-600 mt-1">{t.rejected_reason}</div>}
                </td>
                <td className="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                  {t.status === 'APPROVED' && (
                    <button onClick={() => { setTestOpenFor(t); setTestForm(TEST_FORM_EMPTY) }} className="text-sm text-blue-600 hover:underline">Test</button>
                  )}
                  <button onClick={() => handleDelete(t.name)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">Submit WhatsApp Template</h2>
            <p className="text-xs text-gray-500">
              Submitted templates start as <span className="font-mono">PENDING</span>. Meta auto-reviews UTILITY templates within minutes.
              Once <span className="font-mono">APPROVED</span> they can be sent to anyone, regardless of the 24h window.
              Use <code className="bg-gray-100 px-1 rounded">{'{{1}}'}</code>, <code className="bg-gray-100 px-1 rounded">{'{{2}}'}</code> etc for placeholders.
            </p>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Name (lowercase, underscores only)</label>
                <input
                  value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_') })}
                  placeholder="graphite_anomaly_alert"
                  className="w-full px-3 py-2 border rounded-md text-sm font-mono"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Category</label>
                <select value={form.category} onChange={e => setForm({ ...form, category: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm">
                  <option value="UTILITY">UTILITY (transactional / alerts)</option>
                  <option value="MARKETING">MARKETING (promotional)</option>
                  <option value="AUTHENTICATION">AUTHENTICATION (OTP)</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Language</label>
                <input value={form.language} onChange={e => setForm({ ...form, language: e.target.value })} placeholder="en" className="w-full px-3 py-2 border rounded-md text-sm" />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Body text</label>
              <textarea
                value={form.body}
                onChange={e => setForm({ ...form, body: e.target.value })}
                rows={6}
                placeholder={'⚠️ Graphite anomaly: {{1}}\n\nSeverity: {{2}}\nTime: {{3}}'}
                className="w-full px-3 py-2 border rounded-md text-sm font-mono"
              />
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm rounded-md bg-gray-100 hover:bg-gray-200">Cancel</button>
              <button
                onClick={handleCreate}
                disabled={saving || !form.name || !form.body}
                className="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:bg-gray-300"
              >{saving ? 'Submitting…' : 'Submit to Meta'}</button>
            </div>
          </div>
        </div>
      )}

      {testOpenFor && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setTestOpenFor(null) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">Send test — <span className="font-mono text-sm">{testOpenFor.name}</span></h2>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Recipient (with country code, no +)</label>
              <input value={testForm.to} onChange={e => setTestForm({ ...testForm, to: e.target.value })} placeholder="917276312582" className="w-full px-3 py-2 border rounded-md text-sm font-mono" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Body parameters (pipe-separated, in order)</label>
              <input value={testForm.body_params} onChange={e => setTestForm({ ...testForm, body_params: e.target.value })} placeholder="DPO ServiceRef mismatch | high | 14:32" className="w-full px-3 py-2 border rounded-md text-sm" />
              <div className="text-[10px] text-gray-500 mt-1">For "{'{{1}}'}, {'{{2}}'}, {'{{3}}'}" → "value1 | value2 | value3"</div>
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setTestOpenFor(null)} className="px-4 py-2 text-sm rounded-md bg-gray-100 hover:bg-gray-200">Cancel</button>
              <button onClick={handleTest} disabled={testing || !testForm.to} className="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:bg-gray-300">
                {testing ? 'Sending…' : 'Send test'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
