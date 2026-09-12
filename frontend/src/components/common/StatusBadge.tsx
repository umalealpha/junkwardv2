// Semantic status → token classes. Uses the design-system status tokens
// (src/index.css), so every badge gets correct light/dark colours and
// WCAG-AA contrast automatically. Previously hardcoded Tailwind palette
// classes (e.g. bg-yellow-100 text-yellow-700) which failed contrast and
// had no dark-mode variant.
const VARIANT_CLASSES: Record<string, string> = {
  active:      'bg-status-success-bg text-status-success-fg',
  'in-active': 'bg-status-danger-bg text-status-danger-fg',
  inactive:    'bg-status-danger-bg text-status-danger-fg',
  cancelled:   'bg-status-danger-bg text-status-danger-fg',
  expired:     'bg-status-warning-bg text-status-warning-fg',
  pending:     'bg-status-accent-bg text-status-accent-fg',
  settled:     'bg-status-success-bg text-status-success-fg',
  paid:        'bg-status-success-bg text-status-success-fg',
  approved:    'bg-status-success-bg text-status-success-fg',
  rejected:    'bg-status-danger-bg text-status-danger-fg',
  default:     'bg-surface-2 text-ink-muted',
}

interface StatusBadgeProps {
  status: string | null | undefined
  label?: string
}

/** Title Case a status string: "in-active" → "In-Active", "cancelled" → "Cancelled" */
function titleCase(str: string): string {
  return str.replace(/\b\w/g, (c) => c.toUpperCase())
}

/**
 * Status badge. Renders a coloured pill for known statuses, falls back
 * to a neutral grey "Unknown" pill for null / empty / unrecognised
 * statuses — never renders a blank invisible badge.
 *
 * UAT 2026-05-26 (Arjun H6, Prathap BUG-007): claim list rows were
 * shipping `claim_status = null` for ~1,550 of 3,503 claims, which
 * rendered as an empty colourless pill. Testers couldn't tell whether
 * the cell was supposed to be empty or whether the data load failed.
 * "Unknown" makes the missing-data case visible.
 */
export default function StatusBadge({ status, label }: StatusBadgeProps) {
  const normalized = (status ?? '').toString().trim()
  if (normalized === '') {
    return (
      <span
        className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-surface-2 text-ink-faint italic"
        title="Status not set"
      >
        Unknown
      </span>
    )
  }
  const classes = VARIANT_CLASSES[normalized.toLowerCase()] ?? VARIANT_CLASSES.default
  return (
    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${classes}`} title={label ?? titleCase(normalized)}>
      {label ?? titleCase(normalized)}
    </span>
  )
}
