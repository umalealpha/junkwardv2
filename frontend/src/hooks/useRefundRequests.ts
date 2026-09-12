import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  listRefundRequests,
  getRefundRequest,
  getRefundMetrics,
  createRefundRequest,
  updateRefundRequest,
  uploadRefundDocument,
  submitRefundRequest,
  resetRefundRequestToDraft,
  reviewRefundRequest,
  approveRefundRequest,
  rejectRefundRequest,
  escalateRefundRequest,
  cfoApproveRefundRequest,
  deleteRefundRequest,
  restoreRefundRequest,
  settleRefundManually,
  undoManualSettlement,
  assignRefundRequest,
  listAssignableUsers,
  listRefundAccounting,
  postRefundAccounting,
  dismissRefundAccounting,
  type RefundListParams,
  type RefundCreatePayload,
  type RefundDocType,
} from '../api/refundRequests'

export function useRefundRequestsList(params: RefundListParams = {}) {
  return useQuery({
    queryKey: ['refund-requests', params],
    queryFn: () => listRefundRequests(params),
    staleTime: 15_000,
  })
}

export function useRefundRequest(id: number | null | undefined) {
  return useQuery({
    queryKey: ['refund-request', id],
    queryFn: () => getRefundRequest(id!),
    enabled: !!id,
  })
}

export function useRefundMetrics() {
  return useQuery({
    queryKey: ['refund-metrics'],
    queryFn: getRefundMetrics,
    staleTime: 30_000,
  })
}

function useInvalidate() {
  const qc = useQueryClient()
  return (id?: number) => {
    qc.invalidateQueries({ queryKey: ['refund-requests'] })
    qc.invalidateQueries({ queryKey: ['refund-metrics'] })
    if (id) qc.invalidateQueries({ queryKey: ['refund-request', id] })
  }
}

export function useCreateRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (payload: RefundCreatePayload) => createRefundRequest(payload),
    onSuccess: () => invalidate(),
  })
}

export function useUpdateRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<RefundCreatePayload> }) =>
      updateRefundRequest(vars.id, vars.payload),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useUploadRefundDocument() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; file: File; docType: RefundDocType }) =>
      uploadRefundDocument(vars.id, vars.file, vars.docType),
    onSuccess: (_d, vars) => invalidate(vars.id),
  })
}

export function useSubmitRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (id: number) => submitRefundRequest(id),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useResetRefundToDraft() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (id: number) => resetRefundRequestToDraft(id),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useReviewRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; comment?: string }) => reviewRefundRequest(vars.id, vars.comment ?? ''),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useApproveRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; bankAccountConfirmed: boolean; reason: string }) =>
      approveRefundRequest(vars.id, vars.bankAccountConfirmed, vars.reason),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useRejectRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; reason: string; missingDocs?: string[] }) =>
      rejectRefundRequest(vars.id, vars.reason, vars.missingDocs ?? []),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useEscalateRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; reason: string }) =>
      escalateRefundRequest(vars.id, vars.reason),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useCfoApproveRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; bankAccountConfirmed?: boolean; reason: string; overrideFraud?: boolean }) =>
      cfoApproveRefundRequest(vars.id, vars.bankAccountConfirmed ?? false, vars.reason, vars.overrideFraud ?? false),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useDeleteRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; reason: string }) => deleteRefundRequest(vars.id, vars.reason),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useRestoreRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; reason?: string }) => restoreRefundRequest(vars.id, vars.reason ?? ''),
    onSuccess: (r) => invalidate(r.id),
  })
}

/**
 * Record that Finance already paid the client outside Graphite. No money moves;
 * the request leaves the payout queue so it can never be paid a second time.
 */
export function useSettleRefundManually() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; reason: string; paid_at?: string | null; paid_ref?: string | null }) =>
      settleRefundManually(vars.id, { reason: vars.reason, paid_at: vars.paid_at, paid_ref: vars.paid_ref }),
    onSuccess: (r) => invalidate(r.id),
  })
}

/** Undo a manual-payment record made in error. */
export function useUndoManualSettlement() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; reason: string }) => undoManualSettlement(vars.id, vars.reason),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useAssignRefundRequest() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (vars: { id: number; userId: number }) => assignRefundRequest(vars.id, vars.userId),
    onSuccess: (r) => invalidate(r.id),
  })
}

export function useAssignableUsers(id: number | null | undefined, enabled = true) {
  return useQuery({
    queryKey: ['refund-assignable-users', id],
    queryFn: () => listAssignableUsers(id!),
    enabled: !!id && enabled,
    staleTime: 60_000,
  })
}

export function useRefundAccountingList(params: { status?: string; policy_number?: string; page?: number } = {}) {
  return useQuery({
    queryKey: ['refund-accounting', params],
    queryFn: () => listRefundAccounting(params),
    staleTime: 15_000,
  })
}

export function usePostRefundAccounting() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { id: number; overrides?: { earned_premium?: number; unearned_premium?: number; effective_date?: string; end_date?: string } }) =>
      postRefundAccounting(vars.id, vars.overrides ?? {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['refund-accounting'] }),
  })
}

export function useDismissRefundAccounting() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { id: number; reason: string }) => dismissRefundAccounting(vars.id, vars.reason),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['refund-accounting'] }),
  })
}
