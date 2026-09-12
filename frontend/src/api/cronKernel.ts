import apiClient from './client'

export interface KernelJob {
  id: number
  cron_name: string
  run_type: 'Hourly' | 'Daily' | 'weekly_sundays' | 'lastDayOfMonth'
  run_time: string | null
  enabled: boolean
  run_on_server: string
  created_at: string
  updated_at: string
  emails: string[]
  emails_dev: string[]
  cron_mail_id: number | null
  last_run_start: string | null
  last_run_end: string | null
  last_run_ok: boolean
}

export interface KernelStatusSummary {
  total: number
  enabled: number
  disabled: number
  by_server: { run_on_server: string; total: number; enabled: number }[]
  currently_running: { name: string; start: string }[]
  recent_runs: { name: string; start: string; end: string | null; processedCount: string | null }[]
}

export interface KernelJobDetail extends KernelJob {
  run_history: {
    id: number
    name: string
    start: string
    end: string | null
    processedCount: string | null
    // Heartbeat columns (added via cron_status migration on 2026-05-18).
    // Cron commands write these as they progress so a stuck/dead run is
    // observable from the Cron Portal without needing AWS CloudWatch
    // access.
    current_step?: string | null
    last_policy_id?: number | string | null
    error_message?: string | null
  }[]
}

export async function getKernelJobs(params?: { search?: string; server?: string; enabled?: string }): Promise<{ data: KernelJob[]; meta: { total: number; active: number; servers: Record<string, number> } }> {
  const res = await apiClient.get('/cron-kernel', { params })
  return res.data
}

export async function getKernelStatusSummary(): Promise<KernelStatusSummary> {
  const res = await apiClient.get('/cron-kernel/status-summary')
  return res.data
}

export async function getKernelJobDetail(id: number): Promise<KernelJobDetail> {
  const res = await apiClient.get(`/cron-kernel/${id}`)
  return res.data.data
}

export async function updateKernelJob(id: number, data: { status?: number; run_time?: string; run_type?: string; run_on_server?: string }): Promise<void> {
  await apiClient.put(`/cron-kernel/${id}`, data)
}

export async function addKernelMail(id: number, email: string): Promise<{ success: boolean; emails: string[] }> {
  const res = await apiClient.post(`/cron-kernel/${id}/mail`, { email })
  return res.data
}

export async function removeKernelMail(id: number, email: string): Promise<{ success: boolean; emails: string[] }> {
  const res = await apiClient.delete(`/cron-kernel/${id}/mail`, { data: { email } })
  return res.data
}

export interface RunNowResponse {
  success: boolean
  cron_name: string
  exit_code: number
  error: string | null
  output: string
  ran_at: string
}

export async function runKernelJobNow(id: number): Promise<RunNowResponse> {
  // Backend runs Artisan::call synchronously, so this request can take
  // seconds-to-minutes depending on what the cron does. Override the
  // default axios timeout to 5 minutes — long enough for renewal sweeps
  // and DomComMonthlyAutoRenew, short enough to fail fast on something
  // genuinely stuck.
  const res = await apiClient.post(`/cron-kernel/${id}/run-now`, {}, { timeout: 5 * 60 * 1000 })
  return res.data
}

export interface DailyActivityRow {
  id: number
  name: string
  start: string | null
  end: string | null
  duration_sec: number | null
  duration: string | null
  completed: boolean
  mail_send: boolean
  created_at: string
}

export interface DailyActivityMeta {
  date: string
  total: number
  completed: number
  running: number
}

export async function getDailyActivity(date?: string): Promise<{ data: DailyActivityRow[]; meta: DailyActivityMeta }> {
  const res = await apiClient.get('/cron-kernel/daily-activity', { params: date ? { date } : {} })
  return res.data
}

export function getDailyActivityDownloadUrl(date: string): string {
  const base = (apiClient.defaults.baseURL ?? '').replace(/\/$/, '')
  return `${base}/cron-kernel/daily-activity/download?date=${date}`
}
