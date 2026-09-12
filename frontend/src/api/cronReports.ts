import apiClient from './client'

export interface CronReport {
  job_key: string
  label: string
  description: string
  icon: string
  color: string
  last_run_at: string | null
  status: 'ok' | 'error' | 'running' | 'never'
  elapsed: string | null
  summary: Record<string, unknown>
  has_file: boolean
  filename: string | null
  file_size: number | null
  file_mtime: string | null
}

export async function getCronReports(): Promise<CronReport[]> {
  const res = await apiClient.get<{ data: CronReport[] }>('/cron-reports')
  return res.data.data
}

export function getReportDownloadUrl(filename: string): string {
  const base = (import.meta.env.VITE_API_URL as string) ?? ''
  return `${base}/api/v1/cron-reports/download/${encodeURIComponent(filename)}`
}

export interface CronJobConfig {
  job_key: string
  label: string
  description: string
  icon: string
  color: string
  schedule: string
  default_schedule: string
  enabled: boolean
  email_enabled: boolean
  recipients_override: string[] | null
  threshold_config: Record<string, unknown> | null
  notes: string | null
  last_config_by: string | null
  updated_at: string | null
  last_run_at: string | null
  last_run_status: 'ok' | 'error' | 'running' | 'never'
  last_run_elapsed: string | null
  last_run_summary: Record<string, unknown>
  stakeholder_count: number
}

export interface Stakeholder {
  id: number
  report_type: string
  name: string | null
  email: string
  active: number
  created_at: string
}

export interface CronJobDetail extends CronJobConfig {
  stakeholders: Stakeholder[]
  run_history: Array<{
    id: number
    job_key: string
    status: string
    summary: Record<string, unknown>
    elapsed: string | null
    created_at: string
  }>
}

export async function getCronConfig(): Promise<CronJobConfig[]> {
  const res = await apiClient.get<{ data: CronJobConfig[] }>('/cron-config')
  return res.data.data
}

export async function getCronJobDetail(key: string): Promise<CronJobDetail> {
  const res = await apiClient.get<{ data: CronJobDetail }>(`/cron-config/${key}`)
  return res.data.data
}

export async function updateCronConfig(key: string, data: Partial<{
  schedule: string; enabled: boolean; email_enabled: boolean
  threshold_config: Record<string, unknown>; notes: string; label: string
}>): Promise<void> {
  await apiClient.put(`/cron-config/${key}`, data)
}

export async function getStakeholders(key: string): Promise<Stakeholder[]> {
  const res = await apiClient.get<{ data: Stakeholder[] }>(`/cron-config/${key}/stakeholders`)
  return res.data.data
}

export async function addStakeholder(key: string, data: { email: string; name?: string }): Promise<Stakeholder> {
  const res = await apiClient.post<{ data: Stakeholder }>(`/cron-config/${key}/stakeholders`, data)
  return res.data.data
}

export async function deleteStakeholder(id: number): Promise<void> {
  await apiClient.delete(`/cron-config/stakeholders/${id}`)
}

export async function triggerCronJob(key: string): Promise<{ success: boolean; message: string }> {
  const res = await apiClient.post<{ success: boolean; message: string }>(`/cron-reports/trigger/${key}`)
  return res.data
}
