import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { useToast } from '../../components/common/Toast'
import {
  FacPage, FacPageHead, FacTile, FacTiles, FacPanel, FacFlags, FacNote, FacDialog,
} from '../../components/fac/FacUI'
import FacPlacementDialog, { EMPTY_FAC_DRAFT } from '../../components/fac/FacPlacementDialog'
import FacCaptureChoice, { type FacCaptureIntent } from '../../components/fac/FacCaptureChoice'
import { FacParticipantsPanel } from '../../components/fac/FacParticipantsPanel'
import {
  fetchFacPlacements, createFacPlacement, askFac, fetchFacPlacement, downloadFacRegisterExport,
  facMoney, facAmount, facPct, FAC_STATUS_LABELS, FAC_STAGES, FAC_STAGE_TONE,
  type FacFilters, type FacPlacementDetail, type FacStage,
} from '../../api/fac'

/**
 * The FAC register — one row per facultative placement.
 *
 * Two things drive the layout. First, the row flags (policy not in Graphite,
 * policy not active, exchange rate missing, warranty breached) are the reason
 * this register exists, so they sit ON the row and in the scoreboard, never
 * behind a click — and each scoreboard tile filters to what it counts. Second,
 * the payable is shown in Pula: a foreign-currency line with no exchange rate
 * shows a dash, not a number, because treating it as Pula would be a silent lie.
 */

const FLAG_FILTERS: Array<{ key: string; label: string }> = [
  { key: '', label: 'Everything' },
  { key: 'awaiting_premium', label: 'Awaiting client premium' },
  { key: 'ready_to_settle', label: 'Ready to settle' },
  { key: 'ppw_breached', label: 'Warranty breached' },
  { key: 'ppw_due', label: 'Warranty due soon' },
  { key: 'unmatched_policy', label: 'Policy not in Graphite' },
  { key: 'inactive_policy', label: 'Policy not active' },
  { key: 'rate_missing', label: 'Exchange rate missing' },
]

