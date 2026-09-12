import type { ReactNode } from 'react'

interface EmptyStateProps {
  /** Short, plain-language headline — e.g. "No policies yet". */
  title: string
  /** Optional one-line guidance on what to do next. */
  description?: ReactNode
  /** Optional call-to-action (usually a button). */
  action?: ReactNode
  /** Override the default icon. */
  icon?: ReactNode
  /** Tighter vertical padding for inline/in-card use. */
  compact?: boolean
}

/**
 * Branded empty state — replaces bare "No data" text and blank screens.
 * Centered icon + headline + optional guidance + optional action, themed via
 * design tokens so it works in light and dark mode.
 */
export default function EmptyState({ title, description, action, icon, compact }: EmptyStateProps) {
  return (
    <div
      className={`flex flex-col items-center justify-center text-center ${compact ? 'py-8' : 'py-16'} px-4`}
      role="status"
    >
      <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-2 text-ink-faint">
        {icon ?? (
          <svg viewBox="0 0 24 24" fill="none" className="h-6 w-6" stroke="currentColor" strokeWidth={1.6} aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h10.5" />
          </svg>
        )}
      </div>
      <p className="text-sm font-semibold text-ink">{title}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-ink-muted">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
