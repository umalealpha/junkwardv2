import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'

/**
 * Refresh Endorsement Range — Super Admin / Admin only.
 *
 * Runs the SAME rebuild engine as the per-policy "Refresh Endorsement" button
 * (EndorseRefreshRunner via the queued RefreshEndorseJob), but BOUNDED to a
 * span: it pushes the From action's coverage tree forward into every action up
 * to and including the To action, instead of the whole downstream tail.
 *
 * Use when only a specific stretch of batches went wrong and refreshing the
 * entire policy is unnecessary. The From action must be the earlier one (its
 * data flows forward) and must be ISSUED. The backend re-checks the role, the
 * ordering, and refuses if the range would overwrite a newer issued
 * endorsement (data-loss guard).
 */

interface CoverageOption {
  id: number
  coverage_id: number
  name: string
  risk_address: string | null
}

interface ActionSummary {
  id: number
  policy_id: number
  policy_number: string | null
  transaction_type: string
  status: string
  effective_from: string
  effective_to: string
  coverages?: CoverageOption[]
}

type RefreshMode =
  | 'rebuild'
  | 'fill_missing'
  | 'fill_missing_reprice'
  | 'coverage_forward'
  | 'coverage_drop'
  | 'fill_missing_backward'

interface RefreshStatus {
  status: 'idle' | 'queued' | 'running' | 'completed' | 'failed'
  message?: string
  done?: number
  total?: number
}

