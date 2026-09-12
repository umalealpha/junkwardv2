#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────
// Design-token guardrail — blocks NEWLY-ADDED hardcoded neutral colors.
//
// WHY: the frontend is mid-migration from raw Tailwind neutral classes
// (bg-white / text-gray-500 / border-gray-300 …) and raw hex to the themed
// design tokens defined in src/index.css + tailwind.config.js
// (bg-surface / text-ink / border-line …). The tokens flip correctly in dark
// mode; the hardcoded classes do not (they only work today because index.css
// ships a hand-written `.dark .bg-white { … }` shim for a FIXED list).
//
// The migration only covered high-traffic pages — ~240 files still contain
// legacy classes. So this check must NEVER fail on pre-existing code. It looks
// ONLY at lines this PR/branch ADDED (the `+` side of the diff vs the base
// branch). Touching a legacy file is fine; INTRODUCING a new violation is not.
//
// Usage:
//   node scripts/check-hardcoded-colors.mjs [--base <ref>]
//     --base   base ref to diff against (default: origin/main)
//
// Exits 1 if any newly-added violation is found, 0 otherwise.
//
// Opt-out (rare, justified cases): add `// token-exempt` on the offending
// line or the line immediately above it.
// ─────────────────────────────────────────────────────────────────────────

import { execSync, execFileSync } from 'node:child_process'
import { readFileSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const FRONTEND_DIR = resolve(__dirname, '..')
const REPO_ROOT = resolve(FRONTEND_DIR, '..')
const IN_CI = !!process.env.GITHUB_ACTIONS

// ── args ──────────────────────────────────────────────────────────────────
function argValue(flag, fallback) {
  const i = process.argv.indexOf(flag)
  return i !== -1 && process.argv[i + 1] ? process.argv[i + 1] : fallback
}
const BASE = argValue('--base', 'origin/main')

// Token source files never fail this check (they legitimately define the
// hardcoded values / the .dark shims that everything else migrates toward).
const EXEMPT_FILES = new Set([
  'frontend/src/index.css',
  'frontend/tailwind.config.js',
])

const OPT_OUT = 'token-exempt'

// ── forbidden patterns + the token to use instead ─────────────────────────
// Each: { name, re, fix }. `re` is matched against a single added line.
const RULES = [
  { name: 'bg-white',        re: /\bbg-white\b/,                fix: 'bg-surface' },
  { name: 'bg-gray-*',       re: /\bbg-gray-\d{2,3}\b/,         fix: 'bg-app / bg-surface / bg-surface-2' },
  { name: 'text-gray-*',     re: /\btext-gray-\d{2,3}\b/,       fix: 'text-ink / text-ink-muted / text-ink-faint' },
  { name: 'border-gray-*',   re: /\bborder-gray-\d{2,3}\b/,     fix: 'border-line' },
  { name: 'divide-gray-*',   re: /\bdivide-gray-\d{2,3}\b/,     fix: 'divide-line' },
  { name: 'placeholder-gray-*', re: /\bplaceholder-gray-\d{2,3}\b/, fix: 'placeholder:text-ink-faint' },
  { name: 'ring-gray-*',     re: /\bring-gray-\d{2,3}\b/,       fix: 'ring-line' },
]

// Raw hex is only a violation in a className / inline-style context (an SVG
// path `d="M#…"` or a plain comment shouldn't trip it). We flag a hex when the
// line looks like styling: a className/style attribute, a JS style object, a
// CSS-ish color property, or a Tailwind arbitrary value `-[#…]`.
const HEX_RE = /(?<![\w#])#[0-9a-fA-F]{3,8}\b/
const HEX_CONTEXT_RE = /className|style\s*[=:]|-\[#[0-9a-fA-F]|(?:color|background|border|fill|stroke|outline|shadow)\s*:/i
const HEX_FIX = 'a brand/theme token (e.g. bg-primary, text-ink, brand.navy) — see tailwind.config.js'

// ── git helpers ─────────────────────────────────────────────────────────────
function git(args, opts = {}) {
  return execFileSync('git', args, { cwd: REPO_ROOT, encoding: 'utf8', ...opts })
}

function refExists(ref) {
  try {
    git(['rev-parse', '--verify', '--quiet', `${ref}^{commit}`], { stdio: ['ignore', 'pipe', 'ignore'] })
    return true
  } catch {
    return false
  }
}

// Make the base ref resolvable even on a shallow CI checkout.
function ensureBase(base) {
  if (refExists(base)) return base
  // base looks like "origin/<branch>" — try to fetch that branch.
  const m = base.match(/^origin\/(.+)$/)
  if (m) {
    try {
      console.log(`ℹ base ref "${base}" not present — fetching origin/${m[1]}…`)
      git(['fetch', '--no-tags', '--depth=1000', 'origin', m[1]])
    } catch { /* fall through */ }
    if (refExists(base)) return base
    if (refExists('FETCH_HEAD')) return 'FETCH_HEAD'
  }
  return base // let the diff surface a clear error if still missing
}

// ── build the set of added lines, per file ──────────────────────────────────
// Parse `git diff --unified=0 <base>...HEAD` into { file -> [{ line, text }] }.
function collectAddedLines(base) {
  let raw
  try {
    raw = git(['diff', '--unified=0', '--no-color', `${base}...HEAD`, '--', 'frontend/src'])
  } catch (e) {
    console.error(`✖ could not compute diff against "${base}":\n${e.message}`)
    process.exit(2)
  }

  const added = new Map() // file -> [{ line, text }]
  let file = null
  let newLineNo = 0

  for (const line of raw.split('\n')) {
    if (line.startsWith('+++ ')) {
      // "+++ b/frontend/src/foo.tsx"  (or "+++ /dev/null" for deletions)
      const p = line.slice(4).replace(/^b\//, '')
      file = p === '/dev/null' ? null : p
      continue
    }
    if (line.startsWith('--- ')) continue
    if (line.startsWith('@@')) {
      // @@ -a,b +c,d @@  — c is the first new-file line number of this hunk
      const m = line.match(/@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/)
      newLineNo = m ? parseInt(m[1], 10) : 0
      continue
    }
    if (!file) continue
    // only real content lines belong to a hunk
    if (line.startsWith('+')) {
      added.set(file, added.get(file) || [])
      added.get(file).push({ line: newLineNo, text: line.slice(1) })
      newLineNo++
    }
    // With --unified=0 there are no context lines; deletions ('-') don't
    // advance the new-file counter. Anything else we ignore.
  }
  return added
}

// ── scan ─────────────────────────────────────────────────────────────────
function isExemptFile(file) {
  return EXEMPT_FILES.has(file) || !/\.(ts|tsx)$/.test(file)
}

// token-exempt on the offending line, or the physical line above it in HEAD.
function lineOptedOut(fileLinesCache, file, lineNo, addedText) {
  if (addedText.includes(OPT_OUT)) return true
  let lines = fileLinesCache.get(file)
  if (lines === undefined) {
    const abs = resolve(REPO_ROOT, file)
    lines = existsSync(abs) ? readFileSync(abs, 'utf8').split('\n') : null
    fileLinesCache.set(file, lines)
  }
  if (!lines) return false
  const prev = lines[lineNo - 2] // 1-based lineNo -> previous line index
  return typeof prev === 'string' && prev.includes(OPT_OUT)
}

function scan(added) {
  const findings = []
  const fileLinesCache = new Map()

  for (const [file, lines] of added) {
    if (isExemptFile(file)) continue
    for (const { line, text } of lines) {
      const hits = []
      for (const rule of RULES) {
        if (rule.re.test(text)) hits.push({ token: rule.name, fix: rule.fix })
      }
      if (HEX_RE.test(text) && HEX_CONTEXT_RE.test(text)) {
        hits.push({ token: (text.match(HEX_RE) || ['raw hex'])[0], fix: HEX_FIX })
      }
      if (!hits.length) continue
      if (lineOptedOut(fileLinesCache, file, line, text)) continue
      findings.push({ file, line, text: text.trim().slice(0, 200), hits })
    }
  }
  return findings
}

// ── report ─────────────────────────────────────────────────────────────────
function report(findings, base) {
  if (!findings.length) {
    console.log(`✅ Token guardrail: no newly-added hardcoded neutral colors vs ${base}.`)
    return 0
  }
  console.log(`\n❌ Token guardrail: ${findings.length} newly-added hardcoded color(s) vs ${base}.\n`)
  console.log('The design-token migration must not regress. Replace the raw class/hex')
  console.log('with the themed token (these flip correctly in dark mode):\n')

  for (const f of findings) {
    if (IN_CI) {
      const tokens = f.hits.map((h) => h.token).join(', ')
      console.log(`::error file=${f.file},line=${f.line}::Hardcoded color (${tokens}). Use a design token instead.`)
    }
    console.log(`  ${f.file}:${f.line}`)
    console.log(`    ${f.text}`)
    for (const h of f.hits) {
      console.log(`      • "${h.token}" → use ${h.fix}`)
    }
    console.log('')
  }

  console.log('Token reference (tailwind.config.js / src/index.css):')
  console.log('  bg-white        → bg-surface')
  console.log('  bg-gray-*       → bg-app · bg-surface · bg-surface-2')
  console.log('  text-gray-*     → text-ink · text-ink-muted · text-ink-faint')
  console.log('  border-gray-*   → border-line     divide-gray-* → divide-line')
  console.log('  ring-gray-*     → ring-line        placeholder-gray-* → placeholder:text-ink-faint')
  console.log('  raw #hex        → brand.navy / brand.orange / bg-primary / text-ink …')
  console.log('')
  console.log(`If a line is a genuine, justified exception, add "// ${OPT_OUT}" on it or the line above.`)
  return 1
}

// ── main ─────────────────────────────────────────────────────────────────
const base = ensureBase(BASE)
const added = collectAddedLines(base)
const findings = scan(added)
process.exit(report(findings, base))
