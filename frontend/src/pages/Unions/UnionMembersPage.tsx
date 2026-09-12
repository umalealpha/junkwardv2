import { useState, useRef } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import Modal from '../../components/common/Modal'
import {
  fetchUnions, fetchMembers, createMember, updateMember, removeMember,
  downloadMemberTemplate, exportMembersFile, importMembers, legalClaimPath,
  type UnionMember, type MemberPayload, type ImportResult,
} from '../../api/unions'

type FormState = {
  id?: number
  id_number: string
  member_name: string
  member_type: string
  date_of_birth: string
  gender: string
  contact_number: string
  email: string
  nationality: string
  status: string
}

const EMPTY: FormState = {
  id_number: '', member_name: '', member_type: '', date_of_birth: '', gender: '',
  contact_number: '', email: '', nationality: '', status: '1',
}

const genderLabel = (g: number | null) => (g === 1 ? 'Male' : g === 0 ? 'Female' : '—')

export default function UnionMembersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const unionId = searchParams.get('union') || ''
  const [search, setSearch] = useState('')
  const searchRef = useRef<HTMLInputElement>(null)
  const runSearch = () => { setPage(1); setSearch(searchRef.current?.value.trim() ?? '') }
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [modal, setModal] = useState<{ open: boolean; form: FormState }>({ open: false, form: EMPTY })
  const [importOpen, setImportOpen] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const unionsQ = useQuery({ queryKey: ['unions', {}], queryFn: () => fetchUnions() })
  const unions = unionsQ.data ?? []
  const selectedUnion = unions.find(u => String(u.id) === unionId)

  const membersQ = useQuery({
    queryKey: ['union-members', unionId, { search, status, page }],
    queryFn: () => fetchMembers(unionId, { search: search || undefined, status: status || undefined, page, per_page: 25 }),
    enabled: !!unionId,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['union-members', unionId] })

  const toPayload = (f: FormState): MemberPayload => ({
    id_number: f.id_number.trim(),
    member_name: f.member_name.trim(),
    member_type: f.member_type.trim(),
    date_of_birth: f.date_of_birth || null,
    gender: f.gender || null,
    contact_number: f.contact_number.trim(),
    email: f.email || null,
    nationality: f.nationality.trim(),
    status: Number(f.status),
  })

  const saveMut = useMutation({
    mutationFn: (f: FormState) => f.id
      ? updateMember(unionId, f.id, toPayload(f))
      : createMember(unionId, toPayload(f)),
    onSuccess: () => { invalidate(); setModal({ open: false, form: EMPTY }) },
    onError: (err: any) => alert(err?.response?.data?.error || err?.response?.data?.message || 'Save failed'),
  })

  const removeMut = useMutation({
    mutationFn: (id: number) => removeMember(unionId, id),
    onSuccess: invalidate,
    onError: (err: any) => alert(err?.response?.data?.error || 'Remove failed'),
  })

  const statusMut = useMutation({
    mutationFn: (m: UnionMember) => updateMember(unionId, m.id, { status: m.status === 1 ? 0 : 1 } as Partial<MemberPayload>),
    onSuccess: invalidate,
    onError: (err: any) => alert(err?.response?.data?.error || 'Status change failed'),
  })

  function setUnion(id: string) {
    const next = new URLSearchParams(searchParams)
    id ? next.set('union', id) : next.delete('union')
    setSearchParams(next)
    setPage(1)
  }

  function openEdit(m: UnionMember) {
    setModal({
      open: true,
      form: {
        id: m.id,
        id_number: m.id_number ?? '',
        member_name: m.member_name ?? '',
        member_type: m.member_type ?? '',
        date_of_birth: m.date_of_birth ? m.date_of_birth.slice(0, 10) : '',
        gender: m.gender === 1 ? 'Male' : m.gender === 0 ? 'Female' : '',
        contact_number: m.contact_number ?? '',
        email: m.email ?? '',
        nationality: m.nationality ?? '',
        status: String(m.status ?? 1),
      },
    })
  }

  const members = membersQ.data?.data ?? []
  const meta = membersQ.data ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
  const editing = !!modal.form.id
  const unionInactive = selectedUnion?.status === 0

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Members</h1>
          <p className="text-sm text-gray-500 mt-0.5">Manage and bulk-import members per union.</p>
        </div>
        {unionId && (
          <div className="flex gap-2">
            <button onClick={() => downloadMemberTemplate(unionId)} className="px-3 py-2 border text-sm rounded hover:bg-gray-50">Download Template</button>
            <button onClick={() => setImportOpen(true)} disabled={unionInactive}
              className="px-3 py-2 border text-sm rounded hover:bg-gray-50 disabled:opacity-50">Import Members</button>
            <button onClick={() => exportMembersFile(unionId)} className="px-3 py-2 border text-sm rounded hover:bg-gray-50">Export Members</button>
            <button onClick={() => setModal({ open: true, form: { ...EMPTY } })} disabled={unionInactive}
              className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50">+ Add Member</button>
          </div>
        )}
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Union</label>
          <select value={unionId} onChange={e => setUnion(e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 bg-white">
            <option value="">— select a union —</option>
            {unions.map(u => (
              <option key={u.id} value={u.id}>{u.union_name}{u.status === 0 ? ' (inactive)' : ''}</option>
            ))}
          </select>
        </div>
        {unionId && <>
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Search client</label>
            {/* Explicit Search button (BONU brief 2026-09-08): Enter/blur alone
                was not discoverable — users looked for a button. */}
            <div className="flex gap-1">
              <input ref={searchRef} type="text" placeholder="Name, ID, type, contact..."
                defaultValue={search}
                onKeyDown={e => { if (e.key === 'Enter') runSearch() }}
                className="px-3 py-1.5 border border-line rounded-md text-sm w-72 bg-surface text-ink" />
              <button type="button" onClick={runSearch}
                className="px-3 py-1.5 border border-line rounded-md text-sm text-ink hover:bg-surface-2">Search</button>
            </div>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select value={status} onChange={e => { setPage(1); setStatus(e.target.value) }}
              className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-40 bg-white">
              <option value="">All</option>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </>}
      </div>

      {!unionId ? (
        <div className="bg-white rounded-lg shadow-sm border"><EmptyState title="Select a union" description="Choose a union above to view and manage its members." /></div>
      ) : (
        <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
          {membersQ.isFetching && !membersQ.isLoading && (
            <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center"><LoadingSpinner size="md" /></div>
          )}
          <DualScrollTable>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                  <th className="px-4 py-3 text-left">ID Number</th>
                  <th className="px-4 py-3 text-left">Name</th>
                  <th className="px-4 py-3 text-left">Type</th>
                  <th className="px-4 py-3 text-left">Date of Birth</th>
                  <th className="px-4 py-3 text-left">Gender</th>
                  <th className="px-4 py-3 text-left">Contact</th>
                  <th className="px-4 py-3 text-left">Email</th>
                  <th className="px-4 py-3 text-left">Nationality</th>
                  <th className="px-4 py-3 text-left">Union</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {membersQ.isLoading ? (
                  <tr><td colSpan={11} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
                ) : members.length === 0 ? (
                  <tr><td colSpan={11} className="p-0"><EmptyState compact title="No members" description="No members for this union yet." /></td></tr>
                ) : members.map((m: UnionMember) => (
                  <tr key={m.id} className="hover:bg-gray-50">
                    <td className="px-4 py-2 text-gray-600 font-mono text-xs">{m.id_number}</td>
                    <td className="px-4 py-2 font-medium text-gray-800">{m.member_name}</td>
                    <td className="px-4 py-2 text-gray-600">{m.member_type ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{m.date_of_birth ? m.date_of_birth.slice(0, 10) : '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{genderLabel(m.gender)}</td>
                    <td className="px-4 py-2 text-gray-600">{m.contact_number ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{m.email || '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{m.nationality ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-600">{selectedUnion?.union_name ?? '—'}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs ${m.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                        {m.status === 1 ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                      <Link to={legalClaimPath(selectedUnion?.union_code, unionId, m.id)} state={{ member: m }}
                        className="text-blue-600 hover:underline text-xs">File Claim</Link>
                      <button onClick={() => openEdit(m)} className="text-blue-600 hover:underline text-xs">Edit</button>
                      <button onClick={() => statusMut.mutate(m)} disabled={statusMut.isPending}
                        className={`text-xs hover:underline ${m.status === 1 ? 'text-amber-600' : 'text-green-600'} disabled:opacity-50`}>
                        {m.status === 1 ? 'Deactivate' : 'Activate'}
                      </button>
                      <button onClick={() => { if (confirm(`Remove ${m.member_name}?`)) removeMut.mutate(m.id) }}
                        className="text-red-600 hover:underline text-xs">Remove</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </DualScrollTable>
          {meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
              <span className="text-gray-500">Showing {meta.from}–{meta.to} of {meta.total}</span>
              <div className="flex items-center gap-1">
                <button disabled={meta.current_page === 1} onClick={() => setPage(p => p - 1)}
                  className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40">Prev</button>
                <span className="px-2 text-gray-600">{meta.current_page} / {meta.last_page}</span>
                <button disabled={meta.current_page === meta.last_page} onClick={() => setPage(p => p + 1)}
                  className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40">Next</button>
              </div>
            </div>
          )}
        </div>
      )}

      {modal.open && (
        <MemberModal form={modal.form} editing={editing} pending={saveMut.isPending}
          onClose={() => setModal({ open: false, form: EMPTY })}
          onChange={f => setModal(m => ({ ...m, form: f }))}
          onSave={() => {
            const f = modal.form
            if (!f.id_number.trim()) { alert('ID Number is required'); return }
            if (!f.member_name.trim()) { alert('Name is required'); return }
            if (!f.member_type.trim()) { alert('Type is required'); return }
            if (!f.date_of_birth) { alert('Date of Birth is required'); return }
            if (!f.gender) { alert('Gender is required'); return }
            if (!f.contact_number.trim()) { alert('Contact Number is required'); return }
            if (!f.nationality.trim()) { alert('Nationality is required'); return }
            saveMut.mutate(f)
          }} />
      )}

      {importOpen && (
        <ImportModal unionId={unionId} onClose={() => setImportOpen(false)}
          onImported={() => { invalidate(); setImportOpen(false) }} fileRef={fileRef} />
      )}
    </div>
  )
}

// ─── Add / Edit member modal ──────────────────────────────────────────────────

function MemberModal(props: {
  form: FormState; editing: boolean; pending: boolean
  onClose: () => void; onChange: (f: FormState) => void; onSave: () => void
}) {
  const { form, editing, pending, onClose, onChange, onSave } = props
  const set = (k: keyof FormState, v: string) => onChange({ ...form, [k]: v })
  return (
    <Modal open onClose={onClose} size="2xl" title={`${editing ? 'Edit' : 'Add'} Member`}
      footer={
        <>
          <button onClick={onClose} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Cancel</button>
          <button onClick={onSave} disabled={pending}
            className="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {pending ? 'Saving…' : (editing ? 'Update Member' : 'Add Member')}
          </button>
        </>
      }>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs text-gray-600 mb-1">ID Number *</label>
                <input value={form.id_number} disabled={editing} onChange={e => set('id_number', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm font-mono disabled:bg-gray-100" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Type *</label>
                <input value={form.member_type} onChange={e => set('member_type', e.target.value)}
                  placeholder="Principal / Dependent…" className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="sm:col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Name *</label>
                <input value={form.member_name} onChange={e => set('member_name', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Date of Birth *</label>
                <input type="date" value={form.date_of_birth} onChange={e => set('date_of_birth', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Gender *</label>
                <select value={form.gender} onChange={e => set('gender', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="">—</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Contact Number *</label>
                <input value={form.contact_number} onChange={e => set('contact_number', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Nationality *</label>
                <input value={form.nationality} onChange={e => set('nationality', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div className="sm:col-span-2">
                <label className="block text-xs text-gray-600 mb-1">Email</label>
                <input type="email" value={form.email} onChange={e => set('email', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">Status</label>
                <select value={form.status} onChange={e => set('status', e.target.value)}
                  className="w-full px-3 py-2 border rounded text-sm bg-white">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
            </div>
    </Modal>
  )
}

// ─── Import modal (upload → preview → commit) ──────────────────────────────────

/**
 * Surface the real reason an import call failed. Laravel returns either
 * { error } (our own guards) or { message, errors: { field: [msgs] } } (422
 * validation) — the old code only read `error`, so every validation failure
 * showed a bare "Preview failed" with no clue what to fix.
 */
function importError(err: any, fallback: string): string {
  const d = err?.response?.data
  if (d?.error) return d.error
  if (d?.errors) {
    const msgs = Object.values(d.errors as Record<string, string[]>).flat()
    if (msgs.length) return msgs.join('\n')
  }
  if (d?.message) return d.message
  // A client-side abort does NOT stop the server — it commits its transaction
  // regardless. Tell the operator to look before re-uploading; the duplicate-ID
  // rule means a re-run can't double-register, but it will report every
  // already-imported row as a duplicate, which reads like a failure otherwise.
  if (err?.code === 'ECONNABORTED') {
    return 'The upload timed out after 10 minutes. The import may still be finishing on the server — '
      + 'refresh the member list before trying again, and split very large files if it keeps timing out.'
  }
  return err?.message ? `${fallback}: ${err.message}` : fallback
}

function ImportModal(props: {
  unionId: string; onClose: () => void; onImported: () => void
  fileRef: React.RefObject<HTMLInputElement>
}) {
  const { unionId, onClose, onImported, fileRef } = props
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<ImportResult | null>(null)

  const previewMut = useMutation({
    mutationFn: () => importMembers(unionId, file!, false),
    onSuccess: (r) => setResult(r),
    onError: (err: any) => alert(importError(err, 'Preview failed')),
  })
  const commitMut = useMutation({
    mutationFn: () => importMembers(unionId, file!, true),
    onSuccess: (r) => { setResult(r); if (r.committed) onImported() },
    onError: (err: any) => alert(importError(err, 'Import failed')),
  })

  const s = result?.summary
  const badge = (st: string) =>
    st === 'valid' ? 'bg-green-100 text-green-700' : st === 'duplicate' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'

  return (
    <Modal open onClose={onClose} size="3xl" title="Import Members"
      footer={
        <>
          <button onClick={onClose} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">
            {result?.committed ? 'Close' : 'Cancel'}
          </button>
          {s && !result!.committed && (
            <button onClick={() => commitMut.mutate()} disabled={s.valid === 0 || commitMut.isPending}
              className="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
              {commitMut.isPending ? 'Importing…' : `Import ${s.valid} valid record(s)`}
            </button>
          )}
        </>
      }>
          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-3">
              <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv"
                onChange={e => { setFile(e.target.files?.[0] ?? null); setResult(null) }}
                className="text-sm min-w-0 flex-1" />
              <button onClick={() => file && previewMut.mutate()} disabled={!file || previewMut.isPending}
                className="px-3 py-2 border text-sm rounded hover:bg-gray-50 disabled:opacity-50 whitespace-nowrap">
                {previewMut.isPending ? 'Validating…' : 'Validate & Preview'}
              </button>
            </div>

            {!s && (
              <p className="text-xs text-gray-500">
                Use the member template (.xlsx, .xls or .csv) — download it from the Members page if you
                don’t have it. Only <strong>ID Number</strong> is required; rows with missing name, date of
                birth, gender, contact or nationality still import. A row is only rejected when it has no ID
                Number, or when that ID is already registered. Nothing is saved until you confirm the preview.
              </p>
            )}

            {s && (
              <>
                <div className="grid grid-cols-3 md:grid-cols-6 gap-2 text-center">
                  {[
                    ['Total', s.total, 'text-gray-800'],
                    ['Valid', s.valid, 'text-green-600'],
                    ['Imported', s.imported, 'text-blue-600'],
                    ['Failed', s.failed, 'text-red-600'],
                    ['Duplicates', s.duplicates, 'text-amber-600'],
                    ['Errors', s.validation_errors, 'text-red-600'],
                  ].map(([label, val, cls]) => (
                    <div key={label as string} className="border rounded p-2">
                      <div className="text-[10px] uppercase text-gray-500">{label}</div>
                      <div className={`text-lg font-bold ${cls}`}>{val}</div>
                    </div>
                  ))}
                </div>

                <div className="border rounded max-h-[45vh] overflow-auto">
                  <table className="w-full text-xs">
                    <thead className="bg-gray-50 text-gray-600 uppercase sticky top-0 z-10">
                      <tr>
                        <th className="px-2 py-1.5 text-left">Row</th>
                        <th className="px-2 py-1.5 text-left">ID Number</th>
                        <th className="px-2 py-1.5 text-left">Name</th>
                        <th className="px-2 py-1.5 text-left">Status</th>
                        <th className="px-2 py-1.5 text-left">Messages</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {result!.preview.map((r) => (
                        <tr key={r.row}>
                          <td className="px-2 py-1 text-gray-500">{r.row}</td>
                          <td className="px-2 py-1 font-mono">{r.id_number || '—'}</td>
                          <td className="px-2 py-1">{r.name || '—'}</td>
                          <td className="px-2 py-1"><span className={`px-2 py-0.5 rounded-full ${badge(r.status)}`}>{r.status}</span></td>
                          <td className="px-2 py-1 text-gray-500">{r.messages.join('; ')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {result!.committed && (
                  <div className="text-sm text-green-700 font-medium">Imported {s.imported} member(s) successfully.</div>
                )}
              </>
            )}
          </div>
    </Modal>
  )
}
