import { useEffect, useState, useRef } from 'react'

interface ProgressBarProps {
  /** Whether data is currently loading */
  isLoading: boolean
  /** Optional label shown next to the percentage */
  label?: string
  /** CSS class for the outer container */
  className?: string
}

/**
 * Animated progress bar that simulates realistic loading progress.
 * Fast start (0→60%), slows in the middle (60→90%), keeps creeping to 99%, snaps to 100% on complete.
 */
export default function ProgressBar({ isLoading, label = 'Loading', className = '' }: ProgressBarProps) {
  const [progress, setProgress] = useState(0)
  const [visible, setVisible] = useState(false)
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  useEffect(() => {
    if (isLoading) {
      setProgress(0)
      setVisible(true)

      let current = 0
      intervalRef.current = setInterval(() => {
        if (current < 15) current += 3
        else if (current < 40) current += 1.5
        else if (current < 60) current += 0.8
        else if (current < 75) current += 0.4
        else if (current < 85) current += 0.2
        else if (current < 92) current += 0.1
        else if (current < 97) current += 0.05
        else current += 0.02
        setProgress(Math.min(current, 99.5))
      }, 200)
    } else {
      if (intervalRef.current) clearInterval(intervalRef.current)
      setProgress(100)
      const timer = setTimeout(() => setVisible(false), 500)
      return () => clearTimeout(timer)
    }

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current)
    }
  }, [isLoading])

  if (!visible && !isLoading) return null

  const pct = Math.round(progress)

  return (
    <div className={`space-y-2 ${className}`}>
      <div className="flex items-center justify-between text-sm">
        <span className="text-gray-500 font-medium">{label}…</span>
        <span className="text-blue-600 font-semibold tabular-nums">{pct}%</span>
      </div>
      <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-300 ease-out ${
            progress >= 100 ? 'bg-green-500' : 'bg-blue-500'
          }`}
          style={{ width: `${progress}%` }}
        />
      </div>
    </div>
  )
}
