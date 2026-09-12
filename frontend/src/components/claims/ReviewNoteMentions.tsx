import { useMemo, useRef, useState } from 'react'
import type { ClaimReviewNoteMention } from '../../api/claims'

/**
 * Claim review-note @mentions UI (claims_mentions feature).
 *
 * Three additive, self-contained pieces used by the Claim Review tab:
 *   - MentionComposer — a textarea with @mention autocomplete over a provided
 *     candidate list (reused from the note's "Notify" internal-user list; no
 *     extra endpoint call).
 *   - MentionText — renders a posted note body, highlighting the `@handles`
 *     that the backend parsed (resolved → badge, unresolved → plain highlight).
 *   - countMyMentions — how many notes mention the current user (current-claim
 *     scope), for the in-tab mentions indicator.
 *
 * Everything degrades gracefully: MentionText only highlights handles present
 * in the note's `mentions[]`, which the API returns empty when the feature is
 * off — so notes render exactly as before with no mention markup.
 */

export interface MentionCandidate {
  id: number
  name: string
  email?: string | null
}

/**
 * Insertable handle for a candidate — mirrors what ClaimMentionParser::resolve
 * matches on server-side: the email local-part when present (e.g. `jdoe`),
 * otherwise the name with whitespace stripped (matches the firstName+lastName
 * identity key, e.g. "John Doe" → `johndoe`). Lower-cased, no spaces, so it
 * survives the `@handle` token grammar.
 */
export function candidateHandle(c: MentionCandidate): string {
  const email = (c.email ?? '').trim()
  if (email.includes('@')) return email.slice(0, email.indexOf('@')).toLowerCase()
  return c.name.trim().toLowerCase().replace(/\s+/g, '')
}

// Handle grammar matching the backend parser: 1..64 of [A-Za-z0-9._-],
// starting alphanumeric. Used to detect the token being typed before the caret.
const ACTIVE_MENTION = /(^|[^\w@])@([A-Za-z0-9._-]{0,63})$/

// ── Composer ────────────────────────────────────────────────────────────────

interface MentionComposerProps {
  value: string
  onChange: (value: string) => void
  candidates: MentionCandidate[]
  placeholder?: string
  rows?: number
  className?: string
  id?: string
}

/**
 * Controlled <textarea> that offers an @mention dropdown as the user types
 * `@…`. Keyboard-navigable (↑/↓ move, Enter/Tab pick, Esc dismiss) and
 * mouse-selectable. Selecting a candidate replaces the in-progress `@token`
 * with `@handle ` and restores the caret after it.
 */
