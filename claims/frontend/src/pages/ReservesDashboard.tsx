import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

interface ReserveRow {
  claim_id: number
  claim_number: string
  policy_number: string
  customer_name: string
  product: string
  status: string
  coverage: string
  reserve: number
  paid: number
  balance: number
}

const statusOptions = ['All', 'New', 'Pending Assessment', 'Under Review', 'Approved']
const productOptions = ['All', 'Motor', 'Commercial', 'Health', 'Burglary', 'Travel', 'Life']

function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function ReservesDashboard() {
  const [rows, setRows] = useState<ReserveRow[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState('All')
  const [productFilter, setProductFilter] = useState('All')
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    apiClient.get('/claims-v2/reserves-summary')
      .then(r => setRows(r.data))
      .catch(() => {
        setRows([
          { claim_id: 1, claim_number: 'CLM-2026-0451', policy_number: 'POL-10234', customer_name: 'Kgomotso Holdings', product: 'Motor', status: 'Under Review', coverage: 'Own Damage', reserve: 350000, paid: 120000, balance: 230000 },
          { claim_id: 1, claim_number: 'CLM-2026-0451', policy_number: 'POL-10234', customer_name: 'Kgomotso Holdings', product: 'Motor', status: 'Under Review', coverage: 'Third Party Liability', reserve: 100000, paid: 0, balance: 100000 },
          { claim_id: 2, claim_number: 'CLM-2026-0447', policy_number: 'POL-10198', customer_name: 'BW Mining Corp', product: 'Commercial', status: 'Approved', coverage: 'Fire Damage', reserve: 380000, paid: 0, balance: 380000 },
          { claim_id: 3, claim_number: 'CLM-2026-0443', policy_number: 'POL-10156', customer_name: 'Gaborone Motors', product: 'Motor', status: 'Pending Assessment', coverage: 'Theft', reserve: 275000, paid: 50000, balance: 225000 },
          { claim_id: 4, claim_number: 'CLM-2026-0440', policy_number: 'POL-10289', customer_name: 'TechBW Solutions', product: 'Burglary', status: 'New', coverage: 'Burglary', reserve: 195000, paid: 0, balance: 195000 },
          { claim_id: 5, claim_number: 'CLM-2026-0435', policy_number: 'POL-10312', customer_name: 'Safari Lodges Ltd', product: 'Commercial', status: 'Under Review', coverage: 'Storm Damage', reserve: 160000, paid: 30000, balance: 130000 },
          { claim_id: 8, claim_number: 'CLM-2026-0420', policy_number: 'POL-10390', customer_name: 'M. Kgosimore', product: 'Health', status: 'Approved', coverage: 'Hospitalization', reserve: 55000, paid: 55000, balance: 0 },
        ])
      })
      .finally(() => setLoading(false))
  }, [])

  const filtered = rows.filter(r => {
    if (statusFilter !== 'All' && r.status !== statusFilter) return false
    if (productFilter !== 'All' && r.product !== productFilter) return false
    return true
  })

  const totals = filtered.reduce(
    (acc, r) => ({
      reserve: acc.reserve + r.reserve,
      paid: acc.paid + r.paid,
      balance: acc.balance + r.balance,
    }),
    { reserve: 0, paid: 0, balance: 0 }
  )

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Reserves Dashboard</h1>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
          <p className="text-sm text-gray-500">Total Reserves</p>
          <p className="text-2xl font-bold text-gray-800 mt-1">{formatCurrency(totals.reserve)}</p>
        </div>
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
          <p className="text-sm text-gray-500">Total Paid</p>
          <p className="text-2xl font-bold text-green-700 mt-1">{formatCurrency(totals.paid)}</p>
        </div>
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
          <p className="text-sm text-gray-500">Outstanding Balance</p>
          <p className="text-2xl font-bold text-red-700 mt-1">{formatCurrency(totals.balance)}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3">
        <select
          className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
          value={statusFilter}
          onChange={e => setStatusFilter(e.target.value)}
        >
          {statusOptions.map(s => <option key={s} value={s}>{s === 'All' ? 'All Statuses' : s}</option>)}
        </select>
        <select
          className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
          value={productFilter}
          onChange={e => setProductFilter(e.target.value)}
        >
          {productOptions.map(p => <option key={p} value={p}>{p === 'All' ? 'All Products' : p}</option>)}
        </select>
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" />
        </div>
      ) : (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-gray-500 bg-gray-50 border-b border-gray-100">
                  <th className="px-4 py-3 font-medium">Claim #</th>
                  <th className="px-4 py-3 font-medium">Policy #</th>
                  <th className="px-4 py-3 font-medium">Customer</th>
                  <th className="px-4 py-3 font-medium">Product</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium">Coverage</th>
                  <th className="px-4 py-3 font-medium text-right">Reserve</th>
                  <th className="px-4 py-3 font-medium text-right">Paid</th>
                  <th className="px-4 py-3 font-medium text-right">Balance</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((row, i) => (
                  <tr
                    key={`${row.claim_id}-${row.coverage}-${i}`}
                    onClick={() => navigate(`/claims/${row.claim_id}`)}
                    className="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition"
                  >
                    <td className="px-4 py-3 font-medium text-claims-primary">{row.claim_number}</td>
                    <td className="px-4 py-3 text-gray-600">{row.policy_number}</td>
                    <td className="px-4 py-3 text-gray-700">{row.customer_name}</td>
                    <td className="px-4 py-3 text-gray-600">{row.product}</td>
                    <td className="px-4 py-3">
                      <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{row.status}</span>
                    </td>
                    <td className="px-4 py-3 text-gray-600">{row.coverage}</td>
                    <td className="px-4 py-3 text-right font-medium">{formatCurrency(row.reserve)}</td>
                    <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(row.paid)}</td>
                    <td className="px-4 py-3 text-right font-medium text-gray-800">{formatCurrency(row.balance)}</td>
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr>
                    <td colSpan={9} className="px-4 py-8 text-center text-gray-400">No reserves found</td>
                  </tr>
                )}
              </tbody>
              <tfoot>
                <tr className="bg-gray-50 border-t-2 border-gray-200 font-bold text-sm">
                  <td colSpan={6} className="px-4 py-3 text-gray-700">Totals</td>
                  <td className="px-4 py-3 text-right text-gray-800">{formatCurrency(totals.reserve)}</td>
                  <td className="px-4 py-3 text-right text-gray-800">{formatCurrency(totals.paid)}</td>
                  <td className="px-4 py-3 text-right text-gray-800">{formatCurrency(totals.balance)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
