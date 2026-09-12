import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useCreateRefundRequest, useRefundRequest, useUpdateRefundRequest, useUploadRefundDocument } from '../../../hooks/useRefundRequests'
import { PLAIN_REASON_CODES, RETURN_PREMIUM_REASON_CODES, type CollectionMethod, type RefundArea, type RefundDocType } from '../../../api/refundRequests'
import { AREA_LABEL, apiErrorMessage, useRefundAccess } from './refundShared'

/**
 * Customer Refund Engine — intake form (Creator role), used for BOTH creating a
 * new request and editing a draft/rejected one.
 *
 * Edit mode exists because a rejected refund almost always needs a data fix
 * (wrong account, wrong amount) and raising a fresh one trips the
 * duplicate-policy fraud flag — so correcting the original is the intended
 * route (Finance ask, Keetile 2026-08-18). The backend has always allowed it
 * (PUT /refund-requests/{id}, isEditable() = draft|rejected); only the screen
 * was missing.
 *
 * One form for both modes on purpose: Finance adds fields to this SOP often
 * (branch name, collection methods, reason codes), and a duplicated edit form
 * would drift out of step within weeks.
 */
export default function RefundRequestCreatePage() {
  const navigate = useNavigate()
  const { id } = useParams()
  const editId = id ? Number(id) : null
  const isEdit = editId !== null
  const access = useRefundAccess()
  const create = useCreateRefundRequest()
  const update = useUpdateRefundRequest()
  const upload = useUploadRefundDocument()
  const existing = useRefundRequest(editId)

  const [form, setForm] = useState({
    area: (access.areas[0] ?? 'mis') as RefundArea,
    policy_number: '',
    refund_amount: '',
    product_name: '',
    customer_name: '',
    agent_name: '',
    reason: '',
    reason_code: '',
    collection_method: '' as '' | CollectionMethod,
    bank_name: '',
    branch_code: '',
    branch_name: '',
    account_number: '',
    vehicle_not_client: false,
  })
  const [bankProof, setBankProof] = useState<File | null>(null)
  const [bankProofType, setBankProofType] = useState<RefundDocType>('bank_statement')
  const [affidavit, setAffidavit] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
    setForm(f => ({ ...f, [k]: v }))

  // Pre-fill from the saved request in edit mode — ONCE.
  //
  // This used to depend on `existing.data`, which React Query hands back as a
  // new object on every refetch, and it refetches whenever the browser window
  // regains focus. So an intaker who typed a correction, alt-tabbed to check
  // RealPay or their mail, and came back had the form silently reset to the
  // saved values and lost the edit — then saved or resubmitted believing the
  // change was in (reported by Phatsimo on RFND-000026, 19 Aug). Keying the
  // effect on the request id means later background refetches can no longer
  // overwrite what the user is typing.
  const loaded = existing.data
  const prefilledFor = useRef<number | null>(null)
  useEffect(() => {
    if (!isEdit || !loaded || prefilledFor.current === loaded.id) return
    prefilledFor.current = loaded.id
    setForm({
      area: loaded.area,
      policy_number: loaded.policy_number ?? '',
      refund_amount: loaded.refund_amount != null ? String(loaded.refund_amount) : '',
      product_name: loaded.product_name ?? '',
      customer_name: loaded.customer_name ?? '',
      agent_name: loaded.agent_name ?? '',
      reason: loaded.reason ?? '',
      reason_code: loaded.reason_code ?? '',
      collection_method: (loaded.collection_method ?? '') as '' | CollectionMethod,
      bank_name: loaded.bank_name ?? '',
      branch_code: loaded.branch_code ?? '',
      branch_name: loaded.branch_name ?? '',
      account_number: '',
      vehicle_not_client: !!loaded.vehicle_not_client,
    })
  }, [isEdit, loaded])  // guarded by prefilledFor — runs once per request

  if (!access.canCreate) {
    return (
      <div className="p-6">
        <p className="text-sm text-ink-muted">You don't have permission to create or edit refund requests.</p>
      </div>
    )
  }
  if (isEdit && existing.isLoading) {
    return <div className="p-6 text-ink-faint">Loading…</div>
  }
  if (isEdit && loaded && !['draft', 'rejected'].includes(loaded.status)) {
    return (
      <div className="p-6 space-y-3">
        <Link to={`/finance/refund-engine/${editId}`} className="text-sm text-brand-navy hover:underline">← Back to {loaded.graphite_ref}</Link>
        <p className="text-sm text-ink-muted">
          This refund is already in review or beyond, so it can no longer be edited. Only a draft or a
          rejected refund can be changed.
        </p>
      </div>
    )
  }

  async function handleSave() {
    setError(null)
    if (!form.policy_number.trim()) return setError('Policy number is required.')
    const amount = parseFloat(form.refund_amount)
    if (!amount || amount <= 0) return setError('Refund amount must be greater than zero.')
    // On edit the account number is optional: blank means "leave the saved one
    // alone" (we never receive it back to pre-fill). Same for the documents —
    // they are already attached.
    if (!isEdit && !form.account_number.trim()) return setError('The client bank account number is required.')
    if (!isEdit && !bankProof) return setError('A bank statement or bank confirmation letter is required — no exceptions (SOP).')
    if (!isEdit && form.vehicle_not_client && !affidavit) {
      return setError('The vehicle is not registered to the client — a signed affidavit is required (SOP).')
    }

    setSaving(true)
    try {
      if (isEdit) {
        await update.mutateAsync({
          id: editId!,
          payload: {
            policy_number: form.policy_number.trim(),
            refund_amount: amount,
            product_name: form.product_name.trim(),
            customer_name: form.customer_name.trim(),
            agent_name: form.agent_name.trim(),
            reason: form.reason.trim(),
            reason_code: form.reason_code || undefined,
            collection_method: form.collection_method || undefined,
            bank_name: form.bank_name.trim(),
            branch_code: form.branch_code.trim(),
            branch_name: form.branch_name.trim(),
            // Only send the account number when a new one was typed.
            ...(form.account_number.trim() ? { account_number: form.account_number.trim() } : {}),
            vehicle_not_client: form.vehicle_not_client,
          },
        })
        // Any newly attached files are additive — the originals stay.
        if (bankProof) await upload.mutateAsync({ id: editId!, file: bankProof, docType: bankProofType })
        if (affidavit) await upload.mutateAsync({ id: editId!, file: affidavit, docType: 'affidavit' })
        navigate(`/finance/refund-engine/${editId}`)
        return
      }
      const r = await create.mutateAsync({
        area: form.area,
        policy_number: form.policy_number.trim(),
        refund_amount: amount,
        product_name: form.product_name.trim() || undefined,
        customer_name: form.customer_name.trim() || undefined,
        agent_name: form.agent_name.trim() || undefined,
        reason: form.reason.trim() || undefined,
        reason_code: form.reason_code || undefined,
        collection_method: form.collection_method || undefined,
        bank_name: form.bank_name.trim() || undefined,
        branch_code: form.branch_code.trim() || undefined,
        branch_name: form.branch_name.trim() || undefined,
        account_number: form.account_number.trim(),
        vehicle_not_client: form.vehicle_not_client,
      })
      if (bankProof) await upload.mutateAsync({ id: r.id, file: bankProof, docType: bankProofType })
      if (form.vehicle_not_client && affidavit) {
        await upload.mutateAsync({ id: r.id, file: affidavit, docType: 'affidavit' })
      }
      navigate(`/finance/refund-engine/${r.id}`)
    } catch (e) {
      setError(apiErrorMessage(e))
    } finally {
      setSaving(false)
    }
  }

  const inputCls = 'w-full border border-line rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-navy'
  const labelCls = 'block text-xs font-medium uppercase tracking-wide text-ink-muted mb-1'

  return (
    <div className="p-6 max-w-3xl space-y-5">
      <div>
        <Link to={isEdit ? `/finance/refund-engine/${editId}` : '/finance/refund-engine'}
          className="text-sm text-brand-navy hover:underline">
          {isEdit ? `← Back to ${loaded?.graphite_ref ?? 'refund'}` : '← Customer Refunds'}
        </Link>
        <h1 className="text-2xl font-bold text-ink mt-1">
          {isEdit ? `Edit ${loaded?.graphite_ref ?? 'refund request'}` : 'New Refund Request'}
        </h1>
        <p className="text-sm text-ink-muted mt-1">
          {isEdit
            ? 'Correct the details, save, then submit it again for review from the request page.'
            : 'Saved as a draft first — you submit it for review from the request page. Requests submitted after 15:00 process the next business day.'}
        </p>
      </div>

      {/* What the reviewer asked to be fixed — kept in front of the intaker
          while they edit, so they do not have to go back and forth. */}
      {isEdit && loaded?.rejected_reason && (
        <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm">
          <div className="font-semibold text-red-700">Reviewer asked for this to be fixed</div>
          <div className="text-red-700 mt-1 whitespace-pre-wrap">{loaded.rejected_reason}</div>
        </div>
      )}

      {error && (
        <div className="rounded border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3">{error}</div>
      )}

      <div className="bg-surface rounded-lg shadow-sm p-5 space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className={labelCls}>Area</label>
            <select value={form.area} onChange={e => set('area', e.target.value as RefundArea)}
              disabled={isEdit} className={`${inputCls} disabled:opacity-60`}>
              {access.areas.map(a => <option key={a} value={a}>{AREA_LABEL[a]}</option>)}
            </select>
            {isEdit && <p className="text-xs text-ink-faint mt-1">Area cannot be changed after the request is created.</p>}
          </div>
          <div>
            <label className={labelCls}>Policy Number *</label>
            <input value={form.policy_number} onChange={e => set('policy_number', e.target.value)} className={inputCls} placeholder="e.g. MIS0012345" />
          </div>
          <div>
            <label className={labelCls}>Refund Amount (BWP) *</label>
            <input type="number" min="0.01" step="0.01" value={form.refund_amount} onChange={e => set('refund_amount', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Collected Via</label>
            <select value={form.collection_method} onChange={e => set('collection_method', e.target.value as '' | CollectionMethod)} className={inputCls}>
              <option value="">—</option>
              <option value="DPO">DPO</option>
              <option value="RealPay">RealPay</option>
              <option value="VCS">VCS</option>
              <option value="PM8">PM8</option>
              <option value="CASH">CASH</option>
              <option value="N-GENIUS">N-GENIUS</option>
            </select>
          </div>
          <div>
            <label className={labelCls}>Customer Name</label>
            <input value={form.customer_name} onChange={e => set('customer_name', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Product</label>
            <input value={form.product_name} onChange={e => set('product_name', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Agent</label>
            <input value={form.agent_name} onChange={e => set('agent_name', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Refund Reason (drives accounting)</label>
            <select value={form.reason_code} onChange={e => set('reason_code', e.target.value)} className={inputCls}>
              <option value="">— select —</option>
              <optgroup label="Return premium (raises a Credit Note for Finance)">
                {Object.entries(RETURN_PREMIUM_REASON_CODES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </optgroup>
              <optgroup label="Cash refund only">
                {Object.entries(PLAIN_REASON_CODES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </optgroup>
            </select>
            {form.reason_code in RETURN_PREMIUM_REASON_CODES && (
              <p className="text-xs text-ink-muted mt-1">
                Return-premium: when paid, a Credit Note entry is prepared for Finance to review and post
                (written premium reduces). Nothing posts automatically.
              </p>
            )}
          </div>
          <div>
            <label className={labelCls}>Reason Details</label>
            <textarea value={form.reason} onChange={e => set('reason', e.target.value)} rows={2} className={inputCls}
              placeholder="Short narrative for the reviewer and Omni…" />
          </div>
        </div>
      </div>

      <div className="bg-surface rounded-lg shadow-sm p-5 space-y-4">
        <h2 className="text-sm font-semibold text-ink">Client Bank Details</h2>
        <p className="text-xs text-ink-muted">
          The account number is encrypted at rest and shown as last-4 only after saving (Data Protection Act).
        </p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className={labelCls}>Bank</label>
            <input value={form.bank_name} onChange={e => set('bank_name', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Branch Name</label>
            <input value={form.branch_name} onChange={e => set('branch_name', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Branch Code (optional)</label>
            <input value={form.branch_code} onChange={e => set('branch_code', e.target.value)} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>{isEdit ? 'Account Number' : 'Account Number *'}</label>
            <input value={form.account_number} onChange={e => set('account_number', e.target.value)}
              className={inputCls} autoComplete="off"
              placeholder={isEdit && loaded?.account_last4 ? `Currently •••• ${loaded.account_last4} — type to replace` : ''} />
            {isEdit && (
              <p className="text-xs text-ink-faint mt-1">Leave blank to keep the saved account number.</p>
            )}
          </div>
        </div>
      </div>

      <div className="bg-surface rounded-lg shadow-sm p-5 space-y-4">
        <h2 className="text-sm font-semibold text-ink">Supporting Documents (SOP)</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className={labelCls}>{isEdit ? 'Bank Proof (attach only if replacing)' : 'Bank Proof * (always required)'}</label>
            <select value={bankProofType} onChange={e => setBankProofType(e.target.value as RefundDocType)} className={`${inputCls} mb-2`}>
              <option value="bank_statement">Bank statement</option>
              <option value="bank_confirmation">Bank confirmation letter</option>
            </select>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png"
              onChange={e => setBankProof(e.target.files?.[0] ?? null)}
              className="block w-full text-sm text-ink-muted file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-brand-navy file:text-white" />
          </div>
          <div>
            <label className="flex items-center gap-2 text-sm text-ink mb-2">
              <input type="checkbox" checked={form.vehicle_not_client}
                onChange={e => set('vehicle_not_client', e.target.checked)} className="rounded" />
              Vehicle is <b>not</b> registered to the client
            </label>
            {form.vehicle_not_client && (
              <>
                <label className={labelCls}>Signed Affidavit * (required)</label>
                <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                  onChange={e => setAffidavit(e.target.files?.[0] ?? null)}
                  className="block w-full text-sm text-ink-muted file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-brand-navy file:text-white" />
              </>
            )}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-3">
        <button onClick={handleSave} disabled={saving}
          className="px-4 py-2 rounded bg-brand-navy text-white text-sm font-medium hover:opacity-90 disabled:opacity-50">
          {saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Save Draft + Documents')}
        </button>
        <Link to={isEdit ? `/finance/refund-engine/${editId}` : '/finance/refund-engine'}
          className="text-sm text-ink-muted hover:underline">Cancel</Link>
      </div>
    </div>
  )
}
