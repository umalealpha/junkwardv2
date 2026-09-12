import apiClient from './client'
import type { ListMeta } from './groupPolicies'

export interface RenewalFilters { is_renewed?: number; search?: string; per_page?: number; page?: number }
export interface RenewalItem {
  id: number; policyNumber: string; policyId: number | null
  customerName: string | null; cellphone: string | null; email: string | null
  oldPremium: number | null; newPremium: number | null; expiryDate: string | null
  paymentMethod: string | null; paymentFrequency: string | null
  rerated: boolean; isRenewed: boolean; createdAt: string | null
}
export interface RenewalResponse { data: RenewalItem[]; meta: ListMeta }

export async function fetchRenewals(filters: RenewalFilters = {}): Promise<RenewalResponse> {
  const { data } = await apiClient.get<RenewalResponse>('/renewals', { params: filters })
  return data
}

// ─── MIS (product_id 3 / MIS retail) renew flow ────────────────────────────
// JSON port of the legacy Blade "Policy Renewal" page. Backed by the
// Api/V1/RenewalController endpoints (routes/api_v1.php).

export interface MisRenewFlow {
  canRenew: boolean
  canMoveToRenew: boolean
  needsRerate: boolean
  message: string | null
  policy?: { id: number; policyNumber: string; productId: number }
  vehicle?: {
    japaneseImport: string; make: string | null; manufacturingYear: string | number | null
    model: string | null; estimatedValue: number | string | null; priorAccidents: number
    condition: string; mileage: string; purpose: string
  }
  premium?: {
    oldPremium: number | string | null; oldFrequency: number; oldFrequencyLabel: string
    oldSumInsured: number | string | null; newPremium: number | string | null
    newMonthlyPremium: number | string | null; newThreeInstallmentPremium: number | string | null
    newSumInsured: number | string | null
  }
  agents?: { id: number; name: string }[]
}

export interface RenewActionResult { status?: string; message?: string; [k: string]: unknown }

// These renew actions call slow external services synchronously on the backend
// (the rating API at rate.alphadirect.co.bw with no cURL timeout, RealPay, and
// SMS/email), so they routinely run past the 30s axios default. Use a generous
// per-request timeout so a long-but-successful operation isn't reported as a
// client-side timeout (which previously left the policy moved to renew while
// the UI showed "timeout of 30000ms exceeded").
const LONG_RUNNING = { timeout: 180_000 }

export async function fetchMisRenewFlow(policyId: number): Promise<MisRenewFlow> {
  const { data } = await apiClient.get<MisRenewFlow>(`/renewals/${policyId}/mis-renew-flow`)
  return data
}

export async function moveToRenew(policyId: number): Promise<RenewActionResult> {
  const { data } = await apiClient.post<RenewActionResult>(`/renewals/${policyId}/move-to-renew`, undefined, LONG_RUNNING)
  return data
}

export async function rerateRenewal(policyId: number, body: Record<string, unknown>): Promise<RenewActionResult> {
  const { data } = await apiClient.post<RenewActionResult>(`/renewals/${policyId}/rerate`, body, LONG_RUNNING)
  return data
}

export async function generateRenewLink(policyId: number): Promise<RenewActionResult> {
  const { data } = await apiClient.post<RenewActionResult>(`/renewals/${policyId}/generate-link`, undefined, LONG_RUNNING)
  return data
}

export async function payRenewalCash(policyId: number, body: Record<string, unknown> | FormData): Promise<RenewActionResult> {
  const { data } = await apiClient.post<RenewActionResult>(`/renewals/${policyId}/pay-cash`, body, LONG_RUNNING)
  return data
}

export async function payRenewalRealpay(policyId: number, body: Record<string, unknown>): Promise<RenewActionResult> {
  const { data } = await apiClient.post<RenewActionResult>(`/renewals/${policyId}/pay-realpay`, body, LONG_RUNNING)
  return data
}

// Renew the policy term without recording any payment. Body: new_premium,
// paymentFreq (1|2|3), term_start_date, agent_id.
export async function renewNoPayment(policyId: number, body: Record<string, unknown>): Promise<RenewActionResult> {
  const { data } = await apiClient.post<RenewActionResult>(`/renewals/${policyId}/renew-no-payment`, body, LONG_RUNNING)
  return data
}

export interface HighRiskRenewResult {
  message?: string
  term?: { id: number | null; from: string; to: string }
  requiresPaymentContract?: boolean
  error?: string
}

// High-risk customer yearly renewal — every product EXCEPT Motor Comp (3).
// Creates a new 1-year term; the new payment contract is then created via the
// RealPay contract screen. Backend re-checks the high-risk / product / due gate.
export async function highRiskRenew(policyId: number): Promise<HighRiskRenewResult> {
  const { data } = await apiClient.post<HighRiskRenewResult>(`/policies/${policyId}/high-risk-renew`, undefined, LONG_RUNNING)
  return data
}
