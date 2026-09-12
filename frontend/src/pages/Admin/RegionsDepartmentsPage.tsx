import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

export default function RegionsDepartmentsPage() {
  const [tab, setTab] = useState<'regions' | 'departments'>('regions')
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [name, setName] = useState('')
  const [saving, setSaving] = useState(false)

  const load = () => {
    setLoading(true)
    apiClient.get(`/${tab}`).then(r => setItems(r.data.data ?? [])).catch(() => setItems([])).finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [tab])

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await apiClient.put(`/${tab}/${editingId}`, { name })
      else await apiClient.post(`/${tab}`, { name })
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  const label = tab === 'regions' ? 'Region' : 'Department'

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Regions & Departments</h1>
        <button onClick={() => { setEditingId(null); setName(''); setModalOpen(true) }}
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add {label}</button>
      </div>
      <div className="flex gap-2">
        {(['regions', 'departments'] as const).map(t => (
          <button key={t} onClick={() => setTab(t)} className={`px-4 py-1.5 text-sm rounded-md font-medium capitalize ${tab === t ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{t}</button>
        ))}
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={3} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={3} className="p-0"><EmptyState compact title="Nothing here yet" description={`No ${tab} have been added yet.`} /></td></tr>}
            {items.map((i: any) => (
              <tr key={i.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 text-gray-500">{i.id}</td>
                <td className="px-4 py-3 font-medium">{i.name}</td>
                <td className="px-4 py-3 text-right"><button onClick={() => { setEditingId(i.id); setName(i.name); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-sm p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit' : 'Add'} {label}</h2>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Name</label><input value={name} onChange={e => setName(e.target.value)} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !name} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
