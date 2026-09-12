import apiClient from './client'
import type { ListMeta } from './kyc'

// ── AD Group KYC campaign management ─────────────────────────────────
// FE client for /api/v1/kyc/ad-group/* (Api\V1\AdGroupKycController) —
// the V2 port of the V8 Blade admin campaign screens.

export type CampaignStatus = 'draft' | 'active' | 'paused' | 'completed' | 'cancelled'
export type LinkStatus =
  | 'pending' | 'sent' | 'opened' | 'otp_verified'
  | 'completed' | 'expired' | 'failed' | 'not_generated'
export type NotifyChannel = 'email' | 'sms' | 'whatsapp'

export interface AdGroupKycDashboardStats {
  total_campaigns: number
  active_campaigns: number
  total_links: number
  completed_links: number
  pending_links: number
  expired_links: number
  sent_links: number
  opened_links: number
  completion_rate: number
  response_time: number
}

export interface AdGroupKycActivityItem {
  id: number
  linkId: number | null
  activityType: string
  description: string | null
  customerName: string | null
  occurredAt: string | null
}

export interface AdGroupKycDashboard {
  stats: AdGroupKycDashboardStats
  recent_activities: AdGroupKycActivityItem[]
}

export interface AdGroupKycCampaignRow {
  id: number
  name: string
  description: string | null
  status: CampaignStatus
  employerGroupId: string | null
  employerGroupName: string | null
  linksCount: number
  activitiesCount: number
  completionRate: number
  createdAt: string | null
}

export interface AdGroupKycCampaignStats {
  total_links: number
  sent_links: number
  opened_links: number
  completed_links: number
  expired_links: number
  pending_links: number
  otp_verified: number
  response_time: number
  completion_rate: number
  open_rate: number
}

export interface AdGroupKycCampaignDetail {
  id: number
  name: string
  description: string | null
  status: CampaignStatus
  employerGroupId: string | null
  employerGroupName: string | null
  linkExpiryHours: number
  otpExpiryMinutes: number
  maxAttempts: number
  escalationDays: number
  reminderDays: number[]
  createdAt: string | null
  stats: AdGroupKycCampaignStats
}

export interface AdGroupKycLinkRow {
  policyId: number
  employeeId: string | null
  policyNumber: string | null
  customerId: number
  customerName: string
  email: string | null
  cellphone: string | null
  linkId: number | null
  status: LinkStatus
  sentAt: string | null
  openedAt: string | null
  otpVerifiedAt: string | null
  completedAt: string | null
  expiresAt: string | null
}

export interface AdGroupKycLinkActivity {
  id: number
  activityType: string
  description: string | null
  metadata: Record<string, unknown> | null
  ipAddress: string | null
  occurredAt: string | null
}

export interface AdGroupKycLinkDetail {
  id: number
  status: LinkStatus
  token: string
  kycUrl: string | null
  deliveryMethod: string | null
  deliveryReference: string | null
  ipAddress: string | null
  userAgent: string | null
  otpAttempts: number | null
  createdAt: string | null
  sentAt: string | null
  openedAt: string | null
  otpVerifiedAt: string | null
  completedAt: string | null
  expiresAt: string | null
  campaign: { id: number; name: string; status: CampaignStatus } | null
  customer: { id: number; name: string; email: string | null; cellphone: string | null } | null
  policyNumber: string | null
  activities: AdGroupKycLinkActivity[]
}

