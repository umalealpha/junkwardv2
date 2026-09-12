import { useState } from 'react'
import type { CreateEmployerGroupPayload, UpdateEmployerGroupPayload } from '../../api/employerGroups'

// Shared employer-group form (create + edit). Design-token styled.

export const INDUSTRIES = [
  'Agriculture', 'Construction', 'Education', 'Finance', 'Government',
  'Health', 'Hospitality', 'Insurance', 'IT / Technology', 'Legal',
  'Manufacturing', 'Media', 'Mining', 'NGO / Non-Profit', 'Real Estate',
  'Retail', 'Security', 'Telecommunications', 'Transport', 'Utilities', 'Other',
]

export const PAYMENT_METHODS = ['Bank Transfer', 'Cheque', 'Cash', 'Debit Order']

export interface EmployerGroupFormValues extends CreateEmployerGroupPayload {
  /** HR emails as a single textarea string (comma/newline separated). */
  bulk_emails_text: string
}

export const EMPTY_FORM: EmployerGroupFormValues = {
  name: '', industry: '', other_industry: '', address: '', town: '',
  postal_code: '', contact_name: '', contact_phone: '', contact_email: '',
  no_of_employees: null, broker: '', payment_method: '', notes: '', status: 'active',
  bulk_emails_text: '',
}

/** Convert form values to the API payload (bulk_emails as string array). */
export function toPayload(form: EmployerGroupFormValues): UpdateEmployerGroupPayload {
  const { bulk_emails_text, ...rest } = form
  return {
    ...rest,
    // != null (not falsy check) so a legitimate 0 survives.
    no_of_employees: rest.no_of_employees != null ? Number(rest.no_of_employees) : null,
    bulk_emails: bulk_emails_text
      ? bulk_emails_text.split(/[\n,;]+/).map(s => s.trim()).filter(Boolean)
      : [],
  }
}

