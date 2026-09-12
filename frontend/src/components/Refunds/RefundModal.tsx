import { useState, useEffect } from 'react'
import { useRefundPayment } from '../../hooks/useRefunds'

interface Props {
  open: boolean
  onClose: () => void
  /** The payment_transactions.id to refund. */
  paymentTransactionId: number
  /** The original charge amount — used as default and max for the input. */
  originalAmount: number
  /** How much has already been refunded — limits the max. */
  alreadyRefunded?: number
  /** Optional display metadata for the user. */
  policyNumber?: string
  customerName?: string
  onSuccess?: (refund: { id: number; status: string; dpo_refund_reference: string | null }) => void
}

const REASON_CODES = [
  { value: 'wrong_customer',   label: 'Wrong customer debited' },
  { value: 'duplicate_charge', label: 'Duplicate charge' },
  { value: 'goodwill',         label: 'Goodwill / complaint resolution' },
  { value: 'policy_cancelled', label: 'Policy cancelled, refund due' },
  { value: 'other',            label: 'Other (explain in notes)' },
]

/**
 * Single-payment refund modal. Triggers DPO refundToken via the admin API.
 *
 * Wire into any transaction detail view:
 *   <button onClick={() => setRefundOpen(true)}>Refund</button>
 *   <RefundModal open={refundOpen} onClose={...} paymentTransactionId={tx.id} originalAmount={tx.amount} />
 */
export default function RefundModal(props: Props) {
  const {
    open, onClose, paymentTransactionId, originalAmount,
    alreadyRefunded = 0, policyNumber, customerName, onSuccess,
  } = props

  const maxRefundable = Math.max(0, originalAmount - alreadyRefunded)

  const [amount, setAmount] = useState<string>(maxRefundable.toFixed(2))
  const [reasonCode, setReasonCode] = useState<string>('wrong_customer')
  const [reason, setReason] = useState<string>('')
  const [confirm, setConfirm] = useState(false)
  const { mutate, isPending, error, data } = useRefundPayment()

  useEffect(() => {
    if (open) {
      setAmount(maxRefundable.toFixed(2))
      setReasonCode('wrong_customer')
      setReason('')
      setConfirm(false)
    }
  }, [open, maxRefundable])

  if (!open) return null

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const amt = parseFloat(amount)
    if (!Number.isFinite(amt) || amt <= 0 || amt > maxRefundable) return

    mutate(
      {
        paymentTransactionId,
        amount: amt,
        reason_code: reasonCode,
        reason: reason || undefined,
      },
      {
        onSuccess: (row) => {
          if (row.status === 'succeeded') {
            onSuccess?.({ id: row.id, status: row.status, dpo_refund_reference: row.dpo_refund_reference })
          }
        },
      }
    )
  }

  const result = data
  const succeeded = result?.status === 'succeeded'
  const failed    = result?.status === 'failed'

  return (
    <div
      className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10 overflow-y-auto"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div className="px-5 py-3 border-b flex items-center justify-between shrink-0">
          <h3 className="font-semibold text-gray-800">Refund DPO Payment</h3>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl leading-none cursor-pointer">×</button>
        </div>

        <form onSubmit={handleSubmit} className="px-5 py-3 space-y-3 overflow-y-auto grow min-h-0">
          {/* Context panel */}
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs space-y-1">
            {policyNumber && <div><span className="text-gray-500">Policy:</span> <span className="font-mono">{policyNumber}</span></div>}
            {customerName && <div><span className="text-gray-500">Customer:</span> {customerName}</div>}
            <div><span className="text-gray-500">Original amount:</span> P{originalAmount.toFixed(2)}</div>
            {alreadyRefunded > 0 && <div><span className="text-gray-500">Already refunded:</span> P{alreadyRefunded.toFixed(2)}</div>}
            <div><span className="text-gray-500">Max refundable:</span> <strong>P{maxRefundable.toFixed(2)}</strong></div>
          </div>

          {!succeeded && (
            <>
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">Refund amount (P)</label>
                <input
                  type="number"
                  step="0.01"
                  min={0}
                  max={maxRefundable}
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  disabled={isPending}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                />
                <p className="text-xs text-gray-500 mt-1">Enter 0 or blank for a full refund. Partial refunds are supported.</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">Reason code</label>
                <select
                  value={reasonCode}
                  onChange={(e) => setReasonCode(e.target.value)}
                  disabled={isPending}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                >
                  {REASON_CODES.map((r) => (
                    <option key={r.value} value={r.value}>{r.label}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">Notes (optional)</label>
                <textarea
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  disabled={isPending}
                  rows={3}
                  maxLength={500}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                  placeholder="Ticket ref, call log, context…"
                />
              </div>

              <label className="flex items-start gap-2 text-sm text-gray-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                <input
                  type="checkbox"
                  checked={confirm}
                  onChange={(e) => setConfirm(e.target.checked)}
                  disabled={isPending}
                  className="mt-0.5"
                />
                <span>
                  I confirm this refund. DPO will credit the original card and will charge a small processing fee.
                  The action is logged and cannot be un-done.
                </span>
              </label>
            </>
          )}

          {/* Result banners */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-sm text-red-700">
              {(error as any)?.response?.data?.error ?? (error as any)?.message ?? 'Refund request failed'}
            </div>
          )}
          {succeeded && (
            <div className="bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-sm text-green-800">
              Refund succeeded. DPO reference <code className="font-mono">{result?.dpo_refund_reference ?? 'pending'}</code>.
            </div>
          )}
          {failed && (
            <div className="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-sm text-red-700">
              DPO rejected this refund: {result?.dpo_result_explanation || 'unknown'} (code {result?.dpo_result_code || '—'}).
            </div>
          )}
        </form>

        <div className="flex justify-end gap-2 px-5 py-4 border-t bg-gray-50">
          <button onClick={onClose} className="px-4 py-2 text-sm border rounded hover:bg-gray-100">
            {succeeded || failed ? 'Close' : 'Cancel'}
          </button>
          {!succeeded && !failed && (
            <button
              onClick={handleSubmit as any}
              disabled={isPending || !confirm || !parseFloat(amount) || parseFloat(amount) > maxRefundable}
              className="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
            >
              {isPending ? 'Refunding…' : 'Issue refund'}
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
