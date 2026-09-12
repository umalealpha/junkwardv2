/**
 * Loading skeletons — reserve layout space while async data loads so content
 * doesn't jump in (WCAG-adjacent UX: no layout shift). The shimmer uses
 * `animate-pulse`, which the global prefers-reduced-motion rule already
 * neutralises for motion-sensitive users.
 */

/** A single shimmering bar. Width/height are Tailwind classes. */
export function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`animate-pulse rounded bg-surface-2 ${className}`} aria-hidden="true" />
}

interface SkeletonTableProps {
  /** Number of placeholder rows. */
  rows?: number
  /** Number of columns per row. */
  cols?: number
}

/**
 * A table-shaped skeleton: a header strip plus `rows` × `cols` cells.
 * Drop this in place of the table body while the first page loads.
 */
export function SkeletonTable({ rows = 8, cols = 5 }: SkeletonTableProps) {
  return (
    <div className="w-full overflow-hidden rounded-lg border border-line" role="status" aria-label="Loading">
      <div className="flex gap-4 border-b border-line bg-surface-2/60 px-4 py-3">
        {Array.from({ length: cols }).map((_, i) => (
          <Skeleton key={i} className="h-3 flex-1" />
        ))}
      </div>
      <div className="divide-y divide-line">
        {Array.from({ length: rows }).map((_, r) => (
          <div key={r} className="flex items-center gap-4 px-4 py-3.5">
            {Array.from({ length: cols }).map((_, c) => (
              <Skeleton key={c} className={`h-3 flex-1 ${c === 0 ? 'max-w-[30%]' : ''}`} />
            ))}
          </div>
        ))}
      </div>
    </div>
  )
}
