import { HELP_MODULES, type HelpModule } from './helpModules'

// Lightweight route → guide resolver for the contextual "?" button in the
// header. The header is in the always-loaded bundle, so this MUST NOT import
// the guide bodies (registry.ts does, eagerly). We use a NON-eager glob and
// only read the filenames — Vite keeps the markdown in lazy per-guide chunks,
// so nothing here weighs down the initial page load.
const guideModules = import.meta.glob('./guides/*.md', { query: '?raw', import: 'default' })
const GUIDE_KEYS = new Set(Object.keys(guideModules).map((p) => p.split('/').pop()!.replace(/\.md$/, '')))

export const hasGuide = (key: string): boolean => GUIDE_KEYS.has(key)

const firstSegment = (route: string) => route.replace(/^\//, '').split('/')[0]

/**
 * The most relevant PUBLISHED guide for a live route. Prefers the longest
 * matching module route (so /system/document-jobs beats /system/cron-portal),
 * then falls back to a first-path-segment match (incl. coversRoutes). Returns
 * undefined when no guide fits (dashboard, the /help pages themselves, or a
 * module whose guide isn't written yet) so the button can hide.
 */
export function getHelpModuleForPath(pathname: string | undefined): HelpModule | undefined {
  if (!pathname) return undefined

  let best: HelpModule | undefined
  for (const m of HELP_MODULES) {
    if (pathname === m.route || pathname.startsWith(m.route + '/')) {
      if (!best || m.route.length > best.route.length) best = m
    }
  }

  if (!best) {
    const seg = firstSegment(pathname)
    if (seg) {
      best = HELP_MODULES.find(
        (m) => firstSegment(m.route) === seg || (m.coversRoutes ?? []).some((r) => firstSegment(r) === seg)
      )
    }
  }

  return best && hasGuide(best.key) ? best : undefined
}
