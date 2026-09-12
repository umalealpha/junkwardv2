import apiClient from './client'

/**
 * Admin integration on/off settings. Backed by the backend
 * IntegrationSettingsController (GET/PUT /api/v1/integrations*).
 */
export interface Integration {
  integration: string          // slug, e.g. 'swiftly'
  label: string                // human label, e.g. 'Swiftly Finance'
  enabled: boolean
  configured: boolean          // true when the integration's credentials are present
  program_id: string | null    // non-secret identifier, editable from the UI
  updated_by: string | null    // email/name of the last person who toggled it
  updated_at: string | null    // ISO timestamp of the last change
  notes: string | null
}

export interface IntegrationsResponse {
  data: Integration[]
  can_manage: boolean          // whether the current user may toggle integrations
}

export interface UpdateIntegrationResult {
  integration: string
  enabled: boolean
  previous: boolean | null
  updated_by: string | null
  updated_at: string
}

export async function getIntegrations(): Promise<IntegrationsResponse> {
  const res = await apiClient.get('/integrations')
  return res.data
}

export async function updateIntegration(
  slug: string,
  data: { enabled: boolean; notes?: string },
): Promise<{ success: boolean; message: string; data: UpdateIntegrationResult }> {
  const res = await apiClient.put(`/integrations/${slug}`, data)
  return res.data
}

export interface TestConnectionResult {
  ok: boolean
  http_status?: number | null
  // Swiftly: read back from GET /offtaker/suppliers.
  program_id?: string | null
  supplier_count?: number
  suppliers?: unknown[]
  raw_preview?: string | null
  // MAPFRE: the Cognito -> eMiA auth chain carries no supplier list, so it
  // reports which dealer/environment it authenticated against instead — the
  // usual reason to run the test (the PRE dealer is IT-only, production BW).
  message?: string
  base_url?: string | null
  country_id?: string | null
  error?: string
}

export async function testIntegrationConnection(slug: string): Promise<TestConnectionResult> {
  const res = await apiClient.post(`/integrations/${slug}/test-connection`)
  return res.data
}

export interface TestInvoicePayload {
  invoice_id?: string            // reuse to exercise duplicate / retry cases
  supplier_id: string
  amount?: number                // Pula
  currency?: string
  due_at?: string
  percentage_requested?: number
  auto_request_early_payment?: boolean
  force?: boolean                // bypass our local dedupe → hit Swiftly's 422
}

export interface TestInvoiceResult {
  ok: boolean
  invoice_id?: string
  http_status?: number | null
  error?: string | null
  request?: Record<string, unknown>
  response?: unknown
  raw_preview?: string | null
}

export async function submitTestInvoice(slug: string, data: TestInvoicePayload): Promise<TestInvoiceResult> {
  const res = await apiClient.post(`/integrations/${slug}/test-invoice`, data)
  return res.data
}

export interface WebhookEvent {
  id: number
  event_type: string | null
  invoice_id: string | null
  signature_valid: boolean
  status: string
  received_at: string | null
  payload: Record<string, unknown> | null
}

export async function getIntegrationWebhooks(slug: string): Promise<{ data: WebhookEvent[] }> {
  const res = await apiClient.get(`/integrations/${slug}/webhooks`)
  return res.data
}

export async function updateIntegrationSettings(
  slug: string,
  data: { program_id?: string | null },
): Promise<{ success: boolean; message: string; data: Integration }> {
  const res = await apiClient.put(`/integrations/${slug}/settings`, data)
  return res.data
}
