import { useMemo } from 'react'
import { Link, useParams } from 'react-router-dom'
import { getHelpEntry } from '../../help/registry'
import { slugify } from '../../help/slug'
import Markdown from '../../components/common/Markdown'

// Renders a single module's guide. Builds a table of contents from the
// guide's ## headings and links out to the live module.
export default function HelpModulePage() {
  const { moduleKey } = useParams<{ moduleKey: string }>()
  const entry = getHelpEntry(moduleKey)

  const toc = useMemo(() => {
    if (!entry?.body) return []
    return [...entry.body.matchAll(/^##\s+(.+)$/gm)].map((m) => {
      const text = m[1].trim()
      return { text, id: slugify(text) }
    })
  }, [entry])

  if (!entry) {
    return (
      <div className="p-6 max-w-3xl mx-auto">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center text-red-600">
          That guide could not be found.
        </div>
        <Link to="/help" className="inline-block mt-4 text-sm text-brand-navy hover:underline">
          &larr; Back to Help
        </Link>
      </div>
    )
  }

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <Link to="/help" className="text-xs text-brand-navy hover:underline">
        &larr; Back to Help
      </Link>

      <div className="flex flex-wrap items-start justify-between gap-3 mt-2 mb-2">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">{entry.title}</h1>
          <p className="text-sm text-gray-500 mt-1">{entry.summary}</p>
        </div>
        <Link
          to={entry.route}
          className="px-3 py-1.5 text-xs font-medium text-white bg-brand-navy rounded-md hover:bg-brand-navy-light transition whitespace-nowrap"
        >
          Open this module →
        </Link>
      </div>
      {entry.lastReviewed && (
        <p className="text-[11px] text-gray-400 mb-4">Last reviewed: {entry.lastReviewed}</p>
      )}

      {entry.hasGuide ? (
        <div className="flex flex-col lg:flex-row gap-8 mt-4">
          {toc.length > 1 && (
            <nav className="lg:w-56 flex-shrink-0 lg:sticky lg:top-4 self-start">
              <p className="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">On this page</p>
              <ul className="space-y-1.5 border-l border-gray-200 pl-3">
                {toc.map((h) => (
                  <li key={h.id}>
                    <a href={`#${h.id}`} className="text-sm text-gray-600 hover:text-brand-navy transition block">
                      {h.text}
                    </a>
                  </li>
                ))}
              </ul>
            </nav>
          )}
          <article className="min-w-0 flex-1">
            <Markdown content={entry.body} />
          </article>
        </div>
      ) : (
        <div className="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
          <p className="text-gray-600 font-medium">This guide is coming soon.</p>
          <p className="text-sm text-gray-500 mt-1">
            In the meantime, open the module directly and explore — or check back after the next release.
          </p>
        </div>
      )}
    </div>
  )
}

