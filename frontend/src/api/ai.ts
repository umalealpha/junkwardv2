import apiClient from './client'

// Frontend deployment trigger - fixed reinsurance CASE statement
export interface AiMessage {
  id: number
  role: 'user' | 'assistant'
  content: string
  result_data?: AiResultData | null
  created_at: string
}

export interface AiResultData {
  tool: string
  count: number
  columns: string[]
  rows: any[][]
  label?: string
  period?: string
  grouped_by?: string
  summary?: any[]
  found?: boolean
  customer?: any
  recent_payments?: any[]
  policies_count?: number
  error?: string
}

export interface AiConversation {
  id: number
  title: string
  updated_at: string
}

export interface AiQueryResponse {
  conversation_id: number
  message: string
  result_data: AiResultData | null
  tokens_used: number
  provider?: string
  model?: string
}

export async function sendAiQuery(message: string, conversationId?: number): Promise<AiQueryResponse> {
  const res = await apiClient.post('/ai/query', { message, conversation_id: conversationId })
  return res.data
}

export async function fetchConversations(): Promise<AiConversation[]> {
  const res = await apiClient.get('/ai/conversations')
  return res.data.data
}

export async function fetchConversation(id: number): Promise<AiMessage[]> {
  const res = await apiClient.get(`/ai/conversations/${id}`)
  return res.data.data
}

export async function createConversation(): Promise<{ id: number }> {
  const res = await apiClient.post('/ai/conversations')
  return res.data
}

export async function deleteConversation(id: number): Promise<void> {
  await apiClient.delete(`/ai/conversations/${id}`)
}
