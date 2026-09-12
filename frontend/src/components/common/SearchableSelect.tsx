import { useState, useRef, useEffect } from 'react'

interface Option {
  value: string
  label: string
}

interface SearchableSelectProps {
  label: string
  value: string | number
  onChange: (value: string) => void
  fetchOptions: (search: string) => Promise<Option[]>
  options?: Option[]  // Static options (used as initial/fallback)
  placeholder?: string
  error?: string
  required?: boolean
  loading?: boolean
  debounceMs?: number
}

export default function SearchableSelect({
  label, value, onChange, fetchOptions, options: staticOptions,
  placeholder = 'Type to search...', error, required, loading: externalLoading, debounceMs = 300,
}: SearchableSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [options, setOptions] = useState<Option[]>(staticOptions ?? [])
  const [loading, setLoading] = useState(false)
  const [highlightIndex, setHighlightIndex] = useState(-1)
  const containerRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const timerRef = useRef<ReturnType<typeof setTimeout>>()

  // Selected label
  const selectedLabel = [...options, ...(staticOptions ?? [])].find(o => String(o.value) === String(value))?.label || ''

  // Close on outside click
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [])

  // Load initial options — only once on mount
  const optionsLoaded = useRef(false)
  useEffect(() => {
    if (!optionsLoaded.current && staticOptions?.length) {
      setOptions(staticOptions)
      optionsLoaded.current = true
    }
  }, [staticOptions?.length])

  // Debounced search — only fetch when user types 2+ chars
  useEffect(() => {
    if (!open || search.length < 2) return
    clearTimeout(timerRef.current)
    timerRef.current = setTimeout(async () => {
      setLoading(true)
      try {
        const results = await fetchOptions(search)
        setOptions(results)
        setHighlightIndex(-1)
      } catch { }
      setLoading(false)
    }, debounceMs)

    return () => clearTimeout(timerRef.current)
  }, [search, open])

  function handleSelect(opt: Option) {
    onChange(opt.value)
    setSearch('')
    setOpen(false)
  }

  function handleKeyDown(e: React.KeyboardEvent) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setHighlightIndex(i => Math.min(i + 1, options.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setHighlightIndex(i => Math.max(i - 1, 0))
    } else if (e.key === 'Enter' && highlightIndex >= 0) {
      e.preventDefault()
      handleSelect(options[highlightIndex])
    } else if (e.key === 'Escape') {
      setOpen(false)
    }
  }

  return (
    <div ref={containerRef} className="relative">
      <label className="block text-xs font-medium text-ink-muted mb-1">
        {label}{required && <span className="text-red-500 ml-0.5">*</span>}
      </label>
      <div
        className={`flex items-center border rounded-md px-3 py-2 text-sm cursor-pointer ${
          error ? 'border-red-300' : open ? 'border-blue-500 ring-1 ring-blue-500' : 'border-line'
        } bg-surface`}
        onClick={() => { setOpen(true); setTimeout(() => inputRef.current?.focus(), 0) }}
      >
        {open ? (
          <input
            ref={inputRef}
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder={placeholder}
            className="flex-1 outline-none text-sm bg-transparent"
          />
        ) : (
          <span className={`flex-1 ${value ? 'text-ink' : 'text-ink-faint'}`}>
            {selectedLabel || placeholder}
          </span>
        )}
        {(loading || externalLoading) ? (
          <div className="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin ml-2" />
        ) : (
          <svg className={`w-4 h-4 text-ink-faint ml-2 transition ${open ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        )}
        {value && (
          <button
            onClick={e => { e.stopPropagation(); onChange(''); setSearch('') }}
            className="ml-1 text-ink-faint hover:text-ink"
          >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}
      </div>

      {open && (
        <div className="absolute z-50 w-full mt-1 bg-surface border border-line rounded-lg shadow-lg max-h-60 overflow-y-auto">
          {/* UAT 2026-05-28 (Muskan H7 sub-issue, follow-up to Arjun H7):
              When parent passes a non-empty staticOptions (e.g., the agency
              list pre-loaded from SSO lookups), the dropdown shows ALL
              entries unfiltered at 1-char input — the async fetch is gated
              at length >= 2 (line 59 above). The empty-state hint below
              therefore never appears for the most common case, leaving
              operators wondering why typing didn't narrow the list. Surface
              the hint as a banner ABOVE the list whenever search is 1 char,
              regardless of how many options are visible. */}
          {search.length === 1 && (
            <div className="px-4 py-2 text-xs text-amber-700 bg-amber-50 border-b border-amber-100">
              Type at least 2 characters to search…
            </div>
          )}
          {loading ? (
            <div className="px-4 py-3 text-sm text-ink-faint text-center">Searching...</div>
          ) : options.length === 0 ? (
            <div className="px-4 py-3 text-sm text-ink-faint text-center">
              {/* UAT 2026-05-26 (Arjun H7): typing 1 char returned a
                  bald "No results found" — operators couldn't tell
                  whether they'd typed too few chars or whether the
                  agency really didn't exist. Now we say so explicitly. */}
              {!search ? 'Type to search...'
                : search.length < 2 ? 'Type at least 2 characters...'
                : 'No results found'}
            </div>
          ) : (
            options.map((opt, i) => (
              <div
                key={opt.value}
                onClick={() => handleSelect(opt)}
                className={`px-4 py-2 text-sm cursor-pointer ${
                  String(opt.value) === String(value) ? 'bg-status-info-bg text-status-info-fg font-medium' :
                  i === highlightIndex ? 'bg-surface-2' : 'hover:bg-surface-2'
                }`}
              >
                {opt.label}
              </div>
            ))
          )}
        </div>
      )}

      {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
    </div>
  )
}
