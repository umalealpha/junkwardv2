import apiClient from './client'

// Mirrors the Blade admin.sanctioned-customers page. Backed by
// GET /kyc/sanctioned (Api\V1\CustomerKycController::sanctioned), which
// reads kyc_cases.sanctions_max > 0 and the latest AML result per case.
export interface SanctionedCustomer {
  customerId: number
  /** customer_kyc.id — null when the flagged customer has no KYC row yet.
   *  When set, the name links to the KYC detail page. */
  kycId: number | null
  customerName: string
  maxScore: number
  status: string | null
  datasets: string[]
  /** Human-readable "Country (Agency)" labels derived from datasets. */
  countries: string[]
  programIds: string[]
  /** Raw AML "target" value: 'true' | 'false' | boolean | null. */
  target: string | boolean | null
  checkedAt: string | null
}

export interface SanctionedCustomersResponse {
  data: SanctionedCustomer[]
  meta: { total: number }
}

export async function fetchSanctionedCustomers(): Promise<SanctionedCustomersResponse> {
  const { data } = await apiClient.get<SanctionedCustomersResponse>('/kyc/sanctioned')
  return data
}
