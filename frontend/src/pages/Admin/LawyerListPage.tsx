import { useState, useEffect } from 'react'
import { createPortal } from 'react-dom'
import {
  fetchLawyers, createLawyer, updateLawyer, deleteLawyer,
  type Lawyer, type LawyerPayload,
} from '../../api/lawyers'
import EmptyState from '../../components/common/EmptyState'

const CATEGORIES: { value: NonNullable<LawyerPayload['category']>; label: string }[] = [
  { value: '', label: '—' },
  { value: 'motor', label: 'Motor' },
  { value: 'non_motor', label: 'Non-Motor' },
  { value: 'both', label: 'Both' },
]

const EMPTY: LawyerPayload = {
  name: '', email: '', phone: '', company: '', category: '', is_active: true, notes: '',
}

const CATEGORY_LABEL: Record<string, string> = { motor: 'Motor', non_motor: 'Non-Motor', both: 'Both' }

export default function LawyerListPage() {
  const [items, setItems] = useState<Lawyer[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<LawyerPayload>(EMPTY)
  const [saving, setSaving] = useState(false)
  const [viewing, setViewing] = useState<Lawyer | null>(null)

  const load = () => {
    setLoading(true)
    fetchLawyers({ search: search || undefined, page, per_page: 25 })
      .then(r => { setItems(r.data); setHasMore(r.meta?.has_more ?? false) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [search, page]) // eslint-disable-line react-hooks/exhaustive-deps

  function openCreate() {
    setEditingId(null); setForm(EMPTY); setModalOpen(true)
  }
  function openEdit(a: Lawyer) {
    setEditingId(a.id)
    setForm({
      name: a.name, email: a.email ?? '', phone: a.phone ?? '', company: a.company ?? '',
      category: (a.category ?? '') as LawyerPayload['category'], is_active: a.is_active, notes: a.notes ?? '',
    })
    setModalOpen(true)
  }

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await updateLawyer(editingId, form)
      else await createLawyer(form)
      setModalOpen(false); load()
    } catch (e: any) {
      alert(e.response?.data?.message || 'Failed to save lawyer.')
    } finally { setSaving(false) }
  }

  async function handleDelete(a: Lawyer) {
    if (!confirm(`Delete lawyer "${a.name}"? This cannot be undone.`)) return
    try { await deleteLawyer(a.id); load() }
    catch (e: any) { alert(e.response?.data?.message || 'Failed to delete lawyer.') }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-ink">Lawyers</h1>
        <div className="flex gap-2">
          <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
            placeholder="Search name, email, company…" className="px-3 py-1.5 border border-line rounded-md text-sm w-60" />
          <button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Lawyer</button>
        </div>
      </div>

      <div className="bg-surface shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface-2"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Contact</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Company</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Category</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {loading && <tr><td colSpan={6} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={6} className="p-0"><EmptyState compact title="No lawyers yet" description="Add a lawyer to get started." /></td></tr>}
            {items.map(a => (
              <tr key={a.id} className="hover:bg-surface-2">
                <td className="px-4 py-3 font-medium">{a.name}</td>
                <td className="px-4 py-3 text-xs text-ink-muted"><div>{a.email || '—'}</div><div>{a.phone || ''}</div></td>
                <td className="px-4 py-3 text-ink-muted">{a.company || '—'}</td>
                <td className="px-4 py-3">{a.category ? <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">{CATEGORY_LABEL[a.category]}</span> : '—'}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 text-xs rounded-full ${a.is_active ? 'bg-green-100 text-green-700' : 'bg-surface-2 text-ink-muted'}`}>
                    {a.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td className="px-4 py-3 text-right space-x-3">
                  <button onClick={() => setViewing(a)} className="text-sm text-ink-muted hover:underline">View</button>
                  <button onClick={() => openEdit(a)} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => handleDelete(a)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex justify-between">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 text-sm border border-line rounded-md disabled:opacity-30">Previous</button>
        <span className="text-sm text-ink-muted">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 text-sm border border-line rounded-md disabled:opacity-30">Next</button>
      </div>

      {/* Create / Edit modal */}
      {modalOpen && createPortal(
        <div
          className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}
        >
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-lg p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Lawyer' : 'Add Lawyer'}</h2>
            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2"><label className="block text-xs font-medium text-ink-muted mb-1">Name *</label><input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-muted mb-1">Email</label><input value={form.email ?? ''} onChange={e => setForm({ ...form, email: e.target.value })} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-muted mb-1">Phone</label><input value={form.phone ?? ''} onChange={e => setForm({ ...form, phone: e.target.value })} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-muted mb-1">Company</label><input value={form.company ?? ''} onChange={e => setForm({ ...form, company: e.target.value })} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-muted mb-1">Category</label><select value={form.category ?? ''} onChange={e => setForm({ ...form, category: e.target.value as LawyerPayload['category'] })} className="w-full px-3 py-2 border border-line rounded-md text-sm">{CATEGORIES.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}</select></div>
              <div className="col-span-2"><label className="block text-xs font-medium text-ink-muted mb-1">Notes</label><textarea value={form.notes ?? ''} onChange={e => setForm({ ...form, notes: e.target.value })} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div className="col-span-2"><label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={!!form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} className="rounded" /> Active</label></div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.name.trim()} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* View modal */}
      {viewing && createPortal(
        <div
          className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setViewing(null) }}
        >
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{viewing.name}</h2>
            <dl className="text-sm space-y-2">
              <div className="flex justify-between gap-4"><dt className="text-ink-muted">Email</dt><dd className="text-ink text-right">{viewing.email || '—'}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-ink-muted">Phone</dt><dd className="text-ink text-right">{viewing.phone || '—'}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-ink-muted">Company</dt><dd className="text-ink text-right">{viewing.company || '—'}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-ink-muted">Category</dt><dd className="text-ink text-right">{viewing.category ? CATEGORY_LABEL[viewing.category] : '—'}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-ink-muted">Status</dt><dd className="text-ink text-right">{viewing.is_active ? 'Active' : 'Inactive'}</dd></div>
              {viewing.notes && <div><dt className="text-ink-muted mb-1">Notes</dt><dd className="text-ink whitespace-pre-wrap">{viewing.notes}</dd></div>}
            </dl>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setViewing(null)} className="px-4 py-2 text-sm border border-line rounded-md">Close</button>
              <button onClick={() => { openEdit(viewing); setViewing(null) }} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md">Edit</button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  )
}
