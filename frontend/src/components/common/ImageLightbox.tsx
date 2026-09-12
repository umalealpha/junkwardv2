import { useCallback, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import LoadingSpinner from './LoadingSpinner'

/**
 * Reusable image lightbox / gallery — carousel-style viewer in a full-window
 * modal. Rendered through a portal to <body> so it always sits above the rest
 * of the app (page content lives inside a transformed wrapper that would
 * otherwise trap its z-index). Supports prev/next (+ keyboard ←/→), a thumbnail
 * strip, zoom in/out, fullscreen, download, and Esc / click-outside to close.
 *
 * Each item supplies a lazy `load()` returning a displayable src (object URL
 * for auth-gated blobs, or a plain URL). The current image loads first, the
 * rest load in the background for the thumbnails; object URLs are revoked on
 * unmount. Drop into any module that shows images.
 */
export interface LightboxItem {
  name: string
  load: () => Promise<string>
}

interface Props {
  items: LightboxItem[]
  startIndex?: number
  onClose: () => void
}

const ZOOM_MIN = 1
const ZOOM_MAX = 4
const ZOOM_STEP = 0.25

export default function ImageLightbox({ items, startIndex = 0, onClose }: Props) {
  const total = items.length
  const [index, setIndex] = useState(Math.min(Math.max(startIndex, 0), Math.max(total - 1, 0)))
  const [loaded, setLoaded] = useState<Record<number, string>>({})
  const [failed, setFailed] = useState<Record<number, boolean>>({})
  const [zoom, setZoom] = useState(1)
  const [isFs, setIsFs] = useState(false)
  const loadedRef = useRef(loaded)
  loadedRef.current = loaded
  const rootRef = useRef<HTMLDivElement>(null)

  const go = useCallback((next: number) => {
    if (total === 0) return
    setZoom(1)
    setIndex(((next % total) + total) % total) // wrap around
  }, [total])

  // Load current first, then the rest in the background (thumbnails).
  useEffect(() => {
    let cancelled = false
    const order = [index, ...items.map((_, i) => i).filter((i) => i !== index)]
    ;(async () => {
      for (const i of order) {
        if (cancelled) break
        if (loadedRef.current[i]) continue
        try {
          const url = await items[i].load()
          if (!cancelled) setLoaded((p) => ({ ...p, [i]: url }))
        } catch {
          if (!cancelled) setFailed((p) => ({ ...p, [i]: true }))
        }
      }
    })()
    return () => { cancelled = true }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Keyboard navigation. In fullscreen, let Esc exit fullscreen (don't close).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') { if (document.fullscreenElement) return; onClose() }
      else if (e.key === 'ArrowRight') go(index + 1)
      else if (e.key === 'ArrowLeft') go(index - 1)
      else if (e.key === '+' || e.key === '=') setZoom((z) => Math.min(ZOOM_MAX, +(z + ZOOM_STEP).toFixed(2)))
      else if (e.key === '-') setZoom((z) => Math.max(ZOOM_MIN, +(z - ZOOM_STEP).toFixed(2)))
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [index, go, onClose])

  // Track fullscreen state so the icon reflects it.
  useEffect(() => {
    const onFs = () => setIsFs(!!document.fullscreenElement)
    document.addEventListener('fullscreenchange', onFs)
    return () => document.removeEventListener('fullscreenchange', onFs)
  }, [])

  // Revoke object URLs on unmount; exit fullscreen if still in it.
  useEffect(() => () => {
    Object.values(loadedRef.current).forEach((url) => { if (url.startsWith('blob:')) URL.revokeObjectURL(url) })
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {})
  }, [])

  if (total === 0) return null
  const current = items[index]
  const src = loaded[index]
  const isFailed = failed[index]

  const zoomIn = () => setZoom((z) => Math.min(ZOOM_MAX, +(z + ZOOM_STEP).toFixed(2)))
  const zoomOut = () => setZoom((z) => Math.max(ZOOM_MIN, +(z - ZOOM_STEP).toFixed(2)))

  const toggleFullscreen = () => {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {})
    else rootRef.current?.requestFullscreen().catch(() => {})
  }

  const download = () => {
    if (!src) return
    const a = document.createElement('a')
    a.href = src
    a.download = current?.name || 'image'
    document.body.appendChild(a)
    a.click()
    a.remove()
  }

  const iconBtn = 'flex h-9 w-9 items-center justify-center rounded-full text-white/80 hover:bg-white/10 hover:text-white disabled:opacity-40 transition'

  const modal = (
    <div
      ref={rootRef}
      role="dialog" aria-modal="true" aria-label="Image viewer"
      className="fixed inset-0 z-[200] flex flex-col bg-black/85 backdrop-blur-sm"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
    >
      {/* Top bar */}
      <div className="flex items-center justify-between gap-3 px-4 py-3 text-white/90 shrink-0">
        <span className="text-sm font-medium truncate">
          {current?.name}{total > 1 ? `   ·   ${index + 1} / ${total}` : ''}
        </span>
        <div className="flex items-center gap-1 shrink-0">
          <button onClick={zoomOut} disabled={zoom <= ZOOM_MIN} title="Zoom out" className={iconBtn}>
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path strokeLinecap="round" d="M21 21l-4.3-4.3M8 11h6" /></svg>
          </button>
          <span className="w-12 text-center text-xs tabular-nums select-none">{Math.round(zoom * 100)}%</span>
          <button onClick={zoomIn} disabled={zoom >= ZOOM_MAX} title="Zoom in" className={iconBtn}>
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path strokeLinecap="round" d="M21 21l-4.3-4.3M11 8v6M8 11h6" /></svg>
          </button>
          <span className="mx-1 h-5 w-px bg-white/20" />
          <button onClick={toggleFullscreen} title={isFs ? 'Exit fullscreen' : 'Fullscreen'} className={iconBtn}>
            {isFs ? (
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4" /></svg>
            ) : (
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
            )}
          </button>
          <button onClick={download} disabled={!src} title="Download" className={iconBtn}>
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
          </button>
          <button onClick={onClose} aria-label="Close" title="Close (Esc)" className={`${iconBtn} text-2xl leading-none`}>&times;</button>
        </div>
      </div>

      {/* Image stage — arrows sit in the side gutters so they never cover the image */}
      <div className="relative flex-1 min-h-0">
        {total > 1 && (
          <button onClick={() => go(index - 1)} aria-label="Previous"
            className="absolute left-3 top-1/2 -translate-y-1/2 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
          </button>
        )}

        <div className="absolute inset-0 overflow-auto" onClick={(e) => { if (e.target === e.currentTarget) onClose() }}>
          <div className="min-h-full min-w-full flex items-center justify-center px-16 py-4">
            {isFailed ? (
              <p className="text-white/70 text-sm">Couldn't load this image.</p>
            ) : src ? (
              <img
                src={src}
                alt={current?.name}
                draggable={false}
                style={{ transform: `scale(${zoom})`, transformOrigin: 'center' }}
                className="max-h-[78vh] max-w-full object-contain rounded shadow-2xl transition-transform duration-150 select-none"
              />
            ) : (
              <LoadingSpinner size="lg" />
            )}
          </div>
        </div>

        {total > 1 && (
          <button onClick={() => go(index + 1)} aria-label="Next"
            className="absolute right-3 top-1/2 -translate-y-1/2 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" /></svg>
          </button>
        )}
      </div>

      {/* Thumbnail strip */}
      {total > 1 && (
        <div className="flex items-center justify-center gap-2 px-4 py-3 overflow-x-auto shrink-0">
          {items.map((it, i) => (
            <button key={i} onClick={() => go(i)} title={it.name}
              className={`h-12 w-12 shrink-0 overflow-hidden rounded border-2 flex items-center justify-center text-[10px] font-medium text-white/70 transition ${
                i === index ? 'border-brand-orange' : 'border-white/20 hover:border-white/50'
              }`}>
              {loaded[i] ? <img src={loaded[i]} alt="" className="h-full w-full object-cover" /> : i + 1}
            </button>
          ))}
        </div>
      )}
    </div>
  )

  return createPortal(modal, document.body)
}
