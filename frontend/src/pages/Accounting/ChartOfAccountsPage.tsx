import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

interface Account { id: number; account_name: string; account_num: string; branch_name: string | null; branch_code: string | null }
const EMPTY = { account_name: '', account_num: '', branch_name: '', branch_code: '' }

export default function ChartOfAccountsPage() {
  const [accounts, setAccounts] = useState<Account[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)
  const [search, setSearch] = useState('')

  const load = () => {
    setLoading(true)
    const params: any = {}
    if (search) params.search = search
    apiClient.get('/accounts', { params }).then(r => setAccounts(r.data.data ?? [])).catch(() => {}).finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [search])

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await apiClient.put(`/accounts/${editingId}`, form)
      else await apiClient.post('/accounts', form)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Chart of Accounts</h1>
        <div className="flex gap-2">
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search..." className="px-3 py-1.5 border rounded-md text-sm w-48" />
          <button onClick={() => { setEditingId(null); setForm(EMPTY); setModalOpen(true) }} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Account</button>
        </div>
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account No</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && accounts.length === 0 && <tr><td colSpan={4} className="p-0"><EmptyState compact title="No accounts" description="No chart-of-accounts entries have been set up yet." /></td></tr>}
            {accounts.map(a => (
              <tr key={a.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{a.account_name}</td>
                <td className="px-4 py-3 font-mono text-xs text-blue-700">{a.account_num}</td>
                <td className="px-4 py-3 text-gray-500">{a.branch_name || '-'} {a.branch_code ? `(${a.branch_code})` : ''}</td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => { setEditingId(a.id); setForm({ account_name: a.account_name, account_num: a.account_num, branch_name: a.branch_name || '', branch_code: a.branch_code || '' }); setModalOpen(true) }} className="text-sm text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Account' : 'Add Account'}</h2>
            {[{k:'account_name',l:'Account Name'},{k:'account_num',l:'Account Number'},{k:'branch_name',l:'Branch Name'},{k:'branch_code',l:'Branch Code'}].map(f => (
              <div key={f.k}><label className="block text-xs font-medium text-gray-500 mb-1">{f.l}</label>
              <input value={(form as any)[f.k]} onChange={e => setForm({...form, [f.k]: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            ))}
            <div className="flex justify-end gap-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.account_name} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
