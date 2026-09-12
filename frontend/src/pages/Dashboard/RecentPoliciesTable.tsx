import DualScrollTable from '../../components/common/DualScrollTable'
import { Link } from 'react-router-dom'
import { fmtDate } from '../../utils/format'
import StatusBadge from '../../components/common/StatusBadge'

interface RecentPolicy {
  id: number
  policyNumber: string
  status: number
  created_at: string
  firstName: string
  lastName: string
  product_name: string
}

// Map the numeric policy status to the semantic status string StatusBadge
// understands, so the pill gets the correct light/dark tokens automatically.
const STATUS_MAP: Record<number, string> = {
  1: 'active',
  2: 'cancelled',
  3: 'expired',
}

export default function RecentPoliciesTable({ policies }: { policies: RecentPolicy[] }) {
  if (!policies?.length) return null

  return (
    <div className="bg-surface rounded-lg border border-line shadow-elev-sm">
      <div className="px-4 py-3 border-b border-line">
        <h2 className="text-sm font-semibold text-ink">Recent Policies</h2>
      </div>
      <DualScrollTable>
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
            <tr>
              <th className="px-4 py-2 text-left">Policy #</th>
              <th className="px-4 py-2 text-left">Customer</th>
              <th className="px-4 py-2 text-left">Product</th>
              <th className="px-4 py-2 text-left">Status</th>
              <th className="px-4 py-2 text-left">Created</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {policies.map((p) => {
              const status = STATUS_MAP[p.status] ?? 'in-active'
              return (
                <tr key={p.id} className="hover:bg-surface-2">
                  <td className="px-4 py-2 font-mono text-primary">
                    <Link to={`/policies/${p.id}`} className="hover:underline">{p.policyNumber}</Link>
                  </td>
                  <td className="px-4 py-2 text-ink">{p.firstName} {p.lastName}</td>
                  <td className="px-4 py-2 text-ink">{p.product_name}</td>
                  <td className="px-4 py-2">
                    <StatusBadge status={status} />
                  </td>
                  <td className="px-4 py-2 text-ink-muted">
                    {fmtDate(p.created_at)}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </DualScrollTable>
    </div>
  )
}
