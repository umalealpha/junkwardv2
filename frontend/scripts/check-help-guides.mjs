#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────
// Help-guide staleness checker (the "auto-update" guardrail).
//
// This does NOT rewrite guides. It WARNS — surfacing drift so a human keeps
// the guides honest as the system changes:
//
//   1. STALE GUIDE     — a module's source (its `codePaths`) changed in git
//      more recently than its guide's `lastReviewed` date.
//   2. UNCOVERED AREA  — a top-level area reachable from the sidebar menu has
//      no module entry in src/help/helpModules.ts at all.
//   3. UNWRITTEN GUIDE — a registered module has no <key>.md yet (informational;
//      these render a "Coming soon" card in-app).
//
// Output is GitHub Actions annotations (::warning::) in CI plus a readable
// summary. Exit code 0 by default (non-blocking, per the hybrid policy); pass
// --strict to exit 1 when stale or uncovered issues are found.
// ─────────────────────────────────────────────────────────────────────────

import { execSync } from 'node:child_process'
import { readFileSync, readdirSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join, resolve } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const FRONTEND_DIR = resolve(__dirname, '..')
const REPO_ROOT = resolve(FRONTEND_DIR, '..')
const STRICT = process.argv.includes('--strict')
const IN_CI = !!process.env.GITHUB_ACTIONS

const HELP_MODULES_PATH = join(FRONTEND_DIR, 'src/help/helpModules.ts')
const GUIDES_DIR = join(FRONTEND_DIR, 'src/help/guides')
const SIDEBAR_PATH = join(FRONTEND_DIR, 'src/components/Layout/Sidebar.tsx')

function warn(msg, file) {
  if (IN_CI) console.log(`::warning${file ? ` file=${file}` : ''}::${msg}`)
  console.log(`  ⚠ ${msg}`)
}

const topSegment = (route) => route.replace(/^\//, '').split('/')[0]

// ── Parse helpModules.ts: each module's key, route and codePaths ──────────
function parseHelpModules() {
  const src = readFileSync(HELP_MODULES_PATH, 'utf8')
  const modules = []
  const blockRe = /key:\s*'([^']+)'[\s\S]*?route:\s*'([^']+)'[\s\S]*?codePaths:\s*\[([\s\S]*?)\]([\s\S]*?)\n  \},/g
  let m
  while ((m = blockRe.exec(src))) {
    const tail = m[4] || ''
    const coversMatch = tail.match(/coversRoutes:\s*\[([\s\S]*?)\]/)
    const coversRoutes = coversMatch ? [...coversMatch[1].matchAll(/'([^']+)'/g)].map((p) => p[1]) : []
    modules.push({
      key: m[1],
      route: m[2],
      codePaths: [...m[3].matchAll(/'([^']+)'/g)].map((p) => p[1]),
      coversRoutes,
    })
  }
  return modules
}

// ── Top-level areas reachable from the sidebar menu ───────────────────────
function parseMenuAreas() {
  const src = readFileSync(SIDEBAR_PATH, 'utf8')
  const areas = new Set()
  for (const m of src.matchAll(/path:\s*'([^']+)'/g)) {
    const seg = topSegment(m[1].split('?')[0])
    if (seg) areas.add(seg)
  }
  return areas
}

// ── git: newest commit time (ms) touching any of the given globs ──────────
function lastCodeChange(codePaths) {
  let newest = 0
  for (const glob of codePaths) {
    const pathspec = glob.replace(/\/\*\*.*$/, '') // dir/** → dir
    try {
      const out = execSync(`git log -1 --format=%ct -- "${pathspec}"`, {
        cwd: REPO_ROOT,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'ignore'],
      }).trim()
      if (out) newest = Math.max(newest, parseInt(out, 10) * 1000)
    } catch {
      /* path may not exist yet */
    }
  }
  return newest
}

function guideLastReviewed(key) {
  const file = join(GUIDES_DIR, `${key}.md`)
  if (!existsSync(file)) return null
  const fm = readFileSync(file, 'utf8').match(/^---\s*\n([\s\S]*?)\n---/)
  const date = fm?.[1].match(/lastReviewed\s*:\s*(.+)/)?.[1]?.trim()
  return date ? { date, ts: Date.parse(date) } : { date: null, ts: 0 }
}

// ── Run ───────────────────────────────────────────────────────────────────
const modules = parseHelpModules()
const guideKeys = existsSync(GUIDES_DIR)
  ? readdirSync(GUIDES_DIR).filter((f) => f.endsWith('.md')).map((f) => f.replace(/\.md$/, ''))
  : []

let stale = 0
let uncovered = 0

console.log('\nHelp-guide check\n────────────────')

// 1. Stale guides
for (const mod of modules) {
  if (!guideKeys.includes(mod.key)) continue
  const reviewed = guideLastReviewed(mod.key)
  const codeTs = lastCodeChange(mod.codePaths)
  // Compare by calendar DAY: lastReviewed is date-only (midnight UTC) while a
  // commit timestamp falls later in the day, so a same-day review must count
  // as fresh. Only flag when code changed on a strictly later day.
  const codeDayTs = codeTs ? Date.parse(new Date(codeTs).toISOString().slice(0, 10)) : 0
  if (reviewed?.ts && codeDayTs && codeDayTs > reviewed.ts) {
    stale++
    const codeDate = new Date(codeTs).toISOString().slice(0, 10)
    warn(
      `Guide "${mod.key}" was last reviewed ${reviewed.date} but its code (${mod.codePaths.join(', ')}) changed on ${codeDate}. Re-review and bump lastReviewed.`,
      `frontend/src/help/guides/${mod.key}.md`
    )
  }
}

// 2. Menu areas not covered by any Help module entry
const moduleAreas = new Set()
for (const m of modules) {
  moduleAreas.add(topSegment(m.route))
  for (const r of m.coversRoutes || []) moduleAreas.add(topSegment(r))
}
// Areas we deliberately don't document as user modules (auth, the help area itself).
const IGNORED_AREAS = new Set(['help', 'login', 'sso'])
for (const area of parseMenuAreas()) {
  if (!moduleAreas.has(area) && !IGNORED_AREAS.has(area)) {
    uncovered++
    warn(`Sidebar area "/${area}" has no entry in helpModules.ts — add a module so it can get a guide.`, 'frontend/src/help/helpModules.ts')
  }
}

// 3. Unwritten guides (informational)
const unwritten = modules.filter((m) => !guideKeys.includes(m.key)).map((m) => m.key)
if (unwritten.length) {
  console.log(`\nℹ ${unwritten.length} module(s) registered without a guide yet (render "Coming soon"):`)
  console.log('  ' + unwritten.join(', '))
}

console.log('\nSummary')
console.log(`  guides published : ${guideKeys.length}/${modules.length}`)
console.log(`  stale guides     : ${stale}`)
console.log(`  uncovered areas  : ${uncovered}`)

if ((stale > 0 || uncovered > 0) && STRICT) {
  console.error('\nStrict mode: issues found.')
  process.exit(1)
}
console.log('\nDone (non-blocking).')
