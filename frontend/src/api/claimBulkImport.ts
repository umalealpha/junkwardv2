import apiClient from './client'

// ─── Bulk claim import ────────────────────────────────────────────────────
//
// Three-step admin flow (Claims → Bulk Import), matching the backend
// ClaimBulkImportController (routes: /claims-import/{analyze,preview,commit}):
//   1. analyze — sniff the file: headers + sample + target fields.
//   2. preview — DRY-RUN: validate + de-dupe every mapped row, create nothing.
//   3. commit  — create valid rows through the existing claim-create path.
//
// analyze + preview work for admins regardless of the flag so a file can be
// prepared and validated; commit is refused unless the `claims_bulk_import`
// flag is ON (server-enforced) and confirm=true is passed.

export interface ImportTargetField {
  field: string
  label: string
  required: boolean
}

export interface ImportAnalysis {
  headers: string[]
  sample: string[][]
  rowCount: number
  maxRows: number
  targetFields: ImportTargetField[]
  claimTypes: string[]
}

export interface ImportRowResult {
  row: number
  status: 'valid' | 'invalid' | 'duplicate' | 'created' | 'failed'
  errors: string[]
  claim_number?: string | null
  preview: {
    policyNumber: string | null
    claimType: string | null
    dateOfLoss: string | null
    externalRef: string | null
    handlerEmail: string | null
  }
}

export interface ImportResult {
  mode: 'dry_run' | 'commit'
  summary: {
    total: number
    valid: number
    invalid: number
    duplicate: number
    created: number
    failed: number
  }
  truncated: boolean
  maxRows: number
  rows: ImportRowResult[]
}

export async function analyzeImport(file: File): Promise<{ data: ImportAnalysis; flagOn: boolean }> {
  const form = new FormData()
  form.append('file', file)
  const { data } = await apiClient.post('/claims-import/analyze', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function previewImport(file: File, mapping: Record<string, string>): Promise<{ data: ImportResult; flagOn: boolean }> {
  const form = new FormData()
  form.append('file', file)
  form.append('mapping', JSON.stringify(mapping))
  const { data } = await apiClient.post('/claims-import/preview', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function commitImport(file: File, mapping: Record<string, string>): Promise<{ data: ImportResult }> {
  const form = new FormData()
  form.append('file', file)
  form.append('mapping', JSON.stringify(mapping))
  form.append('confirm', 'true')
  const { data } = await apiClient.post('/claims-import/commit', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}
