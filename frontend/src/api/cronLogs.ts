import apiClient from './client'

export interface CronLogLine {
  ts: string
  level: string
  channel: string
  message: string
}

export interface CronLogTailResponse {
  lines: CronLogLine[]
  file_size: number
  file_modified: string | null
  truncated: boolean
  matched_count?: number
  returned_count?: number
  parsed_entries?: number
  message?: string
  error?: string
}

export type LogScope = 'all' | 'cron' | 'app'

export interface CronLogTailParams {
  cron_name?: string
  lines?: number
  level?: '' | 'info' | 'warning' | 'error'
  search?: string
  scope?: LogScope
}

export async function getCronLogNames(): Promise<string[]> {
  const res = await apiClient.get('/cron-logs/names')
  return res.data?.names ?? []
}

export async function getCronLogTail(params: CronLogTailParams): Promise<CronLogTailResponse> {
  const clean: Record<string, string | number> = {}
  if (params.cron_name) clean.cron_name = params.cron_name
  if (params.lines)     clean.lines     = params.lines
  if (params.level)     clean.level     = params.level
  if (params.search)    clean.search    = params.search
  if (params.scope)     clean.scope     = params.scope

  const res = await apiClient.get('/cron-logs/tail', { params: clean })
  return res.data
}
