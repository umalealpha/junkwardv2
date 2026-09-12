import apiClient from './client'

export interface Customer360Customer {
  id: number
  name: string
  id_number: string
  phone: string
  email: string
  created_at: string
}

export interface Customer360Policy {
  id: number
  policy_number: string
  product_name: string
  status_label: string
  premium: number
  start_date: string
}

export interface Customer360PolicySummary {
  total: number
  active: number
  cancelled: number
  total_premium: string
}

export interface Customer360Payment {
  policy_number: string
  amount: string
  status: string
  date: string
  method: string
}

export interface Customer360PaymentSummary {
  total_paid: string
  last_payment_date: string
  payment_methods: string[]
}

export interface Customer360Claim {
  claim_number: string
  claim_type: string
  status: string
  created_at: string
}

export interface Customer360ClaimSummary {
  total: number
  open: number
  approved: number
  rejected: number
}

export type RiskCategory = 'low' | 'medium' | 'high'

export interface Customer360Data {
  customer: Customer360Customer
  kyc_status: string
  aml_status: string
  policies: Customer360Policy[]
  policy_summary: Customer360PolicySummary
  recent_payments: Customer360Payment[]
  payment_summary: Customer360PaymentSummary
  claims: Customer360Claim[]
  claim_summary: Customer360ClaimSummary
  risk_score: string
  // Admin-set risk classification (graphiteBWV8 customer_category parity) —
  // distinct from the computed risk_score above.
  risk_category: RiskCategory | null
  risk_category_reason: string | null
  is_blocked: boolean
  block_reason: string | null
}

export async function fetchCustomer360(id: number): Promise<Customer360Data> {
  const { data } = await apiClient.get<{ data: Customer360Data }>(`/customers/${id}`)
  return data.data
}

export async function updateCustomer(id: number, payload: Record<string, any>): Promise<void> {
  await apiClient.put(`/customers/${id}`, payload)
}
