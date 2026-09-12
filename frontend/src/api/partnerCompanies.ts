import apiClient from './client'

/**
 * Partner companies (couriers / retailers) and their start-portal logins.
 * Backed by PartnerCompanyAdminController (/api/v1/partner-companies/*).
 * Permissions: partner-company-list (read), partner-company-edit (write).
 */

export interface AssignableProduct { id: number; name: string }

export interface PartnerCompany {
  id: number
  company_code: string
  name: string
  contact_name: string | null
  contact_email: string | null
  contact_phone: string | null
  agency_id: number | null
  products: number[]
  notes: string | null
  status: boolean
  user_count: number
  policy_count?: number
  total_premium?: number
  created_at: string | null
  users?: PartnerUser[]
}

export interface PartnerUser {
  id: number
  company_id: number
  name: string
  email: string
  is_active: boolean
  password_set: boolean
  password_set_at: string | null
  last_login_at: string | null
  locked: boolean
  created_at: string | null
}

export interface CompanyInput {
  company_code?: string
  name: string
  contact_name?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  agency_id?: number | null
  products: number[]
  notes?: string | null
  status: boolean
}

export const partnerCompaniesApi = {
  list: async (search = '') => {
    const r = await apiClient.get<{ data: PartnerCompany[]; assignable_products: AssignableProduct[] }>('/partner-companies', { params: search ? { search } : {} })
    return r.data
  },
  get: async (id: number) => (await apiClient.get<{ data: PartnerCompany }>(`/partner-companies/${id}`)).data.data,
  create: async (input: CompanyInput) => (await apiClient.post<{ data: PartnerCompany }>('/partner-companies', input)).data.data,
  update: async (id: number, input: Partial<CompanyInput>) => (await apiClient.put<{ data: PartnerCompany }>(`/partner-companies/${id}`, input)).data.data,

  createUser: async (companyId: number, input: { name: string; email: string; send_credentials?: boolean }) =>
    (await apiClient.post<{ data: PartnerUser; credentials_sent: boolean; send_error: string | null }>(`/partner-companies/${companyId}/users`, input)).data,
  updateUser: async (companyId: number, userId: number, input: { name?: string; is_active?: boolean }) =>
    (await apiClient.put<{ data: PartnerUser }>(`/partner-companies/${companyId}/users/${userId}`, input)).data.data,
  sendCredentials: async (companyId: number, userId: number) =>
    (await apiClient.post<{ ok: boolean; message: string }>(`/partner-companies/${companyId}/users/${userId}/send-credentials`)).data,
  revokeSessions: async (companyId: number, userId: number) =>
    (await apiClient.post<{ ok: boolean }>(`/partner-companies/${companyId}/users/${userId}/revoke-sessions`)).data,
}
