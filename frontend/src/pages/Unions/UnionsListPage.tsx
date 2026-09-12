import { useState, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import Modal from '../../components/common/Modal'
import {
  fetchUnions, createUnion, updateUnion, setUnionStatus, deleteUnion, fetchLegalProducts,
  type UnionListRow, type UnionPayload, type LegalProduct,
} from '../../api/unions'

type FormState = {
  id?: number
  union_name: string
  union_code: string
  description: string
  policy_number: string
  /** product_plans.id of the Legal Insurance product — drives the premium. */
  plan_id: string
  effective_date: string
  expiry_date: string
  contact_person: string
  contact_number: string
  email: string
  address: string
  status: string
}

const EMPTY: FormState = {
  union_name: '', union_code: '', description: '', policy_number: '',
  plan_id: '', effective_date: '', expiry_date: '',
  contact_person: '', contact_number: '', email: '', address: '', status: '1',
}

const money = (n: number) =>
  'P' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export default function UnionsListPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const searchRef = useRef<HTMLInputElement>(null)
  const runSearch = () => setSearch(searchRef.current?.value.trim() ?? '')
  const [status, setStatus] = useState('')
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })

  const list = useQuery({
    queryKey: ['unions', { search, status }],
    queryFn: () => fetchUnions({ search: search || undefined, status: status || undefined }),
  })

  // Legal Insurance products (product 4 plans) — the union → product mapping.
  // Loaded with the page rather than on modal open so the dropdown is populated
  // the moment Register Union is clicked.
  const legalProducts = useQuery({
    queryKey: ['legal-products'],
    queryFn: fetchLegalProducts,
    staleTime: 5 * 60 * 1000,
  })

  const products: LegalProduct[] = legalProducts.data ?? []
  const selectedProduct = (id: string) => products.find(p => String(p.id) === id) ?? null

  const invalidate = () => qc.invalidateQueries({ queryKey: ['unions'] })

  const saveMut = useMutation({
    mutationFn: (form: FormState) => {
      const payload: UnionPayload = {
        union_name: form.union_name.trim(),
        union_code: form.union_code.trim().toUpperCase(),
        description: form.description || null,
        policy_number: form.policy_number.trim() || null,
        // The product carries the price; the backend derives monthly_premium
        // from it, so this screen never posts an amount.
        plan_id: Number(form.plan_id),
        effective_date: form.effective_date || null,
        expiry_date: form.expiry_date || null,
        contact_person: form.contact_person || null,
        contact_number: form.contact_number || null,
        email: form.email || null,
        address: form.address || null,
        status: Number(form.status),
      }
      return form.id ? updateUnion(form.id, payload) : createUnion(payload)
    },
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || err?.response?.data?.message || 'Save failed'),
  })

  const statusMut = useMutation({
    mutationFn: (u: UnionListRow) => setUnionStatus(u.id, u.status === 1 ? 0 : 1),
    onSuccess: invalidate,
    onError: (err: any) => alert(err?.response?.data?.error || 'Status change failed'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => deleteUnion(id),
    onSuccess: invalidate,
    onError: (err: any) => alert(err?.response?.data?.error || 'Delete failed'),
  })

  function openEdit(u: UnionListRow) {
    // Full detail isn't in the list row; prime the editable fields we have and
    // let the user adjust. (Code + policy number are immutable and read-only.)
    setModal({
      open: true,
      form: {
        id: u.id,
        union_name: u.union_name ?? '',
        union_code: u.union_code ?? '',
        description: '',
        policy_number: u.policy_number ?? '',
        plan_id: u.plan_id != null ? String(u.plan_id) : '',
        effective_date: u.effective_date ?? '',
        expiry_date: u.expiry_date ?? '',
        contact_person: '', contact_number: '', email: '', address: '',
        status: String(u.status ?? 1),
      },
    })
  }

  const rows = list.data ?? []
  const editing = !!modal.form.id

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Unions</h1>
          <p className="text-sm text-gray-500 mt-0.5">Legal Insurance Group Schemes — one group policy &amp; member register per union.</p>
        </div>
        <button onClick={() => setModal({ open: true, form: { ...EMPTY } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">+ Register Union</button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Search</label>
          <div className="flex gap-1">
            <input ref={searchRef} type="text" placeholder="Name, code or policy no..."
              defaultValue={search}
              onKeyDown={e => { if (e.key === 'Enter') runSearch() }}
              className="px-3 py-1.5 border border-line rounded-md text-sm w-72 bg-surface text-ink" />
            <button type="button" onClick={runSearch}
              className="px-3 py-1.5 border border-line rounded-md text-sm text-ink hover:bg-surface-2">Search</button>
          </div>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select value={status} onChange={e => setStatus(e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-40 bg-white">
            <option value="">All</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {list.isFetching && !list.isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center"><LoadingSpinner size="md" /></div>
        )}
        <DualScrollTable>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
              <tr>
                <th className="px-4 py-3 text-left">Union</th>
                <th className="px-4 py-3 text-left">Code</th>
                <th className="px-4 py-3 text-left">Group Policy</th>
                <th className="px-4 py-3 text-left">Legal Product</th>
                <th className="px-4 py-3 text-right">Premium / member</th>
                <th className="px-4 py-3 text-right">Active</th>
                <th className="px-4 py-3 text-right">Total</th>
                <th className="px-4 py-3 text-right">Monthly Premium</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {list.isLoading ? (
                <tr><td colSpan={10} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={10} className="p-0"><EmptyState compact title="No unions" description="Register a union to create its group scheme." /></td></tr>
              ) : rows.map((u: UnionListRow) => (
                <tr key={u.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 font-medium text-gray-800 cursor-pointer" onClick={() => navigate(`/unions/${u.id}`)}>{u.union_name}</td>
                  <td className="px-4 py-2 text-gray-500 font-mono text-xs">{u.union_code ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-600 font-mono text-xs">{u.policy_number ?? '—'}</td>
                  <td className="px-4 py-2 text-gray-600 text-xs">{u.plan_name ?? '—'}</td>
                  <td className="px-4 py-2 text-right text-gray-600">{money(u.monthly_premium)}</td>
                  <td className="px-4 py-2 text-right text-gray-600">{u.active_members}</td>
                  <td className="px-4 py-2 text-right text-gray-600">{u.total_members}</td>
                  <td className="px-4 py-2 text-right font-medium text-gray-800">{money(u.total_monthly_premium)}</td>
                  <td className="px-4 py-2">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${u.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {u.status === 1 ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                    <button onClick={() => navigate(`/unions/${u.id}`)} className="text-blue-600 hover:underline text-xs">Manage</button>
                    <button onClick={() => openEdit(u)} className="text-blue-600 hover:underline text-xs">Edit</button>
                    <button onClick={() => statusMut.mutate(u)} disabled={statusMut.isPending}
                      className={`text-xs hover:underline ${u.status === 1 ? 'text-amber-600' : 'text-green-600'} disabled:opacity-50`}>
                      {u.status === 1 ? 'Deactivate' : 'Activate'}
                    </button>
                    <button onClick={() => { if (confirm(`Delete "${u.union_name}"? (blocked if it has members)`)) deleteMut.mutate(u.id) }}
                      className="text-red-600 hover:underline text-xs">Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </DualScrollTable>
      </div>

      {modal.open && (
        <Modal open onClose={() => setModal({ open: false, form: EMPTY })} size="2xl"
          title={`${editing ? 'Edit' : 'Register'} Union`}
          footer={
            <>
              <button onClick={() => setModal({ open: false, form: EMPTY })}
                className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Cancel</button>
              <button onClick={() => {
                if (!modal.form.union_name.trim()) { alert('Union name is required'); return }
                if (!editing && !/^[A-Za-z0-9]+$/.test(modal.form.union_code.trim())) { alert('Union code must be letters/numbers only'); return }
                if (!modal.form.plan_id) { alert('Select a Legal Insurance product'); return }
                if (modal.form.effective_date && modal.form.expiry_date && modal.form.expiry_date < modal.form.effective_date) {
                  alert('Expiry date cannot be before the effective date'); return
                }
                saveMut.mutate(modal.form)
              }} disabled={saveMut.isPending}
                className="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                {saveMut.isPending ? 'Saving…' : (editing ? 'Update Union' : 'Register Union + Group Policy')}
              </button>
            </>
          }>
          <div className="space-y-3">
            {!editing && (
              <p className="text-xs text-gray-500">
                Registering a union auto-creates its Legal Insurance group policy
                (<span className="font-mono">MIS&lt;code&gt;</span>, e.g. <span className="font-mono">BONU</span> → <span className="font-mono">MISBONU</span>).
                Leave Group Policy Number blank to auto-mint, or enter one to override.
              </p>
            )}
            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Union Name *</label>
                <input type="text" value={modal.form.union_name}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, union_name: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Union Code * <span className="text-gray-400">(A–Z, 0–9)</span></label>
                <input type="text" value={modal.form.union_code} disabled={editing}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, union_code: e.target.value.toUpperCase() } }))}
                  placeholder="BONU"
                  className="w-full px-3 py-2 border rounded text-sm font-mono uppercase disabled:bg-gray-100" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Group Policy Number {editing ? '' : '(optional override)'}</label>
                <input type="text" value={modal.form.policy_number} disabled={editing}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, policy_number: e.target.value.toUpperCase() } }))}
                  placeholder="auto: MIS<code>"
                  className="w-full px-3 py-2 border rounded text-sm font-mono disabled:bg-gray-100" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Description</label>
                <input type="text" value={modal.form.description}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, description: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              {/* Legal Insurance product — replaces the hand-typed monthly
                  premium. The plan's price IS the per-member premium, so the
                  amount is shown read-only underneath rather than captured. */}
              <div>
                <label className="block text-xs text-gray-600 mb-1">Legal Insurance Product *</label>
                <select value={modal.form.plan_id}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, plan_id: e.target.value } }))}
                  disabled={legalProducts.isLoading}
                  className="w-full px-3 py-2 border rounded text-sm bg-white disabled:bg-gray-100">
                  <option value="">
                    {legalProducts.isLoading ? 'Loading products…' : 'Select a product'}
                  </option>
                  {products.map(p => (
                    <option key={p.id} value={p.id}>{p.name} — {money(p.premium)}</option>
                  ))}
                </select>
                {legalProducts.isError ? (
                  <p className="text-xs text-red-600 mt-1">
                    Could not load Legal Insurance products. Reload the page and try again.
                  </p>
                ) : !legalProducts.isLoading && products.length === 0 ? (
                  <p className="text-xs text-amber-600 mt-1">
                    No active Legal Insurance products are configured.
                  </p>
                ) : (
                  <p className="text-xs text-gray-500 mt-1">
                    {selectedProduct(modal.form.plan_id)
                      ? `Premium / member: ${money(selectedProduct(modal.form.plan_id)!.premium)} per month`
                      : 'The selected product sets the per-member monthly premium.'}
                  </p>
                )}
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Status</label>
                <select value={modal.form.status}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, status: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Effective Date</label>
                <input type="date" value={modal.form.effective_date}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, effective_date: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Expiry Date</label>
                <input type="date" value={modal.form.expiry_date}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, expiry_date: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Contact Person</label>
                <input type="text" value={modal.form.contact_person}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, contact_person: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Contact Number</label>
                <input type="text" value={modal.form.contact_number}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, contact_number: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Email</label>
                <input type="email" value={modal.form.email}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, email: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Physical Address</label>
                <input type="text" value={modal.form.address}
                  onChange={e => setModal(m => ({ ...m, form: { ...m.form, address: e.target.value } }))}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}
