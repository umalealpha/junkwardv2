import { useNavigate, useLocation } from 'react-router-dom'
import { useEffect, useRef, useState } from 'react'
import { openAlphaBridgeWidget } from '../../utils/alphaBridge'
import { useToast } from './Toast'

/**
 * Right-edge action rail — a small vertical stack of quick-launch tabs docked
 * to the right edge of every screen: AI Assistant (purple→blue) and Help Desk
 * (navy), each keeping its own colour and expanding to its label on hover.
 *
 * The rail is dragged as ONE unit (so the tabs can never overlap each other);
 * its vertical position persists per browser (localStorage). Click launches the
 * tab's action; a drag is suppressed from firing a click. The Help Desk tab is
 * hidden while inside the Help Desk so it can't cover its own UI.
 */
const STORAGE_KEY = 'action_rail_top'
const MARGIN = 80 // keep the rail this far from the top/bottom edges

export default function ActionRail() {
  const navigate = useNavigate()
  const location = useLocation()
  const { toast } = useToast()
  const onAi = location.pathname === '/ai'

  // Issue reporting now goes through the Alpha Bridge widget (Graphite's
  // helpdesk module is migrating to bridge.alphadirect.co.bw).
  const reportIssue = () =>
    openAlphaBridgeWidget().catch(() =>
      toast.error('Could not open the issue reporter — Alpha Bridge is unreachable. Please try again.')
    )

  const [top, setTop] = useState<number>(() => {
    const saved = Number(localStorage.getItem(STORAGE_KEY))
    if (saved && !Number.isNaN(saved)) return saved
    return typeof window !== 'undefined' ? Math.round(window.innerHeight * 0.55) : 380
  })
  const topRef = useRef(top)
  topRef.current = top
  const draggedRef = useRef(false)

  useEffect(() => {
    const clamp = () => setTop((t) => Math.min(Math.max(t, MARGIN), window.innerHeight - MARGIN))
    window.addEventListener('resize', clamp)
    return () => window.removeEventListener('resize', clamp)
  }, [])

  const startDrag = (e: React.PointerEvent) => {
    if (e.button !== 0) return
    draggedRef.current = false
    const startY = e.clientY
    const startTop = topRef.current
    const onMove = (ev: PointerEvent) => {
      const dy = ev.clientY - startY
      if (Math.abs(dy) > 4) draggedRef.current = true
      setTop(Math.min(Math.max(startTop + dy, MARGIN), window.innerHeight - MARGIN))
    }
    const onUp = () => {
      document.removeEventListener('pointermove', onMove)
      document.removeEventListener('pointerup', onUp)
      localStorage.setItem(STORAGE_KEY, String(topRef.current))
    }
    document.addEventListener('pointermove', onMove)
    document.addEventListener('pointerup', onUp)
  }

  // Suppress the click that ends a drag.
  const act = (fn: () => void) => () => { if (!draggedRef.current) fn() }

  const tabBase =
    'group flex items-center rounded-l-xl shadow-lg pl-3 pr-3 py-3 text-white cursor-grab active:cursor-grabbing ' +
    'transition-all duration-200 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1'
  const labelCls =
    'max-w-0 overflow-hidden whitespace-nowrap text-sm font-medium group-hover:ml-2 group-hover:max-w-[96px] transition-all duration-200'

  return (
    <div className="fixed right-0 z-drawer -translate-y-1/2 flex flex-col items-end gap-2.5 select-none" style={{ top }}>
      {/* AI Assistant */}
      <button
        onPointerDown={startDrag}
        onClick={act(() => navigate('/ai'))}
        title="AI Assistant — click to open · drag to move"
        aria-label="AI Assistant"
        className={`${tabBase} bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 focus-visible:ring-purple-400 ${onAi ? 'ring-2 ring-blue-300' : ''}`}
      >
        <svg className="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
        <span className={labelCls}>AI Assistant</span>
      </button>

      {/* Help Desk */}
      <button
        onPointerDown={startDrag}
        onClick={act(() => { void reportIssue() })}
        title="Help Desk — click to report an issue · drag to move"
        aria-label="Help Desk — report an issue"
        className={`${tabBase} bg-brand-navy hover:bg-brand-navy/90 focus-visible:ring-brand-orange`}
      >
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M18 12v-1a6 6 0 10-12 0v1m12 0a2 2 0 012 2v2a2 2 0 01-2 2h-1v-6h1zM6 12a2 2 0 00-2 2v2a2 2 0 002 2h1v-6H6zm6 6v1a2 2 0 002 2h1" />
        </svg>
        <span className={labelCls}>Help Desk</span>
      </button>
    </div>
  )
}
