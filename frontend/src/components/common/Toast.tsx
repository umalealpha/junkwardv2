import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react'

/**
 * Toast notifications — a non-blocking replacement for window.alert().
 *
 * Usage:
 *   const { toast } = useToast()
 *   toast.success('Policy renewed')
 *   toast.error(err?.response?.data?.message || 'Action failed')
 *
 * Mounted once at the app root (see App.tsx). Toasts stack top-right,
 * auto-dismiss after ~5s, are dismissible, and use the design-system
 * status tokens so they render correctly in light and dark mode.
 * aria-live="polite" announces them to screen readers.
 */

type ToastKind = 'success' | 'error' | 'info' | 'warning'

interface ToastItem {
  id: number
  kind: ToastKind
  message: string
}

interface ToastApi {
  success: (message: string) => void
  error: (message: string) => void
  info: (message: string) => void
  warning: (message: string) => void
}

const ToastContext = createContext<{ toast: ToastApi } | null>(null)

const AUTO_DISMISS_MS = 5000

const KIND: Record<ToastKind, { cls: string; iconPath: string }> = {
  success: { cls: 'bg-status-success-bg text-status-success-fg', iconPath: 'M5 13l4 4L19 7' },
  error:   { cls: 'bg-status-danger-bg text-status-danger-fg',   iconPath: 'M6 18L18 6M6 6l12 12' },
  warning: { cls: 'bg-status-warning-bg text-status-warning-fg', iconPath: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z' },
  info:    { cls: 'bg-status-info-bg text-status-info-fg',       iconPath: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([])
  const idRef = useRef(0)

  const remove = useCallback((id: number) => {
    setItems((s) => s.filter((t) => t.id !== id))
  }, [])

  const push = useCallback((kind: ToastKind, message: string) => {
    const id = (idRef.current += 1)
    setItems((s) => [...s, { id, kind, message }])
    window.setTimeout(() => remove(id), AUTO_DISMISS_MS)
  }, [remove])

  const toast = useMemo<ToastApi>(() => ({
    success: (m) => push('success', m),
    error:   (m) => push('error', m),
    info:    (m) => push('info', m),
    warning: (m) => push('warning', m),
  }), [push])

  return (
    <ToastContext.Provider value={{ toast }}>
      {children}
      <div
        aria-live="polite"
        aria-atomic="false"
        className="fixed top-4 right-4 z-toast flex flex-col gap-2 w-full max-w-sm pointer-events-none"
      >
        {items.map((t) => {
          const k = KIND[t.kind]
          return (
            <div
              key={t.id}
              role="status"
              className={`pointer-events-auto flex items-start gap-2.5 px-4 py-3 rounded-lg shadow-lg ring-1 ring-black/5 transition-opacity ${k.cls}`}
            >
              <svg className="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d={k.iconPath} />
              </svg>
              <span className="text-sm font-medium grow break-words">{t.message}</span>
              <button
                onClick={() => remove(t.id)}
                aria-label="Dismiss"
                className="shrink-0 -mr-1 -mt-0.5 text-lg leading-none opacity-60 hover:opacity-100 transition-opacity cursor-pointer"
              >
                &times;
              </button>
            </div>
          )
        })}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast(): { toast: ToastApi } {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used within a ToastProvider')
  return ctx
}
