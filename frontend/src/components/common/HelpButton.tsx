import { Link } from 'react-router-dom'
import { getHelpEntry } from '../../help/registry'

// Small contextual "?" button that deep-links a module page to its guide.
// Drop it into a page header: <HelpButton moduleKey="policies" />
// Renders nothing if there is no published guide for that module yet.
export default function HelpButton({
  moduleKey,
  label = 'Help',
  className = '',
}: {
  moduleKey: string
  label?: string
  className?: string
}) {
  const entry = getHelpEntry(moduleKey)
  if (!entry?.hasGuide) return null

  return (
    <Link
      to={`/help/${moduleKey}`}
      title={`Help: ${entry.title}`}
      className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-brand-navy border border-brand-navy/30 rounded-md hover:bg-brand-navy/5 transition ${className}`}
    >
      <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      {label}
    </Link>
  )
}
