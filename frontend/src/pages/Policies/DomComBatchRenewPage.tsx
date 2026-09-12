import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'

/**
 * DOM/COM Batch Renew — Super Admin / Admin only.
 *
 * Manually runs the same backend renewal crons the scheduler runs:
 *   monthly                → DomComMonthlyAutoRenew:cron
 *   quarterly              → DomComQuaterlyAutoRenew:cron
 *   anniversary            → policy:renew-annual  (DOM/COM ANNIVERSARY-RENEW quote)
 *   monthly_specialist     → SpecialistMonthlyAutoRenew:cron   (products 16..22)
 *   quarterly_specialist   → SpecialistQuaterlyAutoRenew:cron  (products 16..22)
 *   anniversary_specialist → policy:renew-annual-specialist (specialist products
 *                            16/17/18/19/20/22 — Engineering, Marine, etc.)
 *   manual_specialist      → SpecialistManualAutoRenew:cron (specialist MANUAL
 *                            INPUT policies — no cadence, so the batch repeats
 *                            the period's own effective-from → to date difference)
 *
 * With a policy entered, the backend auto-detects the batch from the
 * policy's premium frequency (or uses the explicitly selected type) and
 * runs with --policy=<ref>, so ONLY that policy is processed — all the
 * cron's eligibility gates still apply. With no policy, it runs the FULL
 * batch for the selected type. Runs are logged with the cron's name, so
 * they show up in System → Cron Logs like any scheduled run.
 */

type BatchType =
  | ''
  | 'monthly'
  | 'quarterly'
  | 'anniversary'
  | 'monthly_specialist'
  | 'quarterly_specialist'
  | 'anniversary_specialist'
  | 'manual_specialist'

interface RunResult {
  message: string
  type: string
  command: string
  exit: number
  output: string
}

const TYPE_LABELS: Record<Exclude<BatchType, ''>, string> = {
  monthly: 'Monthly renew — DOM/COM (DomComMonthlyAutoRenew:cron)',
  quarterly: 'Quarterly renew — DOM/COM (DomComQuaterlyAutoRenew:cron)',
  anniversary: 'Anniversary quote — DOM/COM (policy:renew-annual)',
  monthly_specialist: 'Monthly renew — Specialist (SpecialistMonthlyAutoRenew:cron)',
  quarterly_specialist: 'Quarterly renew — Specialist (SpecialistQuaterlyAutoRenew:cron)',
  anniversary_specialist: 'Anniversary quote — Specialist (policy:renew-annual-specialist)',
  manual_specialist: 'Manual input renew — Specialist (SpecialistManualAutoRenew:cron)',
}

export default function DomComBatchRenewPage() {
  const [policy, setPolicy] = useState('')
  const [type, setType] = useState<BatchType>('')
  const [running, setRunning] = useState(false)
  const [result, setResult] = useState<RunResult | null>(null)
  const [error, setError] = useState('')

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

  const fullBatch = policy.trim() === ''

  async function run() {
    const what = fullBatch
      ? `Run the FULL ${type || '?'} batch for ALL eligible DOM/COM policies?`
      : `Run a batch renew for policy ${policy.trim()}${type ? ` (${type})` : ' (auto-detect frequency)'}?`
    if (!confirm(what)) return

    setRunning(true)
    setResult(null)
    setError('')
    try {
      const res = await apiClient.post('/dom-com-batch-renew/run', {
        policy: policy.trim() || undefined,
        type: type || undefined,
      })
      setResult(res.data)
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Batch renew failed')
    }
    setRunning(false)
  }

  if (!allowed) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 text-red-700 rounded p-4 text-sm">
          Only Admin / Super Admin can access DOM/COM Batch Renew.
        </div>
      </div>
    )
  }

  return (
    <div className="p-6 max-w-3xl">
      <h1 className="text-xl font-bold text-gray-800 mb-1">DOM/COM Batch Renew</h1>
      <p className="text-sm text-gray-500 mb-6">
        Manually run the DOM/COM renewal crons. Enter a policy to renew just that policy
        (frequency auto-detected: monthly / quarterly / anniversary), or leave it empty to run
        the full batch for the selected type. Output is logged like a scheduled cron run — see{' '}
        <Link to="/system/cron-logs" className="text-blue-600 hover:underline">Cron Logs</Link>.
      </p>

      <div className="bg-white rounded-lg shadow p-5 space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Policy number or ID <span className="text-gray-400">(optional — empty = full batch)</span>
          </label>
          <input
            type="text"
            value={policy}
            onChange={(e) => setPolicy(e.target.value)}
            placeholder="e.g. COMG2025205882"
            className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Batch type</label>
          <select
            value={type}
            onChange={(e) => setType(e.target.value as BatchType)}
            className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">{fullBatch ? '— select a batch type —' : 'Auto-detect from policy frequency'}</option>
            <option value="monthly">{TYPE_LABELS.monthly}</option>
            <option value="quarterly">{TYPE_LABELS.quarterly}</option>
            <option value="anniversary">{TYPE_LABELS.anniversary}</option>
            <option value="monthly_specialist">{TYPE_LABELS.monthly_specialist}</option>
            <option value="quarterly_specialist">{TYPE_LABELS.quarterly_specialist}</option>
            <option value="anniversary_specialist">{TYPE_LABELS.anniversary_specialist}</option>
            <option value="manual_specialist">{TYPE_LABELS.manual_specialist}</option>
          </select>
        </div>

        {fullBatch && (
          <div className="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-xs">
            ⚠ No policy entered — this will process <b>every eligible DOM/COM policy</b> in the
            selected batch, exactly like the scheduled cron. It can take several minutes.
          </div>
        )}

        <button
          onClick={run}
          disabled={running || (fullBatch && !type)}
          className="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50"
        >
          {running ? 'Running… (do not close this page)' : 'Run Batch Renew'}
        </button>
      </div>

      {error && (
        <div className="mt-4 bg-red-50 border border-red-200 text-red-700 rounded p-3 text-sm">{error}</div>
      )}

      {result && (
        <div className="mt-4 bg-white rounded-lg shadow p-5">
          <div className="text-sm text-green-700 font-medium mb-1">{result.message}</div>
          <div className="text-xs text-gray-500 mb-3 font-mono">
            php artisan {result.command} (exit {result.exit})
          </div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Command output</label>
          <pre className="bg-gray-900 text-green-300 text-xs rounded p-3 overflow-auto max-h-96 whitespace-pre-wrap">
            {result.output?.trim() || '(no console output — check Cron Logs for the detailed per-policy log lines)'}
          </pre>
        </div>
      )}
    </div>
  )
}
