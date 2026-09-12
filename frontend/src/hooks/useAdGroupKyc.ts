import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  bulkNotifyAdGroupKyc,
  createAdGroupKycCampaign,
  fetchAdGroupKycCampaign,
  fetchAdGroupKycCampaignLinks,
  fetchAdGroupKycCampaigns,
  fetchAdGroupKycDashboard,
  fetchAdGroupKycLink,
  generateAdGroupKycLinks,
  resendAdGroupKycLink,
  sendAdGroupKycEscalations,
  sendAdGroupKycLinks,
  sendAdGroupKycReminders,
  updateAdGroupKycCampaign,
  type CampaignFilters,
  type CampaignLinkFilters,
  type CreateCampaignPayload,
  type NotifyChannel,
  type UpdateCampaignPayload,
} from '../api/adGroupKyc'

const STALE = 2 * 60 * 1000

export function useAdGroupKycDashboard() {
  return useQuery({
    queryKey: ['adGroupKyc-dashboard'],
    queryFn: fetchAdGroupKycDashboard,
    staleTime: STALE,
    refetchOnWindowFocus: false,
  })
}

export function useAdGroupKycCampaigns(filters: CampaignFilters = {}) {
  return useQuery({
    queryKey: ['adGroupKyc-campaigns', filters],
    queryFn: () => fetchAdGroupKycCampaigns(filters),
    placeholderData: (prev) => prev,
    staleTime: STALE,
    refetchOnWindowFocus: false,
  })
}

export function useAdGroupKycCampaign(id: number) {
  return useQuery({
    queryKey: ['adGroupKyc-campaign', id],
    queryFn: () => fetchAdGroupKycCampaign(id),
    enabled: Number.isFinite(id) && id > 0,
    staleTime: STALE,
    refetchOnWindowFocus: false,
  })
}

export function useAdGroupKycCampaignLinks(id: number, filters: CampaignLinkFilters = {}) {
  return useQuery({
    queryKey: ['adGroupKyc-campaign-links', id, filters],
    queryFn: () => fetchAdGroupKycCampaignLinks(id, filters),
    enabled: Number.isFinite(id) && id > 0,
    placeholderData: (prev) => prev,
    staleTime: STALE,
    refetchOnWindowFocus: false,
  })
}

export function useAdGroupKycLink(id: number) {
  return useQuery({
    queryKey: ['adGroupKyc-link', id],
    queryFn: () => fetchAdGroupKycLink(id),
    enabled: Number.isFinite(id) && id > 0,
    staleTime: STALE,
    refetchOnWindowFocus: false,
  })
}

function useInvalidate() {
  const qc = useQueryClient()
  return (campaignId?: number, linkId?: number) => {
    qc.invalidateQueries({ queryKey: ['adGroupKyc-dashboard'] })
    qc.invalidateQueries({ queryKey: ['adGroupKyc-campaigns'] })
    if (campaignId) {
      qc.invalidateQueries({ queryKey: ['adGroupKyc-campaign', campaignId] })
      qc.invalidateQueries({ queryKey: ['adGroupKyc-campaign-links', campaignId] })
    }
    if (linkId) qc.invalidateQueries({ queryKey: ['adGroupKyc-link', linkId] })
  }
}

export function useCreateAdGroupKycCampaign() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (payload: CreateCampaignPayload) => createAdGroupKycCampaign(payload),
    onSuccess: () => invalidate(),
  })
}

export function useUpdateAdGroupKycCampaign(campaignId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (payload: UpdateCampaignPayload) => updateAdGroupKycCampaign(campaignId, payload),
    onSuccess: () => invalidate(campaignId),
  })
}

export function useGenerateAdGroupKycLinks(campaignId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (policyIds: number[]) => generateAdGroupKycLinks(campaignId, policyIds),
    onSuccess: () => invalidate(campaignId),
  })
}

export function useSendAdGroupKycLinks(campaignId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (p: { linkIds: number[]; channels: NotifyChannel[] }) =>
      sendAdGroupKycLinks(campaignId, p.linkIds, p.channels),
    onSuccess: () => invalidate(campaignId),
  })
}

export function useResendAdGroupKycLink(campaignId?: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (p: { linkId: number; channels: NotifyChannel[]; message?: string }) =>
      resendAdGroupKycLink(p.linkId, p.channels, p.message),
    onSuccess: (_res, vars) => invalidate(campaignId, vars.linkId),
  })
}

export function useBulkNotifyAdGroupKyc(campaignId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (p: { linkIds: number[]; channels: NotifyChannel[]; message?: string }) =>
      bulkNotifyAdGroupKyc(campaignId, p.linkIds, p.channels, p.message),
    onSuccess: () => invalidate(campaignId),
  })
}

export function useSendAdGroupKycReminders(campaignId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (p: { channels: NotifyChannel[]; daysSinceSent?: number }) =>
      sendAdGroupKycReminders(campaignId, p.channels, p.daysSinceSent),
    onSuccess: () => invalidate(campaignId),
  })
}

export function useSendAdGroupKycEscalations(campaignId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (p: { channels: NotifyChannel[] }) => sendAdGroupKycEscalations(campaignId, p.channels),
    onSuccess: () => invalidate(campaignId),
  })
}