export default function EmployerGroupForm({ initial, submitLabel, submitting, serverError, groupCode, requireContact = true, onSubmit, onCancel }: {
  initial: EmployerGroupFormValues
  submitLabel: string
  submitting: boolean
  serverError: string
  /** Shown read-only on edit — the short code is immutable. */
  groupCode?: string
  /** Edit passes false: legacy rows may legitimately lack contact fields. */
  requireContact?: boolean
  onSubmit: (form: EmployerGroupFormValues) => void
  onCancel: () => void
}) {
  const [form, setForm] = useState<EmployerGroupFormValues>(initial)
  const [errors, setErrors] = useState<Partial<Record<keyof EmployerGroupFormValues, string>>>({})

  function set<K extends keyof EmployerGroupFormValues>(key: K, value: EmployerGroupFormValues[K]) {
    setForm(f => ({ ...f, [key]: value }))
    setErrors(e => ({ ...e, [key]: undefined }))
  }

  function validate(): boolean {
    const e: typeof errors = {}
    if (!form.name.trim()) e.name = 'Group name is required.'
    if (!form.industry) e.industry = 'Industry is required.'
    if (form.industry === 'Other' && !form.other_industry?.trim()) e.other_industry = 'Please specify the industry.'
    if (requireContact && !form.contact_name.trim()) e.contact_name = 'Contact name is required.'
    if (requireContact && !form.contact_phone.trim()) e.contact_phone = 'Contact phone is required.'
    if (requireContact && !form.contact_email.trim()) e.contact_email = 'Contact email is required.'
    if (form.contact_email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contact_email)) e.contact_email = 'Enter a valid email address.'
    const badEmail = form.bulk_emails_text
      .split(/[\n,;]+/).map(s => s.trim()).filter(Boolean)
      .find(s => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s))
    if (badEmail) e.bulk_emails_text = `"${badEmail}" is not a valid email address.`
    setErrors(e)
    return Object.keys(e).length === 0
  }

  const inp = 'w-full px-3 py-2 border border-line rounded-md text-sm bg-surface text-ink'
  const errInp = 'w-full px-3 py-2 border border-status-danger-fg rounded-md text-sm bg-surface text-ink'
  const label = 'block text-sm font-medium text-ink-muted mb-1'
  const req = <span className="text-status-danger-fg">*</span>
  const sectionCls = 'bg-surface rounded-lg shadow-sm border border-line p-5 space-y-4'
  const headingCls = 'text-sm font-semibold text-ink-muted uppercase tracking-wide'

  return (
    <form onSubmit={e => { e.preventDefault(); if (validate()) onSubmit(form) }} noValidate className="space-y-6">
      {serverError && (
        <div className="bg-status-danger-bg text-status-danger-fg text-sm px-4 py-3 rounded-md">{serverError}</div>
      )}

      <section className={sectionCls}>
        <h2 className={headingCls}>Group Details</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {groupCode && (
            <div className="sm:col-span-2">
              <label className={label}>Group ID</label>
              <input type="text" value={groupCode} disabled className={`${inp} opacity-60 font-mono`} />
            </div>
          )}
          <div className="sm:col-span-2">
            <label className={label}>Group Name {req}</label>
            <input type="text" value={form.name} onChange={e => set('name', e.target.value)}
              className={errors.name ? errInp : inp} placeholder="e.g. Flotek Industries Pty Ltd" />
            {errors.name && <p className="text-status-danger-fg text-xs mt-1">{errors.name}</p>}
          </div>
          <div>
            <label className={label}>Industry {req}</label>
            <select value={form.industry} onChange={e => set('industry', e.target.value)} className={errors.industry ? errInp : inp}>
              <option value="">Select industry…</option>
              {INDUSTRIES.map(i => <option key={i} value={i}>{i}</option>)}
            </select>
            {errors.industry && <p className="text-status-danger-fg text-xs mt-1">{errors.industry}</p>}
          </div>
          {form.industry === 'Other' && (
            <div>
              <label className={label}>Specify Industry {req}</label>
              <input type="text" value={form.other_industry ?? ''} onChange={e => set('other_industry', e.target.value)}
                className={errors.other_industry ? errInp : inp} placeholder="Describe the industry" />
              {errors.other_industry && <p className="text-status-danger-fg text-xs mt-1">{errors.other_industry}</p>}
            </div>
          )}
          <div>
            <label className={label}>Number of Employees</label>
            <input type="number" min={1} value={form.no_of_employees ?? ''}
              onChange={e => set('no_of_employees', e.target.value === '' ? null : Number(e.target.value))}
              className={inp} placeholder="e.g. 50" />
          </div>
          <div>
            <label className={label}>Status</label>
            <select value={form.status} onChange={e => set('status', e.target.value)} className={inp}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
              {/* Legacy vocabulary — still valid on existing rows. */}
              <option value="suspended">Suspended</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
      </section>

      <section className={sectionCls}>
        <h2 className={headingCls}>Address</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="sm:col-span-2">
            <label className={label}>Street Address</label>
            <input type="text" value={form.address ?? ''} onChange={e => set('address', e.target.value)} className={inp} placeholder="Street address" />
          </div>
          <div>
            <label className={label}>Town / City</label>
            <input type="text" value={form.town ?? ''} onChange={e => set('town', e.target.value)} className={inp} placeholder="Gaborone" />
          </div>
          <div>
            <label className={label}>Postal Code</label>
            <input type="text" value={form.postal_code ?? ''} onChange={e => set('postal_code', e.target.value)} className={inp} placeholder="e.g. 00101" />
          </div>
        </div>
      </section>

      <section className={sectionCls}>
        <h2 className={headingCls}>Contact Person</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="sm:col-span-2">
            <label className={label}>Full Name {req}</label>
            <input type="text" value={form.contact_name} onChange={e => set('contact_name', e.target.value)}
              className={errors.contact_name ? errInp : inp} placeholder="e.g. Pearl Gaobakwe" />
            {errors.contact_name && <p className="text-status-danger-fg text-xs mt-1">{errors.contact_name}</p>}
          </div>
          <div>
            <label className={label}>Phone {req}</label>
            <input type="tel" value={form.contact_phone} onChange={e => set('contact_phone', e.target.value)}
              className={errors.contact_phone ? errInp : inp} placeholder="+267 72 000 000" />
            {errors.contact_phone && <p className="text-status-danger-fg text-xs mt-1">{errors.contact_phone}</p>}
          </div>
          <div>
            <label className={label}>Email {req}</label>
            <input type="email" value={form.contact_email} onChange={e => set('contact_email', e.target.value)}
              className={errors.contact_email ? errInp : inp} placeholder="contact@company.co.bw" />
            {errors.contact_email && <p className="text-status-danger-fg text-xs mt-1">{errors.contact_email}</p>}
          </div>
        </div>
      </section>

      <section className={sectionCls}>
        <h2 className={headingCls}>HR Portal Access</h2>
        <div>
          <label className={label}>HR Emails</label>
          <textarea rows={3} value={form.bulk_emails_text} onChange={e => set('bulk_emails_text', e.target.value)}
            className={errors.bulk_emails_text ? errInp : inp}
            placeholder={'hr@company.co.bw, payroll@company.co.bw\nOne per line or comma-separated.'} />
          {errors.bulk_emails_text
            ? <p className="text-status-danger-fg text-xs mt-1">{errors.bulk_emails_text}</p>
            : <p className="text-ink-faint text-xs mt-1">These addresses receive HR portal access when you use "Send HR Access" on the group page.</p>}
        </div>
      </section>

      <section className={sectionCls}>
        <h2 className={headingCls}>Additional</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className={label}>Broker</label>
            <input type="text" value={form.broker ?? ''} onChange={e => set('broker', e.target.value)} className={inp} placeholder="Broker name (optional)" />
          </div>
          <div>
            <label className={label}>Payment Method</label>
            <select value={form.payment_method ?? ''} onChange={e => set('payment_method', e.target.value)} className={inp}>
              <option value="">Select…</option>
              {PAYMENT_METHODS.map(m => <option key={m} value={m}>{m}</option>)}
            </select>
          </div>
          <div className="sm:col-span-2">
            <label className={label}>Notes</label>
            <textarea rows={3} value={form.notes ?? ''} onChange={e => set('notes', e.target.value)}
              className={inp} placeholder="Any relevant notes about this group…" />
          </div>
        </div>
      </section>

      <div className="flex items-center justify-end gap-3 pb-4">
        <button type="button" onClick={onCancel}
          className="px-4 py-2 text-sm border border-line rounded-md text-ink-muted hover:bg-surface-2 transition">
          Cancel
        </button>
        <button type="submit" disabled={submitting}
          className="px-5 py-2 text-sm bg-primary text-primary-contrast rounded-md transition disabled:opacity-60 disabled:cursor-not-allowed">
          {submitting ? 'Saving…' : submitLabel}
        </button>
      </div>
    </form>
  )
}
