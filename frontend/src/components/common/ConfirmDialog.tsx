import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'

/**
 * Branded confirmation dialog — a promise-based replacement for the native
 * window.confirm().
 *
 * Usage:
 *   const confirm = useConfirm()
 *   if (!(await confirm('Delete this item?'))) return
 *   // or, for destructive actions:
 *   if (!(await confirm({ message: 'Delete this item?', danger: true, confirmText: 'Delete' }))) return
 *
 * Mounted once at the app root (see App.tsx). Resolves true on confirm,
 * false on cancel / overlay click / Escape. Enter confirms.
 */

export interface ConfirmOptions {
  title?: string
  message: ReactNode
  confirmText?: string
  cancelText?: string
  danger?: boolean
}

type ConfirmFn = (opts: string | ConfirmOptions) => Promise<boolean>

const ConfirmContext = createContext<ConfirmFn | null>(null)

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [opts, setOpts] = useState<ConfirmOptions | null>(null)
  const resolverRef = useRef<((v: boolean) => void) | undefined>(undefined)

  const confirm = useCallback<ConfirmFn>((input) => {
    const next = typeof input === 'string' ? { message: input } : input
    return new Promise<boolean>((resolve) => {
      resolverRef.current = resolve
      setOpts(next)
    })
  }, [])

  const resolve = useCallback((result: boolean) => {
    resolverRef.current?.(result)
    resolverRef.current = undefined
    setOpts(null)
  }, [])

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      {opts && <ConfirmModal opts={opts} onResolve={resolve} />}
    </ConfirmContext.Provider>
  )
}

function ConfirmModal({ opts, onResolve }: { opts: ConfirmOptions; onResolve: (v: boolean) => void }) {
  const { title = 'Please confirm', message, confirmText = 'Confirm', cancelText = 'Cancel', danger } = opts
  const confirmRef = useRef<HTMLButtonElement>(null)

  // Focus the confirm button on open; Esc cancels, Enter confirms.
  useEffect(() => {
    confirmRef.current?.focus()
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') { e.preventDefault(); onResolve(false) }
      else if (e.key === 'Enter') { e.preventDefault(); onResolve(true) }
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onResolve])

  return (
    <div
      role="dialog" aria-modal="true" aria-label={title}
      className="fixed inset-0 z-modal flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
      onClick={e => { if (e.target === e.currentTarget) onResolve(false) }}
    >
      <div className="bg-surface rounded-xl shadow-elev-lg w-full max-w-md flex flex-col overflow-hidden">
        <div className="px-5 pt-5 pb-4">
          <h3 className="text-base font-semibold text-ink">{title}</h3>
          <div className="mt-2 text-sm text-ink-muted whitespace-pre-line">{message}</div>
        </div>
        <div className="flex items-center justify-end gap-2 px-5 py-3 border-t border-line bg-surface-2">
          <button
            onClick={() => onResolve(false)}
            className="px-4 py-2 text-sm font-medium text-ink-muted border border-line rounded-md hover:bg-surface-2 transition cursor-pointer"
          >
            {cancelText}
          </button>
          <button
            ref={confirmRef}
            onClick={() => onResolve(true)}
            className={`px-4 py-2 text-sm font-medium text-white rounded-md transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 ${
              danger
                ? 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500'
                : 'bg-brand-navy hover:bg-brand-navy/90 focus-visible:ring-brand-navy'
            }`}
          >
            {confirmText}
          </button>
        </div>
      </div>
    </div>
  )
}

export function useConfirm(): ConfirmFn {
  const ctx = useContext(ConfirmContext)
  if (!ctx) throw new Error('useConfirm must be used within a ConfirmProvider')
  return ctx
}
