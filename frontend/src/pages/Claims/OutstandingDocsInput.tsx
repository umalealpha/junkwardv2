import { useState } from 'react'

/**
 * Outstanding-documents checklist / tag input. Free-text entries the handler
 * adds one at a time (Enter or "Add"); each renders as a removable chip.
 * Shared by the FNOL create + detail screens.
 */
export default function OutstandingDocsInput({
  value,
  onChange,
  disabled = false,
}: {
  value: string[]
  onChange: (next: string[]) => void
  disabled?: boolean
}) {
  const [draft, setDraft] = useState('')

  function add() {
    const v = draft.trim()
    if (!v) return
    // De-dupe case-insensitively so the same doc isn't listed twice.
    if (!value.some((d) => d.toLowerCase() === v.toLowerCase())) {
      onChange([...value, v])
    }
    setDraft('')
  }

  function remove(idx: number) {
    onChange(value.filter((_, i) => i !== idx))
  }

  return (
    <div>
      {!disabled && (
        <div className="flex gap-2">
          <input
            type="text"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') { e.preventDefault(); add() }
            }}
            placeholder="e.g. Police report, ID copy, Quotation…"
            className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface text-ink"
          />
          <button
            type="button"
            onClick={add}
            disabled={!draft.trim()}
            className="px-3 py-2 bg-surface border border-line text-ink rounded-md hover:bg-surface-2 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Add
          </button>
        </div>
      )}

      {value.length > 0 ? (
        <div className="flex flex-wrap gap-2 mt-2">
          {value.map((doc, idx) => (
            <span
              key={`${doc}-${idx}`}
              className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-status-warning-bg text-status-warning-fg"
            >
              {doc}
              {!disabled && (
                <button
                  type="button"
                  onClick={() => remove(idx)}
                  aria-label={`Remove ${doc}`}
                  className="leading-none text-sm opacity-70 hover:opacity-100 cursor-pointer"
                >
                  &times;
                </button>
              )}
            </span>
          ))}
        </div>
      ) : (
        disabled && <p className="text-sm text-ink-muted mt-1">None outstanding.</p>
      )}
    </div>
  )
}
