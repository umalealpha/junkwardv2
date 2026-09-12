import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  refundPayment,
  listRefunds,
  getRefund,
  createBulkRefundFromRows,
  createBulkRefundFromCsv,
  listBulkBatches,
  getBulkBatch,
  runBulkBatch,
  type PaymentRefund,
  type BulkRefundBatch,
} from '../api/refunds'

export function useRefundsList(params: {
  policy_number?: string
  customer_id?: number
  status?: string
  batch_id?: number
  tx_id?: number
  from?: string
  to?: string
  per_page?: number
  page?: number
} = {}) {
  return useQuery({
    queryKey: ['refunds', params],
    queryFn: () => listRefunds(params),
    staleTime: 15_000,
  })
}

export function useRefund(refundId: number | null | undefined) {
  return useQuery({
    queryKey: ['refund', refundId],
    queryFn: () => getRefund(refundId!),
    enabled: !!refundId,
  })
}

export function useRefundPayment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { paymentTransactionId: number; amount?: number; reason?: string; reason_code?: string }) =>
      refundPayment(vars.paymentTransactionId, {
        amount: vars.amount,
        reason: vars.reason,
        reason_code: vars.reason_code,
      }),
    onSuccess: (row: PaymentRefund) => {
      qc.invalidateQueries({ queryKey: ['refunds'] })
      qc.invalidateQueries({ queryKey: ['payment-transactions'] })
      qc.invalidateQueries({ queryKey: ['policy-transactions', row.policy_number] })
    },
  })
}

export function useBulkBatches(params: { status?: string; per_page?: number; page?: number } = {}) {
  return useQuery({
    queryKey: ['bulk-refund-batches', params],
    queryFn: () => listBulkBatches(params),
    staleTime: 15_000,
  })
}

export function useBulkBatch(batchId: number | null | undefined, pollMs = 0) {
  return useQuery({
    queryKey: ['bulk-refund-batch', batchId],
    queryFn: () => getBulkBatch(batchId!),
    enabled: !!batchId,
    refetchInterval: pollMs > 0 ? pollMs : false,
  })
}

export function useCreateBulkRefundFromRows() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: createBulkRefundFromRows,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bulk-refund-batches'] }),
  })
}

export function useCreateBulkRefundFromCsv() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: createBulkRefundFromCsv,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bulk-refund-batches'] }),
  })
}

export function useRunBulkBatch() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: runBulkBatch,
    onSuccess: (_r, batchId) => {
      qc.invalidateQueries({ queryKey: ['bulk-refund-batch', batchId] })
      qc.invalidateQueries({ queryKey: ['bulk-refund-batches'] })
    },
  })
}

export type { PaymentRefund, BulkRefundBatch }
