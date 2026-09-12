import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { getHelpEntriesByCategory, searchHelpEntries, type HelpEntry } from '../../help/registry'

// Help landing page — lists every module grouped by area, with search.
// Modules that already have a guide are clickable; the rest show a
// "Guide coming soon" badge so the structure is visible from day one.
export default function HelpHomePage() {
  const [query, setQuery] = useState('')
  const grouped = useMemo(() => getHelpEntriesByCategory(), [])
  const matchedKeys = useMemo(() => new Set(searchHelpEntries(query).map((e) => e.key)), [query])

  const visibleGroups = grouped
    .map((g) => ({ ...g, entries: g.entries.filter((e) => matchedKeys.has(e.key)) }))
    .filter((g) => g.entries.length > 0)

  const total = grouped.reduce((n, g) => n + g.entries.length, 0)
  const withGuides = grouped.reduce((n, g) => n + g.entries.filter((e) => e.hasGuide).length, 0)

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">Help &amp; User Guides</h1>
        <p className="text-sm text-gray-500 mt-1">
          Step-by-step guides for every part of the portal. {withGuides} of {total} module guides published.
        </p>
      </div>

      <div className="relative mb-8">
        <svg
          className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
        </svg>
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search guides — e.g. policy, claim, refund…"
          className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-navy/30 focus:border-brand-navy"
        />
      </div>

      {visibleGroups.length === 0 ? (
        <p className="text-sm text-gray-500 text-center py-10">No guides match "{query}".</p>
      ) : (
        <div className="space-y-8">
          {visibleGroups.map((group) => (
            <section key={group.category}>
              <h2 className="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">{group.category}</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {group.entries.map((entry) => (
                  <HelpCard key={entry.key} entry={entry} />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  )
}

function HelpCard({ entry }: { entry: HelpEntry }) {
  const inner = (
    <>
      <div className="flex items-center justify-between gap-2">
        <h3 className="font-semibold text-gray-800">{entry.title}</h3>
        {entry.hasGuide ? (
          <svg className="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        ) : (
          <span className="text-[10px] font-medium text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full whitespace-nowrap">
            Coming soon
          </span>
        )}
      </div>
      <p className="text-sm text-gray-500 mt-1 leading-relaxed">{entry.summary}</p>
    </>
  )

  const base = 'block border rounded-lg p-4 transition'
  if (entry.hasGuide) {
    return (
      <Link to={`/help/${entry.key}`} className={`${base} border-gray-200 bg-white hover:border-brand-navy/40 hover:shadow-sm`}>
        {inner}
      </Link>
    )
  }
  return <div className={`${base} border-gray-100 bg-gray-50/60 cursor-default`}>{inner}</div>
}
