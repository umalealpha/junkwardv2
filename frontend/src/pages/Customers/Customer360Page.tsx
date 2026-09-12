import DualScrollTable from '../../components/common/DualScrollTable'
import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { fetchCustomer360, updateCustomer } from '../../api/customer360'
import type {
  Customer360Data,
  Customer360Policy,
  Customer360Payment,
  Customer360Claim,
  RiskCategory,
} from '../../api/customer360'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { useMyProfile } from '../../hooks/useMyProfile'

type TabKey = 'policies' | 'payments' | 'claims'

const TABS: { key: TabKey; label: string }[] = [
  { key: 'policies', label: 'Policies' },
  { key: 'payments', label: 'Payments' },
  { key: 'claims', label: 'Claims' },
]

function StatusBadge({ label, variant }: { label: string; variant: 'green' | 'red' | 'yellow' | 'blue' | 'gray' }) {
  const colors = {
    green: 'bg-green-100 text-green-700',
    red: 'bg-red-100 text-red-700',
    yellow: 'bg-yellow-100 text-yellow-700',
    blue: 'bg-blue-100 text-blue-700',
    gray: 'bg-gray-100 text-gray-600',
  }
  return (
    <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[variant]}`}>
      {label}
    </span>
  )
}

function kycVariant(status: string): 'green' | 'red' | 'yellow' | 'gray' {
  const s = status.toLowerCase()
  if (s === 'compliant' || s === 'verified') return 'green'
  if (s === 'non-compliant' || s === 'failed') return 'red'
  if (s === 'pending') return 'yellow'
  return 'gray'
}

function amlVariant(status: string): 'green' | 'red' | 'yellow' | 'gray' {
  const s = status.toLowerCase()
  if (s === 'cleared' || s === 'clear') return 'green'
  if (s === 'flagged' || s === 'blocked') return 'red'
  if (s === 'pending') return 'yellow'
  return 'gray'
}

function riskVariant(score: string): 'green' | 'red' | 'yellow' | 'gray' {
  const s = score.toLowerCase()
  if (s === 'low') return 'green'
  if (s === 'high') return 'red'
  if (s === 'medium') return 'yellow'
  return 'gray'
}

const RISK_CATEGORY_LABELS: Record<RiskCategory, string> = { low: 'Low Risk', medium: 'Medium Risk', high: 'High Risk' }

function riskCategoryVariant(category: RiskCategory | null): 'green' | 'red' | 'yellow' | 'gray' {
  if (category === 'low') return 'green'
  if (category === 'high') return 'red'
  if (category === 'medium') return 'yellow'
  return 'gray'
}

function policyStatusVariant(status: string): 'green' | 'red' | 'yellow' | 'gray' {
  const s = status.toLowerCase()
  if (s === 'active') return 'green'
  if (s === 'cancelled' || s === 'expired') return 'red'
  if (s === 'pending' || s === 'in-active') return 'yellow'
  return 'gray'
}

function paymentStatusVariant(status: string): 'green' | 'red' | 'yellow' | 'gray' {
  const s = status.toLowerCase()
  if (s === 'success' || s === 'paid') return 'green'
  if (s === 'failed' || s === 'rejected') return 'red'
  if (s === 'pending') return 'yellow'
  return 'gray'
}

function claimStatusVariant(status: string): 'green' | 'red' | 'yellow' | 'blue' | 'gray' {
  const s = status.toLowerCase()
  if (s === 'approved' || s === 'settled') return 'green'
  if (s === 'rejected' || s === 'declined') return 'red'
  if (s === 'pending') return 'yellow'
  if (s === 'in progress' || s === 'processing') return 'blue'
  return 'gray'
}

export default function Customer360Page() {
  const { id } = useParams<{ id: string }>()
  const customerId = Number(id)
  const [activeTab, setActiveTab] = useState<TabKey>('policies')

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['customer360', customerId],
    queryFn: () => fetchCustomer360(customerId),
    enabled: !!customerId && !isNaN(customerId),
    staleTime: 2 * 60 * 1000,
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  if (isError) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
          <p className="text-red-600 font-medium">Failed to load customer data</p>
          <p className="text-red-500 text-sm mt-1">{(error as Error)?.message ?? 'Unknown error'}</p>
          <Link to="/customers" className="inline-block mt-4 text-sm text-blue-600 hover:underline">
            Back to Customers
          </Link>
        </div>
      </div>
    )
  }

  if (!data) return null

  return (
    <div className="p-6 space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-gray-500">
        <Link to="/customers" className="hover:text-blue-600">Customers</Link>
        <span>/</span>
        <span className="text-gray-800 font-medium">{data.customer.name}</span>
      </div>

      {/* Customer Header Card */}
      <CustomerHeader data={data} customerId={customerId} />

      {/* Stat Cards */}
      <StatCards data={data} />

      {/* Tabs */}
      <div className="bg-white rounded-lg border border-gray-200">
        <div className="border-b border-gray-200">
          <nav className="flex -mb-px">
            {TABS.map((tab) => (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={`px-6 py-3 text-sm font-medium border-b-2 transition ${
                  activeTab === tab.key
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </nav>
        </div>

        <div className="p-4">
          {activeTab === 'policies' && <PoliciesTable policies={data.policies} />}
          {activeTab === 'payments' && <PaymentsTable payments={data.recent_payments} />}
          {activeTab === 'claims' && <ClaimsTable claims={data.claims} />}
        </div>
      </div>
    </div>
  )
}