export function MentionComposer({
  value, onChange, candidates, placeholder, rows = 7, className, id,
}: MentionComposerProps) {
  const ref = useRef<HTMLTextAreaElement>(null)
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)
  // Caret index of the '@' that started the active token, so we know what to
  // replace on selection.
  const [anchor, setAnchor] = useState<number | null>(null)

  const suggestions = useMemo(() => {
    if (!open) return []
    const q = query.toLowerCase()
    return candidates
      .filter(c => {
        if (!q) return true
        return c.name.toLowerCase().includes(q) || candidateHandle(c).includes(q)
      })
      .slice(0, 8)
  }, [open, query, candidates])

  // Recompute the active token from the text up to the caret.
  const syncFromCaret = (el: HTMLTextAreaElement) => {
    const caret = el.selectionStart ?? el.value.length
    const upto = el.value.slice(0, caret)
    const m = upto.match(ACTIVE_MENTION)
    if (m) {
      setOpen(true)
      setQuery(m[2])
      setActive(0)
      setAnchor(caret - m[2].length - 1) // position of '@'
    } else {
      setOpen(false)
      setAnchor(null)
    }
  }

  const pick = (c: MentionCandidate) => {
    const el = ref.current
    if (!el || anchor === null) return
    const caret = el.selectionStart ?? el.value.length
    const handle = candidateHandle(c)
    const before = value.slice(0, anchor)
    const after = value.slice(caret)
    const insert = `@${handle} `
    const next = before + insert + after
    onChange(next)
    setOpen(false)
    setAnchor(null)
    // Restore caret just past the inserted handle after React re-renders.
    const nextCaret = before.length + insert.length
    requestAnimationFrame(() => {
      const t = ref.current
      if (t) { t.focus(); t.setSelectionRange(nextCaret, nextCaret) }
    })
  }

  const onKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (!open || suggestions.length === 0) return
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive(a => (a + 1) % suggestions.length) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(a => (a - 1 + suggestions.length) % suggestions.length) }
    else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pick(suggestions[active]) }
    else if (e.key === 'Escape') { e.preventDefault(); setOpen(false); setAnchor(null) }
  }

  return (
    <div className="relative">
      <textarea
        ref={ref}
        id={id}
        value={value}
        rows={rows}
        placeholder={placeholder}
        className={className}
        onChange={e => { onChange(e.target.value); syncFromCaret(e.target) }}
        onClick={e => syncFromCaret(e.currentTarget)}
        onKeyUp={e => { if (!['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes(e.key)) syncFromCaret(e.currentTarget) }}
        onKeyDown={onKeyDown}
        onBlur={() => { /* let click on a suggestion land first */ setTimeout(() => setOpen(false), 120) }}
        aria-autocomplete="list"
        aria-expanded={open}
      />
      {open && suggestions.length > 0 && (
        <ul
          role="listbox"
          className="absolute z-popover mt-1 max-h-56 w-72 overflow-y-auto rounded-md border border-line bg-surface shadow-elev-lg"
        >
          {suggestions.map((c, i) => (
            <li key={c.id} role="option" aria-selected={i === active}>
              <button
                type="button"
                // onMouseDown (not onClick) so it fires before the textarea blur.
                onMouseDown={e => { e.preventDefault(); pick(c) }}
                onMouseEnter={() => setActive(i)}
                className={`flex w-full flex-col items-start gap-0.5 px-3 py-1.5 text-left text-sm ${i === active ? 'bg-status-info-bg' : 'hover:bg-surface-2'}`}
              >
                <span className="font-medium text-ink">{c.name}</span>
                <span className="text-xs text-primary">@{candidateHandle(c)}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

// ── Rendered note body ────────────────────────────────────────────────────────

// Same detection grammar as the backend, for splitting a posted body into text
// and @handle tokens. Capturing group keeps the delimiters in String.split().
const MENTION_TOKEN = /((?:^|[^\w@])@[A-Za-z0-9][A-Za-z0-9._-]{0,63})/g

interface MentionTextProps {
  note: string
  mentions?: ClaimReviewNoteMention[]
}

/**
 * Render a note body, highlighting any `@handle` that the backend recorded in
 * `mentions[]`. Resolved handles (mapped to a Graphite user) get a solid badge;
 * unresolved handles get a plain highlight. Handles that aren't in `mentions[]`
 * (e.g. feature was off when the note was written) render as ordinary text.
 */
export function MentionText({ note, mentions }: MentionTextProps) {
  if (!note) return <>{'—'}</>
  if (!mentions || mentions.length === 0) return <>{note}</>

  // handle (lower-cased, as the backend stores it) → resolved?
  const byHandle = new Map<string, boolean>()
  for (const m of mentions) byHandle.set(m.handle.toLowerCase(), m.resolved)

  const parts = note.split(MENTION_TOKEN)
  return (
    <>
      {parts.map((part, i) => {
        // A token part looks like "<lead>@handle" where lead is '' or one non-word char.
        const m = part.match(/^([^\w@]?)@([A-Za-z0-9][A-Za-z0-9._-]{0,63})$/)
        if (!m) return <span key={i}>{part}</span>
        const lead = m[1]
        const handle = m[2]
        // Trailing punctuation the parser trims (e.g. "@jdoe.") isn't part of
        // the stored handle — match on the trimmed form.
        const trimmed = handle.toLowerCase().replace(/[.\-_]+$/, '')
        if (!byHandle.has(trimmed)) return <span key={i}>{part}</span>
        const resolved = byHandle.get(trimmed)
        return (
          <span key={i}>
            {lead}
            <span
              className={
                resolved
                  ? 'rounded px-1 font-medium text-primary bg-status-info-bg'
                  : 'rounded px-1 font-medium text-ink-muted bg-surface-2'
              }
              title={resolved ? 'Mentioned staff member' : 'Mention did not match a staff member'}
            >
              @{handle}
            </span>
          </span>
        )
      })}
    </>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Count review notes on this claim that mention the given user id. Current-claim
 * scope only — see the "gap" note in the tab: Graphite has no global
 * unread-mentions endpoint, so cross-claim unread mentions flow through the
 * standard notification bell (`claim_mention` type) instead.
 */
export function countMyMentions(
  notes: { mentions?: ClaimReviewNoteMention[] }[] | undefined,
  myUserId: number | null | undefined,
): number {
  if (!notes || !myUserId) return 0
  return notes.reduce(
    (acc, n) => acc + ((n.mentions ?? []).some(m => m.resolved && m.userId === myUserId) ? 1 : 0),
    0,
  )
}
