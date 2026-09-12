import { useState } from 'react'

interface PaginationProps {
  currentPage: number
  lastPage: number
  total: number
  from: number | null
  to: number | null
  onPageChange: (page: number) => void
}

export default function Pagination({ currentPage, lastPage, total, from, to, onPageChange }: PaginationProps) {
  const [jumpPage, setJumpPage] = useState('')

  if (lastPage <= 1) return null

  function getPageNumbers(): (number | '...')[] {
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
    return pages
  }

  function handleJump() {
    const p = Number(jumpPage)
    if (p >= 1 && p <= lastPage) {
      onPageChange(p)
      setJumpPage('')
    }
  }

  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-line bg-surface-2 text-sm">
      <span className="text-ink-muted">
        Showing {from ?? 1}&ndash;{to ?? 0} of {total.toLocaleString()}
      </span>
      <div className="flex items-center gap-1">
        <button
          disabled={currentPage === 1}
          onClick={() => onPageChange(currentPage - 1)}
          className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          Prev
        </button>
        {getPageNumbers().map((p, i) =>
          p === '...' ? (
            <span key={`e${i}`} className="px-1.5 text-ink-faint">&hellip;</span>
          ) : (
            <button
              key={p}
              onClick={() => onPageChange(p as number)}
              className={`px-2.5 py-1 rounded border text-sm ${
                p === currentPage
                  ? 'bg-primary text-primary-contrast border-primary'
                  : 'border-line text-ink-muted hover:bg-surface-2'
              }`}
            >
              {p}
            </button>
          )
        )}
        <button
          disabled={currentPage === lastPage}
          onClick={() => onPageChange(currentPage + 1)}
          className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          Next
        </button>
        <div className="flex items-center gap-1 ml-3 border-l border-line pl-3">
          <span className="text-ink-muted text-xs">Go to</span>
          <input
            type="number"
            min={1}
            max={lastPage}
            value={jumpPage}
            onChange={e => setJumpPage(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') handleJump() }}
            className="w-14 px-2 py-1 border border-line rounded text-sm text-center bg-surface text-ink"
            placeholder="#"
          />
        </div>
      </div>
    </div>
  )
}