export interface CampaignFilters { search?: string; status?: string; per_page?: number; page?: number }
export interface CampaignLinkFilters {
  search?: string
  status?: string
  sort?: 'id' | 'customer' | 'status' | 'sent_at' | 'expires_at'
  dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface CreateCampaignPayload {
  name: string
  description?: string
  employer_group_id: string
  link_expiry_hours: number
  otp_expiry_minutes: number
  max_attempts: number
  escalation_days: number
  reminder_days: number[]
  notification_channels: NotifyChannel[]
}

export interface UpdateCampaignPayload {
  name: string
  description?: string
  status: 'active' | 'completed' | 'paused'
  escalation_days?: number
  reminder_days?: number[]
}

export interface NotifyResult {
  message: string
  data: { success_count: number; failure_count: number; total: number }
}

export async function fetchAdGroupKycDashboard(): Promise<AdGroupKycDashboard> {
  const { data } = await apiClient.get<AdGroupKycDashboard>('/kyc/ad-group/dashboard')
  return data
}

export async function fetchAdGroupKycCampaigns(filters: CampaignFilters = {}): Promise<{ data: AdGroupKycCampaignRow[]; meta: ListMeta }> {
  const { data } = await apiClient.get('/kyc/ad-group/campaigns', { params: filters })
  return data
}

export async function fetchAdGroupKycCampaign(id: number): Promise<AdGroupKycCampaignDetail> {
  const { data } = await apiClient.get(`/kyc/ad-group/campaigns/${id}`)
  return data.data
}

export async function fetchAdGroupKycCampaignLinks(id: number, filters: CampaignLinkFilters = {}): Promise<{ data: AdGroupKycLinkRow[]; meta: ListMeta }> {
  const { data } = await apiClient.get(`/kyc/ad-group/campaigns/${id}/links`, { params: filters })
  return data
}

export async function fetchAdGroupKycLink(id: number): Promise<AdGroupKycLinkDetail> {
  const { data } = await apiClient.get(`/kyc/ad-group/links/${id}`)
  return data.data
}

export async function createAdGroupKycCampaign(payload: CreateCampaignPayload): Promise<{ id: number }> {
  const { data } = await apiClient.post('/kyc/ad-group/campaigns', payload)
  return data.data
}

export async function updateAdGroupKycCampaign(id: number, payload: UpdateCampaignPayload): Promise<void> {
  await apiClient.put(`/kyc/ad-group/campaigns/${id}`, payload)
}

export async function generateAdGroupKycLinks(id: number, policyIds: number[]): Promise<{ links_count: number }> {
  const { data } = await apiClient.post(`/kyc/ad-group/campaigns/${id}/generate-links`, { policy_ids: policyIds })
  return data.data
}

export async function sendAdGroupKycLinks(id: number, linkIds: number[], channels: NotifyChannel[]): Promise<NotifyResult> {
  const { data } = await apiClient.post(`/kyc/ad-group/campaigns/${id}/send-links`, { link_ids: linkIds, channels })
  return data
}

export async function resendAdGroupKycLink(linkId: number, channels: NotifyChannel[], message?: string): Promise<NotifyResult> {
  const { data } = await apiClient.post(`/kyc/ad-group/links/${linkId}/resend`, { channels, message: message || undefined })
  return data
}

export async function bulkNotifyAdGroupKyc(id: number, linkIds: number[], channels: NotifyChannel[], message?: string): Promise<NotifyResult> {
  const { data } = await apiClient.post(`/kyc/ad-group/campaigns/${id}/notifications`, { link_ids: linkIds, channels, message: message || undefined })
  return data
}

export async function sendAdGroupKycReminders(id: number, channels: NotifyChannel[], daysSinceSent?: number): Promise<NotifyResult> {
  const { data } = await apiClient.post(`/kyc/ad-group/campaigns/${id}/reminders`, { channels, days_since_sent: daysSinceSent })
  return data
}

export async function sendAdGroupKycEscalations(id: number, channels: NotifyChannel[]): Promise<NotifyResult> {
  const { data } = await apiClient.post(`/kyc/ad-group/campaigns/${id}/escalations`, { channels })
  return data
}

/** Download the campaign CSV export via the authed client (blob). */
export async function exportAdGroupKycCampaign(id: number): Promise<Blob> {
  const { data } = await apiClient.get(`/kyc/ad-group/campaigns/${id}/export`, { responseType: 'blob' })
  return data
}
