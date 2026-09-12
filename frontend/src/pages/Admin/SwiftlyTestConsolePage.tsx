import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  useTestIntegrationConnection,
  useSubmitTestInvoice,
  useIntegrationWebhooks,
} from '../../hooks/useIntegrations'

const SLUG = 'swiftly'

// ─── helpers ────────────────────────────────────────────────────────────────

function newInvoiceId(): string {
  const d = new Date()
  const p = (n: number) => String(n).padStart(2, '0')
  return `TEST-${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`
}

function defaultDue(): string {
  const d = new Date()
  d.setDate(d.getDate() + 60)
  return d.toISOString().slice(0, 10)
}

function fmtTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  return isNaN(d.getTime()) ? iso : d.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
}

function Json({ value }: { value: unknown }) {
  return (
    <pre className="text-xs bg-gray-900 text-gray-100 rounded p-3 overflow-x-auto whitespace-pre-wrap break-words max-h-96 overflow-y-auto">
      {value === undefined ? '—' : JSON.stringify(value, null, 2)}
    </pre>
  )
}

const inputCls =
  'w-full text-sm px-2.5 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-navy/40 focus:border-brand-navy'

// ─── page ─────────────────────────────────────────────────────────────────────

export default function SwiftlyTestConsolePage() {
  const conn = useTestIntegrationConnection()
  const send = useSubmitTestInvoice()
  const webhooks = useIntegrationWebhooks(SLUG)

  const [invoiceId, setInvoiceId] = useState(newInvoiceId())
  const [supplierId, setSupplierId] = useState('')
  const [amount, setAmount] = useState('50000')
  const [currency, setCurrency] = useState('BWP')
  const [dueAt, setDueAt] = useState(defaultDue())
  const [percentage, setPercentage] = useState('')
  const [autoEarly, setAutoEarly] = useState(true)
  const [force, setForce] = useState(false)

  const suppliers = useMemo(() => {
    const list = conn.data?.ok ? conn.data.suppliers : undefined
    return Array.isArray(list) ? (list as Record<string, unknown>[]) : []
  }, [conn.data])

  const result = send.data

  const doSend = () => {
    send.mutate({
      slug: SLUG,
      data: {
        invoice_id: invoiceId.trim() || undefined,
        supplier_id: supplierId.trim(),
        amount: Number(amount) || undefined,
        currency: currency.trim() || undefined,
        due_at: dueAt || undefined,
        percentage_requested: percentage.trim() ? Number(percentage) : undefined,
        auto_request_early_payment: autoEarly,
        force,
      },
    }, {
      onSettled: () => webhooks.refetch(),
    })
  }

  return (
    <div className="p-4 sm:p-6 max-w-6xl">
      <div className="mb-4">
        <Link to="/admin/integrations" className="text-xs text-brand-navy hover:underline">← Integrations</Link>
        <h1 className="mt-1 text-2xl font-bold text-gray-800">Swiftly Test Console</h1>
        <p className="mt-1 text-sm text-gray-500">
          Build and fire invoice requests to Swiftly (staging), see the raw response, and watch the early-payment webhooks land — for driving UAT with the partner.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* ── Request builder ─────────────────────────────── */}
        <div className="bg-white shadow rounded-lg p-5">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-semibold text-gray-800">Request</h2>
            <button
              type="button"
              onClick={() => conn.mutate(SLUG)}
              disabled={conn.isPending}
              className="text-xs px-2.5 py-1 rounded border border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white transition disabled:opacity-50 cursor-pointer"
            >
              {conn.isPending ? 'Loading…' : 'Load suppliers'}
            </button>
          </div>

          <div className="space-y-3">
            {/* Invoice ID */}
            <div>
              <label htmlFor="inv" className="block text-[11px] text-gray-500 mb-1">Invoice ID <span className="text-gray-400">(reuse to test duplicate / retry)</span></label>
              <div className="flex gap-2">
                <input id="inv" className={inputCls + ' font-mono'} value={invoiceId} onChange={(e) => setInvoiceId(e.target.value)} />
                <button type="button" onClick={() => setInvoiceId(newInvoiceId())} className="text-xs px-2.5 py-1.5 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 whitespace-nowrap cursor-pointer">New</button>
              </div>
            </div>

            {/* Supplier */}
            <div>
              <label htmlFor="sup" className="block text-[11px] text-gray-500 mb-1">Supplier ID</label>
              <input id="sup" className={inputCls + ' font-mono'} value={supplierId} onChange={(e) => setSupplierId(e.target.value)} placeholder="paste, or pick below" />
              {suppliers.length > 0 && (
                <select
                  className={inputCls + ' mt-2'}
                  value=""
                  onChange={(e) => e.target.value && setSupplierId(e.target.value)}
                >
                  <option value="">Pick from {suppliers.length} suppliers…</option>
                  {suppliers.map((s, i) => {
                    const id = String(s.supplier_id ?? s.id ?? s.supplierId ?? '')
                    const name = String(s.name ?? s.business_name ?? s.businessName ?? '')
                    return <option key={i} value={id}>{id}{name ? ` — ${name}` : ''}</option>
                  })}
                </select>
              )}
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label htmlFor="amt" className="block text-[11px] text-gray-500 mb-1">Amount (major units, e.g. Pula)</label>
                <input id="amt" type="number" min={1} className={inputCls} value={amount} onChange={(e) => setAmount(e.target.value)} />
                <p className="mt-1 text-[10px] text-gray-400">
                  Sends <span className="font-mono">{((Number(amount) || 0) * 100).toLocaleString()}</span> minor units (×100)
                </p>
              </div>
              <div>
                <label htmlFor="cur" className="block text-[11px] text-gray-500 mb-1">Currency</label>
                <input id="cur" className={inputCls} value={currency} onChange={(e) => setCurrency(e.target.value)} />
              </div>
              <div>
                <label htmlFor="due" className="block text-[11px] text-gray-500 mb-1">Maturity date</label>
                <input id="due" type="date" className={inputCls} value={dueAt} onChange={(e) => setDueAt(e.target.value)} />
              </div>
              <div>
                <label htmlFor="pct" className="block text-[11px] text-gray-500 mb-1">% requested <span className="text-gray-400">(blank = default)</span></label>
                <input id="pct" type="number" min={0} max={100} step="0.1" className={inputCls} value={percentage} onChange={(e) => setPercentage(e.target.value)} placeholder="e.g. 50" />
              </div>
            </div>

            <label className="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none w-fit">
              <input type="checkbox" checked={autoEarly} onChange={(e) => setAutoEarly(e.target.checked)} className="h-3.5 w-3.5 rounded border-gray-300 text-brand-navy focus:ring-brand-navy/40" />
              Auto-request early payment <span className="text-gray-400">(fires the webhook back)</span>
            </label>
            <label className="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none w-fit">
              <input type="checkbox" checked={force} onChange={(e) => setForce(e.target.checked)} className="h-3.5 w-3.5 rounded border-gray-300 text-brand-navy focus:ring-brand-navy/40" />
              Force send <span className="text-gray-400">(skip our local dedupe → see Swiftly's duplicate error)</span>
            </label>

            <button
              type="button"
              onClick={doSend}
              disabled={send.isPending || !supplierId.trim()}
              className="mt-1 text-sm px-4 py-2 rounded bg-brand-navy text-white hover:bg-brand-navy/90 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
              {send.isPending ? 'Sending…' : 'Send invoice'}
            </button>
          </div>
        </div>

        {/* ── Response ────────────────────────────────────── */}
        <div className="bg-white shadow rounded-lg p-5">
          <div className="flex items-center gap-2 mb-3">
            <h2 className="text-sm font-semibold text-gray-800">Response</h2>
            {result && (
              <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${result.ok ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`}>
                {result.ok ? 'OK' : 'Failed'}{result.http_status ? ` · HTTP ${result.http_status}` : ''}
              </span>
            )}
          </div>

          {send.isError && <p className="text-xs text-red-600 mb-2" role="alert">Request failed — please try again.</p>}
          {!result && !send.isPending && <p className="text-xs text-gray-400">Send a request to see the response.</p>}
          {result && !result.ok && result.error && (
            <p className="text-xs text-red-600 mb-2" role="alert">{result.error}</p>
          )}

          {result && (
            <div className="space-y-3">
              <div>
                <p className="text-[11px] text-gray-500 mb-1">Request sent</p>
                <Json value={result.request} />
              </div>
              <div>
                <p className="text-[11px] text-gray-500 mb-1">Swiftly response</p>
                <Json value={result.response ?? result.raw_preview} />
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── Webhooks ──────────────────────────────────────── */}
      <div className="bg-white shadow rounded-lg p-5 mt-4">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-semibold text-gray-800">
            Received webhooks <span className="text-gray-400 font-normal">(auto-refreshing)</span>
          </h2>
          <button
            type="button"
            onClick={() => webhooks.refetch()}
            disabled={webhooks.isFetching}
            className="text-xs px-2.5 py-1 rounded border border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white transition disabled:opacity-50 cursor-pointer"
          >
            {webhooks.isFetching ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>

        {webhooks.isError && <p className="text-xs text-red-600">Could not load webhooks.</p>}
        {webhooks.data && webhooks.data.data.length === 0 && (
          <p className="text-xs text-gray-400">No webhooks received yet.</p>
        )}

        <div className="space-y-2">
          {webhooks.data?.data.map((w) => (
            <details key={w.id} className="border border-gray-100 rounded" open={result?.invoice_id != null && w.invoice_id === result.invoice_id}>
              <summary className="cursor-pointer list-none px-3 py-2 flex items-center gap-2 flex-wrap text-xs hover:bg-gray-50">
                <span className="font-mono text-gray-700">{w.invoice_id ?? `#${w.id}`}</span>
                <span className="text-gray-500">{w.event_type}</span>
                {w.signature_valid
                  ? <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-green-100 text-green-700">✓ signed</span>
                  : <span className="inline-flex items-center px-1.5 py-0.5 rounded bg-red-100 text-red-600">unsigned</span>}
                <span className="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{w.status}</span>
                <span className="text-gray-400 ml-auto">{fmtTime(w.received_at)}</span>
              </summary>
              <div className="px-3 pb-3">
                <Json value={w.payload} />
              </div>
            </details>
          ))}
        </div>
      </div>
    </div>
  )
}
