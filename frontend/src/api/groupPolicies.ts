import apiClient from './client'

export interface GroupPolicyFilters {
  status?: number; product_id?: number; search?: string; per_page?: number; page?: number
}
export interface GroupPolicyItem {
  id: number; policyNumber: string; status: number; premium: number | null
  customerName: string | null; cellphone: string | null; productName: string | null
  companyId: number | null; createdAt: string | null
}
export interface ListMeta { total: number; per_page: number; current_page: number; last_page: number; from: number | null; to: number | null }
export interface GroupPolicyResponse { data: GroupPolicyItem[]; meta: ListMeta }

export async function fetchGroupPolicies(filters: GroupPolicyFilters = {}): Promise<GroupPolicyResponse> {
  const { data } = await apiClient.get<GroupPolicyResponse>('/group-policies', { params: filters })
  return data
}