export default function FacRegisterPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const { toast } = useToast()
  /**
   * The capture flow, in order: pick the process, then fill the form for it.
   *
   * `choosing` is its own state rather than a flag on the form because the two
   * processes are not a variation of one screen — the question has to be answered
   * before the form can know what to ask for.
   */
  const [capture, setCapture] = useState<null | 'choosing' | FacCaptureIntent>(null)
  // The participants panel is opened per row rather than loaded with the list —
  // the panel is a join across two more tables and the register pages 25 at a time.
  const [panelFor, setPanelFor] = useState<{ id: number; ref: string } | null>(null)
  const [exporting, setExporting] = useState(false)
  const [question, setQuestion] = useState('')
  const [answer, setAnswer] = useState<{ text: string | null; error: string | null; redacted: number } | null>(null)

  const filters: FacFilters = {
    search: searchParams.get('search') || undefined,
    status: searchParams.get('status') || undefined,
    placement_type: (searchParams.get('type') as 'fac' | 'auto_fac') || undefined,
    financial_year: searchParams.get('fy') || undefined,
    flag: searchParams.get('flag') || undefined,
    stage: (searchParams.get('stage') as FacStage) || undefined,
    sort: (searchParams.get('sort') as 'oldest' | 'newest') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const list = useQuery({
    queryKey: ['fac-placements', filters],
    queryFn: () => fetchFacPlacements(filters),
  })

  const counterparties = useQuery({
    queryKey: ['fac-counterparties'],
    queryFn: () => apiClient.get('/reinsurance/reinsurers', { params: { per_page: 200 } }).then(r => r.data),
    staleTime: 5 * 60 * 1000,
  })

  // The dialog owns the form, its validation and the per-field rejections; the
  // page owns only the call and the refresh. mutateAsync (not mutate) so a 422
  // reaches the dialog, which is what marks the fields.
  const saveMut = useMutation({
    mutationFn: (payload: Record<string, unknown>) => createFacPlacement(payload),
    // Straight to the placement, carrying the step that comes next.
    //
    // The capture is not the end of either process — one owes the reinsurer a
    // slip, the other owes the file a signed one — and leaving the capturer on
    // the register with a toast is how that second half got forgotten. The
    // placement's own screen is where both actions already live.
    onSuccess: (created: FacPlacementDetail) => {
      qc.invalidateQueries({ queryKey: ['fac-placements'] })
      // The toast outlives the navigation, so it is the only place the capture can
      // confirm itself — and for a new placement it reports whether the slip
      // actually came out, since that happens server-side on save.
      if (capture === 'new') {
        toast.success(created.slipGeneratedAt
          ? `${created.facReference} captured and slip ${created.facSlipNo} generated.`
          : `${created.facReference} captured. The slip still has to be generated.`)
      } else {
        toast.success(`${created.facReference} captured and payable.`)
      }
      const next = capture === 'new' ? 'slip' : 'signed-slip'
      setCapture(null)
      navigate(`/reinsurance/fac/${created.id}?next=${next}`)
    },
  })

  const askMut = useMutation({
    mutationFn: (q: string) => askFac(q, filters.financial_year),
    onSuccess: r => setAnswer({ text: r.answer, error: r.error, redacted: r.redactedFields }),
    onError: () => setAnswer({ text: null, error: 'The assistant could not be reached.', redacted: 0 }),
  })

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    value ? next.set(key, value) : next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  const rows = list.data?.data ?? []
  const totals = list.data?.totals
  const meta = list.data?.meta ?? { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
  const cpList: Array<{ id: number; company_name: string }> = counterparties.data?.data ?? []

  return (
    <FacPage>
      <FacPageHead
        title="FAC Register"
        blurb="Every facultative placement — one row each, so each settlement is tracked on its own. Policy details and client receipts are read from Graphite."
        actions={
          <>
            {/*
              A button, not a link. The old <a href="/api/v1/..."> resolved against
              the FRONTEND host, so it never reached the API at all — the SPA's
              catch-all answered it with "Page Not Found". It also carried no bearer
              token. Both are handled by going through the API client.
            */}
            <button
              className="fac-btn"
              disabled={exporting}
              onClick={async () => {
                setExporting(true)
                try {
                  await downloadFacRegisterExport(filters)
                } catch (e: any) {
                  toast.error(e?.message || 'The export could not be downloaded.')
                } finally {
                  setExporting(false)
                }
              }}
            >
              {exporting ? 'Preparing…' : 'Export'}
            </button>
            <Link className="fac-btn" to="/reinsurance/fac/bordereau">Bordereau</Link>
            <Link className="fac-btn" to="/reinsurance/fac/settlements">Settlements</Link>
            <button className="fac-btn fac-btn--primary" onClick={() => setCapture('choosing')}>
              Add placement
            </button>
          </>
        }
      />

      {/* ── Scoreboard. Every count is also its own filter. ─────────── */}
      {totals && (
        <FacTiles>
          <FacTile label="Total payable" value={facMoney(totals.payableBwp)} tone="navy"
                   title="Sum of the gross ceded premium in Pula, for this filter. Drafts awaiting signature are NOT counted." />
          {/*
            Drafts are out of the payable on purpose — nothing is owed until the
            reinsurer signs. That makes them easy to forget, so they get a tile of
            their own: a placement sitting here is one whose slip still has to go
            out, or come back.
          */}
          <FacTile label="Awaiting signature" value={String(totals.draftCount)}
                   tone={totals.draftCount ? 'warn' : 'quiet'}
                   title="Captured and sent, or waiting to be sent, for the reinsurer's signature. Not counted as payable."
                   onClick={() => setFilter('status', 'draft')} />
          <FacTile label="Awaiting premium" value={String(totals.awaitingPremium)}
                   onClick={() => setFilter('flag', 'awaiting_premium')} />
          <FacTile label="Ready to settle" value={String(totals.readyToSettle)} tone={totals.readyToSettle ? 'ok' : 'quiet'}
                   onClick={() => setFilter('flag', 'ready_to_settle')} />
          <FacTile label="Warranty breached" value={String(totals.ppwBreached)} tone={totals.ppwBreached ? 'danger' : 'quiet'}
                   onClick={() => setFilter('flag', 'ppw_breached')} />
          <FacTile label="Not in Graphite" value={String(totals.unmatchedPolicy)} tone={totals.unmatchedPolicy ? 'danger' : 'quiet'}
                   onClick={() => setFilter('flag', 'unmatched_policy')} />
          <FacTile label="Rate missing" value={String(totals.rateMissing)} tone={totals.rateMissing ? 'warn' : 'quiet'}
                   onClick={() => setFilter('flag', 'rate_missing')} />
        </FacTiles>
      )}

      {/* ── Filters ────────────────────────────────────────────────── */}
      <div className="flex flex-wrap items-end gap-3">
        <label className="w-80 fac-field">
          <span className="fac-label">Search</span>
          <input
            type="text"
            className="fac-input"
            placeholder="Policy, insured, reference, slip, counterparty…"
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => setFilter('search', e.target.value)}
          />
        </label>
        <label className="w-56 fac-field">
          <span className="fac-label">Show</span>
          <select className="fac-select" value={filters.flag || ''} onChange={e => setFilter('flag', e.target.value)}>
            {FLAG_FILTERS.map(f => <option key={f.key} value={f.key}>{f.label}</option>)}
          </select>
        </label>
        <label className="w-44 fac-field">
          <span className="fac-label">Basis</span>
          <select className="fac-select" value={filters.placement_type || ''} onChange={e => setFilter('type', e.target.value)}>
            <option value="">FAC and Auto FAC</option>
            <option value="fac">FAC only</option>
            <option value="auto_fac">Auto FAC only</option>
          </select>
        </label>
        <label className="w-44 fac-field">
          <span className="fac-label">Status</span>
          <select className="fac-select" value={filters.status || ''} onChange={e => setFilter('status', e.target.value)}>
            <option value="">Any status</option>
            {Object.entries(FAC_STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </label>
        {/* Where in the cycle — the filter the stage column exists to feed. */}
        <label className="w-52 fac-field">
          <span className="fac-label">Stage</span>
          <select className="fac-select" value={filters.stage || ''} onChange={e => setFilter('stage', e.target.value)}>
            <option value="">Any stage</option>
            {FAC_STAGES.map(s => <option key={s.key} value={s.key}>{s.label}</option>)}
          </select>
        </label>
        {/*
          Ascending by default, because the register is reconciled against the slip
          series and a numbered series shown backwards is hard to read against the
          master sheet. Newest-first stays available for working the queue.
        */}
        <label className="w-44 fac-field">
          <span className="fac-label">Order</span>
          <select className="fac-select" value={filters.sort || 'oldest'} onChange={e => setFilter('sort', e.target.value)}>
            <option value="oldest">Lowest number first</option>
            <option value="newest">Newest first</option>
          </select>
        </label>
      </div>

      {/* ── Assistant ──────────────────────────────────────────────── */}
      <FacPanel title="Ask about this register">
        <p className="fac-hint" style={{ marginTop: 0, marginBottom: 12 }}>
          Reads the register's own figures and explains them. It never calculates an amount and
          never changes anything. Policy numbers and names are replaced with tokens before the
          question leaves Graphite.
        </p>
        <div className="flex gap-2">
          <input
            type="text"
            className="fac-input"
            aria-label="Ask a question about the FAC register"
            value={question}
            onChange={e => setQuestion(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter' && question.trim()) askMut.mutate(question) }}
            placeholder="e.g. What should we deal with first this week?"
          />
          <button
            className="fac-btn fac-btn--primary"
            onClick={() => question.trim() && askMut.mutate(question)}
            disabled={askMut.isPending || !question.trim()}
          >
            {askMut.isPending ? 'Thinking…' : 'Ask'}
          </button>
        </div>
        {answer && (
          <div className="mt-3">
            {answer.text
              ? (
                <>
                  <div className="fac-note" style={{ whiteSpace: 'pre-wrap' }}>{answer.text}</div>
                  <p className="fac-hint">
                    {answer.redacted} identifying value(s) were replaced with tokens before sending.
                  </p>
                </>
              )
              : <FacNote tone="warn">{answer.error}</FacNote>}
          </div>
        )}
      </FacPanel>

      {/* ── The register ───────────────────────────────────────────── */}
      <FacPanel bodyless>
        {list.isFetching && !list.isLoading && <div className="fac-veil"><LoadingSpinner size="md" /></div>}
        <DualScrollTable>
          <table className="fac-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Policy / insured</th>
                <th>Cover</th>
                <th>We pay</th>
                <th>Risk carried by</th>
                <th className="fac-num">Gross</th>
                <th className="fac-num">Comm %</th>
                <th className="fac-num">Net</th>
                <th className="fac-num">Payable (BWP)</th>
                <th>PPW</th>
                <th>Stage</th>
              </tr>
            </thead>
            <tbody>
              {list.isLoading ? (
                <tr><td colSpan={11} style={{ padding: '48px', textAlign: 'center' }}><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={11} style={{ padding: 0 }}>
                  <EmptyState compact title="No placements"
                    description="Nothing matches this filter. Add a placement, or load the FAC master-sheet history." />
                </td></tr>
              ) : rows.map(r => (
                <tr key={r.id} data-void={r.status === 'cancelled' || r.isReversal ? 'true' : 'false'}>
                  <td>
                    <Link to={`/reinsurance/fac/${r.id}`} className="fac-link fac-ref">{r.facReference}</Link>
                    <div className="fac-hint" style={{ marginTop: 1 }}>
                      {r.placementTypeLabel}{r.facSlipNo ? ` · slip ${r.facSlipNo}` : ''}
                      {/*
                        Who is on the risk, for both bases. Labelled by the row's own
                        basis so it reads as "Auto FAC participants" on an Auto FAC
                        line and "FAC participants" on a FAC one.
                      */}
                      <button
                        type="button"
                        className="fac-link"
                        style={{ marginLeft: 6, background: 'none', border: 0, padding: 0, cursor: 'pointer' }}
                        title={`Who is participating on this ${r.placementTypeLabel} placement`}
                        aria-label={`Participants on ${r.facReference}`}
                        onClick={() => setPanelFor({ id: r.id, ref: r.facReference })}
                      >
                        👥
                      </button>
                    </div>
                  </td>
                  <td>
                    <span className="fac-strong">{r.policyNumber}</span>
                    <span className="fac-truncate fac-hint" style={{ marginTop: 1 }}>{r.insuredName || '—'}</span>
                    <FacFlags flags={r.flags} />
                  </td>
                  <td><span className="fac-truncate" style={{ maxWidth: 160, fontSize: 12 }}>{r.riGroupLabel || '—'}</span></td>
                  <td>{r.counterpartyName || '—'}</td>
                  <td><span className="fac-truncate fac-hint" style={{ maxWidth: 160 }}>{r.riskCarrier || '—'}</span></td>
                  <td className="fac-num">{facAmount(r.grossCededPremium, r.currency)}</td>
                  <td className="fac-num">{facPct(r.commissionPct)}</td>
                  <td className="fac-num">{facAmount(r.netCededPremium, r.currency)}</td>
                  <td className="fac-num">
                    {r.payableBwp === null
                      ? <span style={{ color: 'var(--fac-accent-ink)' }}
                              title="No exchange rate is recorded, so there is no Pula value. It is not being treated as Pula.">—</span>
                      : <span className="fac-strong">{facAmount(r.payableBwp)}</span>}
                  </td>
                  <td style={{ fontSize: 12, whiteSpace: 'nowrap' }}>
                    {r.ppwDueDate
                      ? <span style={{ color: r.flags.includes('ppw_breached') ? 'var(--fac-danger)' : 'inherit',
                                       fontWeight: r.flags.includes('ppw_breached') ? 600 : 400 }}>{r.ppwDueDate}</span>
                      : <span style={{ color: 'var(--fac-accent-ink)' }}>not set</span>}
                  </td>
                  {/*
                    Stage, not status. The status is still shown underneath because
                    Settlements and the filters speak in statuses — but the question
                    a reader has on the register is how far along the line is, and
                    "Placed" does not answer it.
                  */}
                  <td style={{ whiteSpace: 'nowrap' }}>
                    <span className={`fac-stage fac-stage--${FAC_STAGE_TONE[r.stage]}`}>
                      {r.stageStep > 0 && (
                        <span className="fac-stage-dots" aria-hidden="true">
                          {Array.from({ length: r.stageOf }, (_, i) => (
                            <span key={i} className={`fac-stage-dot ${i < r.stageStep ? 'is-done' : ''}`} />
                          ))}
                        </span>
                      )}
                      {r.stageLabel}
                    </span>
                    <span className="fac-hint" style={{ marginTop: 2 }}>
                      {r.stageStep > 0 ? `Step ${r.stageStep} of ${r.stageOf} · ` : ''}
                      {FAC_STATUS_LABELS[r.status]}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </DualScrollTable>

        {meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3"
               style={{ borderTop: '1px solid var(--fac-line)', background: 'var(--fac-surface-alt)' }}>
            <span className="fac-hint">Showing {meta.from}–{meta.to} of {meta.total}</span>
            <div className="flex items-center gap-1">
              <button className="fac-btn fac-btn--sm" disabled={meta.current_page === 1}
                      onClick={() => goToPage(meta.current_page - 1)}>Prev</button>
              <span className="fac-hint px-2">{meta.current_page} / {meta.last_page}</span>
              <button className="fac-btn fac-btn--sm" disabled={meta.current_page === meta.last_page}
                      onClick={() => goToPage(meta.current_page + 1)}>Next</button>
            </div>
          </div>
        )}
      </FacPanel>

      {/* ── Capture: the split, then the form for whichever was chosen ─ */}
      {capture === 'choosing' && (
        <FacCaptureChoice
          onChoose={intent => setCapture(intent)}
          onClose={() => setCapture(null)}
        />
      )}

      {(capture === 'new' || capture === 'signed') && (
        <FacPlacementDialog
          mode="create"
          intent={capture}
          initial={EMPTY_FAC_DRAFT}
          counterparties={cpList}
          onSave={payload => saveMut.mutateAsync(payload)}
          // Back to the question, not out of the flow — picking the wrong process
          // is the mistake this split exists to prevent, so correcting it must not
          // cost the capturer the whole form.
          onClose={() => setCapture('choosing')}
        />
      )}

      {/* ── Who is on the risk, opened from the row ──────────────────── */}
      {panelFor && (
        <FacParticipantsDialog
          id={panelFor.id}
          reference={panelFor.ref}
          onClose={() => setPanelFor(null)}
        />
      )}
    </FacPage>
  )
}

/**
 * The participants panel for one row, fetched on demand.
 *
 * Loaded on open rather than with the register: the panel joins the placement
 * lines and the slip's acceptance rows, and the list pages 25 placements at a
 * time, so carrying it on every row would be 50 extra queries for a panel the
 * capturer usually does not open.
 */
function FacParticipantsDialog({ id, reference, onClose }: {
  id: number
  reference: string
  onClose: () => void
}) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['fac', 'placement', id],
    queryFn: () => fetchFacPlacement(id),
  })

  return (
    <FacDialog title={`Participants — ${reference}`} onClose={onClose} width="lg">
      {isLoading && <LoadingSpinner />}
      {isError && <FacNote tone="danger">This placement could not be loaded.</FacNote>}
      {data && <FacParticipantsPanel participants={data.participants} />}
    </FacDialog>
  )
}
