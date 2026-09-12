import type { ReactNode } from 'react'

/**
 * Card — a tokenized surface panel for the "Elevated" theme. Composable:
 * pass `title`/`header` for a bordered header row, or just children. Padding
 * is configurable (default p-4) and applies to the body; the header keeps its
 * own inset so a full-bleed header divider lines up with the card edges.
 */

interface CardProps {
  children: ReactNode
  /** Convenience title rendered in the header row. */
  title?: ReactNode
  /** Full custom header content (overrides `title` when provided). */
  header?: ReactNode
  /** Body padding utility. Defaults to 'p-4'. */
  padding?: string
  className?: string
}

export default function Card({ children, title, header, padding = 'p-4', className = '' }: CardProps) {
  const headerContent = header ?? (title != null ? (
    <h3 className="text-sm font-semibold text-ink">{title}</h3>
  ) : null)

  return (
    <div className={`bg-surface border border-line rounded-[10px] shadow-elev-sm ${className}`}>
      {headerContent && (
        <div className="px-4 py-3 border-b border-line">{headerContent}</div>
      )}
      <div className={padding}>{children}</div>
    </div>
  )
}
