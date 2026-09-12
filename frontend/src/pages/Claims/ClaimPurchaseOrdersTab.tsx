import { useClaimPurchaseOrders } from '../../hooks/useClaimPurchaseOrders'
import type { OmniPurchaseOrder } from '../../api/claimPurchaseOrders'

/**
 * PO-in-Graphite Phase 2 — the purchase orders Omni raised for THIS claim.
 *
 * Everything shown is LIVE from Omni through our server-side proxy. Nothing
 * here is stored in Graphite (CFO decision: never a second copy of the
 * accounting truth), so a cancelled PO can never linger looking payable.
 *
 * States, in order of honesty:
 *  - Omni unreachable → say so, never show stale or guessed data
 *  - no POs → a quiet empty state ("unknown reference" renders the same)
 *  - list → one row per PO; a claim can have SEVERAL
 */

/** Cancelled/rejected must be UNMISSABLE — nobody tells a repairer a
    cancelled payment is coming. */
function statusBadge(status: string | null) {
  const s = (status ?? '').toLowerCase()
  const dead = s.includes('cancel') || s.includes('reject')
  const good = s.includes('approve') || s.includes('paid') || s.includes('complete')
  const cls = dead
    ? 'bg-red-100 text-red-700 border border-red-300'
    : good
      ? 'bg-green-100 text-green-700 border border-green-200'
      : 'bg-amber-100 text-amber-700 border border-amber-200'
  return (
    <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wide ${cls}`}>
      {status || 'Unknown'}
    </span>
  )
}

function money(total: string | null, currency: string | null): string {
  if (!total) return '—'
  const n = Number(total)
  const amount = Number.isFinite(n)
    ? n.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : total
  return `${currency || 'BWP'} ${amount}`
}

function PoRow({ po }: { po: OmniPurchaseOrder }) {
  const s = (po.status ?? '').toLowerCase()
  const dead = s.includes('cancel') || s.includes('reject')
  return (
    <tr className={dead ? 'bg-red-50/50' : ''}>
      <td className={`px-4 py-3 font-mono text-xs ${dead ? 'line-through text-ink-faint' : ''}`}>{po.po_number || '—'}</td>
      <td className="px-4 py-3">{statusBadge(po.status)}</td>
      <td className={`px-4 py-3 text-right tabular-nums ${dead ? 'line-through text-ink-faint' : ''}`}>{money(po.total, po.currency)}</td>
      <td className="px-4 py-3 whitespace-nowrap">{po.date || '—'}</td>
      <td className="px-4 py-3">{po.supplier_name || '—'}</td>
      <td className="px-4 py-3 text-right">
        {po.deep_link ? (
          /* Same tab, deliberately — the shared Microsoft tenant lands the
             user already signed in on the PO detail screen. */
          <a
            href={po.deep_link}
            className="inline-block px-3 py-1.5 rounded-md bg-brand-navy text-white text-xs font-medium hover:opacity-90 transition"
          >
            Open in Omni
          </a>
        ) : (
          <span className="text-ink-faint text-xs">—</span>
        )}
      </td>
    </tr>
  )
}

export default function ClaimPurchaseOrdersTab({ claimId }: { claimId: number }) {
  const { data, isLoading, isError } = useClaimPurchaseOrders(claimId)

  if (isLoading) {
    return <div className="p-8 text-center text-ink-faint">Loading purchase orders from Omni…</div>
  }

  // 404 (flag off) or Omni down / proxy unconfigured — degrade honestly.
  if (isError || !data || !data.available) {
    return (
      <div className="p-6">
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          PO information is temporarily unavailable. The purchase orders live in Omni — please check there
          directly, or try again shortly. Nothing is wrong with this claim.
        </div>
      </div>
    )
  }

  if (data.purchase_orders.length === 0) {
    return (
      <div className="p-8 text-center text-ink-faint text-sm">
        No purchase orders are linked to this claim in Omni.
      </div>
    )
  }

  return (
    <div className="p-4">
      <div className="flex items-baseline justify-between mb-3 px-1">
        <h3 className="text-sm font-semibold">
          Purchase orders for <span className="font-mono">{data.claim_ref}</span>
        </h3>
        <span className="text-[11px] text-ink-faint">Live from Omni — not stored in Graphite</span>
      </div>
      <div className="overflow-x-auto rounded-lg border border-line">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-surface-2 text-left text-xs text-ink-faint uppercase tracking-wide">
              <th className="px-4 py-2 font-medium">PO Number</th>
              <th className="px-4 py-2 font-medium">Status</th>
              <th className="px-4 py-2 font-medium text-right">Total</th>
              <th className="px-4 py-2 font-medium">Date</th>
              <th className="px-4 py-2 font-medium">Supplier</th>
              <th className="px-4 py-2 font-medium text-right">&nbsp;</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {data.purchase_orders.map((po, i) => (
              <PoRow key={po.uuid ?? i} po={po} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
