import apiClient from './client'

// ─── Lookups (bank + branch) ───────────────────────────────────────────

export interface RealpayBank {
  id: number
  bank_number: string
  bank_name: string
}

export interface RealpayBranch {
  branch_id: string | number
  name: string
}

export async function fetchRealpayBanks(): Promise<RealpayBank[]> {
  const { data } = await apiClient.get<{ data: RealpayBank[] }>('/realpay/banks')
  return data.data
}

export async function fetchRealpayBranches(bankId: number): Promise<RealpayBranch[]> {
  const { data } = await apiClient.get<{ data: RealpayBranch[] }>(`/realpay/banks/${bankId}/branches`)
  return data.data
}

// ─── Contracts (list + create) ─────────────────────────────────────────

export interface RealpayContract {
  id: number
  clientNumber: string
  contractNumber: string
  rateId: number | null
  status: number
  statusLabel: string
  latestInstalmentStatus: string | null
  createdAt: string
}

export async function fetchPolicyRealpayContracts(policyId: number): Promise<RealpayContract[]> {
  const { data } = await apiClient.get<{ data: RealpayContract[] }>(`/policies/${policyId}/realpay/contracts`)
  return data.data
}

export interface CreateRealpayContractPayload {
  id_type: 'omang' | 'passport'
  id_number: string
  email: string
  cellphone: string
  payment_frequency: 'monthly' | 'quarterly' | 'annual'
  premium: number
  billing_date: string                   // YYYY-MM-DD
  is_first_collection_same?: boolean
  first_collection_date?: string | null  // YYYY-MM-DD, required when is_first_collection_same=false
  is_first_instalment_same?: boolean
  first_instalment_amount?: number | null
  bank_id: number
  branch_id: number | string
  account_number: string
  account_type: 'CheckingAccount' | 'SavingsAccount'
}

export interface CreateRealpayContractResponse {
  message: string
  data: {
    contractId: number
    clientNumber: string
    contractNumber: string
    frequency: string
    numberOfInstalments: number
    firstCollectionDate: string
    firstInstalmentAmount: number
    live: boolean
    realpayOk: boolean
    realpayRef: string | null
  }
}

export async function createPolicyRealpayContract(
  policyId: number,
  payload: CreateRealpayContractPayload,
): Promise<CreateRealpayContractResponse> {
  const { data } = await apiClient.post<CreateRealpayContractResponse>(
    `/policies/${policyId}/realpay/contracts`,
    payload,
  )
  return data
}

export async function cancelPolicyRealpayContract(
  policyId: number,
  contractId: number,
): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(
    `/policies/${policyId}/realpay/contracts/${contractId}/cancel`,
  )
  return data
}

// ─── Installments (RealPay Transactions tab) ───────────────────────────

export interface RealpayInstallment {
  id: number
  clientNumber: string
  contractNumber: string
  installmentReferenceNumber: string | null
  installmentSequence: number
  ctcAmount: string | number | null
  installmentActionDate: string | null
  trackingCode: string | null
  installmentAmount: string | number | null
  installmentStatus: string | null
  installmentStatusLabel: string
  retryCount: number
  bankResponse: string | null
  note: string | null
  createdAt: string | null
}

export async function fetchPolicyRealpayInstallments(policyId: number): Promise<RealpayInstallment[]> {
  const { data } = await apiClient.get<{ data: RealpayInstallment[] }>(`/policies/${policyId}/realpay/installments`)
  return data.data
}

// ─── RealPay Transactions tab actions (V8 parity) ──────────────────────
export interface AddRealpayInstallmentPayload {
  client_number: string
  contract_number: string
  installment_date: string
  installment_premium: string | number
  // V8 parity: ContractSequence supplied by the form (optional — backend falls
  // back to the RealPay contract-info lookup when absent).
  contract_sequence?: string
}
export interface UpdateRealpayInstallmentPayload {
  client_number: string
  contract_number: string
  installment_number?: string
  installment_date?: string
  installment_premium?: string | number
}

/** Add New Installment — live RealPay API call + DB insert. */
export async function addRealpayInstallment(policyId: number, payload: AddRealpayInstallmentPayload): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${policyId}/realpay/installments`, payload)
  return data
}

/** Update Installment — queues a single-installment change (processed by cron). */
export async function updateRealpayInstallment(policyId: number, payload: UpdateRealpayInstallmentPayload): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${policyId}/realpay/installments/update`, payload)
  return data
}

