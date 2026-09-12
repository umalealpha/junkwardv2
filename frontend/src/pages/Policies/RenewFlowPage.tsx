import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { useToast } from '../../components/common/Toast'
import { fmtPula } from '../../utils/format'
import {
  fetchMisRenewFlow,
  moveToRenew,
  generateRenewLink,
  payRenewalCash,
  payRenewalRealpay,
  renewNoPayment,
} from '../../api/renewals'
import { fetchRealpayBanks, fetchRealpayBranches } from '../../api/realpay'

/**
 * Per-policy MIS (Motor Comprehensive, product 3) renewal flow — React port of
 * graphiteBWV8's Blade "Policy Renewal" page (renewPolicy.blade.php) plus its
 * "Add Offline Payment" (Cash) and "Add RealPay Payment" screens. Backed by the
 * Api/V1/RenewalController endpoints. Reached from the "Renew" header button.
 */
type Mode = 'view' | 'cash' | 'realpay' | 'none'
const FREQ_OPTS = [
  { value: '1', label: 'Monthly' },
  { value: '2', label: 'Three Installments' },
  { value: '3', label: 'Annual' },
]

export default function RenewFlowPage() {
  const { id } = useParams<{ id: string }>()
  const policyId = Number(id)
  const qc = useQueryClient()
  const navigate = useNavigate()
  const { toast } = useToast()

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['misRenewFlow', policyId],
    queryFn: () => fetchMisRenewFlow(policyId),
    enabled: !!policyId,
  })

  const [mode, setMode] = useState<Mode>('view')
  const [busy, setBusy] = useState<string | null>(null)

  // Renew page inputs (carried into the payment as new term metadata).
  const [termStartDate, setTermStartDate] = useState('')
  const [agentId, setAgentId] = useState('')

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['misRenewFlow', policyId] })
    qc.invalidateQueries({ queryKey: ['policy', String(policyId)] })
    refetch()
  }

  const run = async (key: string, fn: () => Promise<{ message?: string }>) => {
    setBusy(key)
    try {
      const res = await fn()
      toast.success(res?.message || 'Done.')
      invalidate()
    } catch (e: any) {
      toast.error(e?.response?.data?.message || e?.message || 'Action failed.')
    } finally {
      setBusy(null)
    }
  }

  if (isLoading) return <div className="p-6"><LoadingSpinner /></div>
  if (isError || !data) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center text-red-600">
          Failed to load the renewal flow for this policy.
        </div>
        <Link to={`/policies/${policyId}`} className="inline-block mt-4 text-sm text-brand-navy hover:underline">&larr; Back to policy</Link>
      </div>
    )
  }

  const { vehicle, premium, agents } = data
  const newPremium = premium?.newPremium ?? null

  const onPaid = (res: { message?: string }) => {
    toast.success(res?.message || 'Policy renewed successfully.')
    setMode('view')
    invalidate()
    // Return to the policy page so the operator sees the refreshed policy.
    navigate(`/policies/${policyId}`)
  }

  return (
    <div className="p-6 space-y-4 max-w-4xl">
      <div>
        <Link to={`/policies/${policyId}`} className="text-xs text-brand-navy hover:underline">&larr; Back to policy</Link>
        <h1 className="text-2xl font-bold text-gray-800 mt-1">
          Policy Renewal {data.policy?.policyNumber ? `— ${data.policy.policyNumber}` : ''}
        </h1>
      </div>

      {data.canMoveToRenew && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center justify-between gap-4">
          <p className="text-sm text-amber-800">{data.message || 'This policy must be moved to renew first.'}</p>
          <button
            type="button"
            onClick={() => run('move', () => moveToRenew(policyId))}
            disabled={busy === 'move'}
            className="px-4 py-2 text-sm font-medium text-white bg-brand-navy rounded-md hover:bg-brand-navy/90 disabled:opacity-50 whitespace-nowrap"
          >
            {busy === 'move' ? 'Moving…' : 'Move to Renew'}
          </button>
        </div>
      )}

      {data.needsRerate && !data.canMoveToRenew && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
          {data.message || 'Rerate the policy before renewing.'}
        </div>
      )}

      {/* Vehicle + Premium read views (always visible, like the V8 page) */}
      {vehicle && (
        <section className="bg-white border border-gray-200 rounded-lg p-5">
          <h2 className="text-base font-semibold text-gray-800 mb-3">Vehicle Details</h2>
          <dl className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
            <Row label="Japanese Import" value={vehicle.japaneseImport} />
            <Row label="Make" value={vehicle.make} />
            <Row label="Manufacturing Year" value={vehicle.manufacturingYear} />
            <Row label="Model" value={vehicle.model} />
            <Row label="Estimated value of vehicle" value={vehicle.estimatedValue} />
            <Row label="Number of prior accidents" value={vehicle.priorAccidents} />
            <Row label="Condition" value={vehicle.condition} />
            <Row label="Mileage" value={vehicle.mileage} />
            <Row label="Purpose" value={vehicle.purpose} />
          </dl>
        </section>
      )}

      {premium && (
        <section className="bg-white border border-gray-200 rounded-lg p-5">
          <h2 className="text-base font-semibold text-gray-800 mb-3">Premium Details</h2>
          <dl className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
            <Row label="Old Premium" value={money(premium.oldPremium)} />
            <Row label="Old Frequency" value={premium.oldFrequencyLabel} />
            <Row label="Old Sum Insured" value={premium.oldSumInsured} />
            <Row label="New Premium" value={money(premium.newPremium)} />
            <Row label="New Monthly Premium" value={money(premium.newMonthlyPremium)} />
            <Row label="New Three Installment Premium" value={money(premium.newThreeInstallmentPremium)} />
            <Row label="New Sum Insured" value={premium.newSumInsured} />
          </dl>
        </section>
      )}

      {/* ── VIEW MODE: term/agent inputs + action buttons ────────────────── */}
      {mode === 'view' && !data.canMoveToRenew && (
        <section className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <Field label="Term Start Date">
              <input type="date" value={termStartDate} onChange={(e) => setTermStartDate(e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </Field>
            <Field label="Renew By">
              <select value={agentId} onChange={(e) => setAgentId(e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white">
                <option value="">Select agent</option>
                {(agents || []).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </select>
            </Field>
          </div>

          <div className="flex flex-wrap gap-3 items-center border-t border-gray-100 pt-4">
            <span className="text-base font-semibold text-gray-800 mr-2">Payment Method:</span>
            <button
              type="button"
              onClick={() => navigate(`/policies/${policyId}?tab=reratePremium`)}
              className="px-4 py-2 text-sm font-medium text-brand-navy border border-brand-navy/30 rounded-md hover:bg-brand-navy/5"
            >
              Rerate Premium
            </button>
            <button
              type="button"
              onClick={() => run('link', () => generateRenewLink(policyId))}
              disabled={busy === 'link'}
              className="px-4 py-2 text-sm font-medium text-brand-navy border border-brand-navy/30 rounded-md hover:bg-brand-navy/5 disabled:opacity-50"
            >
              {busy === 'link' ? 'Sending…' : 'Generate Renew Link'}
            </button>
            <button
              type="button"
              onClick={() => setMode('cash')}
              className="px-4 py-2 text-sm font-medium text-white bg-brand-navy rounded-md hover:bg-brand-navy/90"
            >
              Pay with Cash
            </button>
            <button
              type="button"
              onClick={() => setMode('realpay')}
              className="px-4 py-2 text-sm font-medium text-white bg-brand-navy rounded-md hover:bg-brand-navy/90"
            >
              Pay with Realpay
            </button>
            <button
              type="button"
              onClick={() => setMode('none')}
              className="px-4 py-2 text-sm font-medium text-brand-navy border border-brand-navy/30 rounded-md hover:bg-brand-navy/5"
            >
              Renew Without Payment
            </button>
          </div>
        </section>
      )}

      {/* ── CASH FORM ─────────────────────────────────────────────────────── */}
      {mode === 'cash' && (
        <CashForm
          policyId={policyId}
          newPremium={newPremium}
          termStartDate={termStartDate}
          agentId={agentId}
          onCancel={() => setMode('view')}
          onPaid={onPaid}
        />
      )}

      {/* ── REALPAY FORM ──────────────────────────────────────────────────── */}
      {mode === 'realpay' && (
        <RealpayForm
          policyId={policyId}
          newPremium={newPremium}
          termStartDate={termStartDate}
          agentId={agentId}
          onCancel={() => setMode('view')}
          onPaid={onPaid}
        />
      )}

      {/* ── NO-PAYMENT FORM (renew without taking payment) ─────────────────── */}
      {mode === 'none' && (
        <NoPaymentForm
          policyId={policyId}
          newPremium={newPremium}
          termStartDate={termStartDate}
          agentId={agentId}
          onCancel={() => setMode('view')}
          onPaid={onPaid}
        />
      )}
    </div>
  )
}

// ─── Cash form (Add Offline Payment) ─────────────────────────────────────────
function CashForm(props: {
  policyId: number; newPremium: number | string | null; termStartDate: string; agentId: string
  onCancel: () => void; onPaid: (r: { message?: string }) => void
}) {
  const { policyId, newPremium, termStartDate, agentId, onCancel, onPaid } = props
  const { toast } = useToast()
  const [submitting, setSubmitting] = useState(false)
  const [f, setF] = useState({
    paymentDate: '', paymentAmount: '', paymentFreq: '', receiptNumber: '',
    paymentRecievedBy: '', numberOfInstalmentsPaid: '', paymentNote: '',
  })
  const [file, setFile] = useState<File | null>(null)
  const set = (k: keyof typeof f) => (e: any) => setF({ ...f, [k]: e.target.value })

  const submit = async () => {
    if (!f.paymentDate || !f.paymentAmount || !f.paymentFreq) {
      toast.warning('Date of payment, payment amount and frequency are required.')
      return
    }
    setSubmitting(true)
    try {
      const fd = new FormData()
      fd.append('paymentDate', f.paymentDate)
      fd.append('paymentAmount', f.paymentAmount)
      fd.append('paymentFreq', f.paymentFreq)
      fd.append('receiptNumber', f.receiptNumber)
      fd.append('paymentRecievedBy', f.paymentRecievedBy)
      fd.append('numberOfInstalmentsPaid', f.numberOfInstalmentsPaid || '1')
      fd.append('paymentNote', f.paymentNote)
      fd.append('new_premium', String(newPremium ?? ''))
      fd.append('term_start_date', termStartDate)
      fd.append('agent_id', agentId)
      if (file) fd.append('payment_image', file)
      const res = await payRenewalCash(policyId, fd)
      onPaid(res)
    } catch (e: any) {
      toast.error(e?.response?.data?.message || e?.message || 'Failed to record payment.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
      <h2 className="text-base font-semibold text-gray-800">Add Offline Payment</h2>
      <Field label="Date of payment">
        <input type="date" value={f.paymentDate} onChange={set('paymentDate')} className={inputCls} />
      </Field>
      <Field label="Payment Amount">
        <input type="number" value={f.paymentAmount} onChange={set('paymentAmount')} placeholder="Please provide amount" className={inputCls} />
      </Field>
      <Field label="Payment Frequency">
        <select value={f.paymentFreq} onChange={set('paymentFreq')} className={inputCls}>
          <option value="">Select payment frequency</option>
          {FREQ_OPTS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </Field>
      <Field label="Receipt number">
        <input value={f.receiptNumber} onChange={set('receiptNumber')} placeholder="Please provide payment receipt number" className={inputCls} />
      </Field>
      <Field label="Payment Received By">
        <input value={f.paymentRecievedBy} onChange={set('paymentRecievedBy')} placeholder="Please provide payment recipient" className={inputCls} />
      </Field>
      <Field label="Numbers of Installments paid">
        <input type="number" value={f.numberOfInstalmentsPaid} onChange={set('numberOfInstalmentsPaid')} placeholder="Please provide the number of Installments paid" className={inputCls} />
      </Field>
      <Field label="Note">
        <textarea value={f.paymentNote} onChange={set('paymentNote')} placeholder="Please provide note" className={inputCls} rows={2} />
      </Field>
      <Field label="Upload Payment Proof">
        <input type="file" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="text-sm" />
      </Field>
      <FormActions submitting={submitting} onCancel={onCancel} onSubmit={submit} />
    </section>
  )
}

// ─── No-payment form (renew without taking payment) ──────────────────────────
// Mirrors the cash renewal but records no payment: just an editable premium and
// a frequency, then submit. The policy term is created; the policy is NOT
// activated (it stays inactive until a payment is later recorded).
function NoPaymentForm(props: {
  policyId: number; newPremium: number | string | null; termStartDate: string; agentId: string
  onCancel: () => void; onPaid: (r: { message?: string }) => void
}) {
  const { policyId, newPremium, termStartDate, agentId, onCancel, onPaid } = props
  const { toast } = useToast()
  const [submitting, setSubmitting] = useState(false)
  const [premium, setPremium] = useState(newPremium != null ? String(newPremium) : '')
  const [paymentFreq, setPaymentFreq] = useState('')

  const submit = async () => {
    if (!termStartDate) {
      toast.warning('Select a Term Start Date on the renewal page first.')
      return
    }
    if (!premium || Number(premium) <= 0) {
      toast.warning('Enter a valid premium.')
      return
    }
    if (!paymentFreq) {
      toast.warning('Select a payment frequency.')
      return
    }
    setSubmitting(true)
    try {
      const res = await renewNoPayment(policyId, {
        new_premium: premium,
        paymentFreq,
        term_start_date: termStartDate,
        agent_id: agentId,
      })
      onPaid(res)
    } catch (e: any) {
      toast.error(e?.response?.data?.message || e?.message || 'Failed to renew policy.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
      <h2 className="text-base font-semibold text-gray-800">Renew Without Payment</h2>
      <p className="text-sm text-gray-500">
        Renews the policy term using the premium below and activates the policy. No payment is recorded.
      </p>
      <Field label="Premium">
        <input type="number" value={premium} onChange={(e) => setPremium(e.target.value)}
          placeholder="Please provide premium" className={inputCls} />
      </Field>
      <Field label="Payment Frequency">
        <select value={paymentFreq} onChange={(e) => setPaymentFreq(e.target.value)} className={inputCls}>
          <option value="">Select payment frequency</option>
          {FREQ_OPTS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </Field>
      <FormActions submitting={submitting} onCancel={onCancel} onSubmit={submit} />
    </section>
  )
}

// ─── RealPay form (Add RealPay Payment) ──────────────────────────────────────
function RealpayForm(props: {
  policyId: number; newPremium: number | string | null; termStartDate: string; agentId: string
  onCancel: () => void; onPaid: (r: { message?: string }) => void
}) {
  const { policyId, newPremium, termStartDate, agentId, onCancel, onPaid } = props
  const { toast } = useToast()
  const [submitting, setSubmitting] = useState(false)
  const [bankId, setBankId] = useState('')   // RealpayBank.id (for branch lookup)
  const [f, setF] = useState({
    billingDay: '', frequency: '', first_premium: '', first_collection_date: '',
    BankName: '', BranchCode: '', accountType: '', accountNumber: '',
  })
  const set = (k: keyof typeof f) => (e: any) => setF({ ...f, [k]: e.target.value })

  const { data: banks } = useQuery({ queryKey: ['realpayBanks'], queryFn: fetchRealpayBanks })
  const { data: branches } = useQuery({
    queryKey: ['realpayBranches', bankId],
    queryFn: () => fetchRealpayBranches(Number(bankId)),
    enabled: !!bankId,
  })

  const onBank = (e: any) => {
    const sel = (banks || []).find((b) => String(b.id) === e.target.value)
    setBankId(e.target.value)
    setF({ ...f, BankName: sel?.bank_name ?? '', BranchCode: '' })
  }

  const submit = async () => {
    if (!f.accountNumber || !f.BankName || !f.BranchCode || !f.accountType) {
      toast.warning('Bank, branch, account type and account number are required.')
      return
    }
    setSubmitting(true)
    try {
      const res = await payRenewalRealpay(policyId, {
        billingDay: f.billingDay,
        paymentStartDate: f.billingDay,
        frequency: f.frequency,
        paymentFreq: f.frequency,
        first_premium: f.first_premium,
        first_collection_date: f.first_collection_date,
        premium: newPremium,
        BankName: f.BankName,
        BranchCode: f.BranchCode,
        accountType: f.accountType,
        accountNumber: f.accountNumber,
        new_premium: newPremium,
        term_start_date: termStartDate,
        agent_id: agentId,
      })
      onPaid(res)
    } catch (e: any) {
      toast.error(e?.response?.data?.message || e?.message || 'Failed to record RealPay payment.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
      <h2 className="text-base font-semibold text-gray-800">Add RealPay Payment</h2>
      <Field label="Instalment Start Date">
        <input type="date" value={f.billingDay} onChange={set('billingDay')} className={inputCls} />
      </Field>
      <Field label="Payment Frequency">
        <select value={f.frequency} onChange={set('frequency')} className={inputCls}>
          <option value="">Select payment frequency</option>
          {FREQ_OPTS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </Field>
      <Field label="First Instalment Amount">
        <input type="number" value={f.first_premium} onChange={set('first_premium')} placeholder="Please provide premium" className={inputCls} />
      </Field>
      <Field label="First Collection Date">
        <input type="date" value={f.first_collection_date} onChange={set('first_collection_date')} className={inputCls} />
      </Field>
      <Field label="Please select bank">
        <select value={bankId} onChange={onBank} className={inputCls}>
          <option value="">Select bank</option>
          {(banks || []).map((b) => <option key={b.id} value={b.id}>{b.bank_name}</option>)}
        </select>
      </Field>
      <Field label="Please select bank branch">
        <select value={f.BranchCode} onChange={set('BranchCode')} className={inputCls} disabled={!bankId}>
          <option value="">Select branch</option>
          {(branches || []).map((b) => <option key={b.branch_id} value={b.branch_id}>{b.name} ({b.branch_id})</option>)}
        </select>
      </Field>
      <Field label="Account Type">
        <select value={f.accountType} onChange={set('accountType')} className={inputCls}>
          <option value="">Select account type</option>
          <option value="1">Cheque</option>
          <option value="2">Savings</option>
        </select>
      </Field>
      <Field label="Account Number">
        <input value={f.accountNumber} onChange={set('accountNumber')} placeholder="Please provide account number" className={inputCls} />
      </Field>
      <FormActions submitting={submitting} onCancel={onCancel} onSubmit={submit} />
    </section>
  )
}

const inputCls = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white'

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 gap-3 items-center">
      <label className="text-sm text-gray-500">{label}</label>
      <div className="col-span-2">{children}</div>
    </div>
  )
}

function FormActions({ submitting, onCancel, onSubmit }: { submitting: boolean; onCancel: () => void; onSubmit: () => void }) {
  return (
    <div className="flex gap-3 pt-3 border-t border-gray-100">
      <button type="button" onClick={onSubmit} disabled={submitting}
        className="px-5 py-2 text-sm font-medium text-white bg-brand-navy rounded-md hover:bg-brand-navy/90 disabled:opacity-50">
        {submitting ? 'Submitting…' : 'Submit'}
      </button>
      <button type="button" onClick={onCancel} disabled={submitting}
        className="px-5 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
        Cancel
      </button>
    </div>
  )
}

function Row({ label, value }: { label: string; value: unknown }) {
  return (
    <div className="flex justify-between border-b border-gray-100 py-1.5">
      <dt className="text-gray-500">{label}</dt>
      <dd className="font-medium text-gray-800 text-right">{value === null || value === undefined || value === '' ? '—' : String(value)}</dd>
    </div>
  )
}

function money(v: number | string | null | undefined): string {
  if (v === null || v === undefined || v === '') return '—'
  const n = typeof v === 'string' ? Number(v) : v
  return Number.isFinite(n) ? fmtPula(n) : String(v)
}
