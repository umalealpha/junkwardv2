import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { ListMeta } from '../../api/kyc'

/**
 * URL-searchParams-driven filter/page state for the AD Group KYC pages.
 * Filter changes reset `page`.
 */
export function useSearchParamsPagination() {
  const [searchParams, setSearchParams] = useSearchParams()

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  return { updateFilter, goToPage }
}

/**
 * Module-level pagination bar (windowed pager + "Go to" jump input). Kept
 * at module scope so its internal input state survives parent re-renders —
 * defining it inside the hook remounted it on every keystroke.
 */
export function PaginationBar({ meta, goToPage }: { meta: ListMeta | undefined; goToPage: (p: number) => void }) {
  const [jumpPage, setJumpPage] = useState('')

  if (!meta || meta.last_page <= 1) return null
  const currentPage = meta.current_page
  const lastPage = meta.last_page

  const pages: (number | '...')[] = []
  if (lastPage <= 7) {
    for (let i = 1; i <= lastPage; i++) pages.push(i)
  } else {
    pages.push(1)
    if (currentPage > 3) pages.push('...')
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) pages.push(i)
    if (currentPage < lastPage - 2) pages.push('...')
    pages.push(lastPage)
  }

  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-line bg-surface-2 text-sm">
      <span className="text-ink-muted">Showing {meta.from}–{meta.to} of {meta.total}</span>
      <div className="flex items-center gap-1">
        <button type="button" disabled={currentPage === 1} onClick={() => goToPage(currentPage - 1)}
          className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
        {pages.map((p, i) =>
          p === '...' ? (
            <span key={`e${i}`} className="px-1.5 text-ink-faint">...</span>
          ) : (
            <button type="button" key={p} onClick={() => goToPage(p as number)}
              className={`px-2.5 py-1 rounded border text-sm ${p === currentPage ? 'bg-primary text-primary-contrast border-primary' : 'border-line text-ink-muted hover:bg-surface'}`}>{p}</button>
          )
        )}
        <button type="button" disabled={currentPage === lastPage} onClick={() => goToPage(currentPage + 1)}
          className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        <div className="flex items-center gap-1 ml-3 border-l border-line pl-3">
          <span className="text-ink-muted text-xs">Go to</span>
          <input type="number" min={1} max={lastPage} value={jumpPage}
            onChange={e => setJumpPage(e.target.value)}
            onKeyDown={e => {
              if (e.key === 'Enter') {
                const p = Number(jumpPage)
                if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') }
              }
            }}
            className="w-14 px-2 py-1 border border-line rounded text-sm text-center bg-surface text-ink" placeholder="#" />
        </div>
      </div>
    </div>
  )
}
