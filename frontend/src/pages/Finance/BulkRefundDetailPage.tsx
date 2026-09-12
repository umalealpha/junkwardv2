import { Link, useParams } from 'react-router-dom'
import { useBulkBatch, useRunBulkBatch } from '../../hooks/useRefunds'
import { bulkBatchCsvUrl } from '../../api/refunds'

/**
 * Detail page for one bulk refund batch. Polls every 3s while status is
 * processing so operators can watch counters change live.
 */
export default function BulkRefundDetailPage() {
  const { id } = useParams<{ id: string }>()
  const batchId = id ? Number(id) : null
  const { data, isLoading, error } = useBulkBatch(batchId, /* pollMs */ 3000)
  const run = useRunBulkBatch()

  if (isLoading) return <div className="p-6 text-gray-500">Loading batch…</div>
  if (error || !data) return <div className="p-6 text-red-600">Failed to load batch {batchId}</div>

  const { batch, rows } = data
  const canRun = ['queued', 'draft', 'failed'].includes(batch.status) && batch.pending_count > 0
  const refundedPct = batch.total_amount ? Math.round((Number(batch.refunded_amount) / Number(batch.total_amount)) * 100) : 0

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <Link to="/finance/bulk-refunds" className="text-sm text-indigo-600 hover:text-indigo-800">← Back to batches</Link>
          <h1 className="text-xl font-bold text-gray-900">Bulk refund #{batch.id}</h1>
          <p className="text-sm text-gray-500">{batch.name} · {batch.reason_code || '—'}</p>
        </div>
        <div className="flex gap-2">
          <a
            href={bulkBatchCsvUrl(batch.id)}
            className="px-3 py-2 text-sm border rounded hover:bg-gray-50"
            target="_blank" rel="noreferrer"
          >Download CSV</a>
          {canRun && (
            <button
              disabled={run.isPending}
              onClick={() => run.mutate(batch.id)}
              className="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
            >
              {run.isPending ? 'Queueing…' : (batch.status === 'failed' ? 'Retry failed rows' : 'Run batch now')}
            </button>
          )}
        </div>
      </div>

      {/* Summary */}
      <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-4 grid grid-cols-5 gap-4 text-sm">
        <Counter label="Status" value={batch.status} highlight />
        <Counter label="Total rows" value={batch.total_count} />
        <Counter label="Succeeded" value={batch.success_count} tone="green" />
        <Counter label="Failed"    value={batch.failed_count}  tone="red" />
        <Counter label="Pending"   value={batch.pending_count} tone="amber" />
      </div>
      <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-4 text-sm">
        <div className="flex items-center justify-between mb-2">
          <div>
            <span className="text-gray-500">Refunded / Total:</span>{' '}
            <strong>P{Number(batch.refunded_amount ?? 0).toFixed(2)}</strong> / P{Number(batch.total_amount ?? 0).toFixed(2)}
          </div>
          <span className="text-gray-500">{refundedPct}%</span>
        </div>
        <div className="h-2 bg-gray-100 rounded overflow-hidden">
          <div className="h-2 bg-green-500 transition-all" style={{ width: `${refundedPct}%` }} />
        </div>
      </div>

      {/* Row table */}
      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 border-b">
            <tr className="text-left text-xs text-gray-600">
              <th className="px-3 py-2">#</th>
              <th className="px-3 py-2">Policy</th>
              <th className="px-3 py-2">Customer</th>
              <th className="px-3 py-2 text-right">Amount</th>
              <th className="px-3 py-2">Status</th>
              <th className="px-3 py-2">DPO result</th>
              <th className="px-3 py-2">Ref</th>
              <th className="px-3 py-2">Completed</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {rows.map(r => (
              <tr key={r.id}>
                <td className="px-3 py-2 font-mono text-xs">{r.id}</td>
                <td className="px-3 py-2 font-mono text-xs">{r.policy_number ?? '—'}</td>
                <td className="px-3 py-2 text-xs">{r.customer_id ?? '—'}</td>
                <td className="px-3 py-2 text-right text-xs">P{Number(r.amount ?? 0).toFixed(2)}</td>
                <td className="px-3 py-2"><RefundStatus status={r.status} /></td>
                <td className="px-3 py-2 text-xs">
                  {r.dpo_result_code && <span className="font-mono mr-1">{r.dpo_result_code}</span>}
                  <span className="text-gray-600">{r.dpo_result_explanation ?? ''}</span>
                </td>
                <td className="px-3 py-2 font-mono text-xs">{r.dpo_refund_reference ?? '—'}</td>
                <td className="px-3 py-2 text-xs text-gray-500">{r.completed_at?.slice(0, 16).replace('T', ' ') ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function Counter({ label, value, tone, highlight }: { label: string; value: string | number; tone?: 'green' | 'red' | 'amber'; highlight?: boolean }) {
  const toneCls = tone === 'green' ? 'text-green-700' : tone === 'red' ? 'text-red-700' : tone === 'amber' ? 'text-amber-700' : 'text-gray-900'
  return (
    <div>
      <div className="text-xs text-gray-500">{label}</div>
      <div className={`text-xl font-semibold ${toneCls} ${highlight ? 'capitalize' : ''}`}>{value}</div>
    </div>
  )
}

function RefundStatus({ status }: { status: string }) {
  const map: Record<string, string> = {
    pending:   'bg-gray-100 text-gray-600',
    submitted: 'bg-blue-100 text-blue-700 animate-pulse',
    succeeded: 'bg-green-100 text-green-700',
    failed:    'bg-red-100 text-red-700',
    cancelled: 'bg-gray-200 text-gray-600',
  }
  return <span className={`px-2 py-0.5 rounded text-xs font-medium ${map[status] ?? 'bg-gray-100'}`}>{status}</span>
}