/** Update All Installment — queues a bulk change across the contract (processed by cron). */
export async function updateRealpayAllInstallments(policyId: number, payload: UpdateRealpayInstallmentPayload): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${policyId}/realpay/installments/update-all`, payload)
  return data
}

// ─── Live portal check + sync ──────────────────────────────────────────

export interface RealpaySyncResult {
  exists: boolean                       // an active contract is on the RealPay portal
  synced: number                        // contracts fetched + stored this call
  contracts: RealpayContract[]
  installments: RealpayInstallment[]
}

/**
 * Check the LIVE RealPay portal for an existing contract on this policy and
 * sync it (contract + installments) into our DB. Slow — makes an external
 * RealPay round-trip — so it returns a few seconds later.
 */
export async function syncPolicyRealpayFromPortal(policyId: number): Promise<RealpaySyncResult> {
  const { data } = await apiClient.post<RealpaySyncResult>(`/policies/${policyId}/realpay/sync-from-portal`)
  return data
}

// ─── Offline payment (extended) ────────────────────────────────────────

export interface OfflinePaymentPayload {
  amount: number
  payment_date: string         // YYYY-MM-DD
  receipt_number: string
  notes?: string
  payment_received_by?: string
  number_of_instalments_paid?: number
  // File handled via FormData when present
}

export async function createOfflinePayment(
  policyId: number,
  payload: OfflinePaymentPayload,
  proofFile?: File | null,
): Promise<{ message: string; data: any }> {
  if (proofFile) {
    const fd = new FormData()
    Object.entries(payload).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') fd.append(k, String(v))
    })
    fd.append('payment_image', proofFile)
    const { data } = await apiClient.post(`/payments/${policyId}/offline`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  }
  const { data } = await apiClient.post(`/payments/${policyId}/offline`, payload)
  return data
}

// ─── Transaction Logs ──────────────────────────────────────────────────

export interface TransactionLogRow {
  id: number
  policyNumber: string
  referenceNumber: string | null
  amount: string | number
  paymentMethod: string | null
  paymentDate: string | null
  paymentSettlementDate: string | null
  numberOfInstalmentsPaid: number | null
  note: string | null
  status: string | null
  paymentReceivedBy: string | null
  paymentLoggedByName: string | null
  paymentProofPath: string | null
  isLedger: boolean
  isReversed: boolean
  isRefunded: boolean
  canReverseBeforeLedger: boolean
  canReverseAfterLedger: boolean
  /**
   * Where the row came from. 'payment' is a payment_transactions row — the only
   * kind this tab used to show. 'ledger' is a refund posted straight to the
   * policy ledger with no payment transaction behind it (legacy and backlog
   * refunds), surfaced read-only so the tab agrees with the Account Statement.
   * 'ledger-archive' is the same thing read out of the archive database
   * (graphite_archive.policy_ledger) — a distinct value because archived ids
   * run in the same range as live ledger ids and would otherwise collide.
   */
  source?: 'payment' | 'ledger' | 'ledger-archive'
}

export interface TransactionLogSummary {
  successCount: number
  successAmount: number
  failedCount: number
  failedAmount: number
  refundCount: number
  refundAmount: number
  reverseCount: number
  reverseAmount: number
  totalBalance: number
}

export interface TransactionLogsResponse {
  data: TransactionLogRow[]
  summary: TransactionLogSummary
}

export async function fetchPolicyTransactionLogs(policyId: number): Promise<TransactionLogsResponse> {
  const { data } = await apiClient.get<TransactionLogsResponse>(`/policies/${policyId}/transaction-logs`)
  return data
}

export async function fetchTransactionProofUrl(policyId: number, txnId: number): Promise<{ url: string; disk: string }> {
  const { data } = await apiClient.get<{ url: string; disk: string }>(
    `/policies/${policyId}/transaction-logs/${txnId}/proof`,
  )
  return data
}

// ─── Reverse + Refund (V8 parity) ──────────────────────────────────────

export interface ReverseTransactionPayload {
  reversal_date: string   // YYYY-MM-DD
  comments: string
  reference_number?: string
}

export interface ReverseTransactionResponse {
  message: string
  data: {
    originalTransactionId: number
    duplicateTransactionId: number
    mode: 'before' | 'after'
  }
}

export async function reversePolicyTransaction(
  policyId: number,
  txnId: number,
  payload: ReverseTransactionPayload,
): Promise<ReverseTransactionResponse> {
  const { data } = await apiClient.post<ReverseTransactionResponse>(
    `/policies/${policyId}/transaction-logs/${txnId}/reverse`,
    payload,
  )
  return data
}

export interface RefundMoneyPayload {
  reference_number: string
  date_of_refund: string   // YYYY-MM-DD
  amount: number
  reason: string
  refunded_by: string
}

export interface RefundMoneyResponse {
  message: string
  data: {
    refundTransactionId: number
    policyNumber: string
    amount: number
    referenceNumber: string
    dateOfRefund: string
  }
}

export async function refundPolicyMoney(
  policyId: number,
  payload: RefundMoneyPayload,
): Promise<RefundMoneyResponse> {
  const { data } = await apiClient.post<RefundMoneyResponse>(
    `/policies/${policyId}/transaction-logs/refund`,
    payload,
  )
  return data
}

// ─── Payment-method conversions (Payment Conversions tab, GRA-0182) ────
//
// V8 "Payment Update Contract" tab parity: the log of every time this
// policy's payment method was switched (e.g. DPO → RealPay) — who did it,
// old/new method, whether the old contract was cancelled, and when.

export interface PaymentConversionRow {
  id: number
  policyNumber: string
  agentName: string | null
  oldPaymentMethod: string | null
  newPaymentMethod: string | null
  oldContractCancel: string | null
  status: number | null
  createdAt: string | null
}

export async function fetchPolicyPaymentConversions(policyId: number): Promise<PaymentConversionRow[]> {
  const { data } = await apiClient.get<{ data: PaymentConversionRow[] }>(
    `/policies/${policyId}/payment-conversions`,
  )
  return data.data
}
