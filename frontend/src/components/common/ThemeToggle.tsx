import { useTheme, type ThemeMode } from '../../hooks/useTheme'

/**
 * Theme toggle — cycles light → dark → system. Sun icon in light, moon in
 * dark, and a monitor glyph in system mode so the user can tell which of the
 * three states is active (not just the effective appearance). Icons match the
 * app's stroke-based inline-SVG style; no emoji.
 */

const NEXT_LABEL: Record<ThemeMode, string> = {
  light: 'Switch to dark theme',
  dark: 'Switch to system theme',
  system: 'Switch to light theme',
}

const STATE_LABEL: Record<ThemeMode, string> = {
  light: 'Theme: light',
  dark: 'Theme: dark',
  system: 'Theme: system',
}

export default function ThemeToggle() {
  const { mode, cycle } = useTheme()

  return (
    <button
      type="button"
      onClick={cycle}
      title={STATE_LABEL[mode]}
      aria-label={NEXT_LABEL[mode]}
      className="flex items-center justify-center w-8 h-8 rounded-full text-ink-muted hover:text-ink hover:bg-surface-2 transition cursor-pointer"
    >
      {mode === 'light' ? (
        // Sun
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="4" strokeWidth={2} />
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.07-7.07l-1.41 1.41M6.34 17.66l-1.41 1.41m12.14 0l-1.41-1.41M6.34 6.34L4.93 4.93" />
        </svg>
      ) : mode === 'dark' ? (
        // Moon
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
        </svg>
      ) : (
        // Monitor (system)
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <rect x="3" y="4" width="18" height="12" rx="2" strokeWidth={2} />
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 20h8m-4-4v4" />
        </svg>
      )}
    </button>
  )
}
