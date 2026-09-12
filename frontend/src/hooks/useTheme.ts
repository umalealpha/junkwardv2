import { useCallback, useEffect, useState } from 'react'

/**
 * Theme control — light / dark / system.
 *
 * The concrete `dark` class on <html> is applied as early as possible by a
 * tiny inline script in index.html (before React mounts) to avoid a flash of
 * the wrong theme. This hook keeps React in sync with, and drives changes to,
 * that same state: it reads/writes localStorage under `graphite-theme`,
 * toggles the `dark` class, and — while in `system` mode — follows the OS
 * preference live via matchMedia.
 */

export type ThemeMode = 'light' | 'dark' | 'system'

export const THEME_STORAGE_KEY = 'graphite-theme'

// TEMP (2026-07): systemPrefersDark() was removed because resolveDark no longer
// consults the OS preference (see below). To restore prefers-color-scheme
// handling once dark-mode QA is signed off, reinstate:
//   function systemPrefersDark(): boolean {
//     return typeof window !== 'undefined'
//       && window.matchMedia('(prefers-color-scheme: dark)').matches
//   }
// and have resolveDark return `mode === 'dark' || (mode === 'system' && systemPrefersDark())`.

/** Resolve a mode to the concrete boolean applied to the <html> class. */
function resolveDark(mode: ThemeMode): boolean {
  // TEMP (2026-07): dark mode still has contrast issues in prod — force LIGHT as
  // the default and treat 'system' as light so no one lands on dark unless they
  // explicitly toggle it. Restore prefers-color-scheme handling once dark-mode
  // QA is signed off.
  return mode === 'dark'
}

function readStoredMode(): ThemeMode {
  if (typeof window === 'undefined') return 'system'
  const stored = window.localStorage.getItem(THEME_STORAGE_KEY)
  // TEMP (2026-07): dark mode still has contrast issues in prod — default to
  // LIGHT (was 'system') when there is no stored preference so no one lands on
  // dark unless they explicitly toggle it. Restore 'system' default once
  // dark-mode QA is signed off.
  return stored === 'light' || stored === 'dark' || stored === 'system'
    ? stored
    : 'light'
}

function applyDarkClass(isDark: boolean) {
  document.documentElement.classList.toggle('dark', isDark)
}

export function useTheme() {
  const [mode, setModeState] = useState<ThemeMode>(readStoredMode)
  const [isDark, setIsDark] = useState<boolean>(() => resolveDark(readStoredMode()))

  // Apply the concrete class + persist whenever the chosen mode changes.
  useEffect(() => {
    const dark = resolveDark(mode)
    applyDarkClass(dark)
    setIsDark(dark)
    window.localStorage.setItem(THEME_STORAGE_KEY, mode)
  }, [mode])

  // While in `system` mode, follow live OS changes.
  useEffect(() => {
    if (mode !== 'system') return
    const mq = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = () => {
      const dark = mq.matches
      applyDarkClass(dark)
      setIsDark(dark)
    }
    mq.addEventListener('change', onChange)
    return () => mq.removeEventListener('change', onChange)
  }, [mode])

  const setMode = useCallback((next: ThemeMode) => setModeState(next), [])

  // Convenience cycle: light → dark → system → light.
  const cycle = useCallback(() => {
    setModeState((m) => (m === 'light' ? 'dark' : m === 'dark' ? 'system' : 'light'))
  }, [])

  return { mode, isDark, setMode, cycle }
}
