import { forwardRef, useEffect, useImperativeHandle, useMemo, useState } from 'react'
import { fetchAssessors } from '../../api/assessors'
import { fetchMasterSuppliers } from '../../api/claimsMasterData'

/**
 * Claims-Tracker "New Claim" stage accordions + Comment Status, embedded into
 * Graphite's Create Claim page. ADDITIVE — this renders the 7 collapsible stage
 * accordions (numbered like the legacy tracker) plus a Comments (comment-status)
 * block, collects the operator's input, and exposes it via `collect()` so the
 * parent's submit can do a two-step save:
 *   1) POST /claims (the existing create flow)
 *   2) PATCH /claims-v2/{id}/sla-timeline  (workflow fields, via claimsSla api)
 *      + PUT /claims/{id}/comment-status   (comment status + sub-reason)
 *
 * The workflow field keys mirror ClaimStageTimelineService::EDITABLE_FIELDS on
 * the backend EXACTLY (the same list ClaimSlaTab edits). `contract_pricing_value`
 * / `cil_value` are the two numeric columns added alongside the PO stage.
 *
 * The comment-status vocabulary mirrors config/claims_comment_status.php — kept
 * as constants here because the per-claim options endpoint needs a claim id that
 * does not exist yet at create time. Keep in sync if that config changes.
 */

// ── Comment-status vocabulary (mirror of config/claims_comment_status.php) ──
export const COMMENT_STATUSES = [
  'Awaiting',
  'Outstanding Premiums',
  'Repudiated',
  'Claim Withdrawn',
  'Claim Closed',
  'Management Review',
  'System Issue',
  'Recovery & Legal',
  'File with Accounts',
  'Claim Below Excess',
]
export const AWAITING_SUB_REASONS = [
  'Claim Documents',
  'Invoices',
  'Assessment Report',
  'Signed CIL/FOR/Ex-Gratia',
  'Proof of Payment',
  'Signed AOL',
  'KYC',
  '3rd Party Insurance',
  'Demand Letter',
  'Salvage',
  'Excess',
]
export const SUB_REASON_STATUS = 'Awaiting'

// ── Static select option lists (Claims-Tracker parity) ──
const DISTANCE_OPTS = ['<50Km', '>50Km']
const YES_NO = ['Yes', 'No']
const NO_YES = ['No', 'Yes']
const JOB_END_STATUS_OPTS = ['Complete', 'Incomplete']
const PO_ISSUE_OPTS = ['Repair Order', 'Parts Supplier PO', 'Contract Pricing PO', 'CIL', 'FOR', 'AOL', 'Ex-gratia', 'Refund', 'Others']
const OTHER = 'Other'

/** Everything the parent needs for the second save step. */
export interface StageAccordionsCollected {
  /** Workflow fields for PATCH /claims-v2/{id}/sla-timeline (only non-empty). */
  workflow: Record<string, string | null>
  /** Comment status (null = leave unset). */
  commentStatus: string | null
  /** "Awaiting — what?" sub-reason (only when status = Awaiting). */
  commentSubReason: string | null
  /** True when anything was entered (drives whether step 2 runs at all). */
  hasData: boolean
}
export interface StageAccordionsHandle {
  collect: () => StageAccordionsCollected
}

interface Props {
  /** The selected claim type (drives Glass / Lock & Key stage muting). */
  claimType: string | null
  /**
   * Whether to render the built-in Comments (comment-status) block. Default
   * true (ClaimCreatePage relies on it). The FNOL create page owns its own
   * Comments block wired to the FNOL comment_status columns, so it passes
   * false here to avoid double-rendering Comments — the accordions'
   * collect().commentStatus is then unused by that caller.
   */
  showComments?: boolean
}

// ── Module-level field primitives (stable identity → no focus loss) ──
const labelCls = 'block text-[11px] font-medium text-ink-muted mb-1'
const inputCls =
  'w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary'

