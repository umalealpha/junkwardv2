import apiClient from './client'

export type DocumentType = 'id' | 'vehicle' | 'property' | 'passport' | 'insurance' | 'invoice' | 'any'

export interface OcrResult {
  [key: string]: string | number | null
}

export interface FraudFlag {
  type: string
  severity: 'low' | 'medium' | 'high'
  description: string
  field?: string | null
}

export interface FraudResult {
  risk_level: 'low' | 'medium' | 'high'
  risk_score: number
  flags: FraudFlag[]
  summary: string
}

export interface DbFlag {
  type: string
  severity: string
  description: string
  customer_id?: number
}

export interface OcrParseResponse {
  data: OcrResult
  fraud: FraudResult
  db_flags: DbFlag[]
}

export interface PolicyDocumentsResponse {
  policy: { id: number; policyNumber: string; customer: string }
  kyc_documents: Array<{ field: string; label: string; url: string; type: string }>
  attachments: Array<{ field: string; label: string; url: string; type: string }>
}

export async function parseDocumentText(
  text: string,
  documentType: DocumentType
): Promise<OcrParseResponse> {
  const { data } = await apiClient.post<OcrParseResponse>('/ocr/parse', {
    text,
    document_type: documentType,
  })
  return data
}

export async function uploadDocumentFile(
  file: File,
  documentType: DocumentType = 'any',
  context?: { product?: string; entityType?: string }
): Promise<OcrParseResponse & { detected_type?: string; file_name?: string }> {
  const form = new FormData()
  form.append('file', file)
  form.append('document_type', documentType)
  if (context?.product) form.append('product_context', context.product)
  if (context?.entityType) form.append('entity_context', context.entityType)
  const { data } = await apiClient.post('/ocr/upload', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
    timeout: 120000,
  })
  return data
}

export async function fetchPolicyDocuments(policyId: number): Promise<PolicyDocumentsResponse> {
  const { data } = await apiClient.get<PolicyDocumentsResponse>(`/ocr/policy-documents/${policyId}`)
  return data
}
