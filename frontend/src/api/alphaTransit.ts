import apiClient from './client'

/**
 * Alpha Transit Cover (courier goods-in-transit) — read-only ops view over
 * the webhook ingestion. Backed by AlphaTransitAdminController
 * (GET /api/v1/alpha-transit/*). Corrections happen on the ATC platform and
 * re-sync through the webhook; nothing here mutates.
 */

export interface AtcSummary {
  enabled: boolean
  shipments: { total: number; premium: number; sum_insured: number; unpaid: number; paid: number; settled: number }
  payments: { total: number; amount: number }
  claims: { total: number; open: number }
  events: { total: number; failed: number; last_received_at: string | null }
  error?: string
}

export interface AtcShipment {
  id: number
  atc_policy_id: number | null
  channel: 'atc' | 'start' | string
  policy_id: number
  policy_number: string
  company_code: string
  payment_status: 'unpaid' | 'paid' | 'settled' | string
  issued_by_email: string | null
  issued_by_name: string | null
  sender_name: string
  sender_phone: string | null
  sender_email: string | null
  receiver_name: string | null
  receiver_phone: string | null
  receiver_email: string | null
  from_zone: string
  from_town: string | null
  to_zone: string
  to_town: string | null
  goods_category: string
  goods_description: string | null
  declared_value: string | number
  weight_kg: string | number | null
  sum_insured: string | number
  premium: string | number
  excess: string | number | null
  rate: string | number | null
  currency: string
  cover_start: string
  cover_end: string
  courier_waybill: string | null
  service_type: string | null
  courier_fee: string | number | null
  issued_at: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AtcCourier {
  id: number
  company_code: string
  name: string
  agency_id: number | null
  status: boolean | number
}

export interface AtcPayment {
  id: number
  atc_payment_id: number
  company_code: string
  payment_type: string | null
  reference_month: string | null
  bank_reference: string | null
  amount: string | number
  currency: string
  payment_date: string | null
  policies_settled: string | null // JSON array of policy numbers
  policies_count: number
  status: 'recorded' | 'partial' | string
  recorded_by_email: string | null
  notes: string | null
  recorded_at: string | null
}

export interface AtcClaim {
  id: number
  atc_claim_id: number
  claim_id: number | null
  claim_number: string
  policy_number: string
  company_code: string | null
  incident_type: string | null
  status: string
  claim_amount: string | number | null
  settled_amount: string | number | null
  claimant_name: string | null
  claimant_phone: string | null
  filed_by_email: string | null
  filed_at: string | null
}

export interface AtcEvent {
  id: number
  idempotency_key: string
  event_type: string
  status: 'received' | 'processed' | 'duplicate' | 'failed' | string
  entity_type: string | null
  graphite_id: number | null
  error: string | null
  received_at: string | null
  processed_at: string | null
}

export interface PageMeta { page: number; per_page: number; has_more: boolean }
export interface PageOf<T> { data: T[]; meta: PageMeta; error?: string }

export interface AtcListParams {
  page?: number
  per_page?: number
  search?: string
  payment_status?: string
  company_code?: string
  status?: string
  event_type?: string
}

export async function getAtcSummary(): Promise<AtcSummary> {
  const res = await apiClient.get('/alpha-transit/summary')
  return res.data
}

export async function getAtcShipments(params: AtcListParams): Promise<PageOf<AtcShipment>> {
  const res = await apiClient.get('/alpha-transit/shipments', { params })
  return res.data
}

export async function getAtcPayments(params: AtcListParams): Promise<PageOf<AtcPayment>> {
  const res = await apiClient.get('/alpha-transit/payments', { params })
  return res.data
}

export async function getAtcClaims(params: AtcListParams): Promise<PageOf<AtcClaim>> {
  const res = await apiClient.get('/alpha-transit/claims', { params })
  return res.data
}

export async function getAtcEvents(params: AtcListParams): Promise<PageOf<AtcEvent>> {
  const res = await apiClient.get('/alpha-transit/events', { params })
  return res.data
}

/** Everything ATC stores for one policy — shipment + courier + ATC claims. */
export interface AtcPolicyDetail {
  shipment: AtcShipment | null
  courier: AtcCourier | null
  claims: AtcClaim[]
  error?: string
}

export async function getAtcPolicyShipment(policyId: number): Promise<AtcPolicyDetail> {
  const res = await apiClient.get(`/alpha-transit/policy/${policyId}`)
  return res.data
}
