import { MENU_ITEMS } from './Sidebar'

/**
 * Derives the top-bar breadcrumb + page title from the sidebar MENU_ITEMS, so
 * the header stays consistent with the navigation (single source of truth) and
 * mirrors the Reporting portal's header pattern. Unknown / detail sub-pages
 * fall back to a humanised path.
 */
export interface Crumb { label: string; to?: string }
export interface PageMeta { title: string; crumbs: Crumb[] }

interface Entry { path: string; title: string; crumbs: Crumb[] }

function buildEntries(): Entry[] {
  const entries: Entry[] = []
  for (const item of MENU_ITEMS) {
    if (item.children && item.children.length) {
      // External (href) children live in another app — they have no route
      // here, so they contribute nothing to breadcrumbs.
      const routed = item.children.filter((c): c is typeof c & { path: string } => !!c.path)
      const parentTo = routed[0]?.path.split('?')[0]
      for (const child of routed) {
        entries.push({
          path: child.path.split('?')[0],
          title: child.label,
          crumbs: [{ label: item.label, to: parentTo }, { label: child.label }],
        })
      }
    } else if (item.path) {
      const base = item.path.split('?')[0]
      entries.push({
        path: base,
        title: item.label,
        crumbs: item.section ? [{ label: item.section }, { label: item.label }] : [{ label: item.label }],
      })
    }
  }
  return entries
}

const ENTRIES = buildEntries()

function humanize(seg: string): string {
  return seg.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

export function getPageMeta(pathname: string): PageMeta {
  const path = (pathname.split('?')[0].replace(/\/+$/, '')) || '/'

  // Exact match (covers most nav destinations).
  const exact = ENTRIES.find((e) => e.path === path)
  if (exact) return { title: exact.title, crumbs: exact.crumbs }

  // Longest-prefix match → a detail / sub-page under a known section.
  let best: Entry | undefined
  for (const e of ENTRIES) {
    if (e.path !== '/' && (path === e.path || path.startsWith(e.path + '/'))) {
      if (!best || e.path.length > best.path.length) best = e
    }
  }
  if (best) {
    const rest = path.slice(best.path.length).split('/').filter(Boolean)
    if (rest.length) {
      const last = rest[rest.length - 1]
      const leaf = /^\d+$/.test(last) ? 'Details' : humanize(last)
      return { title: leaf, crumbs: [...best.crumbs, { label: leaf }] }
    }
    return { title: best.title, crumbs: best.crumbs }
  }

  // Fallback: humanise the path segments.
  const segs = path.split('/').filter(Boolean)
  if (segs.length === 0) return { title: 'Dashboard', crumbs: [{ label: 'Dashboard' }] }
  const crumbs = segs.map((s) => ({ label: humanize(s) }))
  return { title: crumbs[crumbs.length - 1].label, crumbs }
}
