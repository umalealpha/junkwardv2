import { getStoredPermissions, getStoredRoles } from '../../../api/auth'
import type { RefundArea, RefundStatus } from '../../../api/refundRequests'

/** Status pill colours — same visual language as SettlementReconciliationPage. */
export const STATUS_PILL: Record<RefundStatus, string> = {
  draft:        'bg-surface-2 text-ink',
  submitted:    'bg-blue-100 text-blue-700',
  under_review: 'bg-indigo-100 text-indigo-700',
  approved:     'bg-teal-100 text-teal-700',
  approval_pending_2: 'bg-pink-100 text-pink-700',
  cfo_pending:  'bg-amber-100 text-amber-800',
  cfo_approved: 'bg-teal-100 text-teal-700',
  rejected:     'bg-red-100 text-red-700',
  escalated:    'bg-orange-100 text-orange-700',
  handed_off:   'bg-purple-100 text-purple-700',
  paid:         'bg-green-100 text-green-700',
  posted:       'bg-green-200 text-green-800',
  settled_manual: 'bg-emerald-100 text-emerald-800',
}

export const STATUS_LABEL: Record<RefundStatus, string> = {
  draft:        'Draft',
  submitted:    'Submitted',
  under_review: 'Reviewed — awaiting approval',
  approved:     'Approved',
  approval_pending_2: 'Awaiting 2nd approver',
  cfo_pending:  'CFO Pending',
  cfo_approved: 'CFO Approved',
  rejected:     'Rejected',
  escalated:    'Escalated',
  handed_off:   'With Omni',
  paid:         'Paid',
  posted:       'Posted',
  settled_manual: 'Paid outside Graphite',
}

export const AREA_LABEL: Record<RefundArea, string> = {
  mis:        'MIS / UniCoin',
  domestic:   'Domestic',
  commercial: 'Commercial',
}

/**
 * Frontend permission gate — UX ONLY. The backend re-enforces every action
 * (route permission middleware + in-controller area scoping).
 */
export function useRefundAccess() {
  const perms = getStoredPermissions()
  const roles = getStoredRoles()
  const isSuper = roles.includes('Super Admin')
  const can = (p: string) => isSuper || perms.includes(p)

  const areas: RefundArea[] = isSuper
    ? ['mis', 'domestic', 'commercial']
    : [
        ...(perms.includes('refund_area_mis') ? (['mis'] as RefundArea[]) : []),
        ...(perms.includes('refund_area_dc') ? (['domestic', 'commercial'] as RefundArea[]) : []),
      ]

  return {
    areas,
    canCreate:     can('refund-create'),
    canSubmit:     can('refund-submit'),
    canReview:     can('refund-review'),
    canApprove:    can('refund-approve'),
    canCfoApprove: can('refund-cfo-approve'),
    /** Deputy: clears ESCALATED refunds only, and only without a CRITICAL
     *  fraud flag. The >P50k gate and the fraud override stay with the CFO
     *  (CFO 2026-09-02). The backend enforces both limits. */
    canClearEscalation: can('refund-escalation-clear'),
    canReport:     can('refund-report'),
    canPostAccounting: can('refund-accounting-post'),
  }
}

export function apiErrorMessage(e: unknown): string {
  const err = e as { response?: { data?: { error?: string; message?: string; errors?: Record<string, string[]> } } }
  const d = err?.response?.data
  if (d?.errors) {
    const first = Object.values(d.errors)[0]
    if (first?.length) return first[0]
  }
  return d?.error || d?.message || 'Something went wrong — please try again.'
}
