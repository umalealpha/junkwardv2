// ─────────────────────────────────────────────────────────────────────────
// Help center — guide registry.
//
// Loads every markdown guide under ./guides/*.md at build time (Vite glob) and
// joins it to the module list in helpModules.ts. A guide appears in the Help
// center as soon as its <key>.md file exists — no per-guide wiring needed.
//
// Guide files may start with a tiny frontmatter block:
//   ---
//   lastReviewed: 2026-06-03
//   ---
//   ## Overview ...
// `lastReviewed` is surfaced in the UI and used by the CI staleness checker.
// ─────────────────────────────────────────────────────────────────────────

import { HELP_MODULES, HELP_CATEGORY_ORDER, type HelpModule, type HelpCategory } from './helpModules'

// Eagerly import all guide markdown as raw strings.
const rawGuides = import.meta.glob('./guides/*.md', { eager: true, query: '?raw', import: 'default' }) as Record<
  string,
  string
>

interface ParsedGuide {
  body: string
  lastReviewed?: string
}

function parseGuide(raw: string): ParsedGuide {
  const fmMatch = raw.match(/^---\s*\n([\s\S]*?)\n---\s*\n?/)
  if (!fmMatch) return { body: raw.trim() }

  const front = fmMatch[1]
  const body = raw.slice(fmMatch[0].length).trim()
  const lastReviewed = front.match(/^\s*lastReviewed\s*:\s*(.+)\s*$/m)?.[1]?.trim()
  return { body, lastReviewed }
}

// Map of moduleKey -> parsed guide, keyed off the filename (guides/<key>.md).
const guidesByKey: Record<string, ParsedGuide> = {}
for (const [path, raw] of Object.entries(rawGuides)) {
  const key = path.split('/').pop()!.replace(/\.md$/, '')
  guidesByKey[key] = parseGuide(raw)
}

export interface HelpEntry extends HelpModule {
  /** True when a guide markdown file exists for this module. */
  hasGuide: boolean
  /** Guide body markdown (empty string when no guide yet). */
  body: string
  /** ISO date the guide was last reviewed, if declared in frontmatter. */
  lastReviewed?: string
}

export const HELP_ENTRIES: HelpEntry[] = HELP_MODULES.map((m) => {
  const guide = guidesByKey[m.key]
  return {
    ...m,
    hasGuide: !!guide,
    body: guide?.body ?? '',
    lastReviewed: guide?.lastReviewed,
  }
})

const ENTRY_BY_KEY: Record<string, HelpEntry> = Object.fromEntries(HELP_ENTRIES.map((e) => [e.key, e]))

export function getHelpEntry(key: string | undefined): HelpEntry | undefined {
  return key ? ENTRY_BY_KEY[key] : undefined
}

/** Entries grouped by category, in canonical category + array order. */
export function getHelpEntriesByCategory(): { category: HelpCategory; entries: HelpEntry[] }[] {
  return HELP_CATEGORY_ORDER.map((category) => ({
    category,
    entries: HELP_ENTRIES.filter((e) => e.category === category),
  })).filter((group) => group.entries.length > 0)
}

/** Simple case-insensitive search across title, summary and category. */
export function searchHelpEntries(query: string): HelpEntry[] {
  const q = query.trim().toLowerCase()
  if (!q) return HELP_ENTRIES
  return HELP_ENTRIES.filter((e) =>
    [e.title, e.summary, e.category].some((field) => field.toLowerCase().includes(q))
  )
}