export default function RefreshEndorseRangePage() {
  const [fromId, setFromId] = useState('')
  const [toId, setToId] = useState('')
  const [fromInfo, setFromInfo] = useState<ActionSummary | null>(null)
  const [toInfo, setToInfo] = useState<ActionSummary | null>(null)
  const [looking, setLooking] = useState(false)
  const [running, setRunning] = useState(false)
  const [status, setStatus] = useState<RefreshStatus | null>(null)
  const [error, setError] = useState('')
  // Coverage-selective FORWARD / DROP — coverage_ids picked from the From action.
  const [selectedCoverageIds, setSelectedCoverageIds] = useState<number[]>([])

  // Same role gate the sidebar uses — backend re-checks on every call.
  const allowed = useMemo(() => {
    try {
      const roles = JSON.parse(localStorage.getItem('user_roles') || '[]') as string[]
      return roles.some((r) => {
        const s = String(r).toLowerCase()
        return s === 'admin' || (s.includes('super') && s.includes('admin'))
      })
    } catch {
      return false
    }
  }, [])

  async function lookupOne(id: string): Promise<ActionSummary | null> {
    const n = parseInt(id, 10)
    if (!n) return null
    const res = await apiClient.get(`/policies/action-lookup/${n}`)
    return res.data as ActionSummary
  }

  async function lookup() {
    setError('')
    setStatus(null)
    setFromInfo(null)
    setToInfo(null)
    setSelectedCoverageIds([])
    if (!fromId.trim() || !toId.trim()) {
      setError('Enter both a From and a To action id.')
      return
    }
    setLooking(true)
    try {
      const [f, t] = await Promise.all([lookupOne(fromId), lookupOne(toId)])
      setFromInfo(f)
      setToInfo(t)
      if (f && t && f.policy_id !== t.policy_id) {
        setError('From and To actions belong to different policies.')
      }
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Lookup failed')
    }
    setLooking(false)
  }

  /** Poll GET refresh-endorse/status (written by RefreshEndorseJob) to finish. */
  async function pollStatus(policyId: number) {
    const started = Date.now()
    const deadline = started + 15 * 60 * 1000
    // Backend spawns a detached worker per click (status flips to 'running' in
    // seconds); if that spawn failed, this poll is itself the retry — the status
    // endpoint re-kicks a stalled worker (~45s apart, 3 attempts) and parks the
    // run as 'failed' by ~135s. Allow for that before declaring failure.
    const noWorkerAfter = started + 150 * 1000
    while (Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, 3000))
      let d: RefreshStatus
      try {
        const resp = await apiClient.get(`/policies/${policyId}/refresh-endorse/status`)
        d = resp.data
      } catch {
        continue
      }
      if (!d?.status) continue
      setStatus(d)
      if (d.status === 'completed') { setRunning(false); return }
      if (d.status === 'failed') { setRunning(false); return }
      if ((d.status === 'queued' || d.status === 'idle') && Date.now() > noWorkerAfter) {
        setStatus({ status: 'failed', message: 'Not started after 2 minutes — nothing was changed. Retry once; if it stalls again the background refresh worker is not starting (send this policy number to IT).' })
        setRunning(false)
        return
      }
    }
    setStatus({ status: 'failed', message: 'Taking longer than expected — it may still be running. Reload in a few minutes to check.' })
    setRunning(false)
  }

  async function run(mode: RefreshMode = 'rebuild', onlyTarget = false) {
    setError('')
    if (!fromInfo || !toInfo) {
      setError('Look up both actions first so you can confirm them.')
      return
    }
    if (fromInfo.policy_id !== toInfo.policy_id) {
      setError('From and To actions belong to different policies.')
      return
    }
    const isCoverageMode = mode === 'coverage_forward' || mode === 'coverage_drop'
    if (isCoverageMode && selectedCoverageIds.length === 0) {
      setError('Select at least one coverage first.')
      return
    }

    // Preview how many actions the span actually covers so the operator sees
    // the count BEFORE confirming. Targets are chosen by effective_from window
    // (not by id/type), so the number is often larger than the From→To pair
    // suggests — surfacing it up front prevents a surprise mass-refresh. Only
    // meaningful for a full-span run; a single-target "To Only" refresh touches
    // exactly one action, so the count is skipped there.
    // 'fill_missing_backward' runs BACKWARD and always acts on exactly one target,
    // so the forward-only preview endpoint neither applies nor is needed.
    let countLine = ''
    if (!onlyTarget && mode !== 'fill_missing_backward') {
      try {
        const prev = await apiClient.post('/policies/refresh-endorse-range/preview', {
          from_action_id: fromInfo.id,
          to_action_id: toInfo.id,
        })
        const affected = typeof prev.data?.count === 'number' ? prev.data.count : null
        if (affected !== null) {
          countLine = `\n\nThis will refresh ${affected} action row${affected === 1 ? '' : 's'} in the range.`
        }
      } catch {
        // Non-fatal — fall back to confirming without a count.
      }
    }

    const span = onlyTarget
      ? `policy ${fromInfo.policy_number ?? fromInfo.policy_id} from action ${fromInfo.id} ` +
        `(${fromInfo.transaction_type}, ${fromInfo.effective_from}) into ONLY action ${toInfo.id} ` +
        `(${toInfo.transaction_type}, ${toInfo.effective_from}) — the intermediate batches are left untouched?`
      : `policy ${fromInfo.policy_number ?? fromInfo.policy_id} from action ${fromInfo.id} ` +
        `(${fromInfo.transaction_type}, ${fromInfo.effective_from}) into every action up to ${toInfo.id} ` +
        `(${toInfo.transaction_type}, ${toInfo.effective_from})?`

    const coverageNames = (fromInfo.coverages ?? [])
      .filter((c) => selectedCoverageIds.includes(c.coverage_id))
      .map((c) => c.name + (c.risk_address ? ` — ${c.risk_address}` : ''))
      .join(', ')
    const coverageSpan =
      `the ${selectedCoverageIds.length} selected coverage(s) [${coverageNames}] across ` +
      `policy ${fromInfo.policy_number ?? fromInfo.policy_id} from action ${fromInfo.id} ` +
      `up to ${toInfo.id} (${toInfo.transaction_type}, ${toInfo.effective_from})?`

    let what: string
    if (mode === 'coverage_forward') {
      what =
        `Forward ${coverageSpan}\n\n` +
        `This ADDS the selected coverage(s) (and their vehicles/items/notes) into ` +
        `each target that lacks them. RENEW / ANNIVERSARY-RENEW targets are repriced ` +
        `and their invoice refreshed; endorse targets keep their sealed premium and ledger.`
    } else if (mode === 'coverage_drop') {
      what =
        `Drop ${coverageSpan}\n\n` +
        `This REMOVES the selected coverage(s) from each target (soft-deleted, audit ` +
        `preserved). RENEW / ANNIVERSARY-RENEW targets are repriced DOWN and their ` +
        `invoice reduced to the new total (no credit note); endorse targets keep their ` +
        `sealed premium and ledger.`
    } else if (mode === 'fill_missing') {
      what =
        `Fill missing coverages/vehicles from the From action into ${span}\n\n` +
        `This ONLY ADDS what a target is missing — it never deletes, keeps each ` +
        `target's own edits and later endorse batches. RENEW / ANNIVERSARY-RENEW targets ` +
        `ARE repriced and their invoice refreshed. An endorse target still at QUOTE / ` +
        `APPROVED is repriced too (nothing is billed on it yet); an ALREADY-ISSUED ` +
        `endorse keeps its billed premium and ledger.`
    } else if (mode === 'fill_missing_reprice') {
      what =
        `Fill missing coverages/vehicles AND REPRICE from the From action into ${span}

` +
        `Same append-only copy as Fill Missing — nothing is deleted and each target's own ` +
        `edits are kept — but an ALREADY-ISSUED endorse target is ALSO repriced: its ` +
        `pro-rata premium is recomputed off the new tree and its existing invoice is moved ` +
        `to that figure IN PLACE (invoice number kept, nothing discarded, GL legs rescaled).

` +
        `THIS MOVES BILLED MONEY on issued batches. Use it instead of un-issuing a batch to ` +
        `re-rate it, then check the ledger.`
    } else if (mode === 'fill_missing_backward') {
      what =
        `Backfill policy ${fromInfo.policy_number ?? fromInfo.policy_id} BACKWARD — copy what is ` +
        `missing from the LATER action ${fromInfo.id} (${fromInfo.transaction_type}, ` +
        `${fromInfo.effective_from}) into the EARLIER action ${toInfo.id} ` +
        `(${toInfo.transaction_type}, ${toInfo.effective_from})?\n\n` +
        `Append-only: it ADDS only what action ${toInfo.id} is missing. Everything already ` +
        `there is left exactly as it is — values are not overwritten, and anything CANCELLED ` +
        `on that action stays cancelled. No premium is re-rated, and no invoice or ledger ` +
        `row is written.`
    } else {
      what =
        `Rebuild ${span}\n\n` +
        `This HARD-DELETES and re-copies each target's coverage tree. It cannot be undone.`
    }
    // Append the affected-action count to whichever confirmation we built.
    what += countLine
    if (!confirm(what)) return

    const queuedMsg =
      mode === 'coverage_forward' ? 'Forward coverage(s) queued…'
      : mode === 'coverage_drop' ? 'Drop coverage(s) queued…'
      : mode === 'fill_missing' ? 'Fill-missing queued…'
      : mode === 'fill_missing_reprice' ? 'Fill-missing + reprice queued…'
      : mode === 'fill_missing_backward' ? 'Backfill (reverse) queued…'
      : onlyTarget ? 'Single-target refresh queued…'
      : 'Range refresh queued…'

    setRunning(true)
    setStatus({ status: 'queued', message: queuedMsg })
    try {
      const res = await apiClient.post('/policies/refresh-endorse-range', {
        from_action_id: fromInfo.id,
        to_action_id: toInfo.id,
        mode,
        only_target: onlyTarget,
        ...(isCoverageMode ? { coverage_ids: selectedCoverageIds } : {}),
      })
      const policyId = res.data?.policy_id ?? fromInfo.policy_id
      await pollStatus(policyId)
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Refresh failed')
      setRunning(false)
      setStatus(null)
    }
  }

  function toggleCoverage(coverageId: number) {
    setSelectedCoverageIds((prev) =>
      prev.includes(coverageId) ? prev.filter((c) => c !== coverageId) : [...prev, coverageId],
    )
  }

  if (!allowed) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 text-red-700 rounded p-4 text-sm">
          Only Admin / Super Admin can access Refresh Endorsement Range.
        </div>
      </div>
    )
  }

  const samePolicy = fromInfo && toInfo && fromInfo.policy_id === toInfo.policy_id
  const canRun = !!samePolicy && !running

  return (
    <div className="p-6 max-w-3xl">
      <h1 className="text-xl font-bold text-gray-800 mb-1">Refresh Endorsement Range</h1>
      <p className="text-sm text-gray-500 mb-6">
        Push the From action's coverage tree forward into every action up to and including the To action —
        a bounded version of the per-policy{' '}
        <span className="font-medium">Refresh Endorsement</span> button, for when only a specific span of
        batches needs rebuilding. The From action must be the earlier one and must be ISSUED — except for{' '}
        <span className="font-medium">Backfill To (reverse)</span>, where From is the LATER known-good action
        and To is the EARLIER one being topped up (append-only, no money moves). Runs in the
        background on the queue — see{' '}
        <Link to="/system/cron-logs" className="text-blue-600 hover:underline">Application Logs</Link>.
      </p>

      <div className="bg-white rounded-lg shadow p-5 space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">From Action ID <span className="text-gray-400">(source)</span></label>
            <input
              type="number"
              value={fromId}
              onChange={(e) => { setFromId(e.target.value); setFromInfo(null) }}
              placeholder="e.g. 120345"
              className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">To Action ID <span className="text-gray-400">(last target)</span></label>
            <input
              type="number"
              value={toId}
              onChange={(e) => { setToId(e.target.value); setToInfo(null) }}
              placeholder="e.g. 120390"
              className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div className="flex gap-2">
          <button
            onClick={lookup}
            disabled={looking || running}
            className="px-3 py-2 border border-gray-300 text-sm rounded hover:bg-gray-50 disabled:opacity-50"
          >
            {looking ? 'Looking up…' : 'Look up actions'}
          </button>
          <button
            onClick={() => run('fill_missing')}
            disabled={!canRun}
            title="Only adds coverages/vehicles the target is missing from the From action. Never deletes. RENEW/ANNIVERSARY-RENEW targets are repriced + invoice refreshed; endorse targets stay sealed."
            className="px-4 py-2 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700 disabled:opacity-50"
          >
            {running ? 'Running…' : 'Fill Missing (keep edits)'}
          </button>
          <button
            onClick={() => run('fill_missing_reprice')}
            disabled={!canRun}
            title="Same append-only copy as Fill Missing, but an ALREADY-ISSUED endorse target is repriced too and its invoice moved in place. Use instead of un-issuing a batch to re-rate it — it moves billed money."
            className="px-4 py-2 bg-green-700 text-white rounded text-sm hover:bg-green-800 disabled:opacity-50"
          >
            {running ? 'Running…' : 'Fill Missing + Reprice'}
          </button>
          <button
            onClick={() => run('fill_missing_backward')}
            disabled={!canRun}
            title="REVERSE: copies what the EARLIER To action is missing from the LATER From action. Append-only — existing rows, edits and cancellations on the To action are untouched, and no premium / invoice / ledger is written."
            className="px-4 py-2 bg-amber-600 text-white rounded text-sm hover:bg-amber-700 disabled:opacity-50"
          >
            {running ? 'Running…' : 'Backfill To (reverse)'}
          </button>
          <button
            onClick={() => run('rebuild')}
            disabled={!canRun}
            className="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50"
          >
            {running ? 'Running…' : 'Refresh Range (rebuild)'}
          </button>
          <button
            onClick={() => run('rebuild', true)}
            disabled={!canRun}
            title="Rebuild ONLY the To action from the From action — the intermediate batches in the span are left untouched."
            className="px-4 py-2 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700 disabled:opacity-50"
          >
            {running ? 'Running…' : 'Refresh To Only'}
          </button>
        </div>

        {/* Preview of the two actions */}
        {(fromInfo || toInfo) && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <ActionCard label="From (source)" info={fromInfo} />
            <ActionCard label="To (last target)" info={toInfo} />
          </div>
        )}

        {/* Coverage-selective FORWARD / DROP — pick from the From action's coverages */}
        {samePolicy && (fromInfo?.coverages?.length ?? 0) > 0 && (
          <div className="border border-gray-200 rounded p-3 space-y-3">
            <div className="flex items-center justify-between">
              <div className="text-sm font-semibold text-gray-700">
                Coverage-selective actions
                <span className="ml-2 text-xs font-normal text-gray-400">
                  ({selectedCoverageIds.length} selected)
                </span>
              </div>
            </div>
            <div className="max-h-56 overflow-auto border border-gray-100 rounded divide-y divide-gray-100">
              {fromInfo!.coverages!.map((c) => (
                <label
                  key={c.id}
                  className="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    checked={selectedCoverageIds.includes(c.coverage_id)}
                    onChange={() => toggleCoverage(c.coverage_id)}
                    disabled={running}
                    className="h-3.5 w-3.5"
                  />
                  <span>
                    {c.name}
                    {c.risk_address ? <span className="text-gray-400"> — {c.risk_address}</span> : null}
                    <span className="text-gray-300"> · cov {c.coverage_id}</span>
                  </span>
                </label>
              ))}
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => run('coverage_forward')}
                disabled={!canRun || selectedCoverageIds.length === 0}
                title="Add the selected coverage(s) from the From action into every target in the span that lacks them. RENEW/ANNIVERSARY-RENEW targets are repriced + invoice refreshed; endorse targets stay sealed."
                className="px-4 py-2 bg-teal-600 text-white rounded text-sm hover:bg-teal-700 disabled:opacity-50"
              >
                {running ? 'Running…' : 'Forward Coverage(s)'}
              </button>
              <button
                onClick={() => run('coverage_drop')}
                disabled={!canRun || selectedCoverageIds.length === 0}
                title="Remove the selected coverage(s) from every target in the span (soft-deleted). RENEW/ANNIVERSARY-RENEW targets are repriced down + invoice reduced; endorse targets stay sealed."
                className="px-4 py-2 bg-rose-600 text-white rounded text-sm hover:bg-rose-700 disabled:opacity-50"
              >
                {running ? 'Running…' : 'Drop Coverage(s)'}
              </button>
            </div>
          </div>
        )}

        {samePolicy && (
          <div className="space-y-2">
            <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded p-3 text-xs">
              <span className="font-semibold">Fill Missing (keep edits)</span> — only ADDS coverages/vehicles
              the target is missing from the From action. Never deletes, keeps each target's own edits and any
              later endorse batches. RENEW / ANNIVERSARY-RENEW targets ARE repriced and their invoice value
              refreshed to include the added coverages; endorse (and other) targets keep their sealed pro-rata
              premium and ledger. Use this to pull renew data into a later endorsement.
            </div>
            <div className="bg-green-50 border border-green-300 text-green-900 rounded p-3 text-xs">
              <span className="font-semibold">Fill Missing + Reprice</span> — identical append-only copy,
              but an ALREADY-ISSUED endorse target is repriced as well: its pro-rata premium is recomputed
              off the new tree and its existing invoice is moved to that figure in place (invoice number
              kept, nothing discarded, GL legs rescaled with it). Use this instead of UN-ISSUING a batch to
              re-rate it — un-issue zeroes the policy status and re-stamps the policy dates on re-issue.
              It moves billed money, so check the ledger afterwards.
            </div>
            <div className="bg-teal-50 border border-teal-200 text-teal-800 rounded p-3 text-xs">
              <span className="font-semibold">Forward Coverage(s)</span> — copies ONLY the coverage(s) you tick
              (with their vehicles, items and notes) from the From action into every target in the span that
              lacks them. Idempotent; every other coverage is left untouched. RENEW / ANNIVERSARY-RENEW targets
              are repriced and their invoice refreshed; endorse targets stay sealed.
            </div>
            <div className="bg-rose-50 border border-rose-200 text-rose-800 rounded p-3 text-xs">
              <span className="font-semibold">Drop Coverage(s)</span> — removes ONLY the ticked coverage(s) from
              every target in the span (soft-deleted, so the audit trail is preserved). RENEW / ANNIVERSARY-RENEW
              targets are repriced DOWN and their invoice reduced to the new total (no separate credit note);
              endorse targets keep their sealed premium and ledger.
            </div>
            <div className="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-xs">
              ⚠ <span className="font-semibold">Refresh Range (rebuild)</span> HARD-DELETES and re-copies each
              target action's coverage tree from the From action, and regenerates RENEW invoices in the span.
              Any manual edits on the target actions are discarded. Cannot be undone.
            </div>
            <div className="bg-indigo-50 border border-indigo-200 text-indigo-800 rounded p-3 text-xs">
              <span className="font-semibold">Refresh To Only</span> — same destructive rebuild as above, but applied
              to the To action ALONE. The From action's coverage tree is re-copied into just that one batch; the
              intermediate batches between From and To are left untouched. Use this when only a single downstream
              action needs the source pushed into it.
            </div>
          </div>
        )}
      </div>

      {error && (
        <div className="mt-4 bg-red-50 border border-red-200 text-red-700 rounded p-3 text-sm whitespace-pre-wrap">{error}</div>
      )}

      {status && (
        <div className={`mt-4 rounded p-3 text-sm border ${
          status.status === 'completed' ? 'bg-green-50 border-green-200 text-green-700'
          : status.status === 'failed' ? 'bg-red-50 border-red-200 text-red-700'
          : 'bg-blue-50 border-blue-200 text-blue-700'
        }`}>
          <div className="font-medium capitalize">{status.status}</div>
          <div>{status.message}</div>
          {typeof status.done === 'number' && typeof status.total === 'number' && status.total > 0 && (
            <div className="text-xs mt-1">Progress: {status.done} / {status.total} action(s)</div>
          )}
        </div>
      )}
    </div>
  )
}

function ActionCard({ label, info }: { label: string; info: ActionSummary | null }) {
  return (
    <div className="border border-gray-200 rounded p-3 bg-gray-50">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1">{label}</div>
      {info ? (
        <div className="text-xs text-gray-700 space-y-0.5">
          <div><span className="text-gray-500">Policy:</span> {info.policy_number ?? info.policy_id}</div>
          <div><span className="text-gray-500">Type:</span> {info.transaction_type} <span className="text-gray-400">·</span> {info.status}</div>
          <div><span className="text-gray-500">Period:</span> {info.effective_from} → {info.effective_to}</div>
        </div>
      ) : (
        <div className="text-xs text-gray-400 italic">Not looked up.</div>
      )}
    </div>
  )
}