/* ── Customer Header ───────────────────────────────────────── */

function CustomerHeader({ data, customerId }: { data: Customer360Data; customerId: number }) {
  const { customer, kyc_status, aml_status, risk_score, risk_category, risk_category_reason, is_blocked, block_reason } = data
  const [editing, setEditing] = useState(false)
  const [form, setForm] = useState({ name: customer.name, phone: customer.phone, email: customer.email, id_number: customer.id_number })
  const qc = useQueryClient()
  const { data: myProfile } = useMyProfile()
  const canEdit = !!myProfile?.permissions?.some((perm: string) => perm === 'customer-edit' || perm === 'customer-kyc-edit')

  const mutation = useMutation({
    mutationFn: (payload: Record<string, any>) => updateCustomer(customerId, payload),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['customer360', customerId] }); setEditing(false); setEditingRisk(false) },
  })

  function handleSave() {
    const [firstName, ...rest] = form.name.split(' ')
    mutation.mutate({ firstName, lastName: rest.join(' '), email: form.email, cellphone: form.phone, omang: form.id_number })
  }

  const [editingRisk, setEditingRisk] = useState(false)
  const [riskForm, setRiskForm] = useState({ riskCategory: risk_category ?? '', riskCategoryReason: risk_category_reason ?? '' })

  function handleSaveRisk() {
    mutation.mutate({
      riskCategory: riskForm.riskCategory || null,
      riskCategoryReason: riskForm.riskCategoryReason || null,
    })
  }

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xl font-bold flex-shrink-0">
            {customer.name.charAt(0).toUpperCase()}
          </div>
          {editing ? (
            <div className="space-y-2">
              <input value={form.name} onChange={e => setForm(p => ({...p, name: e.target.value}))}
                className="px-2 py-1 border rounded text-sm font-bold" placeholder="Full Name" />
              <div className="flex gap-2">
                <input value={form.id_number} onChange={e => setForm(p => ({...p, id_number: e.target.value}))}
                  className="px-2 py-1 border rounded text-xs w-28" placeholder="ID Number" />
                <input value={form.phone} onChange={e => setForm(p => ({...p, phone: e.target.value}))}
                  className="px-2 py-1 border rounded text-xs w-28" placeholder="Phone" />
                <input value={form.email} onChange={e => setForm(p => ({...p, email: e.target.value}))}
                  className="px-2 py-1 border rounded text-xs w-48" placeholder="Email" />
              </div>
              <div className="flex gap-1">
                <button onClick={handleSave} disabled={mutation.isPending}
                  className="px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 disabled:opacity-50">Save</button>
                <button onClick={() => setEditing(false)}
                  className="px-3 py-1 bg-gray-200 text-gray-600 rounded text-xs hover:bg-gray-300">Cancel</button>
              </div>
            </div>
          ) : (
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-bold text-gray-800">{customer.name}</h1>
                <button onClick={() => setEditing(true)} className="text-xs text-blue-500 hover:underline">Edit</button>
              </div>
              <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                {customer.id_number && <span>ID: {customer.id_number}</span>}
                {customer.phone && <span>Tel: {customer.phone}</span>}
                {customer.email && <span>{customer.email}</span>}
              </div>
            </div>
          )}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <StatusBadge label={`KYC: ${kyc_status}`} variant={kycVariant(kyc_status)} />
          <StatusBadge label={`AML: ${aml_status}`} variant={amlVariant(aml_status)} />
          <StatusBadge label={`Risk: ${risk_score}`} variant={riskVariant(risk_score)} />
          <span title={risk_category_reason ?? undefined}>
            <StatusBadge
              label={`Category: ${risk_category ? RISK_CATEGORY_LABELS[risk_category] : 'Not Assessed'}`}
              variant={riskCategoryVariant(risk_category)}
            />
          </span>
          {is_blocked && (
            <span title={block_reason ?? undefined}>
              <StatusBadge label="Blocked" variant="red" />
            </span>
          )}
          {canEdit && !editingRisk && (
            <button onClick={() => { setRiskForm({ riskCategory: risk_category ?? '', riskCategoryReason: risk_category_reason ?? '' }); setEditingRisk(true) }}
              className="text-xs text-blue-500 hover:underline">Edit Risk Category</button>
          )}
        </div>
      </div>

      {editingRisk && (
        <div className="mt-4 pt-4 border-t border-gray-100 space-y-2">
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Risk Category</label>
              <select value={riskForm.riskCategory} onChange={e => setRiskForm(p => ({ ...p, riskCategory: e.target.value }))}
                className="px-2 py-1 border rounded text-sm">
                <option value="">Not Assessed</option>
                <option value="low">Low Risk</option>
                <option value="medium">Medium Risk</option>
                <option value="high">High Risk</option>
              </select>
            </div>
            <div className="flex-1 min-w-[240px]">
              <label className="block text-xs font-medium text-gray-500 mb-1">Reason</label>
              <input value={riskForm.riskCategoryReason} onChange={e => setRiskForm(p => ({ ...p, riskCategoryReason: e.target.value }))}
                className="px-2 py-1 border rounded text-sm w-full" placeholder="Reason for this classification" />
            </div>
            <div className="flex gap-1">
              <button onClick={handleSaveRisk} disabled={mutation.isPending}
                className="px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 disabled:opacity-50">Save</button>
              <button onClick={() => setEditingRisk(false)}
                className="px-3 py-1 bg-gray-200 text-gray-600 rounded text-xs hover:bg-gray-300">Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

/* ── Stat Cards ────────────────────────────────────────────── */

function StatCards({ data }: { data: Customer360Data }) {
  const stats = [
    { label: 'Active Policies', value: data.policy_summary.active, color: 'text-green-600' },
    { label: 'Total Premium', value: data.policy_summary.total_premium, color: 'text-blue-600' },
    { label: 'Total Paid', value: data.payment_summary.total_paid, color: 'text-indigo-600' },
    { label: 'Open Claims', value: data.claim_summary.open, color: 'text-orange-600' },
  ]

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {stats.map((stat) => (
        <div key={stat.label} className="bg-white rounded-lg border border-gray-200 p-5">
          <p className="text-sm text-gray-500">{stat.label}</p>
          <p className={`text-2xl font-bold mt-1 ${stat.color}`}>{stat.value}</p>
        </div>
      ))}
    </div>
  )
}

/* ── Policies Table ────────────────────────────────────────── */

function PoliciesTable({ policies }: { policies: Customer360Policy[] }) {
  if (policies.length === 0) {
    return <EmptyState message="No policies found for this customer." />
  }

  return (
    <DualScrollTable>
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 text-left text-gray-500 text-xs uppercase tracking-wider">
            <th className="px-4 py-3 font-medium">Policy Number</th>
            <th className="px-4 py-3 font-medium">Product</th>
            <th className="px-4 py-3 font-medium">Status</th>
            <th className="px-4 py-3 font-medium text-right">Premium</th>
            <th className="px-4 py-3 font-medium">Start Date</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {policies.map((p) => (
            <tr key={p.id} className="hover:bg-gray-50">
              <td className="px-4 py-3">
                <Link to={`/policies/${p.id}`} className="text-blue-600 hover:underline font-medium">
                  {p.policy_number}
                </Link>
              </td>
              <td className="px-4 py-3 text-gray-700">{p.product_name}</td>
              <td className="px-4 py-3">
                <StatusBadge label={p.status_label} variant={policyStatusVariant(p.status_label)} />
              </td>
              <td className="px-4 py-3 text-right text-gray-700">P {Number(p.premium).toLocaleString()}</td>
              <td className="px-4 py-3 text-gray-500">{p.start_date}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </DualScrollTable>
  )
}

/* ── Payments Table ────────────────────────────────────────── */

function PaymentsTable({ payments }: { payments: Customer360Payment[] }) {
  if (payments.length === 0) {
    return <EmptyState message="No recent payments found." />
  }

  return (
    <DualScrollTable>
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 text-left text-gray-500 text-xs uppercase tracking-wider">
            <th className="px-4 py-3 font-medium">Policy Number</th>
            <th className="px-4 py-3 font-medium text-right">Amount</th>
            <th className="px-4 py-3 font-medium">Status</th>
            <th className="px-4 py-3 font-medium">Date</th>
            <th className="px-4 py-3 font-medium">Method</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {payments.map((p, idx) => (
            <tr key={idx} className="hover:bg-gray-50">
              <td className="px-4 py-3 text-gray-700">{p.policy_number}</td>
              <td className="px-4 py-3 text-right text-gray-700">P {Number(p.amount).toLocaleString()}</td>
              <td className="px-4 py-3">
                <StatusBadge label={p.status} variant={paymentStatusVariant(p.status)} />
              </td>
              <td className="px-4 py-3 text-gray-500">{p.date}</td>
              <td className="px-4 py-3 text-gray-500">{p.method}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </DualScrollTable>
  )
}

/* ── Claims Table ──────────────────────────────────────────── */

function ClaimsTable({ claims }: { claims: Customer360Claim[] }) {
  if (claims.length === 0) {
    return <EmptyState message="No claims found for this customer." />
  }

  return (
    <DualScrollTable>
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 text-left text-gray-500 text-xs uppercase tracking-wider">
            <th className="px-4 py-3 font-medium">Claim Number</th>
            <th className="px-4 py-3 font-medium">Type</th>
            <th className="px-4 py-3 font-medium">Status</th>
            <th className="px-4 py-3 font-medium">Date</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {claims.map((c, idx) => (
            <tr key={idx} className="hover:bg-gray-50">
              <td className="px-4 py-3 font-medium text-gray-700">{c.claim_number}</td>
              <td className="px-4 py-3 text-gray-700">{c.claim_type}</td>
              <td className="px-4 py-3">
                <StatusBadge label={c.status} variant={claimStatusVariant(c.status)} />
              </td>
              <td className="px-4 py-3 text-gray-500">{c.created_at}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </DualScrollTable>
  )
}

/* ── Empty State ───────────────────────────────────────────── */

function EmptyState({ message }: { message: string }) {
  return (
    <div className="py-12 text-center text-gray-400 text-sm">
      {message}
    </div>
  )
}
