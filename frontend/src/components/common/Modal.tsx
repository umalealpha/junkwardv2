import { useEffect, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { useModalDismiss } from '../../hooks/useModalDismiss'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
  footer?: ReactNode
  /** Max width of the modal box. Defaults to 'lg' (the previous fixed value). */
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl'
}

const SIZE: Record<NonNullable<ModalProps['size']>, string> = {
  sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg',
  xl: 'max-w-xl', '2xl': 'max-w-2xl', '3xl': 'max-w-3xl',
}

const FOCUSABLE =
  'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),' +
  'textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'

export default function Modal({ open, onClose, title, children, footer, size = 'lg' }: ModalProps) {
  // Esc-to-close, backdrop-click-to-close, and background scroll-lock.
  const { overlayProps } = useModalDismiss(open, onClose)
  const panelRef = useRef<HTMLDivElement>(null)

  // Focus management (WCAG 2.4.3): move focus into the dialog on open, keep
  // Tab cycling trapped inside it, and restore focus to whatever was focused
  // before — so keyboard users aren't dumped back at the top of the page.
  useEffect(() => {
    if (!open) return
    const previouslyFocused = document.activeElement as HTMLElement | null
    const panel = panelRef.current

    const visibleFocusables = () =>
      panel
        ? Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(el => el.offsetParent !== null)
        : []

    // Initial focus: first focusable control, else the panel itself.
    ;(visibleFocusables()[0] ?? panel)?.focus()

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return
      const els = visibleFocusables()
      if (els.length === 0) { e.preventDefault(); panel?.focus(); return }
      const first = els[0]
      const last = els[els.length - 1]
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus()
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus()
      }
    }
    document.addEventListener('keydown', onKeyDown, true)
    return () => {
      document.removeEventListener('keydown', onKeyDown, true)
      previouslyFocused?.focus?.()
    }
  }, [open])

  if (!open) return null

  // Portal to <body> so the modal is never trapped by a transformed/overflow
  // ancestor (e.g. the page-enter wrapper) — it always anchors to the viewport.
  return createPortal((
    <div
      role="dialog"
      aria-modal="true"
      aria-label={title}
      // p-4 keeps the box off the screen edges; overflow-y-auto lets the whole
      // overlay scroll on very short viewports as a last-resort fallback.
      className="fixed inset-0 z-modal flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
      {...overlayProps}
    >
      {/* max-h + flex-col so the body scrolls INSIDE the modal, header/footer pinned */}
      <div
        ref={panelRef}
        tabIndex={-1}
        className={`bg-surface rounded-lg shadow-elev-lg w-full ${SIZE[size]} max-h-[85vh] flex flex-col overflow-hidden outline-none`}
      >
        <div className="flex items-center justify-between px-5 py-3 border-b border-line shrink-0">
          <h3 className="text-lg font-semibold text-ink">{title}</h3>
          <button onClick={onClose} aria-label="Close" className="text-ink-faint hover:text-ink text-xl leading-none cursor-pointer">&times;</button>
        </div>
        <div className="px-5 py-3 overflow-y-auto grow min-h-0">{children}</div>
        {footer && <div className="px-5 py-2.5 border-t border-line bg-surface-2 flex justify-end gap-2 shrink-0">{footer}</div>}
      </div>
    </div>
  ), document.body)
}
