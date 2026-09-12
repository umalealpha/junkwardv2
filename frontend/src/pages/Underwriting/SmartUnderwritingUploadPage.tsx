/**
 * Smart Underwriting Upload — drop a broker policy schedule in whatever
 * format it arrived in (workbook, PDF, CSV, Word, or a photo of a printout),
 * the smart-uw-engine extracts cover sections / sums insured / rates / vehicle
 * schedules, and the underwriter reviews + issues. The review action opens the
 * existing CreateWizard pre-filled from extracted_json (no new write paths).
 *
 * Patterns from Imports/PolicyActivationImportPage (dropzone) +
 * BatchProcessing/BatchCreatePage (FormData upload) + api/client.
 */
import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import apiClient from '../../api/client'
import SmartUploadProcessing3D from '../../components/SmartUploadProcessing3D'
import SmartUwAiConfigCard from './SmartUwAiConfigCard'
import { fmtPula } from '../../utils/format'
// One definition of the extraction schema, shared with the wizard's Smart UW
// panel — the review screen and the prefill must never drift apart on what a
// section or a vehicle row looks like.
import type { SmartUwRisk as Extracted } from '../Policies/CreateWizard/smartUwPrefill'
import { insuredMatchesPolicy } from '../Policies/CreateWizard/smartUwPrefill'
// The exception half of the mapping: lines the AI placed but is unsure of, and
// lines it would not guess a bucket for. Both are the underwriter's call, made
// here before the schedule is carried into a policy.
import {
  BUCKETS, BUCKET_LABEL, exceptionCount, pendingLines, flaggedLines,
  placePending, dropPending, confirmFlagged, moveLine, unplaceLine,
  lineText, lineAmounts, sectionTitle,
  type Bucket, type LineAmounts,
} from '../Policies/CreateWizard/smartUwClassify'

interface RiskExtraction {
  id: number
  segment_name: string
  extracted_json: string // parse to the review-screen shape (CreateWizard payloads)
  confidence: number
  provider: string
  // Validation errors the extractor recorded for this segment (JSON array
  // string, or null when the segment validated clean).
  discrepancies: string | null
  human_verified: boolean
}

type Phase = 'idle' | 'uploading' | 'extracting' | 'completed' | 'failed'

// Where the schedule is going. Broker schedules almost never carry a Graphite
// policy number (the Diesel Heads 2026-2027 schedule has none), so the
// underwriter picks the target BEFORE uploading rather than the AI guessing it.
type TargetMode = 'new_business' | 'existing'

interface PolicyAction {
  id: number
  transaction_type: string
  status: string
  effective_from: string | null
  effective_to: string | null
  transaction_reason: string | null
}

interface PolicyHit {
  id: number
  policy_number: string
  customer_name: string | null
  product_id: number | null
  product_name: string | null
  status: number
  term_start_date: string | null
  term_end_date: string | null
  actions: PolicyAction[]
}

// dd/mm/yyyy — matches how dates read everywhere else in the ops portal.
function fmtDate(d: string | null): string {
  if (!d) return '—'
  const t = String(d).slice(0, 10).split('-')
  return t.length === 3 ? `${t[2]}/${t[1]}/${t[0]}` : String(d)
}

// Money, but blank when the schedule genuinely carried no figure. fmtPula
// renders null as "P 0.00", which on a review screen reads as a real zero
// premium rather than "the AI found nothing here".
function money(v: number | string | null | undefined): string {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(v)
  return isNaN(n) ? String(v) : fmtPula(n)
}

// Rates arrive exactly as the schedule wrote them — 0.00101, or 0.4 for a
// percentage column. Printing them through a currency/percent formatter would
// assert a scale the extractor never claimed, so show the raw figure and let
// the underwriter compare it against the schedule.
function rate(v: number | null | undefined): string {
  if (v === null || v === undefined) return '—'
  const n = Number(v)
  return isNaN(n) ? String(v) : String(n)
}

function txt(v: any): string {
  const t = v === null || v === undefined ? '' : String(v).trim()
  return t === '' ? '—' : t
}

// Extraction calls an LLM per sheet/segment, so a big multi-sheet workbook can
// take several minutes. Give the user an honest expectation rather than a
// silent spinner.
const EST_SECONDS = 600 // ~10 minutes for large schedules

function fmt(s: number) {
  const m = Math.floor(s / 60)
  const sec = s % 60
  return `${m}:${sec.toString().padStart(2, '0')}`
}

