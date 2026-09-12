import apiClient from './client'

/** Single DPO refund against an existing successful payment_transaction. */
export interface PaymentRefund {
  id: number
  payment_transaction_id: number
  policy_number: string | null
  customer_id: number | null
  amount: string | number
  currency: string
  reason: string | null
  reason_code: string | null
  status: 'pending' | 'submitted' | 'succeeded' | 'failed' | 'cancelled'
  refund_type: 'single' | 'bulk'
  bulk_refund_batch_id: number | null
  dpo_refund_reference: string | null
  dpo_result_code: string | null
  dpo_result_explanation: string | null
  submitted_at: string | null
  completed_at: string | null
  created_at: string
}

export interface BulkRefundBatch {
  id: number
  name: string
  description: string | null
  reason_code: string | null
  reason: string | null
  source: 'csv' | 'filter' | 'manual'
  status: 'draft' | 'queued' | 'processing' | 'completed' | 'failed' | 'cancelled'
  total_count: number
  success_count: number
  failed_count: number
  pending_count: number
  total_amount: string | number
  refunded_amount: string | number
  created_at: string
  started_at: string | null
  completed_at: string | null
}

// ─── Single refund ────────────────────────────────────────────────────────────

export async function refundPayment(
  paymentTransactionId: number,
  params: { amount?: number; reason?: string; reason_code?: string }
) {
  const { data } = await apiClient.post(
    `/payments/${paymentTransactionId}/refund`,
    params
  )
  return data.data as PaymentRefund
}

export async function listRefunds(params: {
  policy_number?: string
  customer_id?: number
  tx_id?: number
  status?: string
  batch_id?: number
  from?: string
  to?: string
  per_page?: number
  page?: number
} = {}) {
  const { data } = await apiClient.get('/payments/refunds', { params })
  return data as {
    data: PaymentRefund[]
    current_page: number
    last_page: number
    total: number
    per_page: number
  }
}

export async function getRefund(refundId: number) {
  const { data } = await apiClient.get(`/payments/refunds/${refundId}`)
  return data.data as PaymentRefund
}

// ─── Bulk refund ──────────────────────────────────────────────────────────────

/**
 * Create a bulk batch from a list of transaction ids. Set runNow=true to
 * queue the job immediately. Otherwise call runBulkBatch(id) later.
 */
export async function createBulkRefundFromRows(params: {
  rows: Array<{ payment_transaction_id: number; amount?: number }>
  name?: string
  description?: string
  reason?: string
  reason_code?: string
  run_now?: boolean
}) {
  const { data } = await apiClient.post('/payments/refunds/bulk', params)
  return data as { batch_id: number; queued: boolean }
}

/** Upload a CSV (payment_transaction_id,amount) to create a bulk batch. */
export async function createBulkRefundFromCsv(params: {
  file: File
  name?: string
  description?: string
  reason?: string
  reason_code?: string
  run_now?: boolean
}) {
  const fd = new FormData()
  fd.append('file', params.file)
  if (params.name)        fd.append('name', params.name)
  if (params.description) fd.append('description', params.description)
  if (params.reason)      fd.append('reason', params.reason)
  if (params.reason_code) fd.append('reason_code', params.reason_code)
  if (params.run_now)     fd.append('run_now', '1')

  const { data } = await apiClient.post('/payments/refunds/bulk', fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data as { batch_id: number; queued: boolean }
}

export async function listBulkBatches(params: {
  status?: string
  per_page?: number
  page?: number
} = {}) {
  const { data } = await apiClient.get('/payments/refunds/bulk', { params })
  return data as {
    data: BulkRefundBatch[]
    current_page: number
    last_page: number
    total: number
    per_page: number
  }
}

export async function getBulkBatch(batchId: number) {
  const { data } = await apiClient.get(`/payments/refunds/bulk/${batchId}`)
  return data as { batch: BulkRefundBatch; rows: PaymentRefund[] }
}

export async function runBulkBatch(batchId: number) {
  const { data } = await apiClient.post(`/payments/refunds/bulk/${batchId}/run`)
  return data as { queued: boolean; batch_id: number }
}

export function bulkBatchCsvUrl(batchId: number): string {
  // apiClient.baseURL points at /api/v1; we return the full URL so browser can download.
  const base = (apiClient.defaults.baseURL || '').replace(/\/$/, '')
  return `${base}/payments/refunds/bulk/${batchId}/csv`
}
