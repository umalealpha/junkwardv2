import apiClient from './client'

// GRA-0155 — self-service download of the daily Infobip SMS-log exports.

export interface SmsExportFile {
  path: string
  filename: string
  size_bytes: number | null
  last_modified: string | null
}

export interface SmsExportListResponse {
  items: SmsExportFile[]
  max_range_days: number
}

export interface SmsExportDownloadResponse {
  url: string
  expires_in: number
  filename: string
  path: string
  rows?: number
}

export interface SmsExportGenerateResponse extends Partial<SmsExportDownloadResponse> {
  // When the range has no rows the API returns { message, rows: 0 } and no url.
  message?: string
  rows?: number
}

export async function listSmsExports(): Promise<SmsExportListResponse> {
  const res = await apiClient.get('/system/sms-exports')
  return res.data
}

export async function getSmsExportDownloadUrl(path: string): Promise<SmsExportDownloadResponse> {
  const res = await apiClient.get('/system/sms-exports/download', { params: { path } })
  return res.data
}

export async function generateSmsExport(
  startDate: string,
  endDate: string,
): Promise<SmsExportGenerateResponse> {
  const res = await apiClient.post('/system/sms-exports/generate', {
    start_date: startDate,
    end_date: endDate,
  })
  return res.data
}
