import { useRef, useState, useCallback, useEffect } from 'react'
import ReactCrop, { type Crop, type PixelCrop, centerCrop, makeAspectCrop } from 'react-image-crop'
import 'react-image-crop/dist/ReactCrop.css'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { uploadMyAvatar, deleteMyAvatar } from '../../api/me'

const MAX_BYTES = 5 * 1024 * 1024 // mirrors the backend cap (5 MB)
const ACCEPTED  = 'image/jpeg,image/png,image/webp'

// Centre a 1:1 crop covering the larger of the image's dimensions — a
// sensible default so the user sees a meaningful selection on file open
// even before they touch anything.
function centerSquareCrop(width: number, height: number): Crop {
  return centerCrop(
    makeAspectCrop({ unit: '%', width: 90 }, 1, width, height),
    width,
    height,
  )
}

// Render the visible crop region to a 256×256 JPEG blob. Server resizes
// independently as a safety net, but we send a pre-cropped square so the
// bytes-over-the-wire stays small.
function canvasFromCrop(image: HTMLImageElement, crop: PixelCrop): Promise<Blob> {
  return new Promise((resolve, reject) => {
    const scaleX = image.naturalWidth / image.width
    const scaleY = image.naturalHeight / image.height
    const canvas = document.createElement('canvas')
    canvas.width = 256
    canvas.height = 256
    const ctx = canvas.getContext('2d')
    if (!ctx) return reject(new Error('canvas 2d context unavailable'))
    ctx.imageSmoothingQuality = 'high'
    ctx.drawImage(
      image,
      crop.x * scaleX,
      crop.y * scaleY,
      crop.width * scaleX,
      crop.height * scaleY,
      0, 0, 256, 256,
    )
    canvas.toBlob(
      blob => blob ? resolve(blob) : reject(new Error('canvas toBlob returned null')),
      'image/jpeg',
      0.9,
    )
  })
}

export default function AvatarUploadModal({
  open, onClose, hasExistingAvatar,
}: { open: boolean; onClose: () => void; hasExistingAvatar: boolean }) {
  const qc = useQueryClient()
  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const imgRef = useRef<HTMLImageElement | null>(null)

  const [src, setSrc] = useState<string | null>(null)
  const [crop, setCrop] = useState<Crop | undefined>(undefined)
  const [completedCrop, setCompletedCrop] = useState<PixelCrop | undefined>(undefined)
  const [error, setError] = useState<string | null>(null)

  // Reset transient state every time the modal closes so the next open
  // starts on the file picker, not on a stale preview.
  useEffect(() => {
    if (!open) {
      setSrc(null)
      setCrop(undefined)
      setCompletedCrop(undefined)
      setError(null)
    }
  }, [open])

  const uploadMutation = useMutation({
    mutationFn: (blob: Blob) => uploadMyAvatar(blob),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me', 'profile'] })
      onClose()
    },
    onError: (e: any) => {
      setError(e?.response?.data?.message || 'Upload failed. Try again.')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: () => deleteMyAvatar(),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me', 'profile'] })
      onClose()
    },
    onError: (e: any) => {
      setError(e?.response?.data?.message || 'Could not remove avatar.')
    },
  })

  const onFile = useCallback((file: File) => {
    setError(null)
    if (!ACCEPTED.split(',').includes(file.type)) {
      setError('JPG, PNG, or WEBP only.')
      return
    }
    if (file.size > MAX_BYTES) {
      setError('File is too big — 5 MB max.')
      return
    }
    const reader = new FileReader()
    reader.onload = () => setSrc(typeof reader.result === 'string' ? reader.result : null)
    reader.readAsDataURL(file)
  }, [])

  if (!open) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Upload avatar"
      className="fixed inset-0 z-[200] flex items-start justify-center bg-black/50 p-4 pt-10"
      onClick={e => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col">
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 className="text-base font-semibold text-gray-800">Update your avatar</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="text-gray-400 hover:text-gray-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy rounded"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="p-5 overflow-y-auto">
          {!src ? (
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              className="w-full border-2 border-dashed border-gray-300 rounded-lg py-10 px-4 text-center hover:border-brand-navy hover:bg-gray-50 cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy"
            >
              <svg className="w-10 h-10 mx-auto text-gray-400" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12m4.5-4.5v12" />
              </svg>
              <div className="mt-2 text-sm font-medium text-gray-700">Choose an image</div>
              <div className="text-xs text-gray-400 mt-0.5">JPG, PNG or WEBP · 5 MB max</div>
            </button>
          ) : (
            <>
              <div className="text-xs text-gray-500 mb-2">
                Drag inside the box to reposition. The square area is what gets saved.
              </div>
              <div className="bg-gray-50 rounded border border-gray-200 p-2 flex justify-center">
                <ReactCrop
                  crop={crop}
                  onChange={(_, p) => setCrop(p)}
                  onComplete={c => setCompletedCrop(c)}
                  aspect={1}
                  circularCrop
                  keepSelection
                  minWidth={64}
                  minHeight={64}
                >
                  <img
                    ref={imgRef}
                    src={src}
                    alt="To crop"
                    style={{ maxHeight: '50vh', maxWidth: '100%' }}
                    onLoad={e => {
                      const { width, height } = e.currentTarget
                      const c = centerSquareCrop(width, height)
                      setCrop(c)
                    }}
                  />
                </ReactCrop>
              </div>
              <button
                type="button"
                onClick={() => { setSrc(null); setCrop(undefined); setCompletedCrop(undefined) }}
                className="mt-2 text-xs text-gray-500 hover:text-brand-navy underline cursor-pointer"
              >
                Choose a different image
              </button>
            </>
          )}
          {error && (
            <div className="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
              {error}
            </div>
          )}
          <input
            ref={fileInputRef}
            type="file"
            accept={ACCEPTED}
            className="hidden"
            onChange={e => { const f = e.target.files?.[0]; if (f) onFile(f); e.target.value = '' }}
          />
        </div>

        <div className="px-5 py-3 border-t border-gray-100 flex items-center justify-between gap-2 flex-wrap">
          <div>
            {hasExistingAvatar && (
              <button
                type="button"
                onClick={() => { if (confirm('Remove your current avatar?')) deleteMutation.mutate() }}
                disabled={deleteMutation.isPending}
                className="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-200 rounded-md hover:bg-red-50 disabled:opacity-50 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
              >
                {deleteMutation.isPending ? 'Removing…' : 'Remove current avatar'}
              </button>
            )}
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 text-sm font-medium text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy"
            >
              Cancel
            </button>
            <button
              type="button"
              disabled={!src || !completedCrop || !imgRef.current || uploadMutation.isPending}
              onClick={async () => {
                if (!imgRef.current || !completedCrop) return
                try {
                  const blob = await canvasFromCrop(imgRef.current, completedCrop)
                  uploadMutation.mutate(blob)
                } catch (e: any) {
                  setError(e?.message || 'Could not process the crop. Try a smaller image.')
                }
              }}
              className="px-4 py-1.5 text-sm font-medium bg-brand-navy text-white rounded-md hover:bg-brand-navy-light disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy focus-visible:ring-offset-1"
            >
              {uploadMutation.isPending ? 'Uploading…' : 'Save avatar'}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
