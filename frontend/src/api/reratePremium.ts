import apiClient from './client'

// ─── Rerate Premium (MIS Motor Comprehensive, product 3) ──────────────────
// Talks to PolicyReratePremiumController. Two-step flow: recalculate() previews
// a PENDING rate; accept() commits it. Discount/surcharge + custom-rate adjust
// the previewed annual premium before accept.

export interface RerateData {
  policyId: number
  policyNumber: string
  status: number
  canEdit: boolean
  isRenewal: number
  customer: {
    id: number | null
    name: string
    omang: string | null
    passport: string | null
    email: string | null
    cellphone: string | null
    gender: string | null
    dob: string | null
    maritalstatus: string | null
    storeName: string | null
  }
  product: { name: string | null; plan: string | null; sumInsured: number | string | null }
  vehicle: {
    isImported: string | null
    make: string | null
    model: string | null
    year: number | string | null
    estimatedValue: number | string | null
    priorAccidents: number
  }
  premium: {
    ratingsId: string | null
    monthly: number | string | null
    threeInstalment: number | string | null
    annual: number | string | null
    discountSurcharge: number | string | null
    premiumRate: number | string | null
    reason: string | null
  }
  options: { makes: any; models: any; years: number[] }
}

export interface RerateRates {
  rateId: string
  monthly: number | string
  threeInstalment: number | string
  annual: number | string
  premiumRate: number | null
}

export interface DiscountSurchargeResult {
  success: number
  message: string
  annualPremium: string
  monthly_premium: string
  threeintsll_premium: string
}

export interface RerateHistoryRow {
  id: number
  ratingsId: string
  monthly: string | number | null
  threeInstalment: string | number | null
  annual: string | number | null
  sumAssured: string | number | null
  discount: string | number | null
  surcharge: string | number | null
  status: string
  reratedBy: string | null
  createdAt: string | null
}

/** GET — load the form (customer, vehicle, current premium, option lists). */
export async function fetchRerateData(policyId: number): Promise<RerateData> {
  const { data } = await apiClient.get<{ data: RerateData }>(`/policies/${policyId}/rerate-premium`)
  return data.data
}

export interface RecalcPayload {
  make: string
  model: string
  year: number | string
  dob: string            // YYYY-MM-DD
  estimatedValue: number
  is_imported: 'Yes' | 'No'
  marital: number        // 1-6
  prior_accidents: number // 0-3
  gender: number         // 0 female / 1 male
}

/** POST — recalculate via the rating engine; records a PENDING rerate log. */
export async function recalculateRerate(policyId: number, payload: RecalcPayload): Promise<RerateRates> {
  const { data } = await apiClient.post<{ success: boolean; data: RerateRates }>(
    `/policies/${policyId}/rerate-premium/recalculate`, payload,
  )
  return data.data
}

/** POST — apply a discount or surcharge to the previewed annual premium. */
export async function applyDiscountSurcharge(
  policyId: number,
  payload: { type: 'discount' | 'surcharge'; value_type: 1 | 2; value: number; reason: string; annual_premium_rerate: number },
): Promise<DiscountSurchargeResult> {
  const { data } = await apiClient.post<DiscountSurchargeResult>(
    `/policies/${policyId}/rerate-premium/discount-surcharge`, payload,
  )
  return data
}

/** POST — apply a custom rate (flat or percent-of-sum-insured). */
export async function applyCustomRate(
  policyId: number,
  payload: { value_type: 1 | 2; value: number; reason: string; annual_premium_rerate: number },
): Promise<DiscountSurchargeResult> {
  const { data } = await apiClient.post<DiscountSurchargeResult>(
    `/policies/${policyId}/rerate-premium/custom-rate`, payload,
  )
  return data
}

export interface AcceptPayload {
  rateID: string
  frequency: 1 | 2 | 3
  annual_premium_rerate: number
  dis_sur_annual_premium?: number | null
  first_premium?: number | null
  billingDay?: string | null   // YYYY-MM-DD
  rerate_update_renew_term?: boolean
  rerate_update_renewal_rates?: boolean
  rerate_without_payment?: boolean
}

/** POST — commit the previewed (and optionally adjusted) premium to the policy. */
export async function acceptRerate(
  policyId: number,
  payload: AcceptPayload,
): Promise<{ success: boolean; message: string; data: { premium: number; frequency: number } }> {
  const { data } = await apiClient.post(`/policies/${policyId}/rerate-premium/accept`, payload)
  return data
}

/** GET — Premium Update History rows for the policy. */
export async function fetchRerateHistory(policyId: number): Promise<RerateHistoryRow[]> {
  const { data } = await apiClient.get<{ data: RerateHistoryRow[] }>(`/policies/${policyId}/rerate-premium/history`)
  return data.data
}
