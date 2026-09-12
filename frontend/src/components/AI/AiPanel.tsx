import { useState, useEffect, useRef, useCallback } from 'react'
import {
  sendAiQuery,
  fetchConversations,
  fetchConversation,
  deleteConversation,
  type AiMessage,
  type AiConversation,
  type AiResultData,
} from '../../api/ai'

// ─── Types ──────────────────────────────────────────────────────────────────

interface AiPanelProps {
  isOpen: boolean
  onClose: () => void
}

// ─── Suggested prompts ───────────────────────────────────────────────────────

const SUGGESTED_PROMPTS = [
  'Show active policies this month',
  'Total collections in March 2026',
  'Show cancelled policies still collecting',
  'List open claims this week',
  'Premium mismatch anomalies',
  'New policies vs cancellations this month',
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
      <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
        {data.error}
      </div>
    )
  }

  if (!data.columns || data.columns.length === 0 || !data.rows || data.rows.length === 0) {
    return (
      <div className="mt-3 p-3 bg-surface-2 border border-line rounded-lg text-xs text-ink-muted italic">
        No results returned
      </div>
    )
  }

  return (
    <div className="mt-3 space-y-2">
      {data.label && (
        <p className="text-xs font-semibold text-ink-muted uppercase tracking-wide">{data.label}</p>
      )}
      {data.period && (
        <p className="text-xs text-ink-faint">Period: {data.period}</p>
      )}
      <div className="overflow-x-auto rounded-lg border border-line max-h-64">
        <table className="min-w-full text-xs">
          <thead className="bg-surface-2 sticky top-0">
            <tr>
              {data.columns.map((col, i) => (
                <th
                  key={i}
                  className="px-3 py-2 text-left font-semibold text-ink-muted whitespace-nowrap border-b border-line"
                >
                  {col}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="bg-surface divide-y divide-line">
            {data.rows.map((row, ri) => (
              <tr key={ri} className="hover:bg-surface-2 transition-colors">
                {row.map((cell, ci) => (
                  <td key={ci} className="px-3 py-1.5 text-ink whitespace-nowrap">
                    {cell !== null && cell !== undefined ? String(cell) : '—'}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between">
        <span className="text-xs text-ink-faint">{data.count} row{data.count !== 1 ? 's' : ''}</span>
        <button
          onClick={() => exportToCsv(data.columns, data.rows, data.label ?? data.tool ?? 'export')}
          className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 transition-colors font-medium"
        >
          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Export CSV
        </button>
      </div>
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
    <div className={`flex ${isUser ? 'justify-end' : 'justify-start'} mb-3`}>
      {!isUser && (
        <div className="flex-shrink-0 w-7 h-7 rounded-full bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center mr-2 mt-0.5">
          <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        </div>
      )}

      <div className={`max-w-[85%] ${isUser ? 'items-end' : 'items-start'} flex flex-col`}>
        <div
          className={`px-3.5 py-2.5 rounded-2xl text-sm leading-relaxed shadow-sm ${
            isUser
              ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-tr-sm'
              : 'bg-surface border border-line text-ink rounded-tl-sm'
          }`}
        >
          {/* Render message content with basic markdown-like formatting */}
          <div className="whitespace-pre-wrap break-words">
            {message.content.split('\n').map((line, i) => {
              // Bold text: **text**
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
                  {i < message.content.split('\n').length - 1 && <br />}
                </span>
              )
            })}
          </div>

          {/* Render result table inside assistant bubbles */}
          {!isUser && resultData && <ResultTable data={resultData} />}
        </div>

        <span className="text-[10px] text-ink-faint mt-1 px-1">
          {formatTime(message.created_at)}
        </span>
      </div>

      {isUser && (
        <div className="flex-shrink-0 w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center ml-2 mt-0.5">
          <svg className="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
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
    <div className="flex justify-start mb-3">
      <div className="flex-shrink-0 w-7 h-7 rounded-full bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center mr-2 mt-0.5">
        <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      </div>
      <div className="bg-surface border border-line rounded-2xl rounded-tl-sm px-4 py-3 shadow-elev-sm">
        <div className="flex items-center gap-2">
          <div className="flex gap-1">
            <span className="w-2 h-2 rounded-full bg-purple-400 animate-bounce" style={{ animationDelay: '0ms' }} />
            <span className="w-2 h-2 rounded-full bg-blue-400 animate-bounce" style={{ animationDelay: '150ms' }} />
            <span className="w-2 h-2 rounded-full bg-purple-400 animate-bounce" style={{ animationDelay: '300ms' }} />
          </div>
          <span className="text-xs text-ink-muted font-medium">AI is thinking...</span>
        </div>
      </div>
    </div>
  )
}

// ─── Conversation sidebar ─────────────────────────────────────────────────────

interface ConversationSidebarProps {
  conversations: AiConversation[]
  activeId: number | null
  onSelect: (id: number) => void
  onNew: () => void
  onDelete: (id: number) => void
  loading: boolean
}

function ConversationSidebar({
  conversations,
  activeId,
  onSelect,
  onNew,
  onDelete,
  loading,
}: ConversationSidebarProps) {
  return (
    <div className="w-40 flex-shrink-0 border-r border-line bg-surface-2 flex flex-col h-full">
      {/* New chat button */}
      <div className="p-2 border-b border-line">
        <button
          onClick={onNew}
          className="w-full flex items-center justify-center gap-1.5 px-2 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-xs font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all shadow-sm"
        >
          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
          </svg>
          New Chat
        </button>
      </div>

      {/* Conversation list */}
      <div className="flex-1 overflow-y-auto py-1">
        {loading ? (
          <div className="p-3 space-y-2">
            {[1, 2, 3].map(i => (
              <div key={i} className="h-8 bg-surface-2 rounded animate-pulse" />
            ))}
          </div>
        ) : conversations.length === 0 ? (
          <p className="text-xs text-ink-faint text-center p-4 leading-relaxed">
            No conversations yet
          </p>
        ) : (
          conversations.map(conv => (
            <div
              key={conv.id}
              className={`group relative mx-1 my-0.5 rounded-lg transition-all ${
                activeId === conv.id
                  ? 'bg-blue-50 border border-blue-200'
                  : 'hover:bg-surface-2 border border-transparent'
              }`}
            >
              <button
                onClick={() => onSelect(conv.id)}
                className="w-full text-left px-2.5 py-2 pr-6"
              >
                <p
                  className={`text-xs font-medium truncate leading-tight ${
                    activeId === conv.id ? 'text-blue-700' : 'text-ink-muted'
                  }`}
                >
                  {conv.title}
                </p>
                <p className="text-[10px] text-ink-faint mt-0.5">{formatDate(conv.updated_at)}</p>
              </button>

              <button
                onClick={e => {
                  e.stopPropagation()
                  onDelete(conv.id)
                }}
                className="absolute right-1.5 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity p-0.5 text-ink-faint hover:text-red-500"
                title="Delete conversation"
              >
                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          ))
        )}
      </div>
    </div>
  )
}

// ─── Main AiPanel component ───────────────────────────────────────────────────

export default function AiPanel({ isOpen, onClose }: AiPanelProps) {
  const [messages, setMessages] = useState<AiMessage[]>([])
  const [conversations, setConversations] = useState<AiConversation[]>([])
  const [activeConversationId, setActiveConversationId] = useState<number | null>(null)
  const [input, setInput] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [convsLoading, setConvsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const messagesEndRef = useRef<HTMLDivElement>(null)
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  // Auto-scroll to bottom on new messages
  const scrollToBottom = useCallback(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [])

  useEffect(() => {
    scrollToBottom()
  }, [messages, isLoading, scrollToBottom])

  // Load conversations when panel opens
  useEffect(() => {
    if (isOpen) {
      loadConversations()
    }
  }, [isOpen])

  // Auto-resize textarea
  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto'
      textareaRef.current.style.height = Math.min(textareaRef.current.scrollHeight, 120) + 'px'
    }
  }, [input])

  // Focus textarea when panel opens
  useEffect(() => {
    if (isOpen) {
      setTimeout(() => textareaRef.current?.focus(), 300)
    }
  }, [isOpen])

  async function loadConversations() {
    setConvsLoading(true)
    try {
      const data = await fetchConversations()
      setConversations(data)
    } catch {
      // silently fail — conversations list is non-critical
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
      // Parse result_data from string if needed
      const parsed = msgs.map(m => ({
        ...m,
        result_data: m.result_data
          ? (typeof m.result_data === 'string' ? JSON.parse(m.result_data as string) : m.result_data)
          : null,
      }))
      setMessages(parsed)
    } catch {
      setError('Failed to load conversation.')
    } finally {
      setIsLoading(false)
    }
  }

  function startNewChat() {
    setMessages([])
    setActiveConversationId(null)
    setInput('')
    setError(null)
    textareaRef.current?.focus()
  }

  async function handleDeleteConversation(id: number) {
    try {
      await deleteConversation(id)
      setConversations(prev => prev.filter(c => c.id !== id))
      if (activeConversationId === id) {
        startNewChat()
      }
    } catch {
      // silently ignore
    }
  }

  async function handleSend(messageText?: string) {
    const text = (messageText ?? input).trim()
    if (!text || isLoading) return

    setInput('')
    setError(null)

    // Optimistically add user message
    const tempUserMessage: AiMessage = {
      id: Date.now(),
      role: 'user',
      content: text,
      result_data: null,
      created_at: new Date().toISOString(),
    }
    setMessages(prev => [...prev, tempUserMessage])
    setIsLoading(true)

    try {
      const res = await sendAiQuery(text, activeConversationId ?? undefined)

      // Update conversation ID
      setActiveConversationId(res.conversation_id)

      // Add assistant response
      const assistantMessage: AiMessage = {
        id: Date.now() + 1,
        role: 'assistant',
        content: res.message,
        result_data: res.result_data,
        created_at: new Date().toISOString(),
      }
      setMessages(prev => [...prev, assistantMessage])

      // Refresh conversations list (title may have been set)
      await loadConversations()
    } catch (err: any) {
      setError(err?.response?.data?.error ?? err?.message ?? 'Failed to get AI response.')
      // Remove the optimistic user message on error
      setMessages(prev => prev.filter(m => m.id !== tempUserMessage.id))
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
    <>
      {/* Overlay */}
      <div
        className={`fixed inset-0 bg-black/20 z-overlay transition-opacity duration-300 ${
          isOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
        onClick={onClose}
      />

      {/* Panel */}
      <div
        className={`fixed top-0 right-0 bottom-0 z-modal flex flex-col bg-surface shadow-elev-lg transition-transform duration-300 ease-in-out ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
        style={{ width: '480px' }}
      >
        {/* ── Header ── */}
        <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-purple-700 to-blue-700 text-white flex-shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
              <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>
            <div>
              <h2 className="text-sm font-bold leading-tight">AI Assistant</h2>
              <p className="text-[10px] text-white/70 leading-tight">Alpha Direct Intelligence</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* ── Body (sidebar + chat) ── */}
        <div className="flex flex-1 min-h-0">

          {/* Conversation sidebar */}
          <ConversationSidebar
            conversations={conversations}
            activeId={activeConversationId}
            onSelect={loadConversation}
            onNew={startNewChat}
            onDelete={handleDeleteConversation}
            loading={convsLoading}
          />

          {/* Chat area */}
          <div className="flex-1 flex flex-col min-w-0 min-h-0">

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-4">
              {isEmpty ? (
                /* Empty state with suggested prompts */
                <div className="h-full flex flex-col items-center justify-center">
                  <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-100 to-blue-100 flex items-center justify-center mb-4">
                    <svg className="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  </div>
                  <h3 className="text-sm font-bold text-ink mb-1">How can I help?</h3>
                  <p className="text-xs text-ink-muted mb-5 text-center leading-relaxed px-4">
                    Ask me anything about policies, payments, claims, or customers.
                  </p>
                  <div className="grid grid-cols-1 gap-2 w-full px-2">
                    {SUGGESTED_PROMPTS.map((prompt, i) => (
                      <button
                        key={i}
                        onClick={() => handleSend(prompt)}
                        className="text-left text-xs px-3 py-2.5 bg-surface-2 hover:bg-blue-50 border border-line hover:border-blue-300 text-ink-muted hover:text-blue-700 rounded-xl transition-all leading-tight font-medium"
                      >
                        <span className="text-purple-500 mr-1.5">›</span>
                        {prompt}
                      </button>
                    ))}
                  </div>
                </div>
              ) : (
                /* Message list */
                <div>
                  {isLoading && messages.length === 0 ? (
                    /* Loading skeleton when loading a conversation */
                    <div className="space-y-3">
                      {[1, 2, 3].map(i => (
                        <div key={i} className={`flex ${i % 2 === 0 ? 'justify-end' : 'justify-start'}`}>
                          <div className={`h-10 rounded-2xl animate-pulse ${i % 2 === 0 ? 'bg-blue-100 w-2/3' : 'bg-surface-2 w-3/4'}`} />
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
                </div>
              )}

              {/* Error banner */}
              {error && (
                <div className="mt-2 p-3 bg-red-50 border border-red-200 rounded-xl flex items-start gap-2">
                  <svg className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p className="text-xs text-red-700 flex-1">{error}</p>
                  <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              )}

              <div ref={messagesEndRef} />
            </div>

            {/* ── Input area ── */}
            <div className="flex-shrink-0 border-t border-line bg-surface p-3">
              <div className="flex items-end gap-2 bg-surface-2 border border-line rounded-2xl px-3 py-2 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100 transition-all">
                <textarea
                  ref={textareaRef}
                  value={input}
                  onChange={e => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Ask about policies, payments, claims..."
                  rows={1}
                  disabled={isLoading}
                  className="flex-1 bg-transparent text-sm text-ink placeholder-ink-faint resize-none outline-none py-1 min-h-[28px] max-h-[120px] disabled:opacity-50"
                  style={{ lineHeight: '1.5' }}
                />
                <button
                  onClick={() => handleSend()}
                  disabled={!input.trim() || isLoading}
                  className="flex-shrink-0 w-8 h-8 rounded-xl bg-gradient-to-br from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 disabled:from-gray-300 disabled:to-gray-300 text-white flex items-center justify-center transition-all shadow-sm disabled:shadow-none"
                >
                  {isLoading ? (
                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                  ) : (
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                  )}
                </button>
              </div>
              <p className="text-[10px] text-ink-faint text-center mt-1.5">
                Enter to send · Shift+Enter for new line
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
