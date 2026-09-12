import { useState, useEffect, useRef, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  sendAiQuery,
  fetchConversations,
  fetchConversation,
  deleteConversation,
  type AiMessage,
  type AiConversation,
  type AiResultData,
} from '../../api/ai'
import { reportErrorToTeam } from '../../utils/reportError'

// ─── Suggested prompts ───────────────────────────────────────────────────────

const SUGGESTED_PROMPTS = [
  { icon: '📋', text: 'How many active policies do we have?' },
  { icon: '💰', text: 'Total collections this month by product' },
  { icon: '⚠️', text: 'Show cancelled policies still collecting' },
  { icon: '📊', text: 'New policies vs cancellations this month' },
  { icon: '🔍', text: 'List open claims this week' },
  { icon: '⚡', text: 'Premium mismatch anomalies' },
  { icon: '📈', text: 'Collection rate trend for last 3 months' },
  { icon: '🏢', text: 'Top 10 customers by total premium' },
]

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatTime(iso: string): string {
  try {
    return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
  } catch {
    return ''
  }
}

function exportToCsv(columns: string[], rows: any[][], label: string) {
  const header = columns.join(',')
  const body = rows.map(row =>
    row.map(cell => {
      const str = String(cell ?? '')
      return str.includes(',') || str.includes('"') || str.includes('\n')
        ? `"${str.replace(/"/g, '""')}"`
        : str
    }).join(',')
  ).join('\n')
  const csv = header + '\n' + body
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${label.replace(/\s+/g, '_').toLowerCase()}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// ─── Result Table ─────────────────────────────────────────────────────────────

function ResultTable({ data }: { data: AiResultData }) {
  if (data.error) {
    return (
      <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
        {data.error}
      </div>
    )
  }

  if (!data.columns?.length || !data.rows?.length) {
    return (
      <div className="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-500 italic">
        No results returned
      </div>
    )
  }

  return (
    <div className="mt-4 space-y-2">
      <div className="flex items-center justify-between">
        <div>
          {data.label && (
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">{data.label}</p>
          )}
          {data.period && (
            <p className="text-xs text-gray-400 mt-0.5">Period: {data.period}</p>
          )}
        </div>
        <button
          onClick={() => exportToCsv(data.columns, data.rows, data.label ?? data.tool ?? 'export')}
          className="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors px-3 py-1.5 rounded-lg hover:bg-blue-50 border border-blue-200"
        >
          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Export CSV
        </button>
      </div>
      <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              {data.columns.map((col, i) => (
                <th key={i} className="px-4 py-3 text-left font-semibold text-gray-600 whitespace-nowrap">
                  {col}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-100">
            {data.rows.map((row, ri) => (
              <tr key={ri} className="hover:bg-gray-50 transition-colors">
                {row.map((cell, ci) => (
                  <td key={ci} className="px-4 py-2.5 text-gray-700 whitespace-nowrap">
                    {cell !== null && cell !== undefined ? String(cell) : '—'}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="text-xs text-gray-400">{data.count} row{data.count !== 1 ? 's' : ''}</p>
    </div>
  )
}

// ─── Message bubble ───────────────────────────────────────────────────────────

function MessageBubble({ message }: { message: AiMessage }) {
  const isUser = message.role === 'user'
  const resultData = message.result_data
    ? (typeof message.result_data === 'string'
        ? JSON.parse(message.result_data)
        : message.result_data)
    : null

  return (
    <div className={`flex ${isUser ? 'justify-end' : 'justify-start'} mb-6`}>
      {!isUser && (
        <div className="flex-shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center mr-3 mt-1 shadow-md">
          <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        </div>
      )}

      <div className={`${isUser ? 'max-w-[65%]' : 'max-w-[80%]'} flex flex-col ${isUser ? 'items-end' : 'items-start'}`}>
        <div
          className={`px-5 py-3.5 rounded-2xl text-sm leading-relaxed shadow-sm ${
            isUser
              ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-tr-sm'
              : 'bg-white border border-gray-200 text-gray-800 rounded-tl-sm'
          }`}
        >
          {/* Markdown-like formatting */}
          <div className="whitespace-pre-wrap break-words">
            {message.content.split('\n').map((line, i, arr) => {
              const parts = line.split(/(\*\*[^*]+\*\*)/g)
              return (
                <span key={i}>
                  {parts.map((part, j) =>
                    part.startsWith('**') && part.endsWith('**') ? (
                      <strong key={j}>{part.slice(2, -2)}</strong>
                    ) : (
                      <span key={j}>{part}</span>
                    )
                  )}
                  {i < arr.length - 1 && <br />}
                </span>
              )
            })}
          </div>

          {!isUser && resultData && <ResultTable data={resultData} />}
        </div>

        <span className="text-xs text-gray-400 mt-1.5 px-1">{formatTime(message.created_at)}</span>
      </div>

      {isUser && (
        <div className="flex-shrink-0 w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center ml-3 mt-1">
          <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
          </svg>
        </div>
      )}
    </div>
  )
}

// ─── Thinking indicator ───────────────────────────────────────────────────────

function ThinkingIndicator() {
  return (
    <div className="flex justify-start mb-6">
      <div className="flex-shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center mr-3 mt-1 shadow-md">
        <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      </div>
      <div className="bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-5 py-4 shadow-sm">
        <div className="flex items-center gap-3">
          <div className="flex gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-purple-400 animate-bounce" style={{ animationDelay: '0ms' }} />
            <span className="w-2.5 h-2.5 rounded-full bg-blue-400 animate-bounce" style={{ animationDelay: '150ms' }} />
            <span className="w-2.5 h-2.5 rounded-full bg-purple-400 animate-bounce" style={{ animationDelay: '300ms' }} />
          </div>
          <span className="text-sm text-gray-500 font-medium">AI is analysing...</span>
        </div>
      </div>
    </div>
  )
}

// ─── Conversation sidebar ─────────────────────────────────────────────────────

function ConversationSidebar({
  conversations,
  activeId,
  onSelect,
  onNew,
  onDelete,
  loading,
}: {
  conversations: AiConversation[]
  activeId: number | null
  onSelect: (id: number) => void
  onNew: () => void
  onDelete: (id: number) => void
  loading: boolean
}) {
  return (
    <div className="w-64 flex-shrink-0 border-r border-gray-200 bg-white flex flex-col h-full">
      {/* Header */}
      <div className="px-4 py-4 border-b border-gray-100">
        <div className="flex items-center gap-2.5 mb-3">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center shadow-sm">
            <svg className="w-4.5 h-4.5 text-white w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-bold text-gray-800">Conversations</p>
            <p className="text-xs text-gray-400">{conversations.length} chat{conversations.length !== 1 ? 's' : ''}</p>
          </div>
        </div>
        <button
          onClick={onNew}
          className="w-full flex items-center justify-center gap-2 px-3 py-2.5 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-sm font-semibold rounded-xl hover:from-purple-700 hover:to-blue-700 transition-all shadow-sm"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
          </svg>
          New Chat
        </button>
      </div>

      {/* Conversation list */}
      <div className="flex-1 overflow-y-auto py-2 px-2">
        {loading ? (
          <div className="space-y-2 p-2">
            {[1, 2, 3, 4].map(i => (
              <div key={i} className="h-12 bg-gray-100 rounded-xl animate-pulse" />
            ))}
          </div>
        ) : conversations.length === 0 ? (
          <div className="text-center p-6">
            <div className="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
              <svg className="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
            </div>
            <p className="text-sm text-gray-500 font-medium">No chats yet</p>
            <p className="text-xs text-gray-400 mt-1">Start a new conversation above</p>
          </div>
        ) : (
          conversations.map(conv => (
            <div
              key={conv.id}
              className={`group relative rounded-xl mb-1 transition-all ${
                activeId === conv.id
                  ? 'bg-blue-50 border border-blue-200'
                  : 'hover:bg-gray-50 border border-transparent'
              }`}
            >
              <button onClick={() => onSelect(conv.id)} className="w-full text-left px-3 py-2.5 pr-8">
                <p className={`text-sm font-medium truncate ${activeId === conv.id ? 'text-blue-700' : 'text-gray-700'}`}>
                  {conv.title}
                </p>
                <p className="text-xs text-gray-400 mt-0.5">{formatDate(conv.updated_at)}</p>
              </button>
              <button
                onClick={e => { e.stopPropagation(); onDelete(conv.id) }}
                className="absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity p-1 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50"
                title="Delete"
              >
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          ))
        )}
      </div>
    </div>
  )
}

// ─── Main AiPage ──────────────────────────────────────────────────────────────

export default function AiPage() {
  const navigate = useNavigate()
  const [messages, setMessages] = useState<AiMessage[]>([])
  const [conversations, setConversations] = useState<AiConversation[]>([])
  const [activeConversationId, setActiveConversationId] = useState<number | null>(null)
  const [input, setInput] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [convsLoading, setConvsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [tokensUsed, setTokensUsed] = useState(0)
  const [model, setModel] = useState('')

  const messagesEndRef = useRef<HTMLDivElement>(null)
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  const scrollToBottom = useCallback(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [])

  useEffect(() => { scrollToBottom() }, [messages, isLoading, scrollToBottom])

  // Auto-resize textarea
  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto'
      textareaRef.current.style.height = Math.min(textareaRef.current.scrollHeight, 160) + 'px'
    }
  }, [input])

  useEffect(() => {
    textareaRef.current?.focus()
    loadConversations()
  }, [])

  async function loadConversations() {
    setConvsLoading(true)
    try {
      const data = await fetchConversations()
      setConversations(data)
    } catch {
      // non-critical
    } finally {
      setConvsLoading(false)
    }
  }

  async function loadConversation(id: number) {
    setActiveConversationId(id)
    setIsLoading(true)
    setError(null)
    try {
      const msgs = await fetchConversation(id)
      const parsed = msgs.map(m => ({
        ...m,
        result_data: m.result_data
          ? (typeof m.result_data === 'string' ? JSON.parse(m.result_data as string) : m.result_data)
          : null,
      }))
      setMessages(parsed)
    } catch (err: any) {
      // UAT 2026-05-29: surface the real failure + report to developers@
      // instead of a generic "Failed to load conversation." string. Same
      // template as the Commission / KYC / AuditTrail batches.
      const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load conversation.'
      setError(message)
      reportErrorToTeam({ error: message, stack: err?.stack, context: 'AiPage:loadConversation' })
    } finally {
      setIsLoading(false)
    }
  }

  function startNewChat() {
    setMessages([])
    setActiveConversationId(null)
    setInput('')
    setError(null)
    setTokensUsed(0)
    setModel('')
    textareaRef.current?.focus()
  }

  async function handleDeleteConversation(id: number) {
    try {
      await deleteConversation(id)
      setConversations(prev => prev.filter(c => c.id !== id))
      if (activeConversationId === id) startNewChat()
    } catch { /* ignore */ }
  }

  async function handleSend(messageText?: string) {
    const text = (messageText ?? input).trim()
    if (!text || isLoading) return

    setInput('')
    setError(null)

    const tempUserMsg: AiMessage = {
      id: Date.now(),
      role: 'user',
      content: text,
      result_data: null,
      created_at: new Date().toISOString(),
    }
    setMessages(prev => [...prev, tempUserMsg])
    setIsLoading(true)

    try {
      const res = await sendAiQuery(text, activeConversationId ?? undefined)
      setActiveConversationId(res.conversation_id)
      setTokensUsed(prev => prev + (res.tokens_used ?? 0))
      if (res.model) setModel(res.model)

      const assistantMsg: AiMessage = {
        id: Date.now() + 1,
        role: 'assistant',
        content: res.message,
        result_data: res.result_data,
        created_at: new Date().toISOString(),
      }
      setMessages(prev => [...prev, assistantMsg])
      await loadConversations()
    } catch (err: any) {
      setError(err?.response?.data?.error ?? err?.message ?? 'Failed to get AI response.')
      setMessages(prev => prev.filter(m => m.id !== tempUserMsg.id))
    } finally {
      setIsLoading(false)
    }
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  const isEmpty = messages.length === 0 && !isLoading

  return (
    <div className="flex h-[calc(100vh-56px)] bg-gray-50">

      {/* ── Left: Conversation sidebar ── */}
      <ConversationSidebar
        conversations={conversations}
        activeId={activeConversationId}
        onSelect={loadConversation}
        onNew={startNewChat}
        onDelete={handleDeleteConversation}
        loading={convsLoading}
      />

      {/* ── Right: Chat area ── */}
      <div className="flex-1 flex flex-col min-w-0 min-h-0">

        {/* ── Chat header bar ── */}
        <div className="flex-shrink-0 flex items-center justify-between px-6 py-3 bg-white border-b border-gray-200 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center shadow-sm">
              <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>
            <div>
              <h1 className="text-base font-bold text-gray-900">AI Assistant</h1>
              <p className="text-xs text-gray-400">Alpha Direct Intelligence · Ask anything about policies, payments, claims</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {/* Token counter */}
            {tokensUsed > 0 && (
              <div className="hidden sm:flex items-center gap-1.5 text-xs text-gray-400 bg-gray-100 px-3 py-1.5 rounded-full">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                </svg>
                {tokensUsed.toLocaleString()} tokens
              </div>
            )}
            {/* Model badge */}
            {model && (
              <div className="hidden md:flex items-center gap-1.5 text-xs font-medium text-purple-700 bg-purple-50 border border-purple-200 px-3 py-1.5 rounded-full">
                <span className="w-1.5 h-1.5 rounded-full bg-purple-500 inline-block" />
                {model}
              </div>
            )}
            {/* Back button */}
            <button
              onClick={() => navigate(-1)}
              className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors font-medium"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back
            </button>
          </div>
        </div>

        {/* ── Messages area ── */}
        <div className="flex-1 overflow-y-auto px-6 py-6 min-h-0">

          {isEmpty ? (
            /* ── Empty state ── */
            <div className="max-w-3xl mx-auto">
              {/* Welcome */}
              <div className="text-center mb-10">
                <div className="w-20 h-20 rounded-3xl bg-gradient-to-br from-purple-100 to-blue-100 flex items-center justify-center mx-auto mb-5 shadow-inner">
                  <svg className="w-10 h-10 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </div>
                <h2 className="text-2xl font-bold text-gray-900 mb-2">How can I help you today?</h2>
                <p className="text-gray-500 max-w-md mx-auto">
                  Ask me anything about policies, payments, claims, customers, or reconciliation anomalies. I'll fetch real-time data and provide analysis.
                </p>
              </div>

              {/* Suggested prompts grid */}
              <div className="grid grid-cols-2 gap-3">
                {SUGGESTED_PROMPTS.map((prompt, i) => (
                  <button
                    key={i}
                    onClick={() => handleSend(prompt.text)}
                    className="flex items-start gap-3 text-left px-4 py-3.5 bg-white hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-2xl transition-all group shadow-sm hover:shadow-md"
                  >
                    <span className="text-xl flex-shrink-0 mt-0.5">{prompt.icon}</span>
                    <span className="text-sm text-gray-700 group-hover:text-blue-700 font-medium leading-snug">
                      {prompt.text}
                    </span>
                  </button>
                ))}
              </div>

              <p className="text-center text-xs text-gray-400 mt-8">
                I have read access to all policy, payment, claims, and customer data. All queries run in real-time.
              </p>
            </div>
          ) : (
            /* ── Message list ── */
            <div className="max-w-4xl mx-auto">
              {isLoading && messages.length === 0 ? (
                <div className="space-y-4">
                  {[1, 2, 3].map(i => (
                    <div key={i} className={`flex ${i % 2 === 0 ? 'justify-end' : 'justify-start'}`}>
                      <div className={`h-14 rounded-2xl animate-pulse ${i % 2 === 0 ? 'bg-blue-100 w-1/2' : 'bg-gray-200 w-2/3'}`} />
                    </div>
                  ))}
                </div>
              ) : (
                <>
                  {messages.map(msg => (
                    <MessageBubble key={msg.id} message={msg} />
                  ))}
                  {isLoading && <ThinkingIndicator />}
                </>
              )}

              {/* Error */}
              {error && (
                <div className="my-4 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-start gap-3">
                  <svg className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p className="text-sm text-red-700 flex-1">{error}</p>
                  {/* Try again only fires when a conversation is selected — fits
                      the loadConversation failure path. New-chat / send errors
                      don't get this button. */}
                  {activeConversationId && (
                    <button onClick={() => loadConversation(activeConversationId)}
                      className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700 flex-shrink-0">
                      Try again
                    </button>
                  )}
                  <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              )}

              <div ref={messagesEndRef} />
            </div>
          )}
        </div>

        {/* ── Input area ── */}
        <div className="flex-shrink-0 bg-white border-t border-gray-200 px-6 py-4 shadow-lg">
          <div className="max-w-4xl mx-auto">
            <div className="flex items-end gap-3 bg-gray-50 border border-gray-300 rounded-2xl px-4 py-3 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100 focus-within:bg-white transition-all shadow-sm">
              <textarea
                ref={textareaRef}
                value={input}
                onChange={e => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Ask about policies, payments, claims, customers..."
                rows={1}
                disabled={isLoading}
                className="flex-1 bg-transparent text-sm text-gray-800 placeholder-gray-400 resize-none outline-none py-1 min-h-[32px] max-h-[160px] disabled:opacity-50"
                style={{ lineHeight: '1.6' }}
              />
              <button
                onClick={() => handleSend()}
                disabled={!input.trim() || isLoading}
                className="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 disabled:from-gray-300 disabled:to-gray-300 text-white flex items-center justify-center transition-all shadow-md disabled:shadow-none"
              >
                {isLoading ? (
                  <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                ) : (
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                  </svg>
                )}
              </button>
            </div>
            <p className="text-xs text-gray-400 text-center mt-2">
              Enter to send · Shift+Enter for new line · All data is queried live from the database
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
