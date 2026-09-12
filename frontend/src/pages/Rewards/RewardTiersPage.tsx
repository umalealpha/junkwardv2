import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import { fmtPula } from '../../utils/format'

const EMPTY = { name: '', label: '', description: '', level_point: '', status: 1 }

export default function RewardTiersPage() {
  const [tiers, setTiers] = useState<any[]>([])
  const [benefits, setBenefits] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [tab, setTab] = useState<'tiers' | 'benefits'>('tiers')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    setLoading(true)
    Promise.all([apiClient.get('/reward-tiers'), apiClient.get('/benefits')])
      .then(([t, b]) => { setTiers(t.data.data ?? []); setBenefits(b.data.data ?? []) })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  async function handleSave() {
    setSaving(true)
    const payload = { ...form, level_point: Number(form.level_point) || 0 }
    try {
      if (tab === 'tiers') {
        if (editingId) await apiClient.put(`/reward-tiers/${editingId}`, payload)
        else await apiClient.post('/reward-tiers', payload)
      } else {
        await apiClient.post('/benefits', { tag: form.name, type: form.label, point: Number(form.level_point) || 0, status: form.status })
      }
      setModalOpen(false)
      const [t, b] = await Promise.all([apiClient.get('/reward-tiers'), apiClient.get('/benefits')])
      setTiers(t.data.data ?? []); setBenefits(b.data.data ?? [])
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  if (loading) return <div className="p-6"><div className="animate-pulse space-y-3">{[1,2,3].map(i=><div key={i} className="h-10 bg-gray-200 rounded" />)}</div></div>

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Customer Rewards</h1>
        <button onClick={() => { setEditingId(null); setForm(EMPTY); setModalOpen(true) }} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add {tab === 'tiers' ? 'Tier' : 'Benefit'}</button>
      </div>
      <div className="flex gap-2">
        {(['tiers', 'benefits'] as const).map(t => (
          <button key={t} onClick={() => setTab(t)} className={`px-4 py-1.5 text-sm rounded-md font-medium ${tab === t ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{t === 'tiers' ? 'Reward Tiers' : 'Benefits'}</button>
        ))}
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        {tab === 'tiers' ? (
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Level Points</th>
              <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody className="divide-y divide-gray-200">
              {tiers.map((t: any) => (
                <tr key={t.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium">{t.name}</td>
                  <td className="px-4 py-3 text-gray-500">{t.label || '-'}</td>
                  <td className="px-4 py-3 text-right font-bold text-blue-700">{t.level_point}</td>
                  <td className="px-4 py-3 text-center"><span className={`inline-block w-3 h-3 rounded-full ${t.status ? 'bg-green-500' : 'bg-gray-300'}`} /></td>
                  <td className="px-4 py-3 text-right"><button onClick={() => { setEditingId(t.id); setForm({ name: t.name, label: t.label || '', description: t.description || '', level_point: String(t.level_point), status: t.status }); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tag</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Points</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
              <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr></thead>
            <tbody className="divide-y divide-gray-200">
              {benefits.map((b: any) => (
                <tr key={b.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium">{b.tag}</td>
                  <td className="px-4 py-3 text-gray-500">{b.type || '-'}</td>
                  <td className="px-4 py-3 text-right font-bold text-blue-700">{b.point}</td>
                  <td className="px-4 py-3 text-right">{fmtPula(b.price)}</td>
                  <td className="px-4 py-3 text-center"><span className={`inline-block w-3 h-3 rounded-full ${b.status ? 'bg-green-500' : 'bg-gray-300'}`} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit' : 'Add'} {tab === 'tiers' ? 'Tier' : 'Benefit'}</h2>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Name / Tag</label><input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Label / Type</label><input value={form.label} onChange={e=>setForm({...form,label:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Points Required</label><input type="number" value={form.level_point} onChange={e=>setForm({...form,level_point:e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
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