function DateInput({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <label className={labelCls}>{label}</label>
      <input type="date" value={value} onChange={(e) => onChange(e.target.value)} className={inputCls} />
    </div>
  )
}
function TextInput({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <label className={labelCls}>{label}</label>
      <input type="text" maxLength={255} value={value} onChange={(e) => onChange(e.target.value)} className={inputCls} />
    </div>
  )
}
function NumberInput({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <label className={labelCls}>{label}</label>
      <input
        type="number"
        min="0"
        step="0.01"
        inputMode="decimal"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={`${inputCls} tabular-nums`}
      />
    </div>
  )
}
function CommentInput({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div className="md:col-span-2 xl:col-span-3">
      <label className={labelCls}>{label}</label>
      <textarea rows={2} maxLength={2000} value={value} onChange={(e) => onChange(e.target.value)} className={inputCls} />
    </div>
  )
}
function SelectInput({
  label, value, onChange, options,
}: { label: string; value: string; onChange: (v: string) => void; options: string[] }) {
  return (
    <div>
      <label className={labelCls}>{label}</label>
      <select value={value} onChange={(e) => onChange(e.target.value)} className={inputCls}>
        <option value="">— Select —</option>
        {options.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
    </div>
  )
}

/** A numbered, collapsible accordion shell. */
function Accordion({
  n, title, open, onToggle, children,
}: { n: number; title: string; open: boolean; onToggle: () => void; children: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-line overflow-hidden">
      <button
        type="button"
        onClick={onToggle}
        className="w-full flex items-center gap-3 px-4 py-3 bg-surface-2 hover:bg-surface transition cursor-pointer text-left"
      >
        <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary text-primary-contrast text-xs font-semibold tabular-nums shrink-0">
          {n}
        </span>
        <span className="flex-1 text-sm font-semibold text-ink">{title}</span>
        <svg
          className={`w-4 h-4 text-ink-muted transition-transform ${open ? 'rotate-180' : ''}`}
          fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      {open && (
        <div className="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-5 gap-y-3 bg-surface">
          {children}
        </div>
      )}
    </div>
  )
}

const ClaimCreateStageAccordions = forwardRef<StageAccordionsHandle, Props>(function ClaimCreateStageAccordions(
  { claimType, showComments = true },
  ref,
) {
  // ── Stage-field state (keys mirror the backend whitelist) ──
  const [f, setF] = useState<Record<string, string>>({})
  const set = (k: string, v: string) => setF((s) => ({ ...s, [k]: v }))
  const g = (k: string) => f[k] ?? ''

  // assessor_name is a single free-text column: dropdown OR typed "Other".
  const [assessorSel, setAssessorSel] = useState('')
  const [assessorOther, setAssessorOther] = useState('')
  // panel_beater_name + panel_beater_other are TWO columns (parity): the
  // dropdown writes panel_beater_name, "Other" reveals panel_beater_other.
  const [panelSel, setPanelSel] = useState('')
  const [panelOther, setPanelOther] = useState('')

  // po_issue is a checkbox group joined to a comma string; two ticks reveal a
  // numeric value each; "Others" reveals a free-text.
  const [poIssue, setPoIssue] = useState<string[]>([])
  const togglePo = (opt: string) =>
    setPoIssue((prev) => (prev.includes(opt) ? prev.filter((x) => x !== opt) : [...prev, opt]))

  // Comment status.
  const [commentStatus, setCommentStatus] = useState('')
  const [commentSubReason, setCommentSubReason] = useState('')

  // ── Async option lists (graceful — empty on failure, "Other" still works) ──
  const [assessorNames, setAssessorNames] = useState<string[]>([])
  const [panelNames, setPanelNames] = useState<string[]>([])
  useEffect(() => {
    let alive = true
    fetchAssessors({ per_page: 200 })
      .then((r) => { if (alive) setAssessorNames(r.data.map((a) => a.name).filter(Boolean)) })
      .catch(() => { /* endpoint gated / absent — "Other" free-text still works */ })
    fetchMasterSuppliers({ type: 'panel_beater', per_page: 200 })
      .then((r) => { if (alive) setPanelNames(r.data.map((s) => s.name).filter(Boolean)) })
      .catch(() => { /* degrade to "Other" free-text */ })
    return () => { alive = false }
  }, [])

  // ── Claim-type muting: Glass / Lock & Key use only stages 3, 5, 7 ──
  const key = (claimType || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
  const restricted = useMemo(
    () => key.includes('GLASS') || key === 'KEYLOSS' || key.includes('LOCKSANDKEYS') || (key.includes('LOCK') && key.includes('KEY')),
    [key],
  )
  const shows = (n: number) => (restricted ? [3, 5, 7].includes(n) : true)

  // ── Collapse state ──
  const [open, setOpen] = useState<Record<number, boolean>>({})
  const toggle = (n: number) => setOpen((s) => ({ ...s, [n]: !s[n] }))

  useImperativeHandle(ref, () => ({
    collect(): StageAccordionsCollected {
      const wf: Record<string, string | null> = {}
      const put = (k: string, v: string | undefined | null) => {
        if (v != null && String(v).trim() !== '') wf[k] = String(v)
      }
      // Stage 1 — Assessor allotment & file upload
      if (shows(1)) {
        put('claim_docs_received', g('claim_docs_received'))
        put('assessor_allotment_date', g('assessor_allotment_date'))
        put('assessor_name', assessorSel === OTHER ? assessorOther : assessorSel)
        put('file_uploaded_to_gt', g('file_uploaded_to_gt'))
        put('gt_number', g('gt_number'))
        put('stage1_comment', g('stage1_comment'))
      }
      // Stage 2 — Physical assessment
      if (shows(2)) {
        put('distance', g('distance'))
        put('panel_beater_name', panelSel)
        if (panelSel === OTHER) put('panel_beater_other', panelOther)
        put('physical_assessment', g('physical_assessment'))
        put('physical_assessment_comment', g('physical_assessment_comment'))
      }
      // Stage 3 — Quote request & finalisation
      if (shows(3)) {
        put('quote_request_date', g('quote_request_date'))
        put('quote_request_comment', g('quote_request_comment'))
        put('under_warranty', g('under_warranty'))
        put('quote_finalisation', g('quote_finalisation'))
      }
      // Stage 4 — Assessment report
      if (shows(4)) {
        put('assessment_report_date', g('assessment_report_date'))
        put('assessment_report_comment', g('assessment_report_comment'))
      }
      // Stage 5 — PO generation & issue
      if (shows(5)) {
        put('po_generation_date', g('po_generation_date'))
        put('po_issue', poIssue.join(', '))
        if (poIssue.includes('Others')) put('po_issue_other', g('po_issue_other'))
        if (poIssue.includes('Contract Pricing PO')) put('contract_pricing_value', g('contract_pricing_value'))
        if (poIssue.includes('CIL')) put('cil_value', g('cil_value'))
        put('po_issue_date', g('po_issue_date'))
      }
      // Stage 6 — Parts delivery & confirmation
      if (shows(6)) {
        put('parts_eta', g('parts_eta'))
        put('parts_delivery_date', g('parts_delivery_date'))
        put('confirmation_date', g('confirmation_date'))
        put('mismatch_reported', g('mismatch_reported'))
        put('replacement_date', g('replacement_date'))
      }
      // Stage 7 — Job completion
      if (shows(7)) {
        put('job_end_date', g('job_end_date'))
        put('job_end_status', g('job_end_status'))
      }

      const cs = commentStatus || null
      const csr = cs === SUB_REASON_STATUS ? (commentSubReason || null) : null
      return {
        workflow: wf,
        commentStatus: cs,
        commentSubReason: csr,
        hasData: Object.keys(wf).length > 0 || !!cs,
      }
    },
  }))

  return (
    <div className="space-y-4">
      <div className="border-t border-line pt-4">
        <h3 className="text-lg font-semibold text-ink">Claim Tracking (optional)</h3>
        <p className="text-sm text-ink-muted mt-1">
          Comment status and stage timeline — saved to the claim after it is registered.
          {restricted && ' Glass / Lock &amp; Key claims use stages 3, 5 and 7 only.'}
        </p>
      </div>

      {/* ── Comments (comment status) — hidden when the caller owns Comments ── */}
      {showComments && (
        <div className="rounded-xl border border-line bg-surface p-4">
          <h4 className="text-sm font-semibold text-ink mb-3">Comments</h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3">
            <SelectInput label="Comment Status" value={commentStatus} onChange={setCommentStatus} options={COMMENT_STATUSES} />
            {commentStatus === SUB_REASON_STATUS && (
              <SelectInput label="Awaiting — what?" value={commentSubReason} onChange={setCommentSubReason} options={AWAITING_SUB_REASONS} />
            )}
          </div>
        </div>
      )}

      {/* ── Stage accordions ── */}
      <div className="space-y-2">
        {shows(1) && (
          <Accordion n={1} title="Assessor Allotment & File Upload" open={!!open[1]} onToggle={() => toggle(1)}>
            <DateInput label="Claim docs received" value={g('claim_docs_received')} onChange={(v) => set('claim_docs_received', v)} />
            <DateInput label="Assessor allotment date" value={g('assessor_allotment_date')} onChange={(v) => set('assessor_allotment_date', v)} />
            <SelectInput label="Assessor name" value={assessorSel} onChange={setAssessorSel} options={[...assessorNames, OTHER]} />
            {assessorSel === OTHER && (
              <TextInput label="Assessor name (other)" value={assessorOther} onChange={setAssessorOther} />
            )}
            <DateInput label="File uploaded to GT" value={g('file_uploaded_to_gt')} onChange={(v) => set('file_uploaded_to_gt', v)} />
            <TextInput label="GT number" value={g('gt_number')} onChange={(v) => set('gt_number', v)} />
            <CommentInput label="Comment" value={g('stage1_comment')} onChange={(v) => set('stage1_comment', v)} />
          </Accordion>
        )}

        {shows(2) && (
          <Accordion n={2} title="Physical Assessment" open={!!open[2]} onToggle={() => toggle(2)}>
            <SelectInput label="Distance" value={g('distance')} onChange={(v) => set('distance', v)} options={DISTANCE_OPTS} />
            <SelectInput label="Panel beater name" value={panelSel} onChange={setPanelSel} options={[...panelNames, OTHER]} />
            {panelSel === OTHER && (
              <TextInput label="Panel beater (other)" value={panelOther} onChange={setPanelOther} />
            )}
            <DateInput label="Physical assessment" value={g('physical_assessment')} onChange={(v) => set('physical_assessment', v)} />
            <CommentInput label="Comment" value={g('physical_assessment_comment')} onChange={(v) => set('physical_assessment_comment', v)} />
          </Accordion>
        )}

        {shows(3) && (
          <Accordion n={3} title="Quote Request & Finalisation" open={!!open[3]} onToggle={() => toggle(3)}>
            <DateInput label="Quote request date" value={g('quote_request_date')} onChange={(v) => set('quote_request_date', v)} />
            <SelectInput label="Under warranty" value={g('under_warranty')} onChange={(v) => set('under_warranty', v)} options={YES_NO} />
            <DateInput label="Quote finalisation" value={g('quote_finalisation')} onChange={(v) => set('quote_finalisation', v)} />
            <CommentInput label="Comment" value={g('quote_request_comment')} onChange={(v) => set('quote_request_comment', v)} />
          </Accordion>
        )}

        {shows(4) && (
          <Accordion n={4} title="Assessment Report" open={!!open[4]} onToggle={() => toggle(4)}>
            <DateInput label="Assessment report date" value={g('assessment_report_date')} onChange={(v) => set('assessment_report_date', v)} />
            <CommentInput label="Comment" value={g('assessment_report_comment')} onChange={(v) => set('assessment_report_comment', v)} />
          </Accordion>
        )}

        {shows(5) && (
          <Accordion n={5} title="PO Generation & Issue" open={!!open[5]} onToggle={() => toggle(5)}>
            <DateInput label="PO generation date" value={g('po_generation_date')} onChange={(v) => set('po_generation_date', v)} />
            <div className="md:col-span-2 xl:col-span-3">
              <span className={labelCls}>PO issue</span>
              <div className="flex flex-wrap gap-x-5 gap-y-2">
                {PO_ISSUE_OPTS.map((opt) => (
                  <label key={opt} className="flex items-center gap-2 text-sm text-ink cursor-pointer">
                    <input type="checkbox" checked={poIssue.includes(opt)} onChange={() => togglePo(opt)} className="rounded" />
                    {opt}
                  </label>
                ))}
              </div>
            </div>
            {poIssue.includes('Others') && (
              <TextInput label="PO issue (other)" value={g('po_issue_other')} onChange={(v) => set('po_issue_other', v)} />
            )}
            {poIssue.includes('Contract Pricing PO') && (
              <NumberInput label="Contract pricing value" value={g('contract_pricing_value')} onChange={(v) => set('contract_pricing_value', v)} />
            )}
            {poIssue.includes('CIL') && (
              <NumberInput label="CIL value" value={g('cil_value')} onChange={(v) => set('cil_value', v)} />
            )}
            <DateInput label="PO issue date" value={g('po_issue_date')} onChange={(v) => set('po_issue_date', v)} />
          </Accordion>
        )}

        {shows(6) && (
          <Accordion n={6} title="Parts Delivery & Confirmation" open={!!open[6]} onToggle={() => toggle(6)}>
            <DateInput label="Parts ETA" value={g('parts_eta')} onChange={(v) => set('parts_eta', v)} />
            <DateInput label="Parts delivery date" value={g('parts_delivery_date')} onChange={(v) => set('parts_delivery_date', v)} />
            <DateInput label="Confirmation date" value={g('confirmation_date')} onChange={(v) => set('confirmation_date', v)} />
            <SelectInput label="Mismatch reported" value={g('mismatch_reported')} onChange={(v) => set('mismatch_reported', v)} options={NO_YES} />
            <DateInput label="Replacement date" value={g('replacement_date')} onChange={(v) => set('replacement_date', v)} />
          </Accordion>
        )}

        {shows(7) && (
          <Accordion n={7} title="Job Completion" open={!!open[7]} onToggle={() => toggle(7)}>
            <DateInput label="Job end date" value={g('job_end_date')} onChange={(v) => set('job_end_date', v)} />
            <SelectInput label="Job end status" value={g('job_end_status')} onChange={(v) => set('job_end_status', v)} options={JOB_END_STATUS_OPTS} />
          </Accordion>
        )}
      </div>
    </div>
  )
})

export default ClaimCreateStageAccordions
