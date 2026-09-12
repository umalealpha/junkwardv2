import apiClient from './client'
import type { ListMeta } from './groupPolicies'

export interface EmployerGroupFilters { status?: string; search?: string; per_page?: number; page?: number }
export interface EmployerGroupItem {
  id: number; employerGroupId: string; name: string; industry: string | null
  contactName: string | null; contactPhone: string | null; contactEmail: string | null
  broker: string | null; paymentMethod: string | null; status: string | null
  noOfEmployees: number | null; createdAt: string | null
}
export interface EmployerGroupResponse { data: EmployerGroupItem[]; meta: ListMeta }

export async function fetchEmployerGroups(filters: EmployerGroupFilters = {}): Promise<EmployerGroupResponse> {
  const { data } = await apiClient.get<EmployerGroupResponse>('/employer-groups', { params: filters })
  return data
}

export interface CreateEmployerGroupPayload {
  name: string
  industry: string
  other_industry?: string
  address?: string
  town?: string
  postal_code?: string
  contact_name: string
  contact_phone: string
  contact_email: string
  no_of_employees?: number | null
  broker?: string
  payment_method?: string
  notes?: string
  status?: string
  /** HR email addresses — array, or comma/newline-delimited string. */
  bulk_emails?: string[] | string
}

export async function createEmployerGroup(payload: CreateEmployerGroupPayload): Promise<{ message: string; data: { id: number; employerGroupId: string; name: string; status: string } }> {
  const { data } = await apiClient.post('/employer-groups', payload)
  return data
}

// ── Detail / mutations (V8 admin port) ───────────────────────────────

export interface EmployerGroupDocument { key: string; label: string; filename: string | null; url: string | null }
export interface EmployerGroupHrUser {
  id: number; email: string; name: string; isActive: boolean
  hasPassword: boolean; lastLoginAt: string | null; createdAt: string | null
}
export interface EmployerGroupAuditEntry {
  id: number; event: string; actor: string | null
  details: Record<string, unknown> | null; createdAt: string | null
}
export interface EmployerGroupKycSubmission {
  id: number
  createdAt: string | null
  data: Record<string, unknown>
  directors: Record<string, unknown>[]
  shareholders: Record<string, unknown>[]
  documents: Record<string, unknown>[]
}
export interface EmployerGroupDetail extends EmployerGroupItem {
  otherIndustry: string | null
  address: string | null
  town: string | null
  postalCode: string | null
  hrEmails: string[]
  accountName: string | null
  accountNumber: string | null
  bankNameBranch: string | null
  notes: string | null
  updatedAt: string | null
  documents: EmployerGroupDocument[]
  hrUsers: EmployerGroupHrUser[]
  kycSubmission: EmployerGroupKycSubmission | null
  auditLogs: EmployerGroupAuditEntry[]
}

export interface UpdateEmployerGroupPayload extends CreateEmployerGroupPayload {
  bulk_emails?: string[] | string
}

export interface SendCommsResult {
  message: string
  data: Record<string, unknown>
}

export async function fetchEmployerGroup(id: number): Promise<EmployerGroupDetail> {
  const { data } = await apiClient.get(`/employer-groups/${id}`)
  return data.data
}

export async function updateEmployerGroup(id: number, payload: UpdateEmployerGroupPayload): Promise<void> {
  await apiClient.put(`/employer-groups/${id}`, payload)
}

export async function deleteEmployerGroup(id: number): Promise<void> {
  await apiClient.delete(`/employer-groups/${id}`)
}

export async function sendHrCredentials(id: number): Promise<SendCommsResult> {
  const { data } = await apiClient.post(`/employer-groups/${id}/send-hr-credentials`)
  return data
}

export async function sendOnboardingEmail(id: number): Promise<SendCommsResult> {
  const { data } = await apiClient.post(`/employer-groups/${id}/send-onboarding-email`)
  return data
}

export interface ADGroupPolicyFilters { employer_group_id?: string; search?: string; per_page?: number; page?: number }
export interface ADGroupPolicyItem {
  id: number; policyNumber: string; status: number; premium: number | null
  customerName: string | null; cellphone: string | null; productName: string | null
  groupName: string | null; employerGroupId: string | null; employeeId: string | null
  createdAt: string | null
}
export interface ADGroupPolicyResponse { data: ADGroupPolicyItem[]; meta: ListMeta }

export async function fetchADGroupPolicies(filters: ADGroupPolicyFilters = {}): Promise<ADGroupPolicyResponse> {
  const { data } = await apiClient.get<ADGroupPolicyResponse>('/ad-group-policies', { params: filters })
  return data
}
