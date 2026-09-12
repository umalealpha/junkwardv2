import apiClient from './client'

// ─── Scheduled KPI reports ────────────────────────────────────────────────

export interface ReportSchedule {
  id: number
  name: string
  reportType: string
  reportLabel: string
  frequency: 'daily' | 'weekly' | 'monthly'
  dayOfWeek: number | null
  dayOfMonth: number | null
  hour: number
  recipients: string[]
  enabled: boolean
  lastRunAt: string | null
  lastStatus: string | null
  lastNote: string | null
}

export interface ReportTypeOption {
  code: string
  label: string
}

export interface SchedulePayload {
  name: string
  report_type: string
  frequency: 'daily' | 'weekly' | 'monthly'
  day_of_week?: number | null
  day_of_month?: number | null
  hour: number
  recipients: string[]
  enabled: boolean
}

export interface SchedulesIndex {
  data: ReportSchedule[]
  flagOn: boolean
  reportTypes: ReportTypeOption[]
  canManage: boolean
}

export async function fetchReportSchedules(): Promise<SchedulesIndex> {
  const { data } = await apiClient.get<SchedulesIndex>('/claims-report-schedules')
  return data
}

export async function createReportSchedule(payload: SchedulePayload): Promise<ReportSchedule> {
  const { data } = await apiClient.post<{ data: ReportSchedule }>('/claims-report-schedules', payload)
  return data.data
}

export async function updateReportSchedule(id: number, payload: SchedulePayload): Promise<ReportSchedule> {
  const { data } = await apiClient.put<{ data: ReportSchedule }>(`/claims-report-schedules/${id}`, payload)
  return data.data
}

export async function deleteReportSchedule(id: number): Promise<void> {
  await apiClient.delete(`/claims-report-schedules/${id}`)
}

export interface SchedulePreview {
  subject: string
  html: string
  report: unknown
  note: string
}

export async function previewReport(params: {
  report_type?: string
  frequency?: string
  schedule_id?: number
}): Promise<SchedulePreview> {
  const { data } = await apiClient.post<{ data: SchedulePreview }>('/claims-report-schedules/preview', params)
  return data.data
}

export interface RunNowResult {
  data: ReportSchedule
  output: string
  message: string
}

export async function runReportScheduleNow(id: number): Promise<RunNowResult> {
  const { data } = await apiClient.post<RunNowResult>(`/claims-report-schedules/${id}/run`, {})
  return data
}

// Bulk claim import moved to ./claimBulkImport (dedicated client).