export default function SmartUnderwritingUploadPage() {
  const [file, setFile] = useState<File | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const [, setJobId] = useState<number | null>(null)
  const [risks, setRisks] = useState<RiskExtraction[]>([])
  // Which segments have their extracted-data panel open. Nothing was
  // reviewable before this: the list showed a segment name and a confidence
  // badge, so the only way to see what the AI actually read was to click
  // through to the wizard — which prefills contact/date fields only and
  // drops the coverage sections and vehicle schedule entirely.
  const [openRisks, setOpenRisks] = useState<Set<number>>(new Set())
  // The underwriter's amended copy of a segment, keyed by extraction id. The
  // AI's answer is never overwritten in place: an edit lands here, is saved to
  // the extraction row on demand, and is what "Review & Issue" carries into
  // the wizard — so what they classified is what gets applied.
  const [edits, setEdits] = useState<Record<number, Extracted>>({})
  const [savingId, setSavingId] = useState<number | null>(null)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const [phase, setPhase] = useState<Phase>('idle')
  const [elapsed, setElapsed] = useState(0)
  // Backend-reported stage. 'queued' means no worker has started the job yet;
  // 'processing' means the engine is actually reading the workbook. Showing
  // one spinner for both hid a dead smartuw worker behind a fake progress bar.
  const [stage, setStage] = useState<'queued' | 'processing' | null>(null)
  const [stalled, setStalled] = useState(false)
  // ── Target picker ──────────────────────────────────────────────────────
  const [targetMode, setTargetMode] = useState<TargetMode>('new_business')
  const [policyQuery, setPolicyQuery] = useState('')
  const [policyHits, setPolicyHits] = useState<PolicyHit[]>([])
  const [searching, setSearching] = useState(false)
  const [searched, setSearched] = useState(false)
  const [selectedPolicy, setSelectedPolicy] = useState<PolicyHit | null>(null)
  const [selectedActionId, setSelectedActionId] = useState<number | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const navigate = useNavigate()

  // Tick an elapsed-seconds counter while we are uploading or extracting.
  useEffect(() => {
    if (phase !== 'uploading' && phase !== 'extracting') return
    const t = setInterval(() => setElapsed((e) => e + 1), 1000)
    return () => clearInterval(t)
  }, [phase])

  const uploadMutation = useMutation({
    mutationFn: async (f: File) => {
      const fd = new FormData()
      fd.append('file', f)
      // Stamp the operator's chosen target so the backend records WHERE this
      // schedule lands. Without it the row defaults to new business, which is
      // the behaviour that shipped before the picker existed.
      fd.append('target_mode', targetMode)
      if (targetMode === 'existing' && selectedPolicy && selectedActionId) {
        fd.append('target_policy_id', String(selectedPolicy.id))
        fd.append('target_action_id', String(selectedActionId))
      }
      return apiClient.post('/underwriting/smart-upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onMutate: () => {
      setRisks([]); setOpenRisks(new Set()); setMsg(null); setElapsed(0); setPhase('uploading')
      setStage(null); setStalled(false); setEdits({})
    },
    onSuccess: (res) => {
      setJobId(res.data.job_id)
      setPhase('extracting')
      poll(res.data.job_id)
    },
    onError: (err: any) => {
      setPhase('failed')
      setMsg({ ok: false, text: err?.response?.data?.message || 'Upload failed' })
    },
  })

  async function poll(id: number) {
    // Absolute stop. The backend watchdog fails a stalled row at 1500s, but a
    // poller that can never give up is its own bug: before this, a job that
    // was never picked up left the page spinning "Extracting" until the tab
    // was closed, with no error anywhere.
    const started = Date.now()
    const GIVE_UP_MS = 1_620_000 // 27 min — backend watchdog (25m) + slack

    const tick = async () => {
      try {
        const r = await apiClient.get(`/underwriting/smart-upload/${id}`)
        if (r.data.status === 'completed') {
          const rows: RiskExtraction[] = r.data.risks || []
          setRisks(rows)
          setPhase('completed')
          setStage(null); setStalled(false)
          // Say up front how much of this needs a person. An extraction that
          // reads "Extracted 3 risk(s)" and quietly carries nine unplaced
          // lines is the one an underwriter applies without looking.
          const attention = rows.reduce((a, row) => {
            try {
              return a + exceptionCount(JSON.parse(row.extracted_json)).total
            } catch {
              return a
            }
          }, 0)
          // A run can come back 'completed' having read nothing at all — a
          // dead model id, every segment refused, an image-only sheet. The
          // backend already writes the cause to `message` and marks the
          // segment `_unmapped`, but this banner ignored both and rendered a
          // GREEN "Extracted 1 risk(s) — review below." over a total failure,
          // with the reason visible only after expanding "View extracted
          // data". Detect it from the rows themselves — `message` also
          // carries a benign summary on a good run, so it cannot be the test.
          const barren = rows.length === 0 || rows.every((row) => {
            try {
              const risk = JSON.parse(row.extracted_json)
              if (risk?._unmapped) return true
              return (risk?.coverages?.length || 0) === 0
                && (risk?.motor?.length || 0) === 0
                && (risk?.unclassified?.length || 0) === 0
            } catch {
              return true
            }
          })
          // Only worth showing when it is the cause of a barren read; on a
          // good run this banner builds its own text.
          const cause = barren ? String(r.data.message || '').trim() : ''
          setMsg({
            ok: attention === 0 && !barren,
            text: `Extracted ${rows.length} risk(s) — review below.`
              + (attention > 0
                ? ` ${attention} line(s) need your attention: open a segment and`
                  + ' classify the highlighted lines.'
                : '')
              + (barren
                ? ' Nothing could be read from this schedule — it needs to be'
                  + ' classified by hand.'
                  + (cause ? ` Reason: ${cause}` : '')
                : ''),
          })
          return
        }
        if (r.data.status === 'failed') {
          setPhase('failed')
          setStage(null); setStalled(false)
          // Whatever WAS read still belongs to the underwriter. A failed
          // status used to blank the list, which threw away readable segments
          // along with the failure — the upload has to reach a person either
          // way, so the segments stay on screen with the reason above them.
          setRisks(r.data.risks || [])
          setMsg({ ok: false, text: r.data.message || 'Extraction failed' })
          return
        }
        setStage(r.data.status === 'processing' ? 'processing' : 'queued')
        setStalled(!!r.data.stalled)
        // Warn in place while it is still legitimately running — the operator
        // should not have to guess whether a long wait is normal.
        setMsg(r.data.stalled && r.data.message
          ? { ok: false, text: r.data.message }
          : null)
      } catch {
        // transient network blip — fall through and retry
      }
      if (Date.now() - started > GIVE_UP_MS) {
        setPhase('failed')
        setStage(null)
        setMsg({ ok: false, text: 'Extraction did not finish and stopped reporting. '
          + 'Check the smartuw worker/engine, then re-upload the schedule.' })
        return
      }
      setTimeout(tick, 2500)
    }
    tick()
  }

  // Look the typed policy number up. Partial matches are returned, so a
  // half-remembered number still finds the policy. Selecting a single hit
  // automatically opens its action list — that is the "dates dropdown".
  async function searchPolicy() {
    const q = policyQuery.trim()
    if (!q) return
    setSearching(true); setSearched(false)
    setPolicyHits([]); setSelectedPolicy(null); setSelectedActionId(null)
    try {
      const r = await apiClient.get('/underwriting/smart-upload/policy-lookup', {
        params: { policy_number: q },
      })
      const hits: PolicyHit[] = r.data?.policies ?? []
      setPolicyHits(hits)
      // Exactly one match is the common case — select it so the operator only
      // has to pick the action.
      if (hits.length === 1) setSelectedPolicy(hits[0])
      setSearched(true)
    } catch (err: any) {
      setMsg({ ok: false, text: err?.response?.data?.error || 'Policy lookup failed' })
      setSearched(true)
    } finally {
      setSearching(false)
    }
  }

  function onDrop(e: React.DragEvent) {
    e.preventDefault(); setDragOver(false)
    const f = e.dataTransfer.files?.[0]
    if (f) setFile(f)
  }

  // Open the existing DOM/COM create wizard pre-filled from this segment's
  // extraction. No new write path — the underwriter confirms product / agency /
  // plan / coverages on the wizard and issues through the normal flow.
  function handleReviewAndIssue(risk: RiskExtraction) {
    // The underwriter's classification wins over the AI's answer — that is the
    // whole point of the review screen, so it is what travels to the wizard.
    let extracted: any = edits[risk.id] ?? null
    if (!extracted) {
      try {
        extracted = JSON.parse(risk.extracted_json)
      } catch {
        setMsg({ ok: false, text: `Could not parse the extraction for "${risk.segment_name}". Issue it manually via Create Policy.` })
        return
      }
    }
    // The INSURED is the policy holder. Handing a schedule to a policy that
    // belongs to someone else files valid figures against the wrong client,
    // and nothing downstream would ever flag it — so it stops here, at the
    // point where the two names are both known.
    if (targetMode === 'existing' && selectedPolicy) {
      const insured = String(extracted?.customer?.name ?? '').trim()
      const holder  = String(selectedPolicy.customer_name ?? '').trim()
      if (insured && holder && !insuredMatchesPolicy(insured, holder)) {
        setMsg({ ok: false, text:
          `Not matching — this schedule is for "${insured}", but policy `
          + `${selectedPolicy.policy_number ?? selectedPolicy.id} belongs to "${holder}". `
          + 'Nothing was exported. Pick the policy for that insured, or correct the name.' })
        return
      }
    }

    const state = {
      smartUwRisk: extracted,
      smartUwSegment: risk.segment_name,
      smartUwConfidence: risk.confidence,
      smartUwRiskId: risk.id,
    }

    // Existing policy: open THAT policy's edit wizard on THAT action, so the
    // extracted coverages land on the transaction the operator chose rather
    // than creating a second policy for a client we already insure.
    if (targetMode === 'existing' && selectedPolicy && selectedActionId) {
      navigate(`/policies/${selectedPolicy.id}/edit?action_id=${selectedActionId}`, { state })
      return
    }
    navigate('/policies/create', { state })
  }

  /** The segment as it currently stands — the underwriter's copy if they have
   *  touched it, otherwise the AI's. */
  function riskValue(r: RiskExtraction): Extracted | null {
    if (edits[r.id]) return edits[r.id]
    try {
      return JSON.parse(r.extracted_json) as Extracted
    } catch {
      return null
    }
  }

  /**
   * Persist one segment's classification.
   *
   * Writes the extraction row only — no policy data — so it is safe to save
   * mid-review, and the placement survives a reload or a hand-over to another
   * underwriter. The backend recounts the outstanding exceptions from the JSON
   * itself rather than trusting a number from here.
   */
  async function saveClassification(r: RiskExtraction) {
    const value = edits[r.id]
    if (!value) return
    setSavingId(r.id)
    try {
      const res = await apiClient.patch(
        `/underwriting/smart-upload/extraction/${r.id}`,
        { extracted_json: value, human_verified: exceptionCount(value).total === 0 }
      )
      // Keep the row in step with what was stored, so the amber panel and the
      // confidence badge reflect the save without a re-poll.
      const disc = res.data?.discrepancies
      setRisks((prev) => prev.map((row) => row.id === r.id
        ? {
            ...row,
            extracted_json: JSON.stringify(value),
            discrepancies: Array.isArray(disc) && disc.length ? JSON.stringify(disc) : null,
            confidence: res.data?.outstanding === 0 ? 1 : row.confidence,
            human_verified: res.data?.outstanding === 0,
          }
        : row))
      // The row now HOLDS what was saved, so the local copy is dropped: the
      // Save button disappears (nothing outstanding to save) and riskValue
      // reads the same content straight off the row.
      setEdits((prev) => {
        const next = { ...prev }
        delete next[r.id]
        return next
      })
      setMsg({ ok: true, text: res.data?.message || 'Classification saved.' })
    } catch (err: any) {
      setMsg({
        ok: false,
        text: err?.response?.data?.error || err?.response?.data?.message
          || 'Could not save the classification. It is still on screen — try again.',
      })
    } finally {
      setSavingId(null)
    }
  }

  // Upload is blocked until the operator has actually chosen a destination.
  const targetReady = targetMode === 'new_business'
    || (!!selectedPolicy && !!selectedActionId)

  const busy = phase === 'uploading' || phase === 'extracting'
  // Cap the visual bar at 95% until completion so it never reads "done" early.
  const pct = phase === 'completed' ? 100
    : Math.min(95, Math.round((elapsed / EST_SECONDS) * 100))

  const steps: { key: Phase | 'done'; label: string }[] = [
    { key: 'uploading', label: 'Uploading' },
    { key: 'extracting', label: 'Extracting' },
    { key: 'done', label: 'Ready to review' },
  ]
  const phaseIndex = phase === 'uploading' ? 0 : phase === 'extracting' ? 1 : (phase === 'completed' ? 2 : 0)

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Smart Underwriting Upload</h1>
      <p className="text-sm text-gray-500">
        Drop the client's schedule exactly as they sent it — workbook, PDF, CSV, Word
        or a photo of a printout, in their format, not ours. The AI reads it and maps
        it into our format: Coverage, Extension, Miscellaneous Item, Excess, plus the
        vehicle schedule. An upload is never blocked: where the AI is unsure the line
        is flagged <span className="font-medium">please check</span>, and where it
        genuinely cannot tell, the line is left for you to classify — never guessed,
        because a wrong Excess or Coverage changes the premium. You have the final say
        on every line before anything reaches a policy.
      </p>

      {/* Provider key / reader choice. Renders only for an admin role, and
          opens itself when no key is configured — the state in which every
          upload fails with "No Gemini API key". */}
      <SmartUwAiConfigCard />

      {/* ── Step 1: where is this schedule going? ────────────────────────
          Broker schedules rarely carry a Graphite policy number, so the
          destination is chosen here rather than guessed from the file. */}
      <div className="bg-surface rounded-lg shadow p-6 space-y-4">
        <div>
          <div className="text-sm font-semibold text-ink">1. Where should this schedule go?</div>
          <div className="text-xs text-ink-faint mt-0.5">
            Pick the destination first — schedules usually don't carry a policy number.
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          {([
            { id: 'new_business', label: 'New Business', hint: 'No policy yet' },
            { id: 'existing', label: 'Existing Policy', hint: 'Endorse / renew' },
          ] as { id: TargetMode; label: string; hint: string }[]).map((m) => (
            <button
              key={m.id}
              type="button"
              disabled={busy}
              onClick={() => {
                setTargetMode(m.id)
                // Leaving 'existing' clears the selection so a stale policy can
                // never be submitted with a New Business upload.
                if (m.id === 'new_business') {
                  setSelectedPolicy(null); setSelectedActionId(null)
                  setPolicyHits([]); setSearched(false)
                }
              }}
              className={`px-4 py-2 rounded-md border text-sm text-left transition disabled:opacity-50
                ${targetMode === m.id
                  ? 'border-orange-500 bg-orange-50 text-orange-700'
                  : 'border-line text-ink-muted hover:border-ink-faint'}`}
            >
              <div className="font-medium">{m.label}</div>
              <div className="text-xs opacity-70">{m.hint}</div>
            </button>
          ))}
        </div>

        {targetMode === 'existing' && (
          <div className="space-y-3 pt-1">
            <div className="flex flex-wrap gap-2">
              <input
                type="text"
                value={policyQuery}
                disabled={busy}
                onChange={(e) => setPolicyQuery(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); searchPolicy() } }}
                placeholder="Policy number"
                className="flex-1 min-w-[220px] px-3 py-2 border border-line rounded-md text-sm
                  focus:outline-none focus:ring-1 focus:ring-orange-400 disabled:opacity-50"
              />
              <button
                type="button"
                onClick={searchPolicy}
                disabled={busy || searching || !policyQuery.trim()}
                className="px-4 py-2 text-sm bg-primary text-primary-contrast rounded-md disabled:opacity-50"
              >
                {searching ? 'Searching…' : 'Find'}
              </button>
            </div>

            {searched && policyHits.length === 0 && (
              <div className="text-sm text-amber-700 bg-amber-50 rounded-md p-3">
                No policy matches "{policyQuery}". Check the number, or switch to New Business.
              </div>
            )}

            {/* More than one hit — make the operator confirm which policy. */}
            {policyHits.length > 1 && (
              <div className="border border-line rounded-md divide-y divide-line max-h-56 overflow-y-auto">
                {policyHits.map((h) => (
                  <button
                    key={h.id}
                    type="button"
                    onClick={() => { setSelectedPolicy(h); setSelectedActionId(null) }}
                    className={`w-full text-left px-3 py-2 text-sm transition
                      ${selectedPolicy?.id === h.id ? 'bg-orange-50' : 'hover:bg-surface-2'}`}
                  >
                    <span className="font-medium text-ink">{h.policy_number}</span>
                    <span className="text-ink-muted"> · {h.customer_name || 'Unnamed'}</span>
                    <span className="text-ink-faint text-xs"> · {h.product_name || `Product ${h.product_id}`}</span>
                  </button>
                ))}
              </div>
            )}

            {selectedPolicy && (
              <div className="rounded-md border border-line p-3 space-y-3">
                <div className="text-sm">
                  <span className="font-medium text-ink">{selectedPolicy.policy_number}</span>
                  <span className="text-ink-muted"> · {selectedPolicy.customer_name || 'Unnamed'}</span>
                  <div className="text-xs text-ink-faint">
                    {selectedPolicy.product_name || `Product ${selectedPolicy.product_id}`}
                    {' · term '}{fmtDate(selectedPolicy.term_start_date)} – {fmtDate(selectedPolicy.term_end_date)}
                  </div>
                </div>

                {/* The dates dropdown: this policy's own transactions. Labels
                    show the ACTION's effective dates, never the policy expiry. */}
                <label className="block">
                  <span className="text-xs font-medium text-ink-muted">Load into which transaction?</span>
                  <select
                    value={selectedActionId ?? ''}
                    disabled={busy}
                    onChange={(e) => setSelectedActionId(e.target.value ? Number(e.target.value) : null)}
                    className="mt-1 w-full px-3 py-2 border border-line rounded-md text-sm
                      focus:outline-none focus:ring-1 focus:ring-orange-400 disabled:opacity-50"
                  >
                    <option value="">Select a transaction…</option>
                    {selectedPolicy.actions.map((a) => (
                      <option key={a.id} value={a.id}>
                        {a.transaction_type} · {fmtDate(a.effective_from)} – {fmtDate(a.effective_to)} · {a.status}
                      </option>
                    ))}
                  </select>
                </label>

                {selectedPolicy.actions.length === 0 && (
                  <div className="text-xs text-amber-700">
                    This policy has no transactions to load into. Create the endorsement
                    on the policy first, then come back.
                  </div>
                )}
              </div>
            )}
          </div>
        )}
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <div className="text-sm font-semibold text-ink mb-3">2. Upload the schedule</div>
        <div
          className={`border-2 border-dashed rounded-lg p-10 text-center transition ${
            dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300'
          } ${busy ? 'opacity-60 pointer-events-none' : ''}`}
          onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
          onDragLeave={() => setDragOver(false)}
          onDrop={onDrop}
          onClick={() => !busy && inputRef.current?.click()}
          role="button"
        >
          <input
            ref={inputRef}
            type="file"
            accept=".xlsx,.xls,.xlsb,.pdf,.csv,.txt,.tsv,.md,.docx,.png,.jpg,.jpeg,.webp,.gif,.bmp"
            className="hidden"
            onChange={(e) => e.target.files?.[0] && setFile(e.target.files[0])}
          />
          {file ? (
            <p className="text-gray-700">{file.name}</p>
          ) : (
            <p className="text-gray-400">Drag a schedule here, or click to browse</p>
          )}
        </div>

        <button
          onClick={() => file && targetReady && uploadMutation.mutate(file)}
          disabled={!file || busy || !targetReady}
          className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md disabled:opacity-50"
        >
          {phase === 'uploading' ? 'Uploading…' : phase === 'extracting' ? 'Extracting…' : 'Upload & Extract'}
        </button>
        {!targetReady && (
          <span className="ml-3 text-xs text-amber-700">
            Pick a policy and a transaction above first.
          </span>
        )}
      </div>

      {/* ── Status bar ─────────────────────────────────────────────────── */}
      {(busy || phase === 'completed' || phase === 'failed') && (
        <div className="bg-white rounded-lg shadow p-5 space-y-3">
          <SmartUploadProcessing3D phase={phase} pct={pct} elapsed={elapsed} stage={stage} />
          {/* step pills */}
          <div className="flex items-center gap-2">
            {steps.map((s, i) => {
              const done = phase === 'completed' ? true : i < phaseIndex
              const active = i === phaseIndex && busy
              return (
                <div key={s.label} className="flex items-center gap-2">
                  <span className={`flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold transition
                    ${done ? 'bg-green-500 text-white' : active ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}`}>
                    {done ? '✓' : i + 1}
                  </span>
                  <span className={`text-sm ${active ? 'text-blue-700 font-medium' : done ? 'text-green-700' : 'text-gray-400'}`}>{s.label}</span>
                  {i < steps.length - 1 && <span className="w-6 h-px bg-gray-200" />}
                </div>
              )
            })}
          </div>

          {/* progress bar */}
          <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-[width] duration-700 ease-out
                ${phase === 'failed' ? 'bg-red-500' : phase === 'completed' ? 'bg-green-500'
                  : stalled ? 'bg-amber-500' : 'bg-blue-600'}
                ${phase === 'extracting' ? 'animate-pulse' : ''}`}
              style={{ width: `${phase === 'failed' ? 100 : pct}%` }}
            />
          </div>

          {/* status line */}
          <div className="flex items-center justify-between text-xs">
            <span className="text-gray-600">
              {phase === 'uploading' && 'Uploading the schedule…'}
              {phase === 'extracting' && stage === 'queued' &&
                'Starting the extraction engine — this normally takes a few seconds…'}
              {phase === 'extracting' && stage !== 'queued' &&
                'AI is reading the workbook — extracting cover sections, sums insured and vehicle schedules…'}
              {phase === 'completed' && 'Extraction complete — review the segments below.'}
              {phase === 'failed' && 'Extraction did not complete. See the message below.'}
            </span>
            {busy && (
              <span className="text-gray-400 tabular-nums">
                {fmt(elapsed)} elapsed · est. ~10 min for large schedules
              </span>
            )}
          </div>
        </div>
      )}

      {msg && (
        <div className={`p-3 rounded-md text-sm ${msg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
          {msg.text}
        </div>
      )}

      {risks.length > 0 && (
        <div className="bg-white rounded-lg shadow divide-y">
          {risks.map((r) => {
            const open = openRisks.has(r.id)
            const value = riskValue(r)
            const ex = exceptionCount(value)
            const dirty = !!edits[r.id]
            return (
              <div key={r.id}>
                <div className="p-4 flex items-center justify-between gap-3">
                  <div>
                    <div className="font-medium text-ink">{r.segment_name}</div>
                    <div className="text-xs text-ink-faint">
                      via {r.provider || 'the reader'} · confidence {Math.round((r.confidence || 0) * 100)}%
                    </div>
                    {/* The exception count, on the closed row. An unplaced
                        line hidden behind a collapsed panel is a line that
                        gets applied unread. */}
                    {ex.total > 0 && (
                      <div className="mt-1 flex flex-wrap gap-1.5">
                        {ex.pending > 0 && (
                          <span className="px-2 py-0.5 text-[11px] rounded-full bg-amber-100 text-amber-800">
                            {ex.pending} need{ex.pending === 1 ? 's' : ''} classification
                          </span>
                        )}
                        {ex.flagged > 0 && (
                          <span className="px-2 py-0.5 text-[11px] rounded-full bg-orange-100 text-orange-800">
                            {ex.flagged} please check
                          </span>
                        )}
                      </div>
                    )}
                    {ex.total === 0 && r.human_verified && (
                      <div className="mt-1 text-[11px] text-green-700">
                        Every line classified and confirmed.
                      </div>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    {dirty && (
                      <button
                        onClick={() => saveClassification(r)}
                        disabled={savingId === r.id}
                        className="px-3 py-1.5 text-sm border border-green-600 text-green-700 rounded-md
                          hover:bg-green-50 disabled:opacity-40 transition"
                      >
                        {savingId === r.id ? 'Saving…' : 'Save classification'}
                      </button>
                    )}
                    {/* Look at the extraction BEFORE carrying it into a policy. */}
                    <button
                      onClick={() => setOpenRisks((prev) => {
                        const next = new Set(prev)
                        next.has(r.id) ? next.delete(r.id) : next.add(r.id)
                        return next
                      })}
                      className="px-3 py-1.5 text-sm border border-line text-ink-muted rounded-md
                        hover:border-ink-faint transition"
                    >
                      {open ? 'Hide extracted data' : 'View extracted data'}
                    </button>
                    {/* Review opens the CreateWizard pre-filled from extracted_json */}
                    <button
                      onClick={() => handleReviewAndIssue(r)}
                      className="px-3 py-1.5 text-sm bg-orange-500 text-white rounded-md hover:bg-orange-600 transition"
                    >
                      Review &amp; Issue
                    </button>
                  </div>
                </div>
                {open && (
                  <ExtractedReview
                    risk={r}
                    value={value}
                    onChange={(next) => setEdits((prev) => ({ ...prev, [r.id]: next }))}
                  />
                )}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

/**
 * The exceptions, and the underwriter's final say over them.
 *
 * Two lists, and neither of them is an error:
 *
 *   Needs classification — the AI would not guess which of our four buckets a
 *                          line belongs in. It is unplaced until a person
 *                          picks one, because a line guessed into Excess or
 *                          Coverage changes the premium and nothing
 *                          downstream would ever flag it.
 *   Please check         — the AI placed the line but is not confident. It
 *                          stays where it was put, highlighted, and can be
 *                          confirmed, moved, or sent back for classification.
 *
 * Nothing here writes to a policy. It amends the extracted schedule the page
 * holds; the wizard's own endpoints still do every write, so all the usual
 * validation applies to whatever the underwriter decides.
 */
function ClassificationPanel({ risk, onChange }: {
  risk: Extracted
  onChange: (next: Extracted) => void
}) {
  const pending = pendingLines(risk)
  const flagged = flaggedLines(risk)
  if (pending.length === 0 && flagged.length === 0) return null

  const sections = Array.isArray(risk.coverages) ? risk.coverages : []

  return (
    <div className="space-y-4">
      {pending.length > 0 && (
        <div className="rounded-md border border-amber-300 bg-amber-50/60 p-3 space-y-3">
          <div>
            <div className="text-sm font-semibold text-amber-900">
              Needs classification ({pending.length})
            </div>
            <div className="text-xs text-amber-800 mt-0.5">
              The AI would not guess a bucket for these lines, so they are unplaced —
              a wrong Excess or Coverage changes the premium. Correct the wording or
              the figures if the schedule reads differently, pick where each one
              belongs, then Place it. Leave a heading or a total off the quote.
            </div>
          </div>

          {sections.length === 0 && (
            <div className="text-xs text-amber-900 bg-amber-100 rounded p-2">
              This segment has no coverage section to place a line under. Build the
              section in the wizard first, or leave these lines and enter them there.
            </div>
          )}

          <div className="space-y-2">
            {pending.map((line) => (
              <PendingRow
                key={`${line.ref.section ?? 'top'}-${line.ref.index}-${line.text}`}
                line={line}
                sections={sections}
                onPlace={(target, text, amounts) =>
                  onChange(placePending(risk, line.ref, target, text, amounts))}
                onDrop={() => onChange(dropPending(risk, line.ref))}
              />
            ))}
          </div>
        </div>
      )}

      {flagged.length > 0 && (
        <div className="rounded-md border border-orange-300 bg-orange-50/60 p-3 space-y-3">
          <div>
            <div className="text-sm font-semibold text-orange-900">
              Please check ({flagged.length})
            </div>
            <div className="text-xs text-orange-800 mt-0.5">
              Placed, but the AI is not confident. Confirm the ones it read right,
              move the ones it did not.
            </div>
          </div>

          <div className="space-y-1.5">
            {flagged.map((line) => (
              <div
                key={`${line.ref.section}-${line.bucket}-${line.ref.index}-${line.text}`}
                className="rounded border border-orange-200 bg-surface p-2
                  flex flex-wrap items-center gap-2 justify-between"
              >
                <div className="min-w-0">
                  <div className="text-sm text-ink break-words">{line.text}</div>
                  <div className="text-[11px] text-ink-faint">
                    {line.sectionTitle} · read as {BUCKET_LABEL[line.bucket]}
                    {line.reason ? ` — ${line.reason}` : ''}
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-1.5">
                  <button
                    type="button"
                    onClick={() => onChange(confirmFlagged(risk, line.ref))}
                    className="px-2 py-1 text-xs border border-green-600 text-green-700
                      rounded hover:bg-green-50"
                  >
                    Correct as read
                  </button>
                  <select
                    value=""
                    onChange={(e) => {
                      const to = e.target.value as Bucket
                      if (to) onChange(moveLine(risk, line.ref, to))
                    }}
                    className="border border-line rounded bg-surface px-2 py-1 text-xs"
                  >
                    <option value="">Move to…</option>
                    {BUCKETS.filter((b) => b !== line.bucket).map((b) => (
                      <option key={b} value={b}>{BUCKET_LABEL[b]}</option>
                    ))}
                  </select>
                  <button
                    type="button"
                    onClick={() => onChange(unplaceLine(risk, line.ref))}
                    className="px-2 py-1 text-xs border border-line text-ink-muted
                      rounded hover:border-ink-faint"
                  >
                    Unsure — park it
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

/**
 * One unplaced line, editable.
 *
 * Editable on purpose: the schedule's own wording and figures are a starting
 * point, and the underwriter is the one who decides what goes on the quote.
 * Local state, seeded from the extraction and reset by the key when the line
 * moves — so a half-typed correction is never applied to a different line.
 */
function PendingRow({ line, sections, onPlace, onDrop }: {
  line: ReturnType<typeof pendingLines>[number]
  sections: any[]
  onPlace: (target: { section: number; bucket: Bucket }, text: string, amounts: LineAmounts) => void
  onDrop: () => void
}) {
  const [text, setText] = useState(line.text)
  // Its own section when it had one, otherwise the first — a line printed
  // under no heading still has to hang off a section to reach the policy.
  const [section, setSection] = useState<number>(line.ref.section ?? 0)
  // No default bucket. A pre-selected one is a guess wearing a person's name.
  const [bucket, setBucket] = useState<Bucket | ''>('')
  const [si, setSi] = useState(line.sum_insured === null ? '' : String(line.sum_insured))
  const [rt, setRt] = useState(line.rate === null ? '' : String(line.rate))
  const [pr, setPr] = useState(line.premium === null ? '' : String(line.premium))

  const asNum = (v: string): number | null => {
    const t = v.trim()
    if (t === '') return null
    const n = Number(t.replace(/[,\s]/g, ''))
    return isNaN(n) ? null : n
  }

  const isExcess = bucket === 'excesses'
  const canPlace = !!bucket && sections.length > 0 && text.trim() !== ''

  return (
    <div className="rounded border border-amber-200 bg-surface p-2 space-y-2">
      <div className="flex flex-wrap items-start gap-2">
        <input
          value={text}
          onChange={(e) => setText(e.target.value)}
          className="flex-1 min-w-[16rem] border border-line rounded bg-surface px-2 py-1 text-sm"
          placeholder="The line as it should read on the quote"
        />
        {line.reason && (
          <div className="text-[11px] text-amber-800 self-center max-w-xs">{line.reason}</div>
        )}
      </div>

      <div className="flex flex-wrap items-end gap-2">
        <label className="text-[11px] text-ink-faint">
          <div>{isExcess ? 'Min %' : 'Sum insured'}</div>
          <input
            value={isExcess ? rt : si}
            onChange={(e) => (isExcess ? setRt(e.target.value) : setSi(e.target.value))}
            className="w-28 border border-line rounded bg-surface px-2 py-1 text-sm tabular-nums"
          />
        </label>
        <label className="text-[11px] text-ink-faint">
          <div>{isExcess ? 'Min amount' : 'Rate'}</div>
          <input
            value={isExcess ? pr : rt}
            onChange={(e) => (isExcess ? setPr(e.target.value) : setRt(e.target.value))}
            className="w-28 border border-line rounded bg-surface px-2 py-1 text-sm tabular-nums"
          />
        </label>
        {!isExcess && (
          <label className="text-[11px] text-ink-faint">
            <div>Premium</div>
            <input
              value={pr}
              onChange={(e) => setPr(e.target.value)}
              className="w-28 border border-line rounded bg-surface px-2 py-1 text-sm tabular-nums"
            />
          </label>
        )}

        <label className="text-[11px] text-ink-faint">
          <div>Section</div>
          <select
            value={section}
            onChange={(e) => setSection(Number(e.target.value))}
            disabled={sections.length === 0}
            className="border border-line rounded bg-surface px-2 py-1 text-sm max-w-[14rem]"
          >
            {sections.map((c, i) => (
              <option key={i} value={i}>{sectionTitle(c)}</option>
            ))}
          </select>
        </label>

        <label className="text-[11px] text-ink-faint">
          <div>Place as</div>
          <select
            value={bucket}
            onChange={(e) => setBucket(e.target.value as Bucket | '')}
            className="border border-line rounded bg-surface px-2 py-1 text-sm"
          >
            <option value="">Choose…</option>
            {BUCKETS.map((b) => (
              <option key={b} value={b}>{BUCKET_LABEL[b]}</option>
            ))}
          </select>
        </label>

        <button
          type="button"
          disabled={!canPlace}
          onClick={() => onPlace(
            { section, bucket: bucket as Bucket },
            text.trim(),
            { sum_insured: asNum(si), rate: asNum(rt), premium: asNum(pr) }
          )}
          className="px-3 py-1.5 text-xs bg-primary text-primary-contrast rounded
            disabled:opacity-40"
        >
          Place
        </button>
        <button
          type="button"
          onClick={onDrop}
          className="px-3 py-1.5 text-xs border border-line text-ink-muted rounded
            hover:border-ink-faint"
        >
          Not on the quote
        </button>
      </div>

      {line.sectionTitle && line.ref.section === null && (
        <div className="text-[11px] text-ink-faint">
          Printed under “{line.sectionTitle}” — that heading is not a section on this
          risk, so choose the section it belongs to.
        </div>
      )}
    </div>
  )
}

/**
 * Read-only view of ONE segment's extraction.
 *
 * Purpose is verification, not editing: the operator compares what the model
 * read against the schedule in front of them before anything is carried into
 * a policy. Nothing here writes — the wizard is still the only write path.
 *
 * It deliberately shows the coverage sections and the vehicle schedule in
 * full, because those are the parts the wizard prefill does NOT carry over
 * (it fills customer contact, term dates and the risk address only). Until
 * the prefill covers them, this panel is where the underwriter reads the
 * sums insured / rates / registrations off and enters them.
 */
function ExtractedReview({ risk, value, onChange }: {
  risk: RiskExtraction
  /** the segment as it currently stands — the underwriter's copy if edited */
  value: Extracted | null
  /** hand an amended copy back to the page; nothing is written to a policy */
  onChange: (next: Extracted) => void
}) {
  const data: Extracted | null = value

  if (!data) {
    return (
      <div className="px-4 pb-4 text-sm text-red-700">
        The stored extraction for this segment is not valid JSON — issue it manually
        via Create Policy.
      </div>
    )
  }

  const pol = data.policy || {}
  const cust = data.customer || {}
  const loc = data.risk_location || {}
  const coverages = Array.isArray(data.coverages) ? data.coverages : []
  const motor = Array.isArray(data.motor) ? data.motor : []

  // Validation problems the extractor recorded — a missing insured name, a
  // truncated sheet. Shown as-is: a segment the model was unsure about is
  // exactly what a human should be checking.
  //
  // The classification exceptions live in the SAME stored list (they are
  // written to discrepancies so no column had to change), but they are
  // actionable above, line by line, so they are not repeated here as text.
  let issues: string[] = []
  if (risk.discrepancies) {
    try {
      const parsed = JSON.parse(risk.discrepancies)
      issues = Array.isArray(parsed) ? parsed.map(String) : [String(parsed)]
    } catch {
      issues = [String(risk.discrepancies)]
    }
  }
  issues = issues.filter((e) =>
    !/^(Please check|Needs classification)\b/.test(e.trim()))

  // Sum of what was extracted, so the total can be eyeballed against the
  // schedule's own "TOTAL ANNUAL PREMIUM" line. Sections first; a section that
  // carried no total of its own falls back to the sum of its detail lines.
  const covTotal = coverages.reduce((acc, c) => {
    if (c.section_premium !== null && c.section_premium !== undefined) {
      return acc + Number(c.section_premium || 0)
    }
    return acc + (c.details || []).reduce((a, d) => a + Number(d.premium || 0), 0)
  }, 0)
  const motorTotal = motor.reduce((a, m) => a + Number(m.premium || 0), 0)

  const field = (label: string, value: string) => (
    <div>
      <div className="text-[11px] uppercase tracking-wide text-ink-faint">{label}</div>
      <div className="text-sm text-ink break-words">{value}</div>
    </div>
  )

  return (
    <div className="px-4 pb-5 space-y-5">
      {/* Nothing could be mapped. The upload is NOT refused — the schedule
          reaches the underwriter with the reason, and Review & Issue still
          opens the wizard so it can be built by hand. */}
      {data._unmapped && (
        <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm space-y-1">
          <div className="font-semibold text-amber-900">
            Needs classification — the reader could not map this schedule
          </div>
          <div className="text-amber-800 text-xs">{String(data._unmapped)}</div>
          <div className="text-amber-800 text-xs">
            Nothing was guessed. Use <span className="font-medium">Review &amp; Issue</span> to
            open the wizard and enter the schedule by hand, or convert the file
            (a clean .xlsx or a text PDF reads best) and upload it again.
          </div>
        </div>
      )}

      {/* What the reader withheld from the AI before sending. Not a defect and
          not an exception the underwriter can place — so it is a neutral
          notice, deliberately outside exceptionCount(): counting it would make
          the segment permanently un-confirmable with no way to clear it. But it
          MUST be shown. Identifier lines and whole identifier columns are
          removed before the schedule goes to the provider, and without this
          block a schedule that lost a column reads exactly like one that never
          had it. Labels only — the values are never stored or displayed. */}
      {Array.isArray(data._redacted_lines) && data._redacted_lines.length > 0 && (
        <div className="rounded-md border border-slate-300 bg-slate-50 p-3 text-sm space-y-1">
          <div className="font-semibold text-slate-800">
            Withheld from the AI reader — check these against the schedule
          </div>
          <ul className="list-disc pl-4 space-y-0.5 text-slate-700 text-xs">
            {data._redacted_lines.map((label, i) => (
              <li key={i}>{String(label)}</li>
            ))}
          </ul>
          <div className="text-slate-600 text-xs">
            These carried ID, contact or bank details, so they were removed before
            the schedule was sent (AD-POL-AI-GOV-001). Nothing here was read by the
            AI — if any of it belongs on the quote, enter it by hand from the
            original schedule.
          </div>
        </div>
      )}

      {/* The two kinds of exception, and the underwriter's say over both. */}
      <ClassificationPanel risk={data} onChange={onChange} />

      {issues.length > 0 && (
        <div className="rounded-md bg-amber-50 text-amber-800 text-xs p-3 space-y-1">
          <div className="font-semibold">The extractor flagged this segment:</div>
          <ul className="list-disc pl-4 space-y-0.5">
            {issues.map((e, i) => <li key={i}>{e}</li>)}
          </ul>
        </div>
      )}

      {/* ── Insured / policy ─────────────────────────────────────────── */}
      <div>
        <div className="text-xs font-semibold text-ink-muted mb-2">Insured</div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {field('Name', txt(cust.name))}
          {field('Entity type', txt(cust.entity_type))}
          {field('Company reg no', txt(cust.company_reg_no))}
          {field('VAT reg no', txt(cust.vat_reg_no))}
          {field('Business / occupation', txt(cust.occupation))}
          {field('Phone', txt(cust.phone))}
          {field('Email', txt(cust.email))}
          {field('Postal address', txt(cust.postal_address))}
          {field('Physical address', txt(cust.physical_address))}
          {field('Risk location', txt(loc.name))}
          {field('Risk address', txt(loc.physical_address))}
        </div>
      </div>

      <div>
        <div className="text-xs font-semibold text-ink-muted mb-2">Policy</div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {field('Product group', txt(pol.product_group))}
          {field('Term start', fmtDate(pol.term_start_date ?? null))}
          {field('Expiry', fmtDate(pol.expiry_date ?? null))}
          {field('Premium frequency', txt(pol.premium_freq))}
          {field('Existing policy no', txt(pol.existing_policy_number))}
          {field('Renewal', pol.is_renewal ? 'Yes' : 'No')}
          {field('Broker', txt(pol.broker))}
          {field('Account executive', txt(pol.account_executive))}
        </div>
      </div>

      {/* ── Coverage sections ────────────────────────────────────────── */}
      <div>
        <div className="text-xs font-semibold text-ink-muted mb-2">
          Coverage sections ({coverages.length})
        </div>
        {coverages.length === 0 ? (
          <div className="text-sm text-ink-faint">No coverage sections extracted.</div>
        ) : (
          <div className="space-y-3">
            {coverages.map((c, ci) => (
              <div key={ci} className="border border-line rounded-md overflow-hidden">
                <div className="px-3 py-2 flex items-center justify-between gap-3 bg-surface-2">
                  <div className="text-sm font-medium text-ink">
                    {txt(c.section)}
                    <span className="ml-2 text-xs font-normal text-ink-faint">
                      {txt(c.coverage_hint)}
                    </span>
                  </div>
                  <div className="text-sm tabular-nums text-ink">{money(c.section_premium)}</div>
                </div>
                {/* Our four buckets, each shown as its own block so the
                    underwriter can see WHERE the AI put every line — the
                    mapping is the thing being reviewed, not just the figures.
                    Extensions, items and excesses were extracted all along and
                    rendered nowhere, so a wrong bucket was invisible here. */}
                {BUCKETS.map((bucket) => {
                  const rows: any[] = Array.isArray((c as any)[bucket]) ? (c as any)[bucket] : []
                  if (rows.length === 0) return null
                  return (
                    <div key={bucket} className="border-t border-line">
                      <div className="px-3 pt-2 text-[11px] uppercase tracking-wide text-ink-faint">
                        {BUCKET_LABEL[bucket]}
                      </div>
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead>
                            <tr className="text-[11px] uppercase tracking-wide text-ink-faint">
                              <th className="text-left font-medium px-3 py-1.5">Line</th>
                              <th className="text-right font-medium px-3 py-1.5">
                                {bucket === 'excesses' ? 'Min %' : 'Sum insured'}
                              </th>
                              <th className="text-right font-medium px-3 py-1.5">
                                {bucket === 'excesses' ? 'Min amount' : 'Rate'}
                              </th>
                              <th className="text-right font-medium px-3 py-1.5">
                                {bucket === 'excesses' ? '' : 'Premium'}
                              </th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-line">
                            {rows.map((row, di) => {
                              const a = lineAmounts(bucket, row)
                              return (
                                <tr key={di} className={row?.needs_check ? 'bg-orange-50' : undefined}>
                                  <td className="px-3 py-1.5 text-ink">
                                    {txt(lineText(bucket, row))}
                                    {row?.needs_check && (
                                      <span className="ml-2 px-1.5 py-0.5 text-[10px] rounded
                                        bg-orange-200 text-orange-900 align-middle">
                                        please check
                                      </span>
                                    )}
                                    {row?.placed_by_hand && !row?.needs_check && (
                                      <span className="ml-2 text-[10px] text-green-700 align-middle">
                                        placed by you
                                      </span>
                                    )}
                                    {row?.check_reason && (
                                      <div className="text-[11px] text-orange-800">{String(row.check_reason)}</div>
                                    )}
                                    {bucket === 'extensions' && row?.text && !row?.sum_insured && !row?.premium && (
                                      <div className="text-[11px] text-ink-faint">wording only</div>
                                    )}
                                  </td>
                                  <td className="px-3 py-1.5 text-right tabular-nums">
                                    {bucket === 'excesses' ? rate(a.rate) : money(a.sum_insured)}
                                  </td>
                                  <td className="px-3 py-1.5 text-right tabular-nums">
                                    {bucket === 'excesses' ? money(a.premium) : rate(a.rate)}
                                  </td>
                                  <td className="px-3 py-1.5 text-right tabular-nums">
                                    {bucket === 'excesses' ? '' : money(a.premium)}
                                  </td>
                                </tr>
                              )
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )
                })}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ── Vehicle schedule ─────────────────────────────────────────── */}
      <div>
        <div className="text-xs font-semibold text-ink-muted mb-2">
          Vehicle schedule ({motor.length})
        </div>
        {motor.length === 0 ? (
          <div className="text-sm text-ink-faint">No vehicles extracted.</div>
        ) : (
          <div className="border border-line rounded-md overflow-auto max-h-96">
            <table className="w-full text-sm">
              <thead className="sticky top-0 bg-surface-2">
                <tr className="text-[11px] uppercase tracking-wide text-ink-faint">
                  <th className="text-left font-medium px-3 py-1.5">Registration</th>
                  <th className="text-left font-medium px-3 py-1.5">Make / model</th>
                  <th className="text-right font-medium px-3 py-1.5">Year</th>
                  <th className="text-right font-medium px-3 py-1.5">Sum insured</th>
                  <th className="text-right font-medium px-3 py-1.5">Rate</th>
                  <th className="text-right font-medium px-3 py-1.5">Premium</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {motor.map((m, mi) => (
                  <tr key={mi}>
                    <td className="px-3 py-1.5 text-ink font-medium">{txt(m.registration)}</td>
                    <td className="px-3 py-1.5 text-ink">{txt(m.make_model)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{txt(m.year)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{money(m.sum_insured)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{rate(m.rate)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{money(m.premium)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Totals of what was EXTRACTED — not a rated premium. Compare them
          against the schedule's own total to spot a section the reader
          missed or double-counted. */}
      <div className="flex flex-wrap gap-6 text-sm border-t border-line pt-3">
        <div>
          <span className="text-ink-faint text-xs uppercase tracking-wide mr-2">Sections</span>
          <span className="tabular-nums text-ink">{money(covTotal)}</span>
        </div>
        <div>
          <span className="text-ink-faint text-xs uppercase tracking-wide mr-2">Vehicles</span>
          <span className="tabular-nums text-ink">{money(motorTotal)}</span>
        </div>
        <div className="text-xs text-ink-faint self-center">
          Extracted figures only — the policy premium is whatever Rate calculates
          once these are entered.
        </div>
      </div>

      {data.notes && (
        <div className="text-xs text-ink-muted border border-line rounded-md p-3">
          <span className="font-semibold">Extractor notes: </span>{String(data.notes)}
        </div>
      )}
    </div>
  )
}
