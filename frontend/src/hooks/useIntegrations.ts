import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getIntegrations, updateIntegration, testIntegrationConnection, submitTestInvoice, updateIntegrationSettings, getIntegrationWebhooks } from '../api/integrations'
import type { TestInvoicePayload } from '../api/integrations'

export function useIntegrations() {
  return useQuery({
    queryKey: ['integrations'],
    queryFn: getIntegrations,
    staleTime: 30 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useUpdateIntegration() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ slug, data }: { slug: string; data: { enabled: boolean; notes?: string } }) =>
      updateIntegration(slug, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['integrations'] })
    },
  })
}

export function useTestIntegrationConnection() {
  return useMutation({
    mutationFn: (slug: string) => testIntegrationConnection(slug),
  })
}

export function useSubmitTestInvoice() {
  return useMutation({
    mutationFn: ({ slug, data }: { slug: string; data: TestInvoicePayload }) =>
      submitTestInvoice(slug, data),
  })
}

export function useUpdateIntegrationSettings() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ slug, data }: { slug: string; data: { program_id?: string | null } }) =>
      updateIntegrationSettings(slug, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['integrations'] })
    },
  })
}

export function useIntegrationWebhooks(slug: string, enabled = true) {
  return useQuery({
    queryKey: ['integration-webhooks', slug],
    queryFn: () => getIntegrationWebhooks(slug),
    enabled,
    staleTime: 10 * 1000,
    refetchInterval: 15 * 1000, // live-ish for the console during a call
  })
}
