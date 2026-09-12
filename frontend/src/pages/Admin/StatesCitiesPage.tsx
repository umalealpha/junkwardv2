import { useState, useEffect } from 'react'
import EmptyState from '../../components/common/EmptyState'
import {
  fetchStates, createState, updateState,
  fetchCities, createCity, updateCity,
  StateRow, CityRow,
} from '../../api/geography'

type Tab = 'states' | 'cities'

export default function StatesCitiesPage() {
  const [tab, setTab] = useState<Tab>('states')

  // Shared list of provinces — drives the States tab table AND the Cities-tab
  // parent dropdown. Only ~29 rows, so we load them all once and filter client-side.
  const [allStates, setAllStates] = useState<StateRow[]>([])
  const [statesLoading, setStatesLoading] = useState(true)

  const loadStates = () => {
    setStatesLoading(true)
    fetchStates({ per_page: 500 })
      .then(r => setAllStates(r.data))
      .catch(() => setAllStates([]))
      .finally(() => setStatesLoading(false))
  }
  useEffect(() => { loadStates() }, [])

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-ink">States & Cities</h1>
        <p className="text-sm text-ink-muted">Manage the provinces and cities used by Risk Address forms.</p>
      </div>

      <div className="flex gap-2">
        {(['states', 'cities'] as const).map(t => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-1.5 text-sm rounded-md font-medium capitalize ${tab === t ? 'bg-blue-600 text-white' : 'bg-surface-2 text-ink-muted'}`}>
            {t === 'states' ? 'Provinces / States' : 'Cities'}
          </button>
        ))}
      </div>

      {tab === 'states'
        ? <StatesTab states={allStates} loading={statesLoading} onChanged={loadStates} />
        : <CitiesTab states={allStates} statesLoading={statesLoading} />}
    </div>
  )
}

// ─── States tab ────────────────────────────────────────────────────────────
function StatesTab({ states, loading, onChanged }: { states: StateRow[]; loading: boolean; onChanged: () => void }) {
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [name, setName] = useState('')
  const [saving, setSaving] = useState(false)

  const filtered = states.filter(s => s.name.toLowerCase().includes(search.trim().toLowerCase()))

  function openAdd() { setEditingId(null); setName(''); setModalOpen(true) }
  function openEdit(s: StateRow) { setEditingId(s.id); setName(s.name); setModalOpen(true) }

  async function handleSave() {
    setSaving(true)
    try {
      if (editingId) await updateState(editingId, { name: name.trim() })
      else await createState({ name: name.trim() })
      setModalOpen(false); onChanged()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to save province.') }
    finally { setSaving(false) }
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search provinces..."
          className="px-3 py-2 border border-line rounded-md text-sm w-64" />
        <button onClick={openAdd} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Province</button>
      </div>

      <div className="bg-surface shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface-2"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase"># Cities</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {loading && <tr><td colSpan={4} className="px-4 py-8 text-center text-ink-faint">Loading...</td></tr>}
            {!loading && filtered.length === 0 && <tr><td colSpan={4} className="p-0"><EmptyState compact title="No provinces" description="No provinces match your search." /></td></tr>}
            {!loading && filtered.map(s => (
              <tr key={s.id} className="hover:bg-surface-2">
                <td className="px-4 py-3 text-ink-muted">{s.id}</td>
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3 text-ink-muted">{s.cities_count ?? '—'}</td>
                <td className="px-4 py-3 text-right"><button onClick={() => openEdit(s)} className="text-sm text-blue-600 hover:underline">Edit</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {modalOpen && (
        <NameModal
          title={`${editingId ? 'Edit' : 'Add'} Province`}
          name={name} setName={setName} saving={saving}
          onCancel={() => setModalOpen(false)} onSave={handleSave}
        />
      )}
    </div>
  )
}

// ─── Cities tab ──────────────────────────────────────────────────────────────
function CitiesTab({ states, statesLoading }: { states: StateRow[]; statesLoading: boolean }) {
  const [stateId, setStateId] = useState<number | ''>('')
  const [items, setItems] = useState<CityRow[]>([])
  const [loading, setLoading] = useState(false)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [name, setName] = useState('')
  const [formStateId, setFormStateId] = useState<number | ''>('')
  const [saving, setSaving] = useState(false)

  const load = () => {
    if (!stateId) { setItems([]); return }
    setLoading(true)
    fetchCities({ state_id: stateId, search: search || undefined, page, per_page: 50 })
      .then(r => { setItems(r.data); setHasMore(r.meta?.has_more ?? false) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }
  useEffect(() => { setPage(1) }, [stateId, search])
  useEffect(() => { load() }, [stateId, search, page])

  function openAdd() { setEditingId(null); setName(''); setFormStateId(stateId); setModalOpen(true) }
  function openEdit(c: CityRow) { setEditingId(c.id); setName(c.name); setFormStateId(c.state_id); setModalOpen(true) }

  async function handleSave() {
    if (!formStateId) { alert('Please choose a province for this city.'); return }
    setSaving(true)
    try {
      const payload = { name: name.trim(), state_id: formStateId as number }
      if (editingId) await updateCity(editingId, payload)
      else await createCity(payload)
      setModalOpen(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to save city.') }
    finally { setSaving(false) }
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <select value={stateId} onChange={e => setStateId(e.target.value ? Number(e.target.value) : '')}
            className="px-3 py-2 border border-line rounded-md text-sm" disabled={statesLoading}>
            <option value="">{statesLoading ? 'Loading provinces...' : 'Select a province...'}</option>
            {states.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search cities..."
            className="px-3 py-2 border border-line rounded-md text-sm w-56" disabled={!stateId} />
        </div>
        <button onClick={openAdd} disabled={!stateId}
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:opacity-50">+ Add City</button>
      </div>

      {!stateId ? (
        <EmptyState title="Select a province" description="Choose a province above to view and manage its cities." />
      ) : (
        <>
          <div className="bg-surface shadow rounded-lg overflow-hidden">
            <table className="min-w-full divide-y divide-line text-sm">
              <thead className="bg-surface-2"><tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Province</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
              </tr></thead>
              <tbody className="divide-y divide-line">
                {loading && <tr><td colSpan={4} className="px-4 py-8 text-center text-ink-faint">Loading...</td></tr>}
                {!loading && items.length === 0 && <tr><td colSpan={4} className="p-0"><EmptyState compact title="No cities" description="No cities for this province yet." /></td></tr>}
                {!loading && items.map(c => (
                  <tr key={c.id} className="hover:bg-surface-2">
                    <td className="px-4 py-3 text-ink-muted">{c.id}</td>
                    <td className="px-4 py-3 font-medium">{c.name}</td>
                    <td className="px-4 py-3 text-ink-muted">{c.state_name ?? '—'}</td>
                    <td className="px-4 py-3 text-right"><button onClick={() => openEdit(c)} className="text-sm text-blue-600 hover:underline">Edit</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-end gap-2 text-sm">
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
              className="px-3 py-1.5 border border-line rounded-md disabled:opacity-50">Prev</button>
            <span className="text-ink-muted">Page {page}</span>
            <button onClick={() => setPage(p => p + 1)} disabled={!hasMore}
              className="px-3 py-1.5 border border-line rounded-md disabled:opacity-50">Next</button>
          </div>
        </>
      )}

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-sm p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit' : 'Add'} City</h2>
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-1">Province</label>
              <select value={formStateId} onChange={e => setFormStateId(e.target.value ? Number(e.target.value) : '')}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">Select a province...</option>
                {states.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-1">Name</label>
              <input value={name} onChange={e => setName(e.target.value)} maxLength={30}
                className="w-full px-3 py-2 border border-line rounded-md text-sm" />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !name.trim() || !formStateId}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Shared single-name modal (used by the States tab) ───────────────────────
function NameModal({ title, name, setName, saving, onCancel, onSave }: {
  title: string; name: string; setName: (v: string) => void; saving: boolean; onCancel: () => void; onSave: () => void
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
      onClick={(e) => { if (e.target === e.currentTarget) onCancel() }}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-sm p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
        <h2 className="text-lg font-bold">{title}</h2>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Name</label>
          <input value={name} onChange={e => setName(e.target.value)} maxLength={30}
            className="w-full px-3 py-2 border border-line rounded-md text-sm" autoFocus />
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <button onClick={onCancel} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
          <button onClick={onSave} disabled={saving || !name.trim()}
            className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
        </div>
      </div>
    </div>
  )
}
