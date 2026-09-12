import { useRef, useEffect, useState } from 'react'

/**
 * Wraps a table with synchronized top + bottom horizontal scrollbar.
 * Drop-in replacement for <div className="overflow-x-auto">...</div>
 */
export default function DualScrollTable({ children }: { children: React.ReactNode }) {
  const topRef = useRef<HTMLDivElement>(null)
  const bottomRef = useRef<HTMLDivElement>(null)
  const [scrollWidth, setScrollWidth] = useState(0)

  useEffect(() => {
    const el = bottomRef.current
    if (!el) return
    const update = () => setScrollWidth(el.scrollWidth)
    update()
    const obs = new ResizeObserver(update)
    obs.observe(el)
    return () => obs.disconnect()
  }, [children])

  const syncScroll = (source: 'top' | 'bottom') => {
    const from = source === 'top' ? topRef.current : bottomRef.current
    const to = source === 'top' ? bottomRef.current : topRef.current
    if (from && to) to.scrollLeft = from.scrollLeft
  }

  return (
    <div>
      {/* Top scrollbar */}
      <div ref={topRef} onScroll={() => syncScroll('top')}
        className="overflow-x-auto" style={{ height: 12 }}>
        <div style={{ width: scrollWidth, height: 1 }} />
      </div>
      {/* Actual content with bottom scrollbar */}
      <div ref={bottomRef} onScroll={() => syncScroll('bottom')}
        className="overflow-x-auto">
        {children}
      </div>
    </div>
  )
}
