import apiClient from './client'
import type { ListMeta } from './kyc'

/* ── Filters ── */
export interface BlockListFilters { search?: string; per_page?: number; page?: number }

/* ── Data shapes ── */
export interface BlockListItem {
  id: number
  firstName: string
  lastName: string
  idNumber: string | null
  cellphone: string | null
  email: string | null
  aliasNames: string | null
  blockReason: string | null
  amlStatus: 'flagged' | 'clear' | 'not_checked' | null
  blockedAt: string | null
  createdAt: string | null
}

export interface BlockListResponse { data: BlockListItem[]; meta: ListMeta }

export interface CustomerAlias { id: number; aliasName: string; createdAt: string }

export interface BlockedCustomerPolicy {
  id: number
  policyNumber: string
  productName: string
  status: string
  startDate: string | null
  endDate: string | null
}

export interface BlockListDetail extends BlockListItem {
  policies: BlockedCustomerPolicy[]
  aliases: CustomerAlias[]
}

export interface AmlCheckResult {
  status: 'flagged' | 'clear'
  matches: number
  checkedAt: string
  details?: string
}

/* ── API calls ── */

export async function fetchBlockList(filters: BlockListFilters = {}): Promise<BlockListResponse> {
  const { data } = await apiClient.get<BlockListResponse>('/customers/block-list', { params: filters })
  return data
}

export async function fetchBlockListDetail(id: number): Promise<BlockListDetail> {
  const { data } = await apiClient.get<{ data: BlockListDetail }>(`/customers/block-list/${id}`)
  return data.data
}

export async function blockCustomer(payload: { customer_id: number; block_reason: string }): Promise<void> {
  await apiClient.post('/customers/block', payload)
}

/* Add a new person to the block list (graphiteBWV8 "Add to Black List" parity).
   first_name, last_name and block_reason (Reasons of Cancellation) are required. */
export interface AddToBlockListPayload {
  first_name: string
  middle_name?: string
  last_name: string
  email?: string
  omang?: string
  passport?: string
  mobile?: string
  block_reason: string
  // Alias names captured on the create form. Sent as an array (each becomes a
  // customer_aliases row). `alias_names` (comma string) still accepted for
  // back-compat.
  aliases?: string[]
  alias_names?: string
}

export async function addToBlockList(payload: AddToBlockListPayload): Promise<void> {
  await apiClient.post('/customers/block-list', payload)
}

export async function unblockCustomer(id: number): Promise<void> {
  await apiClient.post(`/customers/${id}/unblock`)
}

export async function updateBlockReason(id: number, blockReason: string): Promise<void> {
  await apiClient.put(`/customers/block-list/${id}`, { block_reason: blockReason })
}

export async function fetchAliases(customerId: number): Promise<CustomerAlias[]> {
  const { data } = await apiClient.get<{ data: CustomerAlias[] }>(`/customers/${customerId}/aliases`)
  return data.data
}

export async function addAlias(customerId: number, aliasName: string): Promise<CustomerAlias> {
  const { data } = await apiClient.post<{ data: CustomerAlias }>(`/customers/${customerId}/aliases`, { alias_name: aliasName })
  return data.data
}

export async function removeAlias(aliasId: number): Promise<void> {
  await apiClient.delete(`/customers/aliases/${aliasId}`)
}

export async function runAmlCheck(customerId: number): Promise<AmlCheckResult> {
  const { data } = await apiClient.get<{ data: AmlCheckResult }>(`/customers/${customerId}/aml-check`)
  return data.data
}
