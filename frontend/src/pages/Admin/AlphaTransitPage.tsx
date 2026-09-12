import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  useAtcSummary, useAtcShipments, useAtcPayments, useAtcClaims, useAtcEvents,
} from '../../hooks/useAlphaTransit'
import type { AtcClaim, AtcEvent, AtcPayment, AtcShipment } from '../../api/alphaTransit'
import EmptyState from '../../components/common/EmptyState'

/**
 * Alpha Transit Cover — read-only ops view over the courier goods-in-transit
 * ingestion (transit.alphadirect.co.bw → webhook → atc_* tables). Corrections
 * happen on the ATC platform and re-sync via the webhook; this page never
 * mutates. The on/off toggle lives on Admin > Integrations.
 */

type Tab = 'shipments' | 'payments' | 'claims' | 'events'

const TABS: { key: Tab; label: string }[] = [
  { key: 'shipments', label: 'Shipments' },
  { key: 'payments', label: 'Remittances' },
  { key: 'claims', label: 'Claims' },
  { key: 'events', label: 'Event Log' },
]

function fmtPula(v: string | number | null | undefined): string {
  const n = typeof v === 'string' ? parseFloat(v) : v
  if (n == null || isNaN(n)) return '—'
  return 'P ' + n.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtTime(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
}

function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Status chips always pair color with a text label — never color alone.
const CHIP_TONES: Record<string, string> = {
  settled: 'bg-green-100 text-green-700',
  paid: 'bg-blue-100 text-blue-700',
  unpaid: 'bg-amber-100 text-amber-700',
  recorded: 'bg-green-100 text-green-700',
  partial: 'bg-amber-100 text-amber-700',
  processed: 'bg-green-100 text-green-700',
  duplicate: 'bg-surface-2 text-ink-muted',
  received: 'bg-blue-100 text-blue-700',
  failed: 'bg-red-100 text-red-700',
  open: 'bg-blue-100 text-blue-700',
  under_review: 'bg-blue-100 text-blue-700',
  info_required: 'bg-amber-100 text-amber-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  closed: 'bg-surface-2 text-ink-muted',
}

function Chip({ value }: { value: string | null | undefined }) {
  if (!value) return <span className="text-ink-faint">—</span>
  return (
    <span className={`px-2 py-0.5 text-xs rounded-full whitespace-nowrap ${CHIP_TONES[value] ?? 'bg-surface-2 text-ink-muted'}`}>
      {value.replace(/_/g, ' ')}
    </span>
  )
}

function Tile({ label, value, hint, tone }: { label: string; value: string | number; hint?: string; tone?: 'warn' }) {
  return (
    <div className={`rounded-lg ring-1 p-3 ${tone === 'warn' ? 'ring-amber-200 bg-amber-50' : 'ring-slate-200 bg-surface'}`}>
      <div className="text-xs uppercase tracking-wider text-slate-500">{label}</div>
      <div className="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{value}</div>
      {hint && <div className="text-xs text-slate-500 mt-1">{hint}</div>}
    </div>
  )
}

const TH = ({ children, right }: { children: React.ReactNode; right?: boolean }) => (
  <th className={`px-4 py-3 ${right ? 'text-right' : 'text-left'} text-xs font-medium text-ink-muted uppercase`}>{children}</th>
)

function Pager({ page, hasMore, onPage }: { page: number; hasMore: boolean; onPage: (p: number) => void }) {
  return (
    <div className="flex justify-between">
      <button disabled={page <= 1} onClick={() => onPage(page - 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button>
      <span className="text-sm text-ink-muted">Page {page}</span>
      <button disabled={!hasMore} onClick={() => onPage(page + 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button>
    </div>
  )
}

// ─── Tab tables ───────────────────────────────────────────────────────────────

function ShipmentsTab() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAtcShipments({
    page, search: search || undefined, payment_status: status || undefined,
  })
  const rows = data?.data ?? []

  return (
    <div className="space-y-4">
      <div className="flex gap-2">
        <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
          placeholder="Search policy no, sender, receiver, waybill…" className="px-3 py-1.5 border rounded-md text-sm w-72" />
        <select value={status} onChange={e => { setStatus(e.target.value); setPage(1) }} className="px-3 py-1.5 border rounded-md text-sm">
          <option value="">All payment statuses</option>
          <option value="unpaid">Unpaid</option>
          <option value="paid">Paid</option>
          <option value="settled">Settled</option>
        </select>
      </div>
      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface"><tr>
            <TH>Policy No</TH><TH>Courier</TH><TH>Sender → Receiver</TH><TH>Route</TH>
            <TH>Goods</TH><TH right>Declared</TH><TH right>Premium</TH><TH>Cover</TH><TH>Payment</TH>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {isLoading && <tr><td colSpan={9} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!isLoading && (isError || data?.error) && <tr><td colSpan={9} className="p-0"><EmptyState compact title="Couldn't load this data" description={data?.error ? "Alpha Transit tables are unavailable in this environment." : "The request failed — check the backend and retry."} /></td></tr>}
            {!isLoading && !isError && !data?.error && rows.length === 0 && <tr><td colSpan={9} className="p-0"><EmptyState compact title="No shipments yet" description="Ingested ATC policies will appear here." /></td></tr>}
            {rows.map((s: AtcShipment) => (
              <tr key={s.id} className="hover:bg-surface">
                <td className="px-4 py-3 font-medium whitespace-nowrap">{s.policy_number}</td>
                <td className="px-4 py-3">{s.company_code}</td>
                <td className="px-4 py-3 text-xs text-ink-muted">
                  <div className="font-medium text-ink">{s.sender_name}</div>
                  <div>→ {s.receiver_name || '—'}</div>
                </td>
                <td className="px-4 py-3 text-xs text-ink-muted whitespace-nowrap">{s.from_town || s.from_zone} → {s.to_town || s.to_zone}</td>
                <td className="px-4 py-3 text-xs text-ink-muted">
                  <span className="font-medium">{s.goods_category}</span>
                  {s.courier_waybill && <div className="text-ink-faint">{s.courier_waybill}</div>}
                </td>
                <td className="px-4 py-3 text-right tabular-nums whitespace-nowrap">{fmtPula(s.declared_value)}</td>
                <td className="px-4 py-3 text-right tabular-nums whitespace-nowrap">{fmtPula(s.premium)}</td>
                <td className="px-4 py-3 text-xs text-ink-muted whitespace-nowrap">{fmtDate(s.cover_start)} – {fmtDate(s.cover_end)}</td>
                <td className="px-4 py-3"><Chip value={s.payment_status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={page} hasMore={data?.meta?.has_more ?? false} onPage={setPage} />
    </div>
  )
}

function PaymentsTab() {
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAtcPayments({ page })
  const rows = data?.data ?? []

  return (
    <div className="space-y-4">
      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface"><tr>
            <TH>Courier</TH><TH>Month</TH><TH>Bank Reference</TH><TH right>Amount</TH>
            <TH right>Policies</TH><TH>Payment Date</TH><TH>Recorded By</TH><TH>Status</TH>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {isLoading && <tr><td colSpan={8} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!isLoading && (isError || data?.error) && <tr><td colSpan={8} className="p-0"><EmptyState compact title="Couldn't load this data" description={data?.error ? "Alpha Transit tables are unavailable in this environment." : "The request failed — check the backend and retry."} /></td></tr>}
            {!isLoading && !isError && !data?.error && rows.length === 0 && <tr><td colSpan={8} className="p-0"><EmptyState compact title="No remittances yet" description="Monthly courier remittances (net-7) will appear here." /></td></tr>}
            {rows.map((p: AtcPayment) => (
              <tr key={p.id} className="hover:bg-surface">
                <td className="px-4 py-3 font-medium">{p.company_code}</td>
                <td className="px-4 py-3 whitespace-nowrap">{p.reference_month || '—'}</td>
                <td className="px-4 py-3 text-xs text-ink-muted">{p.bank_reference || '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums whitespace-nowrap">{fmtPula(p.amount)}</td>
                <td className="px-4 py-3 text-right tabular-nums">{p.policies_count}</td>
                <td className="px-4 py-3 whitespace-nowrap">{fmtDate(p.payment_date)}</td>
                <td className="px-4 py-3 text-xs text-ink-muted">{p.recorded_by_email || '—'}</td>
                <td className="px-4 py-3"><Chip value={p.status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={page} hasMore={data?.meta?.has_more ?? false} onPage={setPage} />
    </div>
  )
}

function ClaimsTab() {
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAtcClaims({ page, search: search || undefined })
  const rows = data?.data ?? []

  return (
    <div className="space-y-4">
      <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
        placeholder="Search claim no, policy no, claimant…" className="px-3 py-1.5 border rounded-md text-sm w-72" />
      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface"><tr>
            <TH>Claim No</TH><TH>Policy No</TH><TH>Courier</TH><TH>Claimant</TH><TH>Type</TH>
            <TH right>Claimed</TH><TH right>Settled</TH><TH>Filed</TH><TH>Status</TH>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {isLoading && <tr><td colSpan={9} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!isLoading && (isError || data?.error) && <tr><td colSpan={9} className="p-0"><EmptyState compact title="Couldn't load this data" description={data?.error ? "Alpha Transit tables are unavailable in this environment." : "The request failed — check the backend and retry."} /></td></tr>}
            {!isLoading && !isError && !data?.error && rows.length === 0 && <tr><td colSpan={9} className="p-0"><EmptyState compact title="No claims yet" description="Claims filed on the ATC platform will appear here." /></td></tr>}
            {rows.map((c: AtcClaim) => (
              <tr key={c.id} className="hover:bg-surface">
                <td className="px-4 py-3 font-medium whitespace-nowrap">{c.claim_number}</td>
                <td className="px-4 py-3 whitespace-nowrap">{c.policy_number}</td>
                <td className="px-4 py-3">{c.company_code || '—'}</td>
                <td className="px-4 py-3 text-xs text-ink-muted">{c.claimant_name || '—'}</td>
                <td className="px-4 py-3 text-xs text-ink-muted">{c.incident_type || '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums whitespace-nowrap">{fmtPula(c.claim_amount)}</td>
                <td className="px-4 py-3 text-right tabular-nums whitespace-nowrap">{fmtPula(c.settled_amount)}</td>
                <td className="px-4 py-3 whitespace-nowrap text-xs text-ink-muted">{fmtTime(c.filed_at)}</td>
                <td className="px-4 py-3"><Chip value={c.status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={page} hasMore={data?.meta?.has_more ?? false} onPage={setPage} />
    </div>
  )
}

function EventsTab() {
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAtcEvents({ page, status: status || undefined })
  const rows = data?.data ?? []

  return (
    <div className="space-y-4">
      <select value={status} onChange={e => { setStatus(e.target.value); setPage(1) }} className="px-3 py-1.5 border rounded-md text-sm">
        <option value="">All statuses</option>
        <option value="processed">Processed</option>
        <option value="duplicate">Duplicate</option>
        <option value="failed">Failed</option>
        <option value="received">Received</option>
      </select>
      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface"><tr>
            <TH>Received</TH><TH>Event</TH><TH>Status</TH><TH>Entity</TH><TH right>Graphite ID</TH><TH>Error</TH>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {isLoading && <tr><td colSpan={6} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!isLoading && (isError || data?.error) && <tr><td colSpan={6} className="p-0"><EmptyState compact title="Couldn't load this data" description={data?.error ? "Alpha Transit tables are unavailable in this environment." : "The request failed — check the backend and retry."} /></td></tr>}
            {!isLoading && !isError && !data?.error && rows.length === 0 && <tr><td colSpan={6} className="p-0"><EmptyState compact title="No events yet" description="Inbound ATC webhook events will appear here." /></td></tr>}
            {rows.map((e: AtcEvent) => (
              <tr key={e.id} className="hover:bg-surface">
                <td className="px-4 py-3 whitespace-nowrap text-xs text-ink-muted">{fmtTime(e.received_at)}</td>
                <td className="px-4 py-3 font-mono text-xs">{e.event_type}</td>
                <td className="px-4 py-3"><Chip value={e.status} /></td>
                <td className="px-4 py-3 text-xs text-ink-muted">{e.entity_type || '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums">{e.graphite_id ?? '—'}</td>
                <td className="px-4 py-3 text-xs text-red-600 max-w-md truncate" title={e.error ?? undefined}>{e.error || ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={page} hasMore={data?.meta?.has_more ?? false} onPage={setPage} />
    </div>
  )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AlphaTransitPage() {
  const [tab, setTab] = useState<Tab>('shipments')
  const { data: summary } = useAtcSummary()

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold text-ink">Alpha Transit Cover</h1>
          <p className="text-sm text-ink-muted mt-0.5">
            Courier goods-in-transit — ingested from transit.alphadirect.co.bw. Read-only: corrections happen
            on the ATC platform and re-sync via the webhook.
          </p>
        </div>
        <div className="flex items-center gap-3">
          {summary && (
            <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${summary.enabled ? 'bg-green-100 text-green-700' : 'bg-surface-2 text-ink-muted'}`}>
              {summary.enabled ? '● Ingestion enabled' : '○ Ingestion off'}
            </span>
          )}
          <Link to="/admin/integrations" className="text-sm text-blue-600 hover:underline whitespace-nowrap">Manage toggle →</Link>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <Tile label="Shipments" value={summary?.shipments.total ?? '—'} hint={summary ? `${summary.shipments.settled} settled · ${summary.shipments.unpaid} unpaid` : undefined} />
        <Tile label="Premium Written" value={summary ? fmtPula(summary.shipments.premium) : '—'} />
        <Tile label="Sum Insured" value={summary ? fmtPula(summary.shipments.sum_insured) : '—'} />
        <Tile label="Remittances" value={summary?.payments.total ?? '—'} hint={summary ? fmtPula(summary.payments.amount) : undefined} />
        <Tile label="Open Claims" value={summary?.claims.open ?? '—'} hint={summary ? `${summary.claims.total} total` : undefined} />
        <Tile
          label="Failed Events"
          value={summary?.events.failed ?? '—'}
          tone={summary && summary.events.failed > 0 ? 'warn' : undefined}
          hint={summary?.events.last_received_at ? `last event ${fmtTime(summary.events.last_received_at)}` : undefined}
        />
      </div>

      <div className="border-b border-line">
        <nav className="flex gap-6">
          {TABS.map(t => (
            <button key={t.key} onClick={() => setTab(t.key)}
              className={`py-2 text-sm font-medium border-b-2 -mb-px ${
                tab === t.key ? 'border-blue-600 text-blue-700' : 'border-transparent text-ink-muted hover:text-ink'
              }`}>
              {t.label}
            </button>
          ))}
        </nav>
      </div>

      {tab === 'shipments' && <ShipmentsTab />}
      {tab === 'payments' && <PaymentsTab />}
      {tab === 'claims' && <ClaimsTab />}
      {tab === 'events' && <EventsTab />}
    </div>
  )
}
