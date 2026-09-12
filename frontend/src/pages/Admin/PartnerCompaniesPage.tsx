import { useCallback, useEffect, useState } from 'react'
import EmptyState from '../../components/common/EmptyState'
import {
  partnerCompaniesApi,
  type AssignableProduct,
  type CompanyInput,
  type PartnerCompany,
  type PartnerUser,
} from '../../api/partnerCompanies'

/**
 * Partner Companies — courier / retailer partners for the embedded B2B2C
 * products (Alpha Transit Cover now, AlphaProtect next) and their start-portal
 * logins. Staff-management equivalent: admin creates the company, assigns the
 * products it may sell on start, adds logins, and sends each login a signed
 * set-password email. Admin never sees or types a partner password.
 */

const EMPTY_FORM: CompanyInput = { company_code: '', name: '', contact_name: '', contact_email: '', contact_phone: '', products: [], notes: '', status: true }

function errMsg(e: unknown, fallback: string): string {
  const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
  if (r?.errors) return Object.values(r.errors).flat().join(' ')
  return r?.message || fallback
}

function fmt(d: string | null) { return d ? new Date(d).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—' }

export default function PartnerCompaniesPage() {
  const [companies, setCompanies] = useState<PartnerCompany[]>([])
  const [products, setProducts] = useState<AssignableProduct[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [notice, setNotice] = useState<{ kind: 'ok' | 'err'; text: string } | null>(null)

  const [companyModal, setCompanyModal] = useState<{ id: number | null; form: CompanyInput } | null>(null)
  const [saving, setSaving] = useState(false)

  const [detail, setDetail] = useState<PartnerCompany | null>(null)
  const [userForm, setUserForm] = useState<{ name: string; email: string } | null>(null)
  const [busyUser, setBusyUser] = useState<number | null>(null)

  const load = useCallback(() => {
    setLoading(true)
    partnerCompaniesApi.list(search)
      .then(r => { setCompanies(r.data); setProducts(r.assignable_products) })
      .catch(e => setNotice({ kind: 'err', text: errMsg(e, 'Failed to load partner companies') }))
      .finally(() => setLoading(false))
  }, [search])
  useEffect(() => { load() }, [load])

  const openDetail = async (id: number) => {
    try { setDetail(await partnerCompaniesApi.get(id)) } catch (e) { setNotice({ kind: 'err', text: errMsg(e, 'Failed to load company') }) }
  }
  const refreshDetail = async () => { if (detail) await openDetail(detail.id); load() }

  async function saveCompany() {
    if (!companyModal) return
    setSaving(true)
    try {
      const f = { ...companyModal.form, company_code: companyModal.form.company_code?.toUpperCase().trim() }
      if (companyModal.id) { const { company_code: _c, ...rest } = f; await partnerCompaniesApi.update(companyModal.id, rest) }
      else await partnerCompaniesApi.create(f)
      setCompanyModal(null); setNotice({ kind: 'ok', text: 'Company saved.' }); load()
      if (detail && companyModal.id === detail.id) openDetail(detail.id)
    } catch (e) { setNotice({ kind: 'err', text: errMsg(e, 'Save failed') }) }
    finally { setSaving(false) }
  }

  async function addUser() {
    if (!detail || !userForm) return
    setSaving(true)
    try {
      const r = await partnerCompaniesApi.createUser(detail.id, { ...userForm, send_credentials: true })
      setUserForm(null)
      setNotice(r.credentials_sent
        ? { kind: 'ok', text: `Login created. Access email sent to ${r.data.email}.` }
        : { kind: 'err', text: `Login created but the access email failed: ${r.send_error ?? 'unknown error'}. Use "Resend access email".` })
      refreshDetail()
    } catch (e) { setNotice({ kind: 'err', text: errMsg(e, 'Could not create login') }) }
    finally { setSaving(false) }
  }

  async function userAction(u: PartnerUser, action: 'toggle' | 'resend' | 'revoke') {
    if (!detail) return
    setBusyUser(u.id)
    try {
      if (action === 'toggle') { await partnerCompaniesApi.updateUser(detail.id, u.id, { is_active: !u.is_active }); setNotice({ kind: 'ok', text: u.is_active ? 'Login deactivated and signed out everywhere.' : 'Login reactivated.' }) }
      if (action === 'resend') { const r = await partnerCompaniesApi.sendCredentials(detail.id, u.id); setNotice({ kind: 'ok', text: r.message }) }
      if (action === 'revoke') { await partnerCompaniesApi.revokeSessions(detail.id, u.id); setNotice({ kind: 'ok', text: 'All sessions revoked.' }) }
      refreshDetail()
    } catch (e) { setNotice({ kind: 'err', text: errMsg(e, 'Action failed') }) }
    finally { setBusyUser(null) }
  }

  const productName = (id: number) => products.find(p => p.id === id)?.name ?? `Product ${id}`

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-ink">Partner Companies</h1>
          <p className="text-sm text-ink-muted">Courier and retail partners who issue Alpha Transit Cover from the start portal. Add a company, assign its products, then add logins.</p>
        </div>
        <button onClick={() => setCompanyModal({ id: null, form: { ...EMPTY_FORM } })}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 whitespace-nowrap">+ Add Company</button>
      </div>

      {notice && (
        <div className={`flex items-start justify-between rounded-md px-4 py-2 text-sm ${notice.kind === 'ok' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'}`}>
          <span>{notice.text}</span><button onClick={() => setNotice(null)} className="ml-4 text-xs underline">dismiss</button>
        </div>
      )}

      <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search by name or code…"
        className="w-full max-w-sm px-3 py-2 border rounded-md text-sm" />

      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface">
            <tr>
              {['Code', 'Company', 'Contact', 'Products', 'Logins', 'Policies', 'Premium', 'Status', ''].map(h =>
                <th key={h} className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">{h}</th>)}
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {loading && <tr><td colSpan={9} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!loading && companies.length === 0 && <tr><td colSpan={9} className="p-0"><EmptyState compact title="No partner companies" description="Add the first courier or retailer partner." /></td></tr>}
            {companies.map(c => (
              <tr key={c.id} className="hover:bg-surface">
                <td className="px-4 py-3 font-mono text-ink">{c.company_code}</td>
                <td className="px-4 py-3 font-medium text-ink">{c.name}</td>
                <td className="px-4 py-3 text-ink-muted">{c.contact_name || '—'}<div className="text-xs text-ink-faint">{c.contact_email || ''}</div></td>
                <td className="px-4 py-3 text-ink-muted">{c.products.length ? c.products.map(productName).join(', ') : <span className="text-amber-600">none assigned</span>}</td>
                <td className="px-4 py-3 text-ink">{c.user_count}</td>
                <td className="px-4 py-3 text-ink">{c.policy_count ?? 0}</td>
                <td className="px-4 py-3 text-green-700 font-medium">P {(c.total_premium ?? 0).toLocaleString('en-BW', { minimumFractionDigits: 2 })}</td>
                <td className="px-4 py-3"><span className={`inline-block px-2 py-0.5 rounded text-xs ${c.status ? 'bg-green-100 text-green-800' : 'bg-surface-2 text-ink-muted'}`}>{c.status ? 'Active' : 'Inactive'}</span></td>
                <td className="px-4 py-3 text-right whitespace-nowrap">
                  <button onClick={() => openDetail(c.id)} className="text-blue-600 hover:underline mr-3">Logins</button>
                  <button onClick={() => setCompanyModal({ id: c.id, form: { name: c.name, company_code: c.company_code, contact_name: c.contact_name ?? '', contact_email: c.contact_email ?? '', contact_phone: c.contact_phone ?? '', products: c.products, notes: c.notes ?? '', status: c.status, agency_id: c.agency_id } })}
                    className="text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Company create / edit */}
      {companyModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={e => { if (e.target === e.currentTarget) setCompanyModal(null) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-lg p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{companyModal.id ? 'Edit Company' : 'Add Partner Company'}</h2>
            <div className="grid grid-cols-2 gap-3">
              <label className="block text-xs font-medium text-ink-muted">Company code
                <input value={companyModal.form.company_code ?? ''} disabled={!!companyModal.id} maxLength={16} placeholder="e.g. EGC"
                  onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, company_code: e.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '') } })}
                  className="mt-1 w-full px-3 py-2 border rounded-md text-sm font-mono disabled:bg-surface-2" />
              </label>
              <label className="block text-xs font-medium text-ink-muted">Company name
                <input value={companyModal.form.name} onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, name: e.target.value } })}
                  className="mt-1 w-full px-3 py-2 border rounded-md text-sm" />
              </label>
              <label className="block text-xs font-medium text-ink-muted">Contact name
                <input value={companyModal.form.contact_name ?? ''} onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, contact_name: e.target.value } })}
                  className="mt-1 w-full px-3 py-2 border rounded-md text-sm" />
              </label>
              <label className="block text-xs font-medium text-ink-muted">Contact phone
                <input value={companyModal.form.contact_phone ?? ''} onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, contact_phone: e.target.value } })}
                  className="mt-1 w-full px-3 py-2 border rounded-md text-sm" />
              </label>
              <label className="block text-xs font-medium text-ink-muted col-span-2">Contact email
                <input type="email" value={companyModal.form.contact_email ?? ''} onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, contact_email: e.target.value } })}
                  className="mt-1 w-full px-3 py-2 border rounded-md text-sm" />
              </label>
            </div>
            <fieldset>
              <legend className="text-xs font-medium text-ink-muted mb-1">Products this company may sell on start</legend>
              {products.map(p => (
                <label key={p.id} className="flex items-center gap-2 text-sm text-ink py-0.5">
                  <input type="checkbox" checked={companyModal.form.products.includes(p.id)}
                    onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, products: e.target.checked ? [...companyModal.form.products, p.id] : companyModal.form.products.filter(x => x !== p.id) } })}
                    className="rounded border-line text-blue-600" />
                  {p.name}
                </label>
              ))}
            </fieldset>
            <label className="block text-xs font-medium text-ink-muted">Notes
              <textarea value={companyModal.form.notes ?? ''} rows={2} onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, notes: e.target.value } })}
                className="mt-1 w-full px-3 py-2 border rounded-md text-sm" />
            </label>
            <label className="flex items-center gap-2 text-sm text-ink-muted">
              <input type="checkbox" checked={companyModal.form.status} onChange={e => setCompanyModal({ ...companyModal, form: { ...companyModal.form, status: e.target.checked } })} className="rounded border-line text-blue-600" />
              Active {companyModal.id && !companyModal.form.status && <span className="text-xs text-amber-600">(deactivating signs out every login)</span>}
            </label>
            <div className="flex justify-end gap-2">
              <button onClick={() => setCompanyModal(null)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={saveCompany} disabled={saving || !companyModal.form.name || (!companyModal.id && !companyModal.form.company_code)}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}

      {/* Logins drawer */}
      {detail && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={e => { if (e.target === e.currentTarget) { setDetail(null); setUserForm(null) } }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-3xl p-5 space-y-4 max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between">
              <div>
                <h2 className="text-lg font-bold">{detail.name} <span className="font-mono text-sm text-ink-muted">({detail.company_code})</span></h2>
                <p className="text-xs text-ink-muted">Products: {detail.products.length ? detail.products.map(productName).join(', ') : 'none assigned'} · Agency #{detail.agency_id ?? '—'}</p>
              </div>
              <button onClick={() => setUserForm({ name: '', email: '' })} className="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add login</button>
            </div>

            {userForm && (
              <div className="rounded-md border bg-surface p-3 grid grid-cols-[1fr_1fr_auto] gap-2 items-end">
                <label className="text-xs font-medium text-ink-muted">Name<input value={userForm.name} onChange={e => setUserForm({ ...userForm, name: e.target.value })} className="mt-1 w-full px-3 py-2 border rounded-md text-sm" /></label>
                <label className="text-xs font-medium text-ink-muted">Email<input type="email" value={userForm.email} onChange={e => setUserForm({ ...userForm, email: e.target.value })} className="mt-1 w-full px-3 py-2 border rounded-md text-sm" /></label>
                <div className="flex gap-2">
                  <button onClick={() => setUserForm(null)} className="px-3 py-2 text-sm border rounded-md">Cancel</button>
                  <button onClick={addUser} disabled={saving || !userForm.name || !userForm.email} className="px-3 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Creating…' : 'Create & email link'}</button>
                </div>
                <p className="col-span-3 text-xs text-ink-muted">The person receives a 48-hour link to set their own password. No password is shown here.</p>
              </div>
            )}

            <table className="min-w-full divide-y divide-line text-sm">
              <thead className="bg-surface"><tr>{['Name', 'Email', 'Password', 'Last login', 'Status', ''].map(h => <th key={h} className="px-3 py-2 text-left text-xs font-medium text-ink-muted uppercase">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-line">
                {(detail.users ?? []).length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-ink-faint">No logins yet.</td></tr>}
                {(detail.users ?? []).map(u => (
                  <tr key={u.id}>
                    <td className="px-3 py-2 font-medium text-ink">{u.name}</td>
                    <td className="px-3 py-2 text-ink-muted">{u.email}</td>
                    <td className="px-3 py-2">{u.password_set ? <span className="text-green-700 text-xs">set {fmt(u.password_set_at)}</span> : <span className="text-amber-600 text-xs">not set</span>}</td>
                    <td className="px-3 py-2 text-ink-muted text-xs">{fmt(u.last_login_at)}</td>
                    <td className="px-3 py-2"><span className={`inline-block px-2 py-0.5 rounded text-xs ${u.locked ? 'bg-red-100 text-red-700' : u.is_active ? 'bg-green-100 text-green-800' : 'bg-surface-2 text-ink-muted'}`}>{u.locked ? 'Locked' : u.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td className="px-3 py-2 text-right whitespace-nowrap text-xs">
                      <button disabled={busyUser === u.id} onClick={() => userAction(u, 'resend')} className="text-blue-600 hover:underline mr-3 disabled:opacity-50">{u.password_set ? 'Reset password' : 'Resend access email'}</button>
                      <button disabled={busyUser === u.id} onClick={() => userAction(u, 'revoke')} className="text-ink-muted hover:underline mr-3 disabled:opacity-50">Sign out all</button>
                      <button disabled={busyUser === u.id} onClick={() => userAction(u, 'toggle')} className={`hover:underline disabled:opacity-50 ${u.is_active ? 'text-red-600' : 'text-green-700'}`}>{u.is_active ? 'Deactivate' : 'Activate'}</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="flex justify-end"><button onClick={() => { setDetail(null); setUserForm(null) }} className="px-4 py-2 text-sm border rounded-md">Close</button></div>
          </div>
        </div>
      )}
    </div>
  )
}
