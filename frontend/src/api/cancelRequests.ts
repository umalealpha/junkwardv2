import apiClient from './client'
import type { ListMeta } from './groupPolicies'

export interface CancelRequestFilters { status?: string; search?: string; per_page?: number; page?: number }
export interface CancelRequestItem {
  id: number; policyNumber: string; productName: string | null
  reason: string | null; circumstances: string | null; otherCompany: string | null
  status: string | null; actionBy: string | null
  createdAt: string | null; updatedAt: string | null
}
export interface CancelRequestResponse { data: CancelRequestItem[]; meta: ListMeta }

export async function fetchCancelRequests(filters: CancelRequestFilters = {}): Promise<CancelRequestResponse> {
  const { data } = await apiClient.get<CancelRequestResponse>('/cancel-requests', { params: filters })
  return data
}
export async function approveCancelRequest(id: number) {
  return apiClient.post(`/cancel-requests/${id}/approve`)
}
export async function declineCancelRequest(id: number) {
  return apiClient.post(`/cancel-requests/${id}/decline`)
}

// Immediate cancel from the policy view page (MIS retail products 1,2,3,4,5,9).
// Cancels the policy in one shot — no pending-request/approval step — and runs
// the same channel cancellation (schedule/contract) as the approve flow.
export interface CancelPolicyPayload {
  reason: string
  circumstances?: string | null
  other_company?: string | null
}

// What the RealPay half of the cancellation did. The policy is cancelled either
// way — this says whether its debit order was actually stopped, which is the
// one thing an agent may still need to act on. `failed` names contracts that
// can still debit the customer; they are queued for
// `realpay:retry-policy-cancellations` and logged under '[REALPAY POLICY CANCEL]'.
export interface RealpayCancelOutcome {
  ok: boolean
  attempted: boolean
  identified: string[]
  cancelled: string[]
  failed: string[]
  message: string
}
export interface CancelPolicyResult {
  message: string
  payment_cancel?: { status?: boolean; message?: string; realpay?: RealpayCancelOutcome | null }
}

export async function cancelPolicyImmediate(policyId: number, payload: CancelPolicyPayload): Promise<CancelPolicyResult> {
  const { data } = await apiClient.post<CancelPolicyResult>(`/policies/${policyId}/cancel`, payload)
  return data
}

// DB-driven cancellation reasons (same customer_feedback_options source as the
// start frontend). input_type: 1 = sub-options render as radios, 2 = as a select.
export interface CancelFeedbackSubOption { id?: number; name: string }
export interface CancelFeedbackOption {
  id: number
  name: string
  description: string | null
  input_type: number
  suboptions: CancelFeedbackSubOption[]
}
export async function fetchCancelFeedbackOptions(): Promise<CancelFeedbackOption[]> {
  const { data } = await apiClient.get<{ data: CancelFeedbackOption[] }>('/cancel-requests/feedback-options')
  return data.data
}

// The stored cancellation reason for an already-cancelled policy (newest
// cancel_policy_requests row), shown on the policy detail page.
export interface StoredCancelReason {
  reason: string | null
  circumstances: string | null
  otherCompany: string | null
  cancelledBy: string | null
  status: string | null
  createdAt: string | null
}
export async function fetchPolicyCancelReason(policyId: number): Promise<StoredCancelReason | null> {
  const { data } = await apiClient.get<{ data: StoredCancelReason | null }>(`/policies/${policyId}/cancel-reason`)
  return data.data
}

// Resend the cancellation SMS / email for an already-cancelled policy — parity
// with the old Edit Policy page's "Send policy cancelled email/sms" buttons.
export interface ResendCancellationResult { status: boolean; message: string }
export async function resendCancellationNotice(policyId: number, type: 'email' | 'sms'): Promise<ResendCancellationResult> {
  const { data } = await apiClient.post<ResendCancellationResult>(`/policies/${policyId}/resend-cancellation/${type}`)
  return data
}
