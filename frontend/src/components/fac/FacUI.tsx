import { useEffect, useRef } from 'react'
import '../../styles/fac.css'
import type { FacFlag, FacStatus } from '../../api/fac'
import { FAC_FLAG_LABELS, FAC_FLAG_TONE, FAC_STATUS_LABELS } from '../../api/fac'

/**
 * Shared visual primitives for the FAC register.
 *
 * These live in one place so the three FAC screens cannot drift apart — the
 * register, the placement detail and the settlements view are one product and
 * should read as one. The design decisions themselves are documented at the top
 * of ../../styles/fac.css.
 */

// ─── Page shell ───────────────────────────────────────────────────────────────

export function FacPage({ children }: { children: React.ReactNode }) {
  return <div className="fac-root p-6 space-y-4">{children}</div>
}

export function FacPageHead({ eyebrow, title, blurb, actions }: {
  eyebrow?: React.ReactNode
  title: string
  blurb?: string
  actions?: React.ReactNode
}) {
  return (
    <div className="flex items-start justify-between gap-4 flex-wrap">
      <div>
        {eyebrow && <div className="mb-1">{eyebrow}</div>}
        <h1 className="fac-title">{title}</h1>
        {blurb && <p className="fac-subtitle mt-1">{blurb}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

// ─── Tiles ────────────────────────────────────────────────────────────────────

export type TileTone = 'navy' | 'danger' | 'warn' | 'ok' | 'quiet'

/**
 * A scoreboard tile. Give it an `onClick` and it becomes the filter control for
 * the thing it counts — a count you cannot act on is decoration.
 */
export function FacTile({ label, value, tone, onClick, title }: {
  label: string
  value: string
  tone?: TileTone
  onClick?: () => void
  title?: string
}) {
  const cls = ['fac-tile', tone ? `fac-tile--${tone}` : ''].filter(Boolean).join(' ')

  return (
    <button
      type="button"
      className={cls}
      data-clickable={onClick ? 'true' : 'false'}
      onClick={onClick}
      disabled={!onClick}
      title={title}
    >
      <span className="fac-tile-label">{label}</span>
      <span className="fac-tile-value">{value}</span>
    </button>
  )
}

export function FacTiles({ cols, children }: { cols?: 4 | 5 | 6; children: React.ReactNode }) {
  const cls = cols === 4 ? 'fac-tiles fac-tiles-4' : cols === 5 ? 'fac-tiles fac-tiles-5' : 'fac-tiles'
  return <div className={cls}>{children}</div>
}

// ─── Panel ────────────────────────────────────────────────────────────────────

export function FacPanel({ title, action, bodyless, children }: {
  title?: string
  action?: React.ReactNode
  /** Set when the child is a table that owns its own padding. */
  bodyless?: boolean
  children: React.ReactNode
}) {
  return (
    <div className="fac-panel relative">
      {title && (
        <div className="fac-panel-head">
          <span className="fac-panel-title">{title}</span>
          {action}
        </div>
      )}
      {bodyless ? children : <div className="fac-panel-body">{children}</div>}
    </div>
  )
}

// ─── Flags and badges ─────────────────────────────────────────────────────────

export function FacFlags({ flags }: { flags: FacFlag[] }) {
  if (!flags.length) return null
  return (
    <div className="fac-flags">
      {flags.map(f => (
        <span key={f} className={`fac-flag fac-flag--${FAC_FLAG_TONE[f]}`}>{FAC_FLAG_LABELS[f]}</span>
      ))}
    </div>
  )
}

const BADGE_CLASS: Record<FacStatus, string> = {
  draft: 'draft',
  placed: 'placed',
  awaiting_premium: 'waiting',
  client_paid: 'paid',
  ready_to_settle: 'ready',
  settled: 'closed',
  cancelled: 'void',
}

export function FacStatusBadge({ status }: { status: FacStatus }) {
  return <span className={`fac-badge fac-badge--${BADGE_CLASS[status]}`}>{FAC_STATUS_LABELS[status]}</span>
}

// ─── Notes ────────────────────────────────────────────────────────────────────

export function FacNote({ tone, children }: {
  tone?: 'warn' | 'danger' | 'ok'
  children: React.ReactNode
}) {
  return <p className={`fac-note ${tone ? `fac-note--${tone}` : ''}`}>{children}</p>
}

// ─── Definition rows ──────────────────────────────────────────────────────────

export function FacRow({ k, v, strong }: { k: string; v?: string | null; strong?: boolean }) {
  return (
    <div className="fac-dt-row">
      <span className="fac-dt">{k}</span>
      <span className={`fac-dd ${strong ? 'fac-dd--strong' : ''} ${!v ? 'fac-dd--empty' : ''}`}>{v || '—'}</span>
    </div>
  )
}

// ─── Form bits ────────────────────────────────────────────────────────────────

export function FacFieldset({ legend, children }: { legend: string; children: React.ReactNode }) {
  return (
    <div className="fac-fieldset">
      <span className="fac-legend">{legend}</span>
      {children}
    </div>
  )
}

/**
 * The control is rendered INSIDE the <label>, which gives it an implicit
 * accessible name with no id plumbing. An earlier version put the label
 * alongside the control as a sibling, which looks identical and is worth
 * nothing — axe reported every select on the page as having no accessible name,
 * meaning a screen-reader user hears "combo box" and nothing else.
 */
export function FacField({ label, hint, error, children }: {
  label: string
  hint?: string
  /**
   * The server's complaint about THIS field. Rejections used to arrive as a
   * single browser alert reading "The given data was invalid", which named
   * nothing — the capturer had to guess which of eighteen fields was wrong.
   */
  error?: string
  children: React.ReactNode
}) {
  return (
    <label className="fac-field" data-invalid={error ? 'true' : 'false'}>
      <span className="fac-label">{label}</span>
      {children}
      {error && <span className="fac-error">{error}</span>}
      {hint && <span className="fac-hint">{hint}</span>}
    </label>
  )
}

// ─── Dialog ───────────────────────────────────────────────────────────────────

/**
 * Escape closes, the panel traps the initial focus, and the backdrop click is
 * scoped to the backdrop itself — the three things a modal is normally missing.
 */
export function FacDialog({ title, onClose, width = 'md', footer, children }: {
  title: string
  onClose: () => void
  width?: 'md' | 'lg'
  footer?: React.ReactNode
  children: React.ReactNode
}) {
  const panel = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])

  // Focus the first control ONCE, on open.
  //
  // This used to sit in the effect above, which depends on `onClose` — and every
  // caller passes `onClose` as an inline arrow, so it is a new function on every
  // render. Every keystroke re-rendered the page, re-ran the effect and pulled
  // focus back to the first field, so no field could be typed into: one
  // character, then focus jumped. Reported by Reinsurance on 10 Aug 2026.
  // The empty dependency list is the fix — it must stay empty.
  useEffect(() => {
    panel.current?.querySelector<HTMLElement>('input, select, textarea, button')?.focus()
  }, [])

  return (
    <div
      className="fac-overlay"
      style={{ paddingTop: '48px' }}
      role="presentation"
      onClick={e => { if (e.target === e.currentTarget) onClose() }}
    >
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className="fac-dialog"
        style={{ maxWidth: width === 'lg' ? '820px' : '460px' }}
      >
        <div className="fac-dialog-head">
          <h2 className="fac-dialog-title">{title}</h2>
          <button type="button" className="fac-x" onClick={onClose} aria-label="Close">✕</button>
        </div>
        <div className="fac-dialog-body">{children}</div>
        {footer && <div className="fac-dialog-foot">{footer}</div>}
      </div>
    </div>
  )
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────

export function FacTabs<T extends string>({ value, tabs, onChange }: {
  value: T
  tabs: Array<[T, string]>
  onChange: (v: T) => void
}) {
  return (
    <div className="fac-tabs" role="tablist">
      {tabs.map(([k, label]) => (
        <button
          key={k}
          type="button"
          role="tab"
          aria-selected={value === k}
          className="fac-tab"
          onClick={() => onChange(k)}
        >
          {label}
        </button>
      ))}
    </div>
  )
}
