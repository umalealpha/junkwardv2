import { useEffect, type MouseEvent } from 'react'

/**
 * Accessible modal dismissal + background scroll-lock — the single source of
 * truth for "how a modal closes" across the app.
 *
 * While `open` is true:
 *   - pressing Escape calls `onClose` (capture phase, so the top-most modal
 *     wins and the event doesn't also bubble to a parent modal),
 *   - the page behind is scroll-locked so it can't drift under the overlay.
 *
 * Spread the returned `overlayProps` onto the backdrop element to also get
 * click-outside-to-close (fires only when the backdrop itself is clicked,
 * never when a click bubbles up from the modal panel):
 *
 *   const { overlayProps } = useModalDismiss(open, onClose)
 *   return <div className="fixed inset-0 …" {...overlayProps}> … </div>
 */
export function useModalDismiss(open: boolean, onClose: () => void) {
  useEffect(() => {
    if (!open) return

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation()
        onClose()
      }
    }
    // Capture phase: the most recently-opened modal handles Escape first.
    document.addEventListener('keydown', onKey, true)

    const prevOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', onKey, true)
      document.body.style.overflow = prevOverflow
    }
  }, [open, onClose])

  return {
    overlayProps: {
      onClick: (e: MouseEvent) => {
        if (e.target === e.currentTarget) onClose()
      },
    },
  }
}
