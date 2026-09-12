import { useEffect, useMemo, useState } from 'react'
import {
  useClaimSla,
  useClaimSlaTimeline,
  useUpdateClaimSlaTimeline,
} from '../../hooks/useClaimsSla'
import {
  CLAIM_SLA_STATUS_META,
  CLAIM_STAGE_GROUPS,
  CLAIM_STAGE_FIELD_KEYS,
  type ClaimSlaStatus,
  type ClaimStageFields,
  type ClaimSlaTimelinePayload,
} from '../../api/claimsSla'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { useToast } from '../../components/common/Toast'
import { fmtDate } from '../../utils/format'
import RequestBackdateButton from '../../components/claims/RequestBackdateButton'
import { fetchAssessors } from '../../api/assessors'
import { useClaim } from '../../hooks/useClaims'
import apiClient from '../../api/client'
import { useQueryClient } from '@tanstack/react-query'

function StatusChip({ status }: { status: ClaimSlaStatus }) {
  const meta = CLAIM_SLA_STATUS_META[status]
  return (
    <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-medium ${meta.cls}`}>{meta.label}</span>
  )
}

/** Signed working-day variance → short human string. */
function variance(v: number): { text: string; cls: string } {
  if (v === 0) return { text: 'on due date', cls: 'text-ink-muted' }
  if (v > 0) return { text: `+${v}d late`, cls: 'text-status-danger-fg' }
  return { text: `${Math.abs(v)}d early`, cls: 'text-status-success-fg' }
}

/** Normalise a field value for change-detection (null/undefined ↔ ''). */
function norm(v: string | null | undefined): string {
  return v == null ? '' : v
}

interface Props {
  claimId: number
  /** True when the user may PATCH the timeline (recorder role + claim-edit). */
  editable: boolean
}

export default function ClaimSlaTab({ claimId, editable }: Props) {
  const { toast } = useToast()
  const sla = useClaimSla(claimId)
  const timeline = useClaimSlaTimeline(claimId)
  const save = useUpdateClaimSlaTimeline(claimId)

  const original: ClaimStageFields = useMemo(() => timeline.data?.fields ?? {}, [timeline.data])
  const [form, setForm] = useState<Record<string, string>>({})
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  // Claim Submission Date lives on the claim (new_claims.submission_date), NOT the
  // stage-timeline row. Relocated into Stage 5 here (claims-team v6) and persisted
  // via the claim update endpoint, separately from the stage fields.
  const claimQ = useClaim(claimId)
  const qc = useQueryClient()
  const submissionOriginal = norm(
    (claimQ.data as any)?.submission_date ? String((claimQ.data as any).submission_date).slice(0, 10) : '',
  )
  const [submissionDate, setSubmissionDate] = useState('')
  const [submissionSaving, setSubmissionSaving] = useState(false)
  useEffect(() => { setSubmissionDate(submissionOriginal) }, [submissionOriginal])

  // Master assessor list for the Stage-1 assessor dropdown (claims-team v6).
  const [assessorNames, setAssessorNames] = useState<string[]>([])
  useEffect(() => {
    let alive = true
    fetchAssessors({ per_page: 200 })
      .then((r) => alive && setAssessorNames(r.data.map((a) => a.name).filter(Boolean)))
      .catch(() => {})
    return () => { alive = false }
  }, [])

  // Seed the editable form from the loaded timeline (and re-seed after a save).
  useEffect(() => {
    if (!timeline.data) return
    const seed: Record<string, string> = {}
    for (const k of CLAIM_STAGE_FIELD_KEYS) seed[k] = norm(original[k])
    setForm(seed)
    setFieldErrors({})
  }, [timeline.data, original])

  const changedKeys = useMemo(
    () => CLAIM_STAGE_FIELD_KEYS.filter((k) => norm(form[k]) !== norm(original[k])),
    [form, original],
  )
  const submissionChanged = norm(submissionDate) !== submissionOriginal
  const dirty = changedKeys.length > 0 || submissionChanged

  // ── Loading / error / off states ──────────────────────────────────────
  const status = (sla.error as any)?.response?.status ?? (timeline.error as any)?.response?.status
  if (status === 404) {
    return <div className="py-6"><EmptyState compact title="Claims SLA is not enabled" description="This module is currently turned off." /></div>
  }
  if (status === 403) {
    return <div className="py-6"><EmptyState compact title="No access" description="You do not have permission to view the SLA timeline for this claim." /></div>
  }
  if (sla.isLoading || timeline.isLoading) {
    return <div className="py-10 flex justify-center"><LoadingSpinner /></div>
  }
  if (sla.isError && !sla.data) {
    return (
      <div className="py-6 text-center">
        <p className="text-status-danger-fg text-sm">Could not load the claim SLA.</p>
        <button onClick={() => { sla.refetch(); timeline.refetch() }} className="mt-3 text-sm text-primary underline">Try again</button>
      </div>
    )
  }

  async function handleSave() {
    if (!dirty) return
    setFieldErrors({})

    // 1) Claim Submission Date → the claim record (separate from the stage timeline).
    if (submissionChanged) {
      setSubmissionSaving(true)
      try {
        await apiClient.put(`/claims/${claimId}`, { submission_date: submissionDate || null })
        qc.invalidateQueries({ queryKey: ['claim', claimId] })
      } catch (err: any) {
        setSubmissionSaving(false)
        toast.error(err?.response?.data?.message || 'Failed to save the Claim Submission Date.')
        return
      }
      setSubmissionSaving(false)
    }

    // 2) Stage-timeline fields (only when any changed).
    if (changedKeys.length > 0) {
      const payload: ClaimSlaTimelinePayload = {}
      for (const k of changedKeys) {
        const v = form[k]
        payload[k] = v === '' ? null : v
      }
      try {
        const res = await save.mutateAsync(payload)
        toast.success(res.changed > 0 ? `${res.message} (${res.changed} field${res.changed === 1 ? '' : 's'})` : 'Saved.')
        return
      } catch (err: any) {
        const resp = err?.response
        if (resp?.status === 422 && resp.data?.errors) {
          const errs: Record<string, string> = {}
          for (const [k, msgs] of Object.entries(resp.data.errors as Record<string, string[]>)) {
            errs[k] = Array.isArray(msgs) ? msgs[0] : String(msgs)
          }
          setFieldErrors(errs)
          toast.error('Please fix the highlighted fields.')
        } else {
          toast.error(resp?.data?.message || 'Failed to save the stage timeline.')
        }
        return
      }
    }

    if (submissionChanged) toast.success('Saved.')
  }

  function handleReset() {
    const seed: Record<string, string> = {}
    for (const k of CLAIM_STAGE_FIELD_KEYS) seed[k] = norm(original[k])
    setForm(seed)
    setSubmissionDate(submissionOriginal)
    setFieldErrors({})
  }

  const e = sla.data

  return (
    <div className="space-y-5 pt-4">
      {/* ── SLA summary panel ── */}
      {e && (
        <section className="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
          <div className="bg-surface-2 px-5 py-3 border-b border-line flex items-center justify-between gap-2 flex-wrap">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">SLA — {e.class_label}{e.sub_type ? ` · ${e.sub_type}` : ''}</h2>
            <div className="flex items-center gap-2">
              <StatusChip status={e.overall_status} />
              {editable && <RequestBackdateButton claimId={claimId} />}
            </div>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2 px-5 py-4 text-sm">
            <div><span className="text-ink-faint">SLA start:</span> <span className="font-medium text-ink">{fmtDate(e.start_date)}</span></div>
            <div><span className="text-ink-faint">Working days:</span> <span className="font-medium text-ink">{e.total_working_days}</span></div>
            <div><span className="text-ink-faint">Overall due:</span> <span className="font-medium text-ink">{fmtDate(e.overall_due_date)}</span></div>
            <div><span className="text-ink-faint">Completed:</span> <span className="font-medium text-ink">{e.completed ? 'Yes' : 'No'}</span></div>
          </div>

          {/* Stage timeline */}
          <div className="px-5 pb-5">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-xs text-ink-muted uppercase">
                  <tr>
                    <th className="text-left py-1.5">Stage</th>
                    <th className="text-right">Due (wd)</th>
                    <th className="text-right">Due date</th>
                    <th className="text-right">Completed</th>
                    <th className="text-center">Status</th>
                    <th className="text-right">Variance</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {e.stages.length === 0 ? (
                    <tr><td colSpan={6} className="p-0"><EmptyState compact title="No stages configured" description="This claim class has no SLA stages in the matrix." /></td></tr>
                  ) : e.stages.map((st) => {
                    const v = variance(st.variance_working_days)
                    return (
                      <tr key={st.key}>
                        <td className="py-2 text-ink">{st.label}</td>
                        <td className="text-right tabular-nums text-ink-muted">{st.due_working_days}</td>
                        <td className="text-right tabular-nums text-ink">{fmtDate(st.due_date)}</td>
                        <td className="text-right tabular-nums text-ink">{st.completed_date ? fmtDate(st.completed_date) : <span className="text-ink-faint">—</span>}</td>
                        <td className="text-center"><StatusChip status={st.status} /></td>
                        <td className={`text-right tabular-nums ${v.cls}`}>{v.text}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      )}

      {/* ── Stage timeline editor ── */}
      <section className="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div className="bg-surface-2 px-5 py-3 border-b border-line flex items-center justify-between gap-2 flex-wrap">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Stage Timeline</h2>
          {editable ? (
            <div className="flex items-center gap-2">
              {dirty && (() => { const n = changedKeys.length + (submissionChanged ? 1 : 0); return (
                <span className="text-[11px] text-ink-faint">{n} unsaved change{n === 1 ? '' : 's'}</span>
              ) })()}
              <button
                onClick={handleReset}
                disabled={!dirty || save.isPending || submissionSaving}
                className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition disabled:opacity-50"
              >
                Reset
              </button>
              <button
                onClick={handleSave}
                disabled={!dirty || save.isPending || submissionSaving}
                className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 transition disabled:opacity-50"
              >
                {(save.isPending || submissionSaving) ? 'Saving…' : 'Save changes'}
              </button>
            </div>
          ) : (
            <span className="text-[11px] text-ink-faint">Read-only</span>
          )}
        </div>

        <div className="p-5 space-y-6">
          {CLAIM_STAGE_GROUPS.map((group) => (
            <div key={group.title}>
              <h3 className="text-xs font-semibold text-ink uppercase tracking-wide mb-2">{group.title}</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-5 gap-y-3">
                {/* Claim Submission Date — relocated here before the PO fields
                    (claims-team v6). Stored on the claim, not the stage row. */}
                {group.title.startsWith('Stage 5') && (
                  <div>
                    <label htmlFor="sla-claim-submission-date" className="block text-[11px] font-medium text-ink-muted mb-1">Claim submission date</label>
                    {editable ? (
                      <input
                        id="sla-claim-submission-date"
                        type="date"
                        value={submissionDate}
                        onChange={(ev) => setSubmissionDate(ev.target.value)}
                        className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
                      />
                    ) : (
                      <p className="text-sm text-ink min-h-[1.5rem]">{submissionDate ? fmtDate(submissionDate) : <span className="text-ink-faint">—</span>}</p>
                    )}
                  </div>
                )}
                {group.fields.map((f) => {
                  const val = form[f.key] ?? ''
                  const err = fieldErrors[f.key]
                  const commonLabel = (
                    <label htmlFor={`sla-${f.key}`} className="block text-[11px] font-medium text-ink-muted mb-1">{f.label}</label>
                  )
                  const inputBase = `w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border ${err ? 'border-status-danger-fg' : 'border-line'} focus:outline-none focus:ring-1 focus:ring-primary disabled:opacity-70 disabled:cursor-not-allowed`

                  // Read-only presentation for non-editors.
                  if (!editable) {
                    return (
                      <div key={f.key} className={f.kind === 'comment' ? 'xl:col-span-3 md:col-span-2' : ''}>
                        {commonLabel}
                        <p className="text-sm text-ink min-h-[1.5rem] whitespace-pre-wrap break-words">
                          {val ? (f.kind === 'date' ? fmtDate(val) : val) : <span className="text-ink-faint">—</span>}
                        </p>
                      </div>
                    )
                  }

                  return (
                    <div key={f.key} className={f.kind === 'comment' ? 'xl:col-span-3 md:col-span-2' : ''}>
                      {commonLabel}
                      {f.kind === 'comment' ? (
                        <textarea
                          id={`sla-${f.key}`}
                          value={val}
                          maxLength={2000}
                          rows={2}
                          onChange={(ev) => setForm((s) => ({ ...s, [f.key]: ev.target.value }))}
                          className={inputBase}
                        />
                      ) : f.kind === 'select' ? (
                        <select
                          id={`sla-${f.key}`}
                          value={val}
                          onChange={(ev) => setForm((s) => ({ ...s, [f.key]: ev.target.value }))}
                          className={inputBase}
                        >
                          <option value="">Select…</option>
                          {/* Master list (assessors) + any existing free-text value so nothing is lost. */}
                          {Array.from(new Set([
                            val,
                            ...(f.key === 'assessor_name' ? assessorNames : (f.options ?? [])),
                          ].filter(Boolean))).map((o) => (
                            <option key={o} value={o}>{o}</option>
                          ))}
                        </select>
                      ) : (
                        <input
                          id={`sla-${f.key}`}
                          type={f.kind === 'date' ? 'date' : 'text'}
                          value={val}
                          maxLength={f.kind === 'text' ? 255 : undefined}
                          onChange={(ev) => setForm((s) => ({ ...s, [f.key]: ev.target.value }))}
                          className={inputBase}
                        />
                      )}
                      {err && <p className="text-[11px] text-status-danger-fg mt-0.5">{err}</p>}
                    </div>
                  )
                })}
              </div>
            </div>
          ))}
          {timeline.data && !timeline.data.exists && (
            <p className="text-[11px] text-ink-faint">No stage timeline exists for this claim yet — saving any field creates one.</p>
          )}
        </div>
      </section>
    </div>
  )
}
