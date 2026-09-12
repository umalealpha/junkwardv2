import { useState } from 'react'
import Modal from '../common/Modal'
import { useToast } from '../common/Toast'
import { useCanRequestBackdate, useSubmitBackdateRequest } from '../../hooks/useClaimsBackdate'

interface Props {
  claimId: number
  claimNumber?: string | null
}

/**
 * "Request backdate window" affordance for request-role users (Claims Manager).
 * Rendered only when the `claims_backdate_governance` flag is ON and the user
 * may request (useCanRequestBackdate). Submits a self-service request for a
 * time-limited grant covering this claim; an admin approves it on the Backdate
 * Control screen. Hidden entirely (renders null) when not applicable — so with
 * the flag off it never appears.
 */
export default function RequestBackdateButton({ claimId, claimNumber }: Props) {
  const canRequest = useCanRequestBackdate()
  const { toast } = useToast()
  const submit = useSubmitBackdateRequest()

  const [open, setOpen] = useState(false)
  const [reason, setReason] = useState('')
  const [hours, setHours] = useState('24')
  const [urgent, setUrgent] = useState(false)

  if (!canRequest) return null

  async function handleSubmit() {
    if (reason.trim().length < 10) {
      toast.error('Reason must be at least 10 characters.')
      return
    }
    try {
      await submit.mutateAsync({
        claim_ids: [claimId],
        reason: reason.trim(),
        duration_hours: Number(hours),
        urgency: urgent ? 'urgent' : 'normal',
      })
      toast.success('Backdate request submitted for approval.')
      setOpen(false)
      setReason('')
    } catch (err: any) {
      toast.error(err?.response?.data?.error || 'Failed to submit the request.')
    }
  }

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition"
      >
        Request backdate
      </button>

      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title="Request backdate window"
        size="md"
        footer={
          <div className="flex justify-end gap-2">
            <button
              onClick={() => setOpen(false)}
              className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition"
            >
              Cancel
            </button>
            <button
              onClick={handleSubmit}
              disabled={submit.isPending}
              className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 transition disabled:opacity-50"
            >
              {submit.isPending ? 'Submitting…' : 'Submit request'}
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          <p className="text-sm text-ink-muted">
            Request a time-limited window to backdate stage dates on claim{' '}
            <span className="font-medium text-ink">{claimNumber || claimId}</span>. An admin must approve it before you can save a backdated date.
          </p>
          <div>
            <label htmlFor="rbd-reason" className="block text-[11px] font-medium text-ink-muted mb-1">Reason (min 10 chars)</label>
            <textarea
              id="rbd-reason"
              rows={3}
              value={reason}
              maxLength={2000}
              onChange={(e) => setReason(e.target.value)}
              className="w-full px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
            />
          </div>
          <div className="grid grid-cols-2 gap-4 items-end">
            <div>
              <label htmlFor="rbd-hours" className="block text-[11px] font-medium text-ink-muted mb-1">Window (hours)</label>
              <input
                id="rbd-hours"
                type="number"
                min={1}
                max={168}
                value={hours}
                onChange={(e) => setHours(e.target.value)}
                className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-ink min-h-[44px] md:min-h-0">
              <input type="checkbox" checked={urgent} onChange={(e) => setUrgent(e.target.checked)} />
              Mark urgent
            </label>
          </div>
        </div>
      </Modal>
    </>
  )
}
