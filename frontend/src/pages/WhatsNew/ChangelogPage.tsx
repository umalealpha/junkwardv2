import { useEffect, useState } from 'react'
import { fetchReleaseNotes, type ReleaseNote } from '../../api/releaseNotes'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'

const TAG: Record<string, { label: string; cls: string }> = {
  feature:     { label: 'New',      cls: 'bg-brand-orange/10 text-brand-orange' },
  improvement: { label: 'Improved', cls: 'bg-brand-navy/10 text-brand-navy' },
  fix:         { label: 'Fixed',    cls: 'bg-green-100 text-green-700' },
}

export default function ChangelogPage() {
  const [notes, setNotes] = useState<ReleaseNote[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchReleaseNotes().then(setNotes).catch(() => { /* shown as empty */ }).finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6 max-w-3xl mx-auto">
      <h1 className="text-2xl font-bold text-gray-800 mb-1">What's New</h1>
      <p className="text-sm text-gray-500 mb-6">Updates, improvements and fixes to the Alpha Direct portal.</p>

      {loading ? (
        <div className="py-12 flex justify-center"><LoadingSpinner /></div>
      ) : notes.length === 0 ? (
        <p className="text-sm text-gray-500">No updates yet.</p>
      ) : (
        <ol className="relative border-l-2 border-gray-100 ml-2 space-y-8">
          {notes.map(n => (
            <li key={n.id} className="ml-6">
              <span className="absolute -left-[7px] mt-1.5 w-3 h-3 rounded-full bg-brand-orange" />
              <div className="flex items-baseline gap-2 flex-wrap">
                <h2 className="text-base font-semibold text-gray-800">{n.title}</h2>
                {n.version && <span className="text-xs text-gray-400">v{n.version}</span>}
                {n.publishedAt && <span className="text-xs text-gray-400 ml-auto">{fmtDate(n.publishedAt)}</span>}
              </div>
              <ul className="mt-3 space-y-2">
                {(n.highlights ?? []).map((h, i) => {
                  const t = TAG[h.tag ?? ''] ?? { label: 'Update', cls: 'bg-gray-100 text-gray-600' }
                  return (
                    <li key={i} className="flex gap-2.5 text-sm text-gray-700">
                      <span className={`self-start shrink-0 mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold ${t.cls}`}>{t.label}</span>
                      <span>{h.text}</span>
                    </li>
                  )
                })}
              </ul>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}
